<?php

namespace App\Console\Commands;

use App\Enums\PressReleaseStatus;
use App\Jobs\GenerateArticle;
use App\Models\PressRelease;
use Illuminate\Console\Command;

class GeneratePressReleaseArticle extends Command
{
    protected $signature = 'press-releases:generate
        {pressRelease : ID de la nota de prensa}
        {--retry : Permite reintentar una generación que terminó en error}';

    protected $description = 'Encola la generación de una noticia para una nota de prensa procesada';

    public function handle(): int
    {
        $release = PressRelease::query()->find($this->argument('pressRelease'));
        if ($release === null) {
            $this->error('No se encontró la nota de prensa.');

            return self::FAILURE;
        }
        if ($release->processing_status === PressReleaseStatus::Error && $this->option('retry') && filled($release->source_text)) {
            $release->update([
                'processing_status' => PressReleaseStatus::Processed,
                'error_message' => null,
            ]);
        }
        if ($release->processing_status !== PressReleaseStatus::Processed || ! filled($release->source_text)) {
            $this->error('La nota de prensa debe estar procesada y contener texto fuente.');

            return self::FAILURE;
        }
        if ($release->generatedArticle()->exists()) {
            $this->warn('La nota de prensa ya tiene una noticia generada.');

            return self::SUCCESS;
        }

        GenerateArticle::dispatch($release->id);
        $this->info('Generación encolada.');

        return self::SUCCESS;
    }
}
