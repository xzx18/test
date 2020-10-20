<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LinkCategoryModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class LinkCategoryController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Con ten t
     */
    public function index(Content $content)
    {

        return $content
            ->header('链接分组设置')
            ->description('顺序：数值越小越靠前')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new LinkCategoryModel());
        $grid->disableFilter();
        $grid->disableRowSelector();
        $grid->disableExport();
        $grid->id('ID')->sortable();
        $grid->position('排序')->sortable();
        $grid->title('标题')->display(function ($val) {
            return <<<EOT
<span class="badge badge-{$this->style}">{$val}</span>
EOT;
        });


        $grid->urls('链接数量')->display(function ($val) {
            return sizeof($val);
        });
        $grid->created_at('Created At');
        $grid->model()->orderBy('position', 'asc');
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
        $show = new Show(LinkCategoryModel::findOrFail($id));
        $show->id('ID');
        $show->title('标题');
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
        $form = new Form(new LinkCategoryModel);

        $styles = [];
        foreach (LinkCategoryModel::styles() as $key => $val) {
            $styles[$key] = <<<EOT
<span class="badge badge-{$key}">Style</span>
EOT;

        }

        $form->display('id', 'ID');
        $form->text('title', '标题');
        $form->radio('style', 'Style')->options($styles);
        $form->number('position', '排序')->help("数值越小越靠前");
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
