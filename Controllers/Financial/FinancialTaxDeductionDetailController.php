<?php

namespace App\Admin\Controllers;

use App\Models\Auth\OAPermission;
use App\Models\FinancialTaxDeductionModel;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Grid\Row;


class FinancialTaxDeductionDetailController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    private $user_id;

    public function __Construct(Request $request)
    {
        $this->user_id = $request->input('user_id');
    }

    public function index(Content $content)
    {
        $user = UserModel::getUser($this->user_id) ?? UserModel::getUser();
        return $content
            ->header("{$user->name}({$user->truename}) 专项扣除详情")
            ->description('根据国家税务系统填报： 个人所得税APP->我要查询->专项附加扣除信息查询')
            ->body($this->grid());
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
            ->header('填写专项扣除类别、额度、时间')
            ->description('个人所得税APP->我要查询->专项附加扣除信息查询，查询本年度扣除项目及额度，填写到下面')
            ->body($this->form());
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
            ->header('修改专项扣除类别、额度、时间')
            ->description('个人所得税APP->我要查询->专项附加扣除信息查询，查询本年度扣除项目及额度，填写到下面')
            ->body($this->form()->edit($id));
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $user_id = $this->user_id;
        $current_user = UserModel::getUser();
        if (!$user_id) {
            $user_id = $current_user->id;
        }

        $grid = new Grid(new FinancialTaxDeductionModel);
        $grid->model()->where('user_id', $user_id)->orderBy('id', 'desc');

        $grid->tax_type('税务扣除类型')->display(function ($val) {
            return FinancialTaxDeductionModel::$tax_type[$val];
        });
        $grid->money('每月金额');
        $grid->start_month('开始月份')->display(function ($val) {
            return date('Y-m', strtotime($val));
        });
        $grid->end_month('结束月份')->display(function ($val) {
            return date('Y-m', strtotime($val));
        });
        $grid->updated_at('更新时间');

        $grid->actions(function ($actions) use ($user_id, $current_user) {
            $actions->disableView();
            if ($user_id && $user_id != $current_user->id && !OAPermission::isAdministrator()) {
                $actions->disableEdit();
            }
        });

        $current_date = date("Y-m-d");
        $grid->rows(function (Row $row) use ($current_date) {
            $is_valid = FinancialTaxDeductionModel::where('id', $row->id)
                ->where('start_month', '<=', $current_date)
                ->where('end_month', '>=', $current_date)
                ->value('id');

            if (!$is_valid) {
                $row->style([
                    'color' => 'darkgrey',
                ]);
            }
        });

        if ($user_id && $user_id != $current_user->id && !OAPermission::isAdministrator()) {
            $grid->disableCreateButton();
            $grid->disableRowSelector();
        }

        $grid->disableFilter();
        $grid->disableExport();
        $grid->disableRowSelector();

        return $grid;
    }


    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $current_user = UserModel::getUser();
        $form = new Form(new FinancialTaxDeductionModel);

        $form->display('user_id', '姓名')->with(function ($val) use ($current_user) {
            return !$val ? $current_user->truename : UserModel::where('id', $val)->value('truename');
        });

        $form->select('tax_type', '扣税类型')->options(FinancialTaxDeductionModel::$tax_type)->rules('required');
        $form->decimal('money', '每月金额')->attribute('style', 'width: 120px')->default(0.00)->help('本项每月扣除的总额，请与税务系统中一致');

        $dt = Carbon::now();
        $form->date('start_month', '开始月份')->attribute('style',
            'width: 120px')->default($dt->format('Y-m'))->format('YYYY-MM')->help('本项扣除开始的月份，请与税务系统中一致（不确定的话则从本年度1月开始）');
        $dt = $dt->endOfYear();
        $form->date('end_month', '结束月份')->attribute('style',
            'width: 120px')->default($dt->format('Y-m'))->format('YYYY-MM')->help('本项扣除结束的月份，请与税务系统中一致，默认为到年底');

        $form->tools(function (Form\Tools $tools) {
            $tools->disableList();
            $tools->disableDelete();
            $tools->disableView();

        });

        $form->disableViewCheck();
        $form->disableEditingCheck();

        $user_id = $current_user->id;
        $form->saving(function (Form $form) use ($user_id) { //保存前
            $form->start_month = $form->start_month . '-01';
            $form->end_month = $form->end_month . '-01';
            $form->user_id = $user_id;
        });

        return $form;
    }

}
