<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_types', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('subclaim_types', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('claim_types', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('subclaim_types', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
