<?php

namespace App\Services;

use App\Enums\AttachmentType;
use App\Enums\PressReleaseStatus;
use App\Enums\ProcessingMode;
use App\Enums\ValidationRisk;
use App\Models\GeneratedArticle;
use App\Models\PressReleaseAttachment;
use App\Models\WordPressPublication;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WordPressDraftService
{
    public function create(GeneratedArticle $article, bool $allowMediumRisk = false, bool $editoriallyApproved = false): WordPressPublication
    {
        $article->loadMissing('pressRelease.pressSource', 'pressRelease.attachments');
        $this->assertPublishable($article, $allowMediumRisk, $editoriallyApproved);
        $publication = $this->reserve($article);

        try {
            $categoryIds = config('wordpress.sync_taxonomies') ? $this->categoryIds($article) : [];
            $tagIds = config('wordpress.sync_taxonomies') ? $this->tagIds($article) : [];
            $mediaIds = config('wordpress.upload_images') ? $this->uploadImages($article) : [];
            $payload = array_filter([
                'status' => 'draft',
                'slug' => $publication->idempotency_key,
                'title' => $article->headline,
                'excerpt' => $article->seo_description,
                'content' => $this->content($article),
                'categories' => $categoryIds,
                'tags' => $tagIds,
                'featured_media' => $mediaIds[0] ?? null,
            ], fn ($value) => $value !== null && $value !== []);
            $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

            if ($publication->wordpress_post_id !== null && hash_equals((string) $publication->payload_hash, $payloadHash)) {
                return $this->markSynced($article, $publication, null, $payloadHash);
            }

            $postId = $publication->wordpress_post_id ?? $this->findPostBySlug($publication->idempotency_key);
            $response = $postId === null
                ? $this->client()->post('/wp-json/wp/v2/posts', $payload)->throw()->json()
                : $this->client()->post('/wp-json/wp/v2/posts/'.$postId, $payload)->throw()->json();
            if (! is_int($response['id'] ?? null)) {
                throw new RuntimeException('WordPress no devolvió el identificador del borrador.');
            }

            return $this->markSynced($article, $publication, $response, $payloadHash);
        } catch (Throwable $exception) {
            $publication->update(['sync_status' => 'failed', 'last_error' => Str::limit($exception->getMessage(), 65535, '')]);

            throw $exception;
        }
    }

    private function assertPublishable(GeneratedArticle $article, bool $allowMediumRisk, bool $editoriallyApproved): void
    {
        $acceptedRisks = $allowMediumRisk ? [ValidationRisk::Low, ValidationRisk::Medium] : [ValidationRisk::Low];
        if (! in_array($article->validation_risk, $acceptedRisks, true)) {
            throw new RuntimeException('WordPress solo admite artículos con riesgo bajo o riesgo medio aprobado editorialmente.');
        }
        if (! $editoriallyApproved && $article->pressRelease->pressSource?->processing_mode !== ProcessingMode::Automatic) {
            throw new RuntimeException('La fuente no está configurada en modo automático.');
        }
    }

    private function reserve(GeneratedArticle $article): WordPressPublication
    {
        return DB::transaction(function () use ($article): WordPressPublication {
            $publication = WordPressPublication::query()->lockForUpdate()->firstOrCreate(
                ['generated_article_id' => $article->id],
                ['idempotency_key' => 'portada-article-'.$article->id, 'wordpress_status' => 'draft', 'sync_status' => 'pending'],
            );
            if (! filled($publication->idempotency_key)) {
                $publication->idempotency_key = 'portada-article-'.$article->id;
            }
            $publication->forceFill(['sync_status' => 'syncing', 'last_error' => null, 'last_attempted_at' => now()])->save();

            return $publication;
        });
    }

    /** @return list<int> */
    private function categoryIds(GeneratedArticle $article): array
    {
        $category = $article->suggested_category ?: $article->pressRelease->pressSource?->default_category;

        return filled($category) ? [$this->resolveTerm('categories', $category)] : [];
    }

    /** @return list<int> */
    private function tagIds(GeneratedArticle $article): array
    {
        $tags = collect($article->suggested_tags ?? [])->merge($article->pressRelease->pressSource?->default_tags ?? [])
            ->filter(fn ($tag) => is_string($tag) && filled($tag))->map(fn ($tag) => trim($tag))
            ->unique(fn ($tag) => mb_strtolower($tag))->take(20);

        return $tags->map(fn ($tag) => $this->resolveTerm('tags', $tag))->values()->all();
    }

    private function resolveTerm(string $taxonomy, string $name): int
    {
        $terms = $this->client()->get('/wp-json/wp/v2/'.$taxonomy, ['search' => $name, 'per_page' => 100, 'context' => 'edit'])->throw()->json();
        foreach ($terms as $term) {
            if (is_int($term['id'] ?? null) && mb_strtolower(trim((string) ($term['name'] ?? ''))) === mb_strtolower(trim($name))) {
                return $term['id'];
            }
        }
        try {
            $created = $this->client()->post('/wp-json/wp/v2/'.$taxonomy, ['name' => $name])->throw()->json();
        } catch (RequestException $exception) {
            $error = $exception->response->json();
            $existingTermId = data_get($error, 'data.term_id');
            if (($error['code'] ?? null) === 'term_exists' && is_numeric($existingTermId)) {
                return (int) $existingTermId;
            }

            throw $exception;
        }
        if (! is_int($created['id'] ?? null)) {
            throw new RuntimeException("WordPress no devolvió el identificador para {$taxonomy}: {$name}.");
        }

        return $created['id'];
    }

    /** @return list<int> */
    private function uploadImages(GeneratedArticle $article): array
    {
        return $article->pressRelease->attachments
            ->filter(fn (PressReleaseAttachment $attachment) => $attachment->attachment_type === AttachmentType::Image && ! $attachment->is_blocked)
            ->map(fn (PressReleaseAttachment $attachment) => $this->uploadImage($attachment))->filter()->values()->all();
    }

    private function uploadImage(PressReleaseAttachment $attachment): ?int
    {
        if ($attachment->wordpress_media_id !== null) {
            return $attachment->wordpress_media_id;
        }
        $disk = Storage::disk(config('press_releases.disk'));
        if (! $disk->exists($attachment->storage_path)) {
            throw new RuntimeException("No se encuentra la imagen {$attachment->original_filename} en el almacenamiento.");
        }
        $stream = $disk->readStream($attachment->storage_path);
        if (! is_resource($stream)) {
            throw new RuntimeException("No se pudo leer la imagen {$attachment->original_filename}.");
        }
        try {
            $response = $this->client()->attach('file', $stream, $attachment->stored_filename, ['Content-Type' => $attachment->mime_type])
                ->post('/wp-json/wp/v2/media', [
                    'title' => pathinfo($attachment->original_filename, PATHINFO_FILENAME),
                    'alt_text' => pathinfo($attachment->original_filename, PATHINFO_FILENAME),
                ])->throw()->json();
        } finally {
            fclose($stream);
        }
        if (! is_int($response['id'] ?? null)) {
            throw new RuntimeException("WordPress no devolvió el identificador de la imagen {$attachment->original_filename}.");
        }
        $attachment->update(['wordpress_media_id' => $response['id']]);

        return $response['id'];
    }

    private function findPostBySlug(string $slug): ?int
    {
        $posts = $this->client()->get('/wp-json/wp/v2/posts', ['slug' => $slug, 'status' => 'draft', 'context' => 'edit', 'per_page' => 1])->throw()->json();

        return is_int($posts[0]['id'] ?? null) ? $posts[0]['id'] : null;
    }

    private function markSynced(GeneratedArticle $article, WordPressPublication $publication, ?array $response, string $payloadHash): WordPressPublication
    {
        return DB::transaction(function () use ($article, $publication, $response, $payloadHash): WordPressPublication {
            $postId = $response['id'] ?? $publication->wordpress_post_id;
            $publication->update([
                'wordpress_post_id' => $postId,
                'wordpress_url' => $response['link'] ?? $publication->wordpress_url,
                'wordpress_edit_url' => rtrim((string) config('wordpress.url'), '/').'/wp-admin/post.php?post='.$postId.'&action=edit',
                'wordpress_status' => 'draft', 'sync_status' => 'synced', 'payload_hash' => $payloadHash,
                'last_error' => null, 'last_synced_at' => now(),
            ]);
            $article->pressRelease()->update(['processing_status' => PressReleaseStatus::WordPressDraftCreated, 'error_message' => null]);

            return $publication->refresh();
        });
    }

    private function client(): PendingRequest
    {
        $url = config('wordpress.url');
        $username = config('wordpress.username');
        $password = config('wordpress.application_password');
        if (! is_string($url) || $url === '' || ! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('Falta configurar la conexión con WordPress.');
        }

        return Http::baseUrl(rtrim($url, '/'))->acceptJson()->withBasicAuth($username, $password)->timeout(config('wordpress.timeout'));
    }

    private function content(GeneratedArticle $article): string
    {
        $paragraphs = preg_split('/\R{2,}/u', trim($article->body)) ?: [];
        $html = '<p><strong>'.e($article->lead).'</strong></p>';
        foreach ($paragraphs as $paragraph) {
            $html .= '<p>'.nl2br(e(trim($paragraph)), false).'</p>';
        }

        return $html;
    }
}
