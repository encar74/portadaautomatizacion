<?php

namespace App\Services\AI;

use RuntimeException;

class PromptRepository
{
    public function get(string $name, string $version): string
    {
        if (preg_match('/\A[a-z0-9_-]+\z/', $name) !== 1 || preg_match('/\Av[0-9]+\z/', $version) !== 1) {
            throw new RuntimeException('El nombre o la versión del prompt no son válidos.');
        }

        $path = resource_path("prompts/portada/{$version}/{$name}.txt");
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false || trim($contents) === '') {
            throw new RuntimeException("No se encontró el prompt {$name} en la versión {$version}.");
        }

        return trim($contents);
    }
}
