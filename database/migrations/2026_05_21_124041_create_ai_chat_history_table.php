<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_history', function (Blueprint $table) {
            $table->id();
            $table->string('connection_id');
            $table->string('connection_name');
            $table->string('provider')->nullable();
            $table->text('user_message');
            $table->longText('ai_response');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_history');
    }
};
