<?php

namespace App\Livewire\Forms\ListingWizard;

use App\Models\Listing;
use Livewire\Form;

/**
 * Step 8 of the wizard: the public title. Optional while saving a draft, required to publish.
 */
class PublishStepForm extends Form
{
    public string $title = '';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:'.config('avytra.limits.title_max_length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'title' => __('title'),
        ];
    }

    public function fillFromListing(Listing $listing): void
    {
        $this->title = $listing->title ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $title = trim($this->title);

        return ['title' => $title === '' ? null : $title];
    }
}
