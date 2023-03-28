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
        Schema::create('files', function (Blueprint $table) {
            $table->string('path')->unique();
            $table->string('file_type');
            $table->float('file_size');
            $table->string('file_name');
            $table->string('creator');
            $table->json('look_groups');
            $table->json('look_users');
            $table->json('move_groups');
            $table->json('move_users');
            $table->json('edit_groups');
            $table->json('edit_users');
            $table->json('file_extensions')->nullable();
            $table->timestamps();

            $table->primary('path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
