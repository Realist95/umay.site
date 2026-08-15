<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_updates', function (Blueprint $table) {
            $table->foreignUuid('incoming_message_id')->nullable()->after('status')->constrained('messages')->nullOnDelete();
            $table->foreignUuid('response_message_id')->nullable()->after('incoming_message_id')->constrained('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_updates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('response_message_id');
            $table->dropConstrainedForeignId('incoming_message_id');
        });
    }
};
