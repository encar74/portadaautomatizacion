<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_releases', function (Blueprint $table) {
            $table->longText('source_text')->nullable()->after('raw_message_path');
            $table->timestamp('content_extracted_at')->nullable()->after('source_text');
        });
    }

    public function down(): void
    {
        Schema::table('press_releases', function (Blueprint $table) {
            $table->dropColumn(['source_text', 'content_extracted_at']);
        });
    }
};
