<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Auth\OAPermission;
use App\Models\UserModel;
use App\Models\WorkingAgeCommentModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Http\Request;

class WorkingAgeCommentController extends Controller
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
            ->header('Index')
            ->description('description')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid($user_id ,$type)
    {
        $timezone = UserModel::getUserTimezone();
        $grid = new Grid(new WorkingAgeCommentModel());

        $grid->disableRowSelector();
        $grid->disableCreateButton();
        $grid->disableActions();
        $grid->disableExport();
        $grid->disableFilter();

        $grid->model()->where('to_user_id', $user_id)->orderBy('id', 'desc');
        /*->when($type, function ($query, $type) {
            return $query->where('type', $type);
        })*/

        $grid->id('序号')->style('width:80px');

        $grid->content('内容')->display(function ($val) {
            return $val;
        })->style('word-break:break-all;');

        $grid->type('祝福类型')->display(function ($val) {
            $type_name = WorkingAgeCommentModel::getTypeName($val);
            if ($val == WorkingAgeCommentModel::TYPE_ANNIVERSARY) {
                $type_name = str_replace('N', $this->year, $type_name);
            }
            return $type_name;
        })->style('width:160px');

        $grid->user('发表人')->display(function ($val) {
            return $val['name'] ?? '';
        })->style('width:120px');

        $grid->created_at(trans('admin.created_at'))->display(function ($val) use ($timezone) {
            $dt = Carbon::parse($val)->setTimezone($timezone);
            return $dt->toDateTimeString();
        })->style('width:160px');

        $grid->rows(function (Grid\Row $row) {
            $dt_now = Carbon::now();
            $dt_created_at = Carbon::parse($row->created_at);
            if ($dt_now->diffInDays($dt_created_at) > 31) {
                $row->setAttributes(['style' => 'color:#999999']);
            }
        });

        return $grid;
    }

    public function list(Content $content, Request $request, $user_id)
    {
        $type = $request->get('type');
        $user = UserModel::getUser($user_id);
        if (!$user) {
            return $content
                ->header('未授权访问')
                ->description('您的该次请求将会被记录')
                ->body(OAPermission::ERROR_HTML);
        } else {
            return $content
                ->header("{$user->name}, 我们想对你说")
                ->description(' ')
                ->body($this->grid($user_id ,$type));
        }
    }

    public function save(Request $request)
    {
        $by_user_id = UserModel::getUser()->id;
        $user_id = $request->get('user_id');
        $type = $request->get('type');
        $comment = $request->get('comment');
        $year = $request->get('user_year') ?? 0;
        if (!$user_id || !$type || !$comment) {
            return ['status' => 0, 'message' => '发送失败，缺少接收人、类型或者内容'];
        }

        $exist_row = WorkingAgeCommentModel::where('to_user_id', $user_id)->where('by_user_id',
            $by_user_id)->where('type', $type)->where('year' ,$year)->first();
        if ($exist_row) {
            $exist_row->content = $comment;
            $result = $exist_row->save();
            if ($result) {
                return ['status' => 1, 'message' => '更新成功',];
            } else {
                return ['status' => 0, 'message' => '更新失败',];
            }
        }

        $row = new WorkingAgeCommentModel();
        $row->to_user_id = $user_id;
        $row->by_user_id = $by_user_id;
        $row->year = $request->get('user_year') ?? 0;
        $row->content = $comment;
        $row->type = $type;
        $result = $row->save();
        if ($result) {
            return ['status' => 1, 'message' => '发送成功',];
        } else {
            return ['status' => 0, 'message' => '发送失败',];
        }
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
        $show = new Show(WorkingAgeCommentModel::findOrFail($id));

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
        $form = new Form(new YourModel);

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
}
