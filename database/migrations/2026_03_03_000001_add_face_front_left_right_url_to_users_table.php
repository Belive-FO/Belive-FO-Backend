<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'face_front_url')) {
                $table->string('face_front_url')->nullable()->after('avatar_url');
            }
            if (! Schema::hasColumn('users', 'face_left_url')) {
                $table->string('face_left_url')->nullable()->after('face_front_url');
            }
            if (! Schema::hasColumn('users', 'face_right_url')) {
                $table->string('face_right_url')->nullable()->after('face_left_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'face_front_url')) {
                $table->dropColumn('face_front_url');
            }
            if (Schema::hasColumn('users', 'face_left_url')) {
                $table->dropColumn('face_left_url');
            }
            if (Schema::hasColumn('users', 'face_right_url')) {
                $table->dropColumn('face_right_url');
            }
        });
    }
};
