<?php

namespace server;
require_once('./Database.php');
class index_list
{

    public $db;
	public $timer;
    public $status = [
        'success'=>['code'=>200,'message'=>'连接成功'],
        'error1'=>['code'=>501,'message'=>'连接失败'],
        'error2'=>['code'=>401,'message'=>'token错误'],
        'error3'=>['code'=>503,'message'=>'参数错误'],
        ];
	public $redis;
    public function __construct()
    {
        header("Content-Type: text/html;charset=utf-8");
        //创建websocket服务器对象，监听0.0.0.0:9501端口
        $ws_server = new \swoole_websocket_server('0.0.0.0', 9502);
   $this->redis = new \Redis();
   $this->redis->connect('127.0.0.1', 6379);
   echo "Connection to server successfully";
         //查看服务是否运行
   echo "Server is running: " . $this->redis->ping();
        //设置server运行时的各项参数
		

        $ws_server->set(array(
		'worker_num' => 4,
        //'heartbeat_check_interval' => 10,
        //'heartbeat_idle_time' => 15,
        //'daemonize' => true, //是否作为守护进程
        )); 
        //监听WebSocket连接打开事件
        $ws_server->on('open', function ($ws, $request) {

        //file_put_contents( __DIR__ .'/log.txt' , $request->fd);
            $ws->push($request->fd, json_encode($this->status['success'],JSON_UNESCAPED_UNICODE));
        });
        //监听WebSocket消息事件
        $ws_server->on('message', function ($ws,$frame){
            $db = Database::getInstance();
			$this->redis->set($frame->flags,$frame->fd);
            //var_dump($frame);
            $user = json_decode($frame->data);
			$this->redis->set($frame->flags.'api_name',$user->api_name);
			$user->field = $user->field??'id';
			$user->sortd = $user->sortd??'asc';
            $result = $db->__call('user_token', [$user->token]);

/*			$this->timer[$frame->fd.'tokenTest'] = \Swoole\Timer::tick(5000, function () use ($ws,$frame, $user,$db) {
				$result = $db->__call('user_token', [$user->token]);
				if(!$result){
						\Swoole\Timer::clear($this->timer[$frame->fd.'tokenTest']);
						if(isset($this->timer[$frame->fd.'get_one_for_list']) && $user->api_name = 'get_one_for_list'){
						\Swoole\Timer::clear($this->timer[$frame->fd.'get_one_for_list']);
						}
						if(isset($this->timer[$frame->fd.'userorderlist']) && $user->api_name = 'userorderlist'){
						\Swoole\Timer::clear($this->timer[$frame->fd.'userorderlist']);
						}
						if(isset($this->timer[$frame->fd.'getuserfor']) && $user->api_name = 'getuserfor'){
						\Swoole\Timer::clear($this->timer[$frame->fd.'getuserfor']);
						}
						if(isset($this->timer[$frame->fd.'user_info']) && $user->api_name = 'user_info'){
						\Swoole\Timer::clear($this->timer[$frame->fd.'user_info']);
						}
				$ws->push($frame->fd,json_encode($this->status['error2'],JSON_UNESCAPED_UNICODE));
				}
					});*/
            if (!$result) {
                $ws->push($this->redis->get($frame->flags),json_encode($this->status['error2'],JSON_UNESCAPED_UNICODE));
            }
            else {
				$this->redis->set($frame->flags.'user', $result[0]['id']);
                switch ($this->redis->get($frame->flags.'api_name')) {
                    case  'getuserfor':
                        $data = $this->getUserForList($db,  $this->redis->get($frame->flags.'user'));
                        break;
                    case 'userorderlist':
                        $data = $this->getUserOrderList($db,  $this->redis->get($frame->flags.'user'),$user->field,$user->sortd);
                        break;
                    case 'get_one_for_list':
                        $data = $this->getOneForlist($db, $user->fs);
                        $data=empty($data)?[]:$data[0];
                        break;
					case 'user_info':
                        $data = $this->getUserInfo($db,  $this->redis->get($frame->flags.'user'));
                        $data=empty($data)?[]:$data[0];
                        break;
                    default:
					$ws->push($this->redis->get($frame->flags), json_encode($this->status['error3'],JSON_UNESCAPED_UNICODE));
                }
				$data = $data??[];
                $this->pushMessage($ws, $this->redis->get($frame->flags), $data,$user->api_name);
				if(isset($this->timer[$frame->flags.'get_one_for_list']) && $user->api_name = 'get_one_for_list'){
					\Swoole\Timer::clear($this->timer[$frame->flags.'get_one_for_list']);
				}
				if($this->redis->get($frame->flags.'api_name')=='get_one_for_list'){
					$this->timer[$frame->flags.'get_one_for_list'] = \Swoole\Timer::tick(1000, function () use ($ws, $frame, $user,$result,$db) {
					$user->fs=$user->fs??'';
					$data = $this->getOneForlist($db, $user->fs);
					$data=empty($data)?[]:$data[0];
					$this->pushMessage($ws, $this->redis->get($frame->flags),$data,'get_one_for_list');
					});
				}
 				if(isset($this->timer[$frame->flags.'userorderlist']) && $user->api_name = 'userorderlist'){
					\Swoole\Timer::clear($this->timer[$frame->flags.'userorderlist']);
				} 
				if($this->redis->get($frame->flags.'api_name')=='userorderlist'){
					$this->timer[$frame->flags.'userorderlist'] = \Swoole\Timer::tick(1000, function () use ($ws, $frame, $user,$result,$db) {
					$data = $this->getUserOrderList($db,  $this->redis->get($frame->flags.'user'),$user->field,$user->sortd);
					$this->pushMessage($ws, $this->redis->get($frame->flags),$data,'userorderlist');
					});
				}
				if(isset($this->timer[$frame->flags.'getuserfor']) && $user->api_name = 'getuserfor'){
					\Swoole\Timer::clear($this->timer[$frame->flags.'getuserfor']);
				}
				if($this->redis->get($frame->flags.'api_name')=='getuserfor'){
					$this->timer[$frame->flags.'getuserfor'] = \Swoole\Timer::tick(1000, function () use ($ws, $frame, $user,$result,$db) {
					$data = $this->getUserForList($db,  $this->redis->get($frame->flags.'user'));
					$this->pushMessage($ws, $this->redis->get($frame->flags),$data,'getuserfor');
					});
				}
				if(isset($this->timer[$frame->flags.'user_info']) && $user->api_name = 'user_info'){
					\Swoole\Timer::clear($this->timer[$frame->flags.'user_info']);
				}
				if($this->redis->get($frame->flags.'api_name')=='user_info'){
					echo 'me';
					$this->timer[$frame->flags.'user_info'] = \Swoole\Timer::tick(1000, function () use ($ws, $frame, $user,$result,$db) {
					$data = $this->getUserInfo($db,  $this->redis->get($frame->flags.'user'));
					$data=empty($data)?[]:$data[0];
					$this->pushMessage($ws, $this->redis->get($frame->flags),$data,'user_info');
					});
				}

            }
        });

        //监听WebSocket连接关闭事件
        $ws_server->on('close', function ($ws, $fd) {
            echo "client-{$fd} is closed\n";
        });

        $ws_server->start();
    }

    public function getUserForList($db,$uid)
    {
        return  $db->__call('user_for_list',[$uid]);
    }
	public function getUserInfo($db,$uid)
    {
        return  $db->__call('user_info',[$uid]);
    }

    public function getUserOrderList($db,$uid,$field,$sortd)
    {
        return $db->__call('user_order_list',[$uid,$field,$sortd]);
    }

    public function getOneForlist($db,$id)
    {
        return $db->__call('get_one_for_list',[$id]);
    }
    //消息推送
    public function pushMessage($ws,$fd,$result,$api_name='')
    {
        $result= ['code'=>200,'message'=>'success','api_name'=>$api_name,'data'=>$result];
        $ws->push((int)$fd,json_encode($result,JSON_UNESCAPED_UNICODE));
    }
}
$model = new index_list();



