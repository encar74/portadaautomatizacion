<?php

namespace Database\Factories;

use App\Enums\PressReleaseStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class PressReleaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'press_source_id' => null, 'message_id' => '<'.fake()->uuid().'@example.com>',
            'sender_email' => fake()->safeEmail(), 'sender_name' => fake()->name(),
            'subject' => fake()->sentence(), 'received_at' => fake()->dateTimeBetween('-1 month'),
            'body_text' => fake()->paragraphs(2, true), 'body_html' => null,
            'source_text' => null, 'content_extracted_at' => null,
            'classification' => null, 'processing_status' => PressReleaseStatus::Received,
            'error_message' => null,
        ];
    }
}
