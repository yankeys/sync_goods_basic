<?php

namespace Zdp\BI\Models;

class GoodsStatistic extends Model
{
    protected $table = 'goods_statistic';

    public $timestamps = false;

    protected $fillable = [

        'date',         // 日期 Y-m-d
        'sort',         // 类别
        'brand',        // 品牌
        'province',     // 省
        'city',         // 市
        'district',     // 区
        'market',       // 市场

        'audit',    // 待审核
        'normal',   // 已审核
        'close',    // 已下架
        'delete',   // 已删除
        'reject',   // 拒绝
        'modify_audit', // 修改待审核
        'perfect',  // 待完善

        'new',      // 新增
        'overdue',  // 过期

        'sale',     // 上架
        'not_sale', // 下架

    ];

    protected $primaryKey = 'id';
}