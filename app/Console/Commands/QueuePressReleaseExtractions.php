<?php

namespace App\Console\Commands;

use App\Enums\PressReleaseStatus;
use App\Enums\ProcessingMode;
use App\Jobs\ExtractPressReleaseContent;
use App\Models\PressRelease;
use Illuminate\Console\Command;

class QueuePressReleaseExtractions extends Command
{
    protected $signature = 'press-releases:queue-extraction
        {--retry-errors : Vuelve a encolar también las extracciones fallidas}';

    protected $description = 'Encola la extracción de contenido pendiente de las notas de prensa';

    public function handle(): int
    {
        $statuses = [PressReleaseStatus::Received];
        if ($this->option('retry-errors')) {
            $statuses[] = PressReleaseStatus::Error;
        }

        $queued = 0;
        PressRelease::query()
            ->whereIn('processing_status', $statuses)
            ->whereHas('pressSource', fn ($query) => $query
                ->where('is_active', true)
                ->where('processing_mode', '!=', ProcessingMode::Ignore))
            ->orderBy('id')
            ->chunkById(100, function ($releases) use (&$queued): void {
                foreach ($releases as $release) {
                    $release->update(['processing_status' => PressReleaseStatus::Queued]);
                    ExtractPressReleaseContent::dispatch($release->id);
                    $queued++;
                }
            });

        $this->info("Extracciones encoladas: {$queued}.");

        return self::SUCCESS;
    }
}
