<?php

namespace App\Models;

use App\Enums\AIExecutionStatus;
use Database\Factories\AIExecutionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIExecution extends Model
{
    /** @use HasFactory<AIExecutionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'ai_executions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => AIExecutionStatus::class, 'estimated_cost' => 'decimal:6',
            'input_tokens' => 'integer', 'output_tokens' => 'integer',
            'total_tokens' => 'integer', 'duration_ms' => 'integer',
        ];
    }

    public function pressRelease(): BelongsTo
    {
        return $this->belongsTo(PressRelease::class);
    }

    public function generatedArticle(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class);
    }
}
