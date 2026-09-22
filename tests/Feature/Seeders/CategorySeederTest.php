<?php

use App\Models\Category;
use Database\Seeders\CategorySeeder;

test('the seeder creates the sectors with their subsectors', function () {
    $this->seed(CategorySeeder::class);

    $hospitality = Category::where('slug', 'hosteleria-y-restauracion')->sole();

    expect(Category::roots()->count())->toBe(16)
        ->and(Category::whereNotNull('parent_id')->count())->toBe(80)
        ->and($hospitality->children)->toHaveCount(6)
        ->and($hospitality->children->first()->name)->toBe('Restaurantes')
        ->and($hospitality->children->first()->parent_id)->toBe($hospitality->id);
});

test('seeding again does not duplicate categories', function () {
    $this->seed(CategorySeeder::class);
    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(96);
});
