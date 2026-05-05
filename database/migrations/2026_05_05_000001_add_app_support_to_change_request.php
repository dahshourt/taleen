<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures the `app_support` column exists on the `change_request` table.
 *
 * The column drives the group-assignment override:
 *   app_support = 1  →  CR uses an Application Support path
 *   app_support = 0  →  default path
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('change_request', 'app_support')) {
            Schema::table('change_request', function (Blueprint $table) {
                $table->tinyInteger('app_support')
                      ->default(0)
                      ->after('workflow_type_id')
                      ->comment('1 = Application Support path is active');
            });
        }
    }

    public function down(): void
    {
        Schema::table('change_request', function (Blueprint $table) {
            $table->dropColumn('app_support');
        });
    }
};
