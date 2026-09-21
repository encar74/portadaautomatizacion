<?php

namespace Database\Factories;

use App\Enums\AttachmentType;
use App\Models\PressRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

class PressReleaseAttachmentFactory extends Factory
{
    public function definition(): array
    {
        $filename = fake()->uuid().'.pdf';

        return [
            'press_release_id' => PressRelease::factory(), 'original_filename' => 'nota.pdf',
            'stored_filename' => $filename, 'mime_type' => 'application/pdf',
            'extension' => 'pdf', 'size' => 1024, 'attachment_type' => AttachmentType::Document,
            'storage_path' => 'press-releases/'.$filename, 'extracted_text' => null,
            'width' => null, 'height' => null, 'wordpress_media_id' => null,
        ];
    }
}
