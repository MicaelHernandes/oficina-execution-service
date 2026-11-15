<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_jobs', function (Blueprint $table) {
            $table->id();
            // Referências ao OS Service: sem FK (banco próprio do serviço).
            $table->unsignedBigInteger('os_id')->index();
            $table->string('saga_id', 64)->nullable()->unique();
            $table->string('correlation_id')->nullable()->index();
            $table->string('status', 24)->index();
            $table->integer('priority')->default(0);
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('mechanic_user_id')->nullable();
            $table->text('complaint')->nullable();
            $table->jsonb('services')->default('[]');
            $table->jsonb('parts')->default('[]');
            $table->text('failure_reason')->nullable();
            $table->timestamp('enqueued_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // Fila: maior prioridade primeiro, depois a mais antiga.
            $table->index(['status', 'priority', 'enqueued_at']);
        });

        Schema::create('execution_job_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('execution_jobs')->cascadeOnDelete();
            $table->string('status', 24);
            $table->text('note')->nullable();
            $table->timestamp('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_job_events');
        Schema::dropIfExists('execution_jobs');
    }
};
