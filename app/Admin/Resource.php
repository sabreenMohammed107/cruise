<?php

namespace App\Admin;

use App\Support\MediaLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Describes one admin CRUD screen. Admin\ResourceController and the views in
 * resources/views/admin/resources render any resource from this description.
 * Screens with their own workflow (bookings, sailings) have dedicated controllers.
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

    /** Records this screen may edit or delete (e.g. only customers on Customers). */
    public static function scope(): Builder
    {
        return static::$model::query();
    }

    /** Extra index filters from the request (shown by indexToolbar()). */
    public static function filter(Builder $query, Request $request): Builder
    {
        return $query;
    }

    /** The index list: query() + filter() + the search box over $search. */
    public static function indexQuery(Request $request): Builder
    {
        $query = static::filter(static::query(), $request);

        if (($q = trim((string) $request->query('q'))) !== '' && static::$search) {
            $query->where(function (Builder $w) use ($q) {
                foreach (static::$search as $column) {
                    $w->orWhere($column, 'like', "%{$q}%");
                }
            });
        }

        return $query;
    }

    /** Optional Blade partial with extra filters and buttons, shown next to the index search box. */
    public static function indexToolbar(): ?string
    {
        return null;
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

    /** Custom validation messages of the fields (e.g. oversized photo uploads). */
    public static function messages(): array
    {
        return array_merge(...array_map(fn (Field $field) => $field->messages(), static::allFields()));
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
     * Direct uploads in photo fields ({name}_upload) are saved to the media library, named after the
     * record (e.g. uploads/media/aroya-repositioning-4-1a2b3c.jpg). A gallery gets them appended; a
     * single photo field switches to the new photo.
     */
    protected static function storePhotoUploads(Model $record, Request $request): void
    {
        foreach (static::fields() as $field) {
            if (! in_array($field->type, ['image', 'images'], true) || ! $request->hasFile($field->name.'_upload')) {
                continue;
            }
            $owner = trim((string) $record->getAttribute(static::$title)) ?: static::$singular;
            $files = array_values(array_filter(\Illuminate\Support\Arr::wrap($request->file($field->name.'_upload'))));
            $current = $field->type === 'images' ? array_values((array) $record->getAttribute($field->name)) : [];

            $paths = [];
            foreach ($files as $i => $file) {
                $paths[] = MediaLibrary::store($file, $field->type === 'images' ? $owner.' '.(count($current) + $i + 1) : "{$owner} {$field->label}");
            }

            $record->setAttribute($field->name, $field->type === 'images' ? [...$current, ...$paths] : $paths[0]);
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
