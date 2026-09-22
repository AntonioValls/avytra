<?php

use App\Enums\ListingStatus;
use App\Enums\PriceDisclosure;
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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            // A business with listings is never deleted by the database; DeleteUserAccount removes them first.
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default(ListingStatus::Draft->value)->index();
            // Operation types live in listing_operation_types; this is the one shown first.
            $table->string('primary_operation_type', 30)->nullable()->index();
            $table->unsignedTinyInteger('stake_percent')->nullable();
            $table->string('operation_notes', 500)->nullable();
            // Title and slug are required to publish, not to keep a draft.
            $table->string('title', 120)->nullable();
            $table->string('slug', 140)->nullable()->unique();
            $table->string('reason_for_sale', 500)->nullable();
            $table->json('highlights')->nullable();
            $table->boolean('includes_stock')->nullable();
            $table->boolean('includes_equipment')->nullable();
            $table->boolean('includes_property')->nullable();
            $table->boolean('includes_staff')->nullable();
            $table->boolean('includes_intellectual_property')->nullable();
            $table->text('included_assets_notes')->nullable();
            $table->boolean('premises_is_rented')->nullable();
            $table->string('price_disclosure', 15)->default(PriceDisclosure::OnRequest->value);
            $table->unsignedBigInteger('asking_price')->nullable()->index();
            $table->unsignedBigInteger('asking_price_min')->nullable();
            $table->unsignedBigInteger('asking_price_max')->nullable();
            $table->boolean('is_price_negotiable')->nullable();
            $table->char('currency', 3)->default('EUR');
            // Contact: everything filled in here is public by definition (ADR-009).
            $table->string('contact_name', 80)->nullable();
            $table->string('preferred_contact_method', 20)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_whatsapp', 30)->nullable();
            $table->string('contact_website_url', 255)->nullable();
            $table->string('contact_form_url', 255)->nullable();
            $table->string('contact_other', 255)->nullable();
            $table->string('contact_notes', 255)->nullable();
            // Lifecycle: written only by the Actions in App\Actions\Listings.
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('last_confirmed_at')->nullable()->index();
            $table->timestamp('next_confirmation_at')->nullable()->index();
            $table->timestamp('first_reminder_sent_at')->nullable();
            $table->timestamp('second_reminder_sent_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'next_confirmation_at']);
            $table->index(['status', 'last_confirmed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
