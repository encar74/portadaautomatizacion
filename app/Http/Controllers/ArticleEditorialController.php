<?php

namespace App\Http\Controllers;

use App\Enums\ArticleVersionOrigin;
use App\Enums\PressReleaseStatus;
use App\Enums\ValidationRisk;
use App\Jobs\ApplyGuidedArticleCorrection;
use App\Jobs\PublishArticleToWordPress;
use App\Jobs\ValidateArticle;
use App\Models\GeneratedArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArticleEditorialController extends Controller
{
    public function update(Request $request, GeneratedArticle $article): RedirectResponse
    {
        $this->ensureEditable($article);
        $data = $request->validate([
            'headline' => ['required', 'string', 'max:255'],
            'subheadline' => ['nullable', 'string', 'max:255'],
            'lead' => ['required', 'string', 'max:5000'],
            'body' => ['required', 'string', 'max:100000'],
            'seo_title' => ['required', 'string', 'max:255'],
            'seo_description' => ['required', 'string', 'max:1000'],
            'suggested_category' => ['nullable', 'string', 'max:255'],
            'suggested_tags' => ['nullable', 'string', 'max:1000'],
            'editorial_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $tags = collect(explode(',', $data['suggested_tags'] ?? ''))
            ->map(fn ($tag) => trim($tag))->filter()->unique()->values()->all();

        DB::transaction(function () use ($article, $data, $tags, $request): void {
            $locked = GeneratedArticle::query()->lockForUpdate()->findOrFail($article->id);
            $nextVersion = ((int) $locked->versions()->max('version')) + 1;
            $locked->update([
                'headline' => $data['headline'],
                'subheadline' => $data['subheadline'] ?: null,
                'lead' => $data['lead'],
                'body' => $data['body'],
                'seo_title' => $data['seo_title'],
                'seo_description' => $data['seo_description'],
                'suggested_category' => $data['suggested_category'] ?: null,
                'suggested_tags' => $tags,
                'validation_risk' => null,
                'validation_issues' => [],
                'requires_editorial_approval' => true,
            ]);
            $version = $locked->versions()->create([
                'version' => $nextVersion,
                'origin' => ArticleVersionOrigin::Human,
                'created_by' => $request->user()->id,
                'headline' => $locked->headline,
                'subheadline' => $locked->subheadline,
                'lead' => $locked->lead,
                'body' => $locked->body,
                'seo_title' => $locked->seo_title,
                'seo_description' => $locked->seo_description,
                'category' => $locked->suggested_category,
                'tags' => $locked->suggested_tags,
                'editorial_instruction' => $data['editorial_note'] ?? null,
            ]);
            $locked->editorialActions()->create([
                'user_id' => $request->user()->id,
                'type' => 'manual_edit',
                'risk' => $article->validation_risk,
                'notes' => $data['editorial_note'] ?? null,
                'metadata' => ['article_version_id' => $version->id],
            ]);
            $locked->pressRelease()->update([
                'processing_status' => PressReleaseStatus::Processing,
                'error_message' => null,
            ]);
        });

        ValidateArticle::dispatch($article->id);

        return $this->back($article, 'Edición guardada como una nueva versión. Se ha enviado a validación.');
    }

    public function requestAiCorrection(Request $request, GeneratedArticle $article): RedirectResponse
    {
        $this->ensureEditable($article);
        $data = $request->validate(['instruction' => ['required', 'string', 'min:10', 'max:4000']]);
        $action = $article->editorialActions()->create([
            'user_id' => $request->user()->id,
            'type' => 'ai_correction_requested',
            'status' => 'pending',
            'risk' => $article->validation_risk,
            'notes' => $data['instruction'],
        ]);
        $article->pressRelease()->update([
            'processing_status' => PressReleaseStatus::Processing,
            'error_message' => null,
        ]);
        ApplyGuidedArticleCorrection::dispatch($action->id);

        return $this->back($article, 'Corrección solicitada. La IA generará una nueva versión y volverá a validarla.');
    }

    public function publish(Request $request, GeneratedArticle $article): RedirectResponse
    {
        $hasWordPressDraft = $article->wordpressPublication()->whereNotNull('wordpress_post_id')->exists();
        if (! in_array($article->validation_risk, [ValidationRisk::Low, ValidationRisk::Medium], true)) {
            throw ValidationException::withMessages(['publication' => 'Solo se puede aprobar un artículo con riesgo bajo o medio.']);
        }

        $rules = ['justification' => ['nullable', 'string', 'max:2000']];
        if ($article->validation_risk === ValidationRisk::Medium) {
            $rules['justification'] = ['required', 'string', 'min:20', 'max:2000'];
        }
        $data = $request->validate($rules);
        $action = $article->editorialActions()->create([
            'user_id' => $request->user()->id,
            'type' => $hasWordPressDraft
                ? 'wordpress_draft_updated'
                : ($article->validation_risk === ValidationRisk::Medium ? 'medium_risk_override' : 'wordpress_draft_approved'),
            'status' => 'pending',
            'risk' => $article->validation_risk,
            'notes' => $data['justification'] ?? null,
        ]);

        PublishArticleToWordPress::dispatch(
            $article->id,
            $article->validation_risk === ValidationRisk::Medium,
            $action->id,
        );

        return $this->back($article, 'Creación del borrador de WordPress enviada a la cola.');
    }

    private function ensureEditable(GeneratedArticle $article): void
    {
        abort_if(in_array($article->repair_status, ['queued', 'running', 'awaiting_validation'], true), 409, 'La reparación automática todavía está en curso.');
        abort_if($article->editorialActions()->where('status', 'pending')->exists(), 409, 'Ya existe una acción editorial pendiente para este artículo.');
    }

    private function back(GeneratedArticle $article, string $message): RedirectResponse
    {
        return redirect()->route('press-releases.show', $article->press_release_id)
            ->withFragment('articulo')->with('status', $message);
    }
}
