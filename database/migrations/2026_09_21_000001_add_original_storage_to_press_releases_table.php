<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_releases', function (Blueprint $table) {
            $table->string('raw_message_path')->nullable()->after('body_html');
        });
    }

    public function down(): void
    {
        Schema::table('press_releases', function (Blueprint $table) {
            $table->dropColumn('raw_message_path');
        });
    }
};
