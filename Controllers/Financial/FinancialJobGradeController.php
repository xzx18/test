<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Auth\OAPermission;
use App\Models\FinancialJobGradeModel;
use App\Models\ProfessionModel;
use App\Models\UserModel;
use App\Models\WorkplaceModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Support\Collection;

class FinancialJobGradeController extends Controller
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
            ->header('职级管理')
            ->description(' ')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new FinancialJobGradeModel);

        $grid->id('ID')->sortable();
        //$grid->name('名称')->sortable();

        $grid->job_category('类型')->sortable();
        $grid->job_rank('职级')->sortable();
        $grid->column('profession.name', '名称')->sortable();

        $grid->column('workplace.name', '地域')->sortable()->display(function ($val) {
            if ($val == "南昌") {
                $val .= "/西安";
            }
            return $val;
        });

        $grid->salary_start('薪资起点')->sortable();
        $grid->salary_end('薪资终点')->sortable();

        $grid->performance_salary('绩效工资')->sortable();
        $grid->sale_dividend('销售分红')->sortable()->display(function ($val) {
            if ($val) {
                return "有";
            }
            return "无";
        });
        $grid->share_dividend('股权分红')->sortable()->display(function ($val) {
            if ($val) {
                return "有";
            }
            return "无";
        });
        $grid->floating_coefficient('浮动系数')->sortable();


        $grid->created_at(trans('admin.created_at'));
//			$grid->updated_at(trans('admin.updated_at'));


        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->like('name', '名称');
            $filter->in('workplace_id', '地域')->multipleSelect(WorkplaceModel::getAllWithPluck());
            $filter->in('profession_id', '岗位名称')->multipleSelect(ProfessionModel::getAllWithPluck());
            $filter->in('job_category', '岗位类型')->multipleSelect(ProfessionModel::$JOB_CATEGORIES);
            $filter->equal('job_rank', '岗位职级');
        });


        //授权职级查看区域
        if (!OAPermission::isAdministrator()) {
            $manage_jobgrade_workplace_ids = [0];
            $current_user = UserModel::getUser();
            /** @var Collection $manage_jobgrade_workplaces */
            $manage_jobgrade_workplaces = $current_user->manage_jobgrade_workplaces;
            if ($manage_jobgrade_workplaces) {
                $manage_jobgrade_workplace_ids = $manage_jobgrade_workplaces->pluck('title', 'id')->toArray();
            }
            $grid->model()->whereIn('workplace_id', array_keys($manage_jobgrade_workplace_ids));
        }

        $grid->model()->orderBy('id', 'desc');
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
        $show = new Show(FinancialJobGradeModel::findOrFail($id));

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
        $form = new Form(new FinancialJobGradeModel);

        $form->display('id', 'ID');
        $form->text('name', '职级名称');

        $form->select('job_category', '岗位类型')->options(ProfessionModel::$JOB_CATEGORIES);
        $form->select('profession_id', '岗位名称')->options(ProfessionModel::getAllWithPluck());

        $form->select('workplace_id', '所在地域')->options(WorkplaceModel::getAllWithPluck());
        $form->number('job_rank', '岗位职级')->min(0)->max(14)->default(1);

        $form->decimal('salary_start', '薪资起点');
        $form->decimal('salary_end', '薪资终点');
        $form->decimal('performance_salary', '绩效工资');
        $form->decimal('floating_coefficient', '浮动系数(最高)');
        $form->switch('sale_dividend', '销售分红');
        $form->switch('share_dividend', '股权分红');

        $form->display('created_at', trans('admin.created_at'));
        $form->display('updated_at', trans('admin.updated_at'));

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
