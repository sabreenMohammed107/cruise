<?php

namespace Tests\Feature;

use App\Models\Cruise;
use App\Models\Destination;
use App\Models\User;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Photo fields: the media library, picking, ordering and uploading photos (no photo slots). */
class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** @var list<string> files this test put in public/ */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin');
    }

    protected function tearDown(): void
    {
        $uploaded = collect(MediaLibrary::usedValues())->filter(fn ($v) => str_starts_with($v, MediaLibrary::UPLOAD_DIR.'/'));
        foreach ([...$this->files, ...$uploaded->map(fn ($v) => public_path($v))] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function cruiseForm(Cruise $cruise, array $changes = []): array
    {
        return array_merge($cruise->only(['name', 'name_ar', 'slug', 'ship_id', 'nights', 'departure_port', 'arrival_port', 'summary', 'summary_ar', 'image']), [
            'route_ports' => $cruise->ports->pluck('id')->all(), 'tags' => $cruise->tags, 'is_active' => 1,
            'gallery' => $cruise->gallery,
            'itinerary' => $cruise->itinerary->map(fn ($d) => ['port' => $d->port, 'time' => $d->time_label, 'text' => $d->description])->all(),
        ], $changes);
    }

    /** A real image in public/uploads/home, removed after the test. */
    private function serverImage(): string
    {
        @mkdir(public_path('uploads/home'), 0777, true);
        $path = 'uploads/home/test-library-'.uniqid().'.jpg';
        $fake = UploadedFile::fake()->image('x.jpg', 40, 30);
        copy($fake->getPathname(), public_path($path));
        $this->files[] = public_path($path);

        return $path;
    }

    public function test_photo_slots_are_gone(): void
    {
        $this->get('/admin/image-slots')->assertNotFound();
        $this->get('/admin')->assertDontSee('Photo slots');

        $cruise = Cruise::where('slug', 'aroya-repositioning')->firstOrFail();
        $page = $this->get("/admin/cruises/{$cruise->id}/edit")->assertOk()
            ->assertDontSee('— none —')->assertDontSee('slot')
            ->assertSee('Choose from library')->assertSee('Upload new photos')->assertSee('name="gallery_upload[]"', false);
        $this->assertSame(1, substr_count($page->getContent(), 'id="mediaLibrary"'), 'one shared library modal');
        foreach ($cruise->gallery as $photo) {
            $page->assertSee('name="gallery[]" value="'.e($photo).'"', false);
        }
    }

    public function test_library_lists_server_images_and_photos_in_use(): void
    {
        $path = $this->serverImage();

        $all = $this->getJson('/admin/media-library')->assertOk()->json();
        $this->assertCount(min(24, $all['total']), $all['items']);
        $this->assertSame($path, $all['items'][0]['value']); // newest first

        // The stock photos records use can be picked again
        $values = collect(MediaLibrary::items())->pluck('value');
        $this->assertContains(Cruise::firstOrFail()->image, $values);

        $this->assertSame([$path], $this->getJson('/admin/media-library?q='.basename($path))->json('items.*.value'));
    }

    public function test_pick_reorder_and_remove_gallery_photos(): void
    {
        $path = $this->serverImage();
        $cruise = Cruise::where('slug', 'aroya-repositioning')->firstOrFail();
        [$a, $b] = $cruise->gallery;

        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise, ['gallery' => [$b, $path, $a], 'image' => $path]))
            ->assertSessionHasNoErrors();

        $cruise->refresh();
        $this->assertSame([$b, $path, $a], $cruise->gallery); // stored as picked, in order
        $this->assertSame($path, $cruise->image);
        $this->assertFileExists(public_path($path)); // nothing copied or moved

        // The website shows them
        $site = collect($this->get('/cruises/aroya-repositioning')->viewData('scData')['cruises'])->firstWhere('id', 'aroya-repositioning');
        $this->assertSame(asset($path), $site['image']);
        $this->assertSame([$b, asset($path), $a], $site['gallery']);

        // Unknown files and other folders are rejected
        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise, ['gallery' => ['uploads/missing.jpg', '../.env', 'index.php']]))
            ->assertSessionHasErrors(['gallery.0', 'gallery.1', 'gallery.2']);

        // An empty gallery is allowed
        $form = $this->cruiseForm($cruise);
        unset($form['gallery']);
        $this->put("/admin/cruises/{$cruise->id}", $form)->assertSessionHasNoErrors();
        $this->assertSame([], $cruise->fresh()->gallery);
    }

    public function test_upload_photos_into_a_cruise_gallery(): void
    {
        $cruise = Cruise::where('slug', 'aroya-repositioning')->firstOrFail();
        $before = $cruise->gallery; // 3 photos

        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise, [
            'gallery_upload' => [UploadedFile::fake()->image('deck.jpg', 1600, 1000), UploadedFile::fake()->image('pool.jpg', 1600, 1000)],
        ]))->assertSessionHasNoErrors();

        $gallery = $cruise->fresh()->gallery;
        $this->assertCount(5, $gallery);
        $this->assertSame($before, array_slice($gallery, 0, 3)); // added at the end, in order
        $this->assertMatchesRegularExpression('#^uploads/media/aroya-repositioning-4-[a-z0-9]{6}\.jpg$#', $gallery[3]);
        $this->assertMatchesRegularExpression('#^uploads/media/aroya-repositioning-5-[a-z0-9]{6}\.jpg$#', $gallery[4]);
        $this->assertFileExists(public_path($gallery[3]));
        $this->assertContains($gallery[3], array_column(MediaLibrary::items(), 'value')); // reusable from the library

        // Not images are rejected, nothing saved
        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise->fresh(), ['gallery_upload' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]]))
            ->assertSessionHasErrors('gallery_upload.0');

        // Too large: a clear message with the limit, nothing saved
        $huge = UploadedFile::fake()->image('huge.jpg')->size(intdiv(MediaLibrary::maxUploadBytes(), 1024) + 1);
        $this->put("/admin/cruises/{$cruise->id}", $this->cruiseForm($cruise->fresh(), ['gallery_upload' => [$huge]]))
            ->assertSessionHasErrors(['gallery_upload.0' => 'A photo is too large: the limit is '.MediaLibrary::maxUploadLabel().'. Compress it first (e.g. squoosh.app) and try again.']);
        $this->assertCount(5, $cruise->fresh()->gallery);
    }

    public function test_upload_replaces_a_single_photo_field(): void
    {
        $destination = Destination::where('slug', 'yanbu')->firstOrFail();
        $form = $destination->only(['name', 'name_ar', 'slug', 'country', 'country_ar', 'blurb', 'description', 'visa_note', 'sort_order', 'image'])
            + ['highlights' => implode("\n", $destination->highlights), 'is_active' => 1];

        $this->put("/admin/destinations/{$destination->id}", $form + ['image_upload' => UploadedFile::fake()->image('yanbu.jpg', 1600, 1000)])
            ->assertSessionHasNoErrors();

        $image = $destination->fresh()->image;
        $this->assertMatchesRegularExpression('#^uploads/media/yanbu-photo-[a-z0-9]{6}\.jpg$#', $image);
        $this->get('/destinations')->assertOk()->assertSee('src="'.asset($image).'"', false); // server-rendered since step 21

        // Clearing the photo
        $this->put("/admin/destinations/{$destination->id}", ['image' => ''] + $form)->assertSessionHasNoErrors();
        $this->assertNull($destination->fresh()->image);
    }
}
