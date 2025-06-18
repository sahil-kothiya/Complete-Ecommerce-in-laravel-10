<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductReviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();

            // Nullable foreign keys required for SET NULL
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('SET NULL');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('SET NULL');

            // Rating and review fields
            $table->unsignedTinyInteger('rate')->default(0); // PostgreSQL doesn't have TINYINT, use unsigned small int (tiny -> small cast)
            $table->text('review')->nullable();

            // Enum field for status
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_reviews');
    }
}
