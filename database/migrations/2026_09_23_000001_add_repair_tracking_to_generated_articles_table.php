<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->string('repair_status', 24)->nullable()->after('validation_issues');
            $table->dateTime('repair_attempted_at')->nullable()->after('repair_status');
            $table->dateTime('repaired_at')->nullable()->after('repair_attempted_at');
        });
    }

    public function down(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->dropColumn(['repair_status', 'repair_attempted_at', 'repaired_at']);
        });
    }
};
