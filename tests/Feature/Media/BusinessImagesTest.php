<?php

use App\Enums\MediaCollection;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    fakeMediaDisks();
});

test('the owner uploads a cover: the original stays private, the WebP conversions are public and the alt is filled in', function () {
    $business = Business::factory()->create(['name' => 'Panadería Sol']);
    actingAsOwnerOf($business);

    Livewire::test('businesses.images', ['businessId' => $business->id])
        ->set('cover', fakeImage('mi foto privada.jpg'))
        ->assertHasNoErrors()
        ->assertSet('cover', null);

    $media = $business->fresh()->cover();

    expect($media)->not->toBeNull()
        ->and($media->collection_name)->toBe(MediaCollection::Cover->value)
        ->and($media->disk)->toBe(config('avytra.media.originals_disk'))
        ->and($media->conversions_disk)->toBe(config('avytra.media.disk'))
        ->and($media->file_name)->not->toContain('mi foto privada')
        ->and($media->getCustomProperty('alt'))->toBe('Panadería Sol, imagen de portada');

    Storage::disk(config('avytra.media.originals_disk'))->assertExists($media->getPathRelativeToRoot());

    foreach (MediaCollection::Cover->conversions() as $conversion) {
        expect($media->hasGeneratedConversion($conversion))->toBeTrue($conversion);
        Storage::disk(config('avytra.media.disk'))->assertExists($media->getPathRelativeToRoot($conversion));
        expect($media->getPathRelativeToRoot($conversion))->toEndWith('.webp');
    }

    // The original is never copied to the public disk.
    Storage::disk(config('avytra.media.disk'))->assertMissing($media->getPathRelativeToRoot());
});

test('the cover and the logo hold a single image: a new upload replaces the previous one', function () {
    $business = Business::factory()->create();
    actingAsOwnerOf($business);

    $component = Livewire::test('businesses.images', ['businessId' => $business->id])
        ->set('cover', fakeImage('uno.jpg'));

    $first = $business->fresh()->cover();

    $component->set('cover', fakeImage('dos.jpg'))->assertHasNoErrors();

    $second = $business->fresh()->cover();

    expect($business->fresh()->getMedia('cover'))->toHaveCount(1)
        ->and($second->id)->not->toBe($first->id);

    Storage::disk(config('avytra.media.originals_disk'))->assertMissing($first->getPathRelativeToRoot());
});

test('invalid uploads are rejected', function (UploadedFile $file) {
    $business = Business::factory()->create();
    actingAsOwnerOf($business);

    Livewire::test('businesses.images', ['businessId' => $business->id])
        ->set('cover', $file)
        ->assertHasErrors('cover');

    expect($business->fresh()->media)->toHaveCount(0);
})->with([
    'a PDF' => fn () => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
    'an SVG' => fn () => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    'an image below the minimum size' => fn () => fakeImage('pequena.jpg', 300, 200),
    'a file above the maximum weight' => fn () => UploadedFile::fake()->create('pesada.jpg', 9000, 'image/jpeg'),
]);

test('the logo accepts smaller images than the cover', function () {
    $business = Business::factory()->create();
    actingAsOwnerOf($business);

    Livewire::test('businesses.images', ['businessId' => $business->id])
        ->set('logo', fakeImage('logo.png', 200, 200))
        ->assertHasNoErrors();

    expect($business->fresh()->logo())->not->toBeNull()
        ->and($business->fresh()->logo()->hasGeneratedConversion('logo'))->toBeTrue();
});

test('the gallery accepts up to the configured maximum, numbering the alt texts', function () {
    config(['avytra.media.gallery_max' => 2]);

    $business = Business::factory()->create(['name' => 'Taller Norte']);
    actingAsOwnerOf($business);

    $component = Livewire::test('businesses.images', ['businessId' => $business->id])
        ->set('gallery', [fakeImage('a.jpg'), fakeImage('b.jpg')])
        ->assertHasNoErrors()
        ->assertSet('gallery', []);

    expect($business->fresh()->galleryImages()->pluck('custom_properties.alt')->all())
        ->toBe(['Taller Norte, imagen 1', 'Taller Norte, imagen 2']);

    $component->set('gallery', [fakeImage('c.jpg')])->assertHasErrors('gallery');

    expect($business->fresh()->galleryImages())->toHaveCount(2);
});

test('the alt text of an image can be edited in place', function () {
    $business = Business::factory()->create();
    actingAsOwnerOf($business);

    $component = Livewire::test('businesses.images', ['businessId' => $business->id])
        ->set('gallery', [fakeImage()]);

    $media = $business->fresh()->galleryImages()->first();

    $component->set("alts.{$media->id}", 'Fachada del local')->assertHasNoErrors();

    expect($media->fresh()->getCustomProperty('alt'))->toBe('Fachada del local');
});

test('dragging a gallery image persists the new order', function () {
    $business = Business::factory()->create();
    actingAsOwnerOf($business);

    $component = Livewire::test('businesses.images', ['businessId' => $business->id])
        ->set('gallery', [fakeImage('a.jpg'), fakeImage('b.jpg'), fakeImage('c.jpg')]);

    [$a, $b, $c] = $business->fresh()->galleryImages()->pluck('id')->all();

    $component->call('reorder', $c, 0)->assertHasNoErrors();

    expect($business->fresh()->galleryImages()->pluck('id')->all())->toBe([$c, $a, $b]);
});

test('removing an image deletes the original and its conversions', function () {
    $business = Business::factory()->create();
    actingAsOwnerOf($business);

    $component = Livewire::test('businesses.images', ['businessId' => $business->id])
        ->set('gallery', [fakeImage()]);

    $media = $business->fresh()->galleryImages()->first();
    $original = $media->getPathRelativeToRoot();
    $thumb = $media->getPathRelativeToRoot('thumb');

    $component->call('remove', $media->id)->assertHasNoErrors();

    expect($business->fresh()->media)->toHaveCount(0);
    Storage::disk(config('avytra.media.originals_disk'))->assertMissing($original);
    Storage::disk(config('avytra.media.disk'))->assertMissing($thumb);
});

test('somebody else cannot manage the images of a business', function () {
    $business = Business::factory()->create();
    $this->actingAs(User::factory()->create());

    Livewire::test('businesses.images', ['businessId' => $business->id])->assertForbidden();
});

test('the superadmin manages images on behalf of the owner with an audit trail', function () {
    $business = Business::factory()->create();
    $admin = actingAsSuperadmin();

    Livewire::test('businesses.images', ['businessId' => $business->id])
        ->set('cover', fakeImage())
        ->assertHasNoErrors();

    $log = AuditLog::sole();

    expect($log->action)->toBe('business.images_updated_by_admin')
        ->and($log->actor_user_id)->toBe($admin->id)
        ->and($log->on_behalf_of_user_id)->toBe($business->owner_user_id)
        ->and($log->changes['after']['collection'])->toBe('cover');
});

test('uploads are rate limited per user', function () {
    config(['avytra.media.upload_rate_limit_per_hour' => 1]);

    $business = Business::factory()->create();
    actingAsOwnerOf($business);

    Livewire::test('businesses.images', ['businessId' => $business->id])
        ->set('cover', fakeImage('a.jpg'))
        ->set('logo', fakeImage('b.png', 200, 200))
        ->assertSet('logo', null);

    expect($business->fresh()->cover())->not->toBeNull()
        ->and($business->fresh()->logo())->toBeNull();
});

test('step 7 of the wizard and the business form render the images component', function () {
    $business = Business::factory()->create();
    $listing = Listing::factory()->forBusiness($business)->create();
    actingAsOwnerOf($business);

    $this->get(route('panel.listings.edit', $listing).'?paso=7')
        ->assertOk()
        ->assertSeeLivewire('businesses.images');

    $this->get(route('businesses.edit', $business))
        ->assertOk()
        ->assertSeeLivewire('businesses.images');

    $this->get(route('businesses.create'))
        ->assertOk()
        ->assertDontSeeLivewire('businesses.images')
        ->assertSee(__('Create the business first; you will be able to add images right after.'));
});
