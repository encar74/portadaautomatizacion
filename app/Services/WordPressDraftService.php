<?php

namespace App\Services;

use App\Enums\PressReleaseStatus;
use App\Enums\ProcessingMode;
use App\Enums\ValidationRisk;
use App\Models\GeneratedArticle;
use App\Models\WordPressPublication;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WordPressDraftService
{
    public function create(
        GeneratedArticle $article,
        bool $allowMediumRisk = false,
        bool $editoriallyApproved = false,
    ): WordPressPublication {
        if ($existing = $article->wordpressPublication()->first()) {
            return $existing;
        }

        $article->loadMissing('pressRelease.pressSource');
        $acceptedRisks = $allowMediumRisk ? [ValidationRisk::Low, ValidationRisk::Medium] : [ValidationRisk::Low];
        if (! in_array($article->validation_risk, $acceptedRisks, true)) {
            throw new RuntimeException('WordPress solo admite automáticamente artículos con riesgo bajo.');
        }
        if (! $editoriallyApproved && $article->pressRelease->pressSource?->processing_mode !== ProcessingMode::Automatic) {
            throw new RuntimeException('La fuente no está configurada en modo automático.');
        }

        $response = $this->client()->post('/wp-json/wp/v2/posts', [
            'status' => 'draft',
            'title' => $article->headline,
            'excerpt' => $article->seo_description,
            'content' => $this->content($article),
        ])->throw()->json();

        if (! is_int($response['id'] ?? null)) {
            throw new RuntimeException('WordPress no devolvió el identificador del borrador.');
        }

        return DB::transaction(function () use ($article, $response): WordPressPublication {
            $publication = $article->wordpressPublication()->firstOrCreate([], [
                'wordpress_post_id' => $response['id'],
                'wordpress_url' => $response['link'] ?? null,
                'wordpress_edit_url' => rtrim((string) config('wordpress.url'), '/').'/wp-admin/post.php?post='.$response['id'].'&action=edit',
                'wordpress_status' => 'draft',
                'last_synced_at' => now(),
            ]);
            $article->pressRelease()->update([
                'processing_status' => PressReleaseStatus::WordPressDraftCreated,
                'error_message' => null,
            ]);

            return $publication;
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

        return Http::baseUrl(rtrim($url, '/'))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($username, $password)
            ->timeout(config('wordpress.timeout'));
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
