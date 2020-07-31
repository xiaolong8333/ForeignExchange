<?php

namespace App\Admin\Controllers;

use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class UserController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '用户';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new User());

        $grid->column('id', __('Id'));
        $grid->column('name', __('Name'));
        $grid->column('phone', __('手机号'))
            ->display(function ($phone) {

                return yc_phone($phone);

            });
        $grid->column('email', __('邮箱'));
        //$grid->column('email_verified_at', __('Email verified at'));
        //$grid->column('password', __('Password'));
        $grid->column('balance', __('余额'));
        $grid->column('status', __('状态'))
            ->using(['0' => '正常', '1' => '冻结'])
            ->label([
                0 => 'warning',
                1 => 'success',
            ]);
        $grid->column('level', __('等级'));
        $grid->column('end_time', __('结束时间'));
        //$grid->column('remember_token', __('Remember token'));
        $grid->column('created_at', __('创建时间'));
        $grid->column('updated_at', __('修改时间'));

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(User::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('name', __('Name'));
        $show->field('phone', __('手机号'));
        $show->field('email', __('Email'));
        $show->field('email_verified_at', __('Email verified at'));
        $show->field('password', __('Password'));
        $show->field('balance', __('Balance'));
        $show->field('status', __('Status'));
        $show->field('level', __('Level'));
        $show->field('end_time', __('End time'));
        $show->field('remember_token', __('Remember token'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new User());

        $form->text('name', __('Name'));
        $form->email('email', __('Email'));
        $form->text('phone', __('手机号'));
        $form->datetime('email_verified_at', __('Email verified at'))->default(date('Y-m-d H:i:s'));
        $form->password('password', __('Password'));
        $form->number('balance', __('Balance'));
        $form->switch('status', __('Status'));
        $form->switch('level', __('Level'));
        $form->datetime('end_time', __('End time'))->default(date('Y-m-d H:i:s'));
        $form->text('remember_token', __('Remember token'));
        $form->saving(function (Form $form) {
            if ($form->password && $form->model()->password != $form->password) {
                $form->password = password_hash($form->password, PASSWORD_DEFAULT);
            }
        });
        return $form;
    }
}