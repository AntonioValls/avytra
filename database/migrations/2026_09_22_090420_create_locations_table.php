<?php

use App\Enums\LocationVisibility;
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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->char('country_code', 2)->default('ES');
            $table->foreignId('province_id')->constrained('provinces')->restrictOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->nullOnDelete();
            // Private: postal code, address and real coordinates never reach public output.
            $table->string('postal_code', 10)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_visibility', 20)->default(LocationVisibility::Approximate->value);
            // Derived by SaveBusinessLocation according to the visibility; the only coordinates served publicly.
            $table->decimal('public_latitude', 10, 7)->nullable();
            $table->decimal('public_longitude', 10, 7)->nullable();
            $table->unsignedInteger('public_radius_m')->nullable();
            $table->string('geocoding_source', 30)->nullable();
            $table->string('geocoding_provider', 30)->nullable();
            $table->timestamp('geocoded_at')->nullable();
            $table->timestamps();

            // One location per business in the MVP; is_primary is ready for several premises later.
            $table->unique(['business_id', 'is_primary']);
            $table->index(['public_latitude', 'public_longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
