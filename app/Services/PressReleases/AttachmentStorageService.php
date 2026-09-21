<?php

namespace App\Services\PressReleases;

use App\DTOs\PressReleaseAttachmentData;
use App\Enums\AttachmentType;
use App\Exceptions\InvalidPressReleaseAttachment;
use App\Models\PressRelease;
use App\Models\PressReleaseAttachment;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class AttachmentStorageService
{
    /** @return list<string> */
    public function store(PressRelease $pressRelease, array $attachments): array
    {
        if (count($attachments) > config('press_releases.max_attachments')) {
            throw new InvalidPressReleaseAttachment('El email supera el número máximo de adjuntos permitido.');
        }

        $storedPaths = [];

        try {
            foreach ($attachments as $attachment) {
                if (! $attachment instanceof PressReleaseAttachmentData) {
                    throw new InvalidPressReleaseAttachment('El adjunto recibido no tiene un formato válido.');
                }

                $storedPaths[] = $this->storeOne($pressRelease, $attachment);
            }
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                $this->disk()->delete($path);
            }

            throw $exception;
        }

        return $storedPaths;
    }

    private function storeOne(PressRelease $pressRelease, PressReleaseAttachmentData $attachment): string
    {
        $size = strlen($attachment->content);

        if ($size === 0 || $size > config('press_releases.max_attachment_bytes')) {
            throw new InvalidPressReleaseAttachment("El adjunto {$attachment->originalFilename} está vacío o supera el tamaño permitido.");
        }

        $mimeType = $this->detectMimeType($attachment->content);
        $definition = config("press_releases.allowed_mime_types.{$mimeType}");

        if ($definition === null) {
            throw new InvalidPressReleaseAttachment("El tipo real del adjunto {$attachment->originalFilename} no está permitido ({$mimeType}).");
        }

        $storedFilename = Str::uuid().'.'.$definition['extension'];
        $path = "press-releases/{$pressRelease->id}/attachments/{$storedFilename}";

        if (! $this->disk()->put($path, $attachment->content)) {
            throw new InvalidPressReleaseAttachment("No se pudo almacenar el adjunto {$attachment->originalFilename}.");
        }

        [$width, $height] = $definition['type'] === 'image'
            ? $this->imageDimensions($attachment->content, $attachment->originalFilename)
            : [null, null];

        PressReleaseAttachment::create([
            'press_release_id' => $pressRelease->id,
            'original_filename' => $this->safeOriginalFilename($attachment->originalFilename),
            'stored_filename' => $storedFilename,
            'mime_type' => $mimeType,
            'extension' => $definition['extension'],
            'size' => $size,
            'attachment_type' => AttachmentType::from($definition['type']),
            'storage_path' => $path,
            'width' => $width,
            'height' => $height,
        ]);

        return $path;
    }

    private function detectMimeType(string $content): string
    {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: 'application/octet-stream';

        if (in_array($mimeType, ['application/zip', 'application/octet-stream'], true) && $this->isDocx($content)) {
            return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }

        return $mimeType;
    }

    private function isDocx(string $content): bool
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'press-docx-');

        if ($temporaryPath === false || file_put_contents($temporaryPath, $content) === false) {
            return false;
        }

        $zip = new ZipArchive;
        $opened = $zip->open($temporaryPath) === true;
        $isDocx = $opened && $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('word/document.xml') !== false;

        if ($opened) {
            $zip->close();
        }

        @unlink($temporaryPath);

        return $isDocx;
    }

    /** @return array{int, int} */
    private function imageDimensions(string $content, string $filename): array
    {
        $dimensions = @getimagesizefromstring($content);

        if ($dimensions === false) {
            throw new InvalidPressReleaseAttachment("La imagen {$filename} no es válida.");
        }

        return [$dimensions[0], $dimensions[1]];
    }

    private function safeOriginalFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', trim($filename)));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?: 'adjunto';

        return Str::limit($filename, 255, '');
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('press_releases.disk'));
    }
}
