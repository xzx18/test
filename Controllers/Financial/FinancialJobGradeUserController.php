<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\Tools\ActionCheckJobGradeUser;
use App\Admin\Extensions\Tools\ActionRemindJobGradeUserConfirmer;
use App\Exports\JobGradeExport;
use App\Http\Controllers\Controller;
use App\Models\Auth\OAPermission;
use App\Models\Dingtalk;
use App\Models\FinancialJobGradeModel;
use App\Models\FinancialJobGradeUserModel;
use App\Models\ProfessionModel;
use App\Models\UserGrowthModel;
use App\Models\UserModel;
use App\Models\WorkplaceModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Support\Collection;
use Carbon\Carbon;

use Excel;

class FinancialJobGradeUserController extends Controller
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
            ->header('职级列表')
            ->description('显示最近修改的项目')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $showDetails = $this->hasDetailsPermission();
        $model = new FinancialJobGradeUserModel();
        $grid = new Grid($model);

        $grid->paginate(25);

        //$grid->id('ID')->sortable();
        $grid->column('user_id', '用户ID');
        $grid->column('user.name', '用户名');
        $grid->column('user.truename', '真实名');
        $grid->column('grade.workplace_id', '地域')->display(function ($val) {
            $name = WorkplaceModel::getWorkplaceNameById($val, true);
            if ($name == '南昌') {
                $name .= '/西安';
            }
            return $name;
        })->sortable();
        $grid->column('grade.profession_id', '岗位')->display(function ($val) {
            return ProfessionModel::getProfessionNameById($val, true);
        })->sortable();
        $grid->column('grade.name', '职级')->sortable();

        if ($showDetails) {
            $grid->salary('基本工资')->sortable();
        }

        $grid->use_merit_salary('特殊绩效工资')->display(function ($val) {
            return $val > 0 ? '<span class="text-danger text-bold">是</span>' : '否';
        });
        $grid->from_time('生效时间')->display(function ($val) {
            return $val ? Carbon::parse($val)->toDateString() : "";
        })->sortable();
        $grid->column('mark', '备注')->style('max-width:360px')->display(function ($val) {
            return str_replace("\r\n", '<br/>', $val);
        });
        $grid->created_by('创建人')->display(function ($val) {
            return $val ? UserModel::getUserById($val)->name ?? $val : "";
        });
        $grid->updated_by('更新人(最近)')->display(function ($val) {
            return $val ? UserModel::getUserById($val)->name ?? $val : "";
        });
        $grid->confirmed('已确认')->display(function ($val) {
            return $val ? '是' : '<span class="text-red">否</span>';
        });
        $grid->confirmed_by('确认人')->display(function ($val) {
            return $val ? UserModel::getUser($val)->name ?? '' : '';
        });
        $grid->updated_at('更新时间')->display(function ($val) {
            return $val ? Carbon::parse($val)->toDateString() : "";
        });

        $grid->disableRowSelector();
        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->in('user_id', '用户名')->multipleSelect(UserModel::getAllUsersPluck());
            $filter->in('grade.workplace_id', '地域')->multipleSelect(WorkplaceModel::getAllWithPluck());
            $filter->in('grade.profession_id', '岗位')->multipleSelect(ProfessionModel::getAllWithPluck());
            $filter->in('grade.job_category', '类型')->multipleSelect(ProfessionModel::$JOB_CATEGORIES);
            $filter->equal('grade.job_rank', '职级')->integer()->placeholder('直接填写职级数字，比如 8');
            $filter->where(function ($query) {
                $input = $this->input;
                $dt_start = Carbon::parse($input);
                $dt_end = $dt_start->copy()->endOfMonth();
                $query->whereDate('from_time', '>=', $dt_start->toDateString())->whereDate('from_time', '<=',
                    $dt_end->toDateString());
            }, '起始月')->yearMonth();
            $filter->equal('confirmed', '已确认')->select([1 => '是', 0 => '否']);
        });

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableView();
            $row = $actions->row;
            if (!$row->confirmed) {
                $confirmer = $row->getConfirmer();
                if (UserModel::getCurrentUserId() == $confirmer) {
                    $actions->append(new ActionCheckJobGradeUser($row->id));
                } else {
                    $actions->append(new ActionRemindJobGradeUserConfirmer($confirmer));
                }
            }
        });

        $grid->tools(function (Grid\Tools $tools) use ($showDetails) {
            $url = route('financial.jobgrade.export');
            $export_html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$url}" target="_blank"  class="btn btn-sm btn-warning"  title="导出当前职级">
        <i class="fa fa-upload"></i><span class="hidden-xs">&nbsp;&nbsp;导出当前职级</span>
    </a>
</div>
eot;
            $tools->append($export_html);

            if ($showDetails) {
                $url = route('financial.jobgrade.user.diagram');
                $diagram_html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$url}" target="_self"  class="btn btn-sm btn-primary"  title="一张图">
        <i class="fa fa-money"></i><span class="hidden-xs">&nbsp;&nbsp;一张图</span>
    </a>
</div>
eot;
                $tools->append($diagram_html);
            }
        });

        $grid->disableExport();
        if (!$showDetails) {
            $grid->disableActions();
        }

        //授权职级查看区域
        $query = $grid->model()->orderBy('id', 'desc');
        $this->filterByPermission($query);

        return $grid;
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
        $model = new FinancialJobGradeUserModel();
        $user = $model->find($id)->user;
        return $content
            ->header($user->truename . '(' . $user->name . ')')
            ->description('职级、工资变更历史')
            ->body($this->detail($id, $model));
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id, $model)
    {
        $grid = new Grid($model);

        //$grid->column('user.name', '用户名');
        $grid->column('grade.workplace_id', '地域')->display(function ($val) {
            return WorkplaceModel::getWorkplaceNameById($val, true);
        });
        $grid->column('grade.profession_id', '岗位')->display(function ($val) {
            return ProfessionModel::getProfessionNameById($val, true);
        });
        $grid->column('grade.job_category', '类型');
        $grid->column('grade.job_rank', '职级');

        if ($this->hasDetailsPermission()) {
            $grid->salary('基本工资');
            $grid->mark('备注说明');
        }

        $grid->from_time('生效时间')->display(function ($val) {
            return substr($val, 0, 10);
        });
        $grid->created_by('创建人')->display(function ($val) {
            return $val ? UserModel::getUserById($val)->name ?? $val : "";
        });
        $grid->updated_by('更新人(最近)')->display(function ($val) {
            return $val ? UserModel::getUserById($val)->name ?? $val : "";
        });

        $grid->model()->where('user_id', $model->getUserId($id))->where('confirmed', 1)->orderBy('from_time', 'desc');

        $grid->disableCreateButton();
        $grid->disableFilter();
        $grid->disableExport();
        $grid->disableRowSelector();

        $grid->tools(function (Grid\Tools $tools) {
            $url = route('jobgrade.list');
            $list_html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$url}"  class="btn btn-sm btn-default"  title="返回人员列表">
        <i class="fa fa-upload"></i><span class="hidden-xs">&nbsp;&nbsp;返回人员列表</span>
    </a>
</div>
eot;
            $tools->append($list_html);
        });


        $grid->actions(function ($actions) {
            $actions->disableDelete();
            $actions->disableEdit();
            $actions->disableView();

            $id = $actions->getKey();
            $url = route('jobgrade.list') . '/' . $id . '/edit';
            $href = <<<EOT
<a href="{$url}"><i class="fa fa-edit"></i></a>
EOT;
            // append一个操作
            $actions->append($href);
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
            ->body($this->form()->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new FinancialJobGradeUserModel);
        $form->display('id', 'ID');
        $form->select('user_id', '用户名')->options(UserModel::getAllUsersPluck());
        $form->select('jobgrade_id', '职级')->options(FinancialJobGradeModel::getAllPluck());

        if ($this->hasDetailsPermission()) {
            $form->decimal('salary', '基本工资');
            $form->textarea('mark', '备注');
            $form->switch('use_merit_salary', '启用特殊的绩效工资')->help('这里启用后，计算绩效工资时会使用这里设置的数值，而不是职级中设置的默认标准');
            $form->decimal('merit_salary', '绩效工资')->help('如上所述；无需特殊设置，留空或者不启用即可');
        }

        $form->datetime('from_time', '生效时间')->format('YYYY-MM-DD');

        $form->tools(function (Form\Tools $tools) use ($form) {
            // 去掉`列表`按钮
            $tools->disableList();

            $url = url()->previous();
            $back_html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$url}"  class="btn btn-sm btn-default"  title="返回">
        <i class="fa fa-upload"></i><span class="hidden-xs">&nbsp;&nbsp;返回</span>
    </a>
</div>
eot;
            $tools->append($back_html);

        });

        $form->saving(function ($form) {
            $uid = UserModel::getCurrentUserId();
            $model = $form->model();
            if (!$model->id) {
                $model->created_by = $uid;
            }
            else {
                $model->updated_by = $uid;
            }
            $model->confirmed = 0;
        });

        $form->saved(function ($form) {
            $grade_name = FinancialJobGradeModel::find($form->jobgrade_id)->name;
            UserGrowthModel::modifyJobGrade($form->user_id, $grade_name);
        });
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
        if ($this->hasDetailsPermission()) {
            $body = $this->form();
        }
        else {
            $body = OAPermission::ERROR_HTML;
        }
        return $content
            ->header('Create')
            ->description('description')
            ->body($body);
    }

    public function confirm($id)
    {
        if (OAPermission::isAdministrator()) {
            FinancialJobGradeUserModel::where('id', $id)->update(['confirmed' => 1]);
            return ['status' => 1, 'message' => '已确认'];
        } else {
            return ['status' => 0, 'message' => '未确认（无权限）'];
        }
    }

    public function remindConfirmer($user_id)
    {
        $userid = UserModel::getUser($user_id)->userid ?? '';
        if ($userid) {
            $time = UserModel::getUserTime()->toDateTimeString();
            $message = "有薪酬调整需要您确认。请在 OA->人事工作->职级管理->人员职级 中检查确认。({$time})";
            if (config('app.debug') == 'true') {
                //echo $message;
            } else {
                Dingtalk::sendMessage($userid, $message);
            }
            return ['status' => 1, 'message' => '已通知'];
        }
        return ['status' => 0, 'message' => '未通知，可能要钉钉手动通知'];
    }

    public function getJobgradeExport()
    {
        $model = new FinancialJobGradeUserModel();
        $query = $model->whereIn('id', $model->getLatestIds())->orderBy('user_id');

        $this->filterByPermission($query);

        $data = [];
        $export = new JobGradeExport();
        $export->setHeaders($data);
        $column_count = count($data[0]);

        foreach ($query->get() as $item) {
            $user = $item->user;
            $grade = $item->grade;
            if (empty($user) || empty($grade)) {
                continue;
            }

            $this_data = array_fill(0, $column_count, '');
            $this_data[0] = $user->truename;
            $this_data[1] = $user->name;
            $this_data[3] = $grade->profession->name; //$item->salary;//
            $this_data[4] = $grade->job_category;
            $this_data[5] = $grade->job_rank;
            $this_data[6] = $grade->name;
            $this_data[7] = $user->hired_date;
            $this_data[8] = empty($user->deleted_at) ? '' : '是';

            $this_data[2] = $grade->workplace->name;
            if ($this_data[2] == '南昌') {
                $this_data[2] .= '/西安';
            }

            $data[] = $this_data;
        }

        $export->setArrayData($data);
        return Excel::download($export, "职级表.xlsx");
    }

    private function FilterByPermission(&$query)
    {
        if (!OAPermission::isAdministrator()) {
            $manage_jobgrade_workplace_ids = [0];
            $current_user = UserModel::getUser();
            /** @var Collection $manage_jobgrade_workplaces */
            $manage_jobgrade_workplaces = $current_user->manage_jobgrade_workplaces;
            if ($manage_jobgrade_workplaces) {
                $manage_jobgrade_workplace_ids = $manage_jobgrade_workplaces->pluck('title', 'id')->toArray();
            }
            $query->whereIn('jobgrade_id', function ($q) use ($manage_jobgrade_workplace_ids) {
                $q->select('id')
                    ->from(FinancialJobGradeModel::getTableName())
                    ->whereIn('workplace_id', array_keys($manage_jobgrade_workplace_ids));
            });
        }
    }

    private static function hasDetailsPermission()
    {
        $current_user = UserModel::getUser();
        return $current_user->isRole('administrator') || $current_user->isRole('financial.staff')
            || OAPermission::has('oa.financial.salary');
    }

}
