<?php

use App\Enums\Disclosure;
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
        // One row per declared metric, each with its own disclosure (ADR-010).
        Schema::create('listing_financial_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->string('metric', 40);
            $table->string('disclosure', 15)->default(Disclosure::Exact->value);
            $table->unsignedBigInteger('amount')->nullable();
            $table->unsignedBigInteger('amount_min')->nullable();
            $table->unsignedBigInteger('amount_max')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->unsignedSmallInteger('period_year')->nullable();
            $table->timestamps();

            $table->unique(['listing_id', 'metric']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_financial_metrics');
    }
};
