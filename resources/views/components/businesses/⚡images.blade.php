<?php

use App\Actions\Media\AddBusinessImage;
use App\Actions\Media\RemoveBusinessImage;
use App\Actions\Media\ReorderBusinessGallery;
use App\Actions\Media\UpdateBusinessImageAlt;
use App\Enums\MediaCollection;
use App\Exceptions\GalleryFull;
use App\Models\Business;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Logo, cover and gallery of a business (docs/17). Shared by step 7 of the listing wizard
 * and the business form. Every image is stored as soon as it is chosen, so there is no
 * "save" button: the owner sees exactly what the public pages will show.
 * Orchestrates only: authorize → validate → Action → feedback.
 *
 * @property-read Business $business
 * @property-read bool $hasPendingConversions
 */
new class extends Component {
    use WithFileUploads;

    #[Locked]
    public int $businessId;

    public ?TemporaryUploadedFile $cover = null;

    public ?TemporaryUploadedFile $logo = null;

    /** @var list<TemporaryUploadedFile> */
    public array $gallery = [];

    /** @var array<int, string> alt text per media id, edited in place */
    public array $alts = [];

    public function mount(int $businessId): void
    {
        $this->businessId = $businessId;
        $this->authorize('update', $this->business);
        $this->syncAlts();
    }

    public function updatedCover(): void
    {
        $this->store(MediaCollection::Cover, 'cover');
    }

    public function updatedLogo(): void
    {
        $this->store(MediaCollection::Logo, 'logo');
    }

    public function updatedGallery(): void
    {
        $business = $this->business;
        $this->authorize('update', $business);
        $this->resetErrorBag('gallery');

        $max = (int) config('avytra.media.gallery_max');
        $room = $max - $business->galleryImages()->count();

        if ($room <= 0 || count($this->gallery) > $room) {
            $this->discardTemporary($this->gallery);
            $this->gallery = [];
            $this->addError('gallery', __('The gallery is full: up to :max images per business.', ['max' => $max]));

            return;
        }

        $this->validate(['gallery.*' => $this->rulesFor(MediaCollection::Gallery)], [], ['gallery.*' => __('image')]);

        if (! $this->allowUploads(count($this->gallery))) {
            $this->discardTemporary($this->gallery);
            $this->gallery = [];

            return;
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            foreach ($this->gallery as $file) {
                app(AddBusinessImage::class)->handle($business, $actor, MediaCollection::Gallery, $file);
            }
        } catch (GalleryFull $exception) {
            $this->addError('gallery', $exception->userMessage());
        }

        $this->gallery = [];
        $this->refresh();
    }

    public function remove(int $mediaId): void
    {
        $business = $this->business;
        $this->authorize('update', $business);

        /** @var User $actor */
        $actor = Auth::user();

        app(RemoveBusinessImage::class)->handle($business, $actor, $this->mediaOf($business, $mediaId));

        $this->refresh();
    }

    /**
     * wire:sort handler: the moved media id and its new zero-based position.
     */
    public function reorder(int $mediaId, int $position): void
    {
        $business = $this->business;
        $this->authorize('update', $business);

        $ids = $business->galleryImages()->pluck('id')->map(fn (mixed $id): int => (int) $id)->reject(fn (int $id): bool => $id === $mediaId)->values()->all();
        array_splice($ids, max(0, $position), 0, [$mediaId]);

        /** @var User $actor */
        $actor = Auth::user();

        app(ReorderBusinessGallery::class)->handle($business, $actor, $ids);

        $this->refresh();
    }

    public function updatedAlts(mixed $value, string $key): void
    {
        $business = $this->business;
        $this->authorize('update', $business);

        $this->validate(['alts.*' => ['nullable', 'string', 'max:160']], [], ['alts.*' => __('alternative text')]);

        /** @var User $actor */
        $actor = Auth::user();

        app(UpdateBusinessImageAlt::class)->handle($business, $actor, $this->mediaOf($business, (int) $key), (string) $value);

        $this->refresh();
    }

    #[Computed]
    public function business(): Business
    {
        return Business::query()->with('media')->findOrFail($this->businessId);
    }

    /**
     * True while the queue still has WebP conversions to generate: the view polls meanwhile.
     */
    #[Computed]
    public function hasPendingConversions(): bool
    {
        return $this->business->media->contains(fn (Media $media): bool => ! $media->hasGeneratedConversion(MediaCollection::from($media->collection_name)->conversions()[0]));
    }

    /**
     * Preview URL of a stored image, or null while its conversions are pending.
     */
    public function previewUrl(Media $media, string $conversion): ?string
    {
        return $media->hasGeneratedConversion($conversion) ? $media->getFullUrl($conversion) : null;
    }

    private function store(MediaCollection $collection, string $property): void
    {
        $business = $this->business;
        $this->authorize('update', $business);
        $this->resetErrorBag($property);

        $this->validate([$property => $this->rulesFor($collection)], [], [$property => $collection->label()]);

        /** @var TemporaryUploadedFile $file */
        $file = $this->{$property};

        if (! $this->allowUploads(1)) {
            $this->discardTemporary([$file]);
            $this->{$property} = null;

            return;
        }

        /** @var User $actor */
        $actor = Auth::user();

        app(AddBusinessImage::class)->handle($business, $actor, $collection, $file);

        $this->{$property} = null;
        $this->refresh();
    }

    /**
     * @return list<string>
     */
    private function rulesFor(MediaCollection $collection): array
    {
        /** @var array{max_kilobytes: int, min_width: int, min_height: int, logo_min_width: int, logo_min_height: int, max_width: int, max_height: int, allowed_extensions: list<string>} $media */
        $media = config('avytra.media');

        $minWidth = $collection === MediaCollection::Logo ? $media['logo_min_width'] : $media['min_width'];
        $minHeight = $collection === MediaCollection::Logo ? $media['logo_min_height'] : $media['min_height'];

        return [
            'required',
            'image',
            'mimes:'.implode(',', $media['allowed_extensions']),
            'max:'.$media['max_kilobytes'],
            "dimensions:min_width={$minWidth},min_height={$minHeight},max_width={$media['max_width']},max_height={$media['max_height']}",
        ];
    }

    /**
     * Per-user upload limiter (docs/16). Counts each file.
     */
    private function allowUploads(int $count): bool
    {
        $key = 'image-upload:'.Auth::id();
        $limit = (int) config('avytra.media.upload_rate_limit_per_hour');

        if (RateLimiter::attempts($key) + $count > $limit) {
            Flux::toast(variant: 'warning', text: __('Too many uploads. Try again in a while.'));

            return false;
        }

        for ($i = 0; $i < $count; $i++) {
            RateLimiter::hit($key, 3600);
        }

        return true;
    }

    /**
     * @param  list<TemporaryUploadedFile>  $files
     */
    private function discardTemporary(array $files): void
    {
        foreach ($files as $file) {
            $file->delete();
        }
    }

    private function mediaOf(Business $business, int $mediaId): Media
    {
        $media = $business->media->firstWhere('id', $mediaId);

        abort_if($media === null, 404);

        return $media;
    }

    private function refresh(): void
    {
        unset($this->business, $this->hasPendingConversions);
        $this->syncAlts();
    }

    private function syncAlts(): void
    {
        $this->alts = $this->business->media
            ->mapWithKeys(fn (Media $media): array => [(int) $media->getKey() => (string) $media->getCustomProperty('alt', '')])
            ->all();
    }
}; ?>

@php
    /** @var \App\Models\Business $business */
    $business = $this->business;
    $coverMedia = $business->cover();
    $logoMedia = $business->logo();
    $galleryMedia = $business->galleryImages();
    $galleryMax = (int) config('avytra.media.gallery_max');
    $limits = __('JPG, PNG or WebP up to :size MB, at least :width×:height px.', [
        'size' => (int) round(config('avytra.media.max_kilobytes') / 1024),
        'width' => config('avytra.media.min_width'),
        'height' => config('avytra.media.min_height'),
    ]);
@endphp

<div class="flex flex-col gap-10" @if ($this->hasPendingConversions) wire:poll.5s @endif>
    {{-- Cover --}}
    <section class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <flux:heading size="lg" level="2">{{ __('Cover image') }}</flux:heading>
            <flux:text>{{ __('The main photo of the listing: it opens the page and heads every card. Strongly recommended.') }}</flux:text>
        </div>

        @if ($coverMedia)
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start" wire:key="cover-{{ $coverMedia->id }}">
                <div class="relative aspect-[16/10] w-full overflow-hidden rounded-md bg-mist sm:w-72 dark:bg-zinc-800">
                    @if ($url = $this->previewUrl($coverMedia, 'card'))
                        <img src="{{ $url }}" alt="{{ $alts[$coverMedia->id] ?? '' }}" width="{{ config('avytra.media.conversions.card.width') }}" height="{{ config('avytra.media.conversions.card.height') }}" class="size-full object-cover" />
                    @else
                        <div class="flex size-full flex-col items-center justify-center gap-2 text-slate">
                            <flux:icon.arrow-path class="size-6 animate-spin" />
                            <span class="text-xs">{{ __('Processing image…') }}</span>
                        </div>
                    @endif
                </div>
                <div class="flex flex-1 flex-col gap-3">
                    <flux:input wire:model.blur="alts.{{ $coverMedia->id }}" :label="__('Alternative text')" :description="__('Describes the photo for screen readers and search engines.')" maxlength="160" />
                    <div>
                        <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="remove({{ $coverMedia->id }})" wire:confirm="{{ __('Remove this image?') }}">{{ __('Remove') }}</flux:button>
                    </div>
                </div>
            </div>
            <flux:file-upload wire:model="cover">
                <flux:file-upload.dropzone :heading="__('Drop another image here or click to browse')" :text="$limits" with-progress inline />
            </flux:file-upload>
        @else
            <flux:file-upload wire:model="cover">
                <flux:file-upload.dropzone icon="photo" :heading="__('Drop the cover image here or click to browse')" :text="$limits" with-progress />
            </flux:file-upload>
        @endif
        <flux:error name="cover" />
    </section>

    {{-- Gallery --}}
    <section class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <flux:heading size="lg" level="2">{{ __('Gallery') }}</flux:heading>
            <flux:text>{{ __('Up to :max photos. Drag them to change the order; the first ones matter most.', ['max' => $galleryMax]) }}</flux:text>
        </div>

        @if ($galleryMedia->isNotEmpty())
            <ul wire:sort="reorder" class="grid gap-4 sm:grid-cols-2">
                @foreach ($galleryMedia as $media)
                    <li wire:key="gallery-{{ $media->id }}" wire:sort:item="{{ $media->id }}" class="flex flex-col gap-3 rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <button type="button" wire:sort:handle class="mt-1 cursor-grab text-zinc-400 hover:text-ink active:cursor-grabbing dark:hover:text-white" aria-label="{{ __('Drag to reorder') }}">
                                <flux:icon.bars-3 variant="micro" />
                            </button>
                            <div class="relative aspect-[16/10] w-full overflow-hidden rounded-sm bg-mist dark:bg-zinc-800">
                                @if ($url = $this->previewUrl($media, 'thumb'))
                                    <img src="{{ $url }}" alt="{{ $alts[$media->id] ?? '' }}" width="{{ config('avytra.media.conversions.thumb.width') }}" height="{{ config('avytra.media.conversions.thumb.height') }}" class="size-full object-cover" draggable="false" />
                                @else
                                    <div class="flex size-full flex-col items-center justify-center gap-2 text-slate">
                                        <flux:icon.arrow-path class="size-6 animate-spin" />
                                        <span class="text-xs">{{ __('Processing image…') }}</span>
                                    </div>
                                @endif
                                <span class="absolute start-2 top-2 rounded-full bg-ink/80 px-2 py-0.5 text-xs font-semibold text-white">{{ $loop->iteration }}</span>
                            </div>
                        </div>
                        <div class="flex items-end gap-2" wire:sort:ignore>
                            <flux:input wire:model.blur="alts.{{ $media->id }}" :label="__('Alternative text')" label:sr-only :placeholder="__('Alternative text')" maxlength="160" size="sm" class="flex-1" />
                            <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="remove({{ $media->id }})" wire:confirm="{{ __('Remove this image?') }}" :aria-label="__('Remove')" />
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($galleryMedia->count() < $galleryMax)
            <flux:file-upload wire:model="gallery" multiple>
                <flux:file-upload.dropzone icon="photo" :heading="__('Drop photos here or click to browse')" :text="$limits" with-progress :inline="$galleryMedia->isNotEmpty()" />
            </flux:file-upload>
        @else
            <flux:callout icon="information-circle" variant="secondary" inline>
                <flux:callout.text>{{ __('The gallery is full: up to :max images per business.', ['max' => $galleryMax]) }}</flux:callout.text>
            </flux:callout>
        @endif
        <flux:error name="gallery" />
        @foreach ($errors->get('gallery.*') as $messages)
            @foreach ($messages as $message)
                <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @endforeach
        @endforeach
    </section>

    {{-- Logo --}}
    <section class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <flux:heading size="lg" level="2">{{ __('Logo') }}</flux:heading>
            <flux:text>{{ __('Optional. Square works best; it is shown small next to the business name.') }}</flux:text>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
            @if ($logoMedia)
                <div class="flex items-start gap-3" wire:key="logo-{{ $logoMedia->id }}">
                    <div class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                        @if ($url = $this->previewUrl($logoMedia, 'logo'))
                            <img src="{{ $url }}" alt="{{ $alts[$logoMedia->id] ?? '' }}" class="max-h-full max-w-full object-contain" />
                        @else
                            <flux:icon.arrow-path class="size-6 animate-spin text-slate" />
                        @endif
                    </div>
                    <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="remove({{ $logoMedia->id }})" wire:confirm="{{ __('Remove this image?') }}">{{ __('Remove') }}</flux:button>
                </div>
            @endif
            <div class="flex-1">
                <flux:file-upload wire:model="logo">
                    <flux:file-upload.dropzone
                        icon="photo"
                        :heading="$logoMedia ? __('Drop another logo here or click to browse') : __('Drop the logo here or click to browse')"
                        :text="__('JPG, PNG or WebP up to :size MB, at least :width×:height px.', ['size' => (int) round(config('avytra.media.max_kilobytes') / 1024), 'width' => config('avytra.media.logo_min_width'), 'height' => config('avytra.media.logo_min_height')])"
                        with-progress
                        inline
                    />
                </flux:file-upload>
            </div>
        </div>
        <flux:error name="logo" />
    </section>
</div>
