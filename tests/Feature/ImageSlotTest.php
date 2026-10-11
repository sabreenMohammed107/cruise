<?php

namespace Tests\Feature;

use App\Models\ImageSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slot resolution: uploaded photo → official AROYA file → stock photo → local SVG (data.js slot()).
 */
class ImageSlotTest extends TestCase
{
    use RefreshDatabase;

    private function slot(array $attributes = []): ImageSlot
    {
        return ImageSlot::create($attributes + [
            'key' => 'test-slot-'.uniqid(),
            'label' => 'Test slot',
            'stock_url' => 'https://images.pexels.com/photos/1/pexels-photo-1.jpeg',
            'fallback_path' => 'assets/img/ship-sea.svg',
        ]);
    }

    public function test_empty_slot_uses_the_stock_photo_then_the_svg(): void
    {
        $slot = $this->slot();

        $this->assertFalse($slot->isFilled());
        $this->assertSame('https://images.pexels.com/photos/1/pexels-photo-1.jpeg', $slot->url());
        $this->assertSame([$slot->stock_url, asset('assets/img/ship-sea.svg')], $slot->candidates());
    }

    public function test_slot_without_stock_photo_falls_back_to_the_svg(): void
    {
        $this->assertSame(asset('assets/img/ship-sea.svg'), $this->slot(['stock_url' => null])->url());
    }

    public function test_official_photo_dropped_in_public_folder_wins_over_stock(): void
    {
        $slot = $this->slot();
        $file = public_path($slot->officialPath());
        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, 'jpg');

        try {
            $this->assertTrue($slot->isFilled());
            $this->assertSame(asset($slot->officialPath()), $slot->url());
            $this->assertCount(3, $slot->candidates());
        } finally {
            unlink($file);
        }
    }

    public function test_uploaded_photo_wins_over_everything(): void
    {
        $path = 'uploads/image-slots/test-'.uniqid().'.jpg';
        @mkdir(public_path('uploads/image-slots'), 0777, true);
        file_put_contents(public_path($path), 'jpg');
        $slot = $this->slot(['path' => $path]);

        try {
            $this->assertTrue($slot->isFilled());
            $this->assertSame(asset($path), $slot->url());
        } finally {
            unlink(public_path($path));
        }
    }

    public function test_missing_uploaded_file_is_ignored(): void
    {
        $slot = $this->slot(['path' => 'uploads/image-slots/deleted.jpg']);

        $this->assertFalse($slot->isFilled());
        $this->assertSame($slot->stock_url, $slot->url());
    }

    public function test_urls_resolves_many_slots_in_one_call(): void
    {
        $a = $this->slot();
        $b = $this->slot(['stock_url' => null]);

        $this->assertSame(
            [$a->key => $a->stock_url, $b->key => asset('assets/img/ship-sea.svg')],
            ImageSlot::urls([$a->key, $b->key, null, $a->key]),
        );
    }
}
