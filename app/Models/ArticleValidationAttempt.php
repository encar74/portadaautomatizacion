<?php

namespace App\Models;

use App\Enums\ValidationRisk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleValidationAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'risk' => ValidationRisk::class,
            'issues' => 'array',
            'warnings' => 'array',
            'sequence' => 'integer',
        ];
    }

    public function generatedArticle(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class);
    }

    public function articleVersion(): BelongsTo
    {
        return $this->belongsTo(ArticleVersion::class);
    }
}
