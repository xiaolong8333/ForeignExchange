<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

$api = app('Dingo\Api\Routing\Router');

$api->group([
    'version' => 'v1',
    'namespace' => 'App\Http\Controllers\Api\V1',
    'middleware' => ['bindings', 'throttle:' . config('api.rate_limits.sign')]], function ($api) {

    $api->get('index', 'IndexController@show');
    // 用户注册
    $api->post('users', 'UsersController@store')
        ->name('users.store');
    $api->post('verificationCodes',
        'VerificationCodesController@store')
        ->name('verificationCodes.store');
});

$api->group([
    'version' => 'v1',
    'namespace' => 'App\Http\Controllers\Api\V1',
    'middleware' => ['bindings', 'throttle:' . config('api.rate_limits.access')]], function ($api) {
    // 游客可以访问的接口
    $api->post('authorizations', 'AuthorizationsController@store')
        ->name('api.socials.authorizations.store');
    // 登录后可以访问的接口
    $api->group(['middleware' => 'token.canrefresh'], function ($api) {
        $api->get('user', 'UsersController@me')
            ->name('user.show');
        // 刷新token
        $api->put('authorizations/current', 'AuthorizationsController@update')
            ->name('authorizations.update');
        // 删除token
        $api->delete('authorizations/current', 'AuthorizationsController@destroy')
            ->name('authorizations.destroy');
        // 上传图片
        $api->post('images', 'ImagesController@store')
            ->name('images.store');
        //通知列表
        $api->get('notifications', 'NotificationsController@index')
            ->name('notifications.index');
        //通知统计
        $api->get('notifications/stats', 'NotificationsController@stats')
            ->name('notifications.stats');
        //标记消息通知为已读
        $api->patch('user/read/notifications', 'NotificationsController@read')
            ->name('user.notifications.read');
        //买入外汇
        $api->post('buyforeign', 'OrdersController@buy')
            ->name('orders.buy');
        //卖出外汇
        $api->patch('sellforeign/{order}', 'OrdersController@sell')
            ->name('orders.sell');
        //外汇列表
        $api->get('order_index', 'OrdersController@index')
            ->name('orders.index');

    });
});
