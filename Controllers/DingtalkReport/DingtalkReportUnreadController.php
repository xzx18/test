<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DingtalkReportUnreadModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use function GuzzleHttp\describe_type;

class DingtalkReportUnreadController extends Controller
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
            ->header('未读日志列表')
            ->description(' ')
            ->body($this->grid());
    }

    public function syncUnread()
    {
        if ($this->canSync()) {
            DingtalkReportUnreadModel::syncUnread(true);
        }
        return redirect()->route('dingtalkreport.unread');
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new DingtalkReportUnreadModel());
        $grid->disableCreateButton();
        $grid->disableRowSelector();
        $grid->disableActions();
        $grid->disableExport();

        $grid->model()->orderBy('date', 'desc')->orderBy('hour', 'desc')->orderBy('unreaded_count', 'desc');

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableDelete();
            $actions->disableView();
        });

        $grid->id('ID')->sortable();
        $grid->date('日期')->display(function ($val) {
            return $val;
        });
        $grid->user('用户名')->display(function ($val) {
            return $val['name'];
        });
        $grid->unreaded_count('未读条数')->sortable();
        $grid->update_way('更新方式')->display(function ($val) {
            return $val == 1 ? '手动' : '自动';
        });

        $timezone_china = config('app.timezone_china');
        $grid->updated_at(trans('admin.updated_at'))->display(function ($val) use ($timezone_china) {
            $dt = Carbon::parse($val)->setTimezone($timezone_china);
            return $dt->toDateTimeString();
        });

        if ($this->canSync()) {
            $grid->tools(function (Grid\Tools $tools) {
                $url = route('dingtalkreport.syncunread');
                $html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$url}" target="_self"  class="btn btn-sm btn-primary"  title="每小时可同步一次未读日志">
        <i class="fa fa-money"></i><span class="hidden-xs">&nbsp;&nbsp;同步未读日志</span>
    </a>
</div>
eot;
                $tools->append($html);
            });
        }

        return $grid;
    }

    private function canSync()
    {
        $latest = DingtalkReportUnreadModel::orderBy('id', 'desc')->first();
        return !$latest || Carbon::parse($latest->updated_at)->addHour(1) < Carbon::now();
    }
}
