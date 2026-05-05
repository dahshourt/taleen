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
        Schema::create('sla_calculations', function (Blueprint $table) {
            $table->id(); // auto increment not null
            $table->integer('sla_time'); // int column
            $table->enum('type', ['day', 'hour']); // 'day' or 'hour'

            // status relation
            $table->unsignedBigInteger('status_id');
            $table->foreign('status_id')
                  ->references('id')
                  ->on('statuses');

            // group relation
            $table->unsignedBigInteger('group_id');
            $table->foreign('group_id')
                  ->references('id')
                  ->on('groups');

            $table->timestamps(); // created_at & updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_calculations');
    }
};
