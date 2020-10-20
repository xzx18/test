<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\ExtendedBox;
use App\Http\Controllers\Controller;
use App\Models\AttendanceWorkDaysModel;
use App\Models\CalendarModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Http\Request;

/**
 * 考勤工作日设置
 * Class AttendanceWorkdaysController
 * @package App\Admin\Controllers
 */
class AttendanceWorkdaysController extends Controller
{
    use HasResourceActions;


    public function getCalendarByAjax(Request $request)
    {
        $year = intval($request->get('year'));
        return $this->getCalendar($year);
    }

    public function getCalendar($year)
    {
        $work_days = AttendanceWorkDaysModel::getAllDates($year);
        $fnGetCalendar = function (Carbon $date) use ($work_days) {
            $calendar = new CalendarModel();
            $lang_of_weeks = CalendarModel::getTranslatedWeeks();
            $calendar->orderWithLanguages = $lang_of_weeks;
            $calendar->sixRows = true;
            $calendar->valign = 'valign="middle"';
            $calendar->workDays = $work_days;
            $html = $calendar->render($date, $date->month);
            return $html;
        };
        $months = [];
        for ($m = 1; $m <= Carbon::MONTHS_PER_YEAR; $m++) {
            $dt = Carbon::parse("$year-$m-1");
            $months[$m] = $fnGetCalendar($dt);
        }
        $html = <<<eot
			
<table width="100%" border="0" id="calendar-table">
    <tbody>
    <tr class="tr">
        <td valign="top" class="td">{$months[1]}</td>
        <td valign="top" class="td">{$months[2]}</td>
        <td valign="top" class="td">{$months[3]}</td>
    </tr>
    <tr>
        <td valign="top" class="td">{$months[4]}</td>
        <td valign="top" class="td">{$months[5]}</td>
        <td valign="top" class="td">{$months[6]}</td>
    </tr>
    <tr>
        <td valign="top" class="td">{$months[7]}</td>
        <td valign="top" class="td">{$months[8]}</td>
        <td valign="top" class="td">{$months[9]}</td>
    </tr>
    <tr>
        <td valign="top" class="td">{$months[10]}</td>
        <td valign="top" class="td">{$months[11]}</td>
        <td valign="top" class="td">{$months[12]}</td>
    </tr>
    </tbody>
</table>
eot;
        return $html;
    }

    public function saveCalendarByAjax(Request $request)
    {
        $dates = $request->post('dates');
        foreach ($dates as $date => $is_workday) {
            AttendanceWorkDaysModel::updateOrCreate([
                'date' => $date,
            ], [
                'is_workday' => intval($is_workday)
            ]);
        }
        return [
            'status' => true,
            'type' => 'success',
            'message' => '保存成功'
        ];
    }

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        $year = 2019;
        $ajax_calendar_url = route('attendance.workdays.calendar');
        $js_css = <<<eot
<style>
    .td {
        padding-right: 1px;
        padding-bottom: 1px;
    }

    .wx-calendar {
        height: 300px;
    }

    .wx-calendar .header {
        color: red;
    }

    .wx-calendar td {
        height: 30px;
        padding-bottom: 0px;
        cursor: default;

    }

    .wx-calendar td .day {
        font-weight: normal;
    }

    .wx-calendar .header {
        padding: 3px;
    }

    .wx-calendar .current-month.attendance-work-day {
        background-color: greenyellow;
        color: blue;

    }

    .wx-calendar .current-month.attendance-work-day .day {
        font-weight: bold;
    }
</style>

<script>

    $(document).ready(function (e) {
        var ajax_calendar_url = '{$ajax_calendar_url}';
        var modified_dates = {};
        var current_year = '';
        var datetimepicker_options = {
            format: 'YYYY',
            maxDate: '2029-12-31',
            minDate: '2019-01-01'
        };
        $('.content .box .box-title .date-picker').datetimepicker(datetimepicker_options).on("dp.change", function (e) {
            current_year = $(this).val();
            modified_dates = {};
            console.log(current_year);

            NProgress.configure({parent: '.content .box-header'});
            NProgress.start();
            $.get(ajax_calendar_url + '?year=' + current_year, function (response) {
                $("#attendance-calendar-container #calendar-table").remove();
                $("#attendance-calendar-container").append(response);
                NProgress.done();
            });
        });

        $(document).on('click', '#attendance-calendar-container .current-month', function () {
            var d = $(this).data('attendance-date');
            if ($(this).hasClass('attendance-work-day')) {
                $(this).removeClass('attendance-work-day');
                modified_dates[d] = 0;
            } else {
                $(this).addClass('attendance-work-day');
                modified_dates[d] = 1;
            }
            console.log(modified_dates);
        });

        $("#save-calendar-workdays").click(function (e) {
           
            if (modified_dates) {
                 NProgress.configure({parent: '.content .box-header'});
            NProgress.start();
                var post_date = {
                    dates: modified_dates,
                    _token: LA.token
                };
                $.post(ajax_calendar_url, post_date, function (response) {
                      swal({
							title: response.message,
							type: response.type,
							showCancelButton: false,
							confirmButtonColor: "#DD6B55",
							confirmButtonText: "确认"
						});
                      if(response.status){
                          modified_dates={};
                      }
                }).fail(function (jqXHR, textStatus, errorThrown) {

                }).always(function () {
                    NProgress.done();
                });
            }
        });
    });
</script>
eot;

        $box_title = <<<eot
<span class="input-group">
<span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
<input style="width: 100px;font-weight: bold; text-align: center;" type="text" value="{$year}" class="form-control date date-picker"><button class='btn btn-primary'  style="margin-left: 30px;" id="save-calendar-workdays">保存设置</button>
</span>
eot;
        $box_content = '<div id="attendance-calendar-container">' . $js_css . $this->getCalendar($year) . '</div>';

        $box = new ExtendedBox($box_title, $box_content);
        $box->style('primary');

        return $content
            ->header('工作日设置')
            ->description(' ')
            ->body($box);
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
            ->header('Detail')
            ->description('description')
            ->body($this->detail($id));
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(AttendanceWorkDaysModel::findOrFail($id));

        $show->id('ID');
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
        $form = new Form(new AttendanceWorkDaysModel);

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

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new AttendanceWorkDaysModel());

        $grid->id('ID')->sortable();
        $grid->created_at('Created at');
        $grid->updated_at('Updated at');

        return $grid;
    }
}
