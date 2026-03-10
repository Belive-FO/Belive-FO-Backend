<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_mileage_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('from_location', 200);
            $table->string('to_location', 200);
            $table->decimal('distance_km', 8, 2);
            $table->decimal('rate_per_km', 6, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_mileage_details');
    }
};
