<?php

namespace App\Admin;

use App\Models\ImageSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Describes one admin CRUD screen. Admin\ResourceController and the views in
 * resources/views/admin/resources render any resource from this description.
 * Screens with their own workflow (bookings, sailings, image slots) have dedicated controllers.
 */
abstract class Resource
{
    /** @var class-string<Model> */
    public static string $model;

    /** URL segment and route name part, e.g. "destinations" → admin.destinations.index */
    public static string $slug;

    public static string $label;

    public static string $singular;

    /** Attribute shown as the record's name in headings and confirmations. */
    public static string $title = 'name';

    /** Columns searched by the index search box. */
    public static array $search = [];

    /** @return list<Field> */
    abstract public static function fields(): array;

    public static function query(): Builder
    {
        return static::$model::query()->latest('id');
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return true;
    }

    /**
     * Extra links per row on the index, e.g. a cruise's sailings. label => url
     *
     * @return array<string, string>
     */
    public static function rowActions(Model $record): array
    {
        return [];
    }

    /** Optional Blade partial rendered under the form fields (e.g. the itinerary editor). */
    public static function formExtra(): ?string
    {
        return null;
    }

    /** Rules for formExtra() inputs. */
    public static function extraRules(?Model $record): array
    {
        return [];
    }

    /** Runs after save, inside the same transaction (e.g. to write itinerary rows). */
    public static function afterSave(Model $record, Request $request): void {}

    /**
     * fields() plus the Arabic twin right after every field marked ->arabic().
     *
     * @return list<Field>
     */
    public static function allFields(): array
    {
        $all = [];
        foreach (static::fields() as $field) {
            $all[] = $field;
            if ($field->arabic) {
                $all[] = $field->arabicTwin();
            }
        }

        return $all;
    }

    public static function rules(?Model $record): array
    {
        $rules = [];
        foreach (static::allFields() as $field) {
            if ($field->indexOnly || ($record && $field->readonlyOnEdit)) {
                continue;
            }
            $rules += $field->resolveRules($record);
        }

        return $rules + static::extraRules($record);
    }

    /**
     * Copy validated input onto the model. Override for attributes that aren't mass-assignable.
     */
    public static function fill(Model $record, Request $request): void
    {
        foreach (static::allFields() as $field) {
            if ($field->indexOnly || ($record->exists && $field->readonlyOnEdit)) {
                continue;
            }
            if ($field->type === 'password' && ! $request->filled($field->name)) {
                continue;
            }
            $record->setAttribute($field->name, $field->value($request));
        }

        static::storePhotoUploads($record, $request);
    }

    /**
     * Direct uploads in photo fields ({name}_upload): each file becomes a new photo slot (Photo slots,
     * "Added by you") named after the record. A gallery gets the new slots appended; a single photo
     * field switches to the new slot.
     */
    protected static function storePhotoUploads(Model $record, Request $request): void
    {
        foreach (static::fields() as $field) {
            if (! in_array($field->type, ['image_slot', 'image_slots'], true) || ! $request->hasFile($field->name.'_upload')) {
                continue;
            }
            $owner = trim((string) $record->getAttribute(static::$title)) ?: static::$singular;
            $files = array_values(array_filter(\Illuminate\Support\Arr::wrap($request->file($field->name.'_upload'))));
            $current = $field->type === 'image_slots' ? array_values((array) $record->getAttribute($field->name)) : [];

            $keys = [];
            foreach ($files as $i => $file) {
                $n = count($current) + $i + 1;
                $label = $field->type === 'image_slots' ? "{$owner} – {$field->label} {$n}" : "{$owner} – {$field->label}";
                // short readable keys: aroya-repositioning-4, yanbu-photo
                $keys[] = ImageSlot::fromUpload($file, $label, $field->type === 'image_slots' ? "{$owner} {$n}" : "{$owner} photo")->key;
            }

            $record->setAttribute($field->name, $field->type === 'image_slots' ? [...$current, ...$keys] : $keys[0]);
        }
    }

    /** @return list<Field> */
    public static function formFields(): array
    {
        return array_values(array_filter(static::allFields(), fn (Field $f) => ! $f->indexOnly));
    }

    /** @return list<Field> */
    public static function indexFields(): array
    {
        return array_values(array_filter(static::fields(), fn (Field $f) => $f->onIndex));
    }

    public static function routeName(string $action): string
    {
        return 'admin.'.static::$slug.'.'.$action;
    }
}
