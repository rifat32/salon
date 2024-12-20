<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCouponSubServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coupon_sub_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained("coupons")->onDelete('cascade');
            $table->foreignId('sub_service_id')->constrained("sub_services")->onDelete('cascade');
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
        Schema::dropIfExists('coupon_sub_services');
    }
}
