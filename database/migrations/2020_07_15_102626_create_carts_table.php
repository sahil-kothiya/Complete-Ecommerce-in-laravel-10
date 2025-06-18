<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCartsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // Foreign keys and relations
            $table->foreignId('product_id')->constrained('products')->onDelete('CASCADE');

            // Nullable foreign keys (required for SET NULL or optional relationships)
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('SET NULL');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('CASCADE');

            // Pricing and order details
            $table->decimal('price', 10, 2);
            $table->enum('status', ['new', 'progress', 'delivered', 'cancel'])->default('new');
            $table->integer('quantity');
            $table->decimal('amount', 10, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('carts');
    }
}
