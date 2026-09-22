<?php

namespace App\Services\PressReleases;

use App\Exceptions\PressReleaseExtractionException;
use App\Models\PressReleaseAttachment;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;
use XMLReader;
use ZipArchive;

class AttachmentTextExtractor
{
    public function __construct(private readonly Parser $pdfParser) {}

    public function extract(PressReleaseAttachment $attachment): ?string
    {
        if (! in_array($attachment->extension, ['pdf', 'docx'], true)) {
            return null;
        }

        try {
            $contents = $this->disk()->get($attachment->storage_path);
            $text = match ($attachment->extension) {
                'pdf' => $this->pdfParser->parseContent($contents)->getText(),
                'docx' => $this->extractDocx($contents),
            };
        } catch (Throwable $exception) {
            throw new PressReleaseExtractionException(
                "No se pudo extraer el texto de {$attachment->original_filename}.",
                previous: $exception,
            );
        }

        return $this->normalizeAndLimit($text);
    }

    private function extractDocx(string $contents): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'press-docx-');
        if ($temporaryPath === false || file_put_contents($temporaryPath, $contents) === false) {
            throw new PressReleaseExtractionException('No se pudo preparar temporalmente el documento DOCX.');
        }

        $zip = new ZipArchive;

        try {
            if ($zip->open($temporaryPath) !== true) {
                throw new PressReleaseExtractionException('El documento DOCX no es un archivo válido.');
            }

            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                throw new PressReleaseExtractionException('El documento DOCX no contiene texto procesable.');
            }

            return $this->docxXmlToText($xml);
        } finally {
            if ($zip->status === ZipArchive::ER_OK) {
                $zip->close();
            }
            @unlink($temporaryPath);
        }
    }

    private function docxXmlToText(string $xml): string
    {
        $reader = new XMLReader;
        if (! $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new PressReleaseExtractionException('El XML interno del DOCX no es válido.');
        }

        $text = '';
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) {
                $text .= $reader->value;
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'p') {
                $text .= "\n";
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'tab') {
                $text .= "\t";
            }
        }
        $reader->close();

        return $text;
    }

    private function normalizeAndLimit(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? '';
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? '';
        $text = trim($text);

        return mb_substr($text, 0, config('press_releases.max_extracted_text_chars'));
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('press_releases.disk'));
    }
}
