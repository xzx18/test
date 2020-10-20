<?php

namespace App\Admin\Controllers;

use App\Models\AchievementModel;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class AchievementController extends Controller
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
            ->header('员工称号')
            ->description(' ')
            ->body($this->grid());
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
            ->header('员工称号')
            ->description(' ')
            ->body($this->detail($id));
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
            ->header('员工称号')
            ->description(' ')
            ->body($this->form()->edit($id));
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
            ->header('员工称号')
            ->description(' ')
            ->body($this->form());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new AchievementModel);

        $grid->name('称号');
        $grid->description('描述');
        $grid->type('所属类型')->display(function ($val) {
            return AchievementModel::$types[$val];
        });
        $grid->points('成就点');

        $grid->disableExport();


        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(AchievementModel::findOrFail($id));

        $show->name('称号');
        $show->description('描述');
        $show->field('type', '类型')->as(function ($val) {
            return AchievementModel::$types[$val];
        });
        $show->points('成就点');
//

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new AchievementModel);
        $form->text('name', '称号');
        $form->text('description', '描述');
        $form->select('type', '类型')->options(AchievementModel::$types);
        $form->number('points', '成绩点');

        return $form;
    }
}
