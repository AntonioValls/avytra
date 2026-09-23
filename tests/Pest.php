<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function actingAsSuperadmin(): User
{
    $superadmin = User::factory()->superadmin()->create();

    test()->actingAs($superadmin);

    return $superadmin;
}

function actingAsOwnerOf(Business $business): User
{
    test()->actingAs($business->owner);

    return $business->owner;
}

/**
 * Fakes the two image disks (private originals and public conversions, docs/17).
 */
function fakeMediaDisks(): void
{
    Storage::fake(config('avytra.media.originals_disk'));
    Storage::fake(config('avytra.media.disk'));
}

/**
 * A real JPEG (GD) large enough for the upload rules.
 */
function fakeImage(string $name = 'foto.jpg', int $width = 800, int $height = 500): UploadedFile
{
    return UploadedFile::fake()->image($name, $width, $height);
}
