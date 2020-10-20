<?php

namespace App\Admin\Controllers;

use App\Models\Auth\OAPermission;
use App\Models\Department;
use App\Models\HumanResourcesDistributionModel;
use App\Http\Controllers\Controller;
use App\Models\UserModel;
use App\Models\WorkplaceModel;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Permission;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Box;
use Illuminate\Http\Request;
use Encore\Admin\Form;
use Illuminate\Support\MessageBag;

class HumanResourcesDistributionController extends Controller
{
    use HasResourceActions;

    private static $po_names = [
        'letsview' => '幕享',
        'beecut' => '蜜蜂剪辑',
        'pdf' => '轻闪-PDF',
        'lightmv' => '右糖',
        'pickwish' => '布钉-智能图像',
        'mindmap' => 'GitMind-脑图',
        'others' => '其它项目',
        'platform' => '技术运营中台',
        'organizational_construction' => '组织建设'
    ];

    public static $places = ['深圳' => '深圳', '武汉' => '武汉', '南昌' => '南昌', '西安' => '西安'];

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content, Request $request)
    {
        return $content
            ->header('员工人力分布')
            ->description(' ')
            ->body($this->grid($request));
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid($request)
    {
        $month = $request->input('month');
        $data = HumanResourcesDistributionModel::getPoHumans($month);
        $po_humans = $data['po_humans'];

        $grid = new Grid(new HumanResourcesDistributionModel);
        $grid->id('编号');
        $grid->user_id('员工名')->display(function ($val) {
            $user = UserModel::withTrashed()->where('id', $val)->first();
            return $user ? $user->name : '用户' . $val;
        });
        $grid->column('department', '部门')->display(function ($val) {
            $department = Department::where('id', $val)->value('name');
            return $department || !$val ? $department : '部门' . $val;   //为O的部门不存在
        });
        $grid->column('child_department', '下级部门')->display(function ($val) {
            $department = Department::where('id', $val)->value('name');
            return $department || !$val ? $department : '部门' . $val;
        });
        $grid->month('月份');
        $grid->letsview("幕享{$po_humans['letsview_count']}")->display(function ($val) {
            return $val ? "{$val}%" : '';
        });
        $grid->beecut("蜜蜂剪辑{$po_humans['beecut_count']}")->display(function ($val) {
            return $val ? "{$val}%" : '';
        });
        $grid->pdf("轻闪PDF{$po_humans['pdf_count']}")->display(function ($val) {
            return $val ? "{$val}%" : '';
        });
        $grid->lightmv("右糖{$po_humans['lightmv_count']}")->display(function ($val) {
            return $val ? "{$val}%" : '';
        });
        $grid->pickwish("智能图像-布钉{$po_humans['pickwish_count']}")->display(function ($val) {
            return $val ? "{$val}%" : '';
        });
        $grid->mindmap("脑图{$po_humans['mindmap_count']}")->display(function ($val) {
            return $val ? "{$val}%" : '';
        });
        $grid->others("其它项目{$po_humans['others_count']}")->display(function ($val) {
            return $val ? "{$val}%" : '';
        });
        $grid->platform("技术运营中台{$po_humans['platform_count']}")->display(function ($val) {
            return $val ? "{$val}%" : '';
        });
        $grid->organizational_construction("组织建设{$po_humans['organizational_construction_count']}")->display(function (
            $val
        ) {
            return $val ? "{$val}%" : '';
        });
        $grid->column('sum', "合计")->display(function () {
            $sum = $this->letsview + $this->beecut + $this->pdf + $this->lightmv + $this->pickwish + $this->mindmap + $this->others + $this->platform + $this->organizational_construction;
            if ($sum != 100) {
                return "<span style='color:red'>{$sum}%</span>";
            }
            return '100%';
        });

        if (!OAPermission::isAdministrator() && !OAPermission::has('oa.po.import')) {
            $grid->disableActions();
        } else {
            $grid->actions(function ($actions) {
                $actions->disableView();
            });
        }

        $grid->disableRowSelector();
//        $grid->disableCreateButton();
        $grid->disableExport();
        $grid->paginate(25);
        $grid->model()->orderBy('month', 'desc');

        if (OAPermission::has('oa.human.costs')) {
            $grid->tools(function (Grid\Tools $tools) use ($month) {
                $cost_url = route('human.po.statistics');
                $department_url = route('users.departments');
                $import_html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$cost_url}" target="_self"  class="btn btn-sm btn-success"  title="PO成本统计">
        <i class="fa fa-bar-chart"></i><span class="hidden-xs">&nbsp;&nbsp;PO成本统计</span>
    </a>
</div>
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$department_url}" target="_self"  class="btn btn-sm btn-default"  title="查看人员部门">
        <i class="fa fa-bar-chart"></i><span class="hidden-xs">&nbsp;&nbsp;查看人员部门</span>
    </a>
</div>
eot;
                $tools->append($import_html);
            });
        }

        $department = $request->input('department') ? (int)$request->input('department') : 0;
        $has_import_permission = OAPermission::has('oa.po.import');
        $grid->filter(function (Grid\Filter $filter) use ($department ,$has_import_permission) {
            $filter->disableIdFilter();
            $filter->equal('month', '月份')->datetime(['format' => 'YYYY-MM']);

            $filter->where(function ($query) {
                $workplace_name = $this->input;
                $users = WorkplaceModel::getUsersByWorkplaceName($workplace_name, true);
                $user_ids = $users->pluck('id');
                $query->whereIn("user_id", $user_ids);
            }, '地区', 'workplace')->Select(self::$places);

            $filter->where(function ($query) {
                $query->where("{$this->input}", '>', 0);
            }, 'PO线', 'po')->Select(HumanResourcesDistributionModel::PO);

            $filter->equal('department', '部门')
                ->Select(HumanResourcesDistributionModel::getDepratments())
                ->load('child_department', '/admin/human/child-departments');

            $filter->where(function ($query) use ($department) {
                if ($this->input) {
                    $query->where('child_department', $this->input);
                }
            }, '下级部门', 'child_department')->Select(HumanResourcesDistributionModel::getChildDepratments($department));
            $filter->in('user_id', '用户名')->multipleSelect(HumanResourcesDistributionModel::getHumans());
            if($has_import_permission) {
                $filter->where(function ($query) {
                    if ($this->input) {
                        $names = array_keys(self::$po_names);
                        $name_str = implode('+', $names);
                        $query->whereRaw("$name_str" . $this->input);
                    }
                }, 'PO分布合计取值范围', 'total_po')->Select(['>100' => '>100', '=100' => '=100', '<100' => '<100']);
            }
        });

        $grid->tools(function (Grid\Tools $tools) use ($has_import_permission) {
            $quarterly_url = route('human.distribution.quarterly');
            $html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$quarterly_url}"  class="btn btn-sm btn-success"  title="按季度/月度查看">
        <i class="fa fa-clock-o"></i><span class="hidden-xs">&nbsp;&nbsp;按季度/月度查看</span>
    </a>
</div>
eot;
            if ($has_import_permission) {
                $import_url = route('human.distribution.import');
                $html .= <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$import_url}"  class="btn btn-sm btn-warning"  title="导入">
        <i class="fa fa-upload"></i><span class="hidden-xs">&nbsp;&nbsp;导入</span>
    </a>
</div>
eot;
            }

            $tools->append($html);
        });

        return $grid;
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content)
    {
        return $content
            ->header('员工人力分布')
            ->description('新增')
            ->body($this->form());
    }

    public function poImport(Content $content)
    {
        $previous_dt = Carbon::now()->addMonth(-1);
        $form = new \Encore\Admin\Widgets\Form();
        $form->action(route('human.distribution.store'));
        $form->date('date', '员工人力分布月份')->format('YYYY-MM')->default($previous_dt->format('Y-m'));
        $form->file('file', 'EXCEL文件');
        $box = new Box('导入员工人力分布数据', $form->render());
        $html = $box->render();
        return $content
            ->header('员工分布数据导入')
            ->description(' ')
            ->body($html);
    }

    public function poStore(Request $request)
    {
        $date = $request->post('date');
        $file = $request->file('file');
        if (!$file) {
            admin_error('导入失败', '未上传文件');
        } else {
            $dt = Carbon::now();
            $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            $file_name = sprintf('import-%s.%s', $dt->format('YmdHis'), $extension);
            $result = $file->storeAs('po', $file_name);
            $file_path = storage_path('app/' . $result);
            if (file_exists($file_path)) {
                $return_url = route('human.distribution.index');
                $return_html = <<<eot
<div class="btn-group btn-group-import"  style="margin-right: 10px">
    <a href="{$return_url}" class="btn btn-sm btn-warning"  title="返回">
        <i class="fa fa-refresh"></i><span class="hidden-xs" style="margin-left: 10px;">返回</span>
    </a>
</div>
eot;
                $result = (new HumanResourcesDistributionModel())->poImport($date, $file_path);
                @unlink($file_path);
                if ($result['status']) {
                    admin_success('导入成功', $result['message'] . $return_html);
                } else {
                    admin_error('导入失败', $result['message'] . $return_html);
                }
            } else {
                admin_error('文件上传失败', sprintf('文件不存在: %s', $file_path));
            }
        }
    }

    public function getChildDepratments(Request $request)
    {
        $department_id = $request->get('q') ?? 0;
        $child_departments = HumanResourcesDistributionModel::getChildDepratments($department_id);

        $data[] = ['id' => 0, 'text' => '选择'];
        foreach ($child_departments as $id => $name) {
            $data[] = ['id' => $id, 'text' => $name];
        }
        return $data;
    }

    public function show($id, Content $content)
    {
        return $content
            ->header('Detail')
            ->description('description')
            ->body($this->detail($id));
    }

    /**
     * Edit interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function edit($id, Content $content)
    {
        return $content
            ->header('员工人力分布')
            ->description(' ')
            ->body($this->form($id)->edit($id));
    }


    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($id = null)
    {
        $form = new Form(new HumanResourcesDistributionModel);

        if ($id) {
            $form->display('user.name', '用户名');
        } else {
            $form->date('month', '所属月份')->format("YYYY-MM")->default(Carbon::now()->format('Y-m'));
            $form->select('user_id', '用户名')->options(UserModel::all()->pluck('name', 'id'));
        }
        $form->select('department', '部门')->options(Department::all()->pluck('name', 'id'));;
        $form->select('child_department', '子部门')->options(Department::all()->pluck('name', 'id'));
        $form->number('letsview', '幕享');
        $form->number('beecut', '视频编辑中台');
        $form->number('pdf', 'PDF云');
        $form->number('lightmv', '右糖');
        $form->number('pickwish', '智能图像');
        $form->number('mindmap', '脑图');
        $form->number('others', '其它项目');
        $form->number('platform', '技术运营中台');
        $form->number('organizational_construction', '组织建设');

        $form->saving(function (Form $form) {
            $sum = 0;
            foreach (HumanResourcesDistributionModel::PO as $name => $po) {
                $sum += $form->$name;
            }
            if ($sum != 100) {
                $error = new MessageBag([
                    'title' => "PO总和（{$sum}）不为100",
                    'message' => '请重新填写',
                ]);
                return back()->with(compact('error'));
            }
            $exist = HumanResourcesDistributionModel::where('month', $form->month)->where('user_id',
                $form->user_id)->first();
            if ($exist) {
                $name = UserModel::where('id', $form->user_id)->value('name');
                $error = new MessageBag([
                    'title' => "{$name},{$form->month} PO信息已填写",
                    'message' => '',
                ]);
                return back()->with(compact('error'));
            }
        });

        return $form;
    }

    public function getQuarterlyDistribution(Content $content)
    {
        $quarters = [];
        $months = HumanResourcesDistributionModel::select('month')->distinct()->orderBy('month',
            'desc')->get()->toArray();
        foreach ($months as $month) {
            $dt = Carbon::parse($month['month']);
            $quarter = "{$dt->year}Q{$dt->quarter}";
            if (!isset($quarters[$quarter])) {
                $quarters[$quarter] = "{$dt->year} Q{$dt->quarter}";
            }
        }

        $month_options = "<option value=\"null\">整季</option><option>1</option><option>2</option><option>3</option>";

        $quarter_options = '';
        foreach ($quarters as $k => $v) {
            $quarter_options .= "<option value=\"{$k}\">{$v}</option>";
        }

        $po_options = "<option value=\"null\" hidden>请选择</option>";
        foreach (self::$po_names as $id => $name) {
            $po_options .= "<option value=\"{$id}\">{$name}</option>";
        }

        $return_url = route('human.distribution.index');

        $box_title = <<<EOT
<table class="table" >
    <tr>
        <td style="width: 80px">季度</td>
        <td style="width: 240px"><select id="quarter-selector" style="width: 200px" class="form-control">{$quarter_options}</select></td>
        <td style="width: 180px"></td>
        <td>
            <div class="pull-right"  style="margin-right: 10px">
                <a href="{$return_url}" class="btn btn-sm btn-warning"  title="返回">
                    <i class="fa fa-list"></i><span class="hidden-xs" style="margin-left: 10px;">返回列表</span>
                </a>
            </div>
        </td>
    </tr>
    <tr>
        <td>月度</td>
        <td><select id="month-selector" style="width: 200px" class="form-control">{$month_options}</select></td>
    </tr>
    <tr>
        <td>PO线</td>
        <td><select id="po-selector" style="width: 200px" class="form-control">{$po_options}</select></td>
        <td>
            <button id="fetch-btn" style="width: 100px" class="btn btn-primary pull-left" type="button">查询</button>
        </td>
    </tr>
</table>
EOT;

        $route1 = route('human.distribution.quarterly');
        $route2 = route('human.distribution.monthly');
        $box_content = <<<EOT
<div id="box-info" data-route1='{$route1}' data-route2='{$route2}'></div>
EOT;

        $box_attendance = new Box($box_title, $box_content);

        $box_attendance->style('primary')->view("admin.widgets.box");
        $body = $box_attendance->render();

        $body .= <<<EOT
<script>
    $(function () {
        var quarter = $("#quarter-selector").val();
        var month = $("#month-selector").val();
        var po = $("#po-selector").val();

        updateMonth();

        $("#quarter-selector").change(function () {
            updateMonth();
        });

        $("#fetch-btn").click(function() {
            fetchHumanInfo();
        });

        function fetchHumanInfo() {
            quarter = $("#quarter-selector").val();
            month = $("#month-selector").val();
            po = $("#po-selector").val();

            if (!quarter || !po || po == "null") {
                return;
            }

            var box = $("#box-info");
            if (month == "null") {
                var url = box.data("route1") + "/" + po + "/" + quarter;
            } else {
                var url = box.data("route2") + "/" + po + "/" + month;
            }

            NProgress.configure({
                parent: '.content .box-header'
            });
            NProgress.start();
            $.get(url, function (response) {
                box.html(response);
                NProgress.done();
            })
        }

        function updateMonth() {
            if (!quarter) {
                return;
            }

            var y = quarter.substr(0, 4);
            var q = quarter.substr(5, 1);

            var ms = $("#month-selector").get(0);
            ms.selectedIndex = 0;
            if (q == 1) {
                ms.options[1].value = ms.options[1].text = y + "-01";
                ms.options[2].value = ms.options[2].text = y + "-02";
                ms.options[3].value = ms.options[3].text = y + "-03";
            } else if (q == 2) {
                ms.options[1].value = ms.options[1].text = y + "-04";
                ms.options[2].value = ms.options[2].text = y + "-05";
                ms.options[3].value = ms.options[3].text = y + "-06";
            } else if (q == 3) {
                ms.options[1].value = ms.options[1].text = y + "-07";
                ms.options[2].value = ms.options[2].text = y + "-08";
                ms.options[3].value = ms.options[3].text = y + "-09";
            } else if (q == 4) {
                ms.options[1].value = ms.options[1].text = y + "-10";
                ms.options[2].value = ms.options[2].text = y + "-11";
                ms.options[3].value = ms.options[3].text = y + "-12";
            }
        }

    })
</script>
EOT;

        return $content
            ->header('PO人力季度分布')
            ->description('可以按 季度/月度 查看PO线人力（时间、精力等）分布情况；季度值为当季3个月数据和除以3')
            ->body($body);
    }

    public function getQuarterlyDistributionDetail($po, $quarter)
    {
        $po_names = self::$po_names;
        if (isset($po_names[$po])) {
            $year = intval(substr($quarter, 0, 4));
            $q = intval(substr($quarter, 5, 1));
            if ($q >= 1 && $q <= 4) {
                $month1 = sprintf('%d-%02d', $year, $q * 3 - 2);
                $month2 = sprintf('%d-%02d', $year, $q * 3 - 1);
                $month3 = sprintf('%d-%02d', $year, $q * 3);
                return $this->getDistributionDetailShow($po,
                    $this->queryPoDistributionData($po, [$month1, $month2, $month3]), 3);
            }
        }
        return '';
    }

    public function getMonthlyDistribution(Content $content)
    {
        // 无直接访问处理
        return '';
    }

    public function getMonthlyDistributionDetail($po, $month)
    {
        $po_names = self::$po_names;
        if (isset($po_names[$po])) {
            return $this->getDistributionDetailShow($po,
                $this->queryPoDistributionData($po, [$month]), 1);
        }
        return '';
    }

    private function getDistributionDetailShow($po, $items, $month_count)
    {
        $po_names = self::$po_names;

        $head_tr = "<td>人员</td><td class='text-bold'>$po_names[$po]</td>";
        foreach ($po_names as $po_id => $name) {
            if ($po_id != $po) {
                $head_tr .= "<td>{$name}</td>";
            }
        }
        $head_tr = "<tr class=\"text-center primary-background\">$head_tr</tr>";

        $get_percent = function ($v) use ($month_count) {
            return $v > 0 ? round($v / $month_count, 1) . '%' : '';
        };

        $body_trs = '';
        foreach ($items as $item) {
            $user_name = UserModel::getUser($item['user_id'])->name;
            $po_value = $item[$po];
            $percent = $get_percent($po_value);
            $text_class = $po_value >= 50 * $month_count ? "class='text-bold'" : '';
            $tr = "<td {$text_class} style='background: #F9FAFC'>{$user_name}</td><td {$text_class} style='background: #F9FAFC'>{$percent}</td>";
            foreach ($po_names as $po_id => $name) {
                if ($po_id != $po) {
                    $per = $get_percent($item[$po_id]);
                    $tr .= "<td>{$per}</td>";
                }
            }
            $body_trs .= "<tr class=\"text-center\">$tr</tr>";
        }

        return <<<EOT
<table class="table table-bordered table-condensed">
	<thead>{$head_tr}</thead>
	<tbody>{$body_trs}</tbody>
</table>
EOT;
    }

    private function queryPoDistributionData($po, array $months)
    {
        $po_names = self::$po_names;

        $users = HumanResourcesDistributionModel::select('user_id')
            ->where($po, '>', 0)
            ->whereIn('month', $months)
            ->distinct()->pluck('user_id')->toArray();

        $items = HumanResourcesDistributionModel::whereIn('user_id', $users)
            ->whereIn('month', $months)
            ->get()
            ->toArray();

        $items_new = [];
        foreach ($items as $item) {
            $user_id = $item['user_id'];
            if (!isset($items_new[$user_id])) {
                $items_new[$user_id] = ['user_id' => $user_id];
            }
            $item_new = &$items_new[$user_id];
            foreach ($po_names as $po_id => $name) {
                $item_new[$po_id] = ($item_new[$po_id] ?? 0) + $item[$po_id];
            }
        }

        $sort_func = function ($a, $b) use ($po) {
            if ($a[$po] == $b[$po]) {
                return 0;
            }
            return ($a[$po] < $b[$po]) ? 1 : -1;
        };

        uasort($items_new, $sort_func);

        return $items_new;
    }
}
