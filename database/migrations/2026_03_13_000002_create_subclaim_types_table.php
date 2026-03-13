<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subclaim_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_type_id')->constrained('claim_types')->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('label', 100);
            $table->string('description', 255)->nullable();
            $table->decimal('rate', 8, 2)->nullable();
            $table->string('status', 30)->default('active');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['claim_type_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subclaim_types');
    }
};
