<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductVariationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();

            $table->string("sub_sku");
            $table->integer("quantity")->default(0);
            $table->decimal("price",10,2)->default(0);


            $table->unsignedBigInteger("automobile_make_id")->nullable();
            $table->foreign('automobile_make_id')->references('id')->on('automobile_makes')->onDelete('restrict');

            $table->unsignedBigInteger("product_id");
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            $table->softDeletes();





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
        Schema::dropIfExists('product_variations');
    }
}
