<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApproveModel;
use App\Models\Auth\OAPermission;
use App\Models\CalendarModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use function request;

class AttendanceStatusController extends Controller
{
    use HasResourceActions;


    public function index(Content $content, $date = null)
    {
        $is_mobile = isLoadedFromMobile();

        //以当前用户的时区为准
        $tz = UserModel::getUserTimezone();
        $china_tz = config("app.timezone_china");
        if (empty($date)) {
            $carbon_date = Carbon::now($tz);
        } else {
            $carbon_date = Carbon::parse($date, $tz);
        }

        $body = '';
        $route = request()->url();

        $from_day = $carbon_date->startOfMonth()->day;
        $end_day = $carbon_date->endOfMonth()->day;

        $lang_of_weeks = CalendarModel::getTranslatedWeeks();

        $calendar = new CalendarModel($tz);
        $calendar->orderWithLanguages = $lang_of_weeks;
        $approves = ApproveModel::getApproves($carbon_date->year, $carbon_date->month, null, ['*'], 0, $event_type = [0, 1, 2, 3]);

        $event_items = [];

        $all_users = UserModel::getAllUsers();
        $fnGetUser = function ($user_id) use ($all_users) {
            return $all_users->where('id', $user_id)->first();
        };

        for ($i = $from_day; $i <= $end_day; $i++) {
            $carbon_date = Carbon::createFromDate($carbon_date->year, $carbon_date->month, $i, $tz);
            $filter_items = $approves->filter(function ($item) use ($carbon_date, $tz, $china_tz) {
                $start_dt = Carbon::parse($item->start_time, $china_tz);
                $start_dt->setTimezone($tz);
                $start_time = $start_dt->startOfDay();
                $end_dt = Carbon::parse($item->end_time, $china_tz);
                $end_dt->setTimezone($tz);
                $end_time = $end_dt->startOfDay();
                return $carbon_date->startOfDay()->gte($start_time) && $carbon_date->startOfDay()->lte($end_time);
            });

            $event_title = '';
            $event_count = $filter_items->count();

            $tr_HalfDay = tr("Half Day");
            $tr_FullDay = tr("Full Day");
            $tr_Details = tr("Details");
            $tr_UpdatedAt = trans("admin.updated_at");
            $tr_Type = tr("Type");

            if ($event_count > 0) {
                $events = '';
                /** @var ApproveModel $item */
                foreach ($filter_items as $item) {
                    $start_dt = Carbon::parse($item->start_time, $china_tz);
                    $end_dt = Carbon::parse($item->end_time, $china_tz);
                    $start_dt->setTimezone($tz);
                    $end_dt->setTimezone($tz);
                    if ($start_dt->day == $end_dt->day && $start_dt->day == $i) {
                        $harf_day = $end_dt->diffInMinutes($start_dt) < 5 * 60;
                        if ($harf_day) {

                            $harf_day = "<span style='color: white;background-color: #a99595'>{$tr_HalfDay}</span> ";
                        }
                        $from_to = <<<eot
({$start_dt->format('H:i')}~{$end_dt->format('H:i')} {$harf_day})
eot;
                    } elseif ($start_dt->day == $i) {
                        $from_to = "( " . sprintf(tr("From Time %s"), $start_dt->format('H:i')) . " )";
                    } elseif ($end_dt->day == $i) {
                        $from_to = "( " . sprintf(tr("To Time %s"), $end_dt->format('H:i')) . " )";
                    } else {
                        $from_to = sprintf("( %s )", $tr_FullDay);
                    }
                    $event_type_title = ApproveModel::getEventTypeHuman($item->event_type);
                    $event_type_style = ApproveModel::getEventTypeStyle($item->event_type);

                    $attrs = '';
                    if (OAPermission::has('oa.attendance.status.details')) {
//                        $department = implode(", ", $item->user->departments->pluck('name')->toArray());
                        $updated_at = Carbon::parse($item->updated_at);
                        $updated_at->setTimezone($china_tz);
                        $mark = json_decode($item->mark, true);
                        $marks = '';
                        foreach ($mark as $k => $v) {
                            $marks .= "$k: $v<br>";
                        }
                        $detail = <<<eot
<div class="popup-tooltips">
    <b class="tooltip-title">{$tr_Type}：</b>
    <p>{$event_type_title}, {$item->approve_type}</p>
    <b class="tooltip-title">{$tr_Details}：</b>
    <p>{$marks}</p>
    <b class="tooltip-title">{$tr_UpdatedAt}：</b>
    <p>{$updated_at}</p>
</div>
eot;
                        $tooltip_placement = 'left';
                        if ($is_mobile) {
                            $tooltip_placement = 'top';
                        }
                        $attrs = 'data-toggle="tooltip" data-placement="' . $tooltip_placement . '" title="' . htmlentities($detail) . '"';
                    }
                    $from_to = sprintf('<span style="font-size: 8px;">%s</span>', $from_to);
                    $username = $fnGetUser($item->user_id);
                    if ($username) {
                        $events .= <<<eot
<span class="badge badge-{$event_type_style}" {$attrs} style="font-weight: normal; cursor: default;font-size: 11px;">{$event_type_title} {$username->name} {$from_to}</span>
eot;
                    }
                }
                $event_items[$carbon_date->toDateString()] = $events;
                $calendar->addEvent($carbon_date, $event_title, $events);
            }
        }

        $calendar_html = '';
        if (!$is_mobile) {
            $calendar_html = $calendar->render($carbon_date, $carbon_date->format('Y-m'));
        } else {
            foreach ($event_items as $date => $item) {
                $week = $lang_of_weeks[Carbon::parse($date)->dayOfWeek];
                $box = new Box("$date ($week)", $item);
                $calendar_html .= $box->render();
            }
        }
        $calendar_html .= <<<eot
<script>
$('[data-toggle="tooltip"]').tooltip({
            boundary: 'window',
            container: 'body',
            html: true
        });
</script>
eot;


        if (request()->isXmlHttpRequest() && !empty($date)) {
            return $calendar_html;
        }

        $box_attendance_title = <<<EOT
<table>
    <tr>
        <td>
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
                <input style="width: 100px;font-weight: bold; text-align: center;" type="text" value="{$carbon_date->format('Y-m')}" class="form-control date date-picker">
            </div>
        </td>
        <td>

        </td>
    </tr>
</table>
EOT;
        $box_attendance_content = <<<EOT
<div id="box-approve-info" data-route='{$route}'>{$calendar_html}</div>
<script>
    $(function () {
        if(typeof $('.tooltip') !='undefined'){
            $('.tooltip').remove();
        }

        var current_date="{$carbon_date->format('Y-m')}";
        $('.content .box .box-title .date-picker').datetimepicker({format: 'YYYY-MM' }).on("dp.change", function (e) {
            current_date=$(this).val();
            fetchApproveInfo();
        });

        function fetchApproveInfo() {
            var box= $("#box-approve-info");
            NProgress.configure({ parent: '.content .box-header' });
            NProgress.start();
            var route=box.data("route");
            var url=route +"/" + current_date;
            $.get(url,function(response) {
                box.html(response);
                NProgress.done();
            })
        }
    })
</script>
<style>
.wx-calendar td{
    height: 20px!important;
}
</style>
EOT;

        $box_attendance = new Box($box_attendance_title, $box_attendance_content);
        $box_attendance->style('primary')->view("admin.widgets.box");
        $body .= $box_attendance->render();

//        $extrawork_body = "";
//        foreach ($extrawork_items as $date => $value) {
//            $week = $lang_of_weeks[Carbon::parse($date)->dayOfWeek];
//            $extrawork_box = new Box("$date $week", $value);
//            $extrawork_body .= $extrawork_box->render();
//        }
//        $tab_extrawork = new Tab();
//        $tab_extrawork->add('加班状态', $extrawork_body);
//        $body = $tab_extrawork->render();

        return $content
            ->header(tr("Attendance Status"))
            ->description(tr("Attendance Status Description"))
            ->body($body);
    }

}
