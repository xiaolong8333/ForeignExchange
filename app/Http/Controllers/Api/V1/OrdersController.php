<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Configs;
use App\Models\ForeignExchangeList;
use App\Models\Order;
use App\Models\MoneyRecord;
use App\Models\UserPoosition;
use App\Http\Resources\OrdersResource;
use App\Http\Requests\Api\OrdersRequest;
use Illuminate\Auth\AuthenticationException;
use App\Http\Controllers\Api\Controller;
use Illuminate\Http\Request;
use Dingo\Api\Routing\Helpers;
use Illuminate\Support\Facades\DB;

class OrdersController extends Controller
{
    use Helpers;
    //
    public function index(Request $request)
    {
        //获取配置
        $config = Configs::get();
        $configs = [];
        foreach($config as $k=>$v){
            $configs[$v->name]=$v->value;
        }
        $field = $request->field??'id';
        $sort = $request->sort??'desc';
        $limit = $request->limit??15;
        $whereRaw = '1=1';
        if(isset($request->beginTime) && !empty($request->beginTime))
        $whereRaw  .=  " && updated_at>'{$request->beginTime}'";
        if(isset($request->endTime) && !empty($request->endTime))
            $whereRaw  .= " && updated_at<'{$request->endTime}'";
        $where["user_id"]=$request->user()->id;
        if($request->create_type)
            $where["create_type"] = $request->create_type;
        if($request->status)
            $where["status"] = $request->status;
        if($request->type)
            $where["type"] = $request->type;
        $orders = Order::with('exchangeList')
            ->where($where)
            ->whereRaw($whereRaw)
            ->orderBy($field,$sort)
            ->paginate($limit)
        ->map(function($item)use ($configs){
/*            $toPriceList=[];
            //不是直接盘
            if($item->exchangeList->type!='Forex1'){
                $indirect = substr($item->exchangeList->FS,0,3)."USD";
                $toPriceList = ForeignExchangeList::where("FS",$indirect)->first();
                if(!$toPriceList)
                    return false;
            }
            //盈亏
            $item->newProfit =  getProfit($item,$item->exchangeList,$configs,$toPriceList);*/
            return $item;
        });
        $whereRawNew = $whereRaw;
        $whereRaw .= ' && create_type!=5';
        $whereRawNew.= ' && create_type=5';
        $totalProfit = Order::where($where)
            ->whereRaw($whereRaw)
            ->orderBy($field,$sort)
            ->paginate($limit)->sum('profit');
        $balance = Order::where($where)
            ->whereRaw($whereRawNew)
            ->orderBy($field,$sort)
            ->paginate($limit)->sum('profit');
        //return OrdersResource::collection($orders);
        $jieyu = sprintf("%.5f",$balance+$totalProfit);
        return $this->response->array(['jieyu'=>$jieyu,'profit'=>sprintf("%.5f",$totalProfit),'balance'=>sprintf("%.5f",$balance),'data'=> $orders->toArray()]);

    }
    public function getOrder(Order $order)
    {
        $this->authorize('update', $order);
        if($order->status == 2)
            return $this->response->error('该订单已完成交易', 208);
        return  $this->response->array($order->toArray());
    }
    /**
     * @param Request $request
     * @param OrdersRequest $query
     * @return OrdersResource|void
     */
    public function create(Request $request, OrdersRequest $query)
    {
        if(!isset($query->FS))
            return $this->response->error('交易币种不能为空', 422);
        if(!isset($query->trouble))
            return $this->response->error('交易手数不能为空', 422);
        if(!isset($query->type))
            return $this->response->error('交易类型不能为空', 422);
        $market = ForeignExchangeList::where("FS",$query->FS)->first();
        $toPriceList=[];
        //不是直接盘
        if($market->type!='Forex1'){
            $indirect = substr($market->FS,0,3)."USD";
            $toPriceList = ForeignExchangeList::where("FS",$indirect)->first();
            if(!$toPriceList)
                return $this->response->error('此币种不能交易', 407);
        }
        //获取配置
        $config = Configs::get();
        $configs = [];
        foreach($config as $k=>$v){
            $configs[$v->name]=$v->value;
        }
        //总价格
        $total_price =  getPrice($query,$market,$configs,$toPriceList);
        //手续费
        $fees = $configs['fees'];
        if($request->user()->balance<($total_price)){
            return $this->response->error('预付款金额不足', 405);
        }
        if($order = $this->createAction($market,$fees,$total_price,$request,$query)) {
            return new OrdersResource($order);
        }
        else{
            return $this->response->error('交易失败', 406);
        }
    }


    public function createAction($market,$fees,$total_price,$request,$query)
    {
        $query->buy_price = $query->buy_price??0;
        $trade_no = $this->createNumber();
        try {
            //开启默认数据库的事务
            DB::beginTransaction();
            $order = new Order();
            $order ->trade_no = $trade_no;
            $order ->user_id = $request->user()->id;
            //$order ->new_value = $nowPrice;
            $order ->trouble = $query->trouble;
            $order ->FS = $query->FS;
            $order ->create_price = $query->create_type==0?($query->type=='buy'?$market->S1:$market->B1):$query->create_price;
            $order ->create_total_price = sprintf("%.5f",$total_price);
            $order ->fees = sprintf("%.5f",$fees);
            $order ->stop_loss = sprintf("%.5f",$query->stop_loss??0);
            $order ->stop_profit = sprintf("%.5f",$query->stop_profit??0);
            $order ->deviation= $query->deviation??0;
            $order ->type = $query->type;
            $order ->create_type = $query->create_type;
            $order ->status = $query->create_type==0?1:0;
            $order ->cancel_time = isset($query->cancel_time)?strtotime($query->cancel_time):0;
            $order ->remark = $query->remark??'';
            if($query->create_type==0){
                $order ->create_price = $query->type=='buy'?$market->S1:$market->B1;
                $moneyData = [
                    'type'=>0,
                    'user_id'=>$request->user()->id,
                    'title'=>TRADTYPE[$query->create_type],
                    'trade_no'=>$trade_no,
                    'pre_last_balance'=>$request->user()->last_balance,
                    'last_balance'=>$request->user()->last_balance,
                    'pre_balance'=>$request->user()->balance,
                    'pre_advance'=>$request->user()->advance,
                    'balance'=>sprintf("%.5f",$request->user()->balance-$total_price),
                    'advance'=>sprintf("%.5f",$request->user()->advance+$total_price),
                    'fees' =>sprintf("%.5f",$fees),
                    'money'=>sprintf("%.5f",$total_price),
                ];
                $moneyRecord = new MoneyRecord();
                $moneyRecord->create($moneyData);
                User::where('id',$request->user()->id)->update(
                    [
                        'balance'=>sprintf("%.5f",$request->user()->balance-$total_price),
                        'advance' => sprintf("%.5f",$request->user()->advance+$total_price),
                    ]
                );
            }
            $order->save();
            if (true) {
                //一起提交
                DB::commit();
                return $order;
            } else {
                //一起回滚
                DB::rollback();
                return false;
            }
        } catch (\Exception $exception) {
            echo "catch some errors:".$exception->getMessage();
        }
    }


    /**
     * @param OrdersRequest $query
     * @param Order $order
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */

    public function close(Request $request,Order $order)
    {
        $this->authorize('update', $order);
        if($order->status!=1)
            return $this->response->error('此订单不能平仓', 506);
        $market = ForeignExchangeList::where("FS",$order->FS)->first();
        $toPriceList=[];
        //不是直接盘
        if($market->type!='Forex1'){
            $indirect = substr($market->FS,0,3)."USD";
            $toPriceList = ForeignExchangeList::where("FS",$indirect)->first();
            if(!$toPriceList)
                return $this->response->error('此币种不能交易', 507);
        }
        $request->trouble = $request->trouble??$order->trouble;
        if($request->trouble>$order->trouble)
            return $this->response->error('手数不足', 509);
        //获取配置
        $config = Configs::get();
        $configs = [];
        foreach($config as $k=>$v){
            $configs[$v->name]=$v->value;
        }
        //盈亏
        $profit =  getProfit($order,$market,$configs,$toPriceList);

        if($order = $this->closeAction($profit,$order,$market,$request)) {
            return new OrdersResource($order);
        }
        else{
            return $this->response->error('交易失败', 508);
        }
    }

    public function closeAction($profit,$order,$market,$request)
    {
        $trouble = $request->trouble;
        $fees = $order->fees;

        if($order->trouble!=$trouble) {
            $proportion = $request->trouble / $order->trouble;
            $profit = sprintf("%.5f", $profit*$proportion);
            $fees  = sprintf("%.5f", $order->fees*$proportion);
        }

        //卖出总价
        try {
            //开启默认数据库的事务
            DB::beginTransaction();
            $order ->close_price = $order->type=='buy'?$market->B1:$market->S1;
            $ordercreate_total_price =$order->create_total_price;
            $order ->close_total_price = sprintf("%.5f",$order->create_total_price+$profit+$order->fees);
            $order ->status = 2;
            $total_price = $order->create_total_price;
            if($order->trouble!=$trouble){
                $order ->close_total_price=0;
                $order->trouble = $order->trouble-$trouble;
                $order ->status = 1;
                $order->create_total_price = sprintf("%.5f",$order->create_total_price*(1-$proportion));
                $order->profit =  $order->profit-$profit;
                $order->fees =  sprintf("%.2f", $order->fees-$fees);
                $newOrder = new Order();
                $newOrder->trouble = $trouble;
                $newOrder->create_price = $order->create_price;
                $newOrder ->close_price = $order->type=='buy'?$market->B1:$market->S1;
                $newOrder->trade_no = $this->createNumber();
                $newOrder->user_id = $order->user_id;
                $newOrder->profit =  $profit;
                //$order ->new_value = $nowPrice;
                $newOrder->FS = $order ->FS;
                $newOrder->create_price = $order ->create_price;
                $newOrder->create_total_price = sprintf("%.5f",$ordercreate_total_price*$proportion);
                $newOrder->close_total_price = sprintf("%.5f",$ordercreate_total_price+$profit);
                $newOrder->fees = $fees;
                $newOrder->type = $order->type;
                $newOrder->create_type = $order->create_type;
                $newOrder->status = 2;
                $newOrder->cancel_time =0;
                $newOrder->save();
                $total_price = $newOrder->create_total_price;

            }

                $moneyData = [
                    'type'=>0,
                    'user_id'=>$request->user()->id,
                    'title'=>TRADTYPE[$order->create_type].'平仓',
                    'trade_no'=>$order->trade_no,
                    'pre_last_balance'=>$request->user()->last_balance,
                    'last_balance'=>sprintf("%.5f",$request->user()->last_balance+$profit),
                    'pre_balance'=>$request->user()->balance,
                    'pre_advance'=>$request->user()->advance,
                    'balance'=>sprintf("%.5f",$request->user()->balance+$total_price+$profit),
                    'advance'=>sprintf("%.5f",$request->user()->advance-$total_price),
                    'fees' =>sprintf("%.5f",$fees),
                    'money'=>sprintf("%.5f",$total_price+$profit),
                ];
                User::where('id', $request->user()->id)->update(
                    [
                        'last_balance'=>sprintf("%.5f",$request->user()->last_balance+$profit),
                        'advance'=> sprintf("%.5f",$request->user()->advance-$total_price),
                        'balance' => sprintf("%.5f",$request->user()->balance+$total_price+$profit),
                    ]
                );
            $moneyRecord = new MoneyRecord();
            $moneyRecord->create($moneyData);
            $order->save();
            if (true) {
                //一起提交
                DB::commit();
                if($order->trouble!=$trouble)
                    return $newOrder;
                return $order;
            } else {
                //一起回滚
                DB::rollback();
                return false;
            }
        } catch (\Exception $exception) {
            echo "catch some errors:".$exception->getMessage();
        }

    }

    public static function createNumber()
    {
        $yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');

        $orderSn = $yCode[intval(date('Y')) - 2011] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));

        return $orderSn;
    }

    /**
     * @desc 撤单
     */
    public function removeOrder(Request $request,Order $order)
    {
        $this->authorize('update', $order);
        if($order->status != 0)
            return $this->response->error('该订单已完成交易', 208);
            $order->status = 3;
            if($order->save())
                return $order;
            return  $this->response->error('测单失败', 206);
    }
    public function userIndex(Request $request)
    {

    }
}
