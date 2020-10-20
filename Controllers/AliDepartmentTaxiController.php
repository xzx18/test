<?php

namespace App\Admin\Controllers;

use App\Models\AliDepartmentTaxiModel;
use App\Http\Controllers\Controller;
use App\Models\WorkplaceModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Encore\Admin\Widgets\Tab;
use Encore\Admin\Admin;
use Encore\Admin\Form;


class AliDepartmentTaxiController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content, Request $request)
    {

        $tab = new Tab();
        $url = route('ali.travel.taxi.index');
        $place = $request->place ?? 1;
        $tab->addlink('深圳', $url . '?place=1', $place == 1);
        $tab->addlink('武汉', $url . '?place=2', $place == 2);
        $tab->addlink('南昌', $url . '?place=3', $place == 3);
        $tab->addlink('西安', $url . '?place=4', $place == 4);
        $place_name = WorkplaceModel::getWorkplaceNameById($place);

        return $content
            ->header('阿里商旅费用分布')
            ->description("{$place_name}")
            ->body($tab->render() . $this->grid()->render());
    }


    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new AliDepartmentTaxiModel);
        $grid->name('名称');
        $grid->month('日期');
        $grid->money('金额')->display(function ($v) {
            if ($this->name === '百分比') {
                return round($v, 4) * 100 . '%';
            }
            return $v;
        });
        foreach (AliDepartmentTaxiModel::$department_names as $k => $v) {
            $grid->column($k, $v)->display(function ($v) {
                if ($this->name === '百分比') {
                    return round($v, 4) * 100 . '%';
                }
                return $v;
            });
        }

        $grid->rows(function (Grid\Row $row) {
            if ($row->name === '百分比') {
                $row->style([
                    'background' => '#f5f5e8',
                ]);
            } elseif ($row->name === '总和') {
                $row->style([
                    'background' => 'lightyellow',
                ]);
            }
        });

        $grid->tools(function (Grid\Tools $tools) {
            $import_url = route('ali.travel.taxi.import');
            $html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$import_url}"  class="btn btn-sm btn-warning"  title="导入">
        <i class="fa fa-upload"></i><span class="hidden-xs">&nbsp;&nbsp;导入</span>
    </a>
</div>
eot;
            $tools->append($html);
        });

        $grid->perPage = 100;

        $grid->disableActions();
//        $grid->disableRowSelector();
        $grid->disableCreateButton();
        $grid->disableFilter();
        $grid->disableExport();

        return $grid;
    }

    public function import(Content $content)
    {
        $return_url = route('ali.travel.taxi.index');
        $form = new \Encore\Admin\Widgets\Form();
        $form->action(route('ali.travel.taxi.store'));

        $return_button = <<<eot
<div class="btn-group btn-group-import"  style="float:right">
<a href="{$return_url}" class="btn btn-sm btn-warning"  title="返回列表">
    <i class="fa fa-refresh"></i><span class="hidden-xs" style="margin-left: 10px;">返回列表</span>
</a>
</div>
eot;
        $form->datetime('month', '月份')->format('YYYY-MM')->default(Carbon::now()->addMonth(-1)->firstOfMonth());
        $form->textarea('data_input', '数据')->rows(40)->help('直接把Excel表格式的数据部分（要包含标题）复制到这里提交' . $return_button);
        $box = new Box('阿里商旅记录导入', $form->render());
        $html = $box->render();

        return $content
            ->header('阿里商旅记录导入')
            ->description('从Excel表格复制数据到数据输入框中')
            ->body($html);
    }

    public function store(Request $request)
    {
        $data_input = $request->data_input;
        $month = $request->month;

        if (!$data_input) {
            return self::returnWithMessage('数据为空', '请输入表格数据');
        }

        $data_grid = [];
        $lines = explode("\r\n", $data_input);
        foreach ($lines as $line) {
            $data_grid[] = explode("\t", $line);
        }

        $insert_data = AliDepartmentTaxiModel::getInsertData($data_grid, $month);
        if ($insert_data['status'] == 0) {
            return self::returnWithMessage($insert_data['title'], $insert_data['message']);
        }
        $return_html = route('ali.travel.taxi.index');
        if ($insert_data['status'] == 1) {
            foreach ($insert_data['data'] as $v) {
                AliDepartmentTaxiModel::updateOrCreate(['user_id' => $v['user_id'], 'month' => $v['month'], 'start_time' => $v['start_time'], 'money' => $v['money']], $v);
            }
            return redirect($return_html);
        } else {
            return self::returnWithMessage('unkown error', '');
        }
    }

    protected function form()
    {
        $form = new Form(new AliDepartmentTaxiModel);

        $form->decimal('money', 'Money');
        $form->text('month', 'Month');
        $form->number('user_id', 'User id');
        $form->decimal('project', 'Project')->default(0.00);
        $form->decimal('finance_center', 'Finance center')->default(0.00);
        $form->decimal('legal', 'Legal');
        $form->decimal('finance', 'Finance')->default(0.00);
        $form->decimal('management', 'Management')->default(0.00);
        $form->decimal('hr', 'Hr')->default(0.00);
        $form->decimal('service', 'Service')->default(0.00);
        $form->decimal('administration', 'Administration')->default(0.00);
        $form->decimal('rd', 'Rd')->default(0.00);
        $form->decimal('mobile', 'Mobile')->default(0.00);
        $form->decimal('ops', 'Ops')->default(0.00);
        $form->decimal('windows', 'Windows')->default(0.00);
        $form->decimal('driver', 'Driver')->default(0.00);
        $form->decimal('testing', 'Testing')->default(0.00);
        $form->decimal('backend', 'Backend')->default(0.00);
        $form->decimal('frontend', 'Frontend')->default(0.00);
        $form->decimal('product', 'Product')->default(0.00);
        $form->decimal('creative', 'Creative')->default(0.00);
        $form->decimal('marketing', 'Marketing')->default(0.00);
        $form->decimal('ads', 'Ads')->default(0.00);
        $form->decimal('customer', 'Customer')->default(0.00);
        $form->decimal('operation', 'Operation')->default(0.00);
        $form->decimal('bee', 'Bee')->default(0.00);
        $form->decimal('tiger', 'Tiger')->default(0.00);
        $form->decimal('dragon', 'Dragon')->default(0.00);
        $form->decimal('badger', 'Badger')->default(0.00);
        $form->decimal('warwolf', 'Warwolf')->default(0.00);
        $form->decimal('overseas', 'Overseas')->default(0.00);
        $form->decimal('philippines', 'Philippines')->default(0.00);
        $form->decimal('uk', 'Uk')->default(0.00);
        $form->decimal('eagle', 'Eagle')->default(0.00);
        $form->decimal('gobal', 'Gobal')->default(0.00);
        $form->decimal('portuguese', 'Portuguese')->default(0.00);
        $form->decimal('germany', 'Germany')->default(0.00);
        $form->decimal('spanish', 'Spanish')->default(0.00);
        $form->decimal('japanese', 'Japanese')->default(0.00);
        $form->decimal('french', 'French')->default(0.00);
        $form->decimal('bd', 'Bd')->default(0.00);
        $form->decimal('po', 'Po')->default(0.00);
        $form->decimal('letsview', 'Letsview')->default(0.00);
        $form->decimal('beecut', 'Beecut')->default(0.00);
        $form->decimal('pdf', 'Pdf')->default(0.00);
        $form->decimal('lightmv', 'Lightmv')->default(0.00);
        $form->decimal('pickwish', 'Pickwish')->default(0.00);
        $form->decimal('mindmap', 'Mindmap')->default(0.00);

        return $form;
    }

    public static function returnWithMessage($title, $message)
    {
        $error = new MessageBag(['title' => $title, 'message' => $message]);
        return back()->with(compact('error'));
    }
}
