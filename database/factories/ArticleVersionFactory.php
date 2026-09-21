<?php

namespace Database\Factories;

use App\Enums\ArticleVersionOrigin;
use App\Models\GeneratedArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArticleVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'generated_article_id' => GeneratedArticle::factory(), 'version' => 1,
            'origin' => ArticleVersionOrigin::AI, 'headline' => fake()->sentence(),
            'subheadline' => null, 'lead' => fake()->paragraph(), 'body' => fake()->paragraphs(3, true),
            'seo_title' => fake()->sentence(), 'seo_description' => fake()->text(150),
            'category' => null, 'tags' => [], 'ai_provider' => 'fake', 'ai_model' => 'fake-model',
            'prompt_version' => '1.0',
        ];
    }
}
