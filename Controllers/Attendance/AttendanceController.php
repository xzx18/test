<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppraisalAssignmentExecutor;
use App\Models\ApproveModel;
use App\Models\AttendanceModel;
use App\Models\AuditModel;
use App\Models\AttendanceWorkDaysModel;
use App\Models\Auth\OAPermission;
use App\Models\FinancialSalaryModel;
use App\Models\NoticeModel;
use App\Models\WorkplaceModel;
use App\Models\NoticeUserModel;
use App\Models\ProjectModel;
use App\Models\UserConfirmationModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Form;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Tab;
use http\Client\Curl\User;
use Illuminate\Http\Request;

/** 签到签退 */
class AttendanceController extends Controller
{

    public function check(Content $content)
    {
        $body = '';

        $user = UserModel::getUser();
        $current_time = UserModel::getUserTime();
        $row = AttendanceModel::getAttendance($user->id, $current_time->toDateString());

        $desc = ' ';
        $selected_tab = 0;
        if (!empty($row)) {
            if (!empty($row->signin_time) && empty($row->signout_time)) {
                $desc = tr("Checked In, Uncheck Out");
            }
            if (empty($row->signin_time) && !empty($row->signout_time)) {
                $desc = tr("Checked Out, Uncheck In");
                $selected_tab = 1;
            }
            if (!empty($row->signin_time) && !empty($row->signout_time)) {
                $desc = tr("Checked In, Checked Out");
                $selected_tab = 1;
            }
        }

        $tab = new Tab();
        $tab->add(tr("Check In"), $this->signIn($content, $row));
        $tab->add(tr("Check Out"), $this->signOut($content, $row));
        $body .= $tab->render();

        $body .= <<<EOT
<script>
        $(function () {
            $('.nav-tabs li a')[{$selected_tab}].click();
        
            $('.checkbox-dingtalk-report-signin').change(function (e) {
                var checked = this.checked;
                var el_ids = ['today_plan'];
                el_ids.forEach(function (_key) {
                    var el = $('.' + _key).closest('.form-group');
                    if (checked) {
                        el.hide();
                    } else {
                        el.show();
                    }
                });
            });
        
            $('.checkbox-dingtalk-report-signout').change(function (e) {
                var checked = this.checked;
                var el_ids = ['today_summary', 'tomorrow_plan', 'what_problem', 'what_help', 'what_get'];
                el_ids.forEach(function (_key) {
                    var el = $('.' + _key).closest('.form-group');
                    if (checked) {
                        el.hide();
                    } else {
                        el.show();
                    }
                });
            });
        
            $('.checkbox-dingtalk-report-signin').trigger('change');
            $('.checkbox-dingtalk-report-signout').trigger('change');
        });
</script>
EOT;
        return $content
            ->header(tr('Check In/Out'))
            ->description($current_time->toDateString() . " " . $desc)
            ->row($this->getNotice())
            ->body($body);

    }

    public function signIn(Content $content, $row)
    {
        $form = $this->getForm();
        $form->setTitle(tr('Check In Title'));
        $today_plan = $row['today_plan'] ?? '';
        if (!empty($row) && $row->signin_time) {
            $signin_type = AttendanceModel::getAttendanceCheckTypeHumanFromTypeId($row->signin_type);
            $singin_time_tr = tr('Check In Time');
            $signin_html = <<<eot
            <span class="badge badge-success">{$singin_time_tr}：{$row->signin_time}</span><span class="badge badge-warning">{$signin_type}</span>
eot;
            $form->html($signin_html);
        }

        if (empty($today_plan)) {
            //前一天的签退信息 -> 明日工作计划
            $user = UserModel::getUser();
            $user_id = $user->id;
            $date = $user->currentTime()->toDateString();
            $previous_record = AttendanceModel::getPreviousAttendance($user_id, $date);
            if ($previous_record && !empty($previous_record->tomorrow_plan)) {
                $today_plan = $previous_record->tomorrow_plan;
            }
        }

        $this->displayDingtalkReportCheckbox($form, 'signin');
        $form->textarea('today_plan',
            tr("Today's Plan"))->rows(8)->placeholder(tr('At least 10 characters'))->default($today_plan)->attribute(['signin-dingtalk-replace' => 1]);
        $form->hidden('type')->value('signin');
        $body = $form->render();
        return $body;
    }

    /**
     * @param $action_type
     * @return Form
     */
    private function getForm()
    {
        $form = new Form(new AttendanceModel);
        $form->disableViewCheck();
        $form->disableEditingCheck();
        $form->tools(function (Form\Tools $tools) {
            $tools->disableList();
            $tools->disableDelete();
            $tools->disableView();
        });
        $form->hidden('_hash', '_hash')->value(md5(uniqid() . time()));
        $form->setAction(\request()->url());
        return $form;
    }

    private function displayDingtalkReportCheckbox(Form &$form, $type)
    {
        $checked = '';
        if (session()->get('oa_use_dingtalk_report')) {
            $checked = 'checked';
        }

        $dingtalk_label = tr('I will use DingTalk to write report');
        $dingtalk_html = <<<eot
<label class="font-weight-normal badge badge-warning" style="padding: 10px;"><input  {$checked} type="checkbox" name="checkbox-dingtalk-report" value="1" class="checkbox-dingtalk-report-{$type}" />&nbsp;{$dingtalk_label}</label>
eot;
        $form->html($dingtalk_html, '');
        $form->saving(function (Form $f) {
            $f->ignore('checkbox-dingtalk-report');
        });
    }

    public function signOut(Content $content, $row)
    {
        $form = $this->getForm();

        if (!empty($row) && $row->signout_time) {
            $signout_type = AttendanceModel::getAttendanceCheckTypeHumanFromTypeId($row->signout_type);
            $singout_time_tr = tr('Check Out Time');
            $signout_html = <<<eot
            <span class="badge badge-success">{$singout_time_tr}：{$row->signout_time}</span><span class="badge badge-warning">{$signout_type}</span>
eot;
            $form->html($signout_html);

        }

        $this->displayDingtalkReportCheckbox($form, 'signout');

        $project_create_url = route('project.create');

        //记录到session里面的项目
        $last_project_id = session()->get('oa_last_project_id', 0);
        $form->select('project_id',
            tr('Primary project'))->options(ProjectModel::getAllProjectPlucks())->help('【仅针对研发人员】选择今天主要工作的项目，统计研发工时用  <a href="' . $project_create_url . '">【没找到我的项目？点击添加】</a>')->default($last_project_id);

        $form->textarea('today_summary',
            tr("Today's Work Summary"))->rows(4)->placeholder(tr('At least 10 characters'))->default($row['today_summary'] ?? '')->attribute(['signout-dingtalk-replace' => 1]);
        $form->textarea('tomorrow_plan',
            tr("Tomorrow's Plan"))->rows(4)->placeholder(tr('At least 10 characters'))->default($row['tomorrow_plan'] ?? '')->attribute(['signout-dingtalk-replace' => 1]);

        $form->textarea('what_problem',
            tr('Problem'))->rows(2)->default($row['what_problem'] ?? '')->help(tr("Optional"))->attribute(['signout-dingtalk-replace' => 1]);
        $form->textarea('what_help',
            tr('Help'))->rows(2)->default($row['what_help'] ?? '')->help(tr("Optional"))->attribute(['signout-dingtalk-replace' => 1]);
        $form->textarea('what_get',
            tr('Learn'))->rows(2)->default($row['what_get'] ?? '')->help(tr("Optional"))->attribute(['signout-dingtalk-replace' => 1]);


        $form->hidden('type')->value('signout');

        $form->setTitle(tr('Check Out Title'));

        $default_val = config('oa.attendance.signout.default_dining_duration');
        if (!empty(config('oa.attendance.signout.fill_dining_duration_after_time'))) {
            $dt = Carbon::parse(config('oa.attendance.signout.fill_dining_duration_after_time'),
                UserModel::getUserTimezone());
            if ($dt->gt(UserModel::getUserTime())) {
                $default_val = 0;
            }
        }
        $form->number('dining_duration', tr('Dinner Duration'))->default($default_val);

        $form->number('noonbreak_duration', tr('Noon Break Duration'))->default(0);
        $body = $form->render();

        return $body;
    }

    public function getNotice()
    {
        $body = '';

        $user_id = UserModel::getCurrentUserId();

        // 考勤确认通知
        $dt_now = Carbon::now();
        if ($dt_now->day < 4) {
            $last_month = $dt_now->subMonth()->format('Y-m');
            if (UserConfirmationModel::getAttendanceConfirmStatus($user_id,
                    $last_month) == UserConfirmationModel::STATUS_NOT_CONFIRMED) {
                $_title = "请检查并确认上月({$last_month})考勤";
                $link = sprintf('%s?user=%s&month=%s', route('attendances.record'), $user_id, $last_month);
                $_content = "<a href=\"{$link}\">请点击这里进行核对并且确认</a>";
                $box = new Box($_title, $_content);
                $box->style('warning');
                $box->solid();
                $body .= $box;
            }
        }

        //工资条核对通知
        $tobeConfirmedSalaries = FinancialSalaryModel::getPersonalToBeConfirmedSalaries();
        if ($tobeConfirmedSalaries && $tobeConfirmedSalaries->isNotEmpty()) {
            $_title = sprintf('%s份工资条待确认，请尽快操作', $tobeConfirmedSalaries->count());
            $_content = '<ul>';
            $fnGetDetailsUrl = function ($id) {
                return route('financial.personal.detail', $id);
            };
            foreach ($tobeConfirmedSalaries as $tobeConfirmedSalary) {
                $dt = Carbon::parse($tobeConfirmedSalary->date)->format('Y年m月');
                $link = $fnGetDetailsUrl($tobeConfirmedSalary->id);
                $_content .= sprintf('<li><b>%s</b> 工资条待确认，<a href="%s">请点击这里进行核对并且确认</a></li>', $dt, $link);
            }
            $_content .= '</ul>';
            $box = new Box($_title, $_content);
            $box->style('danger');
            $box->solid();
            $body .= $box;
        }

        //绩效表通知
        $toBeConfirmedAssignment = AppraisalAssignmentExecutor::getToBeConfirmedAssignment();
        if ($toBeConfirmedAssignment && $toBeConfirmedAssignment->isNotEmpty()) {
            $_title = sprintf('%s份表格待完善，请尽快操作', $toBeConfirmedAssignment->count());
            $_content = sprintf('<ul><li><a href="%s">点击这里进行查看</a></li></ul>', route('appraisal.personal.record'));
            $box = new Box($_title, $_content);
            $box->style('danger');
            $box->solid();
            $body .= $box;
        }

        //通知消息
        $notices = NoticeModel::getValidNotices();
        foreach ($notices as $notice) {
            if (!NoticeUserModel::didNoticeRead($notice->id)) {
                $notice_title = <<<eot
				{$notice->title}
eot;
                $notice_html = <<<eot
{$notice->content}
<button type="button" class="btn btn-warning pull-right btn-confirm-notice" data-notice-id="{$notice->id}">已阅读并知晓</button>
eot;
                $notice_box = new Box($notice_title, $notice_html);
                $notice_box->offsetSet('notice-id', $notice->id);
                $notice_box->style('info');
                $notice_box->view('admin.widgets.box');
                $notice_box->solid();
                $body .= $notice_box;
            }
        }

        $notice_confirm_url = route('notice.confirm');
        $notice_js = <<<eot
<script>
$(function () {
	$('.btn-confirm-notice').click(function(e) {
	     var notice_id=$(this).data('notice-id');
	    var box=$('.box[notice-id="'+notice_id+'"]');

	    var notice_title=box.find('.box-header .box-title').text().trim();
	   swal({
			title: '确认 "'+notice_title+'" 已读?',
			type: "warning",
			showCancelButton: true,
			confirmButtonColor: "#DD6B55",
			confirmButtonText: "确认",
			showLoaderOnConfirm: true,
			cancelButtonText: "取消",
			preConfirm: function() {
				return new Promise(function(resolve) {
					$.ajax({
						method: 'post',
						url: '{$notice_confirm_url}',
						data: {
							notice_id: notice_id,
							_token:LA.token
						},success: function (data) {
							resolve(data);
						}
					});
				});
			}
			}).then(function(result) {
				var data = result.value;
				if (typeof data === 'object') {
					if (data.status) {
						swal(data.message, '', 'success').then(function() {
						  box.fadeOut();
						});
					} else {
						swal(data.message, '', 'error');
					}
			}
		});
	});
});

</script>
eot;

        return $body . $notice_js;
    }

    public function store(Request $request)
    {
        $type = $request->post('type');

        //过滤掉重复提交数据，目前暂时不知道为什么会请求2次POST
        $_hash = $request->post('_hash');
        $_session_key = $type . '.storehash';
        if (session($_session_key) && session($_session_key) == $_hash) {
//            Log::info("skip $type duplicate request");
            return "";
        } else {
            session()->put($_session_key, $_hash);
//            Log::info(json_encode(session()));
        }


        switch ($type) {
            case 'signin':
                $this->signInSave($request);
                break;
            case 'signout':
                $this->signOutSave($request);
                break;
            default:
                admin_error(tr('Error'), tr('Invalid request'));
                return redirect($request->url())->withInput();
        }
    }

    public function signInSave(Request $request)
    {
        $use_dingtalk_report = $request->get('checkbox-dingtalk-report');
        if (!$use_dingtalk_report) {
            $request->validate(['today_plan' => 'required|min:10',]);
        } else {
            session()->put('oa_user_dingtalk_report', true);
        }

        $today_plan = $request->post('today_plan');

        $user = UserModel::getUser();
        $current_time = UserModel::getUserTime();

        $record = AttendanceModel::firstOrNew(['user_id' => $user->id, 'date' => $current_time->toDateString()]);
        if ($record->signin_time && !$record->canModifySignIn(AttendanceModel::CHECK_TYPE_OA)) {
            AuditModel::signIn($record->id, $user->id, false, 'Already signed');
            admin_error(tr('Checked in failed'), tr("You have already checked in, time is: %s", $record->signin_time));
            return;
        }

        $record->today_plan = $today_plan;
        $record->signin_time = $current_time->toTimeString();
        $record->defined_signin_time = $user->workplace_info->signin_time;
        $record->defined_signout_time = $user->workplace_info->signout_time;
        $record->lunch_duration = $user->workplace_info->lunch_duration;
        $record->buffer_duration = $user->workplace_info->buffer_duration;
        $record->signin_type = AttendanceModel::CHECK_TYPE_OA;
        $record->is_extrawork = ApproveModel::dateApprovedForExtraWork($user->id, $current_time->toDateString());

        $result = $record->updateAttendanceBeLater();
        if (!$result) {
            AuditModel::signIn(0, $user->id, false, 'Failed to save database');
            admin_error(tr('Checked in failed'), tr('Failed to save database'));
            return;
        }

        AuditModel::signIn($record->id, $user->id, true);

        $belater = $record->is_later;
        $belater_minutes = ceil($record->later_seconds / 60);
        $rank_number = AttendanceModel::getSignInRank($user->id, $current_time->toDateString());

        if ($belater) {
            admin_warning(tr('Checked in successfully'),
                tr("Checked in time: %s, rank: %s, be later: %s minute(s)", $record->signin_time,
                    $rank_number, $belater_minutes));

        } else {
            admin_success(tr('Checked in successfully'),
                tr("Checked in time: %s, rank: %s, you are not late, keep on!", $record->signin_time,
                    $rank_number));
        }
    }

    public function signOutSave(Request $request)
    {
        $use_dingtalk_report = $request->get('checkbox-dingtalk-report');
        if (!$use_dingtalk_report) {
            $request->validate([
                'today_summary' => 'required|min:10',
                'tomorrow_plan' => 'required|min:10',
                'dining_duration' => 'integer|gte:0',
                'noonbreak_duration' => 'integer|gte:0'
            ]);
        } else {
            session()->put('oa_use_dingtalk_report', true);
        }

        $current_time = UserModel::getUserTime();
        $signout_time = $current_time;
        $signout_latest_time = Carbon::parse(config('oa.attendance.signout.latest_time'));

        $timezone = UserModel::getUserTimezone();
        $user = UserModel::getUser();
        $record = AttendanceModel::getAttendance($user->id, $current_time->toDateString());

        if (!$record || !$record->signin_time) {
            if ($current_time->hour > $signout_latest_time->hour) {
                // 正常签退，但未签到的情况
                admin_error(tr('Checked out failed'), tr('You must check in before checking out'));
                return redirect($request->url())->withInput();
            }

            // 过零点签退的情况
            $yestoday_latest_time = arbon::parse($current_time, $current_time->timezone)
                ->addDays(-1)->setTime(23, 59, 59);
            $diff_seconds = $current_time->diffInSeconds($yestoday_latest_time);
            $record = AttendanceModel::getAttendance($user->id, $yestoday_latest_time->toDateString());
            if (!$record) {
                // 前一日未签到
                admin_error(tr('Checked out failed'), tr('You must check in before checking out'));
                return redirect($request->url())->withInput();
            }

            $signout_time = $yestoday_latest_time;
            $session_key = sprintf('%s-update-sign-time', $current_time->toDateString());
            if (!session($session_key)) {
                $record->signin_time = Carbon::parse($record->signin_time)->subSeconds($diff_seconds)->toTimeString();
                $record->save();
                //避免重复签退导致签到时间被重复修改
                session()->put($session_key, 'yes');
            }
        }

        if ($record->signout_time) {
            if (!$record->canModifySignOut(AttendanceModel::CHECK_TYPE_OA)) {
                //如果不允许多次签退
                AuditModel::signOut($record->id, $user->id, false, 'Already signed');
                admin_error(tr('Checked out failed'),
                    tr('You have already checked out, time is: %s', $record->signout_time));
                return redirect($request->url())->withInput();
            }
        }

        $work_seconds = 0;
        if ($record->signin_time) {
            $work_seconds = $signout_time->diffInSeconds(Carbon::parse($record->signin_time, $timezone));
        }

        $project_id = $request->post('project_id') ?? 0;
        session()->put('oa_last_project_id', $project_id);

        $record->today_summary= $request->post('today_summary');
        $record->tomorrow_plan= $request->post('tomorrow_plan');
        $record->what_problem= $request->post('what_problem');
        $record->what_help= $request->post('what_help');
        $record->what_get= $request->post('what_get');
        $record->dining_duration= $request->post('dining_duration') ?? 0;
        $record->noonbreak_duration= $request->post('noonbreak_duration') ?? 0;
        $record->signout_time= $signout_time->toTimeString();
        $record->work_seconds= $work_seconds;
        $record->project_id= $project_id;
        $record->signout_type= AttendanceModel::CHECK_TYPE_OA;

        if (!$record->is_extrawork) {
            // 可能有白天上班中途申请加班的情况
            $record->is_extrawork = ApproveModel::dateApprovedForExtraWork($user->id, $current_time->toDateString());
        }

        $result = $record->updateAttendanceBeLeaveEarly();
        if (!$result) {
            AuditModel::signOut($record->id, $user->id, false, 'Failed to update database');
            admin_error(tr('Checked out failed'), tr("Failed to save database"));
            return redirect($request->url())->withInput();
        }

        AuditModel::signOut($record->id, $user->id, true);

        if (config("oa.attendance.summary.remove_noonbreak")) {
            $work_seconds = $work_seconds - $record->noonbreak_duration * 60;
        }
        $work_duration = round($work_seconds / 3600, 2);
        $rank_number = AttendanceModel::getSignOutRank($user->id, $signout_time->toDateString());

        if ($record->is_leave_early) {
            admin_warning(tr('Checked out successfully'),
                tr("Checked in time: %s, Checked out time: %s, Rank: %s, Duration: %s hours, Leave early: %s minutes",
                    $record->signin_time, $signout_time->toTimeString(), $rank_number, $work_duration,
                    ceil($record->leave_early_seconds / 60)));
        } else {
            $checkout_tip = tr("Checked in time: %s, Checked out time: %s, Rank: %s, Duration: %s hours",
                $record->signin_time, $signout_time->toTimeString(), $rank_number, $work_duration);
            $tomorrow_checkin_time = AttendanceModel::getSigninTimeBySignoutRules($signout_time->toTimeString());
            if ($tomorrow_checkin_time) {
                $tomorrow_checkin_tip = tr('Thanks for the hard work, you can check in before %s tomorrow',
                    $tomorrow_checkin_time->toTimeString());
                $checkout_tip = "$checkout_tip<br>$tomorrow_checkin_tip";
            }
            admin_success(tr('Checked out successfully'), $checkout_tip);
        }
    }

    public function confirmNotice(Request $request)
    {
        $notice_id = intval($request->post('notice_id'));
        $row = NoticeUserModel::confirmNotice($notice_id);
        if ($row) {
            $result = [
                'status' => 1,
                'message' => "确认成功，您将不会再看到该条通知",
            ];
        } else {
            $result = [
                'status' => 0,
                'message' => "确认失败，请反馈给当地行政处理，谢谢！",
            ];
        }
        return $result;
    }

    public static function AppAutoSignIn($user, $first_online_date_time)
    {
        $timezone = UserModel::getUserTimezone();
        if (!self::checkAutoSign($user, $timezone)) {
            return;
        }

        $dt = Carbon::now()->setTimezone($timezone);
        $record = AttendanceModel::firstOrNew([
            'user_id' => $user->id,
            'date' => $dt->toDateString()
        ]);

        if (empty($record->signin_time)) {
            //没有签到 上班时间自动打卡
            $workplace = $user->workplace;
            if ($dt->toTimeString() >= $workplace->signin_time && $dt->toTimeString() <= $workplace->signout_time) {
                $record->signin_time = Carbon::parse($first_online_date_time)->setTimezone($timezone)->toTimeString();
                $record->signin_type = AttendanceModel::CHECK_TYPE_AUTO;
                $record->updateAttendanceBeLater();
            }
        }
    }

    public static function AppAutoSignOut()
    {
        $dt = Carbon::now();
        $atts = AttendanceModel::where('date', $dt->toDateString())
            ->whereNotNull('signin_time')
            ->whereNull('signout_time')
            ->get();

        foreach ($atts as $att) {
            $user = UserModel::getUser($att->user_id);
            $timezone = UserModel::getUserTimezone($user->id);
            if (!self::checkAutoSign($user, $timezone)) {
                continue;
            }

            $timezone_dt = $dt->setTimezone($timezone);
            if ($timezone_dt->toTimeString() <= $user->workplace_info->signout_time) {
                continue;
            }

            $audit_info = AuditModel::where('user_id', $user->id)->where('type', 'online_user')->first(); //最后一次心跳时间
            $audit_dt = Carbon::parse($audit_info->updated_at);
            if ($audit_dt->toDateString() === $dt->toDateString() && $dt >= $audit_dt->addMinutes(20)) {
                // 同一天;不在线时间大于登录30分钟 ，以最后一次心跳为签退（是下班时间）时间
                $update_dt = Carbon::parse($audit_info->updated_at)->setTimezone($timezone);
                $att->signout_time = $update_dt->toTimeString();
                $att->signout_type = AttendanceModel::CHECK_TYPE_AUTO;
                $att->work_seconds = $update_dt->diffInSeconds(Carbon::parse($att->signin_time, $timezone));
                $att->updateAttendanceBeLeaveEarly();
            }
        }
    }

    private static function checkAutoSign($user, $timezone)
    {
        $dt = Carbon::now()->setTimezone($timezone);
        $is_workday = AttendanceWorkDaysModel::isWorkday($dt->toDateString());
        if ($is_workday) {
            // 工作日
            return true;
        }

        $approve_info = ApproveModel::getApproves($dt->year, $dt->month, $dt->day, ['id'], $user->id,
            [ApproveModel::EVENT_TYPE_EXTRAWORK]);
        if ($approve_info->isNotEmpty()) {
            // 申请了加班
            return true;
        }

        return false;
    }

}
