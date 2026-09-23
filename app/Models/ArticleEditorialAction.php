<?php

namespace App\Models;

use App\Enums\ValidationRisk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleEditorialAction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['risk' => ValidationRisk::class, 'metadata' => 'array'];
    }

    public function generatedArticle(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
