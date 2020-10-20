<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WorkplaceModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class WorkplaceController extends Controller
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
            ->header('地域设置')
            ->description('设置每个工作地区的参数')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new WorkplaceModel());
        $grid->disableFilter();
        $grid->disableRowSelector();
        $grid->disableExport();

        $grid->id('ID')->sortable();
        $grid->name('名称');
        $grid->dingtalk_atm('钉钉考勤机')->display(function ($val) {
            if ($val) {
                return '<span class="badge badge-success">启用</span>';
            }
            return '<span class="badge badge-secondary">未启用</span>';
        });
        $grid->allow_attendance_rank('考勤排名')->display(function ($val) {
            if ($val) {
                return '<span class="badge badge-success">参与</span>';
            }
            return '<span class="badge badge-secondary">不参与</span>';
        });
        $grid->allow_financial_salary('OA发放工资')->display(function ($val) {
            if ($val) {
                return '<span class="badge badge-success">是</span>';
            }
            return '<span class="badge badge-secondary">否</span>';
        });

        $grid->signin_time('上班时间');
        $grid->signout_time_midday('上午下班时间');
        $grid->signin_time_midday('下午上班时间');
        $grid->signout_time('下班时间');
        $grid->buffer_duration('缓冲时长')->display(function ($val) {
            return "$val 分钟";
        });
        $grid->lunch_duration('午休时长')->display(function ($val) {
            return "$val 分钟";
        });
        $grid->lowest_wage('最低工资');
        $grid->dinner_bonus('餐补标准');
        $grid->language('界面语言');
        $grid->timezone('时区');

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
            ->header('详情')
            ->description(' ')
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
        $show = new Show(WorkplaceModel::findOrFail($id));
        $show->id('ID');
        $show->name('名称');
        $show->signin_time('上班时间');
        $show->signout_time_midday('上午下班时间');
        $show->signin_time_midday('下午上班时间');
        $show->signout_time('下班时间');
        $show->lunch_duration('午休时长');
        $show->buffer_duration('缓冲时长');
        $show->lowest_wage('最低工资');
        $show->dinner_bonus('餐补标准');
        $show->language('界面语言');
        $show->timezone('时区');
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
            ->header('编辑')
            ->description('编辑区域设置')
            ->body($this->form()->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new WorkplaceModel);

//        $form->display('id', 'ID');
        $form->text('name', '地区名称');
        $form->divider();
        $form->time('signin_time', '上班时间');
        $form->time('signout_time_midday', '上午下班时间');
        $form->time('signin_time_midday', '下午上班时间');
        $form->time('signout_time', '下班时间');
        $form->hidden('lunch_duration', '午休时长');
        $form->number('buffer_duration', '上班缓冲时长')->help('分钟');
        $form->number('lowest_wage', '最低工资');
        $form->number('dinner_bonus', '餐补标准');
        $form->divider();
        $form->select('language', '界面语言')->options(config('app.languages'));

        $form->select('timezone', '时区')->options(
            WorkplaceModel::timezones()
        );

        $form->switch('dingtalk_atm', '钉钉考勤机');
        $form->switch('allow_attendance_rank', '是否参与考勤排名');
        $form->switch('allow_financial_salary', 'OA发放工资');


        $form->display('created_at', 'Created At');
        $form->display('updated_at', 'Updated At');

        $form->disableViewCheck();
        $form->disableEditingCheck();
        $form->saving(function (Form $form) {
            $signout_time_midday = Carbon::parse($form->signout_time_midday);
            $signin_time_midday = Carbon::parse($form->signin_time_midday);
            $mins = $signin_time_midday->diffInMinutes($signout_time_midday);
            $form->lunch_duration = $mins;
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
        return $content
            ->header('Create')
            ->description('description')
            ->body($this->form());
    }
}
