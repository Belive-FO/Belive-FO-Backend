<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE claims DROP CONSTRAINT IF EXISTS claims_category_id_foreign');
        DB::statement('ALTER TABLE claims ALTER COLUMN category_id DROP NOT NULL');
        DB::statement('ALTER TABLE claims ADD CONSTRAINT claims_category_id_foreign FOREIGN KEY (category_id) REFERENCES claim_categories(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE claims DROP CONSTRAINT IF EXISTS claims_category_id_foreign');
        DB::statement('ALTER TABLE claims ALTER COLUMN category_id SET NOT NULL');
        DB::statement('ALTER TABLE claims ADD CONSTRAINT claims_category_id_foreign FOREIGN KEY (category_id) REFERENCES claim_categories(id) ON DELETE CASCADE');
    }
};
