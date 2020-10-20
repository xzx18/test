<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CreditDepartmentAggregationModel;
use App\Models\CreditSummaryDepartmentModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class CreditSummaryDepartmentController extends Controller
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
            ->header('部门积分统计')
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
        $timezone = UserModel::getUserTimezone();
        $grid = new Grid(new CreditSummaryDepartmentModel());
        $grid->disableExport();
        $grid->disableActions();
        $grid->disableCreateButton();
        $grid->disableRowSelector();

        $grid->model()->orderBy('date', 'desc')->orderBy('credit_value', 'desc');

        $grid->id('ID')->sortable();

        $grid->aggregation('部门')->display(function ($val) {
            return $val['title'];
        });
        $grid->date('月份')->sortable()->display(function ($val) {
            $dt = Carbon::parse($val);
            $now = Carbon::now();
            $v = $dt->format('Y年m月');
            if ($dt->year == $now->year && $dt->month == $now->month) {
                return "<span class='blue'>$v</span>";
            }
            return $v;
        });
        $grid->credit_value('积分')->sortable();

        $grid->updated_at(trans('admin.updated_at'))->display(function ($val) use ($timezone) {
            $dt = Carbon::parse($val)->setTimezone($timezone);
            return $dt->toDateTimeString();
        });

        $grid->model()->whereIn('aggregation_id', function ($query) {
            $query->select('id')
                ->from(CreditDepartmentAggregationModel::getTableName());
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
        $show = new Show(CreditSummaryDepartmentModel::findOrFail($id));

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
        $form = new Form(new CreditSummaryDepartmentModel);

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
