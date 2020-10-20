<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Auth\OAPermission;
use App\Models\FinancialJobGradeModel;
use App\Models\WorkplaceModel;
use App\Models\UserModel;
use App\Models\FinancialJobGradeUserModel;
use Carbon\Carbon;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;

class FinancialJobGradeUserDiagramController  extends Controller
{
    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        if (!OAPermission::canManageJobGrade()) {
            return OAPermission::ERROR_HTML;
        }

        $url = Route('jobgrade.list');
        $body = <<<EOT
<div class="btn-group pull-right btn-group-import" style="margin-left: 10px">
    <a href="{$url}" target="_self"  class="btn btn-sm btn-primary"  title="返回人员列表">
        <i class="fa fa-list"></i><span class="hidden-xs">&nbsp;返回人员列表&nbsp;</span>
    </a>
</div>
EOT;

        $places = OAPermission::getAuthorizedJobGradeWorkplaces();
        $places_count = sizeof($places);
        if ($places_count > 0) {
            foreach ($places as $id => $name) {
                $body .= <<<EOT
<button type="button" class="btn btn-light btn-workplace" data-wid={$id} >{$name}</button>
EOT;
            }
        }

        $route = Route('financial.jobgrade.user.diagram');
        $box_attendance_content = <<<EOT
<div id="box-diagram" data-route='{$route}'></div>
EOT;

        if ($places_count == 1) {
            $box_attendance_content .= $this->getWorkplaceJobGrades(array_keys($places)[0]);
        }

        $box_attendance = new Box('历史记录表', $box_attendance_content);
        $box_attendance->style('primary')->view("admin.widgets.box");
        $body .= $box_attendance->render();

        if ($places_count > 1) {
            $body .= <<<EOT
            <script>
                    $('.btn-workplace').click(function(e) {
                        var selected_workplace_id=$(this).data('wid');
                        var box= $("#box-diagram");
                        NProgress.configure({ parent: '.content .box-header' });
                        NProgress.start();

                        var url=box.data("route") + "/"+selected_workplace_id;
                        $.get(url,function(response ) {
                            box.html(response);
                            NProgress.done();
                        })
                    });
            </script>
EOT;
        }

        return $content
            ->header('职级工资图')
            ->description('选择地域查看图表')
            ->body($body);;
    }

    public function getWorkplaceJobGrades($workplace_id)
    {
        $content = '';

        $places = OAPermission::getAuthorizedJobGradeWorkplaces();
        if (!in_array($workplace_id, array_keys($places))) {
            return $content;
        }

        $data = self::getJobGradeUserDataOfWorkplace($workplace_id);
        $data1 = $data[0];
        $data2 = $data[1];

        $dt_now = Carbon::now();
        $n = 0;
        foreach ($data2 as $key => $value) {
            if (sizeof($value) == 0) {
                continue;
            }

            $user_info = $data1[$key];
            $index = ($n + 1) . ' (' . $key . ')';
            $name = $user_info['name'] . '<br/>' . $user_info['truename'];
            $in_job = $user_info['deleted_at'] == null ? 1 : 0;

            $is_even_row = $n % 2;
            $style = "td-text td-back-{$is_even_row} td-fore-{$in_job}";

            $tr1 = '</tr>';
            $tr2 = '</tr>';
            $tr3 = '</tr>';
            $tr4 = '</tr>';

            if ($user_info['deleted_at']) {
                $china_tz = config("app.timezone_china");
                $dt_quit = Carbon::parse($user_info['deleted_at'], $china_tz)->toDateString();
                $tr1 = "<td rowspan='3' class='{$style}'>离职</td>" . $tr1;
                $tr4 = "<td class='{$style}' >{$dt_quit}</td>" . $tr4;
            }

            $current_got = false;
            for ($i = sizeof($value) - 1; $i >= 0; $i--) {
                $item = $value[$i];
                $salary = $item['salary'];
                $mark = $item['mark'] ?? '';

                $salary_style = $style;
                $from_time = Carbon::parse($item['from_time']);
                if ($in_job && !$current_got && $from_time <= $dt_now) {
                    $current_got = true;
                    $salary_style .= ' td-light';
                }
                $from_time = $from_time->toDateString();

                $jobgrade_id = $item['jobgrade_id'];
                $jobgrade = FinancialJobGradeModel::find($jobgrade_id)->name ?? '';

                $tr1 = "<td class='{$salary_style}' >{$salary}</td>" . $tr1;
                $tr2 = "<td class='{$style}' >{$jobgrade}</td>" . $tr2;
                $tr4 = "<td class='{$style}' >{$from_time}</td>" . $tr4;

                if (strlen($mark) > 8) {
                    $tr3 = "<td class='{$style}' title='{$mark}'>{$mark}</td>" . $tr3;
                } else {
                    $tr3 = "<td class='{$style}' >{$mark}</td>" . $tr3;
                }
            }

            $index_name = $index . "<br/>" . $name;
            $backgroud = 'White';
            $color = 'Black';
            if ($is_even_row) {
                $backgroud = '#D3F3F7';
            }
            if (!$in_job) {
                $color = '#AAAAAA';
            }
            $th_style = "min-width:100px; position:-webkit-sticky; position:sticky; left:-1px; color:{$color}; background:{$backgroud};text-align: center;border: 1px solid Black;";
            $tr1 = "<tr class='tr-row'><th rowspan=4 scope='row' style='{$th_style}'>{$index_name}</th><td class='{$style}'>工资</td>" . $tr1;
            $tr2 = "<tr class='tr-row'><td class='{$style}'>职位级别</td>" . $tr2;
            $tr3 = "<tr class='tr-row'><td class='{$style}'>原因</td>" . $tr3;
            $tr4 = "<tr class='tr-row'><td class='{$style}'>生效时间</td>" . $tr4;

            $content .= $tr1;
            $content .= $tr2;
            $content .= $tr3;
            $content .= $tr4;

            $n++;
        }

        $content = <<<EOT
<script>
function setDivHeight() {
    var bodyHeight = document.documentElement.clientHeight;
    bodyHeight = bodyHeight - 250;
    document.getElementsByClassName("nui-scroll")[0].style.height = bodyHeight+'px';
}
window.addEventListener("resize", setDivHeight);
setDivHeight();
</script>
<div class="nui-scroll" style="height:800px"><table border=0 cellpadding=0 cellspacing=0 style="border-collapse:collapse">{$content}</table></div>
EOT;

        return $content;
    }

    private static function getJobGradeUserDataOfWorkplace($workplace_id)
    {
        $users = WorkplaceModel::getUsers($workplace_id, true);
        $users = UserModel::sortByHiredDate($users);

        $data1 = [];
        $data2 = [];
        foreach ($users as $user) {
            $data1[$user->id] = [
                'name' => $user->name,
                'truename' => $user->truename,
                'deleted_at' => $user->deleted_at
            ];
            $data2[$user->id] = [];
        }

        $records = FinancialJobGradeUserModel::whereIn('user_id', array_keys($data2))->where('confirmed', 1)->get()->toArray();
        foreach ($records as $record) {
            $data2[$record['user_id']][] = $record;
        }

        $sort_callback = function ($m1, $m2) {
            $dt1 = Carbon::parse($m1['from_time']);
            $dt2 = Carbon::parse($m2['from_time']);
            return $dt1 == $dt2 ? 0 : ($dt1 > $dt2 ? 1 : -1);
        };

        foreach ($data2 as $key => $value) {
            usort($data2[$key], $sort_callback);
        }

        return [$data1, $data2];
    }


}
