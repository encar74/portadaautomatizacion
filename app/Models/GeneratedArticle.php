<?php

namespace App\Models;

use App\Enums\ValidationRisk;
use Database\Factories\GeneratedArticleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GeneratedArticle extends Model
{
    /** @use HasFactory<GeneratedArticleFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'suggested_tags' => 'array', 'warnings' => 'array',
            'validation_risk' => ValidationRisk::class, 'validation_issues' => 'array',
            'generated_at' => 'datetime',
            'repair_attempted_at' => 'datetime', 'repaired_at' => 'datetime',
        ];
    }

    public function pressRelease(): BelongsTo
    {
        return $this->belongsTo(PressRelease::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ArticleVersion::class)->orderBy('version');
    }

    public function aiExecutions(): HasMany
    {
        return $this->hasMany(AIExecution::class);
    }

    public function validationAttempts(): HasMany
    {
        return $this->hasMany(ArticleValidationAttempt::class)->orderBy('sequence');
    }

    public function wordpressPublication(): HasOne
    {
        return $this->hasOne(WordPressPublication::class);
    }
}
