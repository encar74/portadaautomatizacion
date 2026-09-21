<?php

namespace Database\Factories;

use App\Enums\AIExecutionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class AIExecutionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'press_release_id' => null, 'generated_article_id' => null,
            'provider' => 'fake', 'model' => 'fake-model', 'operation' => 'generation',
            'input_tokens' => 100, 'output_tokens' => 200, 'total_tokens' => 300,
            'estimated_cost' => 0.001, 'duration_ms' => 500,
            'status' => AIExecutionStatus::Successful, 'error_message' => null,
            'prompt_version' => '1.0',
        ];
    }
}
