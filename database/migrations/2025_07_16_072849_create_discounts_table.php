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
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->enum('type', ['percentage', 'amount']); // Discount type
            $table->decimal('value', 10, 2);                // Discount value
            $table->timestamp('starts_at');                 // When discount begins
            $table->timestamp('ends_at');                   // When it ends
            $table->boolean('is_active')->default(true);    // Admin toggle
            $table->timestamps();

            // Indexes
            $table->index(['type', 'starts_at', 'ends_at', 'is_active'], 'idx_discounts_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
