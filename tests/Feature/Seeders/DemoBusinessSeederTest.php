<?php

use App\Enums\BusinessType;
use App\Enums\ListingStatus;
use App\Enums\OperationType;
use App\Models\Business;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Notifications\ListingPublished;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoBusinessSeeder;
use Illuminate\Support\Facades\Notification;

function seedDemoPrerequisites(): void
{
    test()->seed(CategorySeeder::class);

    $province = Province::factory()->create(['code' => '12', 'name' => 'Castellón', 'slug' => 'castellon']);
    Municipality::factory()->withPopulation(37915)->create(['province_id' => $province->id, 'code' => '12032', 'name' => 'Borriana', 'slug' => 'borriana', 'latitude' => 39.890047, 'longitude' => -0.074325]);
}

test('the demo seeder creates a hybrid business in Borriana with a published listing for the test user', function () {
    Notification::fake();
    seedDemoPrerequisites();

    $this->seed(DemoBusinessSeeder::class);

    $owner = User::where('email', DemoBusinessSeeder::OWNER_EMAIL)->sole();
    $business = Business::sole();
    $listing = Listing::sole();

    expect($business->owner_user_id)->toBe($owner->id)
        ->and($business->name)->toBe(DemoBusinessSeeder::BUSINESS_NAME)
        ->and($business->business_type)->toBe(BusinessType::Hybrid)
        ->and($business->category->slug)->toBe('distribucion-y-mayoristas')
        ->and($business->founded_year)->toBe(2024)
        ->and($business->employee_range->value)->toBe('three_to_five')
        ->and($business->location->municipality->slug)->toBe('borriana')
        ->and($business->location->public_latitude)->not->toBeNull()
        ->and($business->onlineProfile)->not->toBeNull()
        ->and($listing->status)->toBe(ListingStatus::Published)
        ->and($listing->slug)->not->toBeNull()
        ->and($listing->offeredOperationTypes())->toBe([OperationType::FullSale, OperationType::PartnerEntry])
        ->and($listing->financialMetrics()->count())->toBe(5);

    Notification::assertSentTo($owner, ListingPublished::class);
});

test('seeding the demo business twice does not duplicate it', function () {
    Notification::fake();
    seedDemoPrerequisites();

    $this->seed(DemoBusinessSeeder::class);
    $this->seed(DemoBusinessSeeder::class);

    expect(Business::count())->toBe(1)
        ->and(Listing::count())->toBe(1);
});
