<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceModel;
use App\Models\AttendanceSummaryModel;
use App\Models\Auth\OAPermission;
use App\Models\Department;
use App\Models\UserModel;
use App\Models\WorkplaceModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Tab;
use Encore\Admin\Widgets\Table;
use Illuminate\Support\Collection;
use function request;

/** 考勤统计 */
class AttendanceSummaryController extends Controller
{
    use HasResourceActions;

    public function getPersonalSummaryBak($user_id, $year)
    {
        $avg_info = AttendanceSummaryModel::getSummary(0, $year);

        $user = UserModel::getUser($user_id);
        $launch_duration = 0;
        if (config('oa.attendance.summary.remove_noonbreak')) {
            $launch_duration = $user->workplace_info->lunch_duration;
        }

        $records = AttendanceModel::getAttendances($user_id, $year, null, null,
            [
                'date',
                'signin_time',
                'signout_time',
                'dining_duration',
                'noonbreak_duration',
                'defined_signin_time',
                'defined_signout_time',
                'buffer_duration'
            ]
        );

        $table_headers = [
            tr("Month"),
            tr("Attendance"),
            tr("Total Duration"),
            "在家工作时长",
            tr("Uncheck In"),
            tr("Uncheck Out"),
            tr("Be Later"),
            tr("Leave Early"),
            tr("Dinner Duration"),
            tr("Noon Break Duration"),
            tr("Person Average Duration"),
            tr("Staff Average Duration")
        ];

        //############ 统计
        //月度统计
        $table_month_rows = [];
        for ($month = 1; $month <= 12; $month++) {
            $month_dt = Carbon::createFromDate($year, $month, 1);

            $attendance_count = 0;        //正常签到签退次数 （签到签退都有时间）
            $total_work_duration = 0;     //工作总时长(减去 午休时长，晚餐时长，午休超时时长)
            $belater_count = 0;           //迟到次数
            $leaveearly_count = 0;        //早退次数

            $uncheckin_count = 0;   //未签到次数
            $uncheckout_count = 0;  //未签退次数

            $dining_duration = 0;             //晚餐总时长（分钟）
            $nookbreak_duration = 0;          //午休超时总时长（分钟）
            $total_homework_duration = 0;     //在家工作总时长

            foreach ($records as $date => $record) {
                $day_dt = Carbon::parse($date);
                if ($day_dt->month == $month_dt->month) {

                    $dining_duration += intval($record['dining_duration']);
                    $nookbreak_duration += intval($record['noonbreak_duration']);

                    if ($record['signin_time'] && $record['signout_time']) {
                        $attendance_count++;
                        $checkin_time = Carbon::parse("$date {$record['signin_time']}");
                        $checkout_time = Carbon::parse("$date {$record['signout_time']}");
                        $total_work_duration += ($checkout_time->diffInMinutes($checkin_time) - $launch_duration - $record['noonbreak_duration'] - $record['dining_duration']);

                        $previous_date = Carbon::parse($date)->addDays(-1);
                        $previous_date_str = $previous_date->toDateString();
                        $previous_signout_time = null;
                        if (isset($records[$previous_date_str])) {
                            $previous_signout_time = $records[$previous_date_str]['signout_time'];
                        } else {
                            $previous_attendance_records = AttendanceModel::getAttendances($user_id, $previous_date->year, $previous_date->month, $previous_date->day);
                            if (isset($previous_attendance_records[$previous_date_str])) {
                                $previous_signout_time = $previous_attendance_records[$previous_date_str]['signout_time'];
                            }
                        }

                        //迟到次数
                        $is_belater = $record->is_later;
                        if ($is_belater) {
                            $belater_count++;
                        }
                        $is_leaveearly = $record->is_leave_early;
                        //早退次数
                        if ($is_leaveearly) {
                            $leaveearly_count++;
                        }
                    } else {
                        if (!$record['signin_time']) {
                            $uncheckin_count++;
                        }
                        if (!$record['signout_time']) {
                            $uncheckout_count++;
                        }
                    }
                }
            }

            $total_work_duration = round($total_work_duration / 60, 2);
            $person_avg_work_duration = 0; //平均工作时长（小时）
            if ($attendance_count > 0) {
                $person_avg_work_duration = round($total_work_duration / $attendance_count, 2);
            }

            $company_avg_work_duration = 0;
            if (isset($avg_info['m'][$month_dt->toDateString()])) {
                $company_avg_work_duration = round(($avg_info['m'][$month_dt->toDateString()][AttendanceSummaryModel::AVG_WORK_SECONDS] - $launch_duration * 60) / 3600, 3);
            }

            $table_month_rows[] = [
                sprintf("%02d", $month),
                $attendance_count,
                $total_work_duration,
                $uncheckin_count,
                $uncheckout_count,
                $belater_count,
                $leaveearly_count,
                $dining_duration,
                $nookbreak_duration,
                $person_avg_work_duration,
                $company_avg_work_duration,
            ];
        }

        $getTableRowWithUnit = function ($rows, array $colume = []) {
            for ($i = 0; $i < sizeof($rows); $i++) {
                for ($j = 0; $j < sizeof($rows[$i]); $j++) {
                    if (in_array($j, [3, 4, 5, 6]) && $rows[$i][$j] > 0) {
                        $rows[$i][$j] = sprintf('<span class="badge badge-danger">%s %s</span>', $rows[$i][$j], $colume[$j]);
                    } else {
                        $rows[$i][$j] = sprintf('%s %s', $rows[$i][$j], $colume[$j]);
                    }

                }
            }
            return $rows;
        };

        $box_solid = false;

        $column_langs = [
            '',
            tr("Day(s)"),
            tr("Hour(s)"),
            tr("Hour(s)"),
            tr("Time(s)"),
            tr("Time(s)"),
            tr("Time(s)"),
            tr("Time(s)"),
            tr("Minute(s)"),
            tr("Minute(s)"),
            tr("Hour(s) per Day"),
            tr("Hour(s) per Day")
        ];
        $table_month = new Table($table_headers, $getTableRowWithUnit($table_month_rows, $column_langs));
        $box_month = new Box(tr("Monthly"), $table_month);
        $box_month->style("warning");
        if ($box_solid) {
            $box_month->solid();
        }

        //季度统计
        $table_quarter_rows = [];

        foreach ($table_month_rows as $month_index => $row) {
            $valid_month_index = $month_index + 1;
            $dt = Carbon::createFromDate($year, $valid_month_index, 1);
            $quarter = $dt->quarter - 1;
            if (isset($table_quarter_rows[$quarter])) {
                for ($i = 0; $i < sizeof($row); $i++) {
                    $table_quarter_rows[$quarter][$i] = $table_quarter_rows[$quarter][$i] + $row[$i];
                }
            } else {
                $table_quarter_rows[$quarter] = $row;
            }

            //第一列的值改成第几季度（1,2,3,4）
            $table_quarter_rows[$quarter][0] = $quarter + 1;

            //统计平均时间
            if ($valid_month_index % Carbon::MONTHS_PER_QUARTER == 0) {

                if (isset($avg_info['q'][$dt->quarter])) {
                    $table_quarter_rows[$quarter][sizeof($row) - 1] = round(($avg_info['q'][$dt->quarter][AttendanceSummaryModel::AVG_WORK_SECONDS] - $launch_duration * 60) / 3600, 2);
                } else {
                    $table_quarter_rows[$quarter][sizeof($row) - 1] = 0;
                }


                $table_quarter_rows[$quarter][sizeof($row) - 2] = 0;    //个人平均时长设置为0小时

                $total_attendance_count = $table_quarter_rows[$quarter][1]; //该季度的有效考勤次数
                if ($total_attendance_count > 0) {
                    //总考勤时长 除以 有效考勤次数，即为 平均工作时长
                    $table_quarter_rows[$quarter][sizeof($row) - 2] = round($table_quarter_rows[$quarter][2] / $total_attendance_count, 2);
                }
            }
        }


        $table_headers[0] = tr("Quarter");
        $table_quarter = new Table($table_headers, $getTableRowWithUnit($table_quarter_rows, $column_langs));
        $box_quarter = new Box(tr("Quarterly"), $table_quarter);
        $box_quarter->style("success");
        if ($box_solid) {
            $box_quarter->solid();
        }


        //年度统计
        $table_year_rows = [];
        foreach ($table_month_rows as $month_index => $row) {
            if (isset($table_year_rows[0])) {
                for ($i = 0; $i < sizeof($row); $i++) {
                    $table_year_rows[0][$i] = $table_year_rows[0][$i] + $row[$i];
                }
            } else {
                $table_year_rows[0] = $row;
            }

            $table_year_rows[0][0] = $year;

            //统计平均时间
            if (($month_index + 1) % Carbon::MONTHS_PER_YEAR == 0) {
                $table_year_rows[0][sizeof($row) - 2] = 0;        //个人平均时长设置为0小时
                $total_attendance_count = $table_year_rows[0][1]; //该季度的有效考勤次数
                if ($total_attendance_count > 0) {
                    //总考勤时长 除以 有效考勤次数，即为 平均工作时长
                    $table_year_rows[0][sizeof($row) - 2] = round($table_year_rows[0][2] / $total_attendance_count, 2);
                }
                if (isset($avg_info['y'][$year])) {
                    $table_year_rows[0][sizeof($row) - 1] = round(($avg_info['y'][$year][AttendanceSummaryModel::AVG_WORK_SECONDS] - $launch_duration * 60) / 3600, 2);
                }

            }

        }

        $table_headers[0] = tr("Year");
        $table_year = new Table($table_headers, $getTableRowWithUnit($table_year_rows, $column_langs));
        $year_title_tips = sprintf("<i style='font-size: 12px'> (%s: %s %s)</i>", tr("Minimum required working hours"), config("oa.attendance.summary.required_work_duration_year"), tr("Hour(s)"));


        $box_year = new Box(tr("Yearly") . "$year_title_tips", $table_year);
        $box_year->style("primary");
        $box_year->view("admin.widgets.box");
        if ($box_solid) {
            $box_year->solid();
        }

        $summary_body = $box_year . $box_quarter . $box_month;
        return $summary_body;
    }

    public function getPersonalSummary($user_id, $year)
    {
        return $this->getSummary(AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL, $user_id, $year);
    }

    private function getSummary($summary_type, $organization_id, $year)
    {
        $fnGetTable = function ($organization_id, $summary_type, $data_type, $year) {
            $allow_allowance = false;
            $where = [
                'summary_type' => $summary_type,
                'type' => $data_type,
                'year' => $year,
            ];

            switch ($summary_type) {
                case AttendanceSummaryModel::TYPE_SUMMARY_DEPARTMENT:
                    $where['department_id'] = $organization_id;
                    break;
                case    AttendanceSummaryModel::TYPE_SUMMARY_WORKPLACE:
                    $where['workplace_id'] = $organization_id;
                    break;
                case AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL:
                    $where['user_id'] = $organization_id;
                    /** @var Collection $_user_ */
                    $_user_ = UserModel::where('id', $organization_id)->first(['allow_allowance']);
                    $allow_allowance = $_user_->allow_allowance ?? false;
                    break;
            }

            $orderBy = 'id';
            switch ($data_type) {
                case AttendanceSummaryModel::TYPE_YEARLY:
                    $orderBy = 'year';
                    break;
                case AttendanceSummaryModel::TYPE_QUARTERLY:
                    $orderBy = 'quarter';
                    break;
                case AttendanceSummaryModel::TYPE_MONTHLY:
                    $orderBy = 'month';
                    break;
            }

            $rows = AttendanceSummaryModel::where($where)->orderBy($orderBy, 'asc')->get();

            $staff_rows = null;

            if ($summary_type == AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL) {
                $staff_rows = AttendanceSummaryModel::where([
                    'summary_type' => AttendanceSummaryModel::TYPE_SUMMARY_STAFF,
                    'year' => $year,
                ])->orderBy($orderBy, 'asc')->get();
            }


            $fnGetStaffInfo = function ($row) use ($staff_rows, $summary_type, $organization_id, $year) {
                $item = $staff_rows->where('type', $row['type'])->where('year', $row['year'])->where('quarter', $row['quarter'])->where('month', $row['month'])->first();
                return $item->toArray();
            };

            $table_rows = [];
            foreach ($rows as $row) {
                $date = '#';
                switch ($data_type) {
                    case AttendanceSummaryModel::TYPE_YEARLY:
                        $date = $row->year;
                        break;
                    case AttendanceSummaryModel::TYPE_QUARTERLY:
                        $date = "Q" . $row->quarter;
                        break;
                    case AttendanceSummaryModel::TYPE_MONTHLY:
                        $date = sprintf("%02d", $row->month);
                        break;
                }
                if ($summary_type == AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL) {
                    $staff_info = $fnGetStaffInfo($row);
                    $staff_avg_work_seconds = round($staff_info['avg_work_seconds'] / 3600, 2);
                    $personal_avg_work_seconds = round($row->avg_work_seconds / 3600, 2);
                    $flag = ' <span style="color: green; font-weight: bold;">↓</span>';
                    if ($personal_avg_work_seconds > $staff_avg_work_seconds) {
                        $flag = ' <span style="color: red;font-weight: bold;">↑</span>';
                    }
                    $table_rows[] = [
                        $date,
                        $row->valid_record_count . ' ' . tr('Day(s)'),                          //考勤数
                        round($row->total_work_seconds / 3600, 2) . ' ' . tr('Hour(s)'),        //总时长
                        round($row->total_homework_seconds / 3600, 2) . ' ' . tr('Hour(s)'),    //总时长
                        $row->avg_signin_time,
                        $row->avg_signout_time,
                        $row->belater_count > 0 ? sprintf('<span class="badge badge-danger">%s</span>', $row->avg_belater_count) : 0,
                        $row->leaveearly_count > 0 ? sprintf('<span class="badge badge-danger">%s</span>', $row->avg_leaveearly_count) : 0,
                        $row->total_dinner_minutes . ' ' . tr('Minute(s)'),
                        $row->total_noonbreak_minutes . ' ' . tr('Minute(s)'),
                        $personal_avg_work_seconds . ' ' . tr('Hour(s)'),
                        $staff_avg_work_seconds . ' ' . tr('Hour(s)') . $flag,
                    ];

                    if ($allow_allowance) {
                        $table_rows[sizeof($table_rows) - 1][] = $row->lunch_allowance_count;
                        $table_rows[sizeof($table_rows) - 1][] = $row->dinner_allowance_count;
                        $table_rows[sizeof($table_rows) - 1][] = $row->traffic_allowance_count;
                    }
                    $table_rows[sizeof($table_rows) - 1][] = $row->updated_at;
                } else {
                    $table_rows[] = [
                        $date,
                        $row->avg_signin_time,
                        $row->avg_signout_time,
                        round($row->avg_work_seconds / 3600, 2) . ' ' . tr('Hour(s)'),
                        $row->avg_belater_count > 0 ? sprintf('<span class="badge badge-danger">%s</span>', $row->avg_belater_count) : 0,
                        $row->avg_leaveearly_count > 0 ? sprintf('<span class="badge badge-danger">%s</span>', $row->avg_leaveearly_count) : 0,
                        $row->user_count,
                        $row->valid_record_count,
                        $row->belater_count,
                        $row->leaveearly_count,
                        $row->updated_at,
                    ];
                }
            }

            if ($summary_type == AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL) {
                $table_headers = [
                    '#',
                    tr("Attendance"),
                    tr("Total Duration"),
                    tr("Total Homework Duration"),
                    tr('Average Check In Time'),
                    tr('Average Check Out Time'),
                    tr("Be Later"),
                    tr("Leave Early"),
                    tr("Dinner Duration"),
                    tr("Noon Break Duration"),
                    tr("Person Average Duration"),
                    tr("Staff Average Duration"),
                ];
                if ($allow_allowance) {
                    $table_headers[] = '午餐补';
                    $table_headers[] = '晚餐补';
                    $table_headers[] = '车补';
                }
                $table_headers[] = trans('admin.updated_at');
//					dd($table_headers);
            } else {
                $table_headers = [
                    '#',
                    tr('Average Check In Time'),
                    tr('Average Check Out Time'),
                    tr('Average Work Duration'),
                    tr('Average Belater Count'),
                    tr('Average Leaveearly Count'),
                    tr('User Count'),
                    tr('Record Count'),
                    tr('Total Belater Count'),
                    tr('Total Leaveearly Count'),
                    trans('admin.updated_at')
                ];
            }


            $table = new Table($table_headers, $table_rows);
            return $table;
        };


        $box_year = new Box(tr('Yearly'), $fnGetTable($organization_id, $summary_type, AttendanceSummaryModel::TYPE_YEARLY, $year));
        $box_year->style("primary");


        $box_quarter = new Box(tr('Quarterly'), $fnGetTable($organization_id, $summary_type, AttendanceSummaryModel::TYPE_QUARTERLY, $year));
        $box_quarter->style("success");


        $box_month = new Box(tr('Monthly'), $fnGetTable($organization_id, $summary_type, AttendanceSummaryModel::TYPE_MONTHLY, $year));
        $box_month->style("warning");

        $body = $box_year->render() . $box_quarter->render() . $box_month->render();
        return $body;
    }

    public function personalIndex(Content $content)
    {
        $current_user = UserModel::getUser();
        $current_date = UserModel::getUserTime();
        $authorized_users = OAPermission::getAuthorizedUsers($current_user);
        $route = request()->url();
        $body = $this->getUserNav($current_user, $current_date, $authorized_users);
        $body .= <<<EOT
            <script>
                $(function () {
                     var current_user_id={$current_user->id};
                     var current_user_name="{$current_user->name}";
                     var current_date="{$current_date->year}";

                     $(".btn-select-user[data-user-id='" + current_user_id +"']").addClass('btn-info activated');

                    //选择用户
                    $('.btn-select-user').click(function(e) {
                        $('.btn-select-user').removeClass('btn-info activated').css('color','black');
                       $(this).addClass('btn-info activated').css('color','white');
                       current_user_id=$(this).data("user-id");
                       current_user_name=$(this).html();
                       fetchSummaryInfo();
                    });

                    fetchSummaryInfo(current_user_id,current_date);

                    var datetimepicker_options={
                        format: 'YYYY'
                    };
                   $('.content .box .box-title .date-picker').datetimepicker(datetimepicker_options).on("dp.change", function (e) {
                      current_date=$(this).val();
                       fetchSummaryInfo();
                     });


                    function fetchSummaryInfo() {
                        $('.current-user-name').html(current_user_name + " " + current_date + " 的考勤统计");
                        var box= $("#box-attendance-summary");
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
<style>
.box-body table tr:hover{
    background: #f3f5f8;
    color:blue;
}
</style>
EOT;

//			$duration_tips = "总工作时长 和 平均工作时长 <b style='color: yellow'>未计算</b> 中午午休时间 " . $current_user->workplace_info->lunch_duration . "分钟";
//			if (config("oa.attendance.summary.remove_noonbreak")) {
//				$duration_tips = "总工作时长 和 平均工作时长 <b style='color: yellow'>已扣除</b> 中午午休时间" . $current_user->workplace_info->lunch_duration . "分钟";
//			}
        $box_content = sprintf('<ul>%s</ul>', tr("Attendance Summary Tips"));
        $box_tips = new Box(tr("Remark"), $box_content);

        $box_attendance_title = <<<EOT
<table>
    <tr>
        <td>
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
                <input style="width: 100px;font-weight: bold; text-align: center;" type="text" value="{$current_date->year}" class="form-control date date-picker">
            </div>
        </td>
        <td>
            <div style="margin-left: 20px;" class="current-user-name">{$current_user->name}</div>
        </td>
    </tr>
</table>
EOT;
        $box_attendance_content = <<<EOT
<div id="box-attendance-summary" data-route='{$route}'><h1>Loading...</h1></div>
EOT;


        $box_summary = new Box($box_attendance_title, $box_attendance_content);
        $box_summary->style('primary')->view("admin.widgets.box");
        $body .= $box_summary->render();

        $body .= $box_tips;

        return $content
            ->header(tr("Attendance Summary"))
            ->description(' ')
            ->body($body);
    }

    private function getUserNav($current_user, $current_date, $authorized_users)
    {
        $getTabTitle = function ($title, $count) {
            return "{$title} <span class='badge badge-light'>{$count}</span>";
        };

        $getButton = function ($user_id, $user_name) {
            $body = <<<EOT
<button type="button" class="btn btn-light btn-select-user" data-user-id="{$user_id}">{$user_name} </button>
EOT;
            return $body;
        };

        $body = '';
        //region 用户选择
        $tab = new Tab();
        $tab_has_tab = sizeof($authorized_users[OAPermission::AUTH_TYPE_USER]['All']) > 1;   //默认有当前用户
        foreach ($authorized_users as $type => $authorized_user) {
            foreach ($authorized_user as $k => $v) {
                $btn_list = [];
                foreach ($v as $u) {
                    $btn_list[] = $getButton($u->id, $u->name);
                }
                $title = "<b>$k</b>";
                $tab->add($getTabTitle($title, sizeof($v)), implode(" ", $btn_list));
            }
        }
        if ($tab_has_tab) {
            $tab->view("admin.widgets.tab");
            $body .= $tab->render();
        }

        return $body;
    }

    public function organizationIndex(Content $content)
    {
        $current_user = UserModel::getUser();
        $current_department = $current_user->departments->first();
        $current_workplace = $current_user->workplace_info;

        $current_date = UserModel::getUserTime();
        $route = request()->url();
        $body = $this->getOrganizationNav();


        $TYPE_DEPARTMENT = AttendanceSummaryModel::TYPE_SUMMARY_DEPARTMENT;
        $TYPE_WORKPLACE = AttendanceSummaryModel::TYPE_SUMMARY_WORKPLACE;


        $body .= <<<EOT
            <script>
                $(function () {
                     var current_department_id="{$current_department->id}";
                     var current_department_name="{$current_department->name}";
                     var current_workplace_id="{$current_workplace->id}";
                     var current_workplace_name="{$current_workplace->title}";

                     var current_year="{$current_date->year}";
                     var datetimepicker=$('.content .box .box-title .date-picker');

                     initActivatedButton(current_department_id);
                     initActivatedButton(current_workplace_id);


                     fetchSummaryInfo(current_year,"{$TYPE_DEPARTMENT}",current_department_id,current_department_name);
                     fetchSummaryInfo(current_year,"{$TYPE_WORKPLACE}",current_workplace_id,current_workplace_name);
                     swithTab("{$TYPE_DEPARTMENT}");


                     $('.nav-tabs li a').click(function(e) {
                          var organization_type=  $(this).find('span').data('type');
                          swithTab(organization_type);
                     });


                    $('.tab-content .tab-pane button').click(function(e) {
                       $('.tab-content .active button').removeClass('btn-info activated').css('color','black');
                       $(this).addClass('btn-info activated').css('color','white');
                       var organization_type=$(this).data("type");
                       var organization_id=$(this).data("id");
                       var organization_name=$(this).html();
                       $('.current-user-name').html(organization_name + " " + datetimepicker.val() + " 的考勤统计");
                       fetchSummaryInfo(current_year,organization_type,organization_id,organization_name);
                    });


                   datetimepicker.datetimepicker({format: 'YYYY'}).on("dp.change", function (e) {
                       current_year=$(this).val();
                       var organization_type=datetimepicker.data("organization_type");
                       var box=$("#box-"+organization_type+"-attendance-summary");
                       var organization_info=box.data("organization");

                       if(organization_info.current_year != current_year){
                            $('.current-user-name').html(organization_info.organization_name + " " + current_year + " 的考勤统计");
                            fetchSummaryInfo(current_year,organization_type,organization_info.organization_id,organization_info.organization_name);
                       }
                   });

                  function initActivatedButton(organization_id) {
                    $(".tab-content .tab-pane button[data-id='" + organization_id +"']").addClass('btn-info activated');
                  }

                   function  swithTab(organization_type) {
                        $(".box-attendance-summary").hide();
                        $("#box-"+organization_type+"-attendance-summary").show();

                        var box=$("#box-"+organization_type+"-attendance-summary");
                        var organization_info=box.data("organization");
                        datetimepicker.val(organization_info.current_year);
                        datetimepicker.data("organization_type",organization_type);
                        $('.current-user-name').html(organization_info.organization_name + " " + organization_info.current_year + " 的考勤统计");
                   }

                    function fetchSummaryInfo(current_year,organization_type,organization_id,organization_name) {
                        NProgress.configure({ parent: '.content .box-header' });
                        NProgress.start();
                        var box=$("#box-"+organization_type+"-attendance-summary");
                        box.data("organization",{
                            current_year:current_year,
                            organization_type:organization_type,
                            organization_id:organization_id,
                            organization_name:organization_name
                        });
                        var url=box.data("route") + "/" + organization_type + "/" +organization_id  + "/" + current_year;
                        $.get(url,function(response) {
                            box.html(response);
                            NProgress.done();
                        });
                    }
                })
            </script>
<style>
    .tab-content .tab-pane button{
        margin: 2px;
    }
    .box-body table tr:hover{
        background: #f3f5f8;
        color:blue;
    }
</style>
EOT;

        $box_attendance_title = <<<EOT
<table>
    <tr>
        <td>
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
                <input style="width: 100px;font-weight: bold; text-align: center;" type="text" value="{$current_date->year}" class="form-control date date-picker">
            </div>
        </td>
        <td>
            <div style="margin-left: 20px;" class="current-user-name">{$current_user->name}</div>
        </td>
    </tr>
</table>
EOT;
        $box_attendance_content = <<<EOT
<div id="box-department-attendance-summary" class="box-attendance-summary" data-route='{$route}'><h1>Loading...</h1></div>
<div id="box-workplace-attendance-summary" class="box-attendance-summary" data-route='{$route}' style="display: none;"><h1>Loading...</h1></div>
EOT;


        $box_summary = new Box($box_attendance_title, $box_attendance_content);
        $box_summary->style('primary')->view("admin.widgets.box");
        $body .= $box_summary->render();


        return $content
            ->header(tr("Attendance Summary"))
            ->description(' ')
            ->body($body);
    }

    public function getOrganizationNav()
    {
        $getTabTitle = function ($title, $count) {
            return "{$title} <span class='badge badge-light'>{$count}</span>";
        };


        $department_info = $this->getDepartmentNav();
        $workplace_info = $this->getWorkplaceNav();


        $tab = new Tab();
        $tab->add($getTabTitle(sprintf('<span data-type="%s">%s</span>', AttendanceSummaryModel::TYPE_SUMMARY_DEPARTMENT, tr("Department")), $department_info['count']), $department_info['html']);
        $tab->add($getTabTitle(sprintf('<span data-type="%s">%s</span>', AttendanceSummaryModel::TYPE_SUMMARY_WORKPLACE, tr("Workplace")), $workplace_info['count']), $workplace_info['html']);


        $tab->view("admin.widgets.tab");
        $body = $tab->render();

        return $body;
    }

    private function getDepartmentNav()
    {
        $departments = Department::getNavTree();
        $exclude_ids = [Department::TOPEST_DEPARTMENT_ID];
        $html = $departments->render(AttendanceSummaryModel::TYPE_SUMMARY_DEPARTMENT, $exclude_ids);

        return [
            'count' => $departments->count - sizeof($exclude_ids),
            'html' => sprintf('%s<div style="clear: both;"></div>', $html),
        ];
    }

    private function getWorkplaceNav()
    {
        $places = WorkplaceModel::all();
        $html = '';
        foreach ($places as $place) {
            $html .= sprintf("<button type='button' class='btn btn-light' data-type='%s' data-id='%s' >%s</button>", AttendanceSummaryModel::TYPE_SUMMARY_WORKPLACE, $place->id, $place->title);
        }

        return [
            'count' => $places->count(),
            'html' => sprintf('%s<div style="clear: both;"></div>', $html),
        ];
    }

    public function getDepartmentSummary($department_id, $year)
    {
        return $this->getSummary(AttendanceSummaryModel::TYPE_SUMMARY_DEPARTMENT, $department_id, $year);
    }

    public function getWorkplaceSummary($workplace_id, $year)
    {
        return $this->getSummary(AttendanceSummaryModel::TYPE_SUMMARY_WORKPLACE, $workplace_id, $year);
    }
}
