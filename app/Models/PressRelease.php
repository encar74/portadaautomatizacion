<?php

namespace App\Models;

use App\Enums\PressReleaseStatus;
use Database\Factories\PressReleaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PressRelease extends Model
{
    /** @use HasFactory<PressReleaseFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'content_extracted_at' => 'datetime',
            'processing_status' => PressReleaseStatus::class,
        ];
    }

    public function pressSource(): BelongsTo
    {
        return $this->belongsTo(PressSource::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PressReleaseAttachment::class);
    }

    public function generatedArticle(): HasOne
    {
        return $this->hasOne(GeneratedArticle::class);
    }

    public function aiExecutions(): HasMany
    {
        return $this->hasMany(AIExecution::class);
    }
}
