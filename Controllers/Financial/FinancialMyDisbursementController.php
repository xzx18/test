<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Auth\OAPermission;
use App\Models\FinancialCategoryModel;
use App\Models\FinancialDisbursementModel;
use App\Models\UserModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class FinancialMyDisbursementController extends Controller
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
            ->header('财务发放')
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
        $user = UserModel::getUser();
        $grid = new Grid(new FinancialDisbursementModel);

        $grid->id('ID')->sortable();
        $grid->column('user.name', '用户名')->sortable();
        $grid->category('财务类别')->display(function ($val) {
            return FinancialCategoryModel::getHumanDisplay($val, true);
        });
        $grid->value('金额')->sortable();


        if (!OAPermission::isFinancialStaff() && !OAPermission::isAdministrator()) {
            $grid->model()->where('created_by', $user->id);
        } else {
            $grid->column('createdBy.name', '发放人')->sortable();
        }
        $grid->mark('备注');
        $grid->created_at(trans('admin.created_at'));
        $grid->model()->orderBy('id', 'DESC');

        $grid->disableRowSelector();
        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableView();
        });
        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->equal('user_id', '用户名')->multipleSelect(UserModel::getAllUsersPluck());
            $filter->equal('category_id', '财务类别')->multipleSelect(FinancialCategoryModel::getAllWithPluck(true));
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
        $show = new Show(FinancialDisbursementModel::findOrFail($id));

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
        $user = UserModel::getUser();
        $form = new Form(new FinancialDisbursementModel);

        $form->display('id', 'ID');
        $form->select('user_id', '用户名')->options(UserModel::getAllUsersPluck());
        $form->select('category_id', '财务类别')->options(FinancialCategoryModel::getAllWithPluck(true, true));
        $form->decimal('value', '金额')->help('<b style="color: red;">不管是奖金，还是扣款，均填写正数（不要填写负数）</b>');
        $form->textarea('mark', '备注');
        $form->hidden('created_by')->value($user->id);
        $form->display('created_at', 'Created At');
        $form->display('updated_at', 'Updated At');
        $form->disableViewCheck();
        $form->disableEditingCheck();
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
            $tools->disableView();
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
            ->header('新建发放')
            ->description(' ')
            ->body($this->form());
    }
}
