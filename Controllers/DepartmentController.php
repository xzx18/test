<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class DepartmentController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return C ontent
     */
    public function index(Content $content)
    {

        return $content
             ->header('部门设置')
            ->description('设置每个部门的参数')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Department());
        $grid->disableRowSelector();
        $grid->disableExport();
        $grid->disableCreateButton();

        $grid->model()->where('id', '>', 1);

        $grid->id('ID')->sortable();
        $grid->name('名称');
        $grid->allow_attendance_rank('考勤排名')->display(function ($val) {
            if ($val) {
                return '<span class="badge badge-success">参与</span>';
            }
            return '<span class="badge badge-secondary">不参与</span>';
        })->sortable();

        $grid->allow_dingtalk_remind('OA未登录钉钉提醒')->display(function ($val) {
            if ($val) {
                return '<span class="badge badge-success">参与</span>';
            }
            return '<span class="badge badge-secondary">不参与</span>';
        })->sortable();

        $grid->actions(function ($actions) {
            $actions->disableDelete();
            $actions->disableView();
        });

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->like('name', trans('admin.name'));
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
            ->header('详情')
            ->description(' ')
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
        $show = new Show(Department::findOrFail($id));
        $show->id('ID');
        $show->name('名称');
        $show->allow_attendance_rank('考勤排名');
        $show->allow_dingtalk_remind('OA未登录钉钉提醒');
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
            ->description('编辑部门设置')
            ->body($this->form()->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Department);
        $form->disableEditingCheck();
        $form->disableViewCheck();
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
        });
        $form->display('name', '名称');
        $form->switch('allow_attendance_rank', '是否参与考勤排名');
        $form->switch('allow_dingtalk_remind', 'OA未登录钉钉提醒');
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
            ->header('Create')
            ->description('description')
            ->body($this->form());
    }
}
