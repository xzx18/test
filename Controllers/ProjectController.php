<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProjectModel;
use App\Models\UserModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class ProjectController extends Controller
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
            ->header('项目管理')
            ->description('管理公司目前的主要项目，方便财务做研发工时统计')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new ProjectModel());
        $grid->disableActions();
        $grid->disableRowSelector();
        $grid->disableExport();
        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->like('name', '项目名');
        });
        $grid->id('ID')->sortable();
        $grid->name('项目名')->sortable();
        $grid->created_user('添加人')->display(function ($val) {
            return $val['name'] ?? '管理员';
        });
        $grid->created_at(trans('admin.created_at'));

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
        $show = new Show(ProjectModel::findOrFail($id));

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
        $user = UserModel::getUser();
        $form = new Form(new ProjectModel());
        $form->disableEditingCheck();
        $form->disableViewCheck();
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
            $tools->disableView();
        });
        $form->display('id', 'ID');
        $form->text('name', '项目名')->help('请规范书写项目名称，比如 ApowerRec、在线视频编辑、视频编辑等等');
        $form->display('created_at', 'Created At');
        $form->display('updated_at', 'Updated At');
        $form->hidden('created_by', 'created_by')->value($user->id);

        $form->saving(function (Form $form) {
            request()->validate([
                'name' => 'required',
            ]);
        });
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
            ->header('创建')
            ->description('添加项目名')
            ->body($this->form());
    }
}
