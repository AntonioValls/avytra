<?php

use App\Enums\WebsiteVisibility;
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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            // Deleting a user never cascades silently: DeleteUserAccount removes the businesses first (ADR-017).
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('business_type', 20)->index();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('legal_name', 160)->nullable();
            $table->string('legal_form', 30)->nullable();
            $table->boolean('show_legal_form')->default(false);
            $table->string('tagline', 160)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('employee_range', 30)->nullable();
            $table->string('website_url', 255)->nullable();
            $table->string('website_visibility', 10)->default(WebsiteVisibility::Private->value);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
