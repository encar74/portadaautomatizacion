<?php

namespace App\Console\Commands;

use App\Contracts\MailboxClientInterface;
use App\Services\PressReleases\PressReleaseService;
use Illuminate\Console\Command;
use Throwable;

class FetchPressReleases extends Command
{
    protected $signature = 'press-releases:fetch {--limit=50 : Número máximo de mensajes por ejecución}';

    protected $description = 'Importa mensajes del proveedor de correo configurado';

    public function handle(PressReleaseService $service): int
    {
        if (! app()->bound(MailboxClientInterface::class)) {
            $this->error('No hay ningún proveedor de correo configurado para MailboxClientInterface.');

            return self::FAILURE;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('El límite debe ser un número entre 1 y 1000.');

            return self::INVALID;
        }

        /** @var MailboxClientInterface $mailbox */
        $mailbox = app(MailboxClientInterface::class);
        $imported = 0;
        $duplicates = 0;
        $errors = 0;

        foreach ($mailbox->messages() as $message) {
            if (($imported + $duplicates + $errors) >= $limit) {
                break;
            }

            try {
                $result = $service->ingest($message);
                $mailbox->markImported($message->messageId);
                $result->wasCreated ? $imported++ : $duplicates++;
            } catch (Throwable $exception) {
                $errors++;
                report($exception);
                $this->warn("No se pudo importar {$message->messageId}: {$exception->getMessage()}");
            }
        }

        $this->info("Importación terminada: {$imported} nuevos, {$duplicates} duplicados, {$errors} errores.");

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
