<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FinancialInsuranceBaseModel;
use App\Models\WorkplaceModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class FinancialSalaryInsuranceBaseController extends Controller
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
            ->header('缴费基数')
            ->description('各地区默认缴费基数')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {

        $grid = new Grid(new FinancialInsuranceBaseModel());
        $grid->id('ID')->sortable();
        $grid->workplace('地区')->sortable();
        $grid->endowment('养老保险')->sortable();
        $grid->medical('医疗保险')->sortable();
        $grid->employment_injury('工伤保险')->sortable();
        $grid->unemployment('失业保险')->sortable();
        $grid->maternity('生育保险')->sortable();
        $grid->housing_provident_fund('公积金')->sortable();
        $grid->mark('备注');
        $grid->updated_at(trans('admin.updated_at'));

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
        $show = new Show(FinancialInsuranceBaseModel::findOrFail($id));

        $show->id('ID');
        $show->name('类目名称');
        $show->tax('扣税');
        $show->mark('备注');
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
            ->header('编辑五险一金缴费基数')
            ->description(' ')
            ->body($this->form($id)->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($id = null)
    {
        $form = new Form(new FinancialInsuranceBaseModel);
        $form->disableViewCheck();
        $form->disableEditingCheck();

        $form->display('id', 'ID');
        if ($id) {
            $form->display('workplace', '地区');
        } else {
            $form->select('workplace', '地区')->options(WorkplaceModel::all()->pluck('title', 'name'));
        }


        $form->decimal('endowment', '养老保险');
        $form->decimal('medical', '医疗保险');
        $form->decimal('employment_injury', '工伤保险');
        $form->decimal('unemployment', '失业保险');
        $form->decimal('maternity', '生育保险');
        $form->decimal('housing_provident_fund', '公积金');

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
            ->header('新建')
            ->description('按地区新建五险一金缴费比率')
            ->body($this->form(null));
    }
}
