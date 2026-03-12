<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('claim_categories')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('type', 50); // receipt, mileage, business-travel, miscellaneous, office, outstation, renovation, special-mileage, transportation
            $table->decimal('amount', 12, 2);
            $table->date('claim_date');
            $table->text('description')->nullable();
            $table->string('merchant', 150)->nullable();
            $table->string('status', 20)->default('draft'); // draft, pending, approved, rejected, paid
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['claim_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
