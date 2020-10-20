<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceModel;
use App\Models\AttendanceSummaryModel;
use App\Models\CalendarModel;
use App\Models\Department;
use App\Models\UserModel;
use App\Models\WorkplaceModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Tab;

class AttendanceTopController extends Controller
{
    use HasResourceActions;

    public function index(Content $content)
    {
        $date = UserModel::getUserTime()->toDateString();

//			//调试
//			$dt = Carbon::parse($date)->addDays(-2);
//			$date = $dt->toDateString();

        $tr_NoRanking = tr("No Ranking");
        $tr_Ranking = tr("Ranking");

        $route = "/admin/attendances/top";

        $tab = new Tab();
        $titles = [
            AttendanceSummaryModel::TYPE_TOP_PERSONAL_DAY => sprintf('%s (%s)', tr('Personal'), tr('Daily')),
            AttendanceSummaryModel::TYPE_TOP_PERSONAL_MONTH => sprintf('%s (%s)', tr('Personal'), tr('Monthly')),
            AttendanceSummaryModel::TYPE_TOP_PERSONAL_QUARTER => sprintf('%s (%s)', tr('Personal'), tr('Quarterly')),
            AttendanceSummaryModel::TYPE_TOP_PERSONAL_YEAR => sprintf('%s (%s)', tr('Personal'), tr('Yearly')),

            AttendanceSummaryModel::TYPE_TOP_DEPARTMENT_MONTH => sprintf('%s (%s)', tr('Department'), tr('Monthly')),
            AttendanceSummaryModel::TYPE_TOP_DEPARTMENT_QUARTER => sprintf('%s (%s)', tr('Department'), tr('Quarterly')),
            AttendanceSummaryModel::TYPE_TOP_DEPARTMENT_YEAR => sprintf('%s (%s)', tr('Department'), tr('Yearly')),

            AttendanceSummaryModel::TYPE_TOP_WORKPLACE_MONTH => sprintf('%s (%s)', tr('Workplace'), tr('Monthly')),
            AttendanceSummaryModel::TYPE_TOP_WORKPLACE_QUARTER => sprintf('%s (%s)', tr('Workplace'), tr('Quarterly')),
            AttendanceSummaryModel::TYPE_TOP_WORKPLACE_YEAR => sprintf('%s (%s)', tr('Workplace'), tr('Yearly')),
        ];

        $colors = [
            'personal' => 'red',
            'department' => 'blue',
            'workplace' => 'green'
        ];

        foreach ($titles as $type => $title) {
            $tabcontent = $this->getTabContent($type, $date);
            foreach ($colors as $_type => $_color) {
                if (str_contains($type, $_type)) {
                    $title = sprintf('<span style="color: %s;">%s</span>', $_color, $title);
                }
            }
            $tab->add($title, $tabcontent);
        }

        $tab->view('admin.widgets.tab');

        $_quarterly_ = AttendanceSummaryModel::TYPE_QUARTERLY;


        $body = <<<eot
			<script>
				$(function () {
					$(document.body).on('click','.box-attendance-top .fa', function(e) {
						e.preventDefault();
						console.log("hook a click");
						var href=$(this).attr("href");
						fetchAttendanceTopInfo(href);
					 });
					
					
					$('.content .date-picker').datetimepicker().on("dp.show", function (e) {
						console.log("show");
						var _type = e.target.dataset.type;
						var _cycle = e.target.dataset.cycle;
						if (_cycle =='{$_quarterly_}') {
							$('.datepicker table tr td span').css({ "width": "100%" });
							$(".month").each(function (index, element) {
								$(element).html( "Q" + (index+1));
								if (index > 3) $(element).hide();
							});
						}
					}).on("dp.change", function (e) {
						console.log("change");
						var _date = e.target.value;
						var _type = e.target.dataset.type;
						console.log("Date: " + _date + ", Type: " + _type);
						$('.datepicker-dropdown').remove();
						fetchAttendanceTopInfo(null,_type,_date);
					});
					
				
					$('.content .date-picker').each(function(e){
						var _date=$(this).val();
						var _type = $(this).data("type");
						console.log("Date: " + _date + ", Type: " + _type);
						fetchAttendanceTopInfo(null,_type,_date);
					});
					
					
					function fetchAttendanceTopInfo(url,type,date) {
						if(!url){
							url= "{$route}/" + type + "/" + date;
						}else{
							var pathname = new URL(url).pathname;
							console.log("pathname: " + pathname);	//pathname: /admin/attendances/top/personal-day/2018-12-05
							var parts=pathname.split('/');
							console.log(parts);
							type=parts[4];
							date=parts[5];
						}
						console.log("fetch -> date: " + date + ", type: " + type + ", url: " + url);
					
				
						var box= $("#box-top-"+type);
						NProgress.configure({ parent: '#box-top-' + type });
						NProgress.start();
				
						$.get(url,function(response) {
							box.html(response);
							NProgress.done();
							if(type.indexOf("personal") != -1){
							    var elMyRankTips=$('#my-rank-' + type);
								var elMyRankRow=$("#current-user-" + type).closest("tr");
								if(elMyRankRow.length>0){
									var rank=elMyRankRow.find(".ranking").html();
									elMyRankTips.html("{$tr_Ranking}: " + rank).removeClass("badge-danger").addClass("badge-success");
									elMyRankRow.css({"background":"lightyellow"});
								}else{
									elMyRankTips.html("{$tr_NoRanking}").addClass("badge-danger");
								}
							}
						})
					}
				});
			</script>
eot;
        $body .= $tab->render();
        return $content
            ->header(tr("Leaderboards"))
            ->description(' ')
            ->body($body);
    }

    function getTabContent($type, $date)
    {
        $datepicker_format = 'YYYY-MM-DD';
        $cycle = AttendanceSummaryModel::TYPE_DAILY;

        $min_date = sprintf('data-date-min-date="%s"', '2010-01-01');
        $max_date = sprintf('data-date-max-date="%s"', Carbon::now()->toDateString());

        if (in_array($type, AttendanceSummaryModel::TOP_MONTHLY_ITEMS)) {
            $datepicker_format = 'YYYY-MM';
            $cycle = AttendanceSummaryModel::TYPE_MONTHLY;
        }
        if (in_array($type, AttendanceSummaryModel::TOP_QUARTERLY_ITEMS)) {
            $datepicker_format = 'YYYY-MM';
            $cycle = AttendanceSummaryModel::TYPE_QUARTERLY;
            $year = date('Y');
            $max_date = sprintf('data-date-max-date="%s-04-01"', $year);
            $dt = Carbon::parse($date);
            $date = sprintf('%s-%s-1', $dt->year, $dt->quarter);
        }

        if (in_array($type, AttendanceSummaryModel::TOP_YEARLY_ITEMS)) {
            $datepicker_format = 'YYYY';
            $cycle = AttendanceSummaryModel::TYPE_YEARLY;
        }
        $tr_Loading = tr("Loading");
        $html = <<<eot
<table>
    <tr>
        <td>
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
                <input style="width: 120px;font-weight: bold; text-align: center;" type="text" value="{$date}" class="form-control date date-picker date-picker-{$type}"  data-type="{$type}" data-cycle="{$cycle}" data-date-format="{$datepicker_format}" {$min_date} {$max_date}>
            </div>
        </td>
        <td>
        <span class="badge badge-danger" id="my-rank-{$type}" style="font-size: larger;margin-left: 10px"></span>
        </td>
    </tr>
</table>
<br>
<div class="box-attendance-top" id="box-top-{$type}"><h1>{$tr_Loading}</h1></div>
eot;
        return $html;
    }

    public function getAttendanceTop($type, $date)
    {
        $dt = Carbon::parse($date);
        /**
         * @param $model
         * @param $title
         * @return Grid
         */
        $fnGetCommonGrid = function ($model, $title) {
            $grid = new Grid($model);
            $grid->setTitle($title);
            $grid->disableFilter();
            $grid->disableRowSelector();
            $grid->disableExport();
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->paginate(200);
            $grid->disablePagination();
            $grid->tools->disableRefreshButton();
            $grid->tools->disableFilterButton();
            $grid->tools->disableBatchActions();

            $grid->rows(function (Grid\Row $row) {
                return $row->number;
            });
            $grid->top(tr("Ranking"))->display(function ($val) {
                return $val;
            });
            $grid->setView('admin.grid.table-attendance-top');
            return $grid;
        };

        /**
         * @param Grid $grid
         * @param $summary_type
         * @return mixed
         */
        $fnGetTopGrid = function ($grid, $summary_type, $top_type = null) {
            switch ($summary_type) {
                case AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL:
                    $items = UserModel::getAllUsers();
                    $current_user = UserModel::getUser();
                    $grid->user_id(tr('Username'))->display(function ($val) use ($items, $current_user, $top_type) {
                        $u = $items->where('id', $val)->first();
                        if ($val == $current_user->id) {
                            return sprintf('<span id="current-user-%s">%s</span>', $top_type, $u->name);
                        }
                        return $u->name;
                    });


                    break;
                case AttendanceSummaryModel::TYPE_SUMMARY_WORKPLACE:
                    $items = WorkplaceModel::all();
                    $grid->workplace_id(tr('Workplace'))->display(function ($val) use ($items) {
                        $u = $items->where('id', $val)->first();
                        return $u->title;
                    });
                    break;
                case AttendanceSummaryModel::TYPE_SUMMARY_DEPARTMENT:
                    $items = Department::all();
                    $grid->department_id(tr('Department'))->display(function ($val) use ($items) {
                        $u = $items->where('id', $val)->first();
                        return $u->name;
                    });
                    break;
            }


            $grid->avg_signin_time(tr('Average Check In Time'))->sortable();
            $grid->avg_signout_time(tr('Average Check Out Time'))->sortable();
            $grid->avg_work_seconds(tr("Person Average Duration"))->sortable()->display(function ($val) {
                return formatSecondsToHours($val);
            });
            $grid->avg_work_seconds_attendance(tr("Person Attendance Duration").'*')->sortable()->display(function ($val) {
                return formatSecondsToHours($val);
            });
            if ($summary_type != AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL) {
                $grid->user_count(tr('User Count'))->sortable();
            }


            $grid->belater_count(tr("Be Later"))->sortable();
            $grid->leaveearly_count(tr("Leave Early"))->sortable();
            $grid->total_record_count(tr("Attendance"))->sortable();

            $grid->total_work_seconds(tr("Total Duration"))->sortable()->display(function ($val) {
                return formatSecondsToHours($val);
            });
            $grid->updated_at(trans('admin.updated_at'))->sortable();
            return $grid;
        };


        switch ($type) {
            case AttendanceSummaryModel::TYPE_TOP_PERSONAL_DAY:
                $allowed_users = UserModel::getAllowedAttendanceRankUsers(['id'])->pluck('id')->toArray();
                $current_user = UserModel::getUser();
                $remove_noonbreak = false;
                if (config('oa.attendance.summary.remove_noonbreak')) {
                    $remove_noonbreak = true;
                }
                $lang_of_weeks = CalendarModel::getTranslatedWeeks();
                $week = $lang_of_weeks[$dt->dayOfWeek];
                $grid = $fnGetCommonGrid(new AttendanceModel(), "$date ($week)");
                $grid->user(tr("Username"))->display(function ($val) use ($current_user, $type) {
                    if ($val['id'] == $current_user->id) {
                        return sprintf('<span id="current-user-%s">%s</span>', $type, $val['name']);
                    }
                    return $val['name'];
                });
                $grid->signin_time(tr("Check In"))->sortable();
                $grid->signout_time(tr("Check Out"))->sortable();
                $grid->work_seconds(tr("Work Duration"))->display(function ($val) use ($remove_noonbreak) {
                    $lunch_duration = $this->lunch_duration;
                    if ($remove_noonbreak) {
                        $val = $val - $lunch_duration * 60;
                    }
                    if ($val > 0) {
                        return formatSecondsToHours($val);
                    }
                    return "";
                })->sortable();

                $grid->model()
                    ->where('date', $date)
                    ->where('user_id', '>', 0)
                    ->where('signin_time', '<>', null)
                    ->whereIn('user_id', $allowed_users)
                    ->orderBy('signin_time')
                    ->select(['id', 'signin_time', 'signout_time', 'user_id', 'work_seconds', 'lunch_duration']);


                $html = $grid->render();
                break;
            case AttendanceSummaryModel::TYPE_TOP_PERSONAL_MONTH:
                $grid = $fnGetCommonGrid(new AttendanceSummaryModel(), "$date");
                $allowed_users = UserModel::getAllowedAttendanceRankUsers(['id'])->pluck('id')->toArray();
                $grid->model()
                    ->where('type', AttendanceSummaryModel::TYPE_MONTHLY)
                    ->where('summary_type', AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL)
                    ->where('year', $dt->year)
                    ->where('month', $dt->month)
                    ->where('user_id', '>', 0)
                    ->whereIn('user_id', $allowed_users)
                    ->orderBy('total_work_seconds', 'desc');
                $grid = $fnGetTopGrid($grid, AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL, $type);
                $html = $grid->render();
                break;
            case AttendanceSummaryModel::TYPE_TOP_PERSONAL_QUARTER:
                $allowed_users = UserModel::getAllowedAttendanceRankUsers(['id'])->pluck('id')->toArray();
                $date_parts = explode('-', $date);
                $year = intval($date_parts[0]);
                $quarter = intval($date_parts[1]);

                $grid = $fnGetCommonGrid(new AttendanceSummaryModel(), "$year-Q$quarter");
                $grid->model()
                    ->where('type', AttendanceSummaryModel::TYPE_QUARTERLY)
                    ->where('summary_type', AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL)
                    ->where('year', $year)
                    ->where('quarter', $quarter)
                    ->where('user_id', '>', 0)
                    ->whereIn('user_id', $allowed_users)
                    ->orderBy('total_work_seconds', 'desc');
                $grid = $fnGetTopGrid($grid, AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL, $type);
                $html = $grid->render();
                break;
            case AttendanceSummaryModel::TYPE_TOP_PERSONAL_YEAR:
                $year = intval($date);
                $allowed_users = UserModel::getAllowedAttendanceRankUsers(['id'])->pluck('id')->toArray();
                $grid = $fnGetCommonGrid(new AttendanceSummaryModel(), "$year");
                $grid->model()
                    ->where('type', AttendanceSummaryModel::TYPE_YEARLY)
                    ->where('summary_type', AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL)
                    ->where('year', $year)
                    ->where('user_id', '>', 0)
                    ->whereIn('user_id', $allowed_users)
                    ->orderBy('total_work_seconds', 'desc');
                $grid = $fnGetTopGrid($grid, AttendanceSummaryModel::TYPE_SUMMARY_PERSONAL, $type);
                $html = $grid->render();
                break;
            case AttendanceSummaryModel::TYPE_TOP_DEPARTMENT_MONTH:
                $allowed_departments = Department::getAllowedAttendanceRankDepartments(['id'])->pluck('id')->toArray();
                $grid = $fnGetCommonGrid(new AttendanceSummaryModel(), "$date");
                $grid->model()
                    ->where('type', AttendanceSummaryModel::TYPE_MONTHLY)
                    ->where('summary_type', AttendanceSummaryModel::TYPE_SUMMARY_DEPARTMENT)
                    ->where('year', $dt->year)
                    ->where('month', $dt->month)
                    ->whereIn('department_id', $allowed_departments)
                    ->orderBy('avg_work_seconds', 'desc');
                $grid = $fnGetTopGrid($grid, AttendanceSummaryModel::TYPE_SUMMARY_DEPARTMENT, $type);
                $html = $grid->render();
                break;
            case AttendanceSummaryModel::TYPE_TOP_DEPARTMENT_QUARTER:
                $allowed_departments = Department::getAllowedAttendanceRankDepartments(['id'])->pluck('id')->toArray();
                $date_parts = explode('-', $date);
                $year = intval($date_parts[0]);
                $quarter = intval($date_parts[1]);
                $grid = $fnGetCommonGrid(new AttendanceSummaryModel(), "$year-Q$quarter");
                $grid->model()
                    ->where('type', AttendanceSummaryModel::TYPE_QUARTERLY)
                    ->where('summary_type', AttendanceSummaryModel::TYPE_SUMMARY_DEPARTMENT)
                    ->where('year', $year)
                    ->where('quarter', $quarter)
                    ->whereIn('department_id', $allowed_departments)
                    ->orderBy('avg_work_seconds', 'desc');

                $grid = $fnGetTopGrid($grid, AttendanceSummaryModel::TYPE_SUMMARY_DEPARTMENT, $type);
                $html = $grid->render();
                break;
            case AttendanceSummaryModel::TYPE_TOP_DEPARTMENT_YEAR:
                $allowed_departments = Department::getAllowedAttendanceRankDepartments(['id'])->pluck('id')->toArray();
                $year = intval($date);
                $grid = $fnGetCommonGrid(new AttendanceSummaryModel(), "$date");
                $grid->model()
                    ->where('type', AttendanceSummaryModel::TYPE_YEARLY)
                    ->where('summary_type', AttendanceSummaryModel::TYPE_SUMMARY_DEPARTMENT)
                    ->where('year', $year)
                    ->whereIn('department_id', $allowed_departments)
                    ->orderBy('avg_work_seconds', 'desc');
                $grid = $fnGetTopGrid($grid, AttendanceSummaryModel::TYPE_SUMMARY_DEPARTMENT, $type);
                $html = $grid->render();
                break;
            case AttendanceSummaryModel::TYPE_TOP_WORKPLACE_MONTH:
                $grid = $fnGetCommonGrid(new AttendanceSummaryModel(), "$date");
                $allowed_places = WorkplaceModel::getAllowedAttendanceRankPlaces(['id'])->pluck('id')->toArray();
                $grid->model()
                    ->where('type', AttendanceSummaryModel::TYPE_MONTHLY)
                    ->where('summary_type', AttendanceSummaryModel::TYPE_SUMMARY_WORKPLACE)
                    ->where('year', $dt->year)
                    ->where('month', $dt->month)
                    ->whereIn('workplace_id', $allowed_places)
                    ->orderBy('avg_work_seconds', 'desc');

                $grid = $fnGetTopGrid($grid, AttendanceSummaryModel::TYPE_SUMMARY_WORKPLACE, $type);
                $html = $grid->render();
                break;
            case AttendanceSummaryModel::TYPE_TOP_WORKPLACE_QUARTER:
                $allowed_places = WorkplaceModel::getAllowedAttendanceRankPlaces(['id'])->pluck('id')->toArray();
                $date_parts = explode('-', $date);
                $year = intval($date_parts[0]);
                $quarter = intval($date_parts[1]);

                $grid = $fnGetCommonGrid(new AttendanceSummaryModel(), "$year-Q$quarter");
                $grid->model()
                    ->where('type', AttendanceSummaryModel::TYPE_QUARTERLY)
                    ->where('summary_type', AttendanceSummaryModel::TYPE_SUMMARY_WORKPLACE)
                    ->where('year', $year)
                    ->where('quarter', $quarter)
                    ->whereIn('workplace_id', $allowed_places)
                    ->orderBy('avg_work_seconds', 'desc');


                $grid = $fnGetTopGrid($grid, AttendanceSummaryModel::TYPE_SUMMARY_WORKPLACE, $type);
                $html = $grid->render();
                break;
            case AttendanceSummaryModel::TYPE_TOP_WORKPLACE_YEAR:
                $allowed_places = WorkplaceModel::getAllowedAttendanceRankPlaces(['id'])->pluck('id')->toArray();
                $year = intval($date);
                $grid = $fnGetCommonGrid(new AttendanceSummaryModel(), "$date");
                $grid->model()
                    ->where('type', AttendanceSummaryModel::TYPE_YEARLY)
                    ->where('summary_type', AttendanceSummaryModel::TYPE_SUMMARY_WORKPLACE)
                    ->where('year', $year)
                    ->whereIn('workplace_id', $allowed_places)
                    ->orderBy('avg_work_seconds', 'desc');

                $grid = $fnGetTopGrid($grid, AttendanceSummaryModel::TYPE_SUMMARY_WORKPLACE, $type);
                $html = $grid->render();
                break;
        }


//			$box_content = '';
//			$avg_info = AttendanceSummaryModel::getSummary(0, $dt->year);
//			$year_month = $dt->format('Y-m-01');
//
//			$avg_signin_time = 'n/a';
//			$avg_signout_time = 'n/a';
//			$avg_work_seconds = 'n/a';
//			if (isset($avg_info['m'][$year_month])) {
//				$avg_signin_time = $avg_info['m'][$year_month][AttendanceSummaryModel::AVG_SIGNIN_TIME];
//				$avg_signout_time = $avg_info['m'][$year_month][AttendanceSummaryModel::AVG_SIGNOUT_TIME];
//				$avg_work_seconds = $avg_info['m'][$year_month][AttendanceSummaryModel::AVG_WORK_SECONDS];
//
//				if ($remove_noonbreak) {
//					$avg_work_seconds = $avg_work_seconds - config("oa.attendance.noonbreak.minutes") * 60;
//				}
//
//				$avg_work_seconds = round($avg_work_seconds / 3600, 2);
//			}
//
//			$box_content .= tr("Checked in time: %s, Checked out time: %s, Work Duration: %s hours", $avg_signin_time, $avg_signout_time, $avg_work_seconds);
//
//			$box = new Box(sprintf("%s %s", $dt->format('Y-m'), tr("Staff Average Information")), $box_content);
//			$box->style('info');
//			$box->solid();
//			$html = $box->render() . $html;
        return $html;
    }
}
