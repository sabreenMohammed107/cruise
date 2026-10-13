<?php

namespace Tests\Feature;

use App\Models\Cruise;
use App\Models\Destination;
use App\Models\ImageSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Photo slots: adding / deleting slots, and direct uploads from gallery and photo fields. */
class PhotoSlotsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin');
    }

    protected function tearDown(): void
    {
        // Remove the files the tests uploaded to public/uploads/image-slots
        foreach (ImageSlot::whereNotNull('path')->pluck('path') as $path) {
            if (is_file(public_path($path))) {
                unlink(public_path($path));
            }
        }
        parent::tearDown();
    }

    private function cruiseForm(Cruise $cruise, array $changes = []): array
    {
        return array_merge($cruise->only(['name', 'name_ar', 'slug', 'ship_id', 'nights', 'departure_port', 'arrival_port', 'price_factor', 'summary', 'summary_ar', 'image_slot']), [
            'route' => implode("\n", $cruise->route), 'tags' => $cruise->tags, 'is_active' => 1,
            'gallery_slots' => $cruise->gallery_slots,
            'itinerary' => $cruise->itinerary->map(fn ($d) => ['port' => $d->port, 'time' => $d->time_label, 'text' => $d->description])->all(),
        ], $changes);
    }

    public function test_add_a_slot_and_use_it(): void
    {
        $this->get('/admin/image-slots')->assertOk()->assertSee('Add a photo slot')->assertSee('Used by:');

        $this->post('/admin/image-slots', ['label' => 'Kids club, morning', 'photo' => UploadedFile::fake()->image('kids.jpg', 1600, 1000)])
            ->assertRedirect()->assertSessionHasNoErrors();

        $slot = ImageSlot::where('label', 'Kids club, morning')->firstOrFail();
        $this->assertSame('kids-club-morning', $slot->key);
        $this->assertTrue($slot->is_custom);
        $this->assertFileExists(public_path($slot->path));
        $this->get('/admin/image-slots')->assertSee('Added by you')->assertSee('Not used on any page yet');

        // Same key again gets a suffix when made from the name, an error when typed
        $this->post('/admin/image-slots', ['label' => 'Kids club, morning', 'photo' => UploadedFile::fake()->image('k.jpg')]);
        $this->assertSame(2, ImageSlot::where('key', 'like', 'kids-club-morning%')->count());
        $this->post('/admin/image-slots', ['label' => 'X', 'key' => 'kids-club-morning', 'photo' => UploadedFile::fake()->image('k.jpg')])
            ->assertSessionHasErrors('key');
        $this->post('/admin/image-slots', ['label' => 'No photo'])->assertSessionHasErrors('photo');
        $this->post('/admin/image-slots', ['label' => 'Pdf', 'photo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');

        // It can be chosen in any photo field, from the media library
        $this->get('/admin/destinations/'.Destination::first()->id.'/edit')->assertSee('Choose from library');
        $this->assertContains('kids-club-morning', $this->getJson('/admin/media-library?q=kids club')->assertOk()->json('items.*.value'));
    }

    public function test_media_library_replaces_the_slot_dropdowns(): void
    {
        $cruise = Cruise::where('slug', 'aroya-repositioning')->firstOrFail();
        $page = $this->get("/admin/cruises/{$cruise->id}/edit")->assertOk();

        $page->assertDontSee('— none —')->assertDontSee('— No image —');
        $this->assertSame(1, substr_count($page->getContent(), 'id="mediaLibrary"'), 'one shared library modal');
        foreach ($cruise->gallery_slots as $key) {
            $page->assertSee('name="gallery_slots[]" value="'.$key.'"', false);
        }

        // Newest first, paged, every slot with a picture is listed
        $first = $this->getJson('/admin/media-library')->assertOk()->json();
        $this->assertCount(min(24, $first['total']), $first['items']);
        $this->assertSame($first['total'] > 24, $first['has_more']);
    }

    public function test_pick_a_loose_upload_and_reorder_the_gallery(): void
    {
        @mkdir(public_path('uploads/home'), 0777, true);
        $file = public_path('uploads/home/test-library-'.uniqid().'.jpg');
        $fake = UploadedFile::fake()->image('x.jpg', 40, 30);
        copy($fake->getPathname(), $file);
        $value = 'file:home/'.basename($file);

        try {
            $this->getJson('/admin/media-library?q='.basename($file))->assertJsonPath('items.0.value', $value);

            $cruise = Cruise::where('slug', 'aroya-repositioning')->firstOrFail();
            [$a, $b] = $cruise->gallery_slots;
            $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise, ['gallery_slots' => [$b, $value, $a], 'image_slot' => $value]))
                ->assertSessionHasNoErrors();

            $gallery = $cruise->fresh()->gallery_slots;
            $this->assertSame([$b, $a], [$gallery[0], $gallery[2]]);
            $copy = ImageSlot::where('key', $gallery[1])->firstOrFail();
            $this->assertTrue($copy->is_custom);
            $this->assertSame('AROYA – Repositioning – Gallery 2', $copy->label);
            $this->assertFileExists(public_path($copy->path));
            $this->assertFileExists($file); // the original stays where it was
            $this->assertNotSame($copy->key, $cruise->fresh()->image_slot);
            $this->assertTrue(ImageSlot::where('key', $cruise->fresh()->image_slot)->exists());

            // Unknown picks are rejected
            $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise->fresh(), ['gallery_slots' => ['no-such-slot', 'file:../.env']]))
                ->assertSessionHasErrors(['gallery_slots.0', 'gallery_slots.1']);
        } finally {
            @unlink($file);
        }
    }

    public function test_every_slot_has_replace_and_delete(): void
    {
        $page = $this->get('/admin/image-slots')->assertOk();
        $count = ImageSlot::count();
        $this->assertSame($count, substr_count($page->getContent(), '>Replace photo</button>'));
        $this->assertSame($count, substr_count($page->getContent(), '>Delete slot</button>'));
        $page->assertDontSee('>Upload</button>', false);

        // Built-in slots: replace works and can be deleted once nothing uses them
        $this->post('/admin/image-slots/cabin-suite-3', ['photo' => UploadedFile::fake()->image('s.jpg')])->assertSessionHasNoErrors();
        $this->assertTrue(ImageSlot::where('key', 'cabin-suite-3')->first()->isFilled());

        $this->delete('/admin/image-slots/cabin-suite-3/slot')->assertSessionHas('error', fn ($m) => str_contains($m, 'Cabin “Superior Suite Khuzama”'));
        $suite = \App\Models\CabinCategory::where('slug', 'suite')->firstOrFail();
        $suite->update(['image_slots' => ['cabin-suite-1', 'cabin-suite-2']]);
        $file = public_path(ImageSlot::where('key', 'cabin-suite-3')->value('path'));
        $this->delete('/admin/image-slots/cabin-suite-3/slot')->assertSessionHas('status');
        $this->assertFalse(ImageSlot::where('key', 'cabin-suite-3')->exists());
        $this->assertFileDoesNotExist($file);

        // Slots the page scripts read directly stay protected
        foreach (['hero', 'exterior'] as $key) {
            $this->delete("/admin/image-slots/$key/slot")->assertSessionHas('error', fn ($m) => str_contains($m, 'Website template'));
        }
    }

    public function test_only_unused_custom_slots_can_be_deleted(): void
    {
        $this->post('/admin/image-slots', ['label' => 'Spare photo', 'photo' => UploadedFile::fake()->image('s.jpg')]);
        $slot = ImageSlot::where('label', 'Spare photo')->firstOrFail();
        $file = public_path($slot->path);

        // In use → refused, with where it is used
        $destination = Destination::where('slug', 'aqaba')->firstOrFail();
        $destination->update(['image_slot' => $slot->key]);
        $this->delete("/admin/image-slots/{$slot->key}/slot")->assertSessionHas('error', fn ($m) => str_contains($m, 'Destination “Aqaba”'));
        $this->assertModelExists($slot);

        // Its photo can't just be removed either: nothing to fall back to
        $this->delete("/admin/image-slots/{$slot->key}")->assertSessionHas('error');
        $this->assertFileExists($file);

        $destination->update(['image_slot' => 'dest-aqaba']);
        $this->delete("/admin/image-slots/{$slot->key}/slot")->assertSessionHas('status');
        $this->assertModelMissing($slot);
        $this->assertFileDoesNotExist($file);

        // Slots used by the website template stay
        $this->delete('/admin/image-slots/hero/slot')->assertSessionHas('error');
        $this->assertTrue(ImageSlot::where('key', 'hero')->exists());
    }

    public function test_upload_photos_straight_into_a_cruise_gallery(): void
    {
        $cruise = Cruise::where('slug', 'aroya-repositioning')->firstOrFail();
        $before = $cruise->gallery_slots; // 3 photos

        $this->get("/admin/cruises/{$cruise->id}/edit")->assertOk()->assertSee('Upload new photos')->assertSee('name="gallery_slots_upload[]"', false);

        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise, [
            'gallery_slots_upload' => [UploadedFile::fake()->image('deck.jpg', 1600, 1000), UploadedFile::fake()->image('pool.jpg', 1600, 1000)],
        ]))->assertSessionHasNoErrors();

        $gallery = $cruise->fresh()->gallery_slots;
        $this->assertCount(5, $gallery);
        $this->assertSame($before, array_slice($gallery, 0, 3)); // added at the end, in order
        $new = ImageSlot::whereIn('key', array_slice($gallery, 3))->orderBy('id')->get();
        $this->assertSame(['AROYA – Repositioning – Gallery 4', 'AROYA – Repositioning – Gallery 5'], $new->pluck('label')->all());
        $this->assertSame(['aroya-repositioning-4', 'aroya-repositioning-5'], $new->pluck('key')->all());
        $this->assertTrue($new->every->is_custom);
        $this->assertTrue($new->every(fn ($s) => is_file(public_path($s->path))));

        // The cruise page gets them, and Photo slots shows where they're used
        $urls = collect($this->get('/cruises/aroya-repositioning')->viewData('scData')['cruises'])->firstWhere('id', 'aroya-repositioning')['gallery'];
        $this->assertSame($new->first()->url(), $urls[3]);
        $this->get('/admin/image-slots')->assertSee('Cruise “AROYA – Repositioning” (gallery)');

        // Not images are rejected, nothing saved
        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise->fresh(), ['gallery_slots_upload' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]]))
            ->assertSessionHasErrors('gallery_slots_upload.0');
        $this->assertCount(5, $cruise->fresh()->gallery_slots);
    }

    public function test_upload_replaces_a_single_photo_field(): void
    {
        $destination = Destination::where('slug', 'yanbu')->firstOrFail();
        $form = $destination->only(['name', 'name_ar', 'slug', 'country', 'country_ar', 'blurb', 'description', 'visa_note', 'sort_order'])
            + ['highlights' => implode("\n", $destination->highlights), 'is_active' => 1, 'image_slot' => $destination->image_slot];

        $this->put("/admin/destinations/{$destination->id}", $form + ['image_slot_upload' => UploadedFile::fake()->image('yanbu.jpg', 1600, 1000)])
            ->assertSessionHasNoErrors();

        $slot = ImageSlot::where('key', $destination->fresh()->image_slot)->firstOrFail();
        $this->assertTrue($slot->is_custom);
        $this->assertSame('Yanbu – Photo', $slot->label);
        $this->assertSame('dest-yanbu', $destination->image_slot); // the old slot stays available
        $this->assertTrue(ImageSlot::where('key', 'dest-yanbu')->exists());
    }
}
