<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin {--name=} {--email=}';

    protected $description = 'Crea un usuario administrador para acceder al dashboard';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Nombre'));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
        $password = (string) $this->secret('Contraseña (mínimo 12 caracteres)');
        $confirmation = (string) $this->secret('Repite la contraseña');

        $validator = Validator::make(compact('name', 'email', 'password', 'confirmation'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'same:confirmation', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create(compact('name', 'email', 'password'));
        $this->info("Administrador {$email} creado correctamente.");

        return self::SUCCESS;
    }
}
