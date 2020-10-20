<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppraisalAssignmentExecutor;
use App\Models\AppraisalTableAssignment;
use App\Models\AppraisalTablesModel;
use App\Models\Department;
use App\Models\FinancialJobGradeUserModel;
use App\Models\UserDepartment;
use App\Models\UserModel;
use App\Models\AssignmentHtml;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Support\Collection;

class AppraisalAssignmentController extends Controller
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
            ->header('绩效表发放')
            ->description('将指定绩效表格发放给指定用户填写')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new AppraisalTableAssignment());
        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableView();
            $actions->disableDelete();
        });
        $grid->disableRowSelector();

        $grid->model()->orderBy('id', 'DESC');
        $grid->id('ID')->sortable();
        $grid->table_info('表格类型')->display(function ($val) {
            $route = route('appraisal.table.preview', $val['id']);
            $link = sprintf('<a href="%s">%s</a>', $route, $val['name']);
            return $link;
        });
        $grid->date('季度')->display(function ($val) {
            $dt = Carbon::parse($val);
            return sprintf('%s Q%s', $dt->year, $dt->quarter);
        })->sortable();

        $grid->deadline('截止时间')->sortable()->display(function ($val) {
            $dt = Carbon::parse($val);
            return $dt->toDateString();
        });
        $grid->executors('填表者')->display(function ($val) {
            $c = new Collection($val);
            $users = $c->pluck('name', 'id')->toArray();
            $content = '';
            foreach ($users as $user_id => $user_name) {
                $content .= "<span class='badge badge-pill badge-primary' style='font-weight: normal;'>$user_name</span> ";
            }
            return sprintf('<div style="max-width: 500px">%s</div>', $content);
        });
        $grid->reviewers('审核者')->display(function ($val) {
            $c = new Collection($val);
            $users = $c->pluck('name', 'id')->toArray();
            $content = '';
            foreach ($users as $user_id => $user_name) {
                $content .= "<span class='badge badge-pill badge-success' style='font-weight: normal;'>$user_name</span> ";
            }
            return $content;
        });
        $grid->auditors('审阅者')->display(function ($val) {
            $c = new Collection($val);
            $users = $c->pluck('name', 'id')->toArray();
            $content = '';
            foreach ($users as $user_id => $user_name) {
                $content .= "<span class='badge badge-pill badge-info' style='font-weight: normal;'>$user_name</span> ";
            }
            return $content;
        });
        $grid->carboncopyers('抄送给')->display(function ($val) {
            $c = new Collection($val);
            $users = $c->pluck('name', 'id')->toArray();
            $content = '';
            foreach ($users as $user_id => $user_name) {
                $content .= "<span class='badge badge-pill badge-secondary' style='font-weight: normal;'>$user_name</span> ";
            }
            return $content;
        });
        $grid->updated_at(trans('admin.updated_at'))->sortable();

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->equal('table_id', '表格类型')->select(AppraisalTablesModel::getExistTables(true)->toArray());
            $filter->year('date', '年度');
            $filter->equal('quarter', '季度')->select(array(1 => 1, 2 => 2, 3 => 3, 4 => 4));
            $filter->where(function ($query) {
                $ids = AppraisalTableAssignment::getAssignmentsByUserId($this->input, ['assignment_id'])->pluck('assignment_id')->toArray();
                $query->whereIn('id', $ids);
            }, '填表者')->Select(UserModel::getAllUsersPluck());
        });

        return $grid;
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
            ->body($this->form('edit', $id)->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($mode = 'create', $id = null)
    {
        $form = new Form(new AppraisalTableAssignment);
        $form->disableViewCheck();
        $form->disableEditingCheck();

        $form->display('id', 'ID');

        if ($mode == 'create') {
            $form->select('table_id', '表格')->options(
                AppraisalTablesModel::getExistTables(true)->toArray()
            );
        } else {
            $table_name = AppraisalTableAssignment::find($id)->table_info->name;

            $html = <<<eot
        <div class="box box-solid box-default no-margin">
            <div class="box-body">
                {$table_name}
            </div>
        </div>
eot;
            $form->html($html, '表格');
        }

        $auditor_help = "无需二次审阅的，将<span style='color:Black'>审阅者</span>和<span style='color:Black'>审核者</span>设置为同一人即可";

        $all_users = UserModel::getAllUsers(true)->pluck('name', 'id');

        if ($mode == 'create') {
            $dt = Carbon::now();
            if ($dt->day < 7 && in_array($dt->month, [1, 4, 7, 10])) {
                $dt->subMonth();
            }
            $form->year('year', '年度')->value($dt->year);
            $form->number('quarter', '季度')->max(4)->min(1)->value($dt->quarter);
        } else {
            $form->year('year', '年度');
            $form->number('quarter', '季度')->max(4)->min(1);
        }

        $form->date('deadline', '截止时间')->help('截止时间到期后，将不能进行该表的填写');
        $form->divider();

        $form->checkbox('executors', '填表者')->setLabelClass(['hidden'])->setElementClass(['hidden'])->options(UserModel::getAllUsersPluck(true));
        $form->html($this->renderDepartment(), '填表者');
        $form->multipleSelect('reviewers', '审核者')->options($all_users);
        $form->multipleSelect('auditors', '审阅者')->options($all_users)->help($auditor_help);
        $form->multipleSelect('carboncopyers', '抄送给')->options($all_users);


        $tips = <<<eot
<div>
<span class="badge badge-info font-weight-normal">发放表格</span> ->
<span class="badge badge-info font-weight-normal"><b>填表者</b> 填表</span> ->
<span class="badge badge-info font-weight-normal"><b>审核者</b> 审核</span> ->
<span class="badge badge-info font-weight-normal"><b>审阅者</b> 审核</span> ->
<span class="badge badge-info font-weight-normal">面谈</span> ->
<span class="badge badge-info font-weight-normal">结束</span>
</div>
eot;

        $form->divider();
        $form->html($tips, '流程提示');
        $form->display('created_at', trans('admin.created_at'));
        $form->display('updated_at', trans('admin.updated_at'));
        $form->hidden('date', 'date');
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
            $tools->disableView();
        });
        $form->saving(function (Form $form) {
            $year = $form->year;
            $quarter = $form->quarter;
            if ($quarter == 2) {
                $month = 4;
            } elseif ($quarter == 3) {
                $month = 7;
            } elseif ($quarter == 4) {
                $month = 10;
            } else {
                $month = 1;
            }
            $dt = Carbon::create($year, $month, 1);
            $form->date = $dt->toDateString();
//				dd([
//					'executors' => $form->executors,
//					'reviewers' => $form->reviewers,
//					'auditors' => $form->auditors,
//				]);
        });

        $form->saved(function (Form $form) {
            $id = $form->model()->id;
            $date = $form->model()->date;
            AppraisalAssignmentExecutor::where('assignment_id', $id)->update(['date' => $date]);
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
        $info = AssignmentHtml::first();
        if ($info && strtotime($info->updated_at) > time() - 3600) {
            $html = $info->html;
        } else {
            $html = "<h5><b><span style='color:#3C8DBC'>9级及以上人员</span> 和 <span style='color:Green'>无职级人员、未转正人员</span> 可不参与绩效考核</b></h5>";
            $fnRender = function ($nodes) use (&$html, &$fnRender, &$fnGetUsers) {
                $html .= sprintf("<ul class='parent'>");
                foreach ($nodes as $node) {
                    if ($node['alias'] == 'PO') {
                        continue;
                    }
                    $html .= sprintf("<li class='child'><a class='department-name badge badge-warning font-weight-normal'>%s</a>",
                        $node['name']);
                    $user_html = $fnGetUsers($node['id']);
                    $html .= sprintf('<span class="department-user">%s</span>', $user_html);
                    if (isset($node['children'])) {
                        $fnRender($node['children']);
                    }
                    $html .= "</li>";
                }
                $html .= "</ul>";
            };

            $html .= '<nav id="executors-tree" class="nav-tree" style="padding-top: 6px;">';
            $html .= $fnRender($tree);
            $html .= '</nav>';

            $html .= <<<eot
<script data-exec-on-popstate>
$(function () {
    $('.department-name').click(function(e) {
    	var icheckboxs= $(this).parent().find('.checkbox-inline .icheckbox_minimal-blue');
		var checkboxs= $(this).parent().find('.checkbox-inline .icheckbox_minimal-blue input:checkbox');

		var total_count=checkboxs.length;
		var selected_count=0;
		var unselected_count=0;
		
		checkboxs.each(function(e) {
		 	var selected=$(this).prop('checked');
		 	if(selected)selected_count++;
			if(!selected)unselected_count++;
		});
		
	    if(total_count==selected_count){
	        icheckboxs.attr("aria-checked",false).removeClass("checked");
			checkboxs.prop('checked',false);
	    }else{
	        icheckboxs.attr("aria-checked",true).addClass("checked");
			checkboxs.prop('checked',true);
	    }
    });
	$('.executors').iCheck({checkboxClass:'icheckbox_minimal-blue'});
	
	var checked_box=$("#executors .checkbox-inline input:checkbox:checked");
	$("#executors").remove();
	if(checked_box && checked_box.length>0){
		var data=$(checked_box[0]).data('value').toString();
		var selected_ids=data.split(',');
		selected_ids.forEach(function(e) {
			var checkbox= $("#executors-tree input:checkbox[value=" + e +  "]");
			var icheckbox= checkbox.parent();
			icheckbox.attr("aria-checked",true).addClass("checked");
			checkbox.prop('checked',true);
		});
	}
});
</script>
<style>
	#executors{display: none;}
</style>
eot;
            if ($info) {
                AssignmentHtml::where('id', $info->id)->update(['html' => $html]);
            } else {
                AssignmentHtml::insert(['html' => $html, 'updated_at' => date("Y-m-d H:i:s")]);
            }
        }
        return $html;
    }

    private function getApproveStatus($user)
    {
        if (!$user) {
            return '';
        }

        $dt = Carbon::now();
        $rank = FinancialJobGradeUserModel::getUserJobGradeRank($user->id, $dt->year, $dt->month);
        if ($rank >= 9) {
            $style = ' style="color:#3C8DBC"';
        } elseif ($rank < 1) {
            $style = ' style="color:Green"';
        } else {
            $regular_date = $user->roster->regular_date ?? '';
            if (empty($regular_date) || Carbon::parse($regular_date) > Carbon::now()) {
                $style = ' style="color:Green"';
            } else {
                $style = '';
            }
        }

        return <<<eot
<label class="checkbox-inline">
	<input type="checkbox" name="executors[]" value="{$user->id}" class="executors"  data-value="" /><span{$style}> {$user->name}</span>
</label>
eot;
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
            ->header('新增表格发放')
            ->description(' ')
            ->body($this->form('create'));
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(AppraisalTableAssignment::findOrFail($id));

        $show->id('ID');
        $show->created_at('Created at');
        $show->updated_at('Updated at');

        return $show;
    }
}
