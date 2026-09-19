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
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('counter_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('restore_count')->default(0)->after('priority');
            $table->timestamp('called_at')->nullable()->after('waiting_since');
            $table->timestamp('serving_at')->nullable()->after('called_at');
            $table->timestamp('skipped_at')->nullable()->after('serving_at');
            $table->timestamp('completed_at')->nullable()->after('skipped_at');

            $table->index(['counter_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['counter_id', 'status']);
            $table->dropConstrainedForeignId('counter_id');
            $table->dropColumn(['restore_count', 'called_at', 'serving_at', 'skipped_at', 'completed_at']);
        });
    }
};
