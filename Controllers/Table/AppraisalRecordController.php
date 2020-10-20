<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\ExtendedBox;
use App\Admin\Extensions\Tools\BatchActionAppraisal;
use App\Http\Controllers\Controller;
use App\Models\AppraisalAssignmentAuditor;
use App\Models\AppraisalAssignmentCarbonCopy;
use App\Models\AppraisalAssignmentExecutor;
use App\Models\AppraisalAssignmentReviewer;
use App\Models\AppraisalTableAssignment;
use App\Models\AppraisalTablesModel;
use App\Models\AppraisalTablValues;
use App\Models\Auth\OAPermission;
use App\Models\CalendarModel;
use App\Models\UserModel;
use App\Models\AppraisalTableTypeGeneral;
use App\Models\AppraisalTableTypeDeveloper;
use App\Models\AppraisalResultModel;
use App\Models\AppraisalTableTypeCorporateCulture;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Form;
use Encore\Admin\Widgets\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Encore\Admin\Admin;

class AppraisalRecordController extends Controller
{
    use HasResourceActions;

    /************************************************************/
    /** 面谈 */
    public static function interviewEvaluateEditable($evaluate_status)
    {
        return in_array($evaluate_status, [AppraisalTablesModel::TABLE_STATUS_APPROVED]);
    }

    public function index(Content $content)
    {
        $current_user = UserModel::getUser();
        /** @var Collection $my_review_items 由我审核的 */
        $my_review_items = AppraisalAssignmentReviewer::getAssignment($current_user->id);
        /** @var Collection $my_audit_items 由我审计的 */
        $my_audit_items = AppraisalAssignmentAuditor::getAssignment($current_user->id);

        //如果是管理员，看到所有的表格
        /** @var Collection $my_copy_items 抄送给我的 */
        if (OAPermission::isAdministrator() || OAPermission::isHr()) {
            $my_copy_items = AppraisalAssignmentExecutor::all(['assignment_id']);
        } else {
            $my_copy_items = AppraisalAssignmentCarbonCopy::getAssignment($current_user->id);

        }

        $tab = new Tab();
        if (self::isSelfEvaluate()) {//个人填表
            $tab->add('由我填写', $this->grid()->render(), true);
            if ($my_review_items && $my_review_items->isNotEmpty()) {
                $tab->addLink('由我审核', self::getReviewEvaluateUrl(), false);
            }
            if ($my_audit_items && $my_audit_items->isNotEmpty()) {
                $tab->addLink('由我审阅', self::getAuditEvaluateUrl(), false);
            }
            if ($my_copy_items && $my_copy_items->isNotEmpty()) {
                $tab->addLink('抄送给我', self::getCopyEvaluateUrl(), false);
            }
        } elseif (self::isReviewEvaluate()) {  //审核者审核绩效表
            $tab->addLink('由我填写', self::getSelfEvaluateUrl(), false);
            if ($my_review_items && $my_review_items->isNotEmpty()) {
                $tab->add('由我审核', $this->grid($my_review_items)->render(), true);
            }
            if ($my_audit_items && $my_audit_items->isNotEmpty()) {
                $tab->addLink('由我审阅', self::getAuditEvaluateUrl(), false);
            }
            if ($my_copy_items && $my_copy_items->isNotEmpty()) {
                $tab->addLink('抄送给我', self::getCopyEvaluateUrl(), false);
            }
        } elseif (self::isAuditEvaluate()) { //监察者审核
            $tab->addLink('由我填写', self::getSelfEvaluateUrl(), false);
            if ($my_review_items && $my_review_items->isNotEmpty()) {
                $tab->addLink('由我审核', self::getReviewEvaluateUrl(), false);
            }
            if ($my_audit_items && $my_audit_items->isNotEmpty()) {
                $tab->add('由我审阅', $this->grid($my_audit_items)->render(), true);
            }
            if ($my_copy_items && $my_copy_items->isNotEmpty()) {
                $tab->addLink('抄送给我', self::getCopyEvaluateUrl(), false);
            }
        } elseif (self::isCopyEvaluate()) { // 绩效表
            $tab->addLink('由我填写', self::getSelfEvaluateUrl(), false);
            if ($my_review_items && $my_review_items->isNotEmpty()) {
                $tab->addLink('由我审核', self::getReviewEvaluateUrl(), false);
            }
            if ($my_audit_items && $my_audit_items->isNotEmpty()) {
                $tab->addLink('由我审阅', self::getAuditEvaluateUrl(), false);
            }
            if ($my_copy_items && $my_copy_items->isNotEmpty()) {
                $tab->add('抄送给我', $this->grid($my_copy_items)->render(), true);
            }
        }

        $html = $tab->render();

        return $content
            ->header('绩效记录')
            ->description(' ')
            ->body($html);
    }

    public static function isSelfEvaluate()
    {
        $route_name = request()->route()->getName();
        return in_array($route_name, [
            'appraisal.personal.record',
            'appraisal.personal.edit.index',
            'appraisal.personal.edit.store',
            'appraisal.personal.record.show'
        ]);
    }

    /**
     * @param Collection|null $my_items 待 我评审、审计、抄送给我的 表格
     * @return Grid
     */
    protected function grid(Collection $my_items = null)
    {

        /** @var self $self */
        $self = self::class;
        $lang_of_weeks = CalendarModel::getTranslatedWeeks();
        /** @var UserModel $current_user */
        $current_user = UserModel::getUser();
        $fnGetCurrentLocalTime = function ($dt) use ($current_user) {
            $r = Carbon::parse($dt ?? Carbon::now())->timezone($current_user->timezone())->toDateTimeString();
            return $r;
        };

        $grid = new Grid(new AppraisalAssignmentExecutor());
        $grid->disableCreateButton();
        $grid->disableExport();


        $grid->actions(function (Grid\Displayers\Actions $actions) use ($self) {
            $actions->disableDelete();
            $status = $actions->row->status;
            if ($self::isSelfEvaluate()) {
                if (!$self::selfEvaluateEditable($status)) {
                    $actions->disableEdit();
                }
            } elseif ($self::isReviewEvaluate()) {
                if (!$self::reviewEvaluateEditable($status)) {
                    $actions->disableEdit();
                }
            } elseif ($self::isAuditEvaluate()) {
                if (!$self::auditEvaluateEditable($status)) {
                    $actions->disableEdit();
                }
            } elseif ($self::isCopyEvaluate()) {
                $actions->disableEdit();
            }
        });

        if ($my_items && $my_items->isNotEmpty()) {
            $assignment_ids = $my_items->pluck('assignment_id')->toArray();
            $grid->model()->whereIn('assignment_id', $assignment_ids);
        } else {
            $grid->model()->where('user_id', $current_user->id);
        }
        $grid->model()->orderBy('id', 'DESC');

        $grid->filter(function (Grid\Filter $filter) use ($self) {
            $filter->disableIdFilter();
            $filter->where(function (Builder $query) {
                $input = $this->input;
                $assignment_ids = AppraisalTableAssignment::getAssignmentsByTableIds($input)->pluck('id')->toArray();
                $query->whereIn('assignment_id', $assignment_ids);
            }, '表类型')->multipleSelect(AppraisalTablesModel::getExistTables(true)->toArray());


            $filter->in('date', '季度')->select(AppraisalAssignmentExecutor::getGridFilterDate());

            if (!$self::isSelfEvaluate()) {
                $filter->in('user_id', '填表者')->multipleSelect(UserModel::getAllUsersPluck(true));
            }

            if (!$self::isReviewEvaluate()) {
                $filter->where(function (Builder $query) {
                    $input = $this->input;
                    $assignment_ids = AppraisalAssignmentReviewer::getAssignment($input,
                        ['assignment_id'])->pluck('assignment_id')->toArray();
                    $query->whereIn('assignment_id', $assignment_ids);
                }, '审核者')->multipleSelect(UserModel::getAllUsersPluck(true));
            }

            if (!$self::isAuditEvaluate()) {
                $filter->where(function (Builder $query) {
                    $input = $this->input;
                    $assignment_ids = AppraisalAssignmentAuditor::getAssignment($input,
                        ['assignment_id'])->pluck('assignment_id')->toArray();
                    $query->whereIn('assignment_id', $assignment_ids);
                }, '审阅者')->multipleSelect(UserModel::getAllUsersPluck(true));
            }

            //$filter->in('self_grade', '自评级别')->multipleSelect(AppraisalTablesModel::$GRADE_VALUES);
            //$filter->in('review_grade', '复审级别')->multipleSelect(AppraisalTablesModel::$GRADE_VALUES);
            $filter->where(function ($query) {
                $input = intval($this->input);
                if ($input == AppraisalTablesModel::TABLE_STATUS_NONE) {
                    $query->whereRaw("`status` =  {$input}");
                } else {
                    $query->whereRaw("`status` >=  {$input}");
                }
            }, '状态')->select(AppraisalTablesModel::$TABLE_STATUS);
        });


        $grid->id('ID')->sortable();
        $grid->assignment_id('表格')->display(function ($assignment_id) {
            $info = AppraisalTableAssignment::getTableInfo($assignment_id);
            if ($info) {
                return $info->name;
            } else {
                return "未知";
            }
        })->sortable();


        $grid->date('季度')->display(function ($val) {
            $dt = Carbon::parse($val);
            return sprintf('%s Q%s', $dt->year, $dt->quarter);
        })->sortable();

        $grid->status('进度状态')->display(function ($val) use (&$fnGetCurrentLocalTime) {
            return AppraisalTablesModel::getTableStatusProcess($val,
                $fnGetCurrentLocalTime($this->self_evaluate_at),
                $fnGetCurrentLocalTime($this->report_evaluate_at),
                $fnGetCurrentLocalTime($this->approve_evaluate_at),
                $fnGetCurrentLocalTime($this->interview_evaluate_at));
        })->sortable();

        $grid->deadline('截止时间')->display(function ($val) use ($lang_of_weeks) {
            $assignment_id = $this->assignment_id;
            $deadline = AppraisalTableAssignment::find($assignment_id)->deadline;
            $dt = Carbon::parse($deadline);
            return sprintf('%s %s', $dt->format('m-d'), $lang_of_weeks[$dt->dayOfWeek]);
        });

        $grid->self_value('自评')->sortable()->display(function ($val) {
            if (!in_array($this->status, [
                AppraisalTablesModel::TABLE_STATUS_NONE,
                AppraisalTablesModel::TABLE_STATUS_SAVED
            ])) {
                $grade = self::getGradeInfo($this->assignment_id, $this->self_value);
                return sprintf('<span class="badge badge-%s">%s</span>', $grade['style'], $grade['grade']);
            }
            return '';
        });

        $grid->review_grade('复核')->sortable()->display(function ($val) {
            if (in_array($this->status, [
                AppraisalTablesModel::TABLE_STATUS_REPORTED,
                AppraisalTablesModel::TABLE_STATUS_APPROVED,
                AppraisalTablesModel::TABLE_STATUS_INTERVIEWED
            ])) {
                $grade = self::getGradeInfo($this->assignment_id, $this->review_value);
                return sprintf('<span class="badge badge-%s">%s</span>', $grade['style'], $grade['grade']);
            }
            return '';
        });

        if (!self::isSelfEvaluate()) {
            $grid->user('填表者')->display(function ($val) {
                if ($val) {
                    return "<span class='badge badge-pill badge-purple font-weight-normal'>{$val['name']}</span> ";
                }
                return '';
            });
        }
        if (!self::isReviewEvaluate()) {
            $grid->reviewers('审核者')->display(function () {
                $assignment_info = AppraisalTableAssignment::find($this->assignment_id);
                $auditors = $assignment_info->reviewers->pluck('name', 'id')->toArray();
                $content = '';
                foreach ($auditors as $user_id => $user_name) {
                    $content .= "<span class='badge badge-pill badge-light font-weight-normal'>$user_name</span> ";
                }
                return $content;
            });
        }
        if (!self::isAuditEvaluate()) {
            $grid->auditors('审阅者')->display(function () {
                $assignment_info = AppraisalTableAssignment::find($this->assignment_id);
                $auditors = $assignment_info->auditors->pluck('name', 'id')->toArray();
                $content = '';
                foreach ($auditors as $user_id => $user_name) {
                    $content .= "<span class='badge badge-pill badge-light font-weight-normal'>$user_name</span> ";
                }
                return $content;
            });
        }

        $grid->updated_at(trans('admin.updated_at'))->sortable();
        if (self::isAuditEvaluate()) {
            $grid->tools(function (Grid\Tools $tools) {
                $tools->batch(function (Grid\Tools\BatchActions $batch) {
                    $batch->disableDelete();
                    $batch->add('审核通过', new BatchActionAppraisal(BatchActionAppraisal::ACTION_APPROVE));
                    $batch->add('驳回重审', new BatchActionAppraisal(BatchActionAppraisal::ACTION_REJECT));
                });
            });
        } else {
            $grid->disableRowSelector();
        }
        return $grid;
    }

    /** 自评 */
    public static function selfEvaluateEditable($evaluate_status)
    {
        return in_array($evaluate_status, [
            AppraisalTablesModel::TABLE_STATUS_NONE,
            AppraisalTablesModel::TABLE_STATUS_SAVED
        ]);
    }

    public static function isReviewEvaluate()
    {
        $route_name = request()->route()->getName();
        return in_array($route_name, [
            'appraisal.personal.record.review',
            'appraisal.personal.review.edit.index',
            'appraisal.personal.review.edit.store',
            'appraisal.personal.review.edit.show'
        ]);
    }

    /** 审核 */
    public static function reviewEvaluateEditable($evaluate_status)
    {
        return in_array($evaluate_status, [
            AppraisalTablesModel::TABLE_STATUS_SUBMITTED,
            AppraisalTablesModel::TABLE_STATUS_APPROVED
        ]);
    }

    public static function isAuditEvaluate()
    {
        $route_name = request()->route()->getName();
        return in_array($route_name, [
            'appraisal.personal.record.view',
            'appraisal.personal.view.edit.index',
            'appraisal.personal.view.edit.store',
            'appraisal.personal.view.edit.show'
        ]);
    }

    /** 审阅 */
    public static function auditEvaluateEditable($evaluate_status)
    {
        return in_array($evaluate_status, [AppraisalTablesModel::TABLE_STATUS_REPORTED]);
    }

    public static function isCopyEvaluate()
    {
        $route_name = request()->route()->getName();
        return in_array($route_name, [
            'appraisal.personal.record.carboncopy',
            'appraisal.personal.carboncopy.edit.show'
        ]);
    }

    public static function getReviewEvaluateUrl()
    {
        return route("appraisal.personal.record.review");
    }

    public static function getAuditEvaluateUrl()
    {
        return route("appraisal.personal.record.view");
    }

    public static function getCopyEvaluateUrl()
    {
        return route("appraisal.personal.record.carboncopy");
    }

    public static function getSelfEvaluateUrl()
    {
        return route("appraisal.personal.record");
    }

    /************************************************************/

    public function show($id, Content $content)
    {
        $content->is_edit_mode = false;
        return $this->edit($id, $content);
    }

    public function edit($execute_id, Content $content)
    {
        /** @var AppraisalTableAssignment $executor_info */
        $executor_info = AppraisalAssignmentExecutor::find($execute_id);
        $evaluate_status = $executor_info->status;

        $execute_user = UserModel::getUser($executor_info->user_id);
        $current_user = UserModel::getUser();
        $map['assignment_id'] = $executor_info->assignment_id;
        $map['user_id'] = $current_user->id;
        $reviewer_info = AppraisalAssignmentReviewer::where($map)->first();
        $auditor_info = AppraisalAssignmentAuditor::where($map)->first();

        if (request()->isMethod('get')) {
            if ($reviewer_info && $auditor_info && self::isReviewEvaluate()) {  //弹框条件
                Admin::script($this->script($execute_id));
            }
            $list_url = self::getListUrl();
            $list_btn = <<<eot
	<div class="btn-group" style="margin-right: 5px">
		<a href="{$list_url}" class="btn btn-sm btn-primary" title="返回列表">
			<i class="fa fa-list"></i><span class="hidden-xs">&nbsp;返回列表</span>
		</a>
	</div>
eot;
            $fnGetCurrentLocalTime = function ($dt) use ($current_user) {
                $r = Carbon::parse($dt ?? Carbon::now())->timezone($current_user->timezone())->toDateTimeString();
                return $r;
            };

            $table_info = AppraisalAssignmentExecutor::getTableInfo($execute_id);

            //oa_appraisal_table_values
            $init_data = AppraisalTablValues::where('assignment_id', $execute_id)->get()->pluck('value',
                'name')->toArray();
            //oa_appraisal_assignment_executors
            $init_data = array_merge($init_data, $executor_info->toArray());

            //合法化数据
            $review_user_id = $init_data['review_user_id'] ?? 0;
            $init_data['result_self_user'] = $execute_user->name;
            $init_data['result_review_user'] = $review_user_id ? UserModel::getUser($review_user_id)->name : '';

            $init_data['result_self_at'] = $fnGetCurrentLocalTime($init_data['self_evaluate_at']);
            $init_data['result_review_at'] = $fnGetCurrentLocalTime($init_data['review_evaluate_at']);

            $init_data['result_self_value'] = $init_data['self_value'] ?? 0;
            $init_data['result_self_grade'] = $init_data['self_grade'] ?? 0;

            $init_data['result_review_value'] = $init_data['review_value'] ?? 0;
            $init_data['result_review_grade'] = $init_data['review_grade'] ?? 0;


            $operate_mode = self::getOperateMode();


            $table_template = AppraisalTablesModel::getTemplateByTableType($table_info->type, $init_data,
                $evaluate_status, $operate_mode);


            $url = request()->url();
            $form = new Form($init_data);
            $form->hidden('_execute_id')->default($execute_id);
            $form->hidden('_user_id')->default($current_user->id);
            $form->hidden('_assignment_id')->default($executor_info->assignment_id);

            if (is_array($table_template)) {
                foreach ($table_template as $key => $val) {
                    $form->html($val, $key);
                }
            } else {
                $form->html($table_template);
                $form->setWidth(12, 0);
            }

            $form->action($url);
            $form->disableReset();
            self::displayEditor($form, $init_data, $evaluate_status, $operate_mode);
            self::displaySubmitStatus($form, $evaluate_status, $operate_mode);
            $box = new ExtendedBox($table_info->name, $form->render());
            $box->addTool($list_btn);
            $box->solid();

            return $content
                ->header($table_info->name)
                ->description($execute_user->name)
                ->body($box);
        } else {
            $_execute_id = request('_execute_id');
            $_assignment_id = request('_assignment_id');
            $_status = request('status');

            $all = request()->all();
//				dd($all);
            $current_datetime = Carbon::now();
            if (self::isSelfEvaluate()) {
                $update_data = [
                    'self_value' => floatval($all['section_work_self_evaluate_value'] ?? $all['_result_self_value'] ?? 0),
                    //'self_grade' => AppraisalTablesModel::getGradeValueFromHuman($all['_result_self_grade'] ?? 0),
                    'self_evaluate_at' => $current_datetime->toDateTimeString(),
                ];
            } elseif (self::isReviewEvaluate()) {
                $update_data = [
                    'review_value' => floatval($all['section_work_review_evaluate_value'] ?? $all['_result_review_value'] ?? 0),
                    //'review_grade' => AppraisalTablesModel::getGradeValueFromHuman($all['_result_review_grade'] ?? 0),
                    'review_evaluate_at' => $current_datetime->toDateTimeString(),
                ];
                $all['review_user_id'] = $current_user->id;
            } elseif (self::isAuditEvaluate()) {
                $update_data = [
                    'review_value' => floatval($all['section_work_review_evaluate_value'] ?? $all['_result_review_value'] ?? 0),
                    //'review_grade' => AppraisalTablesModel::getGradeValueFromHuman($all['_result_review_grade'] ?? 0),
                    'review_evaluate_at' => $current_datetime->toDateTimeString(),
                ];
                $all['audit_user_id'] = $current_user->id;
            }

            if ($_status) {
                $update_data['status'] = $_status;
            }
            if ($_status == AppraisalTablesModel::TABLE_STATUS_REPORTED) {
                $update_data['report_evaluate_at'] = $current_datetime->toDateTimeString();
            } elseif ($_status == AppraisalTablesModel::TABLE_STATUS_APPROVED) {
                $update_data['approve_evaluate_at'] = $current_datetime->toDateTimeString();
            } elseif ($_status == AppraisalTablesModel::TABLE_STATUS_INTERVIEWED) {
                $update_data['interview_evaluate_at'] = $current_datetime->toDateTimeString();
            }

            if ($update_data) {
                AppraisalAssignmentExecutor::where('assignment_id', $_assignment_id)
                    ->where('id', $_execute_id)
                    ->update($update_data);
            }

            //更新该绩效表的值
            $valid_items = [];
            array_walk_recursive($all, function ($val, $key) use (&$valid_items) {
                if (!starts_with($key, '_')) {
                    $valid_items[$key] = trim($val);
                }
            });

            // 更新绩效结果表中的值
            if ($_status == AppraisalTablesModel::TABLE_STATUS_REPORTED) {
                $this->updateAppraisalResult($_assignment_id, $update_data['review_value'], false);
            }
            elseif ($_status == AppraisalTablesModel::TABLE_STATUS_APPROVED) {
                $this->updateAppraisalResult($_assignment_id, $update_data['review_value'], true);
            }

            foreach ($valid_items as $key => $val) {
                AppraisalTablValues::updateOrCreate(
                    ['assignment_id' => $execute_id, 'name' => $key],
                    ['value' => $val]
                );
            }

            if ($_status == AppraisalTablesModel::TABLE_STATUS_SUBMITTED) {
                admin_success('提交成功');
            }elseif ($_status == AppraisalTablesModel::TABLE_STATUS_REPORTED) {//状态选择
                if ($reviewer_info && $auditor_info && self::isReviewEvaluate()) {  //弹框条件
                    die(' ');
                } else {
                    admin_success('保存成功');
                }
            } else {
                admin_success('保存成功');
            }
        }
    }

    public static function getListUrl()
    {
        if (self::isSelfEvaluate()) {
            return self::getSelfEvaluateUrl();
        } elseif (self::isReviewEvaluate()) {
            return self::getReviewEvaluateUrl();
        } elseif (self::isAuditEvaluate()) {
            return self::getAuditEvaluateUrl();
        } elseif (self::isCopyEvaluate()) {
            return self::getCopyEvaluateUrl();
        }
    }

    public static function getOperateMode()
    {
        $route_name = request()->route()->getName();

        if (self::isSelfEvaluate()) {
            if (in_array($route_name, ['appraisal.personal.edit.index'])) {
                return AppraisalTablesModel::OPERATE_MODE_SELF_EDIT;
            }
            return AppraisalTablesModel::OPERATE_MODE_SELF_VIEW;
        } elseif (self::isReviewEvaluate()) {
            if (in_array($route_name, ['appraisal.personal.review.edit.index'])) {
                return AppraisalTablesModel::OPERATE_MODE_REVIEW_EDIT;
            }
            return AppraisalTablesModel::OPERATE_MODE_REVIEW_VIEW;
        } elseif (self::isAuditEvaluate()) {
            if (in_array($route_name, ['appraisal.personal.view.edit.index'])) {
                return AppraisalTablesModel::OPERATE_MODE_AUDIT_EDIT;
            }
            return AppraisalTablesModel::OPERATE_MODE_AUDIT_VIEW;
        } elseif (self::isCopyEvaluate()) {
            return AppraisalTablesModel::OPERATE_MODE_COPY_VIEW;
        }
    }

    private function displayEditor(Form &$form, $init_data, $status, $operate_mode)
    {
        $names = [
            'self_evaluate_remark' => '评估者备注',
            'review_evaluate_remark' => '审核者点评',
            'view_evaluate_remark' => '审阅者点评',
            'interview_evaluate_remark' => '面谈记录',
        ];
        $fnDisplay = function ($key) use (&$form, $names, $init_data) {
            if (isset($init_data[$key]) && $init_data[$key]) {
                $form->display($key, $names[$key])->setWidth(8, 2);
            }
        };
        $fnEditor = function ($key) use (&$form, $names) {
            $options = [
                'disable-menubar' => true,
                'disable-statusbar' => true,
            ];
            $form->editor($key, $names[$key])->setWidth(8, 2)->options($options)->help("<b>填表者</b> 和 <b>审核者</b> 均可见");
        };

        switch ($operate_mode) {
            case AppraisalTablesModel::OPERATE_MODE_SELF_VIEW:
                $fnDisplay('self_evaluate_remark');
                if (in_array($status, [
                    AppraisalTablesModel::TABLE_STATUS_APPROVED,
                    AppraisalTablesModel::TABLE_STATUS_INTERVIEWED
                ])) {
                    $fnDisplay('review_evaluate_remark');
                    $fnDisplay('view_evaluate_remark');
                    $fnDisplay('interview_evaluate_remark');
                }
                break;
            case AppraisalTablesModel::OPERATE_MODE_SELF_EDIT:
                if (in_array($status, [
                    AppraisalTablesModel::TABLE_STATUS_NONE,
                    AppraisalTablesModel::TABLE_STATUS_SAVED
                ])) {
                    $fnEditor('self_evaluate_remark');
                } else {
                    $fnDisplay('self_evaluate_remark');
                }
                break;


            case AppraisalTablesModel::OPERATE_MODE_REVIEW_VIEW:
                $fnDisplay('self_evaluate_remark');
                $fnDisplay('review_evaluate_remark');
                $fnDisplay('view_evaluate_remark');
                $fnDisplay('interview_evaluate_remark');
                break;
            case AppraisalTablesModel::OPERATE_MODE_REVIEW_EDIT:
                if (in_array($status, [AppraisalTablesModel::TABLE_STATUS_SUBMITTED])) {
                    $fnDisplay('self_evaluate_remark');
                    $fnDisplay('view_evaluate_remark');
                    $fnEditor('review_evaluate_remark');
                } else {
                    if (in_array($status, [AppraisalTablesModel::TABLE_STATUS_APPROVED])) {
                        $fnDisplay('self_evaluate_remark');
                        $fnDisplay('review_evaluate_remark');
                        $fnDisplay('view_evaluate_remark');
                        $fnEditor('interview_evaluate_remark');
                    } else {
                        if (in_array($status, [
                            AppraisalTablesModel::TABLE_STATUS_REPORTED,
                            AppraisalTablesModel::TABLE_STATUS_INTERVIEWED
                        ])) {
                            $fnDisplay('self_evaluate_remark');
                            $fnDisplay('review_evaluate_remark');
                            $fnDisplay('view_evaluate_remark');
                            $fnDisplay('interview_evaluate_remark');
                        }
                    }
                }
                break;

            case AppraisalTablesModel::OPERATE_MODE_AUDIT_VIEW:
                $fnDisplay('self_evaluate_remark');
                $fnDisplay('review_evaluate_remark');
                $fnDisplay('view_evaluate_remark');
                $fnDisplay('interview_evaluate_remark');
                break;
            case AppraisalTablesModel::OPERATE_MODE_AUDIT_EDIT:
                if (in_array($status, [AppraisalTablesModel::TABLE_STATUS_REPORTED])) {
                    $fnDisplay('self_evaluate_remark');
                    $fnDisplay('review_evaluate_remark');
                    $fnEditor('view_evaluate_remark');
                } else {
                    $fnDisplay('self_evaluate_remark');
                    $fnDisplay('review_evaluate_remark');
                    $fnDisplay('view_evaluate_remark');
                    $fnDisplay('interview_evaluate_remark');
                }
                break;
            case AppraisalTablesModel::OPERATE_MODE_COPY_VIEW:
                $fnDisplay('self_evaluate_remark');
                $fnDisplay('review_evaluate_remark');
                $fnDisplay('view_evaluate_remark');
                $fnDisplay('interview_evaluate_remark');
                break;

        }
    }

    private function displaySubmitStatus(Form &$form, $status, $operate_mode)
    {

        $options = [];
        switch ($operate_mode) {
            case AppraisalTablesModel::OPERATE_MODE_SELF_EDIT:
                if (in_array($status, [
                    AppraisalTablesModel::TABLE_STATUS_NONE,
                    AppraisalTablesModel::TABLE_STATUS_SAVED
                ])) {
                    $options = [
                        AppraisalTablesModel::TABLE_STATUS_SAVED => '仅保存 (不会提交到审核者)',
                        AppraisalTablesModel::TABLE_STATUS_SUBMITTED => '提交到审核者 (提交后将无法再次编辑)',
                    ];
                }
                break;
            case AppraisalTablesModel::OPERATE_MODE_REVIEW_EDIT:
                if (in_array($status, [AppraisalTablesModel::TABLE_STATUS_APPROVED])) {
                    $options = [
                        AppraisalTablesModel::TABLE_STATUS_INTERVIEWED => '已面谈',
                    ];
                } else {
                    if (in_array($status, [AppraisalTablesModel::TABLE_STATUS_SUBMITTED])) {
                        $options = [
                            AppraisalTablesModel::TABLE_STATUS_SAVED => '驳回评估者重审',
                            AppraisalTablesModel::TABLE_STATUS_REPORTED => '提交到审阅者 (提交后将无法再次编辑)',
                        ];
                    }
                }
                break;
            case AppraisalTablesModel::OPERATE_MODE_AUDIT_EDIT:
                if (in_array($status, [AppraisalTablesModel::TABLE_STATUS_REPORTED])) {
                    $options = [
                        AppraisalTablesModel::TABLE_STATUS_SUBMITTED => '驳回审核者重审',
                        AppraisalTablesModel::TABLE_STATUS_APPROVED => '审核通过 (提交后将无法再次编辑)',
                    ];
                }
                break;
        }
        $form->disableReset();
        if ($options && sizeof($options) > 0) {
            $form->select('status', '状态')->options($options);

        } else {
            $form->disableSubmit();
        }
    }

    public function batchOperate(Request $request)
    {
        $return['status'] = 0;
        $action = intval($request->post('action'));
        $ids = $request->post('ids');
        if (!is_array($ids) || sizeof($ids) == 0) {
            $return['message'] = '提交的数据不合法';
            return $return;
        }
        $result = 0;
        switch ($action) {
            case BatchActionAppraisal::ACTION_APPROVE:    //批量审核通过
                $result = AppraisalAssignmentExecutor::whereIn('id', $ids)
                    ->whereIn('status', [AppraisalTablesModel::TABLE_STATUS_REPORTED])
                    ->update(['status' => AppraisalTablesModel::TABLE_STATUS_APPROVED]);
                break;
            case BatchActionAppraisal::ACTION_REJECT:    //批量驳回
                $result = AppraisalAssignmentExecutor::whereIn('id', $ids)
                    ->whereIn('status', [AppraisalTablesModel::TABLE_STATUS_REPORTED])
                    ->update(['status' => AppraisalTablesModel::TABLE_STATUS_SUBMITTED]);
                break;
        }

        if ($result) {
            return ['status' => $result, 'message' => sprintf('操作成功: 影响条数 %s', $result),];
        } else {
            return ['status' => $result, 'message' => sprintf('操作失败: 影响条数 %s', $result),];
        }
    }

    private function updateAppraisalResult($assignment_id, $review_value, $confirmed)
    {
        if ($review_value <= 0) {
            return;
        }

        $assignment = AppraisalTableAssignment::find($assignment_id);
        if (!$assignment) {
            return;
        }
        $executor = AppraisalAssignmentExecutor::where('assignment_id', $assignment_id)->first();
        if (!$executor) {
            return;
        }

        $table_type = AppraisalTablesModel::find($assignment->table_id)->type;
        if (!$table_type) {
            return;
        }

        $grade = null;
        if ($table_type == AppraisalTablesModel::TABLE_TYPE_DEVELOPER) {
            $grade = AppraisalTableTypeDeveloper::getGradeFromScore($review_value);
        } elseif ($table_type == AppraisalTablesModel::TABLE_TYPE_GENERAL) {
            $grade = AppraisalTableTypeGeneral::getGradeFromScore($review_value);
        } elseif ($table_type == AppraisalTablesModel::TABLE_TYPE_CORPORATE_CULTURE) {
            $grade = AppraisalTableTypeCorporateCulture::getGradeFromScore($review_value);
        }
        if ($grade === null) {
            return;
        }

        $user_id = $executor->user_id;
        $year = $assignment->year;
        $month = $assignment->quarter * 3;

        $result = AppraisalResultModel::where('user_id', $user_id)->where('year', $year)->where('month',
            $month)->first();
        if (!$result) {
            $result = new AppraisalResultModel();
            $result->user_id = $user_id;
            $result->year = $year;
            $result->month = $month;
            $result->confirmed = 0;
        }

        if ($table_type == AppraisalTablesModel::TABLE_TYPE_DEVELOPER) {
            $result->result = strval($grade);
            $result->confirmed |= $confirmed ? 0b10 : 0b00;
        } elseif ($table_type == AppraisalTablesModel::TABLE_TYPE_GENERAL) {
            $result->result = strval($grade);
            $result->confirmed |= $confirmed ? 0b10 : 0b00;
        } elseif ($table_type == AppraisalTablesModel::TABLE_TYPE_CORPORATE_CULTURE) {
            $result->culture = strval($grade);
            $result->confirmed |= $confirmed ? 0b01 : 0b00;
        }

        $result->save();
    }

    private static function getGradeInfo($assignment_id, $value)
    {
        $grade_info = ['grade' => '', 'style' => ''];

        $assignment = AppraisalTableAssignment::find($assignment_id);
        if ($assignment) {
            $table_type = AppraisalTablesModel::find($assignment->table_id)->type;
            if ($table_type) {
                if ($table_type == AppraisalTablesModel::TABLE_TYPE_DEVELOPER) {
                    $grade_info['grade'] = AppraisalTableTypeDeveloper::getGradeFromScore($value);
                    $grade_info['style'] = $grade_info['grade'] ? AppraisalTableTypeDeveloper::getGradeStyle($grade_info['grade']) : '';
                } elseif ($table_type == AppraisalTablesModel::TABLE_TYPE_GENERAL) {
                    $grade_info['grade'] = AppraisalTableTypeGeneral::getGradeFromScore($value);
                    $grade_info['style'] = $grade_info['grade'] ? AppraisalTableTypeGeneral::getGradeStyle($grade_info['grade']) : '';
                } elseif ($table_type == AppraisalTablesModel::TABLE_TYPE_CORPORATE_CULTURE) {
                    $grade_info['grade'] = AppraisalTableTypeCorporateCulture::getGradeFromScore($value);
                    $grade_info['style'] = $grade_info['grade'] ? AppraisalTableTypeCorporateCulture::getGradeStyle($grade_info['grade']) : '';
                }
                return $grade_info;
            }
        }
    }

    public function pass()
    {
        $_execute_id = request('id');
        AppraisalAssignmentExecutor::where('id', $_execute_id)->update(['status' => 4]);
        return response()->json(['status' => 1, 'message' => '审阅通过']);
    }

    public function cancel()
    {
        $_execute_id = request('id');
        AppraisalAssignmentExecutor::where('id', $_execute_id)->update(['status' => 2]);
        return response()->json(['status' => 1, 'message' => '取消成功']);
    }

    public function script($execute_id){
        return $js = <<<EOT
 $('button.pull-right').on('click', function () {
        var select = $('select[name = status]');
        if(select.val() == 3){
            swal({
                title: '审核通过，你同时是审阅者，确定审阅通过吗？',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '确定！',
                cancelButtonText: '取消！',
                confirmButtonClass: 'btn btn-success',
                cancelButtonClass: 'btn btn-danger',
                buttonsStyling: false
            }).then(function(isConfirm) {
                if (isConfirm.value === true) {
                    $.ajax({
                        method: 'post',
                        url: '/admin/table/appraisal/audit/pass',
                         dataType:"json",
                          data: {
                                _token:LA.token,
                                'id' : {$execute_id}
                            },
                        success: function (data) {
                        $.pjax.reload('#pjax-container');
                            if (typeof data === 'object') {
                                if (data.status) {
                                    swal(data.message, '', 'success');
                                } else {
                                    swal(data.message, '', 'error');
                                }
                            }
                        }
                    });
                }else{
                   $.pjax.reload('#pjax-container');
                }
            });
         }
    });
EOT;
    }
}
