<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppraisalTablesModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Box;

class AppraisalTablesController extends Controller
{
    use HasResourceActions;

    public function getTable($table_id, Content $content)
    {

        $table = AppraisalTablesModel::getTableInfo($table_id);
        $table_name = $table->name;

        $table_template = AppraisalTablesModel::getTemplateByTableType($table->type, null, AppraisalTablesModel::TABLE_STATUS_NONE, AppraisalTablesModel::OPERATE_MODE_PREVIEW);
        if (is_array($table_template)) {
            $html = '';
            foreach ($table_template as $title => $template) {
                $html .= <<<eot
<h3>$title</h3>
$template
eot;
            }
            $table_template = $html;
        }
        $box = new Box($table_name, $table_template);
        return $content
            ->header('绩效表预览')
            ->description(' ')
            ->row(function (Row $row) use ($box, $table) {
                if (in_array($table->type, [AppraisalTablesModel::TABLE_TYPE_TRIAL_PERIOD])) {
                    $row->column(2, '');
                    $row->column(8, $box->render());
                    $row->column(2, '');
                } else {
                    $row->column(12, $box->render());
                }
            });
//				->body($box);
    }


    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('绩效表列表')
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
        $grid = new Grid(new AppraisalTablesModel());

        $grid->id('ID')->sortable();
//				->display(function ($val) {
//				$route = route('appraisal.table.preview', $val);
//				$link = sprintf('<a href="%s">%s</a>', $route, $val);
//				return $link;
//			});
        $grid->name('表格名称')->sortable()->display(function ($val) {
            $route = route('appraisal.table.preview', $this->id);
            $link = sprintf('<a href="%s">%s</a>', $route, $val);
            return $link;
        });
        $grid->type('表格类型')->sortable()->display(function ($val) {
            $style = AppraisalTablesModel::getTableTypeStyle($val);
            $desc = AppraisalTablesModel::getTableTypeHuman($val);
            if ($style) {
                return sprintf('<span class="badge badge-%s">%s</span>', $style, $desc);
            } else {
                return $desc;
            }
        });

        $grid->language('语言')->sortable();

        $grid->updated_at(trans('admin.updated_at'));
        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableView();
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
        $show = new Show(AppraisalTablesModel::findOrFail($id));

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
        $form = new Form(new AppraisalTablesModel);

        $form->display('id', 'ID');
        $form->select('type', 'Type')->options(AppraisalTablesModel::$TABLE_TYPES);
        $form->select('language', tr('Language'))->options(config('app.languages'));
        $form->text('name', 'Name');

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
