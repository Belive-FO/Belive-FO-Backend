<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('step_kind', 50);
            $table->string('status', 20)->default('pending');
            $table->json('eligible_approver_ids')->nullable();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['claim_id', 'level']);
            $table->index(['claim_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_approvals');
    }
};
