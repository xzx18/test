<?php

namespace App\Admin\Controllers;

use App\Admin\Models\AttendanceModel;
use App\Http\Controllers\Controller;
use App\Models\AuditModel;
use App\Models\Auth\OAPermission;
use App\Models\Department;
use App\Models\UserModel;
use App\Models\WorkplaceModel;
use App\Models\UserRosterModel;
use Encore\Admin\Auth\Database\Permission;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Role;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Tab;
use function request;


class UserController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header("用户列表")
            ->description("查看所有用户的信息")
            ->body($this->grid()->render());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $url = request()->route()->uri;

        $grid = new Grid(new UserModel());
        $grid->disableRowSelector();
        $grid->disableExport();
        $grid->disableCreateButton();
//			$grid->disableActions();
        $grid->id('ID');
        $grid->avatar('Avatar')->display(function ($val) {
            if (str_contains($val, 'static.dingtalk.com')) {
                $val = UserModel::getResizedAvatarUrl($val, 64);
                return "<img src='$val' style='border-radius: 50%;' />";
            }
            return '';
        });
        $grid->name(trans('admin.name'))->display(function ($val) use ($url) {
            $user_id = $this->id;
            $edit_url = "/$url/$user_id/edit";
            return "<a href='$edit_url' target='_blank'>$val</a>";
        });
        $grid->truename('真实名')->sortable();
        $grid->departments('部门')->pluck('name')->all()->display(function ($val) {
            return implode(",", $val);
        });
        $grid->allow_attendance_rank('考勤排名')->display(function ($val) {
            if ($val) {
                return '<span class="badge badge-success">参与</span>';
            }
            return '<span class="badge badge-secondary">不参与</span>';
        })->sortable();
        $grid->allow_allowance('餐车补贴')->display(function ($val) {
            if ($val) {
                return '<span class="badge badge-success">参与</span>';
            }
            return '<span class="badge badge-secondary">不参与</span>';
        })->sortable();

        $grid->roles(trans('admin.roles'))->pluck('name')->label();

        $grid->manage('考勤授权')->display(function () {
            $staff_items = [];
            $department_items = [];
            $workplace_items = [];

            $staffs = $this->manage_staffs->pluck('name')->all();
            $departments = $this->manage_departments->pluck('name')->all();
            $workplaces = $this->manage_workplaces->pluck('name')->all();

            foreach ($departments as $v) {
                $department_items[] = "<span class='badge badge-pill badge-primary' style='font-weight: normal;'>$v</span>";
            }
            foreach ($workplaces as $v) {
                $workplace_items[] = "<span class='badge badge-pill badge-warning' style='font-weight: normal;'>$v</span>";
            }
            foreach ($staffs as $v) {
                $staff_items[] = "<span class='badge badge-pill badge-info' style='font-weight: normal;'>$v</span>";
            }
            $items = [];
            if (!empty($department_items)) {
                $items[] = implode(' ', $department_items);
            }
            if (!empty($workplace_items)) {
                $items[] = implode(' ', $workplace_items);
            }
            if (!empty($staff_items)) {
                $items[] = implode(' ', $staff_items);
            }
            $list = implode("<br>", $items);
            $content = <<<EOT
    <div style="max-width: 500px">{$list}</div>
EOT;
            return $content;
        });

        $grid->manage_financial('财务授权')->display(function () {
            $workplace_items = [];
            $workplaces = $this->manage_financial_workplaces->pluck('name')->all();

            foreach ($workplaces as $v) {
                $workplace_items[] = "<span class='badge badge-pill badge-warning' style='font-weight: normal;'>$v</span>";
            }
            $items = [];
            if (!empty($workplace_items)) {
                $items[] = implode(' ', $workplace_items);
            }
            $list = implode("<br>", $items);
            $content = <<<EOT
    <div style="max-width: 500px">{$list}</div>
EOT;
            return $content;
        });

        $grid->manage_jobgrade('职级授权')->display(function () {
            $workplace_items = [];
            $workplaces = $this->manage_jobgrade_workplaces->pluck('name')->all();

            foreach ($workplaces as $v) {
                $workplace_items[] = "<span class='badge badge-pill badge-warning' style='font-weight: normal;'>$v</span>";
            }
            $items = [];
            if (!empty($workplace_items)) {
                $items[] = implode(' ', $workplace_items);
            }
            $list = implode("<br>", $items);
            $content = <<<EOT
    <div style="max-width: 500px">{$list}</div>
EOT;
            return $content;
        });


        $grid->workplace('地点')->sortable();

        $grid->mobile('手机');
        $grid->hired_date('入职时间')->sortable()->display(function ($val) {
            $dt = Carbon::parse($val);
            return $dt->toDateString();
        });

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->like('name', trans('admin.name'));
            $filter->like('username', trans('admin.username'));
            $filter->like('mobile', '手机号');
            $filter->in('workplace', '工作地点')->multipleSelect(WorkplaceModel::all()->pluck('title', 'name'));
            $filter->where(function ($query) {
                $input = $this->input;
                // select * from `oa_users` where exists (select * from `admin_roles` inner join `admin_role_users` on `admin_roles`.`id` = `admin_role_users`.`role_id` where `oa_users`.`id` = `admin_role_users`.`user_id` and `role_id` in (1))
                $query->whereHas('roles', function ($query) use ($input) {
                    $query->whereIn('role_id', $input);
                });
            }, '角色')->multipleSelect(Role::all()->pluck('name', 'id'));
            $filter->equal('allow_allowance', '是否参与餐车补贴')->select(['0' => '不参与', '1' => '参与']);
        });
        $grid->tools(function (Grid\Tools $tools) {
//				$tools->append(new SyncDingtalkData());
        });
        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableView();
            $actions->disableDelete();
        });
        $grid->model()->where('username', '<>', '')->where('id', '>', 1);

        return $grid;
    }

    /**
     * Show interface.
     *
     * @param mixed $id
     * @param Content $content
     *
     * @return Content
     */
    public function show($id, Content $content)
    {
        return $content
            ->header(trans('admin.administrator'))
            ->description(trans('admin.detail'))
            ->body($this->detail($id));
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(UserModel::findOrFail($id));

        $show->id('ID');
        $show->username(trans('admin.username'));
        $show->name(trans('admin.name'));
        $show->roles(trans('admin.roles'))->as(function ($roles) {
            return $roles->pluck('name');
        })->label();
//        $show->permissions(trans('admin.permissions'))->as(function ($permission) {
//            return $permission->pluck('name');
//        })->label();
        $show->avatar('Avatar')->as(function ($val) {
            $val = UserModel::getResizedAvatarUrl($val, 90);
            return "<img src='$val' />";
        });

        $show->mobile('联系方式');
        $show->birthday('Birthday');

        $show->created_at(trans('admin.created_at'));
        $show->updated_at(trans('admin.updated_at'));

        return $show;
    }

    /**
     * Edit interface.
     *
     * @param $id
     *
     * @return Content
     */
    public function edit($id, Content $content)
    {
        return $content
            ->header(trans('admin.edit'))
            ->description(trans('admin.edit'))
            ->body($this->form()->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    public function form()
    {
        $tab = new Tab();
        $form = new Form(new UserModel());

        $form->display('id', 'ID');

        $form->display('username', trans('admin.username'));
        $form->display('name', trans('admin.name'));
        $form->text('truename', '真实名');
        $form->password('password', trans('admin.password'))->rules('required|confirmed');
        $form->password('password_confirmation', trans('admin.password_confirmation'))->rules('required')
            ->default(function ($form) {
                return $form->model()->password;
            });

        $form->ignore(['password_confirmation']);

        $form->image('avatar', 'Avatar');
        $form->html('<style>.file-input .file-caption-main{display: none;}</style>');


        $form->divider();
        if (OAPermission::has('oa.management.settings.user.modify.role')) {
            $form->multipleSelect('roles', trans('admin.roles'))->options(Role::all()->pluck('name', 'id'));
        }

        if (OAPermission::has('oa.management.settings.user.modify.managedepartment')) {
            $form->multipleSelect('manage_departments', '考勤可管理部门')->options(
                Department::all()->pluck('name', 'id')
            );
        }
        if (OAPermission::has('oa.management.settings.user.modify.manageworkplace')) {
            $form->multipleSelect('manage_workplaces', '考勤可管理区域')->options(
                WorkplaceModel::all()->pluck('name', 'id')
            );
        }
        if (OAPermission::has('oa.management.settings.user.modify.manageuser')) {
            $form->multipleSelect('manage_staffs', '考勤可管理人员')->options(
                UserModel::all()->pluck('name', 'id')
            );
        }

        $form->divider();
        if (OAPermission::isAdministrator()) {
            $form->multipleSelect('manage_financial_workplaces', '财务可管理区域')->options(
                WorkplaceModel::all()->pluck('name', 'id')
            );
            $form->multipleSelect('manage_jobgrade_workplaces', '职级可管理区域')->options(
                WorkplaceModel::all()->pluck('name', 'id')
            );
        }

        $form->divider();
        $form->multipleSelect('permissions', trans('admin.permissions'))->options(Permission::all()->pluck('name', 'id'));

        $form->divider();
        $form->switch('allow_attendance_rank', '是否参与考勤排名');
        $form->switch('allow_allowance', '是否参与餐补车补');
        $form->switch('ignore_attendance_time', '是否不计迟到早退');
        $form->display('created_at', trans('admin.created_at'));
        $form->display('updated_at', trans('admin.updated_at'));

        $form->saving(function (Form $form) {
            if ($form->password && $form->model()->password != $form->password) {
                $form->password = bcrypt($form->password);
            }

            $check_keys = array_keys(AuditModel::$USER_MODIFY_ITEMS);
            $dirty_data = [];
            foreach ($check_keys as $check_key) {
                $new_val = array_filter($form->$check_key ?? []);
                $old_val = array_values($form->model()->$check_key->pluck('id')->all());

                $new_collect = collect($new_val)->sort();
                $old_collect = collect($old_val)->sort();
                $new_collect->transform(function ($item, $key) {
                    return intval($item);
                });
                $old_collect->transform(function ($item, $key) {
                    return intval($item);
                });

                $diff = json_encode($old_collect) != json_encode($new_collect);
                if ($diff) {
                    $dirty_data[$check_key] = [
                        'from' => $old_collect->sort()->toArray(),
                        'to' => $new_collect->sort()->toArray()
                    ];
                }
            }
            if (!empty($dirty_data)) {
                AuditModel::modifyUser($form->model()->id, $dirty_data);
            }
        });

        return $form;
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create(Content $content)
    {
        return $content
            ->header(trans('admin.administrator'))
            ->description(trans('admin.create'))
            ->body($this->form());
    }

    /**
     * Show interface.
     *
     * @param mixed $id
     * @param Content $content
     *
     * @return Content
     */
    public function profile($uid, Content $content)
    {
        return $content
            ->header('我的资料')
            ->description('如果有不正确的信息，请联系行政人事同事修改')
            ->body($this->info($uid));
    }

    public function info($uid)
    {
        //中文名、英文名、出生日期、过的生日（阳历还是阴历）、
        //入职日期、毕业时间、转正时间、导师、徒弟 信息。
        $user = UserModel::findOrFail($uid);
        $show = new Show($user);
        $show->truename('中文名');
        $show->name('英文名');
        $show->birthday('出生日期');
        $birthday = UserModel::getUserBirthday($uid);
        $show->field('birthday1', '生日')->as(function () use ($birthday) {
            if (isset($birthday['birthday'])) {
                $dt = Carbon::parse($birthday['birthday']);
                if ($birthday['is_lunar']) {
                    return "农历{$dt->month}月" . ($dt->day <= 10 ? "初{$dt->day}" : "{$dt->day}");
                }
                return "{$dt->month}月{$dt->day}日";
            }
            return '';
        });

        $show->hired_date('入职日期')->as(function ($val) {
            return $val ? Carbon::parse($val)->toDateString() : '';
        });
        $show->field('graduation_date', '毕业时间')->as(function () use ($user) {
            $val = $user->roster->graduation_date;
            return $val ? Carbon::parse($val)->toDateString() : '';
        });
        $show->field('userid', '转正时间')->as(function () use ($user) {
            $val = $user->roster->regular_date;
            return $val ? Carbon::parse($val)->toDateString() : '';
        });

        $show->field('mentor', '导师')->as(function () use ($user) { //
            $val = '';
            $mentor_id = $user->roster->mentor;
            $rostors = UserRosterModel::whereRaw("FIND_IN_SET({$user->id},shared_pupils)")->get(); //通过徒弟获取师傅
            foreach ($rostors as $rostor) {
                if(isset($rostor->owner)) {
                    $val .= $rostor->owner->name . ' ';
                }
            }
            if ($mentor_id) {  //还有mentor信息的
                $single_mentor = UserModel::getUser($mentor_id);
                if (!empty($single_mentor)) {
                    $val .= $single_mentor->name;
                }
            }
            return $val;
        });

        $show->field('pupil', '徒弟')->as(function () use ($user) {
            $rostors = UserRosterModel::where('mentor', $user->id)->get();//
            $val = '';
            foreach ($rostors as $pupil) {
                if(isset($pupil->owner)) {
                    $val .= $pupil->owner->name . ' ';
                }
            }
            if ($user->roster->shared_pupils) {
                $pupils = UserModel::whereRaw('id in (' . $user->roster->shared_pupils . ')')->get();
                foreach ($pupils as $p) {
                    $val .= $p->name . ' ';
                }
            }
            return $val;
        });

        $show->panel()
            ->tools(function ($tools) {
                $tools->disableEdit();
                $tools->disableDelete();
            });;
        return $show;
    }
}
