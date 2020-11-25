<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\AuthorizationRequest;
use App\Http\Controllers\Controller;
use Illuminate\Auth\AuthenticationException;
use App\Models\User;
use App\Models\UserOperationLog;
use App\Models\Configs;
use Illuminate\Http\Request;
use Dingo\Api\Routing\Helpers;

class AuthorizationsController extends Controller
{
    use Helpers;
    public function login()
    {
        return $this->response->error('未登录', 500);
    }
    public function versionNumber()
    {
        $number = Configs::where(['name'=>'versionnumber'])->first()->value;
        return $this->response->array(['number'=>$number]);
    }
    public function store(AuthorizationRequest $request)
    {
/*        /*$username = $request->username;
        //过滤变量
        filter_var($username,FILTER_VALIDATE_EMAIL)?
            $credentials['email'] = $username :
            $credentials['phone'] = $username;*/
        $credentials['name'] = $request->username;
        $credentials['password'] =  $request->password;
        $credentials['status'] = 0;

        if(!$token = \Auth::guard('api')->attempt($credentials)) {
            throw new AuthenticationException('用户名或密码错误');
            //return $this->response->error('用户名或密码错误', 500);
        }
        User::where('name',$request->username)->update(
            ['remember_token'=>$token]
        );
        $log = new UserOperationLog();
        $log->user_id = User::where('name',$request->username)->first()->id;
        $log->user_name = $request->username;
        $log->content = '登录';
        $log->login_ip = Request::createFromGlobals()->getClientIp();
        $log->save();
        return $this->respondWithToken($token)->setStatusCode(200);
    }


    public function update()
    {
        $token = auth('api')->refresh();
        User::where('name',$request->username)->update(
            ['remember_token'=>$token]
        );
        return $this->respondWithToken($token);
    }
    public function updatePass(AuthorizationRequest $request)
    {
        $credentials['name'] = $request->user()->name;
        $credentials['password'] = $request->oldpass;
        if(!\Auth::attempt($credentials))
            return $this->response->error('原密码错误', 206);
        if($request->password!=$request->repassword)
            return $this->response->error('两次密码输入不一致', 200);

        $result = User::where('id',$request->user()->id)
            ->update([
                'password' => password_hash($request->password, PASSWORD_DEFAULT)
            ]);
        if(!$result)
            return $this->response->error('修改密码失败', 200);

        return $this->response->error('修改密码成功', 200);

    }

    public function destroy()
    {
        auth('api')->logout();
        return response(null,200);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }
}
