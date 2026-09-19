<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scheduled_for');
            $table->unsignedTinyInteger('visitors_count')->default(1);
            $table->string('status', 24)->default('scheduled');
            $table->string('check_in_token', 64)->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('missed_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'scheduled_for']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
