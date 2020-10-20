<?php

namespace App\Admin\Controllers;

use App\Models\UserConfirmationModel;
use App\Models\UserModel;
use App\Models\WorkplaceModel;
use App\Helpers\AttendanceConfirmHelper;
use Carbon\Carbon;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserConfirmationController extends Controller
{
    // 目前只做了考勤确认的
    public function attendanceIndex(Content $content)
    {
        $current_user = UserModel::getUser();

        $place_options = '';
        foreach ($current_user->manage_workplaces as $item) {
            $place_options .= "<option value=\"{$item->id}\">{$item->name}</option>";
        }

        $dt_now = Carbon::now()->subMonth();
        $month_options = <<<EOT
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
                <input id="month-selector" style="width: 100px;font-weight: bold; text-align: center;" type="text" value="{$dt_now->format('Y-m')}" class="form-control date date-picker">
            </div>
EOT;

        $notify_url = route('attendance.confirm.remind');

        $box_title = <<<EOT
<table class="table" >
    <tr>
        <td style="width: 80px">地区</td>
        <td style="width: 240px"><select id="place-selector" style="width: 143px" class="form-control">{$place_options}</select></td>
        <td style="width: 180px"></td>
        <td>
            <div class="pull-right"  style="margin-right: 10px">
                <button id="btn-remind" data-route="{$notify_url}" class="btn btn-sm btn-warning"  title="给上月未确认人员发送钉钉提醒通知">
                    <i class="fa fa-list"></i><span class="hidden-xs" style="margin-left: 10px;">提醒未确认人员</span>
                </button>
            </div>
        </td>
    </tr>
    <tr>
        <td>月份</td>    
        <td>{$month_options}</td>
        <td>
        <td>
    </tr>
</table>
EOT;

        $route = route('attendance.confirm.status');
        $box_content = <<<EOT
<div id="box-info" data-route='{$route}'></div>
EOT;

        $box_attendance = new Box($box_title, $box_content);

        $box_attendance->style('primary')->view("admin.widgets.box");
        $body = $box_attendance->render();

        $remind_script = $this->getRemindScript();

        $body .= <<<EOT
<script>
        $(function () {
            var place = $("#place-selector").val();
            var month = $("#month-selector").val();
            fetchConfirmationInfo();
        
            $("#place-selector").change(function () {
                place = $("#place-selector").val();
                fetchConfirmationInfo();
            });
        
            var datetimepicker_options = { format: 'YYYY-MM' };            
            $("#month-selector").datetimepicker(datetimepicker_options).on("dp.change", function (e) {
                month = $(this).val();
                fetchConfirmationInfo();
            });
            
            {$remind_script}
                    
            function fetchConfirmationInfo() {
                if (!place || !month) {
                    return;
                }
        
                var box = $("#box-info");
                var url = box.data("route") + "/" + place + "/" + month;
                NProgress.configure({
                    parent: '.content .box-header'
                });
                NProgress.start();
                $.get(url, function (response) {
                    box.html(response);
                    NProgress.done();
                })
            }        
        })
</script>
EOT;

        return $content
            ->header('考勤确认')
            ->description('查看各地人员的考勤确认情况')
            ->body($body);
    }

    public function doAttendanceConfirmation($month)
    {
        $ret = UserConfirmationModel::doConfirm(UserModel::getCurrentUserId(), $month,
            UserConfirmationModel::TYPE_ATTENDANCE, 1);
        if ($ret) {
            return ['status' => 1, 'message' => "确认成功",];
        } else {
            return ['status' => 0, 'message' => "确认失败",];
        }
    }

    public function getAttendanceConfirmationRender($place_id = null, $month = null)
    {
        if (is_null($month) || is_null($place_id)) {
            return '';
        }

        $date = Carbon::parse($month)->startOfMonth()->toDateString();
        $users = WorkplaceModel::getUsers($place_id);
        $ingore_attendance_users = self::getIngoreAttendanceUsers();

        $confirmed_users = UserConfirmationModel::where('date', $date)
            ->where('info_type', 1)
            ->where('confirmed', 1)
            ->pluck('user_id')
            ->toArray();

        $body = '<table class="table table-bordered table-hover table-striped table-condensed">';

        $i = 1;
        foreach ($users as $user) {
            $user_id = $user->id;
            if (!$user->userid || !$user->truename || starts_with($user->userid, 'userid')) {
                continue;
            }

            if (in_array($user_id, $ingore_attendance_users) && $user_id != 28) {
                $name = $user->name;
                $status = '<span class="text-gray">无需确认</span>';
            } elseif (in_array($user_id, $confirmed_users)) {
                $name = $user->name;
                $status = '<span>已确认</span>';
            } else {
                $name = '<span class="text-red">' . $user->name . '</span>';
                $status = '<span class="text-red">待确认</span>';
            }

            $body .= "<tr><td style='width: 200px'>{$i}</td><td>{$name}</td><td>{$status}</td></tr>";
            $i++;
        }

        $body .= '</table>';

        return $body;
    }

    public function remindAttendanceConfirmationRender(Request $request)
    {
        $count = 0;
        $place = $request->post('place');
        if ($place) {
            $count = AttendanceConfirmHelper::Remind($place);
        }
        return ['status' => 1, 'message' => "已发送确认给 {$count} 人"];
    }

    private static function getIngoreAttendanceUsers()
    {
        $ingore_attendance_users = [];
        $ingore_attendance_users_config = config('oa.financial.ingore.attendance.users');
        if (!empty($ingore_attendance_users_config)) {
            $ingore_attendance_users = explode(",", $ingore_attendance_users_config);
        }
        return $ingore_attendance_users;
    }

    private function getRemindScript()
    {
        return <<<EOT

        $('#btn-remind').click(function (e) {
            var post_url = $(this).data('route');
            var title = "通知上月未确认人员?";
            swal({
                title: title,
                text: "注意：会给未确认人员发送钉钉提醒。",
                type: "warning",
                showCancelButton: true,
                confirmButtonColor: "#DD6B55",
                confirmButtonText: "确认",
                showLoaderOnConfirm: true,
                cancelButtonText: "取消",
                preConfirm: function () {
                    return new Promise(function (resolve) {
                        $.ajax({
                            method: 'post',
                            url: post_url,
                            data: {
                                place: place,
                                _token: LA.token
                            },
                            success: function (data) {
                                resolve(data);
                            }
                        });
                    });
                }
            }).then(function (result) {
                var data = result.value;
                if (typeof data === 'object') {
                    if (data.status) {
                        swal(data.message, '', 'success');
                    } else {
                        swal(data.message, '', 'error');
                    }
                }
            });
        });
EOT;

    }

}
