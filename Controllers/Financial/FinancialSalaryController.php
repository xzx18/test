<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\ExtendedGrid;
use App\Admin\Extensions\Tools\BatchActionFinancial;
use App\Admin\Extensions\Tools\FinancialSalaryImportExportModel;
use App\Http\Controllers\Controller;
use App\Models\Auth\OAPermission;
use App\Models\Financial\FinancialSalaryImport;
use App\Models\FinancialAuditSalaryModel;
use App\Models\FinancialCategoryModel;
use App\Models\FinancialDetailModel;
use App\Models\FinancialInsuranceBaseModel;
use App\Models\FinancialLogModel;
use App\Models\FinancialSalaryModel;
use App\Models\UserJobLevelModel;
use App\Models\UserModel;
use App\Models\WorkplaceModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Tab;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FinancialSalaryController extends Controller
{
    use HasResourceActions;

    private static $salary_details = null;

    private static $managed_workplaces = null;
    private $categories = null;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        $html = $this->getWorkplaceGrid($content)->render();
        $html = str_replace('<th>', '<td style="font-weight:bold;">', $html);
        $html = str_replace('</th>', '</td>', $html);
        return $html;
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content, $workplace_id = null)
    {
        return $content
            ->header('Create')
            ->description('description')
            ->body($this->form(null, $workplace_id));
    }

    public function getWorkplaceGrid(Content $content, $workplace_id = null)
    {
        $current_user = UserModel::getUser();
        $tab = new Tab();
        self::$managed_workplaces = $current_user->getManagedFinancialWorkplaces(true);

        $grid = OAPermission::ERROR_HTML;
        if ($workplace_id == null || self::$managed_workplaces->has($workplace_id)) {
            $grid = $this->grid($workplace_id)->render();
        } else {
            OAPermission::error();
        }
        $js_content = file_get_contents(public_path('js/admin/financial-index.js'));
        $html = <<<eot
			<style>
			thead td{
    			background: lightyellow;
			}
			/*.table.table-hover tr:nth-child(0) {background-color:lightyellow;}*/
			.table.table-hover.dataTable.no-footer {margin-bottom: 0px!important;}
			.table.table-hover.dataTable.no-footer.DTFC_Cloned {margin-bottom: 0px!important;}
			</style>
<script>{$js_content}</script><div class="mouse-over-highlight-table fixed-header-table">{$grid}</div>
eot;
        self::$managed_workplaces->prepend('全部', '0');
        foreach (self::$managed_workplaces as $place_id => $place_title) {
            if ($place_id > 0) {
                $link = route('financial.salary.workplace', $place_id);
                if ($workplace_id == $place_id) {
                    $tab->add($place_title, $html, true);
                } else {
                    $tab->addLink($place_title, $link, false);
                }
            } else {
                $link = route('financial.salary.index');
                if (!$workplace_id) {
                    $tab->add($place_title, $html, true);
                } else {
                    $tab->addLink($place_title, $link, false);
                }
            }
        }
        return $content
            ->header('工资列表')
            ->description(' ')
            ->body($tab->render());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid($workplace_id = null)
    {
        $timezone = UserModel::getUserTimezone();
        if (!isset($this->categories)) {
            $this->categories = FinancialCategoryModel::getAll();
        }

        $grid = new ExtendedGrid(new FinancialSalaryModel());

        $grid->id('ID')->sortable();

        $grid->user('用户名')->display(function ($val) {
            $view_url = FinancialSalaryModel::getSalaryEditUrl($this->id);
            $remark = $val['remark'] ? sprintf('(%s)', $val['remark']) : '';
            return sprintf('<a href="%s" class="td-user-name" data-name="%s">%s%s</a>', $view_url, $val['name'],
                $val['name'], $remark);
        });

        $grid->status('状态')->display(function ($val) {
            $style = FinancialSalaryModel::getTableTypeStyle($val);
            $desc = FinancialSalaryModel::getTableTypeHuman($val);
            $view_url = FinancialSalaryModel::getSalaryDetailsUrl($this->id);
            if ($style) {
                return sprintf('<a href="%s"><span class="badge badge-%s">%s</span></a>', $view_url, $style, $desc);
            } else {
                return sprintf('<a href="%s">%s</a>', $view_url, $desc);
            }
        })->sortable();

        $grid->job_level('职级')->display(function ($val) {
            return $val['name'] ?? '';
        })->sortable();

        $grid->date('所属期')->display(function ($val) {
            $dt = Carbon::parse($val);
            return sprintf('<span class="td-date" data-date="%s">%s</span>', $dt->format('Y-m'), $dt->format('Y-m'));
        })->sortable();

        $basic_columns = [
            'basic_salary' => ['title' => '固定工资'],
            'meal_allowance_lunch' => ['title' => '午餐补贴'],
            'meal_allowance_overtime' => ['title' => '加班餐补'],
            'trans_allowance' => ['title' => '加班车补'],
            'full_attendance_bonus' => ['title' => '全勤奖'],
            'quarter_dividend' => ['title' => '分红'],
            'quarter_bonus_work' => ['title' => '绩效工资'],
            'quarter_bonus_award' => ['title' => '季度奖'],
        ];
        foreach ($basic_columns as $key => $column) {
            $grid->$key($column['title'])->display(function ($val) {
                return $val > 0 ? sprintf('<span class="light">%s</span>', shortNumber($val)) : '-';
            })->sortable();
        }


        /* 其他分类 */
        //税前发放: 满N年奖, 礼金, 交通补贴, 人事奖, 其他
        self::displayCategoryRow($this->categories, $grid, FinancialCategoryModel::CATEGORY_TYPE_PLUS_BEFORE_TAX);


        /* 其他分类 */
        //税前扣除: 扣款
        self::displayCategoryRow($this->categories, $grid, FinancialCategoryModel::CATEGORY_TYPE_MINUS_BEFORE_TAX);


        $grid->taxable_income('工资合计')->display(function ($val) {
            return $val > 0 ? sprintf('<span class="blue font-weight-bold">%s</span>', shortNumber($val)) : '-';
        })->sortable();

        $grid->column('insurance_total', '社保')->display(function () {
            $total = $this->endowment_insurance_personal + $this->medical_insurance_personal + $this->unemployment_insurance_personal;
            return $total > 0 ? sprintf('<span class="red">-%s</span>', shortNumber($total)) : "-";
        });


        $grid->housing_provident_fund_insurance_personal('公积金')->display(function ($val) {
            return $val > 0 ? sprintf('<span class="red">-%s</span>', shortNumber($val)) : '-';
        })->sortable();


        $grid->taxable_salary('计税工资')->display(function ($val) {
            return $val > 0 ? sprintf('<span class="blue font-weight-bold">%s</span>', shortNumber($val)) : '-';
        })->sortable();


        $grid->tax_exemption('专项扣除')->display(function ($val) {
            return $val > 0 ? sprintf('<span class="red">-%s</span>', shortNumber($val)) : '-';
        })->sortable();

        $grid->taxes_payable('应缴个税')->display(function ($val) {
            return $val > 0 ? sprintf('<span class="red">-%s</span>', shortNumber($val)) : '-';
        })->sortable();

        //税后扣除: 大额医疗, 培训费个人部分
        self::displayCategoryRow($this->categories, $grid, FinancialCategoryModel::CATEGORY_TYPE_MINUS_AFTER_TAX);

        $grid->income_after_taxes('实发工资')->display(function ($val) {
            return sprintf('<span class="green font-weight-bold">%s</span>', shortNumber($val));
        })->sortable();

        $grid->accumulated_tax_exemption('累计扣除')->display(function ($val) {
            return $val > 0 ? sprintf('<span class="darkviolet">%s</span>', shortNumber($val)) : '-';
        })->sortable();

        $grid->accumulated_taxes_payable('累计缴税')->display(function ($val) {
            return $val > 0 ? sprintf('<span class="darkviolet">%s</span>', shortNumber($val)) : '-';
        })->sortable();

        $grid->accumulated_taxable_income('累计应纳税额')->display(function ($val) {
            return $val > 0 ? sprintf('<span class="darkviolet">%s</span>', shortNumber($val)) : '-';
        })->sortable();

        $grid->updated_at(trans('admin.updated_at'))->sortable()->display(function ($val) use ($timezone) {
            $dt = Carbon::parse($val)->setTimezone($timezone);
            return $dt->toDateTimeString();
        });

        $grid->actions(function (Grid\Displayers\Actions $actions) use ($grid) {
            $id = $actions->getKey();
            $actions->disableView();
            $actions->disableDelete();
            $actions->disableEdit();

            $edit_url = FinancialSalaryModel::getSalaryEditUrl($id);
            if ($edit_url) {
                $edit_btn = sprintf('<a href="%s"  title="编辑"><i class="fa fa-edit"></i></a>', $edit_url);
                $actions->append($edit_btn);
            }

            $view_url = FinancialSalaryModel::getSalaryDetailsUrl($id);
            if ($view_url) {
                $view_btn = sprintf('<a href="%s" title="查看"><i class="fa fa-eye"></i></a>', $view_url);
                $actions->append($view_btn);
            }

            $ajax_copy_url = route('financial.salary.copy');
            $copy_btn = sprintf('<a href="javascript:;" data-id="%s" data-route="%s" title="复制" class="btn-copy-salary"><i class="fa fa-copy"></i></a>',
                $id, $ajax_copy_url);
            $actions->append($copy_btn);
        });

        $grid->tools(function (Grid\Tools $tools) use (&$grid, $workplace_id) {
            $tools->batch(function (Grid\Tools\BatchActions $batch) {
                $batch->disableDelete();
                $is_hr = OAPermission::isHr();
                $is_financial_staff = OAPermission::isFinancialStaff();
                if ($is_hr) {
                    $batch->add('人事确认', new BatchActionFinancial(BatchActionFinancial::ACTION_HR_CONFIRM));
                }
                if ($is_financial_staff) {
                    $batch->add('财务确认', new BatchActionFinancial(BatchActionFinancial::ACTION_FINANCIAL_CONFIRM));
                    $batch->add('发放工资', new BatchActionFinancial(BatchActionFinancial::ACTION_PAY_SALARY));
                }
                if ($is_hr || $is_financial_staff) {
                    $batch->add('删除选中', new BatchActionFinancial(BatchActionFinancial::ACTION_FINANCIAL_DELETE));
                }
            });

            $quick_export_url = route('financial.salary.quick.export', $workplace_id ?? 0);
            $quick_export_html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$quick_export_url}" target="_blank" class="btn btn-sm btn-primary"  title="导出当前地（或全部）、当前月（即上月）的工资表">
        <i class="fa fa-download"></i><span class="hidden-xs">&nbsp;&nbsp;快速导出</span>
    </a>
</div>
eot;
            $tools->append($quick_export_html);
/*
            $import_url = route('financial.salary.import.index');
            $import_html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$import_url}" class="btn btn-sm btn-warning"  title="导入">
        <i class="fa fa-upload"></i><span class="hidden-xs">&nbsp;&nbsp;导入</span>
    </a>
</div>
eot;
            $tools->append($import_html);
*/

            $workplace_info = WorkplaceModel::find($workplace_id);
            $workplace_title = $workplace_info ? $workplace_info->title : '-';
/*
            $clear_url = route('financial.salary.import.clear');
            $clear_html = <<<eot
<div class="btn-group pull-right btn-group-import" id="btn-remove-imported" data-workplace-id="{$workplace_id}" data-workplace-name="{$workplace_title}" data-route="{$clear_url}" style="margin-right: 10px">
    <a  class="btn btn-sm btn-danger"  title="清除我的导入
        <i class="fa fa-remove"></i><span class="hidden-xs">&nbsp;&nbsp;清除我的导入</span>
    </a>
</div>
eot;
            $tools->append($clear_html);
*/
            $calctax_url = route('financial.salary.import.calctaxes');
            $calctax_html = <<<eot
<div class="btn-group pull-right btn-group-import" id="btn-calctax" data-workplace-id="{$workplace_id}" data-workplace-name="{$workplace_title}" data-route="{$calctax_url}" style="margin-right: 10px">
    <a  class="btn btn-sm btn-info"  title="一键计算税值">
        <i class="fa fa-edit"></i><span class="hidden-xs">&nbsp;&nbsp;一键计算税值</span>
    </a>
</div>
eot;
            $tools->append($calctax_html);

            $notify_url = route('financial.salary.import.notifyconfirm');
            $notify_html = <<<eot
<div class="btn-group pull-right btn-group-import" id="btn-notifyconfirm" " data-route="{$notify_url}" style="margin-right: 10px">
    <a  class="btn btn-sm btn-warning"  title="通知确认工资">
        <i class="fa fa-edit"></i><span class="hidden-xs">&nbsp;&nbsp;通知确认</span>
    </a>
</div>
eot;
            $tools->append($notify_html);
        });

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->in('user_id', '用户名')->multipleSelect(UserModel::getAllUsersPluck(true, true));
            $filter->in('job_level_id', '职级')->multipleSelect(UserJobLevelModel::getAllJobLevelsPluck());
            $filter->in('status', '状态')->multipleSelect(FinancialSalaryModel::$TABLE_TYPES);

            $filter->where(function ($query) {
                $dt = Carbon::parse($this->input);
                $query->where('date', '>=', $dt->toDateString());
            }, '所属期-开始')->yearMonth();
            $filter->where(function ($query) {
                $dt = Carbon::parse($this->input);
                $query->where('date', '<=', $dt->toDateString());
            }, '所属期-结束')->yearMonth();

            $filter->between('basic_salary', '基本工资区间');
            $filter->between('income_after_taxes', '税后收入区间');
        });

        // todo 需要优化
        if (!$workplace_id) {
            $user_ids = WorkplaceModel::getUserIds(self::$managed_workplaces->toArray(), true);
        } else {
            if (self::$managed_workplaces->has($workplace_id)) {
                $user_ids = WorkplaceModel::getUserIds($workplace_id, true);
            } else {
                $user_ids = [];
            }
        }

        $grid->model()->whereIn('user_id', $user_ids);
        $grid->model()->orderBy('id', 'DESC');

        $grid->perPages([20, 60, 180]);
        $grid->paginate($workplace_id > 0 ? 60 : 20);

        return $grid;
    }

    /**
     * 按 type 和 bank_transfer 显示灵活工资类目
     * $type    1：税前发放    2：税后发放    3：税前扣除    4：税后扣除
     * $bank_transfer    是否是银行转发
     * @param Collection $categories
     * @param Grid $grid
     * @param int $type
     * @param bool $bank_transfer 是否为银行转发类目
     * @param object $user 我的工资（当user不为null的时候，将检查该用户是否具备该category的查看权限）
     */
    public static function displayCategoryRow(Collection $categories, Grid &$grid, int $type)
    {
        foreach ($categories->where('type', $type)->all() as $category) {
            $grid->column($category->name)->display(function () use ($category) {
                $category_id = $category->id;
                if (!isset(self::$salary_details[$this->id])) {
                    self::$salary_details[$this->id] = $this->details;
                }
                $details = self::$salary_details[$this->id];
                $row = $details->where('category_id', $category_id)->first();
                if (isset($row)) {
                    if (in_array($category->type, [
                        FinancialCategoryModel::CATEGORY_TYPE_MINUS_BEFORE_TAX,
                        FinancialCategoryModel::CATEGORY_TYPE_MINUS_AFTER_TAX
                    ])) {
                        if ($row->category_value != 0) {
                            return sprintf('<span class="red">-%s</span>', shortNumber($row->category_value));
                        }
                        return '-';
                    } else {
                        $_val_ = $row->category_value != 0 ? $row->category_value : '-';
                        if ($category->private) {
                            return sprintf('<span class="green">%s</span>', $_val_ == 0 ? '-' : shortNumber($_val_));
                        }
                        return sprintf('<span class="light">%s</span>', $_val_ == 0 ? '-' : shortNumber($_val_));
                    }
                } else {
                    return '-';
                }
            });
        }
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
        $item = FinancialSalaryModel::find($id);
        $user = $item->user;

        $tips = '';
        if ($item->status == FinancialSalaryModel::STATUS_FINANCIAL_TRANSFERRED) {
            $tips = '<div class="alert alert-warning" role="alert">注意：工资已发放，数据将无法再修改</div>';
        }

        return $content
            ->header('编辑')
            ->description($user->name)
            ->body($tips . $this->form($id, null)->edit($id)->render() . $tips);
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($financial_id = null, $workplace_id = null)
    {
        $dt = Carbon::now();
        if (!isset($this->categories)) {
            $this->categories = FinancialCategoryModel::getAll();
        }

        $class = 'calculable-salary-item';

        $form = new Form(new FinancialSalaryModel);
        $form->setView('oa.financial-salary-form');

        $fnCreateFormFiled = function ($key, $item, $category = null) use (&$form, $financial_id, $class) {
            if ($category) {
                $category_id = $category->id;
                $category_value = $financial_id ? FinancialSalaryModel::getDetails($financial_id, $category_id,
                    'category_value') : '-';
                $data_attr = sprintf('data-operate-type="%s" data-private-transfer="%s"', $category->type,
                    $category->private);
                $type_tip = FinancialCategoryModel::getCategoryTypeHuman($category->type);
                $type_style = FinancialCategoryModel::getCategoryTypeStyle($category->type);
                $html_variable = <<<eot
        <div class="input-group">
            <span class="input-group-addon">￥</span>
            <input style="width: 130px" type="text" id="salary_detail_items[{$category_id}]" name="salary_detail_items[{$category_id}]" value="{$category_value}" class="form-control salary-item {$class}" placeholder="" {$data_attr}><span class="badge badge-light {$type_style}-background" style="margin-left: 10px; font-weight: normal; margin-top: 6px;">{$type_tip}</span>
        </div>
eot;
                $label_class = ['font-weight-normal'];
                if ($category->type == FinancialCategoryModel::CATEGORY_TYPE_MINUS_BEFORE_TAX || $category->type == FinancialCategoryModel::CATEGORY_TYPE_MINUS_AFTER_TAX) {
                    $label_class = ['font-weight-normal', 'red'];
                }
                $form->html($html_variable, $category->name)->setLabelClass($label_class);
            } else {
                if (is_array($item)) {
                    $_f = $form->decimal($key, $item['title']);

                    $label_class = isset($item['label-class']) ? array_merge(['font-weight-normal'],
                        $item['label-class']) : ['font-weight-normal'];
                    if (isset($item['data-operate'])) {
                        $_f->attribute(['data-operate-type' => $item['data-operate']]);
                    }
                    if (isset($item['element-class'])) {
                        $_f->setElementClass($item['element-class']);
                    }
                    $_f->attribute(['data-title' => $item['title']]);
                    $_f->setLabelClass($label_class);
                    if (isset($item['helper'])) {
                        $_f->help($item['helper']);
                    }
                } else {
                    $form->html($item, $key);
                }
            }
        };


        $fnCreatetTipFormFiled = function ($tip) use (&$form) {
            $form->divider();
            $form->html(sprintf('<span class="alert alert-primary">%s</span>', $tip));
        };

        if ($financial_id) {
            $form->display('user.name', '用户名')->disable();
            $form->hidden('user_id', 'user_id');
        } else {
            if ($workplace_id) {
                $form->select('user_id', '用户名')->options(WorkplaceModel::getUsers($workplace_id, true)->pluck('name',
                    'id'));
            } else {
                $form->select('user_id', '用户名')->options(UserModel::getAllUsersPluck(true, true));
            }
        }
        $form->setWidth(6, 2);

        $form->date('date', '所属期')->format('YYYY-MM')->default($dt->format('Y-m'));
        $fnCreatetTipFormFiled('基本类别');
        $basic_columns = [
            'basic_salary' => ['title' => '基本工资', 'data-operate' => 1, 'element-class' => [$class]],
            'overtime_pay' => ['title' => '加班费', 'data-operate' => 1, 'element-class' => [$class]],
            'meal_allowance_lunch' => ['title' => '午餐补贴', 'data-operate' => 1, 'element-class' => [$class]],
            'meal_allowance_overtime' => ['title' => '加班餐补', 'data-operate' => 1, 'element-class' => [$class]],
            'trans_allowance' => ['title' => '加班车补', 'data-operate' => 1, 'element-class' => [$class]],
            'full_attendance_bonus' => ['title' => '全勤奖', 'data-operate' => 1, 'element-class' => [$class]],
            'quarter_dividend' => ['title' => '分红', 'data-operate' => 1, 'element-class' => [$class]],
            'quarter_bonus_work' => ['title' => '绩效工资', 'data-operate' => 1, 'element-class' => [$class]],
            'quarter_bonus_award' => ['title' => '季度奖', 'data-operate' => 1, 'element-class' => [$class]],
        ];

        foreach ($basic_columns as $key => $item) {
            $fnCreateFormFiled($key, $item);
        }

        $fnCreatetTipFormFiled('其他类别');
        foreach ($this->categories as $category) {
            $fnCreateFormFiled(null, null, $category);
        }


        $fnCreatetTipFormFiled('保险和公积金基数');


        $insurance_columns = [
            'endowment_insurance_base' => ['title' => '养老基数'],
            'medical_insurance_base' => ['title' => '医疗基数'],
            'employment_injury_insurance_base' => ['title' => '工伤基数'],
            'unemployment_insurance_base' => ['title' => '失业基数'],
            'maternity_insurance_base' => ['title' => '生育基数'],
            'housing_provident_fund_insurance_base' => ['title' => '公积金基数'],
        ];
        foreach ($insurance_columns as $key => $item) {
            $fnCreateFormFiled($key, $item);
        }
        $ajax_load_insurance_url = route('financial.insurance.load-base');
        $html_load_insurance_base = <<<eot
<div id="load-insurance-container">
	<button type="button" class="btn btn-secondary btn-sm btn-load-insurance" data-route="{$ajax_load_insurance_url}">加载该用户的默认基数</button> <span class="calc-insurance-base-result" style="font-size: 8pt;"></span>
</div>
eot;

        $hukou_states = [
            'on' => ['value' => 1, 'text' => '是', 'color' => 'success'],
            'off' => ['value' => 0, 'text' => '否', 'color' => 'danger'],
        ];
        $form->switch('native_hukou',
            '是否当地户口')->states($hukou_states)->setLabelClass(['font-weight-normal'])->help('深圳非深户和深户养老缴费比率不一样');
        $form->html($html_load_insurance_base)->setWidth(3);


        $form->divider();
        $ajax_calc_insurance_url = route('financial.calculate.insurance');
        $html_calc_unsurance = <<<eot
<div id="calc-insurance-container">
	<button type="button" class="btn btn-info btn-calc-insurance" data-route="{$ajax_calc_insurance_url}">点击算保险和公积金</button> <span class="calc-insurance-result"></span>
</div>
eot;
        $form->html($html_calc_unsurance)->setWidth(3);
        $insurance_columns = [
            'endowment_insurance_personal' => [
                'title' => '养老保险个人',
                'label-class' => ['danger'],
                'element-class' => [$class, 'danger', 'danger-background', 'insurance-personal-item']
            ],
            'medical_insurance_personal' => [
                'title' => '医疗保险个人',
                'label-class' => ['danger'],
                'element-class' => [$class, 'danger', 'danger-background', 'insurance-personal-item']
            ],
            'unemployment_insurance_personal' => [
                'title' => '失业保险个人',
                'label-class' => ['danger'],
                'element-class' => [$class, 'danger', 'danger-background', 'insurance-personal-item']
            ],
//				'employment_injury_insurance_personal' => ['title' => '工伤保险个人', 'label-class' => ['danger'], 'element-class' => [$class, 'danger', 'danger-background', 'insurance-personal-item']],
//				'maternity_insurance_personal' => ['title' => '生育保险个人', 'label-class' => ['danger'], 'element-class' => [$class, 'danger', 'danger-background', 'insurance-personal-item']],
            'housing_provident_fund_insurance_personal' => [
                'title' => '公积金个人',
                'label-class' => ['danger'],
                'element-class' => [$class, 'danger', 'danger-background', 'insurance-personal-item']
            ],
            '个人缴费合计' => sprintf('<span id="insurance-total-personal" class="badge badge-light" style="width: 170px; text-align: right;margin-top: 5px;">0.0</span>'),
            'endowment_insurance_organization' => [
                'title' => '养老保险单位',
                'label-class' => ['warning'],
                'element-class' => [$class, 'warning', 'warning-background', 'insurance-organization-item']
            ],
            'medical_insurance_organization' => [
                'title' => '医疗保险单位',
                'label-class' => ['warning'],
                'element-class' => [$class, 'warning', 'warning-background', 'insurance-organization-item']
            ],
            'unemployment_insurance_organization' => [
                'title' => '失业保险单位',
                'label-class' => ['warning'],
                'element-class' => [$class, 'warning', 'warning-background', 'insurance-organization-item']
            ],
            'employment_injury_insurance_organization' => [
                'title' => '工伤保险单位',
                'label-class' => ['warning'],
                'element-class' => [$class, 'warning', 'warning-background', 'insurance-organization-item']
            ],
            'maternity_insurance_organization' => [
                'title' => '生育保险单位',
                'label-class' => ['warning'],
                'element-class' => [$class, 'warning', 'warning-background', 'insurance-organization-item']
            ],
            'housing_provident_fund_insurance_organization' => [
                'title' => '公积金单位',
                'label-class' => ['warning'],
                'element-class' => [$class, 'warning', 'warning-background', 'insurance-organization-item']
            ],
//				'supplementary_medical_insurance_organization' => ['title' => '补充医疗保险单位',  'label-class' => ['warning'], 'element-class' => [$class, 'warning', 'warning-background', 'insurance-organization-item']],
            '单位缴费合计' => sprintf('<span id="insurance-total-organization" class="badge badge-light" style="width: 170px; text-align: right;margin-top: 5px;">0.0</span>'),
        ];
        foreach ($insurance_columns as $key => $item) {
            $fnCreateFormFiled($key, $item);
        }

        $fnCreatetTipFormFiled('缴税相关');
        $fax_columns = [
            'tax_exemption' => ['title' => '专项扣除', 'element-class' => [$class]],
            'pretax_income' => [
                'title' => '税前收入(自动计算)',
                'label-class' => ['success'],
                'element-class' => ['success', 'success-background']
            ],
            'taxable_income' => [
                'title' => '应纳税金额(自动计算)',
                'label-class' => ['success'],
                'element-class' => ['success', 'success-background']
            ],
            '详情' => sprintf('<span id="income-details" class="badge badge-light" style="font-weight: normal; text-align: right;margin-top: 5px;">0.0</span>'),
        ];
        foreach ($fax_columns as $key => $item) {
            $fnCreateFormFiled($key, $item);
        }


        $form->divider();
        $ajax_calc_tax_url = route('financial.calculate.tax');
        $html_calc_tax = <<<eot
<div id="calc-tax-container">
	<button type="button" id="btn-calc-tax" class="btn btn-info btn-calc-tax" data-route="{$ajax_calc_tax_url}">点击算个人所得税</button> <span class="calc-tax-result"></span>
</div>
eot;
        $form->html($html_calc_tax)->setWidth(3);


        $insurance_columns = [
            'taxable_salary' => [
                'title' => '计税工资',
                'helper' => "应纳税金额-保险公积金",
                'label-class' => ['success'],
                'element-class' => ['success', 'success-background']
            ],
            'net_taxable_income' => [
                'title' => '净纳税金额',
                'helper' => "应纳税金额-专项附加扣除-保险公积金-个税起征点",
                'label-class' => ['success'],
                'element-class' => ['success', 'success-background']
            ],
            'taxes_payable' => [
                'title' => '应缴个税',
                'helper' => "净纳税额*预扣率",
                'label-class' => ['success'],
                'element-class' => ['success', 'success-background']
            ],
            'insurance_personal' => [
                'title' => '应缴五险一金',
                'label-class' => ['success'],
                'element-class' => ['success', 'success-background']
            ],
            'income_after_taxes' => [
                'title' => '实发工资',
                'helper' => "税前收入-应缴个税-保险公积金+转账工资",
                'label-class' => ['success'],
                'element-class' => ['success', 'success-background']
            ],
            'bank_transfer_salary' => [
                'title' => '银行代发',
                'helper' => "实发工资-转账工资",
                'label-class' => ['success'],
                'element-class' => ['success', 'success-background']
            ],

//				'accumulated_pretax_income' => ['title' => '累计税前收入', 'label-class' => ['primary'], 'element-class' => ['primary', 'primary-background']],
            'accumulated_taxable_income' => [
                'title' => '累计应纳税额',
                'label-class' => ['primary'],
                'element-class' => ['primary', 'primary-background']
            ],
            'accumulated_taxes_payable' => [
                'title' => '累计应缴个税',
                'label-class' => ['primary'],
                'element-class' => ['primary', 'primary-background']
            ],
//				'accumulated_insurance_personal' => ['title' => '累计保险公积金扣除', 'label-class' => ['primary'], 'element-class' => ['primary', 'primary-background']],
            'accumulated_tax_exemption' => [
                'title' => '累计扣除',
                'label-class' => ['primary'],
                'element-class' => ['primary', 'primary-background']
            ],
        ];
        foreach ($insurance_columns as $key => $item) {
            $fnCreateFormFiled($key, $item);
        }

        $status = $this::getFinancialStatus();
        $styles = [];
        foreach ($status as $key => $val) {
            $styles[$key] = sprintf('<span  data-status="%s" class="badge badge-%s">%s</span>', $key,
                FinancialSalaryModel::getTableTypeStyle($key), FinancialSalaryModel::getTableTypeHuman($key));
        }
        $form->radio('status', '状态')->options($styles)->default(FinancialSalaryModel::STATUS_HR_CHECKING);
        $form->textarea('mark', '备注')->rows(2);

        $form->html('&nbsp;');
        $form->display('created_at', trans('admin.created_at'));
        $form->display('updated_at', trans('admin.updated_at'));

        if ($financial_id) {
            $form->html('&nbsp;');
            $form->divider();
            $form->textarea('reply', '回复信息')->rows(2);
            $log_html = FinancialLogModel::getLogHtml($financial_id);
            if ($log_html) {
                $form->html("&nbsp;");
                $form->html($log_html, '日志记录');
            }
        }


        $form->disableEditingCheck();
        $form->disableViewCheck();
        $form->tools(function (Form\Tools $tools) use ($financial_id) {
//				$tools->disableList();
            $tools->disableDelete();
            $tools->disableView();

            if ($financial_id) {
                $view_url = route('financial.personal.detail', $financial_id);
                $view_button = <<<eot
<a href="{$view_url}" class="btn btn-sm btn-primary" title="查看">
        <i class="fa fa-eye"></i><span class="hidden-xs"> 查看</span>
</a>&nbsp;
eot;
                $tools->append($view_button);
            }
        });

        $form->footer(function (Form\Footer $footer) use ($financial_id) {
            $footer->disableReset();
            if ($financial_id && FinancialSalaryModel::find($financial_id)->status == FinancialSalaryModel::STATUS_FINANCIAL_TRANSFERRED) {
                $footer->disableSubmit();
            }
        });

        $js_content = file_get_contents(public_path('js/admin/financial-form.js'));
        $html = <<<eot
<style>
	.form-group{
		margin-bottom: 1px;
	}
	.box-body{
		padding: 5px;
	}
	.swal2-popup .swal2-title{
	    font-size: 1.475em;
	    line-height: 1.5em;
	    font-weight: normal;
	    text-align: left;
	}
</style>
<script data-exec-on-popstate>
	{$js_content}
</script>
eot;

        $form->html($html, '');

        $form->saving(function (Form $form) {
            $form->ignore('salary_detail_items');
            $form->ignore('reply');
            $form->date = Carbon::parse("{$form->date}-01")->toDateString();
            if ($form->status == null || $form->status == FinancialSalaryModel::STATUS_COPIED) {
                $form->status = FinancialSalaryModel::STATUS_HR_CHECKING;
            }
        });

        $form->saved(function (Form $form) {
            $financial_id = $form->model()->id;
            //更新details项目
            $salary_items = $form->salary_detail_items;
            FinancialDetailModel::saveDetails($financial_id, $salary_items);

            //审计
            $array = $form->model()->toArray();
            unset($array['id']);
            $audit = new FinancialAuditSalaryModel($array);
            $cur_user = UserModel::getUser();
            $audit->financial_id = $financial_id;
            $audit->updated_by_user_id = $cur_user->id;
            $audit->updated_by_user_name = $cur_user->name;
            $audit->detail_items = json_encode($salary_items);
            $audit->save();

            //日志记录

            if ($form->reply) {
                $log_status = FinancialSalaryModel::getTableTypeHuman($form->status);
                $log_content = "[ $log_status ] : " . $form->reply;
                FinancialLogModel::insertLog($financial_id, $log_content, FinancialLogModel::INTERACTION_TYPE_FINANCIAL,
                    FinancialLogModel::CONTENT_TYPE_MARK);
            }

        });
        return $form;
    }

    /**
     * 复制工资条
     * 比如将harry 2019年1月 的工资条 复制一份成 2019年2月，减少重复填写
     * @param Request $request
     * @return array
     */
    public function copySalary(Request $request)
    {
        $from_id = intval($request->post('from_id'));
        $row = FinancialSalaryModel::find($from_id);
        $copied = $row->replicate();
        $copied->status = FinancialSalaryModel::STATUS_COPIED;
        //获取该用户的最新年月记录，然后加1个月
        $latest_record = FinancialSalaryModel::getLatestRecord($copied->user_id);
        $dt = Carbon::parse($latest_record->date)->addMonth(1);
        $copied->date = $dt->toDateString();
        $result = $copied->save();
        return [
            'status' => (int)$result
        ];
    }

    /**
     * 批量操作
     * 1: 发放工资
     * 2: 财务确认
     * @param Request $request
     * @return array
     */
    public function batchOperate(Request $request)
    {
        $return['status'] = 0;

        $is_financial_staff = OAPermission::isFinancialStaff();
        $is_hr = OAPermission::isHr();

        if (!$is_financial_staff && !$is_hr) {
            $return['message'] = '未授权访问';
            return $return;
        }

        $action = intval($request->post('action'));
        $ids = $request->post('ids');
        if (!is_array($ids) || sizeof($ids) == 0) {
            $return['message'] = '提交的数据不合法';
            return $return;
        }

        $result = 0;
        switch ($action) {
            case BatchActionFinancial::ACTION_HR_CONFIRM:
                if ($is_hr) {
                    $result = FinancialSalaryModel::whereIn('id', $ids)
                        ->where('status', '<', FinancialSalaryModel::STATUS_HR_CONFIRMED)
                        ->update(['status' => FinancialSalaryModel::STATUS_HR_CONFIRMED]);
                }
                break;
            case BatchActionFinancial::ACTION_FINANCIAL_CONFIRM:
                if ($is_financial_staff) {
                    $result = FinancialSalaryModel::whereIn('id', $ids)
                        ->whereIn('status', [FinancialSalaryModel::STATUS_HR_CONFIRMED])
                        ->update(['status' => FinancialSalaryModel::STATUS_FINANCIAL_CONFIRMED]);
                }
                break;
            case BatchActionFinancial::ACTION_PAY_SALARY:    //批量发放工资
                if ($is_financial_staff) {
                    $result = FinancialSalaryModel::whereIn('id', $ids)
                        ->whereIn('status', [
                            FinancialSalaryModel::STATUS_FINANCIAL_CONFIRMED,
                            FinancialSalaryModel::STATUS_PERSONAL_CONFIRMED
                        ])
                        ->update(['status' => FinancialSalaryModel::STATUS_FINANCIAL_TRANSFERRED]);
                }
                break;
            case BatchActionFinancial::ACTION_FINANCIAL_DELETE:
                if ($is_hr) {
                    $result = FinancialSalaryModel::deleteByIds($ids, FinancialSalaryModel::STATUS_HR_CONFIRMED);
                } elseif ($is_financial_staff) {
                    $result = FinancialSalaryModel::deleteByIds($ids, FinancialSalaryModel::STATUS_FINANCIAL_CONFIRMED);
                }
                break;
            default:
                break;
        }

        if ($result) {
            return ['status' => $result, 'message' => '操作成功',];
        } else {
            return ['status' => $result, 'message' => '操作失败',];
        }
    }

    /**
     * 加载某个地区默认的社保和公积金缴费基数
     * @param Request $request
     * @return array
     */
    public function loadInsuranceBase(Request $request)
    {

        $user_id = intval($request->post('user_id'));
        $user = UserModel::getUser($user_id);
        $workplace = $user->workplace_info->name;

        $insurance_info = FinancialInsuranceBaseModel::getByWorkplace($workplace);

        $result = [
            'user' => [
                'id' => $user_id,
                'name' => $user->name,
                'workplace' => $user->workplace_info->title,
            ],
            'info' => $insurance_info,
        ];
        return $result;
    }

    /**
     * 计算个税
     * @param Request $request
     * @return array
     */
    public function calculateTax(Request $request)
    {
        $user_id = intval($request->post('user_id'));
        $date = $request->post('date');
        $pretax_income = doubleval($request->post('pretax_income'));                          //税前收入
        $taxable_income = doubleval($request->post('taxable_income'));                        //应纳税金额
        $tax_exemption = doubleval($request->post('tax_exemption'));                          //专项扣除
        $total_insurance_personal = doubleval($request->post('total_insurance_personal'));    //五险一金个人缴费合计

        $details = $request->post('details');
        $tax_info = TaxCalcHelper::getPersonalIncomeTax($user_id, $date, $pretax_income, $taxable_income,
            $total_insurance_personal, $tax_exemption);

        $user = UserModel::getUser($user_id);

        if ($details) {
            $tax = $tax_info;
        } else {
            $tax = $tax_info['items'][$date];
        }
        $result = [
            'user' => [
                'id' => $user_id,
                'name' => $user->name,
                'workplace' => $user->workplace_info->title,
            ],
            'info' => $tax,
        ];
        return $result;
    }

    /**
     * 计算社保和公积金
     * @param Request $request
     * @return array
     */
    public function calculateInsurance(Request $request)
    {
        $user_id = intval($request->post('user_id'));
        $date = $request->post('date');
        $native_hukou = $request->post('native_hukou') == 'on' ? true : false;

        //五险一金相关
        $endowment_insurance_base = doubleval($request->post('endowment_insurance_base'));
        $medical_insurance_base = doubleval($request->post('medical_insurance_base'));
        $employment_injury_insurance_base = doubleval($request->post('employment_injury_insurance_base'));
        $unemployment_insurance_base = doubleval($request->post('unemployment_insurance_base'));
        $maternity_insurance_base = doubleval($request->post('maternity_insurance_base'));
        $housing_provident_fund_insurance_base = doubleval($request->post('housing_provident_fund_insurance_base'));
        $insurance_info = FinancialSalaryModel::getInsuranceInfo($user_id, $date, $native_hukou,
            $endowment_insurance_base, $medical_insurance_base, $employment_injury_insurance_base,
            $unemployment_insurance_base, $maternity_insurance_base, $housing_provident_fund_insurance_base);

        $user = UserModel::getUser($user_id);
        $result = [
            'user' => [
                'id' => $user_id,
                'name' => $user->name,
                'workplace' => $user->workplace_info->title,
            ],
            'info' => $insurance_info,
        ];
        return $result;
    }

    public function quickExport(Request $request, $workplace_id)
    {
        $date = Carbon::now()->subMonth()->startOfMonth()->toDateString();
        $exporter = new FinancialSalaryImportExportModel();
        $exporter->export2($workplace_id, $date);
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(FinancialSalaryModel::findOrFail($id));
        $show->id('ID');
        $show->created_at('Created at');
        $show->updated_at('Updated at');

        return $show;
    }

    private function getFinancialStatus()
    {
        $statuses = [];
        if (OAPermission::isHr()) {
            $statuses[] = FinancialSalaryModel::STATUS_HR_CONFIRMED;
        }
        if (OAPermission::isFinancialStaff()) {
            $statuses[] = FinancialSalaryModel::STATUS_FINANCIAL_CONFIRMED;
            $statuses[] = FinancialSalaryModel::STATUS_FINANCIAL_TRANSFERRED;
        }

        $ret = [];
        foreach ($statuses as $status) {
            $ret[$status] = FinancialSalaryModel::getTableTypeHuman($status);
        }

        return $ret;
    }


}
