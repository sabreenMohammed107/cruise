<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImageSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Photo slots (replaces legacy-html/photos.html): upload the official photo for a slot once
 * and it is used everywhere that slot appears. Admins can also add their own slots, replace any
 * slot's photo, and delete any slot while nothing uses it.
 *
 * Uploads go to public/uploads/image-slots/, so no storage symlink is needed on Windows/XAMPP.
 */
class ImageSlotController extends Controller
{
    private const PHOTO_RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

    private const PHOTO_MESSAGES = ['photo.max' => 'Photos can be at most 5 MB. Compress it first (e.g. squoosh.app).'];

    public function index(): View
    {
        $slots = ImageSlot::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.image-slots.index', [
            'slots' => $slots,
            'filled' => $slots->filter->isFilled()->count(),
            'usage' => ImageSlot::usageMap(),
        ]);
    }

    /** Add a slot: name, optional key and recommended size, and its photo. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'key' => ['nullable', 'alpha_dash', 'max:30', 'unique:image_slots,key'],
            'recommended_size' => ['nullable', 'string', 'max:20'],
            'photo' => ['required', ...self::PHOTO_RULES],
        ], self::PHOTO_MESSAGES + ['key.unique' => 'That key is already used by another slot.']);

        $slot = ImageSlot::fromUpload($request->file('photo'), $data['label'], $data['key'] ?? null, $data['recommended_size'] ?? null);

        return redirect()->route('admin.image-slots.index')->withFragment('slot-'.$slot->key)
            ->with('status', "Photo slot “{$slot->label}” added. Choose it in any photo or gallery field (key: {$slot->key}).");
    }

    public function update(Request $request, ImageSlot $slot): RedirectResponse
    {
        $request->validate(['photo' => ['required', ...self::PHOTO_RULES]], self::PHOTO_MESSAGES);

        $slot->storeUpload($request->file('photo'));
        $slot->save();

        return back()->with('status', "Photo for “{$slot->label}” updated.");
    }

    /** Remove the uploaded photo; the template's slots fall back to their stock photo / drawing. */
    public function destroy(ImageSlot $slot): RedirectResponse
    {
        if ($slot->is_custom) {
            return back()->with('error', "“{$slot->label}” is your own slot and has no other photo to fall back to. Upload a new photo, or delete the slot.");
        }
        $slot->deleteUpload();
        $slot->save();

        return back()->with('status', "Uploaded photo removed from “{$slot->label}”. The fallback is shown again.");
    }

    /**
     * Delete a slot (built-in or added from the admin), but only when nothing uses it: pages, records
     * and the slots the page scripts need (ImageSlot::usageMap()) would otherwise lose their photo.
     */
    public function remove(ImageSlot $slot): RedirectResponse
    {
        if ($uses = ImageSlot::usageMap()[$slot->key] ?? []) {
            return back()->with('error', "“{$slot->label}” is still used by: ".implode(', ', $uses).'. Choose another photo there first.');
        }

        $slot->deleteUpload();
        $slot->delete();

        return back()->with('status', "Photo slot “{$slot->label}” deleted.");
    }
}
