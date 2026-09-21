<?php

namespace Database\Factories;

use App\Models\GeneratedArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

class WordPressPublicationFactory extends Factory
{
    public function definition(): array
    {
        $id = fake()->unique()->numberBetween(1, 999999);

        return [
            'generated_article_id' => GeneratedArticle::factory(), 'wordpress_post_id' => $id,
            'wordpress_url' => "https://example.com/?p={$id}",
            'wordpress_edit_url' => "https://example.com/wp-admin/post.php?post={$id}&action=edit",
            'wordpress_status' => 'draft', 'last_synced_at' => now(),
        ];
    }
}
