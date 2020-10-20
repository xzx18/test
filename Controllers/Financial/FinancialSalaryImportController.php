<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\Tools\FinancialSalaryImportExportModel;
use App\Http\Controllers\Controller;
use App\Helpers\SalaryConfirmHelper;
use App\Models\Auth\OAPermission;
use App\Models\FinancialSalaryModel;
use App\Models\WorkplaceModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class FinancialSalaryImportController extends Controller
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

//			$workplace_id = 1;
//			$date = '2018-12-01';
//			$file_path = storage_path('app/salaries/201812-nc.xlsx');
//			(new FinancialSalaryImportExportModel())->import($workplace_id, $date, $file_path);

        $previous_dt = Carbon::now()->addMonth(-1);
        $form = new Form();
        $form->action(route('financial.salary.import.store'));
        $form->select('workplace_id', '地区')->options(WorkplaceModel::getAllWithPluck());
        $form->date('date', '工资所属期')->format('YYYY-MM')->default($previous_dt->format('Y-m'));
        $form->file('file', 'CSV文件');

        $tips_html = <<<eot
			<p class="red">由于不同操作系统导出的csv文件编码不太统一，为了确保导入的正确性，请先将文件保存成UTF8格式后再进行导入操作</p>
<ul style="list-style: decimal">
<li>
<p class="font-weight-bold blue">在要导入的csv文件上面"右键"，选择用"记事本"打开：</p>
<img src="/image/20190317091700.jpg" />
</li>
<li>
<p class="font-weight-bold blue">点击 "文件" -> "另存为"：</p>
<img src="/image/20190317091813.jpg" />
</li>
<li>
<p class="font-weight-bold blue">选择 "UTF8" 编码，然后点击 "保存" 按钮：</p>
<img src="/image/20190317091849.jpg" />
</li>
<li>
<p class="font-weight-bold blue">选择 刚刚另存为后的csv文件 进行导入</p>
</li>
</ul>
eot;

        $tips_box = new Box('提示', $tips_html);
        $tips_box->solid();
        $tips_box->style('warning');

        $box = new Box('导入工资条', $form->render());
        $html = $box->render() . $tips_box->render();

        return $content
            ->header('导入')
            ->description('导入excel格式的工资条')
            ->body($html);
    }

    public function store(Request $request)
    {
        $workplace_id = intval($request->post('workplace_id'));
        $date = $request->post('date');
        $file = $request->file('file');
        if (!$file) {
            admin_error('导入失败', '未上传csv文件');
        } else {
            $dt = Carbon::now();
            $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            $file_name = sprintf('import-%s.%s', $dt->format('YmdHis'), $extension);
            $result = $file->storeAs('salaries', $file_name);
            $file_path = storage_path('app/' . $result);

            if (file_exists($file_path)) {
                $return_url = route('financial.salary.workplace', $workplace_id);
                $return_html = <<<eot
<div class="btn-group btn-group-import"  style="margin-right: 10px">
    <a href="{$return_url}" class="btn btn-sm btn-warning"  title="返回">
        <i class="fa fa-refresh"></i><span class="hidden-xs" style="margin-left: 10px;">返回工资列表</span>
    </a>
</div>
eot;

                $result = (new FinancialSalaryImportExportModel())->import($workplace_id, $date, $file_path);
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

    public function clear(Request $request)
    {
        $workplace_id = intval($request->post('workplace_id'));
        FinancialSalaryModel::clearImported($workplace_id);
        return [
            'status' => 1,
            'message' => "清理成功"
        ];
    }

    public function calcTaxes(Request $request)
    {
        $is_financial_staff = OAPermission::isFinancialStaff();
        $is_hr = OAPermission::isHr();
        if (!$is_financial_staff && !$is_hr) {
            return ['status' => 0, 'message' => '非人事或者财务角色不可操作'];
        }

        $workplace_id = intval($request->post('workplace_id'));

        $failed_items = [];
        $ret = FinancialSalaryModel::calcTax($workplace_id, $failed_items);
        if ($ret) {
            if (count($failed_items) > 0) {
                $message = '部分计算失败: ' . implode(' ,', $failed_items);
            } else {
                $message = '计算成功';
            }
        } else {
            $message = '全部计算失败';
        }

        return [
            'status' => $ret ? 1 : 0,
            'message' => $message
        ];
    }

    public function notifyConfirm(Request $request)
    {
        SalaryConfirmHelper::Remind();
        return [
            'status' => 1,
            'message' => '已发送确认'
        ];
    }
}
