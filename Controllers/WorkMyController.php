<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Auth\OAPermission;
use App\Models\CalendarModel;
use App\Models\UserModel;
use App\Models\WorkEventModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Illuminate\Http\Request;

class WorkMyController extends Controller
{
    use HasResourceActions;

    public function index(Content $content)
    {
        if (!isLoadedFromClient(false) && !isDev()) {
            $body = OAPermission::ERROR_HTML;
        } else {
            $current_user = UserModel::getUser();
            $body = view('oa.mywork',
                [
                    'user_id' => $current_user->id,
                    'current_url' => \request()->url(),
                    'translate' => tr("Continuous working duration: %s, Total working duration: %s"),
                ]
            );
            $box = new Box(tr("Work Operate"), $body);
            $body = $box->render();
            $body .= $this->grid()->render();
        }

        return $content
            ->header(tr("Work Records"))
            ->description(' ')
            ->body($body);
    }

    protected function grid()
    {
        $translated_weeks = CalendarModel::getTranslatedWeeks();

        $current_user = UserModel::getUser();
        $grid = new Grid(new WorkEventModel());
        $grid->disableFilter();
        $grid->disableExport();
        $grid->disableActions();
        $grid->disableRowSelector();
        $grid->disableCreateButton();
        $grid->id('ID')->sortable();
        $grid->date(tr("Date"))->sortable()->display(function ($val) use ($translated_weeks) {
            $dt = Carbon::parse($val);
            return sprintf("%s %s", $val, $translated_weeks[$dt->dayOfWeek]);
        });
        $grid->start_at(tr("Start Time"))->sortable()->display(function ($val) {
            return "<span class=\"badge badge-success\">$val</span>";
        });
        $grid->end_at(tr("End Time"))->sortable()->display(function ($val) {
            return "<span class=\"badge badge-warning\">$val</span>";
        });

        $grid->work_seconds(tr("Work Duration"))->sortable()->display(function ($val) {
            return round($val / 3600, 2) . " " . tr("Hour(s)");
        });
        $grid->model()->where('user_id', $current_user->id)->orderBy('id', 'desc');
        return $grid;
    }

    public function storeEvent(Request $request)
    {

        $type = $request->post('type');
        $current_user = UserModel::getUser();
        $current_time = UserModel::getUserTime();


        $content = '';
        $html = '';
        $result = false;

        if ($type == 'start') {
            //不知道什么原因，会重复保存2次；先查询一下是否存在，否则不保存  2019-05-08
            $exist_record = WorkEventModel::where('user_id', $current_user->id)->where('date', $current_time->toDateString())->where('start_at', $current_time->toTimeString())->first();
            if (!$exist_record) {
                $model = new   WorkEventModel();
                $model->date = $current_time->toDateString();
                $model->start_at = $current_time->toTimeString();
                $model->user_id = $current_user->id;
                $result = $model->save();
            }
        } else {
            $last_record = WorkEventModel::where('user_id', $current_user->id)
                ->where('date', $current_time->toDateString())
                ->where('start_at', '<>', '')
                ->orderBy('id', 'desc')
                ->first();
            $last_record->end_at = $current_time->toTimeString();
            $last_record->work_seconds = $current_time->diffInSeconds(Carbon::parse($last_record->start_at, $current_time->timezone));
            $result = $last_record->save();

            //统计今日工作时长
            $records = WorkEventModel::where('user_id', $current_user->id)
                ->where('date', $current_time->toDateString())
                ->where('start_at', '<>', '')
                ->where('end_at', '<>', '')
                ->get();
            $total_work_seconds = 0;
            foreach ($records as $record) {
                $total_work_seconds += Carbon::parse($record->end_at)->diffInSeconds(Carbon::parse($record->start_at));
            }
            $total_work_hours = round($total_work_seconds / 3600, 2);

            $content = sprintf("Today(%s) total working : %s hour(s)", $current_time->toDateString(), $total_work_hours);
            $box_content = "<i class='fa fa-clock-o'></i> $content";
            $box = new Box(tr("Work Statistics"), $box_content);
            $box->style('success');
            $html = $box->render();
        }


        if ($result) {
            $o = [
                'state' => 1,
                'message' => tr("Success"),
                'content' => $content,
                'html' => $html,
            ];
        } else {
            $o = [
                'state' => 0,
                'message' => tr("Failed"),
                'content' => $content,
                'html' => $html,
            ];
        }
        return $o;
    }
}
