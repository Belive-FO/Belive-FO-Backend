<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supabase pooler (6543) + DDL: avoid wrapping in a DB transaction so the pool
     * does not reuse an aborted transaction block.
     *
     * @var bool
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     *
     * Canonical HR / directory id (may match Lark open_id or be synced from Supabase).
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'employee_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->nullable()->after('lark_open_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'employee_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('employee_id');
        });
    }
};
