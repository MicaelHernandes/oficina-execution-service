<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->string('type', 64);
            $table->string('saga_id', 64)->nullable()->index();
            $table->jsonb('body');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Relay busca as pendentes em ordem de gravação.
            $table->index(['published_at', 'id']);
        });

        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->uuid('message_id')->primary();
            $table->string('type', 64);
            $table->string('source', 64);
            $table->timestamp('processed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('outbox_messages');
    }
};
