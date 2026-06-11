<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagrams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('connection_id')->nullable();
            $table->json('layout_data')->nullable();
            $table->timestamps();
        });

        Schema::create('diagram_nodes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagram_id')->constrained()->cascadeOnDelete();
            $table->string('table_name');
            $table->float('x_pos')->default(0);
            $table->float('y_pos')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagram_nodes');
        Schema::dropIfExists('diagrams');
    }
};
