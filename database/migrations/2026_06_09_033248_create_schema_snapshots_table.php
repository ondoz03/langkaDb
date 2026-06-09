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
        Schema::create('schema_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('connection_id', 36); // ULID from connections.id
            $table->foreign('connection_id')->references('id')->on('connections')->cascadeOnDelete();
            $table->string('label');
            $table->json('schema_data');
            $table->timestamps();

            $table->index('connection_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schema_snapshots');
    }
};
