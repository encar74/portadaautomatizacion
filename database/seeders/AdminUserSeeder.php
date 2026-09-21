<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'name' => config('seeding.admin.name'),
            'email' => mb_strtolower(trim((string) config('seeding.admin.email'))),
            'password' => config('seeding.admin.password'),
        ];

        if ($data['name'] === null && $data['email'] === '' && $data['password'] === null) {
            $this->command?->warn('Administrador omitido: configura SEED_ADMIN_NAME, SEED_ADMIN_EMAIL y SEED_ADMIN_PASSWORD si deseas crearlo mediante db:seed.');

            return;
        }

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::min(12)],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('Configuración del administrador inválida: '.$validator->errors()->first());
        }

        User::query()->updateOrCreate(
            ['email' => $data['email']],
            ['name' => $data['name'], 'password' => $data['password']],
        );
    }
}
