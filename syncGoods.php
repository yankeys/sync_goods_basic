<?php

namespace Zdp\BI\Services\Sync;

use App\Models\DpGoodsInfo;
use Carbon\Carbon;
use Zdp\BI\Models\GoodsSnapshot;
use Zdp\BI\Models\GoodsStatistic;

class Goods
{
    const SNAPSHOT_FILTER = [
        'date',
        'sort',
        'brand',
        'province',
        'city',
        'district',
        'market',
    ];
    const DIFF_STATUS = [
        'shenghe_act',
        'on_sale',
        'overdue',
    ];

    // 获取当前所有的商品以及基本信息
    public function syncAllGoods()
    {
        DpGoodsInfo
            ::join(
                'dp_shopInfo', 'dp_goods_info.shopId', '=', 'dp_shopInfo.shopid'
            )
            ->join(
                'dp_pianqu', 'dp_shopInfo.pianquId', '=',
                'dp_pianqu.pianquId'
            )
            ->join(
                'dp_goods_types',
                'dp_goods_info.goods_type_id',
                '=',
                'dp_goods_types.id'
            )
            ->join(
                'dp_goods_basic_attributes',
                'dp_goods_info.id',
                '=',
                'dp_goods_basic_attributes.goodsid'
            )
            ->select([
                'dp_goods_info.id as goods_id',                 // 商品id
                'dp_goods_info.gname as goods_name',            // 商品名称
                'dp_goods_info.shenghe_act as examine_status',  // 商品状态
                'dp_goods_info.on_sale as shelves_status',      // 商品上下架状态
                'dp_goods_info.brand as brand_name',        // 商品品牌名称
                'dp_goods_basic_attributes.auto_soldout_time',  // 自动下架时间
                'dp_shopInfo.shopId as shop_id',            // 店铺id
                'dp_shopInfo.province as shop_province',    // 省
                'dp_shopInfo.city as shop_city',            // 市
                'dp_shopInfo.county as shop_district',      // 县/区
                'dp_pianqu.pianqu as market_name',          // 市场名字
                'dp_goods_types.sort_name',                 // 分类名字
            ])
            ->chunk(1000,function ($goods){
                self::sync($goods);
            });
    }

    /**
     * 同步商品基础信息
     *
     * @param $currentGoods
     */
    protected function sync($currentGoods)
    {
        // 获取现在的日期
        $today = Carbon::now()->toDateString();
        // 需要对比的字段
        $compareParam = self::SNAPSHOT_FILTER;
        $compareStatus = self::DIFF_STATUS;
        // 判断是否过期
        foreach ($currentGoods as $currentGood) {
            // 对比快照表数据准备
            $snapshotData = self::formatAllGoods($currentGood);
            // 商品所有基础信息(所有状态都转化成字段)
            $goodsStatistic = self::formatAllGoods($currentGood,true);
            // 新添加商品所有状态
            $goodsStatusData = self::getStatus($goodsStatistic,$currentGood);
            \DB::transaction(function ()use(
                $currentGood,
                $today,
                $snapshotData,
                $goodsStatistic,
                $compareParam,
                $compareStatus,
                $goodsStatusData
            ){
                // 统计表不存在则插入
                $insertStatistic = self::insertStatistic($goodsStatistic,$compareParam,$today);
                // 判断是否已经存在该商品快照，且判断是否需要更新
                $snapshot = self::getSnapshot($currentGood->goods_id);

                if (empty($snapshot)) {
                    $snapshotData = array_merge(['new'=>1,'date' => $today,],$snapshotData);
                    GoodsSnapshot::create($snapshotData);
                    // 所有项目数量+1
                    self::updateNum($insertStatistic->id,$goodsStatusData,true);
                } else {
                    // 对比,返回不一样的键值
                    $existsSnapshot = $snapshot->toArray();
                    unset($existsSnapshot['id'],$existsSnapshot['date'],$existsSnapshot['new']);
                    $diff = array_filter($snapshotData, function ($key) use($snapshotData,$existsSnapshot){
                        return $snapshotData[$key] != $existsSnapshot[$key];
                    },ARRAY_FILTER_USE_KEY);

                    if (!empty($diff)) {
                        // 插入快照表
                        $reSnapshotData = array_merge(['new'=>1,'date'=> $today],$snapshotData);
                        GoodsSnapshot::create($reSnapshotData);
                        // 查询数据所有更改项
                        $diffStatus = self::filterData($diff,$compareStatus);
                        $diffShopInfo = self::filterData($diff,$compareParam);
                        if (array_key_exists('on_sale',$diffStatus))
                        {
                            $key = self::changeShelves($diffStatus['on_sale'],true);
                            $saleStatus = array_combine([$key],['on_sale']);
                            $diffStatus = array_merge($saleStatus,$diffStatus);
                            unset($diffStatus['on_sale']);
                        }
                        if (!empty($diffStatus) && empty($diffShopInfo))
                        {
                            $update = array_keys($diffStatus);
                            self::updateNum($insertStatistic->id,$update,true);
                        }elseif(!empty($diffShopInfo)){
                            // 更改之后的地址所有状态+1，且为新商品
                            $update = $goodsStatusData;
                            self::updateNum($insertStatistic->id,$update,true);
                            # todo // 更改之前的地址所有状态-1，不为新商品
                        }
                    }
                }
            });
        }
    }

    // 格式化商品的基础数据
    protected function formatAllGoods(
        $goodsData,
        $isSnapshot = false
    )
    {
        if (empty($goodsData)) {
            throw new \Exception('商品基础信息获取失败');
        }

        return [
            'sort'        => $goodsData->sort_name,
            'brand'       => $goodsData->brand_name,
            'province'    => $goodsData->shop_province,
            'city'        => $goodsData->shop_city,
            'district'    => $goodsData->shop_district,
            'market'      => $goodsData->market_name,
            'shop_id'     => $goodsData->shop_id,
            'goods_id'    => $goodsData->goods_id,
            'goods_name'  => $goodsData->goods_name,
            'shenghe_act' => self::changeExamine($goodsData->examine_status,$isSnapshot),
            'on_sale'     => self::changeShelves($goodsData->shelves_status,$isSnapshot),
            'overdue'     => self::judgeOverdue($goodsData->auto_soldout_time,$isSnapshot)
        ];
    }

    // 对需要插入数据库的商品上下架状态进行字符串转换
    protected function changeShelves($num,$isSnapshot = false)
    {
        $status = 0;
        if (empty($num)) {
            throw new \Exception('商品状态码不存在');
        }
        if (!$isSnapshot){
            switch ($num) {
                case DpGoodsInfo::GOODS_NOT_ON_SALE:
                    $status = -1;
                    break;
                case DpGoodsInfo::GOODS_SALE:
                    $status = 1;
                    break;
                default:
                    echo '商品上下架状态码' . $num . '未解释';
            }
        }else{
            switch ($num) {
                case DpGoodsInfo::GOODS_NOT_ON_SALE:
                    $status = 'not_sale';
                    break;
                case DpGoodsInfo::GOODS_SALE:
                    $status = 'sale';
                    break;
                default:
                    echo '商品上下架状态码' . $num . '未解释';
            }
        }

        return $status;
    }

    // 对需要插入数据库的商品审核状态进行字符串转换
    protected function changeExamine($num,$isSnapshot = false)
    {
        $string = '';
        if (empty($num)) {
            throw new \Exception('商品状态码不存在');
        }
        if (!$isSnapshot){
            switch ($num) {
                case DpGoodsInfo::STATUS_AUDIT:
                    $string = '待审核';
                    break;
                case DpGoodsInfo::STATUS_NORMAL:
                    $string = '已审核';
                    break;
                case DpGoodsInfo::STATUS_CLOSE:
                    $string = '已下架';
                    break;
                case DpGoodsInfo::STATUS_DEL:
                    $string = '已删除';
                    break;
                case DpGoodsInfo::STATUS_REJECT:
                    $string = '拒绝';
                    break;
                case DpGoodsInfo::STATUS_MODIFY_AUDIT:
                    $string = '修改待审核';
                    break;
                case DpGoodsInfo::WAIT_PERFECT:
                    $string = '待完善';
                    break;
                default:
                    echo '商品审核状态码' . $num . '未解释';
            }
        }else{
            switch ($num)
            {
                case DpGoodsInfo::STATUS_AUDIT:
                    $string = 'audit';
                    break;
                case DpGoodsInfo::STATUS_NORMAL:
                    $string = 'normal';
                    break;
                case DpGoodsInfo::STATUS_CLOSE:
                    $string = 'close';
                    break;
                case DpGoodsInfo::STATUS_DEL:
                    $string = 'delete';
                    break;
                case DpGoodsInfo::STATUS_REJECT:
                    $string = 'reject';
                    break;
                case DpGoodsInfo::STATUS_MODIFY_AUDIT:
                    $string = 'modify_audit';
                    break;
                case DpGoodsInfo::WAIT_PERFECT:
                    $string = 'perfect';
                    break;
                default:
                    echo '商品审核状态码' . $num . '未解释';
            }
        }

        return $string;
    }

    // 判断是否过期
    protected function judgeOverdue($time , $isSnapshot = false)
    {
        if (!strtotime($time)) {
            throw new \Exception('这不是一个时间字符串');
        }
        $now = Carbon::now()->format('Y-m-d H:i:s');
        if ($isSnapshot)
        {
            if ($time > $now) {
                $output = '';
            }else{
                $output = 'overdue';
            }
        } else{
            if ($time > $now) {
                $output = -1;
            } else {
                $output = 1;
            }
        }

        return $output;
    }

    // 获取单个的商品距离现在时间最近的快照
    protected function getSnapshot($goodsId)
    {
        $data = GoodsSnapshot::where('goods_id', $goodsId)
                             ->orderBy('id', 'desc')
                             ->first();

        return $data;
    }

    // 拿到当前商品信息并插入统计表
    protected function insertStatistic($goodsStatistic,$compareParam,$today)
    {
        $statisticCompare = self::filterData($goodsStatistic,$compareParam);
        $statisticFilter = array_merge(['date'=> $today],$statisticCompare);
        // 创建或更新
        $insertStatistic = GoodsStatistic::firstOrCreate($statisticFilter);

        return $insertStatistic;
    }

    // 数据筛选
    protected function filterData($diff,$compareParam)
    {
        $diffData = array_filter($diff, function ($key) use($compareParam){
            return array_key_exists($key,array_flip($compareParam));
        },ARRAY_FILTER_USE_KEY);

        return $diffData;
    }

    // 获取当前商品的所有状态，且插入新品状态(用到商品所有状态地方都是作为新品进行添加)
    protected function getStatus($goodsStatistic,$currentGood)
    {
        $goodsStatusData = [
            'new',
            $goodsStatistic['shenghe_act'],
            $goodsStatistic['on_sale']
        ];
        $overdue = self::judgeOverdue($currentGood->auto_soldout_time,true);
        if (!empty($overdue))
        {
            $goodsStatusData = array_merge([$overdue],$goodsStatusData);
        }

        return $goodsStatusData;
    }

    // 更新统计个数
    protected function updateNum($id,array $columns,$isAdd = false)
    {
        foreach($columns as $column){
            if ($isAdd)
            {
                GoodsStatistic::where('id',$id)
                              ->increment($column,1);
            }else{
                GoodsStatistic::where('id',$id)
                              ->dncrement($column,1);
            }
        }
    }
}