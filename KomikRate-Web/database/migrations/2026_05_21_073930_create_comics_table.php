<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comics', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('alternative_title')->nullable();
            $table->string('author');
            $table->string('artist')->nullable();
            $table->enum('type', ['Manga', 'Manhwa', 'Manhua']);
            $table->text('synopsis')->nullable();
            $table->string('cover_image')->nullable();
            $table->enum('status', ['ongoing', 'completed'])->default('ongoing');
            $table->unsignedBigInteger('view_count')->default(0); // untuk "trending/popular"
            $table->timestamps();
            $table->softDeletes(); // Soft delete agar data aman

            // Index untuk query filter yang sering dipakai
            $table->index(['type', 'status']);
            $table->index('view_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comics');
    }
};