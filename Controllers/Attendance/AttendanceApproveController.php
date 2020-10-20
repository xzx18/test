<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApproveModel;
use App\Models\Auth\OAPermission;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;

class AttendanceApproveController extends Controller
{
    use HasResourceActions;

    public function index(Content $content)
    {
        return $content
            ->header('假勤列表')
            ->description(' ')
            ->body($this->grid());
    }

    protected function grid()
    {
        $user = UserModel::getUser();
        $is_admin = OAPermission::isAdministrator();
        $is_hr = OAPermission::isHr();

        $grid = new Grid(new ApproveModel());

        $grid->id('ID')->sortable();

        $grid->column('user.name', '用户名')->sortable();
        $grid->event_type('事件类型')->display(function ($val) {
            return ApproveModel::getEventTypeHuman($val);
        })->sortable();
        $grid->approve_type('审批类型')->display(function ($val) {
            return $val;
        })->sortable();

        $grid->status_type('状态')->display(function ($val) {
            $style = 'info';
            switch ($val) {
                case 'completed':
                    $style = 'success';
                    break;
                case 'terminated':
                    $style = 'danger';
                    break;
                case 'start':
                    $style = 'primary';
                    break;
                case 'revoke':
                    $style = 'warning';
                    break;
                case 'cancel':
                    $style = 'dark';
                    break;
            }
            return "<span class='badge badge-$style font-weight-normal'>$val</span>";
        })->sortable();
        $grid->result_type('结果')->display(function ($val) {
            $style = '';
            if ($val == 'agree') {
                $style = 'success';
            }
            if ($val == 'refuse') {
                $style = 'danger';
            }
            if ($style) {
                return "<span class='badge badge-$style font-weight-normal'>$val</span>";
            }
            return $val;
        })->sortable();


        $grid->start_time('开始时间');
        $grid->end_time('结束时间');
        $grid->duration('时长')->display(function () {
            if ($this->start_time && $this->end_time) {
                $start = Carbon::parse($this->start_time);
                $end = Carbon::parse($this->end_time);
                return str_replace('after', '', $end->diffForHumans($start, false, true));
            }

        });

        if ($is_admin) {
            $grid->reason('事由')->display(function ($val) {
                return "<div style='max-width: 400px'>$val</div>";
            });
        }


        $grid->created_at(trans('admin.created_at'));
        $grid->updated_at(trans('admin.updated_at'));

        if ($is_admin || $is_hr) {
            $grid->model()->where('user_id', '>', 0);
        } else {
            $grid->model()->where('user_id', $user->id);
        }

        $grid->model()->orderBy('start_time', 'desc');


        $grid->filter(function (Grid\Filter $filter) {
            $filter->in('user_id', '用户名')->multipleSelect(UserModel::getAllUsersPluck());
            $filter->in('event_type', '事件类型')->multipleSelect(ApproveModel::$EVENT_TYPES);
            $filter->in('status_type', '状态')->multipleSelect(ApproveModel::getStatusTypesWithPluck());
            $filter->in('result_type', '结果')->multipleSelect(ApproveModel::getResultTypesWithPluck());
            $filter->where(function ($query) {
                $input = $this->input;
                $dt_start = Carbon::parse($input);
                $dt_end = $dt_start->copy()->endOfMonth();
                $query->whereDate('start_time', '>=', $dt_start->toDateString())->whereDate('end_time', '<=', $dt_end->toDateString());
            }, '月份')->yearMonth();
        });

        $grid->disableRowSelector();
        $grid->disableCreateButton();
        $grid->disableActions();
        $grid->disableExport();

        return $grid;
    }

}
