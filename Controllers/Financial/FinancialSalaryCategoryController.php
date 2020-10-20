<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FinancialCategoryModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class FinancialSalaryCategoryController extends Controller
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
            ->header('财务类别')
            ->description('如果某员工没有该类别的项目（金额=0），则该员工的工资条中不会显示该列')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new FinancialCategoryModel());

        $grid->id('ID')->sortable();
        $grid->name('类目名称')->sortable();
        $grid->type('发放方式')->display(function ($val) {
            return sprintf('<span class="badge badge-%s" style="font-weight: normal;">%s</span>', FinancialCategoryModel::getCategoryTypeStyle($val), FinancialCategoryModel::getCategoryTypeHuman($val));
        })->sortable();
        $grid->allow_disbursement('允许发放')->display(function ($val) {
            if ($val) {
                return '<span class="badge badge-success" style="font-weight: normal;">是</span>';
            }
            return '<span class="badge badge-danger" style="font-weight: normal;">否</span>';
        })->sortable();

//			$grid->viewusers('可见人员')->display(function () {
//				$staff_items = [];
//				$staffs = $this->view_users->pluck('name')->all();
//				foreach ($staffs as $v) {
//					$staff_items[] = "<span class='badge badge-pill badge-info' style='font-weight: normal;'>$v</span>";
//				}
//				$items = [];
//				if (!empty($staff_items)) {
//					$items[] = implode(' ', $staff_items);
//				}
//				$list = implode("<br>", $items);
//				$content = "<div style=\"max-width: 500px\">{$list}</div>";
//				return $content;
//			});


        $grid->mark('备注');
        $grid->created_at(trans('admin.created_at'));
        $grid->updated_at(trans('admin.updated_at'));
        $grid->model()->orderBy('type');
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
        $show = new Show(FinancialCategoryModel::findOrFail($id));

        $show->id('ID');
        $show->name('类目名称');
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
        $form = new Form(new FinancialCategoryModel);

        $form->display('id', 'ID');
        $form->text('name', '类目名称');

        $styles = [];
        foreach (FinancialCategoryModel::$TABLE_TYPES as $key => $val) {
            $styles[$key] = sprintf('<span  data-status="%s" class="badge badge-%s">%s</span>', $key, FinancialCategoryModel::getCategoryTypeStyle($key), FinancialCategoryModel::getCategoryTypeHuman($key));
        }
        $form->radio('type', '发放方式')->options($styles)->default(FinancialCategoryModel::CATEGORY_TYPE_PLUS_BEFORE_TAX);
        //			$form->number('position', '顺序')->help('数值越小越靠前')->min(100)->max(9999)->default(1000);

        $form->switch('allow_disbursement', '允许发放')->help('允许业务主管或者人事进行财务发放操作');
//			$form->multipleSelect('view_users', '可见人员')->options(UserModel::getAllUsersPluck())->help('默认所有人可见 （留空表示所有人可见）');
        $form->textarea('mark', '备注');
        $form->display('created_at', trans('admin.created_at'));
        $form->display('updated_at', trans('admin.updated_at'));
        $form->disableEditingCheck();
        $form->disableViewCheck();

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
