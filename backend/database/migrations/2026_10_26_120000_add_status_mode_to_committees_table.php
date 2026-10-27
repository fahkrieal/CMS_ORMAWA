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
        Schema::table('committees', function (Blueprint $table) {
            $table->enum('status_mode', ['auto', 'manual'])->default('auto')->after('status');
            $table->string('status_reason', 255)->nullable()->after('status_mode');
            $table->foreignId('status_changed_by')->nullable()->after('status_reason')->constrained('users')->onDelete('set null');
            $table->timestamp('status_changed_at')->nullable()->after('status_changed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->dropForeign(['status_changed_by']);
            $table->dropColumn(['status_mode', 'status_reason', 'status_changed_by', 'status_changed_at']);
        });
    }
};
