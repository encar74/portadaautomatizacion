<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->string('validation_risk', 16)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('generated_articles')->whereNull('validation_risk')->update(['validation_risk' => 'medium']);

        Schema::table('generated_articles', function (Blueprint $table) {
            $table->string('validation_risk', 16)->nullable(false)->change();
        });
    }
};
