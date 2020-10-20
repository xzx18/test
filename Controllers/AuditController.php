<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditModel;
use App\Models\Department;
use App\Models\UserModel;
use App\Models\WorkplaceModel;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Role;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;

class AuditController extends Controller
{
    use HasResourceActions;


    public function login(Content $content)
    {
        return $content
            ->header('登录审计')
            ->description('查看所有的登录操作记录')
            ->body($this->grid('login'));
    }

    protected function grid($type)
    {
        $timezone_china = config('app.timezone_china');
        $grid = new Grid(new AuditModel());
        $grid->disableCreateButton();
        $grid->disableActions();
        $grid->disableRowSelector();
        $grid->disableExport();
        $grid->id('ID')->sortable();

        $display_common_info = true;
        switch ($type) {
            case 'login':
                $grid->filter(function (Grid\Filter $filter) {
                    $filter->disableIdFilter();
                    $filter->like('user_name', trans('admin.name'));
                });
                $grid->model()->where('type', AuditModel::AUDIT_TYPE_LOGIN);
                $grid->user_name('用户名')->sortable();
                $grid->event_type('类型')->sortable()->display(function ($val) {
                    if ($val == AuditModel::LOGIN_TYPE_SCAN) {
                        return AuditModel::getBadge('primary', '钉钉');
                    }
                    return '';
                });
                $grid->result('状态')->sortable()->display(function ($val) {
                    if ($val) {
                        return AuditModel::getBadge('success', '成功');
                    }
                    return AuditModel::getBadge('danger', '失败');
                });
                break;
            case 'attendance':
                $grid->filter(function (Grid\Filter $filter) {
                    $filter->disableIdFilter();
                    $filter->like('user_name', trans('admin.name'));
                });

//                $grid->model()->where('type', AuditModel::AUDIT_TYPE_SIGNIN)->orWhere('type', AuditModel::AUDIT_TYPE_SIGNOUT);
                $grid->model()->where(function ($query) {
                    $query->where('type', AuditModel::AUDIT_TYPE_SIGNIN)->orWhere('type', AuditModel::AUDIT_TYPE_SIGNOUT);
                });


                $grid->user_name('用户名')->sortable();
                $grid->result('状态')->sortable()->display(function ($val) {
                    if ($val) {
                        return AuditModel::getBadge('success', '成功');
                    }
                    return AuditModel::getBadge('danger', '失败');
                });
                $grid->type('事件')->sortable()->display(function ($val) {
                    if ($val == AuditModel::AUDIT_TYPE_SIGNIN) {
                        return AuditModel::getBadge('info', '签到');
                    }
                    return AuditModel::getBadge('primary', '签退');
                });

                $grid->content('原因')->display(function ($val) {
                    $obj = json_decode($val, true);
                    return $obj['error'] ?? '';
                });
                break;
            case 'attendance-modify':
                $grid->filter(function (Grid\Filter $filter) {
                    $filter->disableIdFilter();
                    $filter->like('user_name', '被修改者');
                    $filter->like('modify_user_name', '修改者');
                });
                $display_common_info = false;
                $grid->model()->where('type', AuditModel::AUDIT_TYPE_MODIFY_ATTENDANCE);
                $grid->attendance_no('编号')->sortable()->display(function ($val) {
                    return <<<eot
<a href="/admin/attendances/record/{$this->attendance_id}" target="_blank">{$this->attendance_id}</a>
eot;
                });

                $grid->attendance_id('考勤日期')->sortable()->display(function ($val) {
                    $attendance_record = $this->attendance;
                    if ($attendance_record) {
                        return $attendance_record->date;
                    }
                    return null;
                });

                $grid->user_name('被修改者')->sortable()->display(function ($val) {
                    return $val;
                });
                $grid->modify_user_name('修改者')->sortable()->display(function ($val) {
                    return $val;
                });
                $grid->event_type('类型')->sortable()->display(function ($val) {
                    if ($val == AuditModel::ATTENDANCE_TYPE_CREATE) {
                        return AuditModel::getBadge('primary', '新增');
                    }
                    return '修改';
                });
                $grid->content('修改内容')->display(function ($val) {
                    return AuditModel::getAttendanceModifyHumanContent($val);
                });
                break;
            case 'user':
                $grid->filter(function (Grid\Filter $filter) {
                    $filter->disableIdFilter();
                    $filter->like('user_name', '被修改者');
                    $filter->like('modify_user_name', '修改者');
                });
                $display_common_info = false;
                $grid->model()->where('type', AuditModel::AUDIT_TYPE_MODIFY_USER);
                $grid->user_name('被修改者')->sortable()->display(function ($val) {
                    return $val;
                });
                $grid->modify_user_name('修改者')->sortable()->display(function ($val) {
                    return $val;
                });
                $grid->content('修改内容')->display(function ($val) {
                    $obj = json_decode($val, true);
                    $data = [];
                    foreach (AuditModel::$USER_MODIFY_ITEMS as $key => $desc) {
                        if (isset($obj[$key])) {
                            $from_items = [];
                            $to_items = [];
                            $_from_ = $obj[$key]['from'];
                            $_to_ = $obj[$key]['to'];

                            switch ($key) {
                                case 'roles':
                                    $from_items = Role::whereIn('id', $_from_)->pluck('name');
                                    break;
                                case 'manage_departments':
                                    $from_items = Department::whereIn('id', $_from_)->pluck('name');
                                    break;
                                case 'manage_workplaces' :
                                    $from_items = WorkplaceModel::whereIn('id', $_from_)->pluck('name');
                                    break;
                                case 'manage_staffs' :
                                    $from_items = UserModel::whereIn('id', $_from_)->pluck('name');
                                    break;
                            }

                            switch ($key) {
                                case 'roles':
                                    $to_items = Role::whereIn('id', $_to_)->pluck('name');
                                    break;
                                case 'manage_departments':
                                    $to_items = Department::whereIn('id', $_to_)->pluck('name');
                                    break;
                                case 'manage_workplaces' :
                                    $to_items = WorkplaceModel::whereIn('id', $_to_)->pluck('name');
                                    break;
                                case 'manage_staffs' :
                                    $to_items = UserModel::whereIn('id', $_to_)->pluck('name');
                                    break;
                            }

                            $from = AuditModel::getBadge('secondary', $from_items->toArray());
                            $to = AuditModel::getBadge('primary', $to_items->toArray());
                            $data[] = "{$desc}: 从 {$from} 到 {$to}";
                        }
                    }
                    return implode("<br>", $data);
                });
                break;
            case 'online-user':
                $current_time = Carbon::now();
                $after_time = $current_time->addSeconds(-10)->toDateTimeString();
                $grid->filter(function (Grid\Filter $filter) {
                    $filter->disableIdFilter();
                    $filter->like('user_name', '用户名');
                });
                $grid->model()->where('type', AuditModel::AUDIT_TYPE_ONLINE_USER);
                $grid->model()->where('updated_at', '>=', $after_time);
                $grid->user_name('用户名')->sortable()->display(function ($val) {
                    return $val;
                });
                break;
        }

        $grid->model()->orderBy('id', 'desc');
        $grid->ip('IP')->sortable();
        $grid->address('地址')->sortable()->display(function ($val) {
            $val = str_replace('中国,', '', $val);
            $val = trim($val, " \,");
            return $val;
        });

        if ($display_common_info) {
            $grid->platform_name('平台')->sortable();
            $grid->platform_version('版本')->sortable();
            $grid->browser_name('浏览器')->sortable()->display(function ($val) {
                if ($val == 'Desktop') {
                    return AuditModel::getBadge('warning', $val);
                }
                return $val;
            });
            $grid->browser_version('版本')->sortable();
            $grid->device_name('设备')->sortable();
            $grid->device_type('类型')->sortable()->display(function ($val) {
                if ($val == AuditModel::DEVICE_TYPE_MOBILE) {
                    return AuditModel::getBadge('primary', '手机');
                }
                return AuditModel::getBadge('success', '电脑');
            });
        }


        $grid->updated_at('时间（中国时区）')->sortable()->display(function ($val) use ($timezone_china) {
            $dt = Carbon::parse($val);
            $dt->setTimezone($timezone_china);
            return $dt->toDateTimeString();
        });
        return $grid;
    }

    public function attendance(Content $content)
    {
        return $content
            ->header('打卡审计')
            ->description('查看所有的打卡操作记录')
            ->body($this->grid('attendance'));
    }

    public function attendanceModify(Content $content)
    {
        return $content
            ->header('考勤修改审计')
            ->description('查看所有的考勤修改操作记录')
            ->body($this->grid('attendance-modify'));
    }

    public function user(Content $content)
    {
        return $content
            ->header('用户审计')
            ->description('查看所有的用户敏感操作记录')
            ->body($this->grid('user'));
    }

    public function onlineuser(Content $content)
    {
        return $content
            ->header('在线用户')
            ->description('查看当前在线用户')
            ->body($this->grid('online-user'));
    }
}
