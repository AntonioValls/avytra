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
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces')->restrictOnDelete();
            $table->char('country_code', 2)->default('ES');
            $table->string('code', 10);
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('population')->nullable();
            $table->timestamps();

            $table->unique(['country_code', 'code']);
            $table->unique(['province_id', 'slug']);
            $table->index(['province_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
