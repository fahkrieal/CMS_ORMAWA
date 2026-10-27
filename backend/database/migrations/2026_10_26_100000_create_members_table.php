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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('nim', 50);
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('study_program', 100)->nullable();
            $table->date('join_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'alumni'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Unique NIM per organization (A student can be a member of multiple orgs, but unique within one org)
            $table->unique(['organization_id', 'nim'], 'members_org_nim_unique');
            $table->index(['organization_id', 'status']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
