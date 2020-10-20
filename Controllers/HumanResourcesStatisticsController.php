<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use App\Models\UserModel;
use Illuminate\Support\Facades\Input;
use Encore\Admin\Widgets\Box;
use App\Models\WorkplaceModel;
use App\Models\Department;
use App\Models\Auth\OAPermission;
use Encore\Admin\Admin;
use App\Helpers\PoCostsCalcHelper;

class HumanResourcesStatisticsController extends Controller
{
    use HasResourceActions;
    protected static $po_title = ['行标签', '幕享', '蜜蜂剪辑', '轻闪PDF', '右糖', '智能图像', '脑图', '其他', '中台', '组织建设', '管理', '汇总'];
    protected static $pos = ['letsview', 'beecut', 'pdf', 'lightmv', 'pickwish', 'mindmap', 'others', 'platform', 'organizational', 'manage', 'total'];

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content, Request $request)
    {
        $body = '';

        if (OAPermission::has('oa.human.costs')) {
            $current_month = Carbon::now()->addMonth(-1)->format('Y-m');
            $route = route('human.po.statistics');

            $box_title = <<<EOT
<table>
	<tr>
		<td>
			<div class="input-group">
			<span class="input-group-addon"><i class="fa fa-calendar fa-fw"/></span>
			<input style="width: 100px;font-weight: bold; text-align: center;" type="text" value="{$current_month}" class="form-control date date-picker" >
			</div>
		</td>

		<td>
			<div class="btn-group pull-right btn-group-import" style="margin-left: 88px">
			<input type="button" class="btn btn-sm btn-default" onClick="selectText()" value="点击选中全部内容" title="选中按Ctrl+C复制后可以直接粘贴到Excel里面">
			</div>
		</td>
	</tr>
	<tr>
		<td colspan="2">
        <div class="checkbox">
            <label><input id="chk_divmgr" type="checkbox"> 分摊管理费用</label>
        </div>
        </td>
    </tr>
</table>
EOT;

            $box_content = <<<EOT
<div id="box-info" data-route="{$route}"></div>
EOT;

            $box = new Box($box_title, $box_content);
            $box->style('primary')->view("admin.widgets.box");
            $body .= $box->render();
            $body .= <<<EOT
            <script>
            $(function () {
                var month = "{$current_month}";
                var datetimepicker_options = {
                    format: 'YYYY-MM'
                };
                $('.content .box .box-title .date-picker').datetimepicker(datetimepicker_options).on("dp.change", function (e) {
                    month = $(this).val();
                    fetchData();
                });
                
                $("#chk_divmgr").click(function () {
                    fetchData();
                });

                fetchData();

                function fetchData() {
                    var divmgr = $("#chk_divmgr").prop("checked") ? 1 : 0;
                    var box = $("#box-info");
                    NProgress.configure({
                        parent: '.content .box-header'
                    });
                    NProgress.start();
                    var url = box.data("route") + "/" + month + "?divmgr=" + divmgr;
                    $.get(url, function (response) {
                        box.html(response);
                        NProgress.done();
                    })
                }
            })
            
            function selectText() {
                var text = document.getElementById("data_str");
                if (text) {
                    var selection = window.getSelection();
                    var range = document.createRange();
                    range.selectNodeContents(text);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            }
    
            </script>
EOT;
        }

        return $content
            ->header('PO统计')
            ->description(' ')
            ->body($body);
    }


    public function getPoStatisticsRender(Content $content, Request $request, $month)
    {
        $divmgr = $request->get('divmgr') ?? 0;
        $data = PoCostsCalcHelper::calcCostsOfMonth($month, $divmgr);

        //dd($data);
        $data_str = $this->getTotalTable($data['total'], '总和')
            . $this->getUlTable($data['salary'], '工资')
            . $this->getUlTable($data['shebao'], '社保')
            . $this->getUlTable($data['gongjijin'], '公积金');
        return "<div id='data_str'>{$data_str}</div>";
    }

    public function getUlTable($salary_data, $title)
    {
        $html[] = "<h4><b>{$title}</b></h4>";
        $str = '<table  border="1" width="100%" class="finacial" ><tr style = "color: white;background: #3c8dbc;height: 36px;font-size: 16px">';
        foreach (self::$po_title as $title) {
            $str .= "<td>{$title}</td>";
        }
        $str .= '</tr>';
        $html[] = $str;

        $data = $salary_data['data'];
        $desc = $salary_data['desc'];
        foreach ($data as $wordplace_id => $place) {  //999地区最大，他会在最后显示，没有问题
            $workplace = WorkplaceModel::getWorkplaceNameById($wordplace_id);
            $total_depart = $place[999]; //部门id，保证每个地区的和在最上面。
            $workplace_des = $workplace ? $workplace . '(求和)' : '地区总和';

            $str2 = "<tr height='30px' style='background: #DDEBF7'><th><b>{$workplace_des}</b></th>";
            foreach (self::$pos as $po) {
                $v = round($total_depart[$po], 2);
                $str2 .= "<th>{$v}</th>";
            }
            $str2 .= "</tr>";
            $html[] = $str2;

            foreach ($place as $dapartment_id => $depart) {
                if ($dapartment_id == 999) {
                    continue;
                }
                $department_name = Department::where('id', $dapartment_id)->value('name');
                if (!$department_name) {
                    $department_name = '未知部门';
                }
                $str = "<tr height='30px'><td style='width: 160px'>&nbsp&nbsp&nbsp{$department_name}</td>";
                foreach (self::$pos as $po) {
                    $v = round($depart[$po], 2);
                    $d = $desc[$wordplace_id][$dapartment_id][$po] ?? '';
                    $is_total = $po == 'total';
                    $str .= $is_total ? "<td><b>{$v}</b></td>" : "<td title='{$d}'>{$v}</td>";
                }
                $str .= "</tr>";
                $html[] = $str;
            }

            if ($wordplace_id != 999) {
                $html[] = "<tr height='30px'><td colspan='12'></td></tr>";
            }
        }

        $html[] = '</table>';
        return implode('', $html);
    }


    public function getTotalTable($total_data, $title)
    {
        $html[] = "<h4>{$title}</b></h4>";

        $str = '<table  border="1" width="100%" class="finacial" ><tr style = "color: white;background: #3c8dbc;height: 36px;font-size: 16px">';
        foreach (self::$po_title as $title) {
            $str .= "<td>{$title}</td>";
        }
        $html[] = $str;
        foreach ($total_data as $dapartment_id => $depart) {  //999地区最大，他会在最后显示，没有问题
            if ($dapartment_id == 999) {
                continue;
            }
            $department_name = Department::where('id', $dapartment_id)->value('name');
            $str = "<tr height='30px'  ><td style='width: 160px'>{$department_name}</td>";
            foreach (self::$pos as $po) {
                $v = round($depart[$po], 2);
                $str .= "<td>{$v}</td>";
            }
            $str .= "</tr>";
            $html[] = $str;
        }
        $total_depart = $total_data[999]; //部门id，保证每个地区的和在最上面。
        $depart_des = '总和';
        $str2 = "<tr height='30px' ><th><b>{$depart_des}</b></th>";
        foreach (self::$pos as $po) {
            $v = round($total_depart[$po], 2);
            $str2 .= "<th>{$v}</th>";
        }
        $str2 .= "</tr>";
        $html[] = $str2;
        $html[] = '</table>';
        return implode('', $html);
    }
}
