<?php

use App\Enums\ListingReportStatus;
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
        Schema::create('listing_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_email', 255)->nullable();
            $table->string('reason', 30);
            $table->text('message')->nullable();
            $table->string('status', 15)->default(ListingReportStatus::Open->value)->index();
            // sha256 of IP + app key: enough to spot repeats, never the IP itself.
            $table->string('ip_hash', 64)->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_notes', 500)->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'status']);
            $table->index(['ip_hash', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_reports');
    }
};
