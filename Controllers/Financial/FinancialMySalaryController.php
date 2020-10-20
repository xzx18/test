<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Auth\OAPermission;
use App\Models\FinancialCategoryModel;
use App\Models\FinancialLogModel;
use App\Models\FinancialSalaryModel;
use App\Models\UserModel;
use App\Models\WorkplaceModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Box;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FinancialMySalaryController extends Controller
{
    use HasResourceActions;

    private static $salary_details = null;
    private $categories = null;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        $grid = $this->grid()->render();
        $js_content = file_get_contents(public_path('js/admin/financial-index.js'));
        $html = <<<eot
<script>
{$js_content}
</script>
<div class="mouse-over-highlight-table">
{$grid}
</div>
eot;
        return $content
            ->header('我的工资')
            ->description(' ')
            ->body($html);
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid($id = null, $summary_info = false, $table_title = '')
    {
        if ($id) {
            $user_ids = [FinancialSalaryModel::find($id)->user_id];
        } else {
            $current_user = UserModel::getUser();
            $user_ids = array_merge([$current_user->id],
                $current_user->manage_financial_staffs->pluck('id')->toArray());
        }

        if (!isset($this->categories)) {
            $this->categories = FinancialCategoryModel::getAllNoneZeroEextendCategoriesWithUser($user_ids);
        }

        $grid = new Grid(new FinancialSalaryModel());
        if (!$summary_info) {
            $grid->id('ID')->display(function ($val) {
                $view_url = route('financial.personal.detail', $this->id);
                return sprintf('<a href="%s">%s</a>', $view_url, $val, $val);
            });
            $grid->status('状态')->display(function ($val) {
                $style = FinancialSalaryModel::getTableTypeStyle($val);
                $desc = FinancialSalaryModel::getTableTypeHuman($val);
                if ($style) {
                    return sprintf('<span class="badge badge-%s">%s</span>', $style, $desc);
                } else {
                    return $desc;
                }
            });
            if (count($user_ids) > 1) {
                $grid->user_id('姓名')->display(function ($val) {
                    return UserModel::getUser($val)->truename;
                });
            }
            $grid->date('所属期')->display(function ($val) {
                $dt = Carbon::parse($val);
                return sprintf('<span class="td-date" data-date="%s">%s</span>', $dt->format('Y-m'), $dt->format('Y-m'));
            });
        }
        $basic_columns = [
            'basic_salary' => ['title' => '固定工资'],
            'overtime_pay' => ['title' => '加班工资'],
            'meal_allowance_lunch' => ['title' => '午餐补贴'],
            'meal_allowance_overtime' => ['title' => '加班餐补'],
            'trans_allowance' => ['title' => '加班车补'],
            'full_attendance_bonus' => ['title' => '全勤奖'],
            'quarter_dividend' => ['title' => '分红'],
            'quarter_bonus_work' => ['title' => '绩效工资'],
            'quarter_bonus_award' => ['title' => '季度奖'],
        ];
        $basic_columns = FinancialCategoryModel::getAllNoneZeroBasicCategoriesWithUser($basic_columns, $user_ids);
        /*
         SELECT sum(overtime_pay) as overtime_pay FROM `oa_financial_salary` WHERE user_id=16
         * */


        foreach ($basic_columns as $key => $column) {
            $grid->$key($column['title'])->display(function ($val) {
                return $val > 0 ? sprintf('<span class="light">%s</span>', $val) : '';
            });
        }
        /* 其他分类 */
        //税前发放: 满N年奖, 礼金, 交通补贴, 人事奖, 其他
        self::displayCategoryRow($this->categories, $grid, FinancialCategoryModel::CATEGORY_TYPE_PLUS_BEFORE_TAX);


        /* 其他分类 */
        //税前扣除: 扣款
        self::displayCategoryRow($this->categories, $grid, FinancialCategoryModel::CATEGORY_TYPE_MINUS_BEFORE_TAX);

        if (!$summary_info) {
            $grid->taxable_income('工资合计')->display(function ($val) {
                return $val > 0 ? sprintf('<span class="blue font-weight-bold">%s</span>', $val) : '';
            });
        }
        $grid->column('insurance_total', '社保')->display(function () {
            $total = $this->endowment_insurance_personal + $this->medical_insurance_personal + $this->unemployment_insurance_personal;
            return sprintf('<span class="red">-%s</span>', $total);
        });


        $grid->housing_provident_fund_insurance_personal('公积金')->display(function ($val) {
            return $val > 0 ? sprintf('<span class="red">-%s</span>', $val) : '';
        });

        if (!$summary_info) {
            $grid->taxable_salary('计税工资')->display(function ($val) {
                return $val > 0 ? sprintf('<span class="blue font-weight-bold">%s</span>', $val) : '';
            });
        }

        $grid->taxes_payable('应缴个税')->display(function ($val) {
            return $val > 0 ? sprintf('<span class="red">-%s</span>', $val) : '';
        });

        //税后扣除: 大额医疗, 培训费个人部分
        self::displayCategoryRow($this->categories, $grid, FinancialCategoryModel::CATEGORY_TYPE_MINUS_AFTER_TAX);
        //税后发放: 误餐补助, 误车补助
        self::displayCategoryRow($this->categories, $grid, FinancialCategoryModel::CATEGORY_TYPE_PLUS_AFTER_TAX);
        if (!$summary_info) {
            $grid->income_after_taxes('实发工资')->display(function ($val) {
                return sprintf('<span class="green font-weight-bold">%s</span>', $val);
            });

            $grid->updated_at(trans('admin.updated_at'));
            $grid->actions(function (Grid\Displayers\Actions $actions) use (&$fnGetEditUrl) {
                $id = $actions->getKey();
                $actions->disableDelete();
                $actions->disableEdit();
                $edit_url = $fnGetEditUrl($id);
                $edit_btn = sprintf('<a href="%s" target="_blank" title="编辑"><i class="fa fa-edit"></i></a>', $edit_url);
                $actions->append($edit_btn);

                $copy_btn = sprintf('<a href="javascript:;" data-id="%s" title="复制" class="btn-copy-salary"><i class="fa fa-copy"></i></a>', $id);
                $actions->append($copy_btn);
            });
        }

        if ($summary_info) {
            if ($table_title) {
                $grid->setTitle($table_title);
            }
            $grid->disableFilter();
            $grid->disablePagination();
            $grid->disableActions();
        }

        $grid->disableRowSelector();
        $grid->disableExport();
        $grid->disableCreateButton();

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableView();
            $actions->disableEdit();
            $actions->disableDelete();
            $view_url = route('financial.personal.detail', $actions->row->id);
            $actions->append(sprintf('<a href="%s" title="在工资试算中查看详情">查看</a>', $view_url));
        });

        $grid->tools(function (Grid\Tools $tools) {
            $tools->disableRefreshButton();
        });
        if ($id) {
            //财务人员可以看到所有
            $grid->model()->where('id', $id);
        } else {
            //自己只能看到自己的
            $grid->model()->whereIn('user_id', $user_ids);
            $grid->model()->whereIn('status', [
                FinancialSalaryModel::STATUS_PERSONAL_DISPUTED,
                FinancialSalaryModel::STATUS_PERSONAL_CONFIRMED,
                FinancialSalaryModel::STATUS_FINANCIAL_CONFIRMED,
                FinancialSalaryModel::STATUS_FINANCIAL_TRANSFERRED
            ]);
        }


        $grid->model()->orderBy('id', 'DESC');

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->in('status', '状态')->multipleSelect(FinancialSalaryModel::getPersonalStatus());
            $filter->equal('date', '所属期');

        });
        return $grid;
    }

    public static function displayCategoryRow(Collection $categories, Grid &$grid, int $type)
    {
        FinancialSalaryController::displayCategoryRow($categories, $grid, $type);
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
        $current_user = UserModel::getUser();
        $record = FinancialSalaryModel::find($id);

        if (!$record) {
            return $content
                ->header('无记录')
                ->description('您访问的记录不存在或者已删除')
                ->body('');
        }

        if (!OAPermission::isAdministrator()) {
            if (!$record || $record->user_id != $current_user->id) {
                $managed_workplaces = $current_user->getManagedFinancialWorkplaces(true);
                $user_ids = WorkplaceModel::getUserIds($managed_workplaces->toArray(), true);
                $user_ids = array_merge($user_ids, $current_user->manage_financial_staffs->pluck('id')->toArray());
                if (!in_array($record->user_id, $user_ids)) {
                    return $content
                        ->header('未授权访问')
                        ->description('您的该次请求将会被记录')
                        ->body(OAPermission::ERROR_HTML);
                }
            }
        }


        $record_status = sprintf('<span id="salary-status" class="badge badge-%s">%s</span>',
            FinancialSalaryModel::getTableTypeStyle($record->status),
            FinancialSalaryModel::getTableTypeHuman($record->status));
        $user = UserModel::getUser();
        $date = Carbon::parse($record->date)->format('Y年m月');
        $userame = $record->user->name;
        return $content
            ->header("$userame $date 工资条")
            ->description($record_status)
//				->row(function (Row $row) use ($id, $record) {
//					$name = $record->user->name;
//					$date = Carbon::parse($record->date)->format('Y年m月');
//
//					$infoBox = new InfoBox("更新时间：{$record->updated_at}", 'user', 'aqua', '', "$name <span style='font-size: 9pt;'>$date</span>");
//					$infoBox->view('oa.info-box-without-link');
//					$row->column(3, $infoBox);
//
//					$infoBox = new InfoBox('实发工资', 'money', 'green', '', $record->income_after_taxes ?? 0);
//					$infoBox->view('oa.info-box-without-link');
//					$row->column(3, $infoBox);
//				})
            ->row(function (Row $row) use ($id, $record) {
//					$date = Carbon::parse($record->date)->format('Y年m月');

                $income_after_taxes = $record->income_after_taxes ?? 0;
                $update_time = $record->updated_at;
                $summary_html = <<<eot
<ul>
<li>实发工资：{$income_after_taxes}</li>
<li>更新时间：{$update_time}</li>
</ul>
<script>
	$(function () {
			$('.table-responsive').prev().remove();
	});
</script>
<style>
.grid-refresh{display: none;}
</style>
eot;

                $box = new Box("汇总", $summary_html);
                $box->style('primary');
                $grid = $this->grid($id, true, "详情");
                $row->column(12, $box->render() . $grid->render());

                $mark_html = '<ul>';
                if (isset($record->mark) && $record->mark) {
                    $mark_html .= "<li class='red'>{$record->mark}</li>";
                }
                $mark_html .= '<li><b>培训费</b>：公司组织的各种培训，个人需要承担的部分费用</li>';
                $mark_html .= '<li><b>礼金</b>：含生日礼金、结婚礼金、生育礼金等等</li>';
                $mark_html .= '<li><b>满N年奖</b>：满3年奖、满5年奖等等</li>';
                $mark_html .= '</ul>';
                $mark_box = new Box('备注', $mark_html);
                $row->column(12, $mark_box);
            })
            ->row(function (Row $row) use ($id, $record, $user) {
                $ajax_audit_url = route('financial.personal.audit');
                $operate_html = null;
                if ($record->user_id == $user->id && $record->status == FinancialSalaryModel::STATUS_FINANCIAL_CONFIRMED) {
                    $operate_title = '个人操作';
                    $operate_html = <<<eot
<div class="operate-button-container" style="text-align: center">
	<button data-action="confirm" class="btn btn-success btn-lg btn-salary-confirm" style="margin-right: 20px;" data-confirm-div="salary-confirm-div">对此工资确认无误</button>
	<button data-action="dispute" class="btn btn-danger btn-lg btn-salary-dispute" data-confirm-div="salary-dispute-div">对此工资有争议</button>
</div>
eot;
                }
                if ($record->status == FinancialSalaryModel::STATUS_PERSONAL_CONFIRMED && OAPermission::isFinancialStaff()) {
                    $operate_title = '财务操作';
                    $operate_html = <<<eot
<div class="operate-button-container" style="text-align: center">
	<button data-action="transfer" class="btn btn-success btn-lg btn-salary-transfer">对该工资进行发放</button>
</div>
eot;
                }
                if ($operate_html) {
                    $html = <<<eot
{$operate_html}
<script>
	$(function () {
	    $('.btn-salary-confirm, .btn-salary-dispute, .btn-salary-transfer').click(function(e) {
	        var txt=$(this).text();
	        var action=$(this).data('action');
	     	var confirm_text="我已确认该工资金额无误，将不再针对该月工资找财务进行争议处理。";
	        var html_tip='';
	        switch (action) {
	          	case "dispute":
	          	    html_tip="<b class='red'>必须详细说明争议事项，以降低多次沟通成本</b>";
					break;
				case "confirm":
				    html_tip="确认前请再次自行核对，如果确认无误，请在输入框中完整输入以下文字(含标点符号)<br><b class='red'>" +confirm_text+ "</b>";
					break;
				case "transfer":
					break;
	        }
	        swal({
				title: "[ " + txt+ " ] 确认操作?",
				type: "warning",
				html:"<p style='margin:10px 0px;'>" + html_tip + "</p>",
                input: 'textarea',
				showCancelButton: true,
				confirmButtonColor: "#DD6B55",
				confirmButtonText: "确认",
				showLoaderOnConfirm: true,
				cancelButtonText: "取消",
				inputValidator: (text) => {
                    if (action == 'dispute') {
                        if (!text) {
                            return '必须详细说明争议事项'
                        }
                    } else if (action == 'confirm') {
                        if (text != confirm_text) {
                            return '请在输入框中完整输入确认文字（含标点符号）';
                        }
                    }
				},
				preConfirm: function(text) {
					return new Promise(function(resolve) {
						$.ajax({
							method: 'post',
							url: '{$ajax_audit_url}',
							data: {
								salary_id: {$id},
								action:action,
								content:text,
								_token:LA.token
							},success: function (data) {
								resolve(data);
							}
						})
					});
				}
				}).then(function(result) {
                    var data = result.value;
                    if (typeof data === 'object') {
                         $.pjax.reload('#pjax-container');
                        if (data.status) {
                            // $('#salary-status').html(data.message);
                            // $(".operate-button-container").closest(".box").remove();
                            swal(data.message, '', 'success');
                        } else {
                            swal(data.message, '', 'error');
                        }
                    }
			    });
	    });
	});
</script>
eot;
                    $box = new Box($operate_title, $html);
                    $box->style('info');
                    $row->column(12, $box);
                }
            })->row(function (Row $row) use ($id, $record, $user) {
                $log_html = FinancialLogModel::getLogHtml($record->id);
                if ($log_html) {
                    $box = new Box("日志记录", $log_html);
                    $box->style('info');
                    $row->column(12, $box);
                }
            })->row(function (Row $row) use ($id, $record, $user) {
                $detail_title = '试算详情';
                $year_month = substr($record->date, 0, 7);
                $route = route('financial.personal.calculation') . '/' . $record->user_id . '/' . $year_month;

                $html = <<<EOT
<div id="calc_details" data-route='{$route}'></div>
<div class="operate-button-container" style="text-align: center">
	<button class="btn btn-primary btn-lg btn-calc-details" title="查看/刷新试算详情">查看试算详情</button>
</div>
<script>
    $(".btn-calc-details").click(function() {
      fetchCalcDetails();
    });

    function fetchCalcDetails() {
        var box= $("#calc_details");
        NProgress.configure({ parent: '.content .box-header' });
        NProgress.start();
        var route=box.data("route");
        $.get(route,function(response ) {
            box.html(response);
            NProgress.done();
        })
    }
</script>
EOT;

                $box = new Box($detail_title, $html);
                $box->style('info');
                $row->column(12, $box);
            });
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
        $form = new Form(new YourModel);

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

    public function auditSalary(Request $request)
    {
        $result = [
            'status' => 0,
            'message' => '非法操作',
        ];
        $salary_id = intval($request->post('salary_id'));
        $action = $request->post('action');
        $content = $request->post('content');

        $current_user = UserModel::getUser();
        $record = FinancialSalaryModel::find($salary_id);
        if ($record && $record->user_id == $current_user->id) {

            //确保自己只能修改自己的
            switch ($action) {
                case 'confirm':
                    FinancialLogModel::insertLog($salary_id, $content, FinancialLogModel::INTERACTION_TYPE_USER, FinancialLogModel::CONTENT_TYPE_CONFIRM);
                    FinancialSalaryModel::updatePersonalStatus($record, FinancialSalaryModel::STATUS_PERSONAL_CONFIRMED);
                    $result = [
                        'status' => 1,
                        'message' => '个人确认成功，等待财务发放工资',
                    ];
                    break;
                case 'dispute':
                    FinancialLogModel::insertLog($salary_id, $content, FinancialLogModel::INTERACTION_TYPE_USER, FinancialLogModel::CONTENT_TYPE_DISPUTE);
                    FinancialSalaryModel::updatePersonalStatus($record, FinancialSalaryModel::STATUS_PERSONAL_DISPUTED);
                    $result = [
                        'status' => 1,
                        'message' => '争议提交成功，等待财务再次审核',
                    ];
                    break;
                case 'transfer':
                    FinancialSalaryModel::updatePersonalStatus($record, FinancialSalaryModel::STATUS_FINANCIAL_TRANSFERRED);
                    $result = [
                        'status' => 1,
                        'message' => '工资已发放',
                    ];
                    break;
            }
        }
        return $result;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(YourModel::findOrFail($id));

        $show->id('ID');
        $show->created_at('Created at');
        $show->updated_at('Updated at');

        return $show;
    }
}
