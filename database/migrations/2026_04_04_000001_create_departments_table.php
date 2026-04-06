<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('short_code', 10)->unique();
            $table->string('color_scheme')->default('slate');
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('status');
            $table->index('name');
        });

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('department_id')
                    ->references('id')
                    ->on('departments')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
            });
        }

        Schema::dropIfExists('departments');
    }
};
