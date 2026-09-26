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
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name', 120);
            $table->string('sender_email', 255);
            $table->string('sender_phone', 30)->nullable();
            $table->text('message');
            // sha256 of IP + app key: enough to cap repeats, never the IP itself.
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('delivery_failed_at')->nullable();
            $table->string('delivery_error', 500)->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'read_at']);
            $table->index(['listing_id', 'ip_hash', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
    }
};
