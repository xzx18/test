<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\ExtendedGrid;
use App\Http\Controllers\Controller;
use App\Models\UserGrowthModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class UserGrowthController extends Controller
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
        return $content
            ->header('成长记录')
            ->description('记录重要事件')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $timezone = UserModel::getUserTimezone();
        $grid = new ExtendedGrid(new UserGrowthModel());

        $grid->id('ID')->sortable();
        $grid->column('user.name', '用户名')->sortable();
        $grid->record_type('类型')->sortable()->display(function ($val) {
            return UserGrowthModel::getEventTypeHuman($val);
        });
        $grid->mark('备注')->display(function ($val) {
            return ($val);
        });
        $grid->column('created_user.name', '创建人')->sortable();

        $grid->created_at(trans('admin.created_at'))->display(function ($val) use ($timezone) {
            $dt = Carbon::parse($val)->setTimezone($timezone);
            return $dt->toDateTimeString();
        });
        $grid->model()->orderBy('id', 'desc');

        $grid->disableRowSelector();
        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->in('user_id', '用户名')->multipleSelect(UserModel::getAllUsersPluck());
            $filter->in('record_type', '类型')->multipleSelect(UserGrowthModel::getPluck());
        });

        $grid->disableActions();
        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableDelete();
            $actions->disableView();
        });

        return $grid;
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
            ->header('Detail')
            ->description('description')
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
        $show = new Show(UserGrowthModel::findOrFail($id));

        $show->id('ID');
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
            ->header('Edit')
            ->description('description')
            ->body($this->form()->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $current_user = UserModel::getUser();
        $form = new Form(new UserGrowthModel);
        $form->hidden('created_by')->default($current_user->id);
        $form->display('id', 'ID');

        $form->select('user_id', '用户名')->options(UserModel::getAllUsersPluck());
        $form->select('record_type', '事件类型')->options(UserGrowthModel::getPluck());
        $form->editor('mark', '备注')->help('可以上传图片作为依据保存');
        $form->html('<p style="color: red; font-size: 20px; margin-top: 3px;">一旦创建，将无法修改</p>', '提示');

        $form->display('created_at', 'Created At');
        $form->display('updated_at', 'Updated At');

        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
            $tools->disableView();
        });
        $form->disableViewCheck();
        $form->disableEditingCheck();

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
            ->header('创建记录')
            ->description('一旦创建，将无法修改')
            ->body($this->form());
    }
}
