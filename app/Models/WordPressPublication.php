<?php

namespace App\Models;

use Database\Factories\WordPressPublicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordPressPublication extends Model
{
    /** @use HasFactory<WordPressPublicationFactory> */
    use HasFactory;

    protected $table = 'wordpress_publications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'wordpress_post_id' => 'integer',
            'last_synced_at' => 'datetime',
            'last_attempted_at' => 'datetime',
        ];
    }

    public function generatedArticle(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class);
    }
}
