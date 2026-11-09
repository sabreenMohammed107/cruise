<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin → Bookings → Record a payment or refund: optional receipts (several photos or PDFs per payment), kept private
 * and opened from the payment history by staff who can see the booking.
 */
class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** A real 1×1 PNG (no GD needed). */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Storage::fake(PaymentReceipt::DISK);
        $this->booking = Booking::where('reference', 'SC2611-K4P2Q')->firstOrFail(); // $4,155 due
    }

    private function staff(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /**
     * A real upload of a temporary file: its type is read from the content, as for a browser upload (Laravel's
     * UploadedFile::fake() takes it from the name, so a renamed file would pass for what its name says).
     */
    private function upload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rcpt');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function png(string $name = 'instapay.png'): UploadedFile
    {
        return $this->upload($name, base64_decode(self::PNG));
    }

    private function pdf(string $name = 'Bank slip.pdf'): UploadedFile
    {
        return $this->upload($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    }

    private function record(array $extra = [])
    {
        return $this->post(route('admin.bookings.payments.store', $this->booking), $extra + [
            'type' => 'payment', 'method' => 'bank_transfer', 'amount' => 1000, 'received_at' => today()->toDateString(), 'reference' => 'TRX-881',
        ]);
    }

    private function lastPayment(): Payment
    {
        return $this->booking->payments()->latest('id')->firstOrFail();
    }

    public function test_the_form_takes_several_files(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin')
            ->get(route('admin.bookings.show', $this->booking))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="receipts[]" type="file" multiple', false)
            ->assertSeeInOrder(['name="reference"', 'name="notes"', 'name="receipts[]"'], false)
            ->assertSee('Payment receipts / proof')
            ->assertSee('data-max-files="10"', false);
    }

    public function test_a_payment_with_several_receipts(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');

        $this->record(['receipts' => [$this->png(), $this->pdf(), $this->png('second photo.png')]])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Payment of $1,000.00 recorded with 3 receipts.');

        // One payment (totals as before), three receipts in the order chosen, each its own private file
        $this->assertSame(PaymentStatus::Partial, $this->booking->fresh()->payment_status);
        $payment = $this->lastPayment();
        $receipts = $payment->receipts;
        $this->assertSame(['instapay.png', 'Bank slip.pdf', 'second photo.png'], $receipts->pluck('name')->all());
        foreach ($receipts as $r) {
            $this->assertMatchesRegularExpression('#^payment-receipts/sc2611-k4p2q/[A-Za-z0-9]{40}\.(png|pdf)$#', $r->path);
            Storage::disk(PaymentReceipt::DISK)->assertExists($r->path);
            $this->assertFileDoesNotExist(public_path($r->path));
        }
        $this->assertCount(3, array_unique($receipts->pluck('path')->all()));

        // In the payment history: every receipt with its own view and download links; thumbnails for photos only
        $page = $this->get(route('admin.bookings.show', $this->booking))->assertSee('data-receipts', false);
        foreach ($receipts as $r) {
            $url = route('admin.bookings.payments.receipt', [$this->booking, $payment, $r]);
            $page->assertSee($r->name)->assertSee($url.'?download=1', false);
            $r->isImage() ? $page->assertSee('src="'.$url.'"', false) : $page->assertDontSee('src="'.$url.'"', false);
        }

        // A photo opens as an image (sandboxed), the PDF inline as a PDF; downloads under their own names
        [$photo, $pdf] = [$receipts[0], $receipts[1]];
        $this->get(route('admin.bookings.payments.receipt', [$this->booking, $payment, $photo]))->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', 'sandbox');
        $response = $this->get(route('admin.bookings.payments.receipt', [$this->booking, $payment, $pdf]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $download = $this->get(route('admin.bookings.payments.receipt', [$this->booking, $payment, $pdf]).'?download=1');
        $this->assertStringStartsWith('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Bank slip.pdf', $download->headers->get('Content-Disposition'));
    }

    public function test_one_receipt_and_none(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');

        $this->record(['type' => 'refund', 'receipts' => [$this->pdf()]])->assertSessionHas('status', 'Refund of $1,000.00 recorded with 1 receipt.');
        $this->assertCount(1, $this->lastPayment()->receipts);

        $this->record()->assertSessionHasNoErrors()->assertSessionHas('status', 'Payment of $1,000.00 recorded.');
        $this->assertCount(0, $this->lastPayment()->receipts);
    }

    public function test_only_photos_and_pdfs_within_the_limits_are_accepted(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');
        $payments = Payment::count();
        $html = '<html><body><script>alert(1)</script></body></html>';

        // One bad file among good ones refuses the whole entry, naming the file's place in the list
        $this->record(['receipts' => [$this->png(), $this->upload('slip.pdf', $html)]])
            ->assertSessionHasErrors(['receipts.1' => 'Receipt 2 must be a photo (JPG, PNG, WebP) or a PDF.']);
        $this->record(['receipts' => [$this->upload('photo.png', $html)]])->assertSessionHasErrors('receipts.0');
        $this->record(['receipts' => [$this->upload('notes.txt', 'Paid in full')]])->assertSessionHasErrors('receipts.0');
        $this->record(['receipts' => [UploadedFile::fake()->create('scan.pdf', 6 * 1024, 'application/pdf')]])
            ->assertSessionHasErrors('receipts.0');
        // At most 10 at a time
        $this->record(['receipts' => array_map(fn ($i) => $this->png("p{$i}.png"), range(1, 11))])
            ->assertSessionHasErrors(['receipts' => 'Attach at most 10 receipts at a time.']);

        // Nothing recorded, nothing stored; the errors show under the field
        $this->assertSame($payments, Payment::count());
        $this->assertSame([], Storage::disk(PaymentReceipt::DISK)->allFiles());
        $this->record(['receipts' => [$this->upload('slip.pdf', $html)]]);
        $this->get(route('admin.bookings.show', $this->booking))->assertSee('Receipt 1 must be a photo (JPG, PNG, WebP) or a PDF.');
    }

    public function test_files_too_large_to_send_together_come_back_with_a_message(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');

        // What PHP does above post_max_size: an empty form with a huge Content-Length
        $this->call('POST', route('admin.bookings.payments.store', $this->booking), [], [], [], ['CONTENT_LENGTH' => PHP_INT_MAX])
            ->assertRedirect(route('admin.bookings.show', ['booking' => $this->booking->reference, 'receipts' => 'too-large']));
        $this->get(route('admin.bookings.show', ['booking' => $this->booking->reference, 'receipts' => 'too-large']))
            ->assertSee('data-receipts-too-large', false);
        $this->assertSame(0, Payment::where('reference', 'TRX-881')->count());
    }

    public function test_removing_the_entry_removes_its_receipts(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');
        $this->record(['receipts' => [$this->png(), $this->pdf()]]);
        $payment = $this->lastPayment();
        $paths = $payment->receipts->pluck('path')->all();

        $this->delete(route('admin.bookings.payments.destroy', [$this->booking, $payment]))->assertSessionHas('status');

        foreach ($paths as $path) {
            Storage::disk(PaymentReceipt::DISK)->assertMissing($path);
        }
        $this->assertSame(0, PaymentReceipt::count());
    }

    public function test_only_staff_who_can_see_the_booking_open_its_receipts(): void
    {
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');
        $this->record(['receipts' => [$this->png()]]);
        $payment = $this->lastPayment();
        $receipt = $payment->receipts->first();
        $url = route('admin.bookings.payments.receipt', [$this->booking, $payment, $receipt]);

        // Signed out of the panel, or a customer signed in on the website: sent to the panel sign-in
        auth('admin')->logout();
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs(User::where('email', 'reem@example.com')->firstOrFail(), 'web')->get($url)->assertRedirect(route('admin.login'));

        // A seller: only on bookings assigned to them, and they can attach receipts there too
        $seller = $this->staff('seller@safercruise.test');
        $this->actingAs($seller, 'admin')->get($url)->assertForbidden();
        $this->booking->forceFill(['seller_id' => $seller->id])->save();
        $this->get($url)->assertOk();
        $this->record(['receipts' => [$this->pdf(), $this->png()], 'amount' => 50])->assertSessionHasNoErrors();

        // A receipt can't be opened through another booking or another payment
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');
        $other = Booking::whereKeyNot($this->booking->id)->firstOrFail();
        $this->get(route('admin.bookings.payments.receipt', [$other, $payment, $receipt]))->assertNotFound();
        $this->get(route('admin.bookings.payments.receipt', [$this->booking, $this->lastPayment(), $receipt]))->assertNotFound();
    }
}
