
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comic_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5, pakai tinyInteger agar hemat storage
            $table->text('review_text')->nullable();
            $table->timestamps();
            $table->softDeletes(); // Soft delete untuk moderasi (hapus tanpa hilangkan data)

            // Satu user hanya bisa review satu komik sekali
            $table->unique(['user_id', 'comic_id']);
            $table->index('comic_id'); // Sering di-query untuk avg rating
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings_reviews');
    }
};