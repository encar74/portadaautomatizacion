<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_executions', function (Blueprint $table) {
            $table->string('provider_error_code', 100)->nullable();
            $table->timestamp('finished_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('ai_executions', function (Blueprint $table) {
            $table->dropColumn(['provider_error_code', 'finished_at']);
        });
    }
};
