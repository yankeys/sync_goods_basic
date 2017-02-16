<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class GoodsStatistic extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection(config('bi.connection', 'mysql_bi'))
              ->create('goods_statistic', function (Blueprint $table) {
                  $table->increments('id');

                  $table->date('date')->index();              // 日期
                  $table->string('sort',50)->index();         // 分类
                  $table->string('brand',20)->index();        // 品牌
                  $table->string('province',32)->index();     // 省
                  $table->string('city',32)->index();         // 市
                  $table->string('district',32)->index();     // 区
                  $table->string('market',32)->index();       // 市场

                  $table->integer('audit')->unsigned()->default(0);    // 待审核
                  $table->integer('normal')->unsigned()->default(0);   // 已审核
                  $table->integer('close')->unsigned()->default(0);    // 已下架(审核后)
                  $table->integer('delete')->unsigned()->default(0);   // 已删除
                  $table->integer('reject')->unsigned()->default(0);   // 拒绝
                  $table->integer('modify_audit')->unsigned()->default(0); // 修改待审核
                  $table->integer('perfect')->unsigned()->default(0);  // 待完善商品

                  $table->integer('new')->unsigned()->default(0);       // 新增商品数
                  $table->integer('overdue')->unsigned()->default(0);   // 过期商品数

                  $table->integer('sale')->unsigned()->default(0);     // 已上架
                  $table->integer('not_sale')->unsigned()->default(0); // 已下架
              });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('goods_statistic');
    }
}
