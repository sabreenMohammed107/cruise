<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 16: route ports managed in the admin panel (Cruises → Route ports), in English and Arabic.
 * A cruise's route is an ordered list of ports in cruise_port (a port may repeat, e.g. Jeddah … Jeddah).
 * cruises.route stays as the English names of those stops, so core.js and destination links work unchanged.
 *
 * Existing data is converted: every destination and every port named in a cruise route becomes a port
 * (Arabic name from the destination of the same name), and each cruise's route becomes its cruise_port rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ports', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 120)->unique();
            $table->string('name_ar', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cruise_port', function (Blueprint $table) {
            $table->foreignId('cruise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('port_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position'); // 1 = first stop
            $table->primary(['cruise_id', 'position']);
        });

        $this->convertExistingRoutes();
    }

    public function down(): void
    {
        Schema::dropIfExists('cruise_port');
        Schema::dropIfExists('ports');
    }

    private function convertExistingRoutes(): void
    {
        $now = now();
        $destinations = DB::table('destinations')->orderBy('sort_order')->get(['name', 'name_ar']);

        // Route entries are "Yanbu" or "Yanbu, Saudi Arabia": the port is the part before the comma
        $routes = DB::table('cruises')->pluck('route', 'id')->map(fn ($route) => collect(json_decode($route ?? '[]', true) ?: [])
            ->map(fn ($stop) => trim(explode(',', (string) $stop)[0]))
            ->filter(fn ($stop) => $stop !== '' && $stop !== 'At sea')
            ->values());

        $names = $destinations->pluck('name')->merge($routes->flatten())->unique()->values();
        $arabic = $destinations->pluck('name_ar', 'name');

        foreach ($names as $i => $name) {
            DB::table('ports')->insert([
                'name_en' => $name, 'name_ar' => $arabic[$name] ?? null, 'sort_order' => $i + 1,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $ids = DB::table('ports')->pluck('id', 'name_en');
        foreach ($routes as $cruiseId => $stops) {
            DB::table('cruise_port')->insert($stops->map(fn ($stop, $i) => [
                'cruise_id' => $cruiseId, 'port_id' => $ids[$stop], 'position' => $i + 1,
            ])->all());
            // Same English names as before, without the country part
            DB::table('cruises')->where('id', $cruiseId)->update(['route' => json_encode($stops->all())]);
        }
    }
};
