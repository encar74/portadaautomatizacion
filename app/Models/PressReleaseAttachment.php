<?php

namespace App\Models;

use App\Enums\AttachmentType;
use Database\Factories\PressReleaseAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PressReleaseAttachment extends Model
{
    /** @use HasFactory<PressReleaseAttachmentFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attachment_type' => AttachmentType::class,
            'size' => 'integer', 'width' => 'integer', 'height' => 'integer', 'is_blocked' => 'boolean',
            'wordpress_media_id' => 'integer',
        ];
    }

    public function pressRelease(): BelongsTo
    {
        return $this->belongsTo(PressRelease::class);
    }
}
