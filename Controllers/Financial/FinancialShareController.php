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

class FinancialShareController extends Controller
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
            $tab->addLink('上季度绩效工资', route('financial.prize.appraisal'), false);
        }

        $tab->add('上季度分红', $this->grid(), true);
        $html = $tab->render();

        return $content
            ->header('分红')
            ->description('分红数据')
            ->body($html);
    }

    public function refresh()
    {
        if (!self::hasPermission()) {
            OAPermission::error();
        }

        $dt = Carbon::now()->addQuarter(-1);
        FinancialAggregativePrizesModel::refreshQuarterShares($dt->year, $dt->quarter);
        return redirect(Route('financial.share'));
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $content = '';

        $url = route('financial.share.refresh');
        $content .= <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$url}" target="_self"  class="btn btn-sm btn-primary"  title="从后台刷新上季度分红数据">
        <i class="fa fa-money"></i><span class="hidden-xs">&nbsp;&nbsp;刷新分红数据</span>
    </a>
</div>
eot;

        $dt = Carbon::now();
        $dt->addQuarter(-1);
        $content .= "<h5> {$dt->year} 年 {$dt->quarter} 季度</h5>";
        $content .= '<table  border="1" width="1000px" class="finacial"><tr height="30px"><td>员工名</td><td>总额</td><td>实发(80%)</td></tr>';

        $value = FinancialAggregativePrizesModel::getShare($dt->year, $dt->quarter * 3);
        if (!empty($value)) {
            $data = json_decode($value->value, true);
            if (!empty($data)) {
                foreach ($data as $datum) {
                    $content .= "<tr bgcolor='#EBD6D6'><td>{$datum[0]}</td><td>{$datum[1]}</td><td><b>{$datum[2]}</b></td></tr>";
                }
            }
        }
        $content .= "</table>";
        return $content;
    }

    private static function hasPermission()
    {
        $current_user = UserModel::getUser();
        return $current_user->isRole('administrator') || $current_user->isRole('financial.staff');
    }

}
