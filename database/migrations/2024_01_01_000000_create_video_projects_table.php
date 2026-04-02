<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_projects', function (Blueprint $table) {
            $table->id();
            $table->string('theme', 500);
            $table->enum('status', ['pending', 'processing', 'done', 'error'])
                  ->default('pending')
                  ->index();
            $table->tinyInteger('current_step')->default(0)->unsigned();
            $table->longText('story_text')->nullable();
            $table->json('scenes_json')->nullable();
            $table->string('video_url')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('n8n_execution_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_projects');
    }
};
