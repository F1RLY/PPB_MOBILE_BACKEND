
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comic_id')->constrained()->cascadeOnDelete();
            $table->string('platform_name'); // Webtoon, MangaPlus, Kakao, dll.
            $table->string('url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_links');
    }
};