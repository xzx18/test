<?php

namespace App\Admin\Controllers;

use App\Models\AppraisalResultModel;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\UserModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Carbon\Carbon;
use App\Models\AppraisalTableTypeDeveloper;
use App\Models\AppraisalTableTypeCorporateCulture;
use App\Models\Auth\OAPermission;

class AppraisalResultGradeController extends Controller
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
            ->header('绩效列表')
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
        $grid = new Grid(new AppraisalResultModel);

        $grid->id('ID')->sortable();
        $grid->column('季度')->display(function () {
            $date = $this->year . '-' . $this->month;
            $dt = Carbon::parse($date);
            return sprintf('%s Q%s', $dt->year, $dt->quarter);
        });
        $grid->user_id('员工名')->display(function ($uid) {
            $user = UserModel::getUser($uid);
            return $user->name;
        });
        $grid->department_id('考核归属部门')->display(function ($val) {
            return Department::find($val)->name ?? strval($val);
        })->sortable();
        $grid->result('绩效等级')->display(function ($val) {
            $style = AppraisalTableTypeDeveloper::getGradeStyle($val);
            return sprintf('<span class="badge badge-%s">%s</span>', $style, $val);
        })->sortable();
        $grid->culture('企业文化')->display(function ($val) {
            $style = AppraisalTableTypeCorporateCulture::getGradeStyle($val);
            return sprintf('<span class="badge badge-%s">%s</span>', $style, $val);
        })->sortable();
        $grid->confirmed('审阅状态')->display(function ($val) {
            $strs = AppraisalResultModel::getConfirmedValues();
            $str = $strs[$val] ?? '';
            $color = AppraisalResultModel::isConfirmed($val) ? 'green' : '#880000';
            return "<span style='color:{$color}'>{$str}</span>";
        });
        $grid->updated_at('更新时间');

        $grid->model()->orderBy('id', 'desc');
        $grid->paginate(30);

        if (!(OAPermission::isAdministrator() || OAPermission::isHr() || OAPermission::isFinancialStaff() || OAPermission::isRole('appraisal.manager'))) {
            $current_user = UserModel::getUser();
            $grid->model()->where('user_id', $current_user->id);
        }

        $grid->filter(function ($filter) {  //通过名字筛选
            $filter->disableIdFilter();
            $filter->where(function ($query) {
                $query->whereHas('evaluate_user', function ($query) {
                    $query->where('name', 'like', "%{$this->input}%");
                });
            }, '员工名');
            $filter->where(function ($query) {
                if ($this->input) {
                    $date = explode('-', $this->input);
                    $query->where('year', $date[0]);
                    $query->where('month', $date[1]);
                }
            }, '季度', 'quarter')->select(
                AppraisalResultModel::getGridFilterQuarter()
            );
            $filter->equal('department_id', '考核归属部门')->select(AppraisalResultModel::getDepartments());
            $filter->equal('result', '绩效等级')->select(AppraisalResultModel::getResultLevels());
            $filter->equal('culture', '企业文化')->select(AppraisalResultModel::getCultureLevels());
            $filter->in('confirmed', '审阅状态')->multipleSelect(AppraisalResultModel::getConfirmedValues());
        });

        $grid->disableCreateButton();
        $grid->disableExport();
        $grid->disableRowSelector();
        $grid->disableActions();

        return $grid;
    }

}
