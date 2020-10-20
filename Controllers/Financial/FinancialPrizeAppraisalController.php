<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Auth\OAPermission;
use App\Models\FinancialAggregativePrizesModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Tab;

class FinancialPrizeAppraisalController extends Controller
{
    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        if (!self::hasPermission()) {
            OAPermission::error();
        }
        $current_user = UserModel::getUser();
        $is_administrator = $current_user->isRole('administrator');
        $is_hr = $current_user->isRole('hr');
        $is_financial = $current_user->isRole('financial.staff');

        $tab = new Tab();

        if ($is_administrator || $is_hr || $is_financial) {
            $tab->addLink('季度奖', route('financial.prize-season.index'), false);
            $tab->addLink('上季度贡献奖', route('financial.prize.contribution.index'), false);
        }
        $tab->add('上季度绩效工资', $this->grid(), true);
        if ($current_user->isRole('administrator') || $current_user->isRole('financial.staff')) {
            $tab->addLink('上季度分红', route('financial.share'), false);
        }
        $html = $tab->render();
        return $content
            ->header('上季度绩效工资')
            ->description('绩效系数: ' . FinancialAggregativePrizesModel::getAppraisalFactorsDesc())
            ->body($html);
    }

    public function refresh()
    {
        if (!self::hasPermission()) {
            OAPermission::error();
        }

        $dt = Carbon::now()->addQuarter(-1);
        FinancialAggregativePrizesModel::refreshQuarterAppraisalPrizes($dt->year, $dt->quarter);
        return redirect(Route('financial.prize.appraisal'));
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $content = '';

        $url = route('financial.prize.appraisal.refresh');
        $content .= <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$url}" target="_self"  class="btn btn-sm btn-primary"  title="重新计算上季度绩效工资数据">
        <i class="fa fa-money"></i><span class="hidden-xs">&nbsp;&nbsp;刷新绩效工资数据</span>
    </a>
</div>
eot;

        $dt = Carbon::now();
        $dt->addQuarter(-1);
        $content .= "<h5> {$dt->year} 年 {$dt->quarter} 季度</h5>";
        $content .= '<table  border="1" width="1000px" class="finacial"><tr height="30px"><td>员工名</td><td>工作地</td><td>职级</td><td>绩效工资（月）</td><td>绩效等级</td><td>绩效系数</td><td>时间比例</td><td>金额</td></tr>';
        $sort_func = function ($a, $b) {
            $place_a = $a[1];
            $place_b = $b[1];
            if ($place_a == $place_b) {
                $user_a = $a[0];
                $user_b = $b[0];
                if ($user_a == $user_b) {
                    return 0;
                }
                return $user_a < $user_b ? -1 : 1;
            }
            return $place_a < $place_b ? -1 : 1;
        };

        $value = FinancialAggregativePrizesModel::getAppraisalPrize($dt->year, $dt->quarter * 3);
        if (!empty($value)) {
            $data = json_decode($value->value, true);
            usort($data, $sort_func);
            foreach ($data as $datum) {
                $style = self::getPlaceStyle($datum[1]);
                $content .= <<<EOT
<tr {$style}><td><b>{$datum[0]}</b></td><td>{$datum[1]}</td><td>{$datum[2]}</td><td>{$datum[3]}</td><td>{$datum[4]}</td><td>{$datum[5]}</td><td>{$datum[6]}</td><td><b>{$datum[7]}</b></td></tr>
EOT;
            }
        }

        /*
         * $data[$user->id] = [
                $user->name,                  // 人名
                $user->workplace_info->title, // 工作地
                $job_grade_info->name,        // 职级
                $performance_salary,          // 绩效工资标准
                $result->result,              // 绩效等级
                $factor,                      // 绩效系数
                $ratio,                       // 时间比例
                $money                        // 金额
            ];
         */
        $content .= "</table>";
        return $content;
    }

    private static function hasPermission()
    {
        $current_user = UserModel::getUser();
        return $current_user->isRole('administrator') || $current_user->isRole('hr') || $current_user->isRole('financial.staff');
    }

    public static function getPlaceStyle($place)
    {
        switch ($place) {
            case  'Madagascar' :
                $style = "bgcolor=#ECECFF";
                break;
            case  '南昌' :
                $style = "bgcolor=#ECFFFF";
                break;
            case  '武汉' :
                $style = "bgcolor=#FFD9EC";
                break;
            case  '西安' :
                $style = "bgcolor=#DEDEBE";
                break;
            case  '深圳' :
                $style = "bgcolor=#F1E1FF";
                break;
            case  '无锡' :
                $style = "bgcolor=#FFE4CA";
                break;
            default  :
                $style = "bgcolor=#AD5A5A";
                break;
        }
        return $style;
    }

}
