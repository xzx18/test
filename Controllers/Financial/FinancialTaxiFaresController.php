<?php

namespace App\Admin\Controllers;

use App\Models\Auth\OAPermission;
use App\Models\FinancialTaxiFaresModel;
use App\Models\UserModel;
use App\Http\Controllers\Controller;
use App\Models\WorkplaceModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;


class FinancialTaxiFaresController extends Controller
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
            ->header('乘车记录')
            ->description('列表')
            ->body($this->grid());
    }

    /**
     * Show interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function show($id, Content $content)
    {
        return $content
            ->header('乘车记录')
            ->description('详情')
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
            ->header('乘车记录')
            ->description('修改记录')
            ->body($this->form($action = 'edit')->edit($id));
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
            ->header('乘车记录')
            ->description('手动录入')
            ->body($this->form($action = 'create'));
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new FinancialTaxiFaresModel);

        $grid->id('记录编号');
        $grid->column('ride_user.name', '乘车人');
        $grid->money('金额');
        $grid->start_point('起点');
        $grid->end_point('终点');
        $grid->start_time('乘车时间');
        $grid->column('added_user.name', '添加人');
        $grid->column('type', '结算类型')->display(function ($val) {
            $val ? $type = '阿里商旅' : $type = '行政报销';
            return $type;
        });
        $grid->created_at('创建时间');
        $grid->mark('简要说明');

        $current_user = UserModel::getUser();
        $is_administrator = $current_user->isRole('administrator');
        $is_administration = $current_user->isRole('administration.staff');//行政
        $is_financial = $current_user->isRole('financial.staff');//财务

        if (!$is_administrator && !$is_administration && !$is_financial) {
            $grid->disableActions();
        }

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->equal('user_id', '乘车人')->select(UserModel::getAllUsersPluck());
        });

        $grid->disableExport();

        if ($is_administrator || $is_financial || $is_administration) {
            $grid->tools(function (Grid\Tools $tools) use (&$grid) {
                $url = route('financial.taxi.import');
                $import_html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$url}"  class="btn btn-sm btn-warning"  title="导入">
        <i class="fa fa-upload"></i><span class="hidden-xs">&nbsp;&nbsp;导入</span>
    </a>
</div>
eot;
                $tools->append($import_html);
            });
        }

        $grid->paginate(25);
        $grid->model()->orderBy('id', 'desc');

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(FinancialTaxiFaresModel::findOrFail($id));

        $show->user_id('乘车人(报销费用的人)')->as(function ($user_id) {
            return UserModel::find($user_id)->name ?? ('User' . $user_id);
        });
        $show->money('金额');
        $show->start_point('起点');
        $show->end_point('终点');
        $show->start_time('乘车时间');
        $show->add_by('添加人')->as(function () {
            $current_user = UserModel::getUser();
            return $current_user->name;
        });
        $show->type('结算类型')->as(function ($val) {
            return $val ? '阿里商旅' : '行政报销';
        });
        $show->created_at('创建时间');

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($action = '')
    {
        $current_user = UserModel::getUser();
        $form = new Form(new FinancialTaxiFaresModel);
        if ($action == 'create') {
            $form->select('user_id', '乘车人（报销费用的人）')->options(UserModel::getAllUsersPluck())->load('start_point', route('financial.taxi.start.points'));
            $form->select('start_point', '起点');
        } else { //修改时如果人不变的情况下做联动，会导致起点select会没有数据
            $form->select('user_id', '乘车人（报销费用的人）')->options(UserModel::getAllUsersPluck())->rules('required');
            $form->text('start_point', '起点（如果不能选中，请选择乘车人后再试）')->default('公司')->rules('required');
        }
        $form->text('end_point', '终点')->rules('required');
        $form->currency('money', '金额')->default(0.00)->rules('required')->symbol("￥");
        $form->datetime('start_time', '乘车时间')->attribute('style', 'width: 200px')->default(date('Y-m-d H:i:s'));
        $form->hidden('added_by')->default($current_user->id)->value($current_user->id);
        $form->select('type', '结算类型')->options(['行政报销', '阿里商旅']);
        $form->text('mark', '简要说明')->help('最多输入100字')->default('加班');
        Admin::script($this->script());

        return $form;
    }

    public function recordImport(Content $content)
    {
        $current_user = UserModel::getUser();
        $is_administrator = $current_user->isRole('administrator');
        $is_administration = $current_user->isRole('administration.staff');//行政
        $is_financial = $current_user->isRole('financial.staff');//财务

        if ($is_administrator || $is_financial || $is_administration) {


            $return_url = route('financial.taxi.index');
            $form = new \Encore\Admin\Widgets\Form();
            $form->action(route('financial.taxi.store'));
            $return_button = <<<eot
<div class="btn-group btn-group-import"  style="float:right">
    <a href="{$return_url}" class="btn btn-sm btn-warning"  title="返回列表">
        <i class="fa fa-refresh"></i><span class="hidden-xs" style="margin-left: 10px;">返回列表</span>
    </a>
</div>
eot;
            $form->textarea('data_input', '数据')->rows(40)->help('直接把Excel表格式的数据部分（要包含标题）复制到这里提交' . $return_button);

            $box = new Box('乘车报销记录导入', $form->render());

            $html = $box->render();
        } else {
            $html = OAPermission::ERROR_HTML;
        }
        return $content
            ->header('导入乘车记录')
            ->description('从Excel表格复制数据到数据输入框中')
            ->body($html);
    }

    public function recordStore(Request $request)
    {
        $data_input = $request->data_input;

        if (!$data_input) {
            return $this->returnWithMessage('数据为空', '请输入表格数据');
        }

        $data_grid = [];
        $lines = explode("\r\n", $data_input);
        foreach ($lines as $line) {
            $data_grid[] = explode("\t", $line);
        }
        $this->getInsertData($data_grid, $insert_data);
        if (empty($insert_data)) {
            return $this->returnWithMessage('数据不正确', '请检查后再导入数据');
        }
        //数据对比
        $ids = [];
        foreach ($insert_data as $v) {
            if (isset($v['number'])) {
                unset($v['number']);
            }
            if (isset($v['truename'])) {
                unset($v['truename']);
            }
            $v['date'] = date('Y-m-d', strtotime($v['start_time']));
            $recort = FinancialTaxiFaresModel::updateOrCreate(
                ['user_id' => $v['user_id'], 'start_time' => $v['start_time'], 'start_point' => $v['start_point'], 'money' => $v['money']],
                $v
            );
            $ids[] = $recort->id;
        }
        $str_ids = implode(',', $ids);
        $html = $this->getExamHtml($insert_data, $str_ids);
        return $html;
    }


    private function getInsertData($data_grid, &$insert_data)
    {
        $current_user = UserModel::getUser();
        foreach ($data_grid as $id => $record) {
            if (is_numeric($record[0])) {
                $insert_data[$id]['number'] = $record[0];
                $insert_data[$id]['start_time'] = $record[1];
                $insert_data[$id]['truename'] = $record[2];
                $add_user_id = UserModel::where('truename', $record[2])->orwhere('name', $record[2])->value('id');
                if (!$add_user_id) {
                    continue;
                }
                $insert_data[$id]['user_id'] = $add_user_id; //user_id
                $insert_data[$id]['start_point'] = $record[5];
                $insert_data[$id]['end_point'] = $record[6];
                $insert_data[$id]['money'] = $record[7];
                $insert_data[$id]['mark'] = $record[8];
                $insert_data[$id]['type'] = 1;
                $insert_data[$id]['added_by'] = $current_user->id;
            }
        }
    }

    private function returnWithMessage($title, $message)
    {
        $error = new MessageBag(['title' => $title, 'message' => $message]);
        return back()->with(compact('error'));
    }

    private function getExamHtml($insert_data, $data_ids)
    {
        $route = route('financial.taxi.import'); //导入页面
        $route_cancel = route('financial.taxi.cancel'); //请求页面
        $route_finish = route('financial.taxi.index'); //保存页面
        $count = count($insert_data);
        $html = <<<EOT
<h4>有效的导入({$count}条)</h4>
EOT;
        if ($count > 0) {
            $html = "<table border='1' class='taxi'>";
            $html .= "<th>序号</th><th>预定日期</th>"
                . "<th>出行人</th><th>出发地</th><th>到达地</th>"
                . "<th>结算金额</th><th>用车事由</th>";

            foreach ($insert_data as $item) {
                $html .= "<tr><td>{$item['number']}</td>"
                    . "<td>{$item['start_time']}</td><td>{$item['truename']}</td>"
                    . "<td>{$item['start_point']}</td>" . "<td>{$item['end_point']}</td>"
                    . "<td>{$item['money']}</td>" . "<td>{$item['mark']}</td>" . "</tr>";
            }
            $html .= "</table>";
        }

        $html .= <<<EOT
<h4>是否提交保存？</h4>
<button type="button" class="btn btn-sm btn-info" style="margin-right:32px" id="btn-cancel" data-route="{$route}" data-route-cancel="{$route_cancel}"  data-resid="{$data_ids}">取消</button>
EOT;

        if ($count > 0) {
            $html .= <<<EOT
<button type="button" class="btn btn-sm btn-warning" id="btn-save"  data-route="{$route_finish}"  data-resid="{$data_ids}">保存</button>
EOT;
        }

        $html .= <<<EOT
<script>
    $(function () {
        $('#btn-cancel').click(function(e) {
            var url = $(this).data('route'); //跳转页面
            var url_cancel = $(this).data('route-cancel');
             var post_data = {
                'resid' : $(this).data('resid'),
                _token: LA.token
                };
            $.post(url_cancel, post_data, function(response) {
                if (typeof response === 'object') {
                    if (response.status) {
                        swal(response.message, '', '取消成功');
                        window.open(url, '_self');
                    } else {
                        swal(response.message, '', 'error');
                    }
                }
            });
        });
        $('#btn-save').click(function(e) {
            var url = $(this).data('route');
            swal('导入成功', '', '导入成功');
            window.open(url, '_self');
        });
    })
</script>
EOT;
        return $html;
    }

    public function recordCancel(Request $request)
    {
        $ids = $request->post('resid');
        FinancialTaxiFaresModel::whereRaw("id in({$ids})")->delete();
        return ['status' => 1, 'message' => "已取消"];
    }

    public function getStartPinits(Request $request) //联动
    {
        $input = $request->all();
        $user_id = $input['q'];
        $user_workplace = UserModel::where('id', $user_id)->value('workplace');
        $options = WorkplaceModel::pluck('name', 'id')->toArray();
        foreach ($options as $k => $option) {
            $options[$k] = $option . '公司';
        }
        if ($user_workplace) {
            array_unshift($options, $user_workplace . '公司');
        }
        $options = array_unique($options);
        return response()->json($options);
    }

    public function getEndPinit(Request $request) //联动
    {
        $input = $request->all();
        $user_id = $input['id'];
        $last_end_point = FinancialTaxiFaresModel::where('user_id', $user_id)->orderBy('id', 'desc')->value('end_point');
        return response()->json(['end_point' => $last_end_point]);
    }


    public function script()
    {
        $url = route('financial.taxi.end.point');
        $js = <<<SCRIPT
          var url = "{$url}";
          $('.user_id').on('change', function () {
             $.ajax({
                method: 'get',
                url: url,
                data: {
                    'id':$(this).val(),
                    _token:LA.token,
                },
                success: function (data) {
                   $('.end_point').val(data.end_point)
               }
            });

    });
SCRIPT;
        return $js;
    }
}
