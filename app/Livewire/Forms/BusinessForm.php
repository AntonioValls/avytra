<?php

namespace App\Livewire\Forms;

use App\Enums\BusinessType;
use App\Enums\EmployeeRange;
use App\Enums\LegalForm;
use App\Enums\WebsiteVisibility;
use App\Models\Business;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Descriptive data of a business (docs/05-business-fields.md). Location and online
 * profile live in their own forms so each business type validates only what it needs.
 */
class BusinessForm extends Form
{
    public string $business_type = BusinessType::Physical->value;

    public ?int $category_id = null;

    public ?int $subcategory_id = null;

    public string $name = '';

    public string $legal_name = '';

    public ?string $legal_form = null;

    public bool $show_legal_form = false;

    public string $tagline = '';

    public string $description = '';

    public ?int $founded_year = null;

    public ?string $employee_range = null;

    public string $website_url = '';

    public string $website_visibility = WebsiteVisibility::Private->value;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'business_type' => ['required', Rule::enum(BusinessType::class)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')->where('is_active', true)],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('parent_id', $this->category_id)->where('is_active', true)],
            'name' => ['required', 'string', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'legal_form' => ['nullable', Rule::enum(LegalForm::class)],
            'show_legal_form' => ['boolean'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:'.config('avytra.limits.description_max_length')],
            'founded_year' => ['nullable', 'integer', 'min:1800', 'max:'.now()->year],
            'employee_range' => ['nullable', Rule::enum(EmployeeRange::class)],
            'website_url' => ['nullable', 'string', 'url:http,https', 'max:255'],
            'website_visibility' => ['required', Rule::enum(WebsiteVisibility::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'business_type' => __('business type'),
            'category_id' => __('sector'),
            'subcategory_id' => __('subsector'),
            'name' => __('trade name'),
            'legal_name' => __('legal name'),
            'legal_form' => __('legal form'),
            'tagline' => __('short description'),
            'description' => __('description'),
            'founded_year' => __('founding year'),
            'employee_range' => __('employees'),
            'website_url' => __('website'),
            'website_visibility' => __('website visibility'),
        ];
    }

    public function fillFromBusiness(Business $business): void
    {
        $this->business_type = $business->business_type->value;
        $this->category_id = $business->category_id;
        $this->subcategory_id = $business->subcategory_id;
        $this->name = $business->name;
        $this->legal_name = $business->legal_name ?? '';
        $this->legal_form = $business->legal_form?->value;
        $this->show_legal_form = $business->show_legal_form;
        $this->tagline = $business->tagline ?? '';
        $this->description = $business->description ?? '';
        $this->founded_year = $business->founded_year;
        $this->employee_range = $business->employee_range?->value;
        $this->website_url = $business->website_url ?? '';
        $this->website_visibility = $business->website_visibility->value;
    }

    public function type(): BusinessType
    {
        return BusinessType::tryFrom($this->business_type) ?? BusinessType::Physical;
    }

    /**
     * Fillable attributes for the Business Actions. Empty strings become null.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'business_type' => $this->business_type,
            'category_id' => $this->category_id,
            'subcategory_id' => $this->subcategory_id,
            'name' => trim($this->name),
            'legal_name' => $this->nullable($this->legal_name),
            'legal_form' => $this->legal_form,
            'show_legal_form' => $this->show_legal_form,
            'tagline' => $this->nullable($this->tagline),
            'description' => $this->nullable($this->description),
            'founded_year' => $this->founded_year,
            'employee_range' => $this->employee_range,
            'website_url' => $this->nullable($this->website_url),
            'website_visibility' => $this->website_visibility,
        ];
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
