<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel untuk menyimpan reply/balasan terhadap reviews
     * Mendukung nested replies (parent_reply_id)
     */
    public function up(): void
    {
        Schema::create('replies', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->unsignedBigInteger('review_id'); // Review yang di-reply
            $table->unsignedBigInteger('user_id');   // User yang membuat reply
            $table->unsignedBigInteger('parent_reply_id')->nullable(); // Untuk nested replies
            
            // Content
            $table->text('content'); // Isi reply (max 1000 char)
            
            // Metadata
            $table->timestamps();
            $table->softDeletes(); // Soft delete agar data tidak hilang
            
            // Indices
            $table->foreign('review_id')->references('id')->on('ratings_reviews')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_reply_id')->references('id')->on('replies')->onDelete('cascade');
            
            // Index untuk query performance
            $table->index('review_id');
            $table->index('user_id');
            $table->index('parent_reply_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replies');
    }
};