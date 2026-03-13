<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->foreignId('claim_type_id')->nullable()->after('type')->constrained('claim_types')->nullOnDelete();
            $table->foreignId('subclaim_type_id')->nullable()->after('claim_type_id')->constrained('subclaim_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropForeign(['claim_type_id']);
            $table->dropForeign(['subclaim_type_id']);
        });
    }
};
