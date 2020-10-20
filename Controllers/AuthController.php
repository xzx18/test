<?php
/**
 * Created by PhpStorm.
 * User: white
 * Date: 9/13/18
 * Time: 7:31 PM
 */

namespace App\Admin\Controllers;

use App\Models\AuditModel;
use App\Models\ClientModel;
use App\Models\UserMetaModel;
use App\Models\UserModel;
use Encore\Admin\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends \Encore\Admin\Controllers\AuthController
{
    public function getLogin()
    {
        if ($this->guard()->check()) {
            return redirect($this->redirectPath());
        }
        return view('admin/login', [
            'redirectTo' => \request('redirectTo')
        ]);
    }

    /**
     * Handle a login request.
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function postLogin(Request $request)
    {
        $credentials = $request->only([$this->username(), 'password']);

        //如果username没有带email后缀  @wangxutech.com，则自动加上
        $validate_result = Validator::make($credentials, [$this->username() => 'email']);
        if ($validate_result->fails()) {
            $credentials[$this->username()] = $credentials[$this->username()] . '@' . config('app.organization_domain');
        }
        /** @var \Illuminate\Validation\Validator $validator */
        $validator = Validator::make($credentials, [
            $this->username() => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $result = $this->guard()->attempt($credentials);

        if ($result) {
            $user = UserModel::getUser();
            AuditModel::login($user->id, $credentials[$this->username()], AuditModel::LOGIN_TYPE_INPUT, true);
            $redirect_url = $this->redirectTo();
            if ($redirect_url) {
                return \redirect($redirect_url);
            }
            return $this->sendLoginResponse($request);
        } else {
            AuditModel::login(0, $credentials[$this->username()], AuditModel::LOGIN_TYPE_INPUT, false);
        }

        return back()->withInput()->withErrors([
            $this->username() => $this->getFailedLoginMessage(),
        ]);
    }

    public function redirectTo()
    {
        $redirect_to = urldecode(session(ClientModel::URL_REDIRECT_KEY, ''));
        if ($redirect_to) {
            session()->remove(ClientModel::URL_REDIRECT_KEY);
            return $redirect_to;
        }
        return config('admin.route.prefix');
    }

    /**
     * Model-form for user setting.
     *
     * @return Form
     */
    protected function settingForm()
    {
        $form = new Form(new UserModel());
        $form->disableEditingCheck();
        $form->disableViewCheck();
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
            $tools->disableView();
            $tools->disableList();
        });
        $form->display('id', 'ID');
        $form->display('username', trans('admin.username'));
        $form->display('name', trans('admin.name'));

        $form->select('language', tr('Language'))->options(config('app.languages'));
        $form->password('password', trans('admin.password'))->rules('confirmed|required');
        $form->password('password_confirmation', trans('admin.password_confirmation'))->rules('required')
            ->default(function ($form) {
                return $form->model()->password;
            });

        $form->setAction(admin_base_path('auth/setting'));

        $form->ignore(['password_confirmation']);

        $form->saving(function (Form $form) {
            if ($form->password && $form->model()->password != $form->password) {
                $form->password = bcrypt($form->password);
            }
        });

        $form->saved(function () {
//            $lang = DingtalkUser::getUser()->getLanguage();
//            if ($lang) {
//                config(['app.locale' => $lang]);
//                session()->put('app.locale', $lang);
//            }
            admin_toastr(trans('admin.update_succeeded'));
            return redirect(admin_base_path('auth/setting'));
        });

        return $form;
    }
}
