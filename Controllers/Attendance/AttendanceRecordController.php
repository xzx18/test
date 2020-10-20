<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApproveModel;
use App\Models\AttendanceModel;
use App\Models\AttendanceWorkDaysModel;
use App\Models\AuditModel;
use App\Models\Auth\OAPermission;
use App\Models\CalendarModel;
use App\Models\DingtalkReportListModel;
use App\Models\DingtalkReportTemplateModel;
use App\Models\UserModel;
use App\Models\WorkEventModel;
use App\Models\UserConfirmationModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Tab;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use Jenssegers\Agent\Agent;

/** 我的签到签退记录 */
class AttendanceRecordController extends Controller
{
    use HasResourceActions;
    private $left_content = '';
    private $right_content = '';

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        $current_date = UserModel::getUserTime();
        $current_user = UserModel::getUser();

        $requested_user_id = request('user');
        $requested_user_authed = false;

        $requested_month = request('month');
        $requested_month = $requested_month ?? $current_date->format('Y-m');

        $authorized_users = OAPermission::getAuthorizedUsers($current_user);

        $body = '';

        $getTabTitle = function ($title, $count) {
            return "{$title} <span class='badge badge-light'>{$count}</span>";
        };

        $getUserButton = function ($user_id, $user_name, $deleted_at) {
            $title = $deleted_at ? Carbon::parse($deleted_at)->toDateString() . '离职' : '';
            $name = $deleted_at ? "{$user_name}(离)" : $user_name;

            $body = <<<EOT
<button type="button" class="btn btn-light btn-select-user" title="{$title}" data-user-id="{$user_id}">$name</button>
EOT;
            return $body;
        };


//region 用户选择
        $tab = new Tab();
        $tab_has_tab = sizeof($authorized_users[OAPermission::AUTH_TYPE_USER]['All']) > 1;   //默认有当前用户

        $dt_month_start = Carbon::now()->subMonth()->startOfMonth();

        foreach ($authorized_users as $type => $authorized_user) {
            foreach ($authorized_user as $k => $v) {
                $btn_list = [];
                foreach ($v as $u) {
                    if ($u->deleted_at) {
                        $dt_leave = Carbon::parse($u->deleted_at);
                        if ($dt_leave < $dt_month_start) {
                            continue;
                        }
                    }

                    $btn_list[] = $getUserButton($u->id, $u->name, $u->deleted_at);
                    if (!$requested_user_authed && $requested_user_id) {
                        $requested_user_authed = $u->id == $requested_user_id;
                    }
                }

                $title = "<b>$k</b>";
                $tab->add($getTabTitle($title, sizeof($v)), implode(" ", $btn_list));
            }
        }

        if ($tab_has_tab) {
            $tab->view("admin.widgets.tab");
            $body .= $tab->render();
        }

        if (!$requested_user_id || !$requested_user_authed) {
            $requested_user_id = $current_user->id;
        }
        $requested_user_name = $requested_user_id != $current_user->id ? UserModel::getUser($requested_user_id)->name : $current_user->name;

//endregion

//region 日期选择
        $route = \request()->url();
        $box_attendance_title = <<<EOT
<table>
    <tr>
        <td>
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
                <input style="width: 100px;font-weight: bold; text-align: center;" type="text" value="{$requested_month}" class="form-control date date-picker">
            </div>
        </td>
        <td>
            <div style="margin-left: 20px;color: blue;" class="current-user-name">{$requested_user_name}</div>
        </td>
    </tr>
</table>
EOT;
        $box_attendance_content = <<<EOT
<div id="box-attendance-info" data-route='{$route}'><h1>Loading...</h1></div>
EOT;

        $box_attendance = new Box($box_attendance_title, $box_attendance_content);
        $box_attendance->style('primary')->view("admin.widgets.box");
        $body .= $box_attendance->render();

//endregion


//region JS代码
        $body .= <<<EOT
            <script>
                $(function () {
                     var current_user_id={$requested_user_id};
                     var current_user_name="{$requested_user_name}";
                     var current_date="{$requested_month}";

                     $(".btn-select-user[data-user-id='" + current_user_id +"']").addClass('btn-info activated');

                    //选择用户
                    $('.btn-select-user').click(function(e) {
                       $('.btn-select-user').removeClass('btn-info activated').css('color','black');
                       $(this).addClass('btn-info activated').css('color','white');
                       current_user_id=$(this).data("user-id");
                       current_user_name=$(this).html();
                       fetchAttendanceInfo();
                    });

                    fetchAttendanceInfo(current_user_id,current_date);

                    var datetimepicker_options={
                        format: 'YYYY-MM'
                    };
                    $('.content .box .box-title .date-picker').datetimepicker(datetimepicker_options).on("dp.change", function (e) {
                      current_date=$(this).val();
                       fetchAttendanceInfo();
                     });


                    function fetchAttendanceInfo() {
                        var box= $("#box-attendance-info");
                        NProgress.configure({ parent: '.content .box-header' });
                        NProgress.start();
                        var route=box.data("route");
                        var url=route + "/"+current_user_id+"/" + current_date;
                        $.get(url,function(response) {
                            box.html(response);
                            NProgress.done();
                        })
                    }
                })
            </script>
EOT;
//endregion

        return $content
            ->header(tr("Attendance Record"))
            ->description(' ')
            ->body($body);
    }

    public function getAttendanceRender($user_id, $from_date)
    {
        $body = '';
        /** @var Carbon $carbon_date */
        $carbon_date = Carbon::parse($from_date);


        //所有在家办公的工作记录情况
        $work_event_records = WorkEventModel::getEventsByMonth($carbon_date->year, $carbon_date->month, $user_id);
        $current_user = UserModel::getUser();
        $current_user_timezone = $current_user->timezone();


        if ($user_id == $current_user->id) {
            $user = $current_user;
        } else {
            $user = UserModel::getUser($user_id);
        }
        OAPermission::checkAuthorizedUser($user);

        $record_user_name = $user->name;
        /* 和区域相关的参数：规定的签到时间，签退时间 和 缓冲时长，默认30分钟 */
        $defined_signin_time = $user->workplace_info['signin_time'];
        $defined_signout_time = $user->workplace_info['signout_time'];
        $defined_buffer_duration = $user->workplace_info['buffer_duration'];


        $from_day = $carbon_date->startOfMonth()->day;
        $end_day = $carbon_date->endOfMonth()->day;

        $dingtalk_report_list = DingtalkReportListModel::getReport($user_id, [
            DingtalkReportTemplateModel::TYPE_DAILY_REPORT,
            DingtalkReportTemplateModel::TYPE_WEEKLY_REPORT
        ], $carbon_date->copy()->startOfMonth()->toDateString(), $carbon_date->copy()->endOfMonth()->toDateString());

        $records = AttendanceModel::getAttendances($user_id, $carbon_date->year, $carbon_date->month);

        //请假等记录
        $not_concerned_approve_types = [ApproveModel::EVENT_TYPE_COMPUTER_ALLOWANCE, ApproveModel::EVENT_TYPE_IPAD_ALLOWANCE];
        $approves = ApproveModel::getApproves($carbon_date->year, $carbon_date->month, null, [
            "event_type",
            "approve_type",
            "start_time",
            "end_time"
        ], $user_id, null, $not_concerned_approve_types);
        $aggregate_approves = ApproveModel::aggregateUserApproveByDate($approves);
        /*
        array:1 [▼
          0 => array:3 [▼
            "approve_type" => "调休"
            "start_time" => "2019-07-01 09:00:00"
            "end_time" => "2019-07-01 12:00:00"
          ]
        ]
        */
        $attendance_ids = collect($records)->pluck('id')->toArray();
        $audits = AuditModel::getAttendanceModifies($attendance_ids);


        $agent = new Agent();
        $lang_of_weeks = CalendarModel::getTranslatedWeeks();

        if ($agent->isDesktop()) {
            $calendar = new CalendarModel();
            $calendar->orderWithLanguages = $lang_of_weeks;
        }
        $target = "_blank";
        if (isLoadedFromMacClient()) {
            $target = "_self";
        }

        for ($i = $from_day; $i <= $end_day; $i++) {
            $carbon_date = Carbon::createFromDate($carbon_date->year, $carbon_date->month, $i);
            $date = $carbon_date->toDateString();

            $is_leave_date = $user->deleted_at && Carbon::parse($user->deleted_at)->toDateString() == $date;

            $dingtalk_reports = DingtalkReportListModel::getReportByDate($dingtalk_report_list, $date);
            $has_dingtalk_report = sizeof($dingtalk_reports) > 0;
            $dingtalk_report_content = DingtalkReportListModel::getDingtalkReportContent($dingtalk_reports);

            $aggregate_approve = $aggregate_approves[$date] ?? null;

            /**
             * 获取box对象的匿名方法
             * @param $content
             * @return Box
             */
            $getBox = function ($content) use ($date, $lang_of_weeks, $carbon_date) {
                $title = $date . " " . $lang_of_weeks[$carbon_date->dayOfWeek];
                $box = new Box($title, $content);
                if ($carbon_date->isSaturday() || $carbon_date->isSunday()) {
                    $box->style("default");
                } else {
                    $box->style("primary");
                }
                $box->solid();
                return $box;
            };

            /** @var Collection $work_event */
            $work_event = $work_event_records->where('date', $date);

            if (isset($records[$date]) || $work_event->isNotEmpty() || $has_dingtalk_report || $aggregate_approve) {
                $has_report = false;
                $record = $records[$date] ?? null;
                $day_titles = [];

                if ($record) {
                    if (empty($record['signin_time']) || $record['signin_time'] == Carbon::createFromTime(0, 0,
                            0)->toTimeString()) {
                        $day_titles[] = tr("Uncheck In");
                    } else {
                        $record_obj = AttendanceModel::where('id',$record['id'])->first();
                        $is_later = $record_obj->is_later;
                        if ($is_later) {
                            $day_titles[] = tr("Be Later");
                        }
                    }

                    if (empty($record['signout_time']) || $record['signout_time'] == Carbon::createFromTime(0, 0,
                            0)->toTimeString()) {
                        $day_titles[] = tr("Uncheck Out");
                    } else {
                        $record_obj = AttendanceModel::where('id',$record['id'])->first();
                        $is_leaveearly = $record_obj->is_leave_early;
                        if ($is_leaveearly) {
                            $day_titles[] = tr("Leave Early");
                        }
                    }


                    $edit_url = \request()->getBaseUrl() . "record/{$record['id']}/edit";
                    $edit_title = $carbon_date->format('d');
                    if (!empty($day_titles)) {
                        $edit_title = sprintf('%s (%s)', $edit_title, implode(", ", $day_titles));
                    }

                    if (OAPermission::isHr() || OAPermission::isAdministrator()) {
                        $day_title = sprintf('<a href="%s" target="%s"><span class="badge badge-%s">%s</span></a>',
                            $edit_url, $target, !empty($day_titles) ? 'danger' : 'success', $edit_title);
                    } else {
                        $day_title = sprintf('<span class="badge badge-%s">%s</span>',
                            !empty($day_titles) ? 'danger' : 'success', $edit_title);
                    }


                    $detail = '';
                    if ($current_user->id == $user_id || OAPermission::canViewAttendanceRecordDetail()) {
                        $detail = $this->getAttendanceDetail($record, $dingtalk_report_content, $audits,
                            $current_user_timezone, false, $has_report);
                    }
                } else {
                    $detail = '';
                    if ($dingtalk_report_content) {
                        $detail = '<div class="popup-tooltips">' . $dingtalk_report_content . '</div>';
                    }
                    $day_title = $carbon_date->format('d');
                }
                if (!isset($records[$date])) {
                    if (OAPermission::isHr() || OAPermission::isAdministrator()) {
                        $create_url = \request()->getBaseUrl() . "record/create/?user_id={$user_id}&date={$carbon_date->toDateString()}";
                        $day_title = '<a href="' . $create_url . '"  target="' . $target . '"><span class="badge badge-light">' . $carbon_date->format('d') . '</span></a>';
                    }
                }

                if ($has_dingtalk_report) {
                    $dingtalk_urls = '';
                    foreach ($dingtalk_reports as $dingtalk_report) {
                        $dingtalk_report_url = route('dingtalkreport.list.show', $dingtalk_report->id);
                        $image_path = $dingtalk_report->template_id == DingtalkReportTemplateModel::TYPE_WEEKLY_REPORT ? '/image/dingtalk-report-weekly.svg' : '/image/dingtalk-report-daily.svg';
                        $dingtalk_urls .= ('<a href="' . $dingtalk_report_url . '" style="float:left;"><img src="' . $image_path . '" width="24" height="24"></a>');
                    }
                    $day_title = $dingtalk_urls . $day_title;
                }

                if ($aggregate_approve) {
                    /* 一天有可能请几次假
                     array (size=2)
                      0 =>
                        array (size=3)
                          'approve_type' => string '事假' (length=6)
                          'start_time' => string '2019-11-08 09:00:00' (length=19)
                          'end_time' => string '2019-11-08 12:00:00' (length=19)
                      1 =>
                        array (size=3)
                          'approve_type' => string '事假' (length=6)
                          'start_time' => string '2019-11-08 13:30:00' (length=19)
                          'end_time' => string '2019-11-08 18:00:00' (length=19)
                     * */
                    $title_tip = '';
                    $approve_type = '';
                    foreach ($aggregate_approve as $item) {
                        $title_tip .= implode(" ", $item);
                        $title_tip .= "\n";
                        $approve_type .= $item['approve_type'];
                        $approve_type .= " ";
                    }
                    $day_title = "$day_title <span class='badge badge-purple font-weight-normal' style='cursor: default;' title='{$title_tip}'>{$approve_type}</span>";
                }

                if (!$has_report) {
                    $day_title = "$day_title <span class='badge badge-dark font-weight-normal' style='cursor: default;'>无日报</span>";
                }

                if ($is_leave_date) {
                    $day_title = "$day_title <span class='badge badge-gray font-weight-normal' style='cursor: default;'>离职</span>";
                }

                $summary = $this->getAttendanceSummary($record, $audits, $work_event, $current_user_timezone);
                $attrs = 'data-toggle="tooltip" data-placement="{data-placement}" title="' . htmlentities($detail) . '"';
                if ($agent->isDesktop()) {
                    $calendar->addEvent($carbon_date, $day_title ?? "", $summary, $attrs);
                } else {
                    $body .= $getBox('<div class="mobile-event">' . $summary . $detail . '</div>')->render();
                }
            } else {
                if (OAPermission::isHr() || OAPermission::isAdministrator()) {
                    $create_url = \request()->getBaseUrl() . "record/create/?user_id={$user_id}&date={$carbon_date->toDateString()}";
                    $day_title = '<a href="' . $create_url . '"  target="' . $target . '"><span class="badge badge-light">' . $carbon_date->format('d') . '</span></a>';
                    if ($is_leave_date) {
                        $day_title = "$day_title <span class='badge badge-gray font-weight-normal' style='cursor: default;'>离职</span>";
                    }
                    $calendar->addEvent($carbon_date, $day_title ?? "", null, null);
                }
            }

            if (!isset($records[$date])) {
                $body .= $getBox(tr("Absence"))->render();
            }
        }

        $title = sprintf("%s %s", $record_user_name, $from_date);
        if ($agent->isDesktop()) {
            $approve_html = $this->getApprovesHtml($approves, $records);
            if ($approve_html) {
                $approve_html = "<span style='background-color: yellow; color: black; border-radius: 3px; font-size: 12px; padding: 3px;'>$approve_html</span>";
            }
            $body = $calendar->render($carbon_date);

            $body .= <<<EOT
<script>
    $(function () {
        if (typeof $('.tooltip') != 'undefined') {
            $('.tooltip').remove();
        }
        $('[data-toggle="tooltip"]').tooltip({
            boundary: 'window',
            container: 'body',
            html: true
        });
        $('.current-user-name').html("{$title} {$approve_html}");
    })

    $('.btn-confirm-attendance').click(function(e) {
        var url = $(this).data('url');
        var title = "确定当月({$from_date})考勤记录无误吗?<br/>手动或自动确认后，因个人原因需要修改请先<br/><br/>~乐~捐~";
        swal({
          title: title,
          type: 'question',
          showCancelButton: true,
          confirmButtonColor: '#DD6B55',
          cancelButtonColor: '#999999',
          cancelButtonText: '取消',
          confirmButtonText: '确定',
          buttonsStyling: true
        }).then(function(isConfirm) {
            if (isConfirm.value === true) {
                $.ajax({
                    method: 'post',
                    url: url,
                    data: {
                        _token:LA.token,
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
            }
        });
    });

</script>
<style>
    .btn-group-sm > .btn, .btn-sm {
        padding: 2px 5px;
        font-size: 9px;
        line-height: 1.5;
        border-radius: 3px;
    }
</style>
EOT;

            $confirm_status = UserConfirmationModel::getAttendanceConfirmStatus($user_id, $from_date);
            if ($confirm_status == UserConfirmationModel::STATUS_NOT_CONFIRMED) {
                $salary_url = sprintf('%s?user=%s&month=%s', route('financial.personal.calculation'), $user_id, $from_date);
                $salary_url = "<a href='{$salary_url}'><span style='vertical-align: bottom;font-size: 20px;color: red;text-decoration: underline'>工资试算</span></a>";
                $confirm_url = route('attendance.confirm', $from_date);
                $body .= <<<EOT
<br/>
<div>
    <img src="/image/infoAlert.svg" width="36" height="36" />{$salary_url}<br/>
    <span style="color: #DD4B39;vertical-align: bottom;font-size: 20px">请检查并确认本人当月考勤（签到、签退、请假、调休、加班、迟到、早退等）。如果有异常的考勤记录，请先联系当地行政同事修正。<br/>
    每月3日24点会自动确认上月考勤（五一、十一等假期顺延）。手动或者自动确认后再因个人原因需要修改考勤及相关工资的，请先~乐~捐~</span>
    <button type="button" class="btn btn-warning btn-confirm-attendance" title="确认考勤" data-url='{$confirm_url}'>
        <span style="margin-left: 20px;margin-right: 20px" >确认考勤</span>
    </button>
</div>
EOT;
            } elseif (in_array($confirm_status,
                [UserConfirmationModel::STATUS_MANUAL_CONFIRMED, UserConfirmationModel::STATUS_AUTO_CONFIRMED])) {
                $way = $confirm_status == UserConfirmationModel::STATUS_AUTO_CONFIRMED ? '自动' : '手动';
                $body .= <<<EOT
<br/>
<div>
    <img src="/image/infoTips.svg" width="36" height="36" />
    <span style="color: green;vertical-align: bottom;font-size: 20px">当月考勤已 <b>{$way}确认</b>。</span>
</div>
EOT;
            }


        }
        return $body;
    }

    private function getAttendanceDetail($record, $dingtalk_report_content, $audits, $timezone, $htmlentities = false, &$has_report = false)
    {
        $today_plan = nl2br($record['today_plan']);
        $today_summary = nl2br($record['today_summary']);
        $tomorrow_plan = nl2br($record['tomorrow_plan']);
        $what_problem = nl2br($record['what_problem']);
        $what_help = nl2br($record['what_help']);
        $what_get = nl2br($record['what_get']);

        $tr_TodaysPlan = tr("Today's Plan");
        $tr_TomorrowsPlan = tr("Tomorrow's Plan");
        $tr_TodaysWorkSummary = tr("Today's Work Summary");
        $tr_Problem = tr("Problem");
        $tr_Help = tr("Help");
        $tr_Learn = tr("Learn");
        $tr_ModifyHistory = tr("Modify History");


        $html = '';
        // 优先显示钉钉日志
        if ($dingtalk_report_content) {
            $html .= $dingtalk_report_content;
        } else {
            if (!empty($today_plan)) {
                $html .= <<<EOT
<b class="tooltip-title">{$tr_TodaysPlan}</b>
<p>{$today_plan}</p>
EOT;
            }
            if (!empty($today_summary)) {
                $html .= <<<EOT
<b class="tooltip-title">{$tr_TodaysWorkSummary}</b>
<p>{$today_summary}</p>
EOT;
            }
            if (!empty($tomorrow_plan)) {
                $html .= <<<EOT
<b class="tooltip-title">{$tr_TomorrowsPlan}</b>
<p>{$tomorrow_plan}</p>
EOT;
            }


            if (!empty($what_problem)) {
                $html .= <<<EOT
<b class="tooltip-title">{$tr_Problem}</b>
<p>{$what_problem}</p>
EOT;
            }

            if (!empty($what_help)) {
                $html .= <<<EOT
<b class="tooltip-title">{$tr_Help}</b>
<p>{$what_help}</p>
EOT;
            }

            if (!empty($what_get)) {
                $html .= <<<EOT
<b class="tooltip-title">{$tr_Learn}</b>
<p>{$what_get}</p>
EOT;
            }
        }

        if ($html) {
            $has_report = true;
        }

        if ($audits) {
            $audit_records = $audits->where('attendance_id', $record['id']);
            $audit_record_count = $audit_records->count();
            if ($audit_record_count > 0) {
                $modify_records = [];
                foreach ($audit_records as $r) {
                    $human = AuditModel::getAttendanceModifyHumanContent($r->content, false);
                    if ($human) {
                        $human = "<br>$human";
                    }
                    $dt = Carbon::parse($r->created_at);
                    $dt->setTimezone($timezone);
                    $who = $r->modify_user_name;
                    $tr_CreatedOrModifiedBy = tr("Modified by %s", $who);
                    if ($r->event_type = AuditModel::ATTENDANCE_TYPE_CREATE) {
                        $tr_CreatedOrModifiedBy = tr("Created by %s", $who);
                    }
                    $modify_records [] = sprintf("<span style='color: darkgoldenrod'>%s %s</span>%s", $dt->toDateTimeString(), $tr_CreatedOrModifiedBy, $human);
                }

                if ($modify_records) {
                    $audit_content = true;
                    $html .= sprintf('<b class="tooltip-title">%s</b><p>%s</p>', $tr_ModifyHistory, implode("<br>", $modify_records));
                }

            }
        }

        if ($html) {
            $html = '<div class="popup-tooltips">' . $html . '</div>';
            if ($htmlentities) {
                $html = htmlentities($html);
            }
        }

        return $html;
    }

    /**
     * @param $record
     * @param null $audits 审计事件
     * @param null $work_event 在家办公事件
     * @return string
     */
    private function getAttendanceSummary($record, $audits = null, Collection $work_event = null, $current_user_timezone = 'UTC')
    {
        $events = '<div class="attendance-details">';

        if ($record) {
            if (config('oa.attendance.record.display.sourcetype')) {
                $signin_type = AttendanceModel::getAttendanceCheckTypeHumanFromTypeId($record['signin_type']);
                if ($signin_type) {
                    $signin_type = <<<eot
<span class="badge badge-warning font-weight-normal">{$signin_type}</span>
eot;
                }

                $signout_type = AttendanceModel::getAttendanceCheckTypeHumanFromTypeId($record['signout_type']);
                if ($signout_type) {
                    $signout_type = <<<eot
<span class="badge badge-warning font-weight-normal">{$signout_type}</span>
eot;
                }
            }

            $tr_CheckIn = tr("Check In");
            $tr_CheckOut = tr("Check Out");

            $signout_font_style = '';
            $checkin_time = AttendanceModel::getSigninTimeBySignoutRules($record['signout_time']);
            if ($checkin_time) {
                $signout_font_style = ' style="color:blue"';
            }

            $events .= <<<EOT
<div class="signin-time"><span class="key">{$tr_CheckIn}：</span><span class="value">{$record['signin_time']}</span>{$signin_type}</div>
<div class="signout-time"><span class="key">{$tr_CheckOut}：</span><span class="value" {$signout_font_style}>{$record['signout_time']}</span>{$signout_type}</div>
EOT;

            if (!empty($record['dining_duration'])) {
                $tr_DinnerDuration = tr("Dinner: %s minutes", $record['dining_duration']);
                $events .= "<div class='dining'>$tr_DinnerDuration</div>";
            }
            if (!empty($record['noonbreak_duration'])) {
                $tr_NookbreakDuration = tr("Nook Break: %s minutes", $record['noonbreak_duration']);
                $events .= "<div class='noonbreak'>$tr_NookbreakDuration</div>";
            }
            if (!empty($record['remark'])) {
                $events .= <<<EOT
<div class="remark">{$record['remark']}</div>
EOT;
            }
            if ($audits) {
                $audit_records = $audits->where('attendance_id', $record['id']);
                $audit_record_count = $audit_records->count();
                if ($audit_record_count > 0) {
                    $has_content = false;
                    foreach ($audit_records as $r) {
                        if ($r->content) {
                            $has_content = true;
                        }
                    }
                    $audit_record_count = tr("Modify Count: %s", $audit_record_count);
                    if ($has_content) {
                        $audit_record_count = "<b>$audit_record_count</b>";
                    }
                    $events .= "<div class='audit'><span class='badge badge-light' style='font-weight: normal; cursor: default'>{$audit_record_count}</span></div>";
                }
            }

            if (false && $record['signin_time'] && $record['signout_time']) {
                // 暂不需要“确认考勤”的功能
                if (Carbon::parse($record['date'])->greaterThanOrEqualTo(Carbon::parse('2020-01-01'))) {
                    if ($record['confirmed']) {
                        $confirm_time = Carbon::parse($record['confirmed_at'])->setTimezone($current_user_timezone);
                        $confirm_at = sprintf(tr('Confirmed At'), $confirm_time);
                        $events .= "<div class='confirm-container-{$record['id']}'><span class='badge badge-success font-weight-normal' style='font-size: 7px;'>$confirm_at</span></div>";
                    } else {
                        $events .= '<div class="confirm-container-' . $record['id'] . '"><button class="btn btn-primary btn-sm btn-confirm-attendance-record" data-record-id="' . $record['id'] . '">' . tr('Confirm Attendance') . '</button></div>';
                    }

                }
            }
        } else {
            //如果有签到签退，就不计算远程办公的时长，否则会重复
            if ($work_event && $work_event->isNotEmpty()) {
                $total_seconds = $work_event->sum('work_seconds');
                $_desc_ = "远程办公：" . gmdate("H:i:s", $total_seconds);
                $events .= <<<EOT
<div class="remark" style="color: blue; background-color: greenyellow;">$_desc_</div>
EOT;
            }
        }

        $events .= '</div>';
        return $events;
    }

    private function getApprovesHtml($approves, $records)
    {
        if ($approves && $approves->isNotEmpty()) {
            $items = [];
            foreach ($approves as $approve) {
                $type = $approve['approve_type'];
                $end_time = Carbon::parse($approve['end_time']);
                $start_time = Carbon::parse($approve['start_time']);
                $date = $start_time->toDateString();

                $dingtalk_diff = $end_time->diffInSeconds($start_time);

                $r = $records[$date] ?? null;
                if ($r) {
                    $items[$type]['oa_seconds'] = ($items[$type]['oa_seconds'] ?? 0) + Carbon::parse($r['signout_time'])->diffInSeconds(Carbon::parse($r['signin_time']));
                }
                $items[$type]['dingtalk_seconds'] = ($items[$type]['dingtalk_seconds'] ?? 0) + $dingtalk_diff;
                $items[$type]['count'] = ($items[$type]['count'] ?? 0) + 1;
            }
            $htmls = [];
            foreach ($items as $type => $data) {
                $dingtalk_duration = formatSecondsToHours($data['dingtalk_seconds'] ?? 0);
                $oa_duration = formatSecondsToHours($data['oa_seconds'] ?? 0);
                $count = $data['count'];
                if ($type == '加班') {
                    $htmls[] = "<b>{$type} $count\次</b> : $dingtalk_duration(申请) -> $oa_duration(实际)";
                } else {
                    $htmls[] = "<b>{$type} $count\次</b> : $dingtalk_duration";
                }
            }
            return implode(", ", $htmls);
        }
        return '';
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

        $record = AttendanceModel::find($id);
        if ($record) {

            $user = $record->user;
            $content->header(tr("Details"))->description($user->name);
            if (!OAPermission::authorizedUsersContain(null, $user)) {
                $content->body(OAPermission::ERROR_HTML);
            } else {
                $content->body($this->detail($id));
            }
        }
        return $content;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(AttendanceModel::findOrFail($id));
        $show->id('ID');
        $show->date(tr("Date"));
        $show->signin_time(tr("Check In"));
        $show->signout_time(tr("Check Out"));
        $show->defined_signin_time(tr("Defined Check In Time"));
        $show->defined_signout_time(tr("Defined Check Out Time"));
        $show->buffer_duration(tr("Buffer Duration"));
        $show->today_plan(tr("Today's Plan"));
        $show->today_summary(tr("Today's Work Summary"));
        $show->tomorrow_plan(tr("Tomorrow's Plan"));
        $show->what_problem(tr("Problem"));
        $show->what_help(tr("Help"));
        $show->what_get(tr("Learn"));
        $show->dining_duration(tr("Dinner Duration"));
        $show->noonbreak_duration(tr("Noon Break Duration"));
        $show->remark(tr("Remark"));

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
        $content->header('Edit')->description(tr("Note: all your operations will be recorded"));
        $record = AttendanceModel::find($id);
        if ($record) {
            $user = $record->user;
            if (!OAPermission::authorizedUsersContain(null, $user)) {
                $content->body(OAPermission::ERROR_HTML);
            } else {
                $content->body($this->form()->edit($id));
            }
        }
        return $content;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $getDisabledTextInput = function ($val) {
            return '<input type="text" style="width:250px;"  value="' . $val . '" class="form-control" disabled>';
        };
        $lang_of_weeks = CalendarModel::getTranslatedWeeks();
        $date = null;
        try {
            $date = Carbon::parse(\request('date'));
        } catch (Exception $exception) {

        }

        $user_id = intval(\request('user_id'));
        $form = new Form(new AttendanceModel);


        $type = 'edit';
        if ($date && $user_id > 0) {
            $type = 'create';
        }
        if ($type == 'create') {
            $week = $lang_of_weeks[$date->dayOfWeek];
            $user = UserModel::getUser($user_id);
            $form->html($getDisabledTextInput($user_id), 'ID');
            $form->html($getDisabledTextInput ($user->name), tr("Username"));
            $form->html($getDisabledTextInput ($date->toDateString() . " ($week)"), tr("Date"));
            $form->hidden('user_id')->value($user_id);
            $form->hidden('date')->value($date->toDateString());
            //服务器上面会使用 /admin/attendances/record/create 作为action地址，是错误的
            //本地环境不会有这个问题，原因不得而知
            $form->setAction('/admin/attendances/record');
            $success = new MessageBag(['title' => '创建成功', 'message' => '']);
        } else {
            $form->text('id', 'ID')->readonly();
            $form->display('user.name', '姓名');
            $form->display('date', '日期');
            $success = new MessageBag(['title' => '修改成功', 'message' => '']);
        }

        if (OAPermission::canModifyAttendanceRecordTime()) {
            $form->time('signin_time', tr("Check In"));
            $form->time('signout_time', tr("Check Out"));
            $form->switch('ignore_belater', '不计迟到早退')->help('勾选后，将不算迟到/早退；有时候周五活动，会导致考勤为早退的情况');
            $form->switch('is_extrawork', '当天申请了加班')->help('勾选后，将按加班计算工时，否则不计工时。工作日无需考虑此项。');
        } else {
            $form->display('signin_time', tr("Check In"));
            $form->display('signout_time', tr("Check Out"));
        }


        $form->textarea('today_plan', tr("Today's Plan"));
        $form->textarea('today_summary', tr("Today's Work Summary"));
        $form->textarea('tomorrow_plan', tr("Tomorrow's Plan"));
        $form->textarea('what_problem', tr("Problem"))->rows(2);
        $form->textarea('what_help', tr("Help"))->rows(2);
        $form->textarea('what_get', tr("Learn"))->rows(2);

        if (OAPermission::canModifyAttendanceRecordDuration()) {
            $form->number('dining_duration', tr("Dinner Duration"))->help(tr("Minute(s)"));
            $form->number('noonbreak_duration', tr("Noon Break Duration"))->help(tr("Minute(s)"));
        } else {
            $form->display('dining_duration', tr("Dinner Duration"))->help(tr("Minute(s)"));
            $form->display('noonbreak_duration', tr("Noon Break Duration"))->help(tr("Minute(s)"));
        }
        if (OAPermission::canModifyAttendanceRecordRemark()) {
            $form->textarea('remark', tr("Remark"));
        } else {
            $form->display('remark', tr("Remark"));
        }
        $form->display('created_at', 'Created At');
        $form->display('updated_at', 'Updated At');
        $form->hidden('work_seconds');
        $form->saving(function (Form $form) use ($type, $success) {
            if ($form->signout_time && $form->signin_time) {
                $form->work_seconds = Carbon::parse($form->signout_time)->diffInSeconds(Carbon::parse($form->signin_time));
            } else {
                $form->work_seconds = 0;
            }

            $check_keys = array_keys(AuditModel::$ATTENDANCE_MODIFY_ITEMS);
            $dirty_data = [];
            foreach ($check_keys as $check_key) {
                $new_val = (string)$form->$check_key;
                if ($new_val == 'off') {
                    $new_val = "0";
                }
                if ($new_val == 'on') {
                    $new_val = "1";
                }
                $old_val = (string)$form->model()->$check_key;
//					echo ("$check_key: $new_val -> $old_val<br>");
                if ($new_val != $old_val) {
//						dd($new_val . "<>" . $old_val . "<br>");
                    $dirty_data[$check_key] = [
                        'from' => $old_val,
                        'to' => $new_val
                    ];
                }
            }
//			dd($dirty_data);

            if ($type != 'create') {    //修改
                if (!empty($dirty_data)) {
                    AuditModel::modifyAttendance($form->model()->id, $form->model()->user_id, $dirty_data);
                }
            } else {
                $exist_record = AttendanceModel::where('date', $form->date)->where('user_id', $form->user_id)->first();
                if ($exist_record) {
                    $redirect_url = \request()->url() . "/{$exist_record->id}";
                    return redirect($redirect_url)->with(compact('success'));
                    die();
                }
            }
        });
        $form->saved(function (Form $form) use ($type, $user_id, $success) {
            $id = $form->model()->id;
            if($form->signin_time) { //修改后更新迟到问题
                $record = AttendanceModel::where('id' ,$id)->first();
                AttendanceModel::updateAttendanceBeLater($record);
            }
            if($form->signout_time) { //修改后更新迟到问题
                $record = AttendanceModel::where('id' ,$id)->first();
                AttendanceModel::updateAttendanceBeLeaveEarly($record);
            }

            if ($type == 'create') {
                AuditModel::modifyAttendance($id, $user_id, null, 0, AuditModel::ATTENDANCE_TYPE_CREATE);
                $redirect_url = \request()->url() . "/$id";
                return redirect($redirect_url)->with(compact('success'));
            } else {
                return redirect(\request()->url() . '/edit')->with(compact('success'));
            }
        });

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
            ->header(trans('admin.create'))
            ->description(' ')
            ->body($this->form());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new AttendanceModel());

//        $grid->id('ID')->sortable();
//        $grid->signin_time('签到');
//        $grid->signout_time('签退');
//        $grid->today_plan('今日工作计划');
//        $grid->today_summary('今日工作总结');
        return $grid;
    }
}
