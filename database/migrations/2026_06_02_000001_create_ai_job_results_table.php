<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_job_results', function (Blueprint $table) {
            $table->id();
            $table->string('job_class');
            $table->string('connection_id');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->json('input')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->integer('progress')->default(0); // 0-100
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_job_results');
    }
};
