<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NoticeModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class NoticeController extends Controller
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
            ->header('通知管理')
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
        $grid = new Grid(new NoticeModel());
        $grid->disableFilter();
        $grid->disableExport();

        $grid->id('ID')->sortable();
        $grid->state('状态')->display(function ($val) {
            $end_time = Carbon::parse($this->end_time);
            $now = Carbon::now();
            if ($end_time->gt($now)) {
                return '<span class="badge badge-success">生效中</span>';
            }
            return '<span class="badge badge-danger">已失效</span>';
        });

        $grid->title('Title');

        $grid->content('Content')->display(function ($val) {
            return ($val);
        });
        $grid->start_time('Start time')->sortable();
        $grid->end_time('End time')->sortable();
        $grid->created_at('Created at');
        $grid->model()->orderby('id', 'desc');
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
        $show = new Show(NoticeModel::findOrFail($id));

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
        $form = new Form(new NoticeModel);

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
}
