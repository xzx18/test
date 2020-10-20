<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CreditDepartmentAggregationModel;
use App\Models\Department;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class CreditDepartmentAggregationController extends Controller
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
            ->header('部门聚合设置')
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
        $all_departments = Department::all(['id', 'name']);
        $grid = new Grid(new CreditDepartmentAggregationModel());

        $grid->id('ID')->sortable();
        $grid->title('显示名');
        $grid->children('聚合部门')->display(function ($val) use ($all_departments) {
            $list = [];
            foreach ($val as $department_info) {
                $dep = $all_departments->where('id', $department_info['department_id'])->first();
                if ($dep) {
                    $dep_name = $dep->name;
                    $list[] = <<<eot
<span class="badge badge-warning font-weight-normal">{$dep_name}</span>
eot;
                }
            }

            return implode(' ', $list);
        });
        $grid->created_at('Created at');
        $grid->updated_at('Updated at');

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
        $show = new Show(CreditDepartmentAggregationModel::findOrFail($id));

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
        $form = new Form(new CreditDepartmentAggregationModel);

        $form->display('id', 'ID');
        $form->text('title', '显示名称');
        $options = Department::all()->pluck('name', 'id');
        $form->multipleSelect('departments', '聚合部门')->options($options);
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
