<?php

namespace App\Admin\Controllers;

use App\Models\FinancialSpecialDeductionModel;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use App\Models\UserModel;

class FinancialSpecialDeductionController extends Controller
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
            ->header('专项扣除列表')
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
            ->header('专项扣除详情')
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
            ->header('编辑专项扣除')
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
            ->header('添加专项扣除')
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
        $grid = new Grid(new FinancialSpecialDeductionModel);

        $grid->id('ID');
        $grid->column('deduction_user.name' ,'员工名');
        $grid->money('金额');
//        $grid->type('类型');
        $grid->created_at('创建时间');
        $grid->updated_at('更新时间');

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->equal('user_id', '员工名')->select(UserModel::getAllUsersPluck());
        });
        $current_user = UserModel::getUser();
        $is_administrator = $current_user->isRole('administrator');
        $is_financial = $current_user->isRole('financial.staff');//财务
        if(!$is_administrator && !$is_financial) { //自己看自己
            $grid->model()->where('user_id', $current_user->id);
        }
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
        $show = new Show(FinancialSpecialDeductionModel::findOrFail($id));

        $show->user_id('员工名')->as(function ($user_id) {
            return UserModel::find($user_id)->name ?? ('User' . $user_id);
        });
//        $show->type('类型');
        $show->money('扣除金额');
        $show->created_at('创建时间');
        $show->updated_at('更新时间');

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new FinancialSpecialDeductionModel);
        $current_user = UserModel::getUser();

        $is_administrator = $current_user->isRole('administrator');
        $is_financial = $current_user->isRole('financial.staff');//财务
        if ($is_administrator || $is_financial) {
            $users = UserModel::getAllUsersPluck();
        } else {
            $users = [$current_user->id => $current_user->name];
        }
        $form->select('user_id', '员工')->options($users)->default($current_user->id)->rules('required');
//        $form->select('type', '扣除类型')->options(['', '']);
        $form->decimal('money', '扣除金额')->default(0.00)->rules('required');

        return $form;
    }

}
