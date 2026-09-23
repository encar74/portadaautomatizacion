<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wordpress_publications', function (Blueprint $table) {
            $table->unsignedBigInteger('wordpress_post_id')->nullable()->change();
            $table->string('idempotency_key', 80)->nullable()->unique()->after('generated_article_id');
            $table->string('sync_status', 24)->default('pending')->after('wordpress_status');
            $table->char('payload_hash', 64)->nullable()->after('sync_status');
            $table->text('last_error')->nullable()->after('payload_hash');
            $table->dateTime('last_attempted_at')->nullable()->after('last_synced_at');
        });

        DB::table('wordpress_publications')->update(['sync_status' => 'synced']);
    }

    public function down(): void
    {
        Schema::table('wordpress_publications', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'sync_status', 'payload_hash', 'last_error', 'last_attempted_at']);
            $table->unsignedBigInteger('wordpress_post_id')->nullable(false)->change();
        });
    }
};
