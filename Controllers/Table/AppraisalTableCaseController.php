<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppraisalTableCase;
use App\Models\Auth\OAPermission;
use App\Models\UserModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Http\Request;
use App\Admin\Extensions\CaseCheck;

class AppraisalTableCaseController extends Controller
{
    use HasResourceActions;

    public function addToCase(Request $request)
    {
        /*
        assignment_id: "1"
        content: "以客户为中心"
        evaluate_type: "review"
        table_id: "1"
        title: "以客户为中心"
        user_id: "28"
        value: "5"
        added_by:
         */
        $current_user = UserModel::getUser();
        $user_id = intval($request->post('user_id'));
        $user = UserModel::getUser($user_id);
        $title = $request->post('title');
        $where = [
            'assignment_id' => intval($request->post('assignment_id')),
            'table_id' => intval($request->post('table_id')),
            'evaluate_type' => $request->post('evaluate_type'),
            'user_id' => $user_id,
            'section_id' => intval($request->post('section_id')),
        ];

        $data = [
            'value' => $request->post('value'),
            'content' => $request->post('content'),
            'added_by' => $current_user->id,
            'title' => $title,
        ];

        $result = AppraisalTableCase::updateOrCreate($where, $data);
        if ($result) {
            return [
                'status' => 1,
                'message' => "{$user->name} 的 \"$title\" 案例<br>加入案例库成功",
            ];
        } else {
            return [
                'status' => 0,
                'message' => "加入案例库失败",
            ];
        }

    }

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('优秀案例')
            ->description(' ')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new AppraisalTableCase());
        $current_user = UserModel::getUser();

        if (!OAPermission::isAdministrator() && !OAPermission::isHr()) {
            $grid->disableActions();
        }

        $grid->disableRowSelector();

        $grid->id('ID')->sortable();

        $grid->section_id('模块')->sortable()->display(function ($val) {
            return AppraisalTableCase::CASE_CORPORATECULTURE_SECTIONS[$val];
        });
        $grid->content('案例')->display(function ($val) {
            return "<div style='max-width: 600px;'>$val</div>";
        });
        $grid->value('分值')->sortable();

        $grid->column('evaluate_user.name', '案例人')->sortable();
        $grid->column('added_user.name', '添加人')->sortable();

        $grid->updated_at(trans('admin.updated_at'));

        $grid->status('状态')->display(function ($status) {
            if ($status) {
                return "<span style='color:Green;font-weight:bold'>已验证</span>";
            }
            return "<span style='color:#999999'>未验证</span>";
        });

        if ($current_user->isRole('administrator') || $current_user->isRole('hr')) {
            $grid->actions(function ($actions) {
                $actions->append(new CaseCheck($actions->getKey()));
            });
        } else {
            $grid->actions(function ($actions) use ($current_user) {
                $case = $this->row;
                if ($case->status != 0 || $case->added_by != $current_user->id) {
                    $actions->disableDelete();
                    $actions->disableEdit();
                }
            });
            $grid->model()->where('status', 1)->orWhere('added_by', $current_user->id);  //未审核不让看
        }

        $grid->model()->orderBy('id', 'desc');


        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->equal('user_id', '案例人')->select(UserModel::getAllUsersPluck());
            $filter->equal('section_id', '案例模块')->select(AppraisalTableCase::CASE_CORPORATECULTURE_SECTIONS);
            $filter->equal('status', '状态')->select([1 => '已验证', 0 => '未验证']);
        });

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
        $user = UserModel::getUser(AppraisalTableCase::find($id)->user_id);
        return $content
            ->header('详情')
            ->description($user->name)
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
        $show = new Show(AppraisalTableCase::findOrFail($id));

        $show->id('ID');
        $show->title('模块');
        $show->content('案例');

        $show->created_at(trans('admin.created_at'));
        $show->updated_at(trans('admin.updated_at'));

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
            ->header('编辑案例')
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
        $current_user = UserModel::getUser();
        $form = new Form(new AppraisalTableCase);
        $form->display('id', 'ID');
        $form->select('user_id', '案例人')->options(UserModel::getAllUsersPluck());
        $form->select('section_id', '模块')->options(AppraisalTableCase::CASE_CORPORATECULTURE_SECTIONS);
        $form->number('value', '分值')->max(5)->min(1)->default(5);
        $form->textarea('content', '案例');
        $form->hidden('added_by')->default($current_user->id)->value($current_user->id);
        $form->display('created_at', trans('admin.created_at'));
        $form->display('updated_at', trans('admin.updated_at'));

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
            ->header('新建案例')
            ->description(' ')
            ->body($this->form());
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function check($id)
    {
        $current_user = UserModel::getUser();
        if ($current_user->isRole('administrator') || $current_user->isRole('hr')) {
            AppraisalTableCase::where('id', $id)->update(['status' => 1]);
            return response()->json(['status' => 1, 'message' => trans('案例验证通过'),]);
        } else {
            return response()->json(['status' => 0, 'message' => trans('案例未验证通过'),]);
        }
    }
}
