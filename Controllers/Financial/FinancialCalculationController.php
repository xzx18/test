<?php

namespace App\Admin\Controllers;

use App\Exports\SalaryPreviewExport;
use App\Helpers\SalaryCalcHelper;
use App\Helpers\AttendanceCalcHelper;
use App\Http\Controllers\Controller;
use App\Models\ApproveModel;
use App\Models\AttendanceModel;
use App\Models\AttendanceWorkDaysModel;
use App\Models\FinancialDetailModel;
use App\Models\FinancialSalaryArchiveModel;
use App\Models\UserModel;
use App\Models\FinancialJobGradeUserModel;
use App\Models\WorkplaceModel;
use App\Models\Auth\OAPermission;
use App\Models\FinancialSalaryModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Table;
use Encore\Admin\Widgets\Tab;

use Excel;
use PHPUnit\Framework\Constraint\Count;
use function foo\func;

class FinancialCalculationController extends Controller
{
    use HasResourceActions;

    private $salary_accounters = null;
    private $salary_place_accounters = null;
    private $attendance_releated_users = null;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        $requested_user_id = request('user');
        $requested_month = request('month');
        $requested_user_authed = false;

        $current_user = UserModel::getUser();
        $current_date = UserModel::getUserTime()->addMonth(-1);
        $authorized_users = self::getAuthorizedUsers($current_date->year, $current_date->month, $current_user);

        $body = '';

        $getTabTitle = function ($title, $count) {
            return "{$title} <span class='badge badge-light'>{$count}</span>";
        };

        $getUserButton = function ($user_id, $user_name) {
            $body = <<<EOT
<button type="button" class="btn btn-light btn-select-user" data-user-id="{$user_id}">{$user_name} </button>
EOT;
            return $body;
        };

        if (count($authorized_users) > 1) {
            $body .= <<<EOT
<button type="button" class="pull-right btn btn-sm btn-warning btn-export" style="margin-left:20px">导出工资</button>
<button type="button" class="pull-right btn btn-sm btn-primary btn-sync"  style="margin-left:20px" title="选定月、选定地方财务确认之前的工资条目，会被更新">同步当地到财务管理系统</button>
<button type="button" class="pull-right btn btn-sm btn-info btn-sync-person" style="margin-left:20px" title="选定月、选定人(财务确认之前的)工资条目，会被更新">同步当人到财务管理系统</button>
EOT;
        }

//region 用户选择
        $has_tab = count($authorized_users) > 1;
        if (!$has_tab) {
            foreach ($authorized_users as $workplace => $users) {
                $has_tab |= count($users) > 1;
            }
        }

        if ($has_tab) {
            $tab = new Tab();
            $places = ['深圳', '南昌', '武汉', '西安'];
            foreach ($authorized_users as $workplace => $users) {
                if (in_array($workplace, $places)) {
                    $btn_list = [];
                    foreach ($users as $u) {
                        $btn_list[] = $getUserButton($u->id, $u->name);
                        if (!$requested_user_authed && !$requested_user_id) {
                            $requested_user_authed = $requested_user_id == $u->id;
                        }
                    }
                    $wid = WorkplaceModel::getIdByName($workplace);
                    $title = "<b id=workplace-{$wid} class='b-workplace'>$workplace</b>";
                    $tab->add($getTabTitle($title, sizeof($users)), implode(" ", $btn_list));
                }
            }
            $tab->view("admin.widgets.tab");
            $body .= $tab->render();
        }

//endregion

//region 日期选择
        $route = \request()->url();
        $box_attendance_title = <<<EOT
<table>
    <tr>
        <td>
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
                <input style="width: 100px;font-weight: bold; text-align: center;" type="text" value="{$current_date->format('Y-m')}" class="form-control date date-picker">
            </div>
        </td>
    </tr>
</table>
EOT;
        $box_attendance_content = <<<EOT
<div id="box-salary-info" data-route='{$route}'></div>
EOT;

        $box_attendance = new Box($box_attendance_title, $box_attendance_content);
        $box_attendance->style('primary')->view("admin.widgets.box");
        $body .= $box_attendance->render();

        if (!$requested_user_id || !$requested_user_authed) {
            $requested_user_id = $current_user->id;
        }
        $requested_user_name = $requested_user_id != $current_user->id ? UserModel::getUser($requested_user_id)->name : $current_user->name;
        $requested_month = $requested_month ?? $current_date->format('Y-m');

//endregion

        //region JS代码
        $body .= <<<EOT
            <script>
                $(function () {
                     var current_user_id={$requested_user_id};
                     var current_user_name="{$requested_user_name}";
                     var current_date="{$requested_month}";

                    //选择用户
                    $('.btn-select-user').click(function(e) {
                       $('.btn-select-user').removeClass('btn-info activated').css('color','black');
                       $(this).addClass('btn-info activated').css('color','white');
                       current_user_id=$(this).data("user-id");
                       current_user_name=$(this).html();
                       fetchSalaryInfo();
                    });

                    $('.btn-export').click(function(e) {
                        var selected_workplace_id=$('.b-workplace').parent().parent('.active').find('b').attr('id');
                        var box= $("#box-salary-info");
                        var route=box.data("route");
                        var url=route + "/"+selected_workplace_id+"/"+current_date;
                        window.open(url, '_self');
                    });

                    $('.btn-sync').click(function(e) {
                        var selected_workplace_id=$('.b-workplace').parent().parent('.active').find('b').attr('id');
                        var box= $("#box-salary-info");
                        var route=box.data("route");
                        var url=route + "/sync-to-financial/"+selected_workplace_id+"/"+current_date;
                        window.open(url, '_blank');
                    });

                    $('.btn-sync-person').click(function(e) {
                        var url=$("#box-salary-info").data("route") + "/sync-person-to-financial/"+current_user_id+"/"+current_date;
                        
                        $.ajax({
                            method: 'post',
                            url: url,
                            data: {
                                _token:LA.token,
                            },
                            success: function (data) {
                                if (typeof data === 'object') {
                                    console.log(data);
                                    if (data.status) {
                                        swal(data.message, '', 'success');
                                    } else {
                                        swal(data.message, '', 'error');
                                    }
                                }
                            }
                        });
                    });

                    var datetimepicker_options={
                        format: 'YYYY-MM'
                    };
                    $('.content .box .box-title .date-picker').datetimepicker(datetimepicker_options).on("dp.change", function (e) {
                      current_date=$(this).val();
                       fetchSalaryInfo();
                     });

                    fetchSalaryInfo();

                    function fetchSalaryInfo() {
                        var box= $("#box-salary-info");
                        NProgress.configure({ parent: '.content .box-header' });
                        NProgress.start();
                        var route=box.data("route");
                        var url=route + "/"+current_user_id+"/" + current_date;
                        $.get(url,function(response ) {
                            box.html(response);
                            NProgress.done();
                        })
                    }
                })
            </script>
EOT;
//endregion

        return $content
            ->header('财务试算')
            ->description('可以计算选定月份的详细数据')
            ->body($body);
    }

    public function getSalaryRender($user_id, $from_date)
    {
        if (OAPermission::checkFinancialPermissonOfUser($user_id)) {
            $dt = Carbon::parse($from_date);
            $nothing = [];
            return self::calculate($user_id, $dt->year, $dt->month, 1, $nothing);
        }
        return '';
    }

    public function getSalaryExport($workplace_id, $date)
    {
        // $workplace_id 是 workplace-1 这样的形式
        $wid = substr($workplace_id, 10);
        if (!OAPermission::checkFinancialPermissonOfWorkplace($wid)) {
            return '';
        }

        $data = [];
        $export = new SalaryPreviewExport();
        $export->setHeaders($data);
        $column_count = count($data[0]);

        $dt = Carbon::parse($date);
        $place_name = is_numeric($wid) ? WorkplaceModel::getWorkplaceNameById($wid) : $wid;

        $authorized_users = self::getAuthorizedUsers($dt->year, $dt->month, UserModel::getUser());
        $users = $authorized_users[$place_name] ?? null;
        if ($users) {
            for ($i = 0; $i < count($users); $i++) {
                $user_data = array_fill(0, $column_count, 0);
                $user_data[0] = $i + 1;
                $user_data[5] = $date;
                self::calculate($users[$i]->id, $dt->year, $dt->month, 3, $user_data);
                $data[] = $user_data;
            }
        }

        $export->calcTotals($data);
        $export->setArrayData($data);
        return Excel::download($export, "工资表-{$place_name}-{$date}.xlsx");
    }

    public function syncWorkplaceSalaryToFinancial($workplace_id, $date)
    {
        // $workplace_id 是 workplace-1 这样的形式
        $wid = substr($workplace_id, 10);
        if (!OAPermission::checkFinancialPermissonOfWorkplace($wid)) {
            return '';
        }

        $body = '';

        $place_name = is_numeric($wid) ? WorkplaceModel::getWorkplaceNameById($wid) : $wid;

        $dt = Carbon::parse($date);
        $authorized_users = self::getAuthorizedUsers($dt->year, $dt->month, UserModel::getUser());
        $users = $authorized_users[$place_name] ?? null;
        if ($users) {
            $n = 0;
            for ($i = 0; $i < count($users); $i++) {
                $nothing = [];
                $body .= self::calculate($users[$i]->id, $dt->year, $dt->month, 2, $nothing);
                $body .= '<br/>';
                $n++;
            }
            $body .= "共处理 {$n} 条记录";
        }

        return $body;
    }

    public function syncPersonSalaryToFinancial($user_id, $date)
    {
        if (!OAPermission::checkFinancialPermissonOfUser($user_id)) {
            return '';
        }

        $dt = Carbon::parse($date);
        $nothing = [];
        $body = self::calculate($user_id, $dt->year, $dt->month, 2, $nothing);

        $status = str_contains($body, '已同步') ? 1 : 0;
        return ['status' => $status, 'message' => $body,];
    }

    public function archiveSalary($user_id, $year, $month)
    {
        $nothing = [];
        $this->calculate($user_id, $year, $month, 4, $nothing);
    }

    private static function getAuthorizedUsers($year, $month, $current_user)
    {
        $users = OAPermission::getAuthorizedUsersForFinancial($year, $month, $current_user);
        if (!empty($users['南昌'])) {
            // 财务计算工资时, 无锡的和南昌的一起算
            if (!empty($users['无锡'])) {
                $users['南昌'] = array_merge($users['南昌'], $users['无锡']);
            }
        }

        $managed_staffs = $current_user->manage_financial_staffs;
        foreach ($managed_staffs as $staff) {
            $place = $staff->workplace;
            if (!isset($users[$place])) {
                $users[$place] = [];
            }
            $users[$place][] = $staff;
        }

        return $users;
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
        $show = new Show(YourModel::findOrFail($id));

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

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new YourModel);

        $grid->id('ID')->sortable();
        $grid->created_at('Created at');
        $grid->updated_at('Updated at');

        return $grid;
    }

    /*
     * $output_type 1 网页；2 财务管理系统；3 导出Excel表格；4 存档到 表: oa_financial_salary_archive
     */
    private function calculate($user_id, $year, $month, $output_type, array &$salary_table_data)
    {
        $body = '';
        $data = [];

        $year_month = Carbon::create($year, $month, 1);

        if ($output_type != 4) {
            $this->getCachedDetailData($user_id, $year, $month, $data);
        }

        if (empty($data)) {
            $body = $this->calcDetailData($user_id, $year, $month, $data);
        }

        if (empty($data)) {
            return $body;
        }

        $salary_data = $data['salary_data'];
        $user_data = $data['user_data'];
        $attendance_salary_details = $data['attendance_salary_details'];
        $attendances_approves = $data['attendances_approves'];
        $shebao_gongjijin = $data['shebao_gongjijin'];

        if ($output_type == 1) {
            $body .= $this->outputToWeb($salary_data, $user_data);
            $body .= $this->showAttendanceDetails($attendance_salary_details,
                $attendances_approves, $user_id, $year_month->format('Y-m'));
        } elseif ($output_type == 2) {
            if ($this->checkIsSalaryAccounter()) {
                $ret = $this->outputToFinancialSystem($salary_data, $user_data, $shebao_gongjijin);
                $body .= "{$user_data['name']}({$user_data['id']} {$user_data['truename']})" . ($ret ? ', 已同步' : ', 未同步');
            } else {
                $body .= '非工资计算人员不可操作';
            }
        } elseif ($output_type == 3) {
            $this->outputToExcelTable($salary_data, $user_data, $salary_table_data);
        } elseif ($output_type == 4) {
            $str = json_encode($data, JSON_UNESCAPED_UNICODE);
            $str = gzcompress($str);
            FinancialSalaryArchiveModel::updateOrCreate(
                ['user_id' => $user_id, 'date' => $year_month->toDateString()],
                ['data' => $str]
            );
        }

        return $body;
    }

    private function calcDetailData($user_id, $year, $month, array &$data)
    {
        if ($year < 2019 || $year == 2019 && $month < 11) {
            return "<h1>2019年11月之前的数据不可计算</h1>";
        }

        $time_now = Carbon::now();
        if ($year > $time_now->year || $year == $time_now->year && $month > $time_now->month) {
            return "<h1>未来月份的数据还不可计算</h1>";
        }

        $month_start_time = Carbon::create($year, $month, 1, 0, 0, 0);
        $month_end_time = Carbon::create($year, $month, 1, 0, 0, 0)->addMonth(1);

        $user = UserModel::getUser($user_id);
        if (!$user) {
            return "<h1>未知用户 {$user_id}</h1>";
        }

        // 入、离职职时间
        $hired_time = Carbon::parse($user->hired_date);
        $quit_time = Carbon::parse($user->deleted_at ?? '2100-01-01 00:00:00');
        if ($hired_time >= $month_end_time) {
            return "<h1>在当月后入职的员工不可计算,入职日期 {$hired_time->toDateString()}</h1>";
        }

        // 本月的工资标准
        $salary_standards = [];
        FinancialJobGradeUserModel::getSalaryStandard($user_id, $year, $month, $salary_standards);
        if (count($salary_standards) == 0) {
            return "<h1>没有设置 {$user->name} 的工资标准</h1>";
        }

        // 职级
        $job_grade = null;
        if (count($salary_standards) > 0) {
            $ss = $salary_standards[count($salary_standards) - 1];
            $job_grade = $ss['job_grade'];
        }
        if (!$job_grade) {
            return "<h1>没有设置 {$user->name} 的职级</h1>";
        }

        // 工作地
        $work_place = WorkplaceModel::getUserWorkplace($user_id);
        if (!$work_place) {
            return "<h1>没有 {$user->name} 的工作地信息</h1>";
        }

        // 不受考勤的人员
        $ignore_attendance = false;
        $ignore_attendance_users = config('oa.financial.ingore.attendance.users');
        if (!empty($ignore_attendance_users)) {
            $ignore_attendance = in_array($user_id, explode(",", $ignore_attendance_users));
        }

        // 工作日
        $workdays = AttendanceWorkDaysModel::getDefinedWorkDays($year, $month)->toArray();
        $defined_person_workdays_count = AttendanceCalcHelper::getWorkdaysCountFromHiredTime($hired_time,
            $month_start_time, $workdays);

        // 考勤数据
        $attendances = AttendanceModel::getAttendanceApproveInfo($user_id, $year, $month);
        if (empty($attendances)) {
            $related_attendance_user_id = $this->getRelatedAttendanceUser($user_id);
            $attendances = AttendanceModel::getAttendanceApproveInfo($related_attendance_user_id, $year, $month);
        }

        // 固定工资和标准工资
        $standard_salary = 0;
        $days_of_month = AttendanceWorkDaysModel::getAllDates($year, $month);
        $fixed_salary = SalaryCalcHelper::calcFixedSalary($salary_standards, $attendances, $days_of_month, $hired_time,
            $quit_time, $ignore_attendance, $standard_salary);

        // 考勤工资
        $attendances_approves = AttendanceCalcHelper::calcAttendanceWithApprove($attendances, $workdays,
            $defined_person_workdays_count);
        $attendance_salary_details = SalaryCalcHelper::calcAttendanceSalary($attendances_approves, $standard_salary,
            $job_grade->job_rank, $ignore_attendance, $user->allow_allowance, $work_place, $user->id, $year, $month);

        // 导师奖
        $mentor_prize = SalaryCalcHelper::calcMentorPrize($user, $year, $month);

        // 人事奖
        $hr_prize = SalaryCalcHelper::calcHrPrize($user, $year, $month);

        // 客服奖，流量奖，广告奖
        // 季度奖
        // 其它奖
        $prizes = SalaryCalcHelper::calcCommonPrizes($user->id, $year, $month);

        // 生日礼金
        $birthday_gift = SalaryCalcHelper::calcBirthdayGift($user, $year, $month);

        // 绩效工资
        $appraisal_prize = SalaryCalcHelper::calcAppraisalPrize($user->id, $year, $month);

        // 分红
        $share = SalaryCalcHelper::calcShare($user->id, $year, $month);

        // N年奖；电脑、平板补贴；还款；计划扣款 等按月规则的费用
        $ruled_prizes = SalaryCalcHelper::calcRuledPrizes($user, $year, $month);

        // 社保公积金
        $shebao_gongjijin = SalaryCalcHelper::calcShebaoGongjijin($user->id, $year, $month);

        //专项扣除
        $tax_deduction = SalaryCalcHelper::calcTaxDeduction($user->id);

        // 汇总数据
        $salary_data['user_id'] = $user_id;
        $salary_data['year'] = $year;
        $salary_data['month'] = $month;
        $salary_data['fixed_salary'] = $fixed_salary;
        $salary_data['salary_standards'] = $salary_standards;
        $salary_data['attendance_salary_details'] = $attendance_salary_details;
        $salary_data['ignore_attendance'] = $ignore_attendance;
        $salary_data['share'] = $share;
        $salary_data['prizes'] = $prizes;
        $salary_data['appraisal_prize'] = $appraisal_prize;
        $salary_data['ruled_prizes'] = $ruled_prizes;
        $salary_data['hr_prize'] = $hr_prize;
        $salary_data['mentor_prize'] = $mentor_prize;
        $salary_data['birthday_gift'] = $birthday_gift;
        $salary_data['shebao_gongjijin'] = $shebao_gongjijin;
        $salary_data['tax_deduction'] = $tax_deduction;

        $user_data['id'] = $user->id;
        $user_data['name'] = $user->name;
        $user_data['truename'] = $user->truename;
        $user_data['workplace'] = $work_place->name;
        $user_data['grade'] = $job_grade->name;
        $user_data['grade_id'] = $job_grade->id;

        $all_salary_data = SalaryCalcHelper::calcAllSalaryData($salary_data);

        $data['salary_data'] = $all_salary_data;
        $data['user_data'] = $user_data;
        $data['attendance_salary_details'] = $attendance_salary_details;
        $data['attendances_approves'] = $attendances_approves;
        $data['shebao_gongjijin'] = $shebao_gongjijin;

        return '';
    }

    private function getCachedDetailData($user_id, $year, $month, &$data)
    {
        if (Carbon::now()->day >= 16) {
            // 16日开始才有缓存
            $date = Carbon::create($year, $month, 1)->toDateString();
            $item = FinancialSalaryArchiveModel::where('user_id', $user_id)->where('date', $date)->first();
            if ($item) {
                $str = gzuncompress($item->data);
                $data = json_decode($str, true);
            }
        }
    }

    private function showAttendanceDetails(&$attendance_salary_details, &$attendances_approves, $user_id, $year_month)
    {
        $salary_details = $this->showAttendanceSalaryDetails($attendance_salary_details, $attendances_approves);

        $approve_all = &$attendances_approves['approve_all'];
        $approve_details = $this->showAttendanceApprovesDetails($approve_all, $user_id, $year_month);

        return <<<EOT
<div class='panel panel-default'>
	<div class='panel-heading'>考勤工资</div>
	<div class='panel-body'><p>{$salary_details}</p></div>
	<div style="width: 100%; height: 1px; background: #dddddd"></div>
	<div class='panel-body'><p>{$approve_details}</p></div>
</div>
EOT;
    }

    private function showAttendanceSalaryDetails(&$attendance_salary_details, &$attendances_approves)
    {
        /*
         * $attendances_approves = [
            'defined_workdays_count' => count($workdays),
            'defined_person_workdays_count' => $defined_person_workdays_count,
            'worked_workdays_count' => 0,
            'legal_holidays_count' => 0,
            'business_trip_days' => [],
            'later_days' => [],
            'leave_early_days' => [],
            'extrawork_days' => [],
            'launch_bonus_times' => 0,
            'extrawork_dinner_bonus_times' => 0,
            'extrawork_traffic_bonus_times' => 0,
            'holiday_shifts' => [],
            'personal_leaves' => [],
            'sick_leaves' => [],
            'approve_all' => []
            ];

        $salary = [
            'full_attendance_bonus' => 0,
            'be_later_cut' => 0,
            'leave_early_cut' => 0,
            'launch_bonus' => 0,
            'extrawork_dinner_bonus' => 0,
            'extrawork_traffic_bonus' => 0,
            'personal_leave_cut' => 0,
            'sick_leave_cut' => 0,
            'extrawork_fee' => 0,
        ];

        $desc = [
            'if_full_attendance' => false,
            'later_total_time' => 0,
            'leaveearly_total_time' => 0,
            'time_for_extrawork_fee' => 0,
            'time_for_shift' => 0,
            'holiday_shift_time' => 0,
            'holiday_shift_count' => 0,
            'holiday_shift_time_shifted' => 0,
            'personal_leave_time' => 0,
            'personal_leave_count' => 0,
            'personal_leave_time_shifted' => 0,
            'sick_leave_time' => 0,
            'sick_leave_count' => 0,
            'sick_leave_time_shifted' => 0,
            'reimbursed_taxi_fares' => 0,
            'alipayed_taxi_fares' => 0,
            'reimbursed_taxi_times' => 0,
            'alipayed_taxi_times' => 0
        ];

 */


        $getUlListHtml = function ($items) {
            if ($items) {
                $html[] = '<ul>';
                foreach ($items as $item) {
                    $html[] = "<li>$item</li>";
                }
                $html[] = '</ul>';
                return implode('', $html);
            }
            return '';
        };


        $salary = $attendance_salary_details['salary'];
        $desc = $attendance_salary_details['desc'];

        $defined_workdays_count = $attendances_approves['defined_workdays_count'];
        $defined_person_workdays_count = $attendances_approves['defined_person_workdays_count'];
        $worked_workdays_count = $attendances_approves['worked_workdays_count'];
        $legal_holidays_count = $attendances_approves['legal_holidays_count'];

        $extrawork_shift_hour = $desc['time_for_shift'];

        $my_extrawork_items_html = [];
        foreach ($attendances_approves['extrawork_days'] as $extrawork_day) {
            // $extrawork_day keys: ['date', 'signin_time', 'signout_time', 'hours', 'for_salary', 'for_shift'];
            if ($extrawork_day['for_salary']) {
                $my_extrawork_items_html[] = "{$extrawork_day['date']} {$extrawork_day['hours']} 小时, 记薪";
            }
            if ($extrawork_day['for_shift']) {
                $my_extrawork_items_html[] = "{$extrawork_day['date']} {$extrawork_day['hours']} 小时, 供调休";
            }
        }
        $my_extrawork_items_html = $getUlListHtml($my_extrawork_items_html);

        $business_trip_items_html = $getUlListHtml($attendances_approves['business_trip_days']);

        $belater_time = $desc['later_total_time'];
        $belater_cut = $salary['be_later_cut'];
        $my_belater_count = count($attendances_approves['later_days']);
        $my_belater_items_html = [];
        foreach ($attendances_approves['later_days'] as $item) {
            // $later_data Keys: ['date', 'signin_time', 'signout_time', 'is_approved', 'minutes', 'hours'];
            $my_belater_items_html[] = "{$item['date']} 签到 {$item['signin_time']}, 签退 {$item['signout_time']}, 迟到 {$item['minutes']} 分钟," . ($item['is_approved'] ? "已请假" : " 记 {$item['hours']} 小时");
        }
        $my_belater_items_html = $getUlListHtml($my_belater_items_html);

        $leaveearly_time = $desc['leaveearly_total_time'];
        $leaveearly_cut = $salary['leave_early_cut'];
        $my_leaveearly_count = count($attendances_approves['leave_early_days']);
        $my_leaveearly_items_html = [];
        foreach ($attendances_approves['leave_early_days'] as $item) {
            // $leave_early_day Keys: ['date', 'signin_time', 'signout_time', 'is_approved', 'minutes', 'hours'];
            $my_leaveearly_items_html[] = "{$item['date']} 签到 {$item['signin_time']}, 签退 {$item['signout_time']}, 早退 {$item['minutes']} 分钟, " . ($item['is_approved'] ? "已请假" : "记 {$item['hours']} 小时");
        }
        $my_leaveearly_items_html = $getUlListHtml($my_leaveearly_items_html);

        $time_for_shift = $desc['time_for_shift'];
        $holiday_shift_count = $desc['holiday_shift_count'];
        $holiday_shift_time = $desc['holiday_shift_time'];
        $holiday_shifted_time = $desc['holiday_shift_time_shifted'];
        $holiday_unshifted_time = $holiday_shift_time - $holiday_shifted_time;

        $work_shift_count = $desc['work_shift_count'];
        $work_shift_time = $desc['work_shift_time'];
        $work_shifted_time = $desc['work_shift_time_shifted'];
        $work_unshift_time_shifted = $work_shift_time - $work_shifted_time;

        $personal_leave_time = $holiday_unshifted_time + $work_unshift_time_shifted + $desc['personal_leave_time'];
        $personal_leave_count = $desc['personal_leave_count'];
        $personal_leave_shifted_time = $desc['personal_leave_time_shifted'];
        $personal_leave_unshifted_time = $personal_leave_time - $personal_leave_shifted_time;
        $personal_leave_cut = $salary['personal_leave_cut'];

        $sick_leave_time = $desc['sick_leave_time'];
        $sick_leave_count = $desc['sick_leave_count'];
        $sick_leave_shifted_time = $desc['sick_leave_time_shifted'];
        $sick_leave_unshifted_time = $sick_leave_time - $sick_leave_shifted_time;
        $sick_leave_cut = $salary['sick_leave_cut'];

        $html = <<<EOT
<p>本月应该出勤：公司{$defined_workdays_count}天, 本人<b>{$defined_person_workdays_count}</b>天，法定节假日{$legal_holidays_count}天</p>
<p>本月正常出勤：<b>{$worked_workdays_count}</b>天</p>
<p>本月加班：供调休 {$extrawork_shift_hour} 小时: {$my_extrawork_items_html}</p>
<p>本月出差：$business_trip_items_html</p>
<p>本月迟到：<b>{$my_belater_count}</b>次, 共 {$belater_time} 小时, 扣 <b>{$belater_cut}</b> $my_belater_items_html</p>
<p>本月早退：<b>{$my_leaveearly_count}</b>次, 共 {$leaveearly_time} 小时, 扣 <b>{$leaveearly_cut}</b> $my_leaveearly_items_html</p>
<p>本月可供调休时间（可用于抵消调休/事假/病假）：<b>{$time_for_shift}</b>小时</p>
<p>本月实际调休时间({$holiday_shift_count}次)：{$holiday_shift_time} 小时申请, {$holiday_shifted_time} 小时已调</p>
<p>本月调班时间({$work_shift_count}次)：{$work_shift_time} 小时申请, {$work_shifted_time} 小时已调, <b>{$work_unshift_time_shifted}</b> 小时待 <b>加班</b> 抵消</p>
<p>本月事假时间({$personal_leave_count}次)：{$personal_leave_time} 小时, {$personal_leave_shifted_time} 小时已抵消, <b>{$personal_leave_unshifted_time}</b> 小时扣 <b>{$personal_leave_cut}</b></p>
<p>本月病假时间({$sick_leave_count}次)：{$sick_leave_time} 小时, {$sick_leave_shifted_time} 小时已抵消, <b>{$sick_leave_unshifted_time}</b> 小时扣 <b>{$sick_leave_cut}</b></p>
EOT;
        return $html;
    }

    private function showAttendanceApprovesDetails(&$approve_all, $user_id, $year_month)
    {

        $approve_table_header = ['日期', '假勤类型', '详情', '开始时间', '结束时间', '事项'];
        $approve_table_rows = [];
        foreach ($approve_all as $date => $approve) {
            foreach ($approve as $item) {
                $approve_table_rows[] = [
                    $date,
                    ApproveModel::getEventTypeHuman($item['event_type']),
                    $item['approve_type'],
                    $item['start_time'],
                    $item['end_time'],
                    $item['reason']
                ];
            }
        }

        $content = (new Table($approve_table_header, $approve_table_rows))->render();

        $link = sprintf('%s?user=%s&month=%s', route('attendances.record'), $user_id, $year_month);
        $content .= "<div><a href='{$link}'\"'><span style='color: #0d6aad;text-decoration: underline'>查看考勤详情</span></a></div>";

        return $content;
    }

    private function getSalaryItemShow($salary_item_data, $highlight = '')
    {
        $money = $salary_item_data['money'];
        if (!config('app.debug') || !OAPermission::isAdministrator()) {
            if (isset($salary_item_data['hide_if_none']) && $salary_item_data['hide_if_none'] && $money == 0) {
                return '';
            }
        }

        $style = '';
        if ($highlight) {
            $style = "style='background: {$highlight}'";
        }

        $name = $salary_item_data['name'];
        $detail = $salary_item_data['detail'] ?? '';
        return "<tr {$style}>"
            . "<td>{$name}</td>"
            . "<td>{$money}</td>"
            . "<td>{$detail}</td>"
            . "</tr>";
    }

    private function outputToWeb($salary_data, $user_data)
    {
        $trs = '';
        $trs .= $this->getSalaryItemShow($salary_data['fixed_salary']);
        $trs .= $this->getSalaryItemShow($salary_data['launch_bonus']);
        $trs .= $this->getSalaryItemShow($salary_data['extrawork_dinner']);
        $trs .= $this->getSalaryItemShow($salary_data['extrawork_traffic']);
        $trs .= $this->getSalaryItemShow($salary_data['full_attendance']);
        $trs .= $this->getSalaryItemShow($salary_data['sales_share']);
        $trs .= $this->getSalaryItemShow($salary_data['contribution']);
        $trs .= $this->getSalaryItemShow($salary_data['appraisal_prize']);
        $trs .= $this->getSalaryItemShow($salary_data['season_prize']);
        $trs .= $this->getSalaryItemShow($salary_data['year_prize']);
        $trs .= $this->getSalaryItemShow($salary_data['computer_bonus']);
        $trs .= $this->getSalaryItemShow($salary_data['pad_bonus']);
        $trs .= $this->getSalaryItemShow($salary_data['hr_prize']);
        $trs .= $this->getSalaryItemShow($salary_data['customer_service']);
        $trs .= $this->getSalaryItemShow($salary_data['flow']);
        $trs .= $this->getSalaryItemShow($salary_data['ads']);
        $trs .= $this->getSalaryItemShow($salary_data['mentor_prize']);
        $trs .= $this->getSalaryItemShow($salary_data['n_year_prize']);
        $trs .= $this->getSalaryItemShow($salary_data['birthday_gift']);
        $trs .= $this->getSalaryItemShow($salary_data['special_gift']);
        $trs .= $this->getSalaryItemShow($salary_data['other_prize']);
        $trs .= $this->getSalaryItemShow($salary_data['personal_leave_cut']);
        $trs .= $this->getSalaryItemShow($salary_data['sick_leave_cut']);
        $trs .= $this->getSalaryItemShow($salary_data['be_later_cut']);
        $trs .= $this->getSalaryItemShow($salary_data['other_cut']);
        $trs .= $this->getSalaryItemShow($salary_data['salary_sum'], '#D9EDF7');
        $trs .= $this->getSalaryItemShow($salary_data['training_fee']);
        $trs .= $this->getSalaryItemShow($salary_data['repay_money']);
        $trs .= $this->getSalaryItemShow($salary_data['large_medical']);
        $trs .= $this->getSalaryItemShow($salary_data['endowment'], '#ECF0F5');
        $trs .= $this->getSalaryItemShow($salary_data['unemployment'], '#ECF0F5');
        $trs .= $this->getSalaryItemShow($salary_data['medical'], '#ECF0F5');
        $trs .= $this->getSalaryItemShow($salary_data['employment_injury'], '#ECF0F5');
        $trs .= $this->getSalaryItemShow($salary_data['maternity'], '#ECF0F5');
        $trs .= $this->getSalaryItemShow($salary_data['housing_provident_fund'], '#ECF0F5');
        $trs .= $this->getSalaryItemShow($salary_data['salary_taxable'], '#D9EDF7');
        $trs .= $this->getSalaryItemShow($salary_data['tax_deduction']);
        $trs .= $this->getSalaryItemShow($salary_data['this_tax'], '#ECF0F5');
        $trs .= $this->getSalaryItemShow($salary_data['accumulative_pretax_salary']);
        $trs .= $this->getSalaryItemShow($salary_data['accumulative_tax_deducted']);
        $trs .= $this->getSalaryItemShow($salary_data['accumulative_tax_payed']);
        $trs .= $this->getSalaryItemShow($salary_data['actual_salary'], '#D9EDF7');

        if (OAPermission::isAdministrator() || OAPermission::isFinancialStaff()) {
            $trs .= $this->getSalaryItemShow($salary_data['insurance_organization']);
            $trs .= $this->getSalaryItemShow($salary_data['housing_provident_fund_organization']);
        }

        $header = "<h2>{$user_data['name']} <small>{$user_data['truename']}, {$user_data['workplace']}, {$user_data['grade']}</small></h2>";

        $sub_header = "本页数据 <span style='color: #c83939;font-weight: bold'>仅供参考</span> ,实际以财务系统中已确认的工资表格数据为准。<br/>"
            . "公司财务系统依据21.75天/月、7.5小时/天的工作日标准做相关计算。";

        return <<<EOT
<div class='panel panel-default'>
	<div class='panel-heading'>{$header}</div>
	<div class='panel-body'><p>{$sub_header}</p></div>
	<table class='table table-bordered table-hover table-condensed' style='width: 100%'>
		<tr class='success text-bold'>
			<td style='width: 140px'>名称</td>
			<td style='width: 140px'>金额</td>
			<td >详情</td>
		</tr>
		{$trs}
    </table>
</div>
EOT;
    }

    private function outputToFinancialSystem(&$salary_data, &$user_data, &$shebao_gongjijin)
    {
        $month_start_date = Carbon::create($salary_data['year'], $salary_data['month'], 1);
        $item = FinancialSalaryModel::firstOrNew([
            'user_id' => $salary_data['user_id'],
            'date' => $month_start_date->toDateString()
        ]);

        if (is_null($item->status)) {
            // 状态为null，说明是新创建的条目
            $item->status = FinancialSalaryModel::STATUS_HR_CHECKING;
            // 备注
            $item->mark = '';
            // 由谁创建的
            $item->created_by = $this->getSalaryAccounter($user_data['workplace']);
        } elseif ($item->status <= FinancialSalaryModel::STATUS_HR_CONFIRMED) {
            $item->status = FinancialSalaryModel::STATUS_HR_CHECKING;
        }

        if ($item->status >= FinancialSalaryModel::STATUS_FINANCIAL_CONFIRMED) {
            return false;
        }

        // 职级，比如T7
        $item->job_level_id = $user_data['grade_id'];
        // 固定工资
        $item->basic_salary = $salary_data['fixed_salary']['money'];
        // 加班费 0
        $item->overtime_pay = 0;
        // 午餐补贴
        $item->meal_allowance_lunch = $salary_data['launch_bonus']['money'];
        // 加班餐补
        $item->meal_allowance_overtime = $salary_data['extrawork_dinner']['money'];
        // 加班车补
        $item->trans_allowance = $salary_data['extrawork_traffic']['money'];
        // 全勤奖
        $item->full_attendance_bonus = $salary_data['full_attendance']['money'];
        // 季度分红金额
        $item->quarter_dividend = $salary_data['sales_share']['money'];
        // 季度贡献奖
        $item->quarter_bonus_duration = 0;
        // 季度工作绩效奖
        $item->quarter_bonus_work = $salary_data['appraisal_prize']['money'];
        // 季度奖，比如"飞跃奖
        $item->quarter_bonus_award = $salary_data['season_prize']['money'] + $salary_data['year_prize']['money'];
        // 税后收入（实收工资）
        $item->income_after_taxes = $salary_data['actual_salary']['money'];
        // 银行代扣工资
        $item->bank_transfer_salary = $salary_data['actual_salary']['money'];
        // 应纳税金额（工资合计）
        $item->taxable_income = $salary_data['salary_sum']['money'];

        // 养老保险缴费基数
        // $item->endowment_insurance_base = 0;
        // 医疗保险缴费基数
        // $item->medical_insurance_base = 0;
        // 工伤保险缴费基数
        // $item->employment_injury_insurance_base = 0;
        // 失业保险缴费基数
        // $item->unemployment_insurance_base = 0;
        // 生育保险缴费基数
        // $item->maternity_insurance_base = 0;
        // 公积金缴费基数
        // $item->housing_provident_fund_insurance_base = 0;
        // 养老保险个人缴费金额
        $item->endowment_insurance_personal = $shebao_gongjijin['endowment_personal'];
        // 医疗保险个人缴费金额
        $item->medical_insurance_personal = $shebao_gongjijin['medical_personal'];
        // 失业保险个人缴费金额
        $item->unemployment_insurance_personal = $shebao_gongjijin['unemployment_personal'];
        // 工伤保险个人缴费金额
        $item->employment_injury_insurance_personal = $shebao_gongjijin['employment_injury_personal'];
        // 生育保险个人缴费金额
        $item->maternity_insurance_personal = $shebao_gongjijin['maternity_personal'];
        // 补充医疗保个人缴费金额
        // $item->supplementary_medical_insurance_personal = $shebao_gongjijin[''];
        // 公积金个人缴费金额
        $item->housing_provident_fund_insurance_personal = $shebao_gongjijin['housing_provident_fund_personal'];
        // 养老保险单位缴费金额
        $item->endowment_insurance_organization = $shebao_gongjijin['endowment_organization'];
        // 医疗保险单位缴费金额
        $item->medical_insurance_organization = $shebao_gongjijin['medical_organization'];
        // 失业保险单位缴费金额
        $item->unemployment_insurance_organization = $shebao_gongjijin['unemployment_organization'];
        // 工伤保险单位缴费金额
        $item->employment_injury_insurance_organization = $shebao_gongjijin['employment_injury_organization'];
        // 生育保险单位缴费金额
        $item->maternity_insurance_organization = $shebao_gongjijin['maternity_organization'];
        // 补充医疗保单位缴费金额
        // $item->supplementary_medical_insurance_organization = $shebao_gongjijin[''];
        // 公积金单位缴费金额
        $item->housing_provident_fund_insurance_organization = $shebao_gongjijin['housing_provident_fund_organization'];
        // 是否是本地户口
        // $item->native_hukou = 0;

        $tax_detail = $salary_data['tax_detail'];
        // 个税专项附加扣除
        $item->tax_exemption = $tax_detail['tax_month']['本次专项附加扣除'];
        // 税前收入（税前总收入）??
        $item->pretax_income = $tax_detail['tax_month']['本次税前收入'];
        // 计税工资（应纳税金额-保险公积金扣除）
        $item->taxable_salary = $tax_detail['tax_month']['本次计税工资'];
        // 计税金额-专项附加扣除-保险公积金扣除-个税起征点净纳税额(计税金额-专项附加扣除-保险公积金扣除-个税起征点)
        $item->net_taxable_income = $tax_detail['tax_month']['本次净纳税额'];
        // 应付税款
        $item->taxes_payable = max(0, $tax_detail['tax_month']['本次应纳税额']);
        // 本次应缴保险和公积金费用
        $item->insurance_personal = $tax_detail['tax_month']['本次保险公积金扣除'];
        // 累计单位保险费
        // $item->accumulated_insurance_organization = $tax_detail[''];
        // 累计个人保险费
        // $item->accumulated_insurance_personal = $tax_detail[''];
        // 累计扣除
        $item->accumulated_tax_exemption = $tax_detail['tax_summary']['累计税收扣除'];
        // 累计应付税款
        $item->accumulated_taxes_payable = $tax_detail['tax_summary']['累计已缴税额'];
        // 累计应纳税金额
        $item->accumulated_taxable_income = $tax_detail['tax_summary']['累计应纳税金额'];
        // 累计税前收入
        $item->accumulated_pretax_income = $tax_detail['tax_summary']['累计税前收入'];

        $item->save();

        if ($item->id) {
            $detail_data = [
                // 11	满N年奖   n_year_prize
                11 => $salary_data['n_year_prize']['money'],
                // 12	礼金  special_gift
                12 => $salary_data['special_gift']['money'],
                // 13	结婚礼金
                13 => 0,
                // 14	事假扣款 personal_leave_cut
                14 => $salary_data['personal_leave_cut']['money'],
                // 18	其他 other_prize
                18 => $salary_data['other_prize']['money'],
                // 19	银行转发 actual_salary，全部代发
                19 => 0,
                // 21	生日礼金 birthday_gift
                21 => $salary_data['birthday_gift']['money'],
                // 22	大额医疗 large_medical
                22 => $salary_data['large_medical']['money'],
                // 23	培训费 training_fee
                23 => $salary_data['training_fee']['money'],
                // 24	人事奖 hr_prize
                24 => $salary_data['hr_prize']['money'],
                // 27	客服奖 customer_service
                27 => $salary_data['customer_service']['money'],
                // 28	流量奖 flow
                28 => $salary_data['flow']['money'],
                // 29	病假扣款 sick_leave_cut
                29 => $salary_data['sick_leave_cut']['money'],
                // 30	迟到扣款 be_later_cut
                30 => $salary_data['be_later_cut']['money'],
                // 31	其他扣款 other_cut
                31 => $salary_data['other_cut']['money'],
                // 32	导师奖 mentor_prize
                32 => $salary_data['mentor_prize']['money'],
                // 34	年度奖 year_prize
                34 => $salary_data['year_prize']['money'],
                // 35	生育礼金
                35 => 0,
                // 36	电脑补贴 computer_bonus
                36 => $salary_data['computer_bonus']['money'],
                // 37	平板补贴 pad_bonus
                37 => $salary_data['pad_bonus']['money'],
                // 38	贡献奖 contribution
                38 => $salary_data['contribution']['money'],
                // 39	广告奖 ads
                39 => $salary_data['ads']['money'],
            ];
            FinancialDetailModel::saveDetails($item->id, $detail_data);
        }

        return true;
    }

    private function outputToExcelTable(&$salary_data, &$user_data, array &$salary_table_data)
    {
        // 1 英文名
        $salary_table_data[1] = $user_data['name'];
        // 2 姓名
        $salary_table_data[2] = $user_data['truename'];
        // 3 职级
        $salary_table_data[3] = $user_data['grade'];
        // 4 区域
        $salary_table_data[4] = $user_data['workplace'];
        // 6 基本工资
        $salary_table_data[6] = $salary_data['fixed_salary']['money'];
        // 7 加班费
        $salary_table_data[7] = 0;
        // 8 午餐补贴
        $salary_table_data[8] = $salary_data['launch_bonus']['money'];
        // 9 加班餐补
        $salary_table_data[9] = $salary_data['extrawork_dinner']['money'];
        // 10 加班车补
        $salary_table_data[10] = $salary_data['extrawork_traffic']['money'];
        // 11 全勤奖
        $salary_table_data[11] = $salary_data['full_attendance']['money'];
        // 12 分红
        $salary_table_data[12] = $salary_data['sales_share']['money'];
        // 13 贡献奖
        $salary_table_data[13] = $salary_data['contribution']['money'];
        // 14 绩效工资
        $salary_table_data[14] = $salary_data['appraisal_prize']['money'];
        // 15 季度奖
        $salary_table_data[15] = $salary_data['season_prize']['money'];
        // 16 年度奖
        $salary_table_data[16] = $salary_data['year_prize']['money'];
        // 17 电脑补贴
        $salary_table_data[17] = $salary_data['computer_bonus']['money'];
        // 18 平板补贴
        $salary_table_data[18] = $salary_data['pad_bonus']['money'];
        // 19 人事奖
        $salary_table_data[19] = $salary_data['hr_prize']['money'];
        // 20 客服奖
        $salary_table_data[20] = $salary_data['customer_service']['money'];
        // 21 流量奖
        $salary_table_data[21] = $salary_data['flow']['money'];
        // 22 广告奖
        $salary_table_data[22] = $salary_data['ads']['money'];
        // 23 导师奖
        $salary_table_data[23] = $salary_data['mentor_prize']['money'];
        // 24 满N年奖
        $salary_table_data[24] = $salary_data['n_year_prize']['money'];
        // 25 生日礼金
        $salary_table_data[25] = $salary_data['birthday_gift']['money'];
        // 26 其它礼金
        $salary_table_data[26] = $salary_data['special_gift']['money'];
        // 27 其他
        $salary_table_data[27] = $salary_data['other_prize']['money'];
        // 28 事假扣款
        $salary_table_data[28] = $salary_data['personal_leave_cut']['money'];
        // 29 病假扣款
        $salary_table_data[29] = $salary_data['sick_leave_cut']['money'];
        // 30 迟到扣款
        $salary_table_data[30] = $salary_data['be_later_cut']['money'];
        // 31 其他扣款
        $salary_table_data[31] = $salary_data['other_cut']['money'];
        // 32 工资合计
        $salary_table_data[32] = $salary_data['salary_sum']['money'];
        // 33 养老保险
        $salary_table_data[33] = $salary_data['endowment']['money'];
        // 34 失业保险
        $salary_table_data[34] = $salary_data['unemployment']['money'];
        // 35 医疗保险
        $salary_table_data[35] = $salary_data['medical']['money'];
        // 36 公积金
        $salary_table_data[36] = $salary_data['housing_provident_fund']['money'];
        // 37 计税工资
        $salary_table_data[37] = $salary_data['salary_taxable']['money'];
        // 38 累计税前工资
        $salary_table_data[38] = $salary_data['accumulative_pretax_salary']['money'];
        // 39 累计扣除金额
        $salary_table_data[39] = $salary_data['accumulative_tax_deducted']['money'];
        // 40 累计个税
        $salary_table_data[39] = $salary_data['accumulative_tax_payed']['money'];
        // 41 专项扣除
        $salary_table_data[41] = $salary_data['tax_deduction']['money'];
        // 42 应缴个税
        $salary_table_data[42] = $salary_data['this_tax']['money'];
        // 43 大额医疗
        $salary_table_data[43] = $salary_data['large_medical']['money'];
        // 44 培训费
        $salary_table_data[44] = $salary_data['training_fee']['money'];
        // 45 借款
        $salary_table_data[45] = $salary_data['repay_money']['money'];
        // 46 实发工资
        $salary_table_data[46] = $salary_data['actual_salary']['money'];
        // 49 公司承担社保
        $salary_table_data[49] = $salary_data['insurance_organization']['money'];
        // 50 公司承担公积金
        $salary_table_data[50] = $salary_data['housing_provident_fund_organization']['money'];
    }

    private function checkIsSalaryAccounter()
    {
        if (is_null($this->salary_accounters)) {
            $this->salary_accounters = [];
            $config_str = config('oa.financial.salary.accounters');
            if (!empty($config_str)) {
                $this->salary_accounters = explode(",", $config_str);
            }
        }
        return in_array(UserModel::getUser()->id, $this->salary_accounters);
    }

    private function getSalaryAccounter($workplace)
    {
        if (is_null($this->salary_place_accounters)) {
            $this->salary_place_accounters = [];
            $config_str = config('oa.financial.salary.place.accounters');
            if (!empty($config_str)) {
                $this->salary_place_accounters = json_decode($config_str, true);
            }
        }

        $ruki_id_as_default = 92;
        return $this->salary_place_accounters[$workplace] ?? $this->salary_place_accounters['Default'] ?? $ruki_id_as_default;
    }

    private function getRelatedAttendanceUser($user_id)
    {
        if (is_null($this->attendance_releated_users)) {
            $config_str = config('oa.attendance.releated.users');
            if (!empty($config_str)) {
                $this->attendance_releated_users = json_decode($config_str, true);
            }
        }
        return $this->attendance_releated_users[$user_id] ?? 0;
    }

}
