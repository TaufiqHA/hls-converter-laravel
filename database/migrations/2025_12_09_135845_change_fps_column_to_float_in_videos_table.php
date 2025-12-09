<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change fps column from INTEGER to REAL (float) in PostgreSQL
        DB::statement('ALTER TABLE videos ALTER COLUMN fps TYPE REAL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change fps column back from REAL to INTEGER
        DB::statement('ALTER TABLE videos ALTER COLUMN fps TYPE INTEGER');
    }
};
