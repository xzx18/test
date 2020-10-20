<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AliyunModel;
use App\Models\Auth\OAPermission;
use App\Models\UserModel;
use App\Models\WorkRecordModel;
use App\Models\WorkRecordStatisticsModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class WorkRecordController extends Controller
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
        $user = UserModel::getUser();
        if (!in_array($user->username, config('app.super_users'))) {
            $body = OAPermission::ERROR_HTML;
        } else {
            $body = $this->grid()->render();
        }
        return $content
            ->header('工作记录')
            ->description(' ')
            ->body($body);
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new WorkRecordStatisticsModel());
        $grid->disableFilter();
        $grid->disableExport();
        $grid->disableCreateButton();
        $grid->disableActions();
        $grid->disableRowSelector();

        $grid->setView('oa.workrecord');

        $grid->id('ID')->sortable();
        $grid->user('user')->display(function ($val) {
            if (!empty($val['deleted_at'])) {
                //说明已经离职了
                return "<span class='badge badge-danger'>{$val['name']}(离职)</span>";
            }
            return $val['name'];
        });

        $grid->size('size')->display(function ($val) {
            return byteFormat($val);
        });
        $grid->count('count');
        $grid->image_url('image_url')->display(function ($val) {
            $url = AliyunModel::getSignedUrlForObject($this->oss_bucket, $this->oss_object, 'style/240p');
            return $url;
        });

        $grid->captured_at('captured_at');
        $grid->uploaded_at('uploaded_at');
        $grid->uploaded_at_forhumans('uploaded_at')->display(function ($val) {
//            Carbon::setLocale('zh');
            $cur_time = UserModel::getUserTime($this->user_id);
            $uploaded_time = Carbon::parse($this->uploaded_at, $cur_time->timezone);
            return $uploaded_time->diffForHumans($cur_time, false, false, 2);
        });
        $grid->link('link')->display(function ($val) {
            return request()->url() . "/users/{$this->user_id}";
        });
        $grid->model()->orderby('user_id', 'desc');
        $grid->paginate(50);
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
        $show = new Show(WorkRecordModel::findOrFail($id));

        $show->id('ID');
        $show->title('Title');
        $show->content('Content');
        $show->start_time('Start time');
        $show->end_time('End time');

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
        $form = new Form(new WorkRecordModel);

        $form->display('id', 'ID');

        $form->text('title', 'Title');
        $form->editor('content', 'Content');

//        $form->datetime('start_time','Start time');
//        $form->datetime('end_time','End time');

        $form->datetimeRange('start_time', 'end_time', '有效期');
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

    public function userDetail($user_id, Content $content)
    {
        $user = UserModel::getUser($user_id);
        $body = $this->userDetailGrid($user_id)->render();
        return $content
            ->header($user->name)
            ->description('当前时间: ' . UserModel::getUserTime($user_id))
            ->body($body);
    }

    protected function userDetailGrid($user_id)
    {

        $grid = new Grid(new WorkRecordModel());
        $grid->disableFilter();
        $grid->disableExport();
        $grid->disableCreateButton();
        $grid->setView('oa.workrecord-details');
        $grid->disableActions();
        $grid->disableRowSelector();
        $grid->id('ID')->sortable();
        $grid->user('user')->display(function ($val) {
            return $val['name'];
        });

        $grid->size('size')->display(function ($val) {
            return byteFormat($val);
        });
        $grid->count('count');
        $grid->thumbnail_url('thumbnail_url')->display(function ($val) {
            $url = AliyunModel::getSignedUrlForObject($this->oss_bucket, $this->oss_object, 'style/240p');
            return $url;
        });
        $grid->original_url('original_url')->display(function ($val) {
            $url = AliyunModel::getSignedUrlForObject($this->oss_bucket, $this->oss_object);
            return $url;
        });

        $grid->captured_at('captured_at');
        $grid->uploaded_at('uploaded_at');
        $grid->uploaded_at_forhumans('uploaded_at')->display(function ($val) {
            $cur_time = UserModel::getUserTime($this->user_id);
            $uploaded_time = Carbon::parse($this->uploaded_at, $cur_time->timezone);
//            dd($cur_time);    //  date: 2018-10-13 17:14:54.299590 Asia/Shanghai (+08:00)
//            dd($uploaded_time); //  date: 2018-10-13 11:47:55.0 Asia/Shanghai (+08:00)

            return $uploaded_time->diffForHumans($cur_time, false, false, 2);
        });
        $grid->link('link')->display(function ($val) {
            return request()->url() . "/users/{$this->user_id}";
        });
        $grid->paginate(30);
        $grid->model()->where('user_id', $user_id);
        $grid->model()->orderby('uploaded_at', 'desc');

        $grid->picture()->lightbox();
        return $grid;
    }

}
