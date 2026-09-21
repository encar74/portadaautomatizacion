<?php

namespace Database\Factories;

use App\Enums\PressSourceMatchType;
use App\Enums\ProcessingMode;
use Illuminate\Database\Eloquent\Factories\Factory;

class PressSourceFactory extends Factory
{
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'name' => fake()->company(), 'email' => $email, 'domain' => null,
            'match_type' => PressSourceMatchType::ExactEmail,
            'is_active' => true, 'processing_mode' => ProcessingMode::Automatic,
            'default_category' => null, 'default_tags' => [],
            'priority' => 0, 'notes' => null,
        ];
    }

    public function forDomain(?string $domain = null): static
    {
        return $this->state(fn () => [
            'email' => null, 'domain' => $domain ?? fake()->unique()->domainName(),
            'match_type' => PressSourceMatchType::Domain,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
