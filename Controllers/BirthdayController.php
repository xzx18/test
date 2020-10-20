<?php

namespace App\Admin\Controllers;

use App\Libs\Lunar;
use App\Models\Dingtalk;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use App\Models\UserModel;
use Carbon\Carbon;
use App\Models\Auth\OAPermission;

class BirthdayController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('本月员工生日')
            ->description(' ')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $is_admin = OAPermission::isAdministrator();
        $start_dt = Carbon::now()->firstOfMonth();
        $end_dt = Carbon::now()->endOfMonth();

        $users = [];
        do {
            $users = array_merge($users, UserModel::getBirthdayUsers($start_dt->toDateString()));
            $start_dt->addDay();
        } while ($start_dt <= $end_dt);
        $ids = array_column($users, 'id');

        $grid = new Grid(new UserModel());
        $grid->model()->whereIn('id', $ids)->orderBy('workplace');

        $grid->id('ID');
        $grid->name('用户名');
        $grid->workplace('工作地')->sortable();
        $grid->column('userid', '生日')->display(function ($userid) use ($users) {
            foreach ($users as $v) {
                if ($userid == $v->userid) {
                    return self::getUserBirthdayDesc($v);
                }
            }
            return '';
        });

        if ($is_admin) {
            $grid->birthday('公历出生日期');
            $grid->lunar_birthday('农历出生月日')->display(function ($val) {
                $dt_solar = Carbon::parse($this->birthday);
                $dt_lunar = Carbon::parse($val);
                if ($dt_solar->month == $dt_lunar->month && $dt_solar->day == $dt_solar->day) {
                    return '';
                }
                return $dt_lunar->month . '.' . $dt_lunar->day;
            });
        }

        $url = route('users.birthday.refresh');
        if ($is_admin) {
            $grid->tools(function (Grid\Tools $tools) use ($url) {
                $tools->append('<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="' . $url . '" target="_self"  class="btn btn-sm btn-primary"  title="重新计算本月的数据">
        <i class="fa fa-money"></i><span class="hidden-xs"> 更新生日数据</span>
    </a>
</div>');
            });
        }

        $grid->disableCreateButton();
        $grid->disableExport();
        $grid->disableActions();
        $grid->disableRowSelector();
        $grid->disableFilter();

        return $grid;
    }

    public function refresh()
    {
        if (OAPermission::isAdministrator()) {
            Dingtalk::syncEmployees();
            return redirect('/admin/users/birthday');
        }
    }

    private static function getUserBirthdayDesc($user)
    {
        if ($user->is_lunar) {
            $dt = Carbon::parse($user->lunar_birthday);
            $desc = '农历' . Lunar::getCapitalNum($dt->month, true) . Lunar::getCapitalNum($dt->day, false);
            if ($user->lunar_to_solar) {
                $dt = Carbon::parse($dt->year . '-' . $user->lunar_to_solar);
                $desc .= " ({$dt->month}月{$dt->day}日)";
            }
            return $desc;
        } else {
            $dt = Carbon::parse($user->birthday);
            return "{$dt->month}月{$dt->day}日";
        }
    }
}
