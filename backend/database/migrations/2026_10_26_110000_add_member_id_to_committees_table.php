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
            $table->foreignId('member_id')->nullable()->after('user_id')->constrained('members')->onDelete('set null');
            $table->date('start_date')->nullable()->after('period');
            $table->date('end_date')->nullable()->after('start_date');
            $table->text('notes')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->dropColumn(['member_id', 'start_date', 'end_date', 'notes']);
        });
    }
};
