<?php

namespace Zdp\BI\Models;

class GoodsSnapshot extends Model
{
    protected $table = 'goods_snapshot';

    public $timestamps = false;

    protected $fillable = [

        'date',         // 日期 Y-m-d
        'sort',         // 类别
        'brand',        // 品牌
        'province',     // 省
        'city',         // 市
        'district',     // 区
        'market',       // 市场
        'shop_id',      // 店铺id

        'goods_id',         // 商品id
        'goods_name',       // 商品名称
        'shenghe_act',      // 商品状态

        'on_sale',      // 商品上架状态(是1/否-1)
        'new',          // 是否新增(是1/否-1)
        'overdue',      // 是否过期(是1/否-1)
    ];

    protected $primaryKey = 'id';
}
