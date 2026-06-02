<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel untuk tracking notifikasi reply
     * Digunakan untuk:
     * - Menentukan apakah user sudah melihat notification
     * - Tracking FCM delivery status
     * - Mengirim ulang notification jika perlu
     */
    public function up(): void
    {
        Schema::create('reply_notifications', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->unsignedBigInteger('reply_id');      // Reply yang di-notify
            $table->unsignedBigInteger('recipient_id');  // User yang menerima notif
            $table->unsignedBigInteger('sender_id');     // User yang membuat reply
            
            // FCM tracking
            $table->string('fcm_token')->nullable(); // Device token saat mengirim
            $table->enum('delivery_status', ['pending', 'sent', 'failed', 'read'])
                  ->default('pending');
            
            // Metadata
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable(); // Jika gagal
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indices
            $table->foreign('reply_id')->references('id')->on('replies')->onDelete('cascade');
            $table->foreign('recipient_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->index('recipient_id');
            $table->index('reply_id');
            $table->index('is_read');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reply_notifications');
    }
};