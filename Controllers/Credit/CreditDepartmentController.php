<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CreditDepartmentDetailsModel;
use App\Models\CreditSummaryDepartmentModel;
use App\Models\Department;
use App\Models\UserDepartment;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Exception;
use Illuminate\Http\Request;

class CreditDepartmentController extends Controller
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
        $url = route('credit.department.count');
        $html = <<<eot
<script>
$(function () {
	 var datetimepicker_options={
		format: 'YYYY-MM'
	};
	$('.date-picker').datetimepicker(datetimepicker_options);
	$("#btn-count-credit").click(function() {
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
            ->header('部门积分明细')
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
        $timezone = UserModel::getUserTimezone();
        $grid = new Grid(new CreditDepartmentDetailsModel());
        $grid->disableExport();
        $grid->actions(function (Grid\Displayers\Actions $action) {
            $action->disableView();
            $action->disableEdit();
        });
        $grid->model()->orderBy('created_at', 'desc');

        $grid->id('ID')->sortable();
        $grid->credit_category('类别')->sortable()->display(function ($val) {
            $style = CreditDepartmentDetailsModel::getCategoryTypeStyle($val);
            $desc = CreditDepartmentDetailsModel::getCategoryTypeHuman($val);
            if ($style) {
                return sprintf('<span class="badge badge-%s font-weight-normal">%s</span>', $style, $desc);
            }
            return $desc;
        });

        $grid->department('部门')->display(function ($val) {
            return $val['name'];
        });

        $grid->credit_value('分值')->sortable();
        $grid->user('加分/修改人')->display(function ($val) {
            return $val['name'];
        });
        $grid->mark('备注')->display(function ($val) {
            if ($this->credit_category == CreditDepartmentDetailsModel::CATEGORY_DINGTALK_REPORT_AUTO) {
                $json = json_decode($val, true);
                $id = $json['report_id'] ?? 0;
                $url = '';
                if ($id) {
                    $url = route('dingtalkreport.list.show', $id);
                }
                $val = '<span class="green">' . $json['template_name'] . "</span> -> " . $json['report_title'];
                if ($url) {
                    $val = "<a href='$url'>$val</a>";
                }
            }
            return $val;
        });


        $grid->created_at(trans('admin.created_at'))->display(function ($val) use ($timezone) {
            $dt = Carbon::parse($val)->setTimezone($timezone);
            return $dt->toDateTimeString();
        });

        $grid->tools(function (Grid\Tools $tools) {
            $html = <<<eot
	<div class="btn-group input-group pull-right" style="width: 250px;">
		<span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
		<input style="width: 100px;font-weight: bold; text-align: center;" type="text" value="2019-05" class="form-control date date-picker">
		<a id="btn-count-credit" class="btn btn-sm btn-warning" style="margin-left: 3px;">
			<i class="fa fa-save"></i><span class="hidden-xs">&nbsp;&nbsp;立即统计</span>
		</a>
	</div>
eot;

            $tools->append($html);
        });

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->equal('credit_category', '类别')->multipleSelect(CreditDepartmentDetailsModel::$CATEGORY_TYPES);
            $filter->equal('department_id', '部门')->multipleSelect(Department::all()->pluck('name', 'id'));
        });
        return $grid;
    }

    /**
     * 统计积分
     */
    public function count(Request $request)
    {
        $response['status'] = 0;
        try {
            $date = Carbon::parse($request->get('date'));
            CreditSummaryDepartmentModel::count($date->year, $date->month);
            return [
                'status' => 1,
                'message' => "{$date->format('Y-m')} 统计成功"
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
        return $content
            ->header('Detail')
            ->description('description')
            ->body($this->detail($id));
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(CreditDepartmentDetailsModel::findOrFail($id));

        $show->id('ID');
        $show->created_at('Created at');
        $show->updated_at('Updated at');

        return $show;
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
            ->header('Edit')
            ->description('description')
            ->body($this->form()->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new CreditDepartmentDetailsModel);

        $form->display('id', 'ID');
        $form->decimal('credit_value', '分值');
        $categorys = CreditDepartmentDetailsModel::$CATEGORY_TYPES;
        unset($categorys[CreditDepartmentDetailsModel::CATEGORY_DINGTALK_REPORT_AUTO]);
        $form->select('credit_category', '类别')->options($categorys)->default(-999);
        $form->textarea('mark', '备注')->placeholder('请详细说明积分情况');
        $form->html($this->renderDepartment(), '部门');
        $form->display('created_at', 'Created At');
        $form->display('updated_at', 'Updated At');
        $form->saving(function (Form $form) {
            $user_id = UserModel::getCurrentUserId();
            $departments = $form->departments;
            if ($departments && sizeof($departments) > 0) {
                foreach ($departments as $department) {
                    $row = new CreditDepartmentDetailsModel();
                    $row->credit_value = $form->credit_value;
                    $row->credit_category = $form->credit_category;
                    $row->mark = $form->mark;
                    $row->department_id = $department;
                    $row->creator_id = $user_id;
                    $row->save();
                }
            }
//				return response()->redirectTo(route('credit.department.index'));
            die();
        });
        return $form;
    }

    public function renderDepartment()
    {
        $tree = Department::getNavTree()->getTree();
        $relation = UserDepartment::all()->toArray();
        $all_users = UserModel::getAllUsers();
        $department_users = [];
        foreach ($relation as $r) {
            $department_users[$r['department_id']][] = $r['user_id'];
        }

        $fnGetUsers = function ($department_id) use ($department_users, $all_users, &$fnIsUserOnline) {
            $list = '';
            if (isset($department_users[$department_id])) {
                $users = $department_users[$department_id];
                foreach ($users as $user_id) {
                    $user = $all_users->where('id', $user_id)->first();
                    $list .= $this->getApproveStatus($user);
                }
            }
            return $list;
        };

        $html = '';
        $fnRender = function ($nodes) use (&$html, &$fnRender, &$fnGetUsers) {
            $html .= sprintf("<ul class='parent'>");
            foreach ($nodes as $node) {
                if ($node['id'] == 1) {
                    $html .= sprintf('<li class="child"><span class="badge badge-warning font-weight-normal">%s</span>', $node['name']);
                } else {
                    $html .= sprintf('<li class="child"><label><input type="checkbox" name="departments[]" value="%s" class="departments"  data-value="" /><span class="badge badge-warning font-weight-normal">%s</span></label>', $node['id'], $node['name']);
                }

                $user_html = $fnGetUsers($node['id']);
                $html .= sprintf('<span class="department-user">%s</span>', $user_html);
                if (isset($node['children'])) {
                    $fnRender($node['children']);
                }
                $html .= "</li>";
            }
            $html .= "</ul>";
        };
        $html .= '<nav id="departments-tree" class="nav-tree" style="padding-top: 6px;">';
        $html .= $fnRender($tree);
        $html .= '</nav>';

        $html .= <<<eot
<script data-exec-on-popstate>
$(function () {
	$('.departments').iCheck({checkboxClass:'icheckbox_minimal-blue'});
});
</script>
<style>
	li{
		list-style: none;
	}
</style>
eot;

        return $html;
    }

    private function getApproveStatus($user)
    {
        $list = '';
        if ($user) {
            $list .= <<<eot
	<span class="badge badge-gray font-weight-normal">{$user->name}</span>
eot;
        }
        return $list;
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
            ->header('积分操作')
            ->description('新增、扣除、对冲积分')
            ->body($this->form());
    }
}
