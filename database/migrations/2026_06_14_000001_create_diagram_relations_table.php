<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagram_relations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagram_id')->constrained()->cascadeOnDelete();
            $table->string('from_table');
            $table->string('from_column');
            $table->string('to_table');
            $table->string('to_column');
            $table->string('type')->default('belongs_to');
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagram_relations');
    }
};
