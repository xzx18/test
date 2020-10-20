<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DingtalkReportTemplateModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class DingtalkReportTemplateController extends Controller
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
            ->header('日志模板列表')
            ->description('可修改积分数值')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $timezone_china = config('app.timezone_china');
        $grid = new Grid(new DingtalkReportTemplateModel);
        $grid->disableCreateButton();
        $grid->disableRowSelector();
//		$grid->disableActions();
        $grid->disableExport();

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableDelete();
            $actions->disableView();
        });
        $grid->id('ID')->sortable();
        $grid->name('模板名称')->display(function ($val) {
            $icon_url = $this->icon_url;
            return sprintf('<img src="%s" width="20" height="20"/> %s', $icon_url, $val);
        });
        $grid->credit('可获积分')->display(function ($val) {
            if ($val > 0) {
                return '<span class="badge badge-success font-weight-normal">' . $val . '</span>';
            }
            return $val;
        });

        $grid->updated_at(trans('admin.updated_at'))->display(function ($val) use ($timezone_china) {
            $dt = Carbon::parse($val)->setTimezone($timezone_china);
            return $dt->toDateTimeString();
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
        $show = new Show(DingtalkReportTemplateModel::findOrFail($id));

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
        $form = new Form(new DingtalkReportTemplateModel);

        $form->display('id', 'ID');
        $form->display('name', '模板名称');
        $form->decimal('credit', '可获积分');
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
