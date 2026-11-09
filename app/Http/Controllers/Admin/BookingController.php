<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AgentTier;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Support\MediaLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Bookings come from the website only. The team follows up on payment (offline), records
 * payments and refunds here, and cancels bookings when needed.
 *
 * Admins and supervisors see every booking and assign them to sellers. A seller sees only the bookings
 * assigned to them, and can add notes and record payments on those (Gate "view-booking").
 */
class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
            'payment' => ['nullable', Rule::enum(PaymentStatus::class)],
            // "Assigned to": a staff member's id (admin, supervisor or seller), or "none" for unassigned bookings
            // (admins and supervisors; the parameter keeps its old name so existing links keep working)
            'seller' => ['nullable', Rule::in(['none', ...User::staff()->pluck('id')->map(fn ($id) => (string) $id)])],
            // B2C (customers and guest checkout) or agent bookings; for agents, a type ("none": no type) or one agent
            'channel' => ['nullable', Rule::in(['b2c', 'agent'])],
            'agent_tier' => ['nullable', Rule::in(['none', ...AgentTier::pluck('id')->map(fn ($id) => (string) $id)])],
            'agent' => ['nullable', 'integer', Rule::exists('bookings', 'agent_id')],
        ]);
        $user = $request->user();

        // Everything but the channel, so the B2C / Agents tabs can count within the other filters
        $filtered = fn () => Booking::query()
            ->when($user->isSeller(), fn (Builder $query) => $query->where('seller_id', $user->id))
            ->when($user->can('assign-bookings') ? ($filters['seller'] ?? null) : null, fn (Builder $query, string $seller) => $seller === 'none'
                ? $query->whereNull('seller_id')
                : $query->where('seller_id', $seller))
            ->when($filters['q'] ?? null, function (Builder $query, string $q) {
                $query->where(fn (Builder $w) => $w
                    ->where('reference', 'like', "%{$q}%")
                    ->orWhere('contact_email', 'like', "%{$q}%")
                    ->orWhere('contact_phone', 'like', "%{$q}%")
                    ->orWhereHas('travellers', fn (Builder $t) => $t->where('last_name', 'like', "%{$q}%")->orWhere('first_name', 'like', "%{$q}%")));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment'] ?? null, fn (Builder $query, string $payment) => $query->where('payment_status', $payment))
            ->when($filters['agent_tier'] ?? null, fn (Builder $query, string $tier) => $tier === 'none'
                ? $query->whereNotNull('agent_id')->whereNull('agent_tier_id')
                : $query->where('agent_tier_id', $tier))
            ->when($filters['agent'] ?? null, fn (Builder $query, int|string $agent) => $query->where('agent_id', $agent));

        $bookings = $filtered()
            ->with(['sailing.cruise', 'cabinCategory', 'seller', 'agent', 'agentTier'])
            ->when($filters['channel'] ?? null, fn (Builder $query, string $channel) => $query->channel($channel))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'all' => $filtered()->count(),
            'b2c' => $filtered()->channel('b2c')->count(),
            'agent' => $filtered()->channel('agent')->count(),
        ];

        return view('admin.bookings.index', [
            'bookings' => $bookings,
            'filters' => $filters,
            'counts' => $counts,
            'agentTiers' => AgentTier::options(),
            // Agents who have booked, for the "Agent" filter
            'agents' => User::whereIn('id', Booking::query()->whereNotNull('agent_id')->select('agent_id'))
                ->orderBy('company_name')->orderBy('name')->get(['id', 'name', 'company_name']),
        ]);
    }

    public function show(Booking $booking): View
    {
        Gate::authorize('view-booking', $booking);
        $booking->load(['sailing.cruise.ship', 'cabinCategory', 'cabins.cabinCategory', 'cabins.travellers', 'travellers', 'payments.recordedBy', 'payments.receipts', 'promoCode', 'user', 'seller', 'agent', 'agentTier']);

        return view('admin.bookings.show', [
            'booking' => $booking,
            'policy' => $this->cancellationRule($booking),
        ]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('view-booking', $booking);
        $booking->update($request->validate(['admin_notes' => ['nullable', 'string', 'max:5000']]));

        return back()->with('status', 'Notes saved.');
    }

    public function cancel(Booking $booking): RedirectResponse
    {
        abort_if($booking->status === BookingStatus::Cancelled, 409);

        $booking->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);
        $booking->syncPaymentTotals();

        return back()->with('status', "Booking {$booking->reference} cancelled. Record any refund below.");
    }

    /**
     * Assign the booking to a staff member, or unassign it (empty seller_id). Done by admins and supervisors; any
     * admin, supervisor or seller can be chosen (not customers, agents or the hidden super admin). The column is
     * still called seller_id: it holds whoever the booking is assigned to.
     */
    public function assignSeller(Request $request, Booking $booking): RedirectResponse
    {
        $staff = array_map(fn (UserRole $role) => $role->value, UserRole::staff());
        $data = $request->validate([
            'seller_id' => ['nullable', Rule::exists('users', 'id')->whereIn('role', $staff)],
        ], ['seller_id.exists' => 'Choose an admin, supervisor or seller.']);

        $booking->update(['seller_id' => $data['seller_id'] ?? null]);
        $seller = $booking->seller()->value('name');

        return back()->with('status', $seller
            ? "Booking {$booking->reference} assigned to {$seller}."
            : "Booking {$booking->reference} is no longer assigned to a seller.");
    }

    public function storePayment(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('view-booking', $booking);
        $data = $request->validate([
            'type' => ['required', Rule::enum(PaymentType::class)],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'received_at' => ['required', 'date', 'before_or_equal:now'],
            // Optional proof, several files: photos or PDFs (checked on the content), each within the upload limit
            'receipts' => ['nullable', 'array', 'max:'.PaymentReceipt::MAX_FILES],
            'receipts.*' => ['file', 'mimes:'.implode(',', PaymentReceipt::TYPES), 'max:'.intdiv(PaymentReceipt::maxBytes(), 1024)],
        ], [
            'receipts.max' => 'Attach at most '.PaymentReceipt::MAX_FILES.' receipts at a time.',
            'receipts.*.mimes' => 'Receipt :position must be a photo (JPG, PNG, WebP) or a PDF.',
            // "uploaded": PHP refused it before Laravel saw it, almost always because of its size
            'receipts.*.max' => $tooBig = 'Receipt :position is too large: the limit is '.PaymentReceipt::maxLabel().' per file. Send a smaller photo or PDF.',
            'receipts.*.uploaded' => $tooBig,
        ]);
        unset($data['receipts']);

        // Files first; if the entry can't be saved, they are removed again (no orphan files)
        $stored = [];
        try {
            foreach ($request->file('receipts', []) as $file) {
                $stored[] = PaymentReceipt::store($file, $booking);
            }

            DB::transaction(function () use ($booking, $data, $request, $stored) {
                $payment = $booking->payments()->create($data + [
                    'currency' => $booking->currency,
                    'recorded_by' => $request->user()->id,
                ]);
                $payment->receipts()->createMany($stored);
            });
        } catch (Throwable $e) {
            foreach ($stored as $receipt) {
                Storage::disk(PaymentReceipt::DISK)->delete($receipt['path']);
            }
            throw $e;
        }
        $booking->syncPaymentTotals();

        $what = $data['type'] === PaymentType::Refund->value ? 'Refund' : 'Payment';
        $with = match (count($stored)) {
            0 => '.',
            1 => ' with 1 receipt.',
            default => ' with '.count($stored).' receipts.',
        };

        return back()->with('status', "{$what} of ".config('cruise.currency_symbol').number_format($data['amount'], 2).' recorded'.$with);
    }

    /**
     * One receipt of a payment, for staff who can see the booking (sellers: their assigned bookings). Opens in the
     * browser (photos, PDF), or downloads with ?download=1 under the name it was uploaded with.
     */
    public function receipt(Request $request, Booking $booking, Payment $payment, PaymentReceipt $receipt): StreamedResponse
    {
        Gate::authorize('view-booking', $booking);
        abort_unless($payment->booking_id === $booking->id && $receipt->payment_id === $payment->id && $receipt->fileExists(), 404);

        $disk = Storage::disk(PaymentReceipt::DISK);
        $headers = [
            // The type found from the content when it was checked, never the browser's guess
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];
        // A photo never runs anything (not set on PDFs: Chrome's PDF viewer refuses sandboxed documents)
        if ($receipt->isImage()) {
            $headers['Content-Security-Policy'] = 'sandbox';
        }

        return $request->boolean('download')
            ? $disk->download($receipt->path, $receipt->name, $headers)
            : $disk->response($receipt->path, $receipt->name, $headers);
    }

    public function destroyPayment(Booking $booking, Payment $payment): RedirectResponse
    {
        abort_unless($payment->booking_id === $booking->id, 404);

        // Its receipt files go with it (Payment::booted)
        $payment->delete();
        $booking->syncPaymentTotals();

        return back()->with('status', 'Payment entry removed.');
    }

    /**
     * Which line of the cancellation policy (config/cruise.php) applies today.
     */
    private function cancellationRule(Booking $booking): array
    {
        $days = (int) today()->diffInDays($booking->sailing->departure_date, false);
        $rows = config('cruise.terms.cancellation');

        $row = match (true) {
            $days >= 121 => $rows[0],
            $days >= 61 => $rows[1],
            $days >= 15 => $rows[2],
            default => $rows[3],
        };

        return ['days' => $days, 'when' => $row[0], 'penalty' => $row[1]];
    }
}
