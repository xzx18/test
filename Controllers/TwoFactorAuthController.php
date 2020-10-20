<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Dingtalk;
use App\Models\TwoFactorAuthModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Form;
use function request;

class TwoFactorAuthController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        $form = new Form();
        $form->action(route('tfa.store'));

//			$form->html("<p style='margin-bottom: 30px; color: lightgray;'>您当前访问的是敏感数据，需要进行二次认证，以确保是您本人访问。<br>Two-factor authentication is an extra layer of security for your account designed to ensure that you're the only person who can access your account, even if someone knows your password.</p>", 'Tips');
        $form->html("<p style='margin-bottom: 30px; color: lightgray;'>您当前访问的是敏感数据，需要进行二次认证，以确保是您本人访问。</p>", 'Tips');

        $form->text('code', '验证码')->attribute(['maxlength' => 6])->placeholder('[钉钉/Dingtalk] 查看');
        $getButton = <<<eot
<button type="button" class="btn btn-primary" id="btn-send-code">点击获取验证码<span id="countdown"></span></button><span id="result" style="margin-left: 20px;"></span>
eot;
        $form->html($getButton);

        $box = new Box('打开 [钉钉/Dingtalk] 查看验证码', $form->render());
        $box->style('warning');
        $box->solid();

        $send_url = route('tfa.send.code');
        $js = <<<eot
			<style>
			.code{color: blue;}
			</style>
<script>
	$(function () {
		var timer_send_code;
		function countdown() {
			timer_send_code = setInterval(_countdown, 1000);
			var c = 60;
			function _countdown() {
				$("#countdown").html( ' (' + --c + ')');
				if (c == 0) {
					clearInterval(timer_send_code);
					btnSend.prop('disabled','');
					$("#countdown").html('');
				}
			}
		}
   
	    var btnSend=$('#btn-send-code');
		btnSend.click(function(e) {
		   btnSend.prop('disabled','disabled');
		   $("#countdown").html( ' (' + 60 + ')');
		   countdown();
			var post_data = {
				_token: LA.token
			};
			NProgress.configure({parent: '.form-horizontal'});
			NProgress.start();
			$.post('{$send_url}', post_data, function (response) {
				if(response['state']==1){
					$("#result").html('发送成功').removeClass().addClass('badge badge-success');
				}else{
				    $("#result").html('发送失败').removeClass().addClass('badge badge-danger');
				    clearInterval(timer_send_code);
				    $("#countdown").html('');
				}
			}).fail(function (jqXHR, textStatus, errorThrown) {
				NProgress.done();
			}).always(function () {
				NProgress.done();
			});
		});
	});
</script>
eot;

        return $content
            ->header('二次认证 ( Two Factor Authentication )')
            ->description(' ')
            ->row(function (Row $row) use ($box) {
                $row->column(3, '');
                $row->column(6, $box->render());
                $row->column(3, '');
            })
            ->body($js);
    }

    public function sendCode()
    {
        $exist_code = TwoFactorAuthModel::getCodeRemaining();

        if (!$exist_code) {
            $user = UserModel::getUser();
            //过滤重复请求
            $duplicate_row = TwoFactorAuthModel::where('user_id', $user->id)->where('created_at', Carbon::now()->toDateTimeString())->first();
            if (!$duplicate_row) {
                $dingtalk_userid = $user->userid;
                $code = TwoFactorAuthModel::createCode($user->id);
                $code_lifetime = TwoFactorAuthModel::CodeLifetime;
                $content = <<<eot
【OA系统】验证码:  {$code}  {$code_lifetime}秒后失效，请勿向任何人提供验证码
eot;

                $response = Dingtalk::sendMessage($dingtalk_userid, $content);
                $state = 0;
                $ding_open_errcode = $response['result']['ding_open_errcode'] ?? -1;
                $ding_task_id = $response['result']['task_id'] ?? 0;
                if ($response && $ding_open_errcode == 0 && $ding_task_id) {
                    TwoFactorAuthModel::saveCode($code);
                    $state = 1;
                }
            }
        } else {
            $state = 1;
        }
        return [
            'state' => $state,
        ];
    }

    public function store(Content $content)
    {
        $code = request('code');
        $valid = TwoFactorAuthModel::verifyCode($code);
        if (!$valid) {
            admin_error('验证码无效', '请打开钉钉查看正确的验证码');
        } else {
            $url = TwoFactorAuthModel::getPreviousUrl();
            if ($url) {
                return redirect($url);
            } else {
                admin_success('验证成功', '请继续访问您要访问的页面');
            }
        }
    }
}
