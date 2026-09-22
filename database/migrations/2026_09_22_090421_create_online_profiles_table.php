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
        Schema::create('online_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('online_business_type', 30);
            $table->string('technology_platform', 30)->nullable();
            $table->string('technology_platform_other', 80)->nullable();
            $table->unsignedSmallInteger('domain_registered_year')->nullable();
            $table->unsignedInteger('monthly_visits')->nullable();
            $table->string('monthly_visits_disclosure', 15)->default(Disclosure::Exact->value);
            $table->unsignedInteger('registered_users')->nullable();
            $table->unsignedInteger('active_customers')->nullable();
            $table->unsignedInteger('monthly_orders')->nullable();
            $table->unsignedTinyInteger('recurring_revenue_percent')->nullable();
            $table->json('acquisition_channels')->nullable();
            $table->json('social_profiles')->nullable();
            $table->json('sells_on_marketplaces')->nullable();
            $table->boolean('has_stock')->nullable();
            $table->string('logistics_type', 20)->nullable();
            $table->boolean('team_included')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_profiles');
    }
};
