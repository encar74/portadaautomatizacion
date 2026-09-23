<?php

namespace App\Console\Commands;

use App\Enums\PressReleaseStatus;
use App\Jobs\ValidateArticle;
use App\Models\PressRelease;
use Illuminate\Console\Command;

class ValidatePressReleaseArticle extends Command
{
    protected $signature = 'press-releases:validate
        {pressRelease : ID de la nota de prensa}
        {--retry : Permite reintentar una validación que terminó en error}';

    protected $description = 'Encola la validación factual de una noticia generada';

    public function handle(): int
    {
        $release = PressRelease::query()->with('generatedArticle')->find($this->argument('pressRelease'));
        if ($release === null) {
            $this->error('No se encontró la nota de prensa.');

            return self::FAILURE;
        }
        if ($release->generatedArticle === null) {
            $this->error('La nota de prensa todavía no tiene una noticia generada.');

            return self::FAILURE;
        }
        if ($release->generatedArticle->validation_risk !== null) {
            $this->warn('La noticia ya está validada.');

            return self::SUCCESS;
        }
        if ($release->processing_status === PressReleaseStatus::Error && ! $this->option('retry')) {
            $this->error('La validación anterior falló. Utiliza --retry para volver a intentarlo.');

            return self::FAILURE;
        }

        ValidateArticle::dispatch($release->generatedArticle->id);
        $this->info('Validación factual encolada.');

        return self::SUCCESS;
    }
}
