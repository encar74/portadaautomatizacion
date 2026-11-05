<?php

namespace App\Console\Commands;

use App\Enums\PressReleaseStatus;
use App\Models\GeneratedArticle;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanTodayDrafts extends Command
{
    protected $signature = 'articles:clean-drafts {date : Fecha de creación YYYY-MM-DD} {--execute : Enviar a la papelera y limpiar Portada}';

    protected $description = 'Revisa o elimina exclusivamente borradores vinculados a Portada creados en una fecha';

    public function handle(): int
    {
        $date = (string) $this->argument('date');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            $this->error('Introduce una fecha válida YYYY-MM-DD.');

            return self::INVALID;
        }
        $start = CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
        $articles = GeneratedArticle::query()->with('wordpressPublication', 'pressRelease')
            ->where('created_at', '>=', $start)->where('created_at', '<', $start->addDay())
            ->whereHas('wordpressPublication', fn ($query) => $query->whereNotNull('wordpress_post_id'))
            ->orderBy('id')->get();
        $this->info("Artículos vinculados creados el {$date}: {$articles->count()}.");
        if ($articles->isEmpty()) {
            return self::SUCCESS;
        }
        if (! filled(config('wordpress.url')) || ! filled(config('wordpress.username')) || ! filled(config('wordpress.application_password'))) {
            $this->error('Falta configurar la conexión con WordPress.');

            return self::FAILURE;
        }
        $client = Http::baseUrl(rtrim((string) config('wordpress.url'), '/'))
            ->withBasicAuth(config('wordpress.username'), config('wordpress.application_password'))
            ->acceptJson()->timeout(config('wordpress.timeout', 30));
        $errors = 0;
        foreach ($articles as $article) {
            $postId = $article->wordpressPublication->wordpress_post_id;
            try {
                $post = $client->get('/wp-json/wp/v2/posts/'.$postId, ['context' => 'edit'])->throw()->json();
                // Never touch published posts or older WordPress drafts, even if linked today.
                $created = CarbonImmutable::parse($post['date_gmt'].'Z')->setTimezone(config('app.timezone'));
                if (! in_array($post['status'] ?? null, ['draft', 'trash'], true) || ! $created->betweenIncluded($start, $start->addDay()->subMicrosecond())) {
                    $this->warn("Conservado artículo {$article->id}, WordPress {$postId}: estado o fecha fuera del lote.");

                    continue;
                }
                $this->line("Artículo {$article->id} → WordPress {$postId}: {$post['status']}");
                if (! $this->option('execute')) {
                    continue;
                }
                $article->load('versions', 'validationAttempts', 'editorialActions', 'aiExecutions');
                $backup = 'cleanup/'.$date.'/article-'.$article->id.'.json';
                if (! Storage::disk('local')->put($backup, json_encode(['article' => $article->toArray(), 'wordpress' => $post], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
                    throw new \RuntimeException('No se pudo guardar la copia de seguridad.');
                }
                if ($post['status'] === 'draft') {
                    $trashed = $client->delete('/wp-json/wp/v2/posts/'.$postId, ['force' => false])->throw()->json();
                    if (($trashed['status'] ?? null) !== 'trash') {
                        throw new \RuntimeException('WordPress no confirmó el envío a la papelera.');
                    }
                }
                DB::transaction(function () use ($article): void {
                    // Keep the original email and its ID to prevent re-import or regeneration.
                    $article->pressRelease()->update(['processing_status' => PressReleaseStatus::Ignored, 'error_message' => 'Artículo retirado durante la limpieza de borradores.']);
                    $article->delete();
                });
            } catch (Throwable $exception) {
                $errors++;
                $this->error("No se limpió artículo {$article->id}: ".$exception->getMessage());
            }
        }
        $this->info($this->option('execute') ? 'Limpieza terminada. Los originales y las copias se conservan.' : 'Solo revisión. Añade --execute para realizar la limpieza con el worker parado.');

        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
