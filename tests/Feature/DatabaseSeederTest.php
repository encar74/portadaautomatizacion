<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_create_test_credentials_by_default(): void
    {
        config()->set('seeding.admin', ['name' => null, 'email' => null, 'password' => null]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_seeding_is_idempotent_and_hashes_the_password(): void
    {
        config()->set('seeding.admin', [
            'name' => 'Administradora',
            'email' => 'ADMIN@PORTADA.INFO',
            'password' => 'very-secure-password',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $user = User::firstOrFail();
        $this->assertSame('admin@portada.info', $user->email);
        $this->assertTrue(Hash::check('very-secure-password', $user->password));
    }
}
