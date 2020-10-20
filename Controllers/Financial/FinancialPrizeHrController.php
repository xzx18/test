<?php

namespace App\Admin\Controllers;

use Carbon\Carbon;
use App\Models\UserModel;
use App\Models\FinancialAggregativePrizesModel;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Widgets\Box;
use Illuminate\Support\Facades\Input;

class FinancialPrizeHrController extends Controller
{

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        $type = Input::get('type') ? Input::get('type') : 1;
        $current_month = Carbon::now()->addMonth(-1)->format('Y-m');
        $route = Route('financial.prize.hr');
        $box_title = '';
        $current_user = UserModel::getUser();
        if ($current_user->isRole('administrator')) {  //仅管理员可见
            $box_title = <<<EOT
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$route}?type=3" target="_self"  class="btn btn-sm btn-primary"  title="重新计算本月的数据">
        <i class="fa fa-money"></i><span class="hidden-xs">&nbsp;&nbsp;刷新人事奖奖数据</span>
    </a>
</div>
EOT;
        }
        $box_title .= <<<EOT
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$route}?type=1" target="_self"  class="btn btn-sm btn-primary"  title="人事奖统计">
        <i class="fa fa-money"></i><span class="hidden-xs">&nbsp;&nbsp;人事奖统计</span>
    </a>
</div>
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$route}?type=2" target="_self"  class="btn btn-sm btn-primary"  title="人事奖来源">
        <i class="fa fa-money"></i><span class="hidden-xs">&nbsp;&nbsp;人事奖来源</span>
    </a>
</div>
<table>
    <tr>
        <td>
            <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
                <input style="width: 100px;font-weight: bold; text-align: center;" type="text" value="{$current_month}" class="form-control date date-picker">
            </div>
        </td>
    </tr>
</table>
EOT;

        $box_content = <<<EOT
<div id="box-info" data-route='{$route}'></div>
EOT;

        $box = new Box($box_title, $box_content);
        $box->style('primary')->view("admin.widgets.box");
        $body = $box->render();
        $body .= <<<EOT
            <script>
            $(function () {
                var month = "{$current_month}";
                var datetimepicker_options = {
                    format: 'YYYY-MM'
                };
                $('.content .box .box-title .date-picker').datetimepicker(datetimepicker_options).on("dp.change", function (e) {
                    month = $(this).val();
                    fetchPrizeInfo();
                });

                fetchPrizeInfo();

                function fetchPrizeInfo() {
                    var box = $("#box-info");
                    NProgress.configure({
                        parent: '.content .box-header'
                    });
                    NProgress.start();
                    var url = box.data("route") + "/" + month+'?type='+{$type};
                    $.get(url, function (response) {
                        box.html(response);
                        NProgress.done();
                    })
                }
            })
            </script>
EOT;
        switch ($type) {
            case 2 :
                $title = '人事奖来源';
                break;
            case 3 :
                $title = '人事奖刷新';
                break;
            default:
                $title = '人事奖统计';
        }
        return $content
            ->header($title)
            ->description('可以计算选定月份的详细数据')
            ->body($body);
    }


    public function getPrizeRender($month)
    {
        $type = Input::get('type');
        // $month 是 2018-09 这样的格式
        $a = explode('-', $month);
        if (is_array($a) && count($a) == 2 && is_numeric($a[0]) && is_numeric($a[1])) {
            if (self::hasPermission()) {
                if ($type == 3) {
                    return $this->refresh($a[0], $a[1]);
                    die;
                } else {
                    $data = [];
                    $data = FinancialAggregativePrizesModel::getHrPrize($a[0], $a[1]);
                    $body = '';
                    if (empty($data)) {
                        $body = '<h3 align="center">无数据（如果有问题，请联系管理员）</h3>';
                        return $body;
                    }
                    $prizes = \GuzzleHttp\json_decode($data->value, true);
                    if ($type == 2) {
                        return $this->calculateOrigin($prizes, $body, $type);
                    } elseif ($type == 1) {
                        return $this->calculate($prizes, $body, $type);
                    }
                }
            }
        }
        dd('aaa');
        return "";
    }

    private function calculateOrigin($prizes, $body, $type)
    {
        //构造数据
        $ids = [];
        foreach ($prizes as $prize) {
            foreach ($prize['prize']['works'] as $work) {
                $work['to'] = $prize['name'];
                $work['to_rank'] = $prize['rank'];
                $ids[] = $work['person_id'];
                $users[] = $work;
            }
        }
        $ids = array_unique($ids);
        foreach ($ids as $id) {
            foreach ($users as $user) {
                if ($user['person_id'] == $id) {
                    $new_data[$id][] = $user;
                }
            }
        }
        foreach ($new_data as $d) {
            $sum = 0;
            $data = [];
            foreach ($d as $v) {  //获取总数
                $sum += $v['money'];
            }
            $data[] = $d;
            $list_html = $this->showHrPrize($data, $type);
            $body .= "<br/><h4>{$d[0]['person_name']} <b>{$sum}</b></h4>{$list_html}";
        }
        return $body;
    }

    private function calculate($prizes, $body, $type)
    {
        foreach ($prizes as $prize) {
            if ($type == 1 && $prize['rank'] >= 10) {
                // 10级以上员工不参与奖金分配，这里就不用显示
                continue;
            }

            //构造数据
            $sort_id = array_column($prize['prize']['works'], 'person_id');
            array_multisort($sort_id, SORT_DESC, $prize['prize']['works']);
            $prize['prize']['to'] = $prize['name'];
            //数据分层
            $ids = [];
            $new_data = [];
            foreach ($prize['prize']['works'] as $work) {
                $ids[] = $work['person_id'];
            }
            $ids = array_unique($ids);
            foreach ($ids as $id) {
                foreach ($prize['prize']['works'] as $user) {
                    if ($user['person_id'] == $id) {
                        $new_data[$id][] = $user;
                    }
                }
            }
            $list_html = $this->showHrPrize($new_data, $type, 0);
            $body .= "<br/><h4>{$prize['name']} <b>{$prize['prize']['sum']}</b></h4>{$list_html}";
        }
        if (empty($body)) {
            $body = '本月无记录';
        }
        return $body;
    }


    private function showHrPrize($new_data, $type)
    {
        $details = [];
        foreach ($new_data as $d) {
            $i = 1;
            foreach ($d as $w) {
                $rowspan = "rowspan = " . count($d);
                $money = $w['money'];
                if ($type == 1) {
                    $person = "<td $rowspan>{$w['person_name']}</td>";
                    $to = "";
                    $money = "<td><b>$money</b></td>";
                } else {
                    $person = "";
                    if ($w['to_rank'] && $w['to_rank'] >= 10) {
                        $to = "<td style='color: #aaaaaa'>{$w['to']}</td>";
                        $money = "<td style='color: #cccccc'>$money</td>";
                    } else {
                        $to = "<td>{$w['to']}</td>";
                        $money = "<td><b>$money</b></td>";
                    }
                }
                if ($i == 1) {
                    $details[] = "{$person }<td {$rowspan}>{$w['person_rank']}级</td><td {$rowspan}> {$w['event']}</td><td {$rowspan}>  {$w['hired_date']}</td><td {$rowspan}> {$w['regular_date']}</td><td>{$w['work']}</td>{$money}{$to}";
                } else {
                    $details[] = "<td>{$w['work']}</td>$money{$to} ";
                }
                $i++;
            }
        }
        $details = $this->getUlListHtml($details, $type);
        return $details;
    }

    private function getUlListHtml($items, $type)
    {
        if ($type == 1) {
            $person = "<th>员工</th>";
            $to = "";
        } else {
            $person = "";
            $to = "<th>奖金去向</th>";
        }
        if ($items) {
            $html[] = '<table  border="1" width="1000px" class="finacial"><tr height="30px">' . $person . '<th>等级</th><th>事项</th><th>入职日期</th><th>转正日期</th><th>负责工作</th><th>奖金</th>' . $to . '</tr>';
            foreach ($items as $item) {
                $html[] = "<tr height='30px'>$item</tr>";
            }
            $html[] = '</table>';
            return implode('', $html);
        }
        return '';
    }

    public function refresh($year, $month)
    {
        if (self::hasPermission()) {
            $dt = new Carbon($year . '-' . $month);
            FinancialAggregativePrizesModel::refreshMonthHumanPrizes($dt);
            $body = "<h3 align='center'>{$year}年{$month}月数据刷新成功</h3>";
            echo $body;
        }
        die;  //减少加载速度
    }

    private static function hasPermission()
    {
        $current_user = UserModel::getUser();
        return $current_user->isRole('administrator') || $current_user->isRole('hr') || $current_user->isRole('financial.staff');
    }
}
