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
            ['name' => 'Human Resource', 'short_code' => 'HR'],
            ['name' => 'Marketing', 'short_code' => 'Mar'],
            ['name' => 'Software & IT', 'short_code' => 'IT'],
            ['name' => 'Finance & Account', 'short_code' => 'FA'],
            ['name' => 'Indoor Sales', 'short_code' => 'IS'],
            ['name' => 'Outdoor Sales', 'short_code' => 'OS'],
            ['name' => 'Customer Service', 'short_code' => 'CS'],
            ['name' => 'Business Development', 'short_code' => 'BD'],
        ];

        foreach ($departments as $row) {
            DB::table('departments')->insertOrIgnore([
                'name' => $row['name'],
                'short_code' => $row['short_code'],
                'status' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
