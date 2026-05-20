<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('driver')->default('mysql');
            $table->string('host')->default('127.0.0.1');
            $table->smallInteger('port')->default(3306);
            $table->string('database');
            $table->string('username');
            $table->text('password')->nullable();
            $table->boolean('ssl_enabled')->default(false);
            $table->boolean('ssh_enabled')->default(false);
            $table->string('ssh_host')->nullable();
            $table->smallInteger('ssh_port')->nullable();
            $table->string('ssh_user')->nullable();
            $table->text('ssh_key')->nullable();
            $table->string('status')->default('disconnected');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
