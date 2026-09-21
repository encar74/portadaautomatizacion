<?php

namespace Database\Factories;

use App\Enums\ValidationRisk;
use App\Models\PressRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

class GeneratedArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'press_release_id' => PressRelease::factory(), 'headline' => fake()->sentence(),
            'subheadline' => null, 'lead' => fake()->paragraph(), 'body' => fake()->paragraphs(4, true),
            'seo_title' => fake()->sentence(), 'seo_description' => fake()->text(150),
            'suggested_category' => null, 'suggested_tags' => ['prensa'], 'location' => null,
            'warnings' => [], 'validation_risk' => ValidationRisk::Low,
            'validation_issues' => [], 'generated_at' => now(),
        ];
    }
}
