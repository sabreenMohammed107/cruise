<?php

namespace App\Admin;

use App\Support\MediaLibrary;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * One form field of an admin resource. Knows how to render (type), validate (rules)
 * and turn request input into a model value (value()).
 */
final class Field
{
    /** @var array|Closure(?Model): array */
    public array|Closure $rules = [];

    public array $options = [];

    public ?string $help = null;

    public bool $onIndex = false;

    public bool $wide = false;

    public bool $readonlyOnEdit = false;

    /** Shown as an index column only, never in the form. */
    public bool $indexOnly = false;

    /** Value shown when creating a new record. */
    public mixed $default = null;

    /** Has an Arabic twin field "{name}_ar" (see arabicTwin()). */
    public bool $arabic = false;

    /** Right-to-left input (the Arabic twin). */
    public bool $rtl = false;

    private function __construct(public string $name, public string $label, public string $type) {}

    public static function text(string $name, string $label): self
    {
        return new self($name, $label, 'text');
    }

    public static function email(string $name, string $label): self
    {
        return new self($name, $label, 'email');
    }

    public static function number(string $name, string $label, string $step = '1'): self
    {
        return (new self($name, $label, 'number'))->options(['step' => $step]);
    }

    public static function textarea(string $name, string $label): self
    {
        return (new self($name, $label, 'textarea'))->wide();
    }

    /** @param  array<string, string>  $options  value => label */
    public static function select(string $name, string $label, array $options): self
    {
        return (new self($name, $label, 'select'))->options($options);
    }

    public static function checkbox(string $name, string $label): self
    {
        return new self($name, $label, 'checkbox');
    }

    public static function date(string $name, string $label): self
    {
        return new self($name, $label, 'date');
    }

    /** JSON list edited as one item per line (features, highlights, route, story paragraphs). */
    public static function lines(string $name, string $label): self
    {
        return (new self($name, $label, 'lines'))->wide();
    }

    /** Several fixed choices stored as a JSON list (cruise tags). */
    public static function checkboxes(string $name, string $label, array $options): self
    {
        return (new self($name, $label, 'checkboxes'))->options($options)->wide();
    }

    /** One photo, picked from the media library or uploaded; stores a path under public/ or a photo URL. */
    public static function image(string $name, string $label): self
    {
        return new self($name, $label, 'image');
    }

    /** Ordered gallery of photos from the media library or uploads; stores a list of paths / photo URLs. */
    public static function images(string $name, string $label): self
    {
        return (new self($name, $label, 'images'))->wide();
    }

    /** Left empty on edit = keep the current password. */
    public static function password(string $name, string $label): self
    {
        return new self($name, $label, 'password');
    }

    public function rules(array|Closure $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    public function index(): self
    {
        $this->onIndex = true;

        return $this;
    }

    public function wide(): self
    {
        $this->wide = true;

        return $this;
    }

    public function indexOnly(): self
    {
        $this->onIndex = true;
        $this->indexOnly = true;

        return $this;
    }

    public function readonlyOnEdit(): self
    {
        $this->readonlyOnEdit = true;

        return $this;
    }

    /** Also edit the Arabic version of this text ({name}_ar column). */
    public function arabic(): self
    {
        $this->arabic = true;

        return $this;
    }

    /**
     * The Arabic field shown right after this one: same type and length limit, optional
     * (an empty Arabic value shows the English text on the website), right-to-left.
     */
    public function arabicTwin(): self
    {
        $twin = new self($this->name.'_ar', $this->label.' (عربي)', $this->type);
        $rules = $this->rules instanceof Closure ? ['string'] : $this->rules;
        $twin->rules = array_values(array_unique(['nullable', ...array_filter($rules, fn ($r) => ! in_array($r, ['required', 'nullable'], true))]));
        $twin->options = $this->options;
        $twin->wide = $this->wide;
        $twin->rtl = true;
        $twin->help = 'Leave empty to show the English text.';

        return $twin;
    }

    public function resolveRules(?Model $record): array
    {
        $rules = $this->rules instanceof Closure ? ($this->rules)($record) : $this->rules;

        // Photo fields also take direct uploads ({name}_upload), saved to the media library by Resource::fill()
        $photo = ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.intdiv(MediaLibrary::maxUploadBytes(), 1024)];
        // A media library pick: an image file under public/ or a photo URL (see MediaLibrary)
        $pick = function (string $attribute, mixed $value, Closure $fail) {
            if (! MediaLibrary::isValid($value)) {
                $fail('That photo is no longer in the media library. Choose another one.');
            }
        };

        return match ($this->type) {
            'images' => [
                $this->name => ['nullable', 'array'], $this->name.'.*' => ['nullable', 'string', $pick],
                $this->name.'_upload' => ['nullable', 'array', 'max:12'], $this->name.'_upload.*' => $photo,
            ],
            'checkboxes' => [$this->name => ['nullable', 'array'], $this->name.'.*' => ['string', 'in:'.implode(',', array_keys($this->options))]],
            'image' => [
                // a new upload replaces the picked photo, so the pick may be empty then
                $this->name => array_merge(request()->hasFile($this->name.'_upload') ? ['nullable'] : ($rules ?: ['nullable']), ['string', $pick]),
                $this->name.'_upload' => ['nullable', ...$photo],
            ],
            'checkbox' => [$this->name => ['nullable', 'boolean']],
            default => [$this->name => $rules],
        };
    }

    /**
     * Clear messages for photo uploads PHP rejected (bigger than the server allows) or that are too large.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        if (! in_array($this->type, ['image', 'images'], true)) {
            return [];
        }
        $key = $this->name.'_upload'.($this->type === 'images' ? '.*' : '');
        $tooBig = 'A photo is too large: the limit is '.MediaLibrary::maxUploadLabel().'. Compress it first (e.g. squoosh.app) and try again.';

        return ["{$key}.uploaded" => $tooBig, "{$key}.max" => $tooBig];
    }

    /**
     * Request input → model value.
     */
    public function value(Request $request): mixed
    {
        $raw = $request->input($this->name);

        return match ($this->type) {
            'checkbox' => $request->boolean($this->name),
            // /u: without it \R also matches the byte 0x85, which is inside Arabic letters (e.g. "م" = D9 85)
            'lines' => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $raw)), 'strlen')),
            'images' => array_values(array_unique(array_filter((array) $raw))),
            'checkboxes' => array_values((array) ($raw ?? [])),
            // Empty input falls back to the field default (e.g. sort_order 0 for NOT NULL columns)
            default => ($raw === '' || $raw === null) ? $this->default : $raw,
        };
    }

    /**
     * Model value → what the form shows.
     */
    public function display(?Model $record): mixed
    {
        $value = old($this->name, $record ? $record->getAttribute($this->name) : $this->default);

        return match ($this->type) {
            'lines' => is_array($value) ? implode("\n", $value) : $value,
            'date' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value,
            'password' => null,
            default => $value instanceof \BackedEnum ? $value->value : $value,
        };
    }
}
