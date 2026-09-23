<?php

use App\Actions\Media\AddBusinessImage;
use App\Actions\Media\RemoveBusinessImage;
use App\Actions\Media\ReorderBusinessGallery;
use App\Actions\Media\UpdateBusinessImageAlt;
use App\Actions\Users\DeleteUserAccount;
use App\Enums\MediaCollection;
use App\Exceptions\GalleryFull;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    fakeMediaDisks();
});

test('the gallery refuses images beyond the configured maximum', function () {
    config(['avytra.media.gallery_max' => 1]);

    $business = Business::factory()->create();
    $add = app(AddBusinessImage::class);

    $add->handle($business, $business->owner, MediaCollection::Gallery, fakeImage());

    expect(fn () => $add->handle($business, $business->owner, MediaCollection::Gallery, fakeImage()))
        ->toThrow(GalleryFull::class);

    expect($business->fresh()->galleryImages())->toHaveCount(1);
});

test('a custom alt text is kept as given', function () {
    $business = Business::factory()->create();

    $media = app(AddBusinessImage::class)->handle($business, $business->owner, MediaCollection::Cover, fakeImage(), '  Escaparate  ');

    expect($media->getCustomProperty('alt'))->toBe('Escaparate');
});

test('reordering ignores ids of other businesses and keeps the images left out at the end', function () {
    $business = Business::factory()->create();
    $other = Business::factory()->create();
    $add = app(AddBusinessImage::class);

    $a = $add->handle($business, $business->owner, MediaCollection::Gallery, fakeImage('a.jpg'));
    $b = $add->handle($business, $business->owner, MediaCollection::Gallery, fakeImage('b.jpg'));
    $c = $add->handle($business, $business->owner, MediaCollection::Gallery, fakeImage('c.jpg'));
    $foreign = $add->handle($other, $other->owner, MediaCollection::Gallery, fakeImage('x.jpg'));

    app(ReorderBusinessGallery::class)->handle($business, $business->owner, [$foreign->id, $c->id, $a->id]);

    expect($business->fresh()->galleryImages()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id])
        ->and($other->fresh()->galleryImages()->pluck('id')->all())->toBe([$foreign->id]);
});

test('images of another business cannot be removed or renamed through a business they do not belong to', function () {
    $business = Business::factory()->create();
    $other = Business::factory()->create();

    $foreign = app(AddBusinessImage::class)->handle($other, $other->owner, MediaCollection::Cover, fakeImage());

    expect(fn () => app(RemoveBusinessImage::class)->handle($business, $business->owner, $foreign))
        ->toThrow(NotFoundHttpException::class);

    expect(fn () => app(UpdateBusinessImageAlt::class)->handle($business, $business->owner, $foreign, 'Nuevo'))
        ->toThrow(NotFoundHttpException::class);

    expect(Media::count())->toBe(1)
        ->and($foreign->fresh()->getCustomProperty('alt'))->not->toBe('Nuevo');
});

test('the superadmin changing an alt text on behalf of the owner is audited with before and after', function () {
    $admin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();

    $media = app(AddBusinessImage::class)->handle($business, $business->owner, MediaCollection::Cover, fakeImage(), 'Antes');

    app(UpdateBusinessImageAlt::class)->handle($business, $admin, $media, 'Después');

    $log = AuditLog::sole();

    expect($media->fresh()->getCustomProperty('alt'))->toBe('Después')
        ->and($log->action)->toBe('business.images_updated_by_admin')
        ->and($log->on_behalf_of_user_id)->toBe($business->owner_user_id)
        ->and($log->changes['before']['alt'])->toBe('Antes')
        ->and($log->changes['after']['alt'])->toBe('Después');
});

test('soft deleting a business keeps its images; deleting the account removes them for good', function () {
    $business = Business::factory()->create();
    $media = app(AddBusinessImage::class)->handle($business, $business->owner, MediaCollection::Cover, fakeImage());
    $original = $media->getPathRelativeToRoot();
    $thumb = $media->getPathRelativeToRoot('thumb');

    $business->delete();

    expect(Media::count())->toBe(1);
    Storage::disk(config('avytra.media.originals_disk'))->assertExists($original);

    app(DeleteUserAccount::class)->handle($business->owner);

    expect(Media::count())->toBe(0);
    Storage::disk(config('avytra.media.originals_disk'))->assertMissing($original);
    Storage::disk(config('avytra.media.disk'))->assertMissing($thumb);
});
