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
        Schema::table('users', function (Blueprint $table) {
            $table->string('lark_user_id')->nullable()->unique()->after('email');
            $table->string('lark_open_id')->nullable()->unique()->after('lark_user_id');
            $table->unsignedBigInteger('department_id')->nullable()->after('lark_open_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['lark_user_id', 'lark_open_id', 'department_id']);
        });
    }
};
