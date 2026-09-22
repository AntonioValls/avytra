<?php

use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;

test('the catalogue loads every region, province and municipality with centroid and population', function () {
    $this->artisan('avytra:import-geography')->assertSuccessful();

    expect(Region::count())->toBe(19)
        ->and(Province::count())->toBe(52)
        ->and(Municipality::count())->toBe(8131)
        ->and(Municipality::whereNull('latitude')->orWhereNull('longitude')->count())->toBe(0)
        ->and(Municipality::whereNull('population')->count())->toBe(0);

    $madrid = Municipality::where('code', '28079')->sole();

    expect($madrid->name)->toBe('Madrid')
        ->and($madrid->slug)->toBe('madrid')
        ->and($madrid->province->code)->toBe('28')
        ->and($madrid->province->region->name)->toBe('Comunidad de Madrid')
        ->and($madrid->population)->toBeGreaterThan(3000000)
        ->and(Province::where('code', '46')->sole()->name)->toBe('Valencia');
});

test('running the import twice updates rows instead of duplicating them', function () {
    $this->artisan('avytra:import-geography')->assertSuccessful();

    Municipality::where('code', '28079')->update(['name' => 'Renamed']);

    $this->artisan('avytra:import-geography')->assertSuccessful();

    expect(Municipality::count())->toBe(8131)
        ->and(Municipality::where('code', '28079')->sole()->name)->toBe('Madrid');
});

test('the import fails clearly when the data files are missing', function () {
    $this->artisan('avytra:import-geography', ['--path' => sys_get_temp_dir().'/avytra-missing'])->assertFailed();

    expect(Region::count())->toBe(0);
});
