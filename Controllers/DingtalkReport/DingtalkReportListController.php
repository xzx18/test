<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DingtalkReportListModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Box;
use Exception;
use Illuminate\Http\Request;

class DingtalkReportListController extends Controller
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
        $url = route('dingtalkreport.sync');
        $html = <<<eot
<script>
$(function () {
	 var datetimepicker_options={
		format: 'YYYY-MM'
	};
	$('.date-picker').datetimepicker(datetimepicker_options);
	$("#btn-sync-dingtalk-report").click(function() {
	  	var date=$('.date-picker').val();
	  	var url="{$url}";
	  	 var post_data = {
            _token: LA.token,
            'date': date
        };
	  	 
	    NProgress.configure({parent: '.content'});
        NProgress.start();
        $.post(url, post_data, function (response) {
            console.log(response);
            if (typeof response === 'object') {
                if (response.status) {
                    $.pjax.reload('#pjax-container');
                    swal(response.message, '', 'success');
                } else {
                    swal(response.message, '', 'error');
                }
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {

        }).always(function () {
            NProgress.done();
        });
	});
});

</script>
eot;

        $html .= $this->grid()->render();
        return $content
            ->header('日志列表')
            ->description(' ')
            ->body($html);
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $timezone_china = config('app.timezone_china');

        $grid = new Grid(new DingtalkReportListModel());
        $grid->disableCreateButton();
        $grid->disableRowSelector();
        $grid->disableExport();
//			$grid->model()->orderBy('id', 'desc');
        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableDelete();
            $actions->disableEdit();
        });
        $grid->tools(function (Grid\Tools $tools) {
            $html = <<<eot
	<div class="btn-group input-group pull-right" style="width: 250px;">
		<span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
		<input style="width: 100px;font-weight: bold; text-align: center;" type="text" value="2019-05" class="form-control date date-picker">
		<a id="btn-sync-dingtalk-report" class="btn btn-sm btn-success" style="margin-left: 3px;">
			<i class="fa fa-save"></i><span class="hidden-xs">&nbsp;&nbsp;立即同步</span>
		</a>
	</div>
eot;

            $tools->append($html);
        });
        $grid->id('ID')->display(function ($val) {
            $url = "list/$val";
            return "<a href='$url'>$val</a>";
        });
        $grid->user_name('提交人');
        $grid->department_name('部门名');
        $grid->report_title('标题');
        $grid->template_name('模板名称');
        $grid->create_time('提交时间');

        $grid->updated_at(trans('admin.updated_at'))->display(function ($val) use ($timezone_china) {
            $dt = Carbon::parse($val)->setTimezone($timezone_china);
            return $dt->toDateTimeString();
        });

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->equal('department_id', '部门')->multipleSelect(Department::all()->pluck('name', 'id'));
        });

        $grid->model()->orderBy('create_time', 'desc');
        return $grid;
    }

    public function sync(Request $request)
    {
        $response['status'] = 0;
        try {
            $timezone_china = config('app.timezone_china');
            $date = Carbon::parse($request->get('date'), $timezone_china);
            $start_date = $date->copy()->startOfMonth();
            $end_date = $date->copy()->endOfMonth();

            DingtalkReportListModel::syncReport($start_date, $end_date);
            return [
                'status' => 1,
                'message' => "同步成功<br>从 {$start_date->toDateTimeString()}<br>到 {$end_date->toDateTimeString()}"
            ];
        } catch (Exception $exception) {
            $response['message'] = $exception->getMessage();
        }
        return $response;
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
        $info = DingtalkReportListModel::find($id);

        $title = $info->template_name . ': ' . $info->department_name;
        $raw = json_decode($info->content, true);
        $contents = $raw['contents'] ?? [];

        $content_items = [];
        foreach ($contents as $c) {
            $key = $c['key'] ?? '';
            $value = nl2br($c['value'] ?? '');
            if ($key) {
                $content_items[] = <<<eot
<p>
	<h3>{$key}</h3>
	<span class="blue">{$value}</span>
</p>
eot;

            }
        }
        $content_html = implode('', $content_items);
        $body = <<<eot
日志提交时间：{$info->create_time}
{$content_html}
eot;
        $box = new Box($info->report_title, $body);
        $box->style('primary');

        return $content
            ->header($title)
            ->description(' ')
            ->row(function (Row $row) use ($box) {
                $row->column(3, '');
                $row->column(6, $box->render());
                $row->column(3, '');
            });
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
            ->header('日志列表')
            ->description(' ')
            ->body($this->form()->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new DingtalkReportListModel);

        $form->display('id', 'ID');
        $form->display('created_at', 'Created At');
        $form->display('updated_at', 'Updated At');

        return $form;
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
            ->header('Create')
            ->description('description')
            ->body($this->form());
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(DingtalkReportListModel::findOrFail($id));

        $show->id('ID');
        $show->created_at('Created at');
        $show->updated_at('Updated at');

        return $show;
    }
}
