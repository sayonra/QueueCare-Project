<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->after('counter_id')->constrained()->nullOnDelete();
            $table->foreignId('preferred_counter_id')->nullable()->after('appointment_id')->constrained('counters')->nullOnDelete();
            $table->unsignedTinyInteger('visitors_count')->default(1)->after('restore_count');
            $table->string('check_in_method', 16)->nullable()->after('visitors_count');
            $table->timestamp('checked_in_at')->nullable()->after('waiting_since');
            $table->timestamp('waiting_since')->nullable()->change();
            $table->unique('appointment_id');
        });

        Schema::table('ticket_status_history', function (Blueprint $table) {
            $table->string('event_type', 32)->default('status')->after('actor_id');
            $table->string('from_priority', 24)->nullable()->after('to_status');
            $table->string('to_priority', 24)->nullable()->after('from_priority');
            $table->foreignId('from_counter_id')->nullable()->after('to_priority')->constrained('counters')->nullOnDelete();
            $table->foreignId('to_counter_id')->nullable()->after('from_counter_id')->constrained('counters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_status_history', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_counter_id');
            $table->dropConstrainedForeignId('to_counter_id');
            $table->dropColumn(['event_type', 'from_priority', 'to_priority']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['appointment_id']);
            $table->dropConstrainedForeignId('preferred_counter_id');
            $table->dropConstrainedForeignId('appointment_id');
            $table->dropColumn(['visitors_count', 'check_in_method', 'checked_in_at']);
            $table->timestamp('waiting_since')->nullable(false)->change();
        });
    }
};
