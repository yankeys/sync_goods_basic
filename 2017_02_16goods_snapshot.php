<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class GoodsSnapshot extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection(config('bi.connection', 'mysql_bi'))
              ->create('goods_snapshot', function (Blueprint $table) {
                  $table->increments('id');

                  $table->date('date')->index();              // 日期
                  $table->string('sort',50)->index();         // 分类
                  $table->string('brand',20)->index();        // 品牌
                  $table->string('province',32)->index();     // 省
                  $table->string('city',32)->index();         // 市
                  $table->string('district',32)->index();     // 区
                  $table->string('market',32)->index();       // 市场
                  $table->integer('shop_id')->index();        // 店铺id

                  $table->integer('goods_id');       // 商品id
                  $table->string('goods_name',50);   // 商品名字
                  $table->string('shenghe_act',32);  // 商品状态

                  $table->smallInteger('on_sale');               // 商品上架状态(是1/否-1)
                  $table->smallInteger('new')->default(-1);      // 商品是否新增(是1/否-1)
                  $table->smallInteger('overdue')->default(-1);  // 商品是否过期(是1/否-1)
              });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('goods_snapshot');
    }
}
