<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin → Website content → Footer & contact: the footer description, website link, phones, email, address,
 * WhatsApp number and social media links (fields in config/site.php, values in site_settings).
 */
class SiteSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.site-settings.edit', ['groups' => config('site.footer')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $fields = SiteSetting::fields();
        $request->validate($this->rules($fields), [
            '*.regex' => 'Use digits only, with the country code (e.g. 905016451111).',
            '*.url' => 'Enter a full address starting with https://',
        ]);

        DB::transaction(function () use ($request, $fields) {
            foreach ($fields as $key => $field) {
                $setting = SiteSetting::firstOrNew(['key' => $key]);
                $setting->value = trim((string) $request->input($key));
                $setting->value_ar = ($field['bi'] ?? false) ? trim((string) $request->input($key.'_ar')) : null;
                $setting->save();
            }
        });

        return redirect()->route('admin.site-settings.edit')->with('status', 'Footer & contact saved.');
    }

    private function rules(array $fields): array
    {
        $rules = [];
        foreach ($fields as $key => $field) {
            $rules[$key] = ['nullable', 'string', ...match ($field['type'] ?? 'text') {
                'url' => ['max:255', 'url:http,https'],
                'email' => ['max:255', 'email'],
                'phone' => ['max:30', 'regex:/^\+?[0-9 ()\-]{6,}$/'],
                'whatsapp' => ['max:20', 'regex:/^[0-9]{8,15}$/'],
                default => ['max:'.($field['max'] ?? 255)],
            }];
            if ($field['bi'] ?? false) {
                $rules[$key.'_ar'] = ['nullable', 'string', 'max:'.($field['max'] ?? 255)];
            }
        }

        return $rules;
    }
}
