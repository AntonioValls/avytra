<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' '.fake()->unique()->numberBetween(1, 9999);

        return [
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * A subsector hanging from the given (or a new) sector.
     */
    public function childOf(?Category $parent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent instanceof Category ? $parent->id : Category::factory(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
