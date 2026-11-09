<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One proof file of a payment or refund (transfer slip, InstaPay screenshot, PDF); a payment can have several.
 *
 * Private: kept on the "local" disk (storage/app/private/payment-receipts), never under public/, and opened only
 * through Admin\BookingController::receipt (staff who can see the booking).
 */
class PaymentReceipt extends Model
{
    public const DISK = 'local';

    public const DIR = 'payment-receipts';

    /** What the receipt field accepts (checked on the file's content, not only its name). */
    public const TYPES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    /** Files in one go. */
    public const MAX_FILES = 3;

    /** Largest receipt: 10 MB each (photos elsewhere in the panel stay at 5 MB). */
    public const MAX_MB = 10;

    protected $fillable = ['path', 'name', 'size'];

    protected static function booted(): void
    {
        static::deleted(fn (PaymentReceipt $receipt) => $receipt->deleteFile());
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Save an uploaded file: payment-receipts/{booking reference}/{random}.{ext}. The extension comes from the file's
     * content, so a renamed file can't pass for an image. Returns the attributes of a new receipt (not yet saved).
     *
     * @return array{path: string, name: string, size: int}
     */
    public static function store(UploadedFile $file, Booking $booking): array
    {
        $path = $file->storeAs(self::DIR.'/'.Str::slug($booking->reference), Str::random(40).'.'.$file->extension(), self::DISK);

        return [
            'path' => $path,
            // Shown in the list and used as the download name; no folders or control characters
            'name' => Str::limit(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename($file->getClientOriginalName())) ?: 'receipt.'.$file->extension(), 180, ''),
            'size' => (int) $file->getSize(),
        ];
    }

    /** MAX_MB in bytes, or less when PHP's own upload limits are lower. */
    public static function maxBytes(): int
    {
        return \App\Support\MediaLibrary::cappedByPhp(self::MAX_MB * 1024 ** 2);
    }

    /** maxBytes() for people: "10 MB". */
    public static function maxLabel(): string
    {
        return \App\Support\MediaLibrary::megabytes(self::maxBytes());
    }

    public function fileExists(): bool
    {
        return Storage::disk(self::DISK)->exists($this->path);
    }

    public function isImage(): bool
    {
        return in_array(strtolower(pathinfo($this->path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    /** Only files inside payment-receipts/ are ever deleted. */
    public function deleteFile(): void
    {
        if (str_starts_with((string) $this->path, self::DIR.'/')) {
            Storage::disk(self::DISK)->delete($this->path);
        }
    }
}
