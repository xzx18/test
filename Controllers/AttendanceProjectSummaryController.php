<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceProjectSummaryModel;
use App\Models\ProjectModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class AttendanceProjectSummaryController extends Controller
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
            ->header('项目台账')
            ->description('')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new AttendanceProjectSummaryModel);

        $grid->id('ID')->sortable();
        $grid->column('user.name', '用户名')->sortable();
        $grid->column('project.name', '项目名')->sortable();
        $grid->date('月份')->sortable()->display(function ($val) {
            $dt = Carbon::parse($val);
            return $dt->format('Y年m月');
        });
        $grid->seconds('时长')->sortable()->display(function ($val) {
            return formatSecondsToHours($val);
        });
        $grid->updated_at(trans('admin.updated_at'));
        $grid->disableRowSelector();
        $grid->disableActions();
        $grid->disableCreateButton();
        $grid->model()->orderBy('id', 'DESC');

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->in('user_id', '用户名')->multipleSelect(UserModel::getAllUsersPluck());
            $filter->in('project_id', '项目名')->multipleSelect(ProjectModel::getAllProjectPlucks());
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
        $show = new Show(AttendanceProjectSummaryModel::findOrFail($id));

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
        $form = new Form(new AttendanceProjectSummaryModel);

        $form->display('id', 'ID');
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
            ->header('Create')
            ->description('description')
            ->body($this->form());
    }
}
