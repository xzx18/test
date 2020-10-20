<?php

namespace App\Admin\Controllers;

use App\Models\FinancialSalaryInsuranceModel;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use App\Models\UserModel;
use App\Models\Auth\OAPermission;
use function MongoDB\BSON\fromJSON;

class FinancialSalaryInsuranceController extends Controller
{
    use HasResourceActions;

    public static $places = [1 => '深圳', 2 => '武汉', 3 => '南昌', 4 => '西安'];

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('社保与住房公积金')
            ->description('各地社保与住房公积金缴纳详情')
            ->body($this->grid());
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
            ->description('公积金/社保')
            ->body($this->detail($id));
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
            ->description('公积金/社保')
            ->body($this->form()->edit($id));
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
            ->description('公积金/社保')
            ->body($this->form());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new FinancialSalaryInsuranceModel);

        $grid->column('user.name', '用户名');
        $grid->column('month', '月份')->display(function ($val) {
            return substr($val, 0, 7);
        });
        $grid->column('place', '所在地')->display(function ($val) {
            return isset(self::$places[$val]) ? self::$places[$val] : $val;
        });

        $display_fun1 = function ($val) {
            return ($this->confirmed & 0b01) > 0 ? $val : '';
        };

        $display_fun2 = function ($val) {
            if (($this->confirmed & 0b01) == 0) {
                return '';
            }
            return $this->endowment_personal + $this->medical_personal + $this->large_medical_personal + $this->employment_injury_personal
                + $this->unemployment_personal + $this->maternity_personal;
        };

        $display_fun3 = function ($val) {
            if (($this->confirmed & 0b01) == 0) {
                return '';
            }
            $total = $this->endowment_organization + $this->medical_organization + $this->large_medical_organization + $this->employment_injury_organization
                + $this->unemployment_organization + $this->maternity_organization;
            return "<span style='font-weight: bold'>$total</span>";
        };
        $display_fun4 = function ($val) {
            return ($this->confirmed & 0b10) > 0 ? $val : '';
        };
        $display_fun5 = function ($val) {
            if (($this->confirmed & 0b10) > 0) {
                return "<span style='font-weight: bold'>$val</span>";
            }
            return '';
        };

        $grid->column('endowment_personal', '养老(个人)')->display($display_fun1);
        $grid->column('endowment_organization', '养老(单位)')->display($display_fun1);
        $grid->column('medical_personal', '医疗(个人)')->display($display_fun1);
        $grid->column('medical_organization', '医疗(单位)')->display($display_fun1);
        $grid->column('large_medical_personal', '大额医疗(个人)')->display($display_fun1);
        $grid->column('large_medical_organization', '大额医疗(单位)')->display($display_fun1);
        $grid->column('employment_injury_personal', '工伤(个人)')->display($display_fun1);
        $grid->column('employment_injury_organization', '工伤(单位)')->display($display_fun1);
        $grid->column('unemployment_personal', '失业(个人)')->display($display_fun1);
        $grid->column('unemployment_organization', '失业(单位)')->display($display_fun1);
        $grid->column('maternity_personal', '生育(个人)')->display($display_fun1);
        $grid->column('maternity_organization', '生育(单位)')->display($display_fun1);
        $grid->column('shebao_personal', '社保总额(个人)')->display($display_fun2);
        $grid->column('shebao_organization', '社保总额(单位)')->display($display_fun3);
        $grid->column('housing_provident_fund_personal', '住房公积金(个人)')->display($display_fun4);
        $grid->column('housing_provident_fund_organization', '住房公积金(单位)')->display($display_fun5);
        $grid->mark('备注');

        $grid->model()->where('confirmed', '>', 0)->orderBy('id', 'desc');

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->in('user_id', '用户名')->multipleSelect(UserModel::getAllUsersPluck());
            $filter->in('place', '工作地')->select(self::$places);
            $filter->equal('confirmed', '确认状态')->select(['1' => '社保', '2' => '公积金', '3' => '公积金和社保']);
            $filter->where(function ($query) {
                $input = $this->input;
                $dt = Carbon::parse($input);
                $query->where('month', $dt->toDateString());
            }, '月份')->datetime(['format' => 'YYYY-MM']);
        });

        $current_user = UserModel::getUser();
        $is_administrator = $current_user->isRole('administrator');
        $is_financial = $current_user->isRole('financial.staff');
        $is_administration = $current_user->isRole('administration.staff');
        if ($is_administrator || $is_financial || $is_administration) {
            $grid->tools(function (Grid\Tools $tools) {
                $import_url = route('financial.salary-insurance.import');
                $import_html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$import_url}" target="_self"  class="btn btn-sm btn-warning"  title="导入">
        <i class="fa fa-upload"></i><span class="hidden-xs">&nbsp;&nbsp;导入</span>
    </a>
</div>
eot;
                $table_url = route('financial.insurance.rule.table');
                $table_html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$table_url}" target="_self"  class="btn btn-sm btn-warning"  title="查看公积金扣除规则">
        <i class="fa fa-table"></i><span class="hidden-xs">&nbsp;&nbsp;查看公积金扣除规则</span>
    </a>
</div>
eot;
                $tools->append($import_html);
                $tools->append($table_html);
            });
        }

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(FinancialSalaryInsuranceModel::findOrFail($id));

        $show->id('ID');
        $show->user_id('用户名')->as(function ($user_id) {
            return UserModel::find($user_id)->name ?? ('User' . $user_id);
        });
        $show->month('日期')->as(function ($val) {
            return Carbon::parse($val)->format("Y-m");
        });
        $show->place('工作地')->as(function ($val) {
            $places = FinancialSalaryInsuranceController::$places;
            return $places[$val];
        });

        $display_sb = function ($val) {
            if (($this->confirmed & 0b01) > 0) {
                return $val;
            }
            return "<span style='color:#e08e0b'>$val</span>";

        };
        $display_gjj = function ($val) {
            if (($this->confirmed & 0b10) > 0) {
                return $val;
            }
            return "<span style='color:#e08e0b'>$val</span>";

        };
        $show->endowment_personal('养老保险(个人)')->as($display_sb);
        $show->endowment_organization('养老保险(单位)')->as($display_sb);
        $show->medical_personal('医疗保险(个人)')->as($display_sb);
        $show->medical_organization('医疗保险(单位)')->as($display_sb);
        $show->employment_injury_personal('工伤保险(个人)')->as($display_sb);
        $show->employment_injury_organization('工伤保险(单位)')->as($display_sb);
        $show->unemployment_personal('失业保险(个人)')->as($display_sb);
        $show->unemployment_organization('失业保险(单位)')->as($display_sb);
        $show->maternity_personal('生育保险(个人)')->as($display_sb);
        $show->maternity_organization('生育保险(单位)')->as($display_sb);
        $show->housing_provident_fund_personal('住房公积金(个人)')->as($display_gjj);
        $show->housing_provident_fund_organization('住房公积金(单位)')->as($display_gjj);
        $show->mark('备注');


        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {

        $current_user = UserModel::getUser();
        $is_administrator = $current_user->isRole('administrator');
        $is_financial = $current_user->isRole('financial.staff');
        $is_administration = $current_user->isRole('administration.staff');
        if (!$is_administrator && !$is_financial && !$is_administration) {
            OAPermission::error();
        }
        $form = new Form(new FinancialSalaryInsuranceModel);

        $form->select('user_id', '用户名')->options(UserModel::getAllUsersPluck())->rules('required');
        $form->datetime('month', '日期')->format("YYYY-MM")->default(Carbon::now()->firstOfMonth());
        $form->select('place', '工作地')->options(self::$places)->rules('required')->default($this->getDefaultPlace());
        $form->decimal('endowment_personal', '养老保险(个人)')->default(0.00);
        $form->decimal('endowment_organization', '养老保险(单位)')->default(0.00);
        $form->decimal('medical_personal', '医疗保险(个人)')->default(0.00);
        $form->decimal('medical_organization', '医疗保险(单位)')->default(0.00);
        $form->decimal('employment_injury_personal', '工伤保险(个人)')->default(0.00);
        $form->decimal('employment_injury_organization', '工伤保险(单位)')->default(0.00);
        $form->decimal('unemployment_personal', '失业保险(个人)')->default(0.00);
        $form->decimal('unemployment_organization', '失业保险(单位)')->default(0.00);
        $form->decimal('maternity_personal', '生育保险(个人)')->default(0.00);
        $form->decimal('maternity_organization', '生育保险(单位)')->default(0.00);
        $form->decimal('housing_provident_fund_personal', '住房公积金(个人)')->default(0.00);
        $form->decimal('housing_provident_fund_organization', '住房公积金(单位)')->default(0.00);
        $form->radio('confirmed', '确认(添加的记录只显示你选中的部分)')->options(['1' => '社保', '2' => '公积金', '3' => '公积金和社保'])->default(3);
        $form->text('mark', '备注');

        $form->saving(function ($form) {
            $form->month = $form->month . '-01';
        });

        return $form;
    }

    private function getDefaultPlace()
    {
        $place = array_search(UserModel::getUser()->workplace, [1 => '深圳', 2 => '武汉', 3 => '南昌', 4 => '西安']);
        return !$place ? 0 : $place;
    }

}
