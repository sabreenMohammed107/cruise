<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSection;
use App\Support\ResponsiveImage;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Page content: text, buttons and photos of the sections of the homepage (config/home.php) and
 * the other public pages (config/pages.php: ship, cruises list, cruise details, destinations),
 * plus the cards / list rows of sections that have them. Sections are fixed by the page layout,
 * so they are edited and shown/hidden here, not created or deleted.
 *
 * Uploads go to public/uploads/home/ (same reason as photo slots: no storage symlink on Windows/XAMPP).
 */
class HomeSectionController extends Controller
{
    /** Site path, anchor, full URL, mailto: or tel:. Blocks javascript: and other schemes. */
    private const LINK = '/^(\/(?!\/)|#|https?:\/\/|mailto:|tel:)/i';

    public function index(): View
    {
        return view('admin.home-sections.index', ['sections' => HomeSection::forHome()]);
    }

    /** Admin → Cruise pages: the sections of every page except the homepage, grouped by page. */
    public function pages(): View
    {
        $pages = collect(HomeSection::pages())->reject(fn ($p) => $p === 'home')->mapWithKeys(fn ($p) => [$p => [
            'config' => HomeSection::pageConfig($p),
            'sections' => HomeSection::forPage($p),
        ]]);

        return view('admin.home-sections.pages', ['pages' => $pages]);
    }

    public function edit(string $key): View
    {
        return $this->editPage('home', $key);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        return $this->updatePage($request, 'home', $key);
    }

    public function editPage(string $page, string $key): View
    {
        $section = $this->find($page, $key);

        return view('admin.home-sections.edit', [
            'section' => $section->load('items'),
            'icons' => config('home.icons'),
            'tones' => config('home.tones'),
        ]);
    }

    public function updatePage(Request $request, string $page, string $key): RedirectResponse
    {
        $section = $this->find($page, $key);

        $data = $request->validate($this->rules($section), [
            'image.max' => 'Photos can be at most 5 MB. Compress it first (e.g. squoosh.app).',
            'image_mobile.max' => 'Photos can be at most 5 MB. Compress it first (e.g. squoosh.app).',
            '*.regex' => 'Links must start with /, #, https://, mailto: or tel:.',
            '*.*.title.required_unless' => 'Every card needs a title.',
            'new_items.*.title.required_with' => 'Every new card needs a title.',
        ]);

        DB::transaction(function () use ($request, $section, $data) {
            $section->fill(collect($data)->only($this->columns($section))->all());
            // Sections the page script shows on its own (e.g. "No results") can't be hidden
            $section->is_visible = $section->config('can_hide', true) ? $request->boolean('is_visible') : true;

            // Photo (image_path) and, for the hero, the portrait photo for phones (image_mobile_path)
            foreach (['image' => 'image_path', 'image_mobile' => 'image_mobile_path'] as $field => $column) {
                if (! $section->supports($field)) {
                    continue;
                }
                if ($request->hasFile($field)) {
                    $this->deleteUpload($section, $column);
                    $file = $request->file($field);
                    $suffix = $field === 'image_mobile' ? '-mobile' : '';
                    $name = $section->page.'-'.$section->key.$suffix.'-'.Str::lower(Str::random(6)).'.'.$file->extension();
                    $file->move(public_path(config('home.upload_dir')), $name);
                    $section->{$column} = config('home.upload_dir').'/'.$name;
                    ResponsiveImage::generate($section->{$column});
                } elseif ($request->boolean('remove_'.$field)) {
                    $this->deleteUpload($section, $column);
                    $section->{$column} = null;
                } elseif (ResponsiveImage::missing($section->{$column})) {
                    // Uploaded before smaller copies existed: make them now (live has no shell to run a command)
                    ResponsiveImage::generate($section->{$column});
                }
            }

            $section->save();

            if ($section->supports('items')) {
                $this->saveItems($section, $data);
            }
        });

        return redirect($section->editUrl())
            ->with('status', "“{$section->name()}” saved.");
    }

    /** The section's row; created from its config defaults if the seeder hasn't run. */
    private function find(string $page, string $key): HomeSection
    {
        $config = HomeSection::pageConfig($page)['sections'][$key] ?? null;
        abort_unless(is_array($config), 404);

        $section = HomeSection::firstOrCreate(['page' => $page, 'key' => $key], $config['content']);
        if ($section->wasRecentlyCreated) {
            foreach ($config['items'] ?? [] as $i => $item) {
                $section->items()->create($item + ['sort_order' => $i + 1]);
            }
        }

        return $section;
    }

    /** Validation rules for the fields this section uses. */
    private function rules(HomeSection $section): array
    {
        $rules = ['is_visible' => ['boolean']];
        $text = fn (int $max, string $req = 'nullable') => [$req, 'string', 'max:'.$max];
        $link = ['nullable', 'string', 'max:255', 'regex:'.self::LINK];

        if ($section->supports('eyebrow')) {
            $rules['eyebrow'] = $text(255);
        }
        if ($section->supports('title')) {
            $rules['title'] = $text(255, $section->config('title_required', true) ? 'required' : 'nullable');
        }
        if ($section->supports('body')) {
            $rules['body'] = $text(2000);
        }
        foreach (['primary', 'secondary'] as $button) {
            if ($section->supports($button)) {
                $rules[$button.'_label'] = $text(60);
                if ($section->hasLinks()) {
                    $rules[$button.'_url'] = [...$link, "required_with:{$button}_label,{$button}_label_ar"];
                }
            }
        }
        if ($section->supports('image')) {
            $rules['image'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            $rules['image_alt'] = $text(255);
            $rules['remove_image'] = ['boolean'];
        }
        if ($section->supports('image_mobile')) {
            $rules['image_mobile'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            $rules['remove_image_mobile'] = ['boolean'];
        }
        if ($section->supports('subheading')) {
            $rules['caption_label'] = $text(80);
        }
        if ($section->supports('video')) {
            $rules['video_url'] = ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (HomeSection::youtubeId($value) === null) {
                    $fail('Paste a YouTube video link, e.g. https://www.youtube.com/watch?v=P-Vzn04vPEQ or https://youtu.be/P-Vzn04vPEQ.');
                }
            }];
        }
        if ($section->supports('caption')) {
            $rules['caption_label'] = $text(80);
            $rules['caption_value'] = $text(120);
        }

        // Arabic twin of every text column: same length limit, optional (empty shows the English text)
        foreach ($this->textColumns($section) as $column) {
            $max = collect($rules[$column])->first(fn ($r) => is_string($r) && str_starts_with($r, 'max:'));
            $rules[$column.'_ar'] = ['nullable', 'string', $max];
        }

        if ($section->supports('items')) {
            $item = fn (string $prefix, string $titleRule) => [
                "$prefix.*.title" => ['nullable', $titleRule, 'string', 'max:255'],
                "$prefix.*.title_ar" => $text(255),
                "$prefix.*.text" => $text(1000),
                "$prefix.*.text_ar" => $text(1000),
                "$prefix.*.icon" => ['nullable', Rule::in(config('home.icons'))],
                "$prefix.*.tone" => ['nullable', Rule::in(array_keys(config('home.tones')))],
                "$prefix.*.sort_order" => ['nullable', 'integer', 'min:0', 'max:999'],
            ] + ($section->config('item_links') ? ["$prefix.*.url" => $link] : []);
            $rules += ['items' => ['array'], 'items.*.delete' => ['boolean'], 'new_items' => ['array']];
            $rules += $item('items', 'required_unless:items.*.delete,1');
            $rules += $item('new_items', 'required_with:new_items.*.text');
        }

        return $rules;
    }

    /** English text columns this section uses (config home.text_fields). */
    private function textColumns(HomeSection $section): array
    {
        return collect(config('home.text_fields'))->only($section->config('fields'))->flatten()->all();
    }

    /** Columns this section lets the admin change: text in both languages, plus button links. */
    private function columns(HomeSection $section): array
    {
        $columns = collect($this->textColumns($section))->flatMap(fn ($c) => [$c, $c.'_ar']);
        if ($section->hasLinks()) {
            foreach (['primary', 'secondary'] as $button) {
                if ($section->supports($button)) {
                    $columns->push($button.'_url');
                }
            }
        }
        if ($section->supports('video')) {
            $columns->push('video_url');
        }

        return $columns->all();
    }

    private function saveItems(HomeSection $section, array $data): void
    {
        $existing = $section->items()->get()->keyBy('id');

        foreach ($data['items'] ?? [] as $id => $values) {
            $item = $existing->get($id);
            if (! $item) {
                continue; // not one of this section's cards
            }
            if (! empty($values['delete'])) {
                // A delete sent with the form (PUT), so Middleware\AuthorizeDeletes doesn't see it
                Gate::authorize('delete-records');
                $item->delete();
                continue;
            }
            $item->update($this->itemValues($section, $values));
        }

        foreach ($data['new_items'] ?? [] as $values) {
            if (filled($values['title'] ?? null)) {
                $section->items()->create($this->itemValues($section, $values) + [
                    'sort_order' => $values['sort_order'] ?? ($section->items()->max('sort_order') + 1),
                ]);
            }
        }
    }

    private function itemValues(HomeSection $section, array $values): array
    {
        return [
            'title' => $values['title'] ?? null,
            'title_ar' => $values['title_ar'] ?? null,
            'text' => $values['text'] ?? null,
            'text_ar' => $values['text_ar'] ?? null,
            'icon' => $values['icon'] ?? null,
            'tone' => $values['tone'] ?? 'ocean',
        ] + (isset($values['sort_order']) ? ['sort_order' => (int) $values['sort_order']] : [])
          + ($section->config('item_links') ? ['url' => $values['url'] ?? null] : []);
    }

    private function deleteUpload(HomeSection $section, string $column = 'image_path'): void
    {
        // Only ever delete files inside the uploads folder
        $dir = config('home.upload_dir').'/';
        $path = $section->{$column};
        if ($path && str_starts_with($path, $dir) && ! str_contains($path, '..') && is_file(public_path($path))) {
            ResponsiveImage::delete($path);
            unlink(public_path($path));
        }
    }
}
