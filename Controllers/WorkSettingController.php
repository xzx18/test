<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AliyunModel;
use App\Models\Auth\OAPermission;
use App\Models\UserModel;
use App\Models\WorkSettingModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Box;

class WorkSettingController extends Controller
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
        $user = UserModel::getUser();
        if (!in_array($user->username, config('app.super_users'))) {
            $body = OAPermission::ERROR_HTML;
        } else {
            $body = $this->grid();
        }
        return $content
            ->header('工作设置')
            ->description(' ')
            ->body($body);
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new WorkSettingModel());
        $grid->disableFilter();
        $grid->disableExport();

        $grid->id('ID')->sortable();
        $grid->user('User')->display(function ($val) {
            return "{$val['name']}";
        });

        $grid->upload_type('上传模式')->display(function ($val) {
            $style = WorkSettingModel::getTypeStyle($val);
            $desc = WorkSettingModel::getUploadTypeHuman($val);
            return "<span class='badge badge-$style'>$desc</span>";
        })->sortable();

        $grid->upload_region('存储区域')->display(function ($val) {
            $style = WorkSettingModel::getUploadRegionStyle($val);
            $region = WorkSettingModel::$UPLOAD_REGION[$val];
            return "<span class='badge badge-$style'>$region</span>";
        })->sortable();

        $grid->web_type('Web操作')->display(function ($val) {
            $style = WorkSettingModel::getTypeStyle($val);
            $desc = WorkSettingModel::getWebTypeHuman($val);
            return "<span class='badge badge-$style'>$desc</span>";
        })->sortable();


        $grid->upload_interval('上传间隔')->sortable();

        $grid->updated_at('Updated at')->sortable();

        $body = $grid->render();

        $box = new Box('说明', <<<eot
<b>上传模式</b>：
<ul>
<li><b>禁用</b>：客户端不会截图上传，即使用户点击了“开始工作”</li>
<li><b>自动</b>：户点击“开始工作”才会开始截图上传，“结束工作”后将不再上传</li>
<li><b>强制</b>：只要软件启动了，就开始上传，一直到软件退出</li>
</ul>
eot
        );
        $box->style('primary');
        $body .= $box->render();
        return $body;

    }

    /**
     * Show interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function show($id, Content $content)
    {
        return $content
            ->header('详情')
            ->description('配置详情')
            ->body($this->detail($id));
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(WorkSettingModel::findOrFail($id));

        $show->id('ID');
        $show->user()->name('User')->label();
        $show->upload_type('上传模式')->label();
        $show->upload_interval('上传间隔')->label();
        $show->created_at('Created at');
        $show->updated_at('Updated at');

        return $show;
    }

    /**
     * Edit interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function edit($id, Content $content)
    {
        return $content
            ->header('编辑')
            ->description('编辑上传配置')
            ->body($this->form(true)->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($is_edit = false)
    {
        $form = new Form(new WorkSettingModel);

        if ($is_edit) {
            $form->display('id', 'ID');
            $form->display('user.name', '选择用户');
        } else {
            $form->select('user_id', '选择用户')->options(UserModel::all(['id', 'name'])->pluck('name', 'id'));
        }

        $form->number('upload_interval', '上传间隔（秒）')->default(config('oa.attendance.upload.interval'));
        $form->radio('upload_type', '上传模式')->options(WorkSettingModel::$UPLOAD_TYPE)->default(WorkSettingModel::UPLOAD_TYPE_AUTO);
        $form->radio('upload_region', '存储区域')->options(WorkSettingModel::$UPLOAD_REGION)->default(AliyunModel::REGION_AUTO)->help('建议选择自动，将自动根据用户所在区域进行全局设置');

        $form->radio('web_type', 'Web操作')->options(WorkSettingModel::$WEB_TYPE)->default(WorkSettingModel::WEB_TYPE_AUTO);

        $form->display('created_at', 'Created At');
        $form->display('updated_at', 'Updated At');

        return $form;
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content)
    {
        return $content
            ->header('新建')
            ->description('新建上传配置')
            ->body($this->form(false));
    }
}
