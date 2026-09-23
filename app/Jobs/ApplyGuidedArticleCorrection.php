<?php

namespace App\Jobs;

use App\Models\ArticleEditorialAction;
use App\Services\AI\GuidedArticleCorrectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ApplyGuidedArticleCorrection implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly int $editorialActionId)
    {
        $this->onQueue(config('press_releases.queue'));
    }

    public function handle(GuidedArticleCorrectionService $service): void
    {
        $article = $service->correct(ArticleEditorialAction::query()->findOrFail($this->editorialActionId));
        ValidateArticle::dispatch($article->id);
    }
}
