<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FinancialPrizeModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;

class FinancialPrizeCommonController extends Controller
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
            ->header('奖项与扣款')
            ->description('按月发放的、无法系统算出的，在此导入')
            ->body($this->grid());
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
            ->header('新建')
            ->description('奖项条目详情')
            ->body($this->form());
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
            ->description('奖项条目详情')
            ->body($this->form()->edit($id));
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new FinancialPrizeModel());

        $grid->column('user.name', '用户名');
        $grid->money('金额');
        $grid->column('type', '类型')->display(function ($val) {
            return FinancialPrizeModel::getTypeName($val);
        });
        $grid->column('date', '所属月份')->display(function ($val) {
            return Carbon::parse($val)->format('Y-m');
        });
        $grid->mark('说明');

        $grid->disableExport();
        $grid->disableRowSelector();

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->in('user_id', '用户名')->multipleSelect(UserModel::getAllUsersPluck());
            $filter->equal('type', '类型')->select(FinancialPrizeModel::getCommonPluck());
            $filter->like('date', '所属月份')->datetime(['format' => 'YYYY-MM']);;
        });

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableView();
        });

        $grid->model()->whereIn('type',
            [
                FinancialPrizeModel::TYPE_SPECIAL_GIFT,
                FinancialPrizeModel::TYPE_SPECIAL_ADD,
                FinancialPrizeModel::TYPE_SPECIAL_CUT,
                FinancialPrizeModel::TYPE_TRAINING_FEE
            ])->orderBy('updated_at', 'desc');

        return $grid;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new FinancialPrizeModel);

        $form->display('id', 'ID');
        $form->select('user_id', '用户名')->options(UserModel::getAllUsersPluck())->rules('required');
        $form->select('type', '类型')->options(FinancialPrizeModel::getCommonPluck())->rules('required');
        $form->month('date', '所属月份')->format('YYYY-MM')->rules('required');
        $form->number('money', '金额');
        $form->text('mark', '简要说明')->help('奖项具体名称等，最多输入100字');
        $form->hidden('year', 'year');

        $form->tools(function (Form\Tools $tools) {
            // 去掉`删除`按钮
            $tools->disableDelete();
            // 去掉`查看`按钮
            $tools->disableView();
        });

        $form->saving(function (Form $f) {
            $f->year = substr($f->date, 0, 4);
            $f->date = $f->date . '-01';
        });

        return $form;
    }
}
