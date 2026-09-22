<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_release_attachments', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false)->after('extracted_text');
            $table->string('blocked_reason')->nullable()->after('is_blocked');
            $table->index('is_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('press_release_attachments', function (Blueprint $table) {
            $table->dropIndex(['is_blocked']);
            $table->dropColumn(['is_blocked', 'blocked_reason']);
        });
    }
};
