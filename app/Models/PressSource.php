<?php

namespace App\Models;

use App\Enums\PressSourceMatchType;
use App\Enums\ProcessingMode;
use Database\Factories\PressSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PressSource extends Model
{
    /** @use HasFactory<PressSourceFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'domain', 'match_type', 'is_active', 'processing_mode',
        'default_category', 'default_tags', 'excluded_subjects', 'priority', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'match_type' => PressSourceMatchType::class,
            'is_active' => 'boolean',
            'processing_mode' => ProcessingMode::class,
            'default_tags' => 'array',
            'excluded_subjects' => 'array',
            'priority' => 'integer',
        ];
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null ? null : mb_strtolower(trim($value));
    }

    public function setDomainAttribute(?string $value): void
    {
        $this->attributes['domain'] = $value === null ? null : mb_strtolower(trim($value, " \t\n\r\0\x0B.@"));
    }

    public function excludesSubject(string $subject): bool
    {
        foreach ($this->excluded_subjects ?? [] as $phrase) {
            if (trim($phrase) !== '' && mb_stripos($subject, trim($phrase)) !== false) {
                return true;
            }
        }

        return false;
    }

    public function pressReleases(): HasMany
    {
        return $this->hasMany(PressRelease::class);
    }
}
