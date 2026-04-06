<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $departments = [
            ['name' => 'Human Resources', 'short_code' => 'HR1', 'color_scheme' => 'cyan', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Operations', 'short_code' => 'OPS1', 'color_scheme' => 'emerald', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Finance', 'short_code' => 'FIN1', 'color_scheme' => 'slate', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'General', 'short_code' => 'GEN1', 'color_scheme' => 'violet', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($departments as $row) {
            DB::table('departments')->insertOrIgnore($row);
        }
    }
}
