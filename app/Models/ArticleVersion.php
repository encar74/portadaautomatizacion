<?php

namespace App\Models;

use App\Enums\ArticleVersionOrigin;
use Database\Factories\ArticleVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleVersion extends Model
{
    /** @use HasFactory<ArticleVersionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['origin' => ArticleVersionOrigin::class, 'tags' => 'array', 'version' => 'integer'];
    }

    public function generatedArticle(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class);
    }
}
