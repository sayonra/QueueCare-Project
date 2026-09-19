<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('public_number', 32);
            $table->string('status', 24)->default('waiting');
            $table->string('priority', 24)->default('standard');
            $table->timestamp('waiting_since');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['queue_id', 'sequence']);
            $table->unique(['queue_id', 'public_number']);
            $table->index(['queue_id', 'status', 'priority', 'waiting_since']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
