<?php

use App\Actions\Media\AddBusinessImage;
use App\Enums\MediaCollection;
use App\Models\Business;
use App\Models\Listing;
use App\Support\Listings\PublicListingPresenter;

beforeEach(function () {
    fakeMediaDisks();
});

function publishedListingWithImages(): Listing
{
    $business = Business::factory()->create(['name' => 'Cafetería Central']);
    $add = app(AddBusinessImage::class);

    $add->handle($business, $business->owner, MediaCollection::Cover, fakeImage('portada-original.jpg'), 'Barra de la cafetería');
    $add->handle($business, $business->owner, MediaCollection::Gallery, fakeImage('sala-original.jpg'));
    $add->handle($business, $business->owner, MediaCollection::Logo, fakeImage('logo-original.png', 200, 200));

    return Listing::factory()->forBusiness($business)->published()->create(['slug' => 'traspaso-cafeteria-central']);
}

test('the listing page shows the cover, the gallery and the logo through WebP conversions only', function () {
    $listing = publishedListingWithImages();
    $business = $listing->business;
    $cover = $business->cover();
    $gallery = $business->galleryImages()->first();
    $logo = $business->logo();

    $response = $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee($cover->getFullUrl('detail'), false)
        ->assertSee($cover->getFullUrl('thumb'), false)
        ->assertSee($gallery->getFullUrl('thumb'), false)
        ->assertSee(basename($gallery->getPathRelativeToRoot('detail')), false)
        ->assertSee($logo->getFullUrl('logo'), false)
        ->assertSee('Barra de la cafetería')
        ->assertSee('property="og:image" content="'.$cover->getFullUrl('og').'"', false)
        ->assertSee('"image":"'.$cover->getFullUrl('detail').'"', false)
        ->assertDontSee(__('No photos yet'));

    // Neither the original file name nor its path ever reaches the HTML.
    foreach ([$cover, $gallery, $logo] as $media) {
        $response->assertDontSee($media->file_name, false)
            ->assertDontSee('portada-original', false)
            ->assertDontSee($media->getPathRelativeToRoot(), false);
    }
});

test('cards use the thumb and card conversions with a srcset', function () {
    $listing = publishedListingWithImages();
    $cover = $listing->business->cover();

    $this->get(route('listings.index'))
        ->assertOk()
        ->assertSee($cover->getFullUrl('thumb'), false)
        ->assertSee($cover->getFullUrl('card').' 800w', false)
        ->assertSee('loading="lazy"', false)
        ->assertDontSee($cover->file_name, false);
});

test('without images the pages show the brand placeholder and the default Open Graph image', function () {
    $listing = Listing::factory()->published()->create(['slug' => 'sin-fotos']);

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee(__('No photos yet'))
        ->assertSee('og-default.png', false);

    expect(PublicListingPresenter::for($listing)->coverImage())->toBeNull()
        ->and(PublicListingPresenter::for($listing)->galleryImages())->toBe([]);
});

test('the first gallery image stands in as cover when no cover was uploaded', function () {
    $business = Business::factory()->create();
    $media = app(AddBusinessImage::class)->handle($business, $business->owner, MediaCollection::Gallery, fakeImage());
    $listing = Listing::factory()->forBusiness($business)->published()->create();

    $presenter = PublicListingPresenter::for($listing);

    expect($presenter->coverImage()?->id)->toBe($media->id)
        ->and($presenter->ogImageUrl())->toBeNull();
});

test('images whose conversions are still queued are not shown', function () {
    $business = Business::factory()->create();
    $media = app(AddBusinessImage::class)->handle($business, $business->owner, MediaCollection::Cover, fakeImage());
    // The conversions were marked on the instance the job loaded; reload before undoing them.
    $media->fresh()->forceFill(['generated_conversions' => []])->save();
    $listing = Listing::factory()->forBusiness($business)->published()->create(['slug' => 'en-cola']);

    expect(PublicListingPresenter::for($listing->fresh())->coverImage())->toBeNull();

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee(__('No photos yet'))
        ->assertDontSee($media->file_name, false);
});
