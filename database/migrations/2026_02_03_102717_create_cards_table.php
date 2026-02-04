<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_column_id')->constrained('board_columns')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->unique(['board_column_id', 'position']);
            $table->index(['board_column_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};

