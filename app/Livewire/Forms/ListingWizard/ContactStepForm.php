<?php

namespace App\Livewire\Forms\ListingWizard;

use App\Enums\ContactMethod;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Step 6 of the wizard: how buyers reach the seller (docs/12-contact-system.md).
 * Everything filled in here is public; the preferred method must have its channel.
 */
class ContactStepForm extends Form
{
    public string $contact_name = '';

    public ?string $preferred_contact_method = null;

    public string $contact_email = '';

    public string $contact_phone = '';

    public string $contact_whatsapp = '';

    public bool $whatsapp_same_as_phone = false;

    public string $contact_website_url = '';

    public string $contact_form_url = '';

    public string $contact_other = '';

    public string $contact_notes = '';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $preferred = $this->preferred();
        $phone = ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ().-]{5,28}$/'];

        return [
            'contact_name' => ['nullable', 'string', 'max:80'],
            'preferred_contact_method' => ['required', Rule::enum(ContactMethod::class)],
            'contact_email' => [Rule::requiredIf($preferred === ContactMethod::Email), 'nullable', 'string', 'email', 'max:255'],
            'contact_phone' => [Rule::requiredIf($preferred === ContactMethod::Phone), ...$phone],
            'contact_whatsapp' => [Rule::requiredIf($preferred === ContactMethod::Whatsapp && ! $this->whatsapp_same_as_phone), ...$phone],
            'whatsapp_same_as_phone' => ['boolean'],
            'contact_website_url' => [Rule::requiredIf($preferred === ContactMethod::Website), 'nullable', 'string', 'url:http,https', 'max:255'],
            'contact_form_url' => [Rule::requiredIf($preferred === ContactMethod::ExternalForm), 'nullable', 'string', 'url:http,https', 'max:255'],
            'contact_other' => [Rule::requiredIf($preferred === ContactMethod::Other), 'nullable', 'string', 'max:255'],
            'contact_notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'preferred_contact_method.required' => __('Choose how buyers should contact you.'),
            'contact_email.required' => __('Fill in the email so buyers can use your preferred contact method.'),
            'contact_phone.required' => __('Fill in the phone so buyers can use your preferred contact method.'),
            'contact_whatsapp.required' => __('Fill in the WhatsApp number so buyers can use your preferred contact method.'),
            'contact_website_url.required' => __('Fill in the website so buyers can use your preferred contact method.'),
            'contact_form_url.required' => __('Fill in the form URL so buyers can use your preferred contact method.'),
            'contact_other.required' => __('Explain how buyers should contact you.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'contact_name' => __('contact name'),
            'preferred_contact_method' => __('preferred contact method'),
            'contact_email' => __('contact email'),
            'contact_phone' => __('contact phone'),
            'contact_whatsapp' => __('WhatsApp number'),
            'contact_website_url' => __('contact website'),
            'contact_form_url' => __('form URL'),
            'contact_other' => __('other contact'),
            'contact_notes' => __('contact notes'),
        ];
    }

    public function preferred(): ?ContactMethod
    {
        return $this->preferred_contact_method === null ? null : ContactMethod::tryFrom($this->preferred_contact_method);
    }

    public function fillFromListing(Listing $listing): void
    {
        $this->contact_name = $listing->contact_name ?? '';
        $this->preferred_contact_method = $listing->preferred_contact_method?->value;
        $this->contact_email = $listing->contact_email ?? '';
        $this->contact_phone = $listing->contact_phone ?? '';
        $this->contact_whatsapp = $listing->contact_whatsapp ?? '';
        $this->whatsapp_same_as_phone = $listing->contact_whatsapp !== null && $listing->contact_whatsapp === $listing->contact_phone;
        $this->contact_website_url = $listing->contact_website_url ?? '';
        $this->contact_form_url = $listing->contact_form_url ?? '';
        $this->contact_other = $listing->contact_other ?? '';
        $this->contact_notes = $listing->contact_notes ?? '';
    }

    /**
     * Visible, editable suggestion for owners filling the step for the first time (ADR-009).
     * Nothing is stored until the step is completed.
     */
    public function suggestFromUser(User $user): void
    {
        if ($this->contact_name === '') {
            $this->contact_name = $user->name;
        }

        if ($this->contact_email === '') {
            $this->contact_email = $user->email;
        }

        $this->preferred_contact_method ??= ContactMethod::Email->value;
    }

    public function isEmpty(): bool
    {
        return $this->contact_name === '' && $this->preferred_contact_method === null && $this->contact_email === ''
            && $this->contact_phone === '' && $this->contact_whatsapp === '' && $this->contact_website_url === ''
            && $this->contact_form_url === '' && $this->contact_other === '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $phone = $this->nullable($this->contact_phone);

        return [
            'contact_name' => $this->nullable($this->contact_name),
            'preferred_contact_method' => $this->preferred_contact_method,
            'contact_email' => $this->nullable($this->contact_email),
            'contact_phone' => $phone,
            'contact_whatsapp' => $this->whatsapp_same_as_phone ? $phone : $this->nullable($this->contact_whatsapp),
            'contact_website_url' => $this->nullable($this->contact_website_url),
            'contact_form_url' => $this->nullable($this->contact_form_url),
            'contact_other' => $this->nullable($this->contact_other),
            'contact_notes' => $this->nullable($this->contact_notes),
        ];
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
