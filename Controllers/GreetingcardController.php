<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GreetingcardModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Box;

class GreetingcardController extends Controller
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
            ->header('贺卡管理')
            ->description('在这里可以设置生日、司龄和节日贺卡')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new GreetingcardModel());
        $grid->disableFilter();
        $grid->disableExport();

        $grid->id('ID')->sortable();
//        $grid->state('状态')->display(function ($val) {
//            $end_time = Carbon::parse($this->end_time);
//            $now = Carbon::now();
//            if ($end_time->gt($now)) {
//                return '<span class="badge badge-success">生效中</span>';
//            }
//            return '<span class="badge badge-danger">已失效</span>';
//        });
        $grid->type('Type')->display(function ($val) {
            $types = GreetingcardModel::getTypes();
            switch ($val) {
                case 1:
                    return '<span class="badge badge-primary">' . $types[$val] . '</span>';
                case 2:
                    return '<span class="badge badge-warning">' . $types[$val] . '</span>';
                case 3:
                    return '<span class="badge badge-info">' . $types[$val] . '</span>';
                case 4:
                    return '<span class="badge badge-dark">' . $types[$val] . '</span>';
            }
        });


        $grid->title('Title');

        $grid->content('Content')->display(function ($val) {
            return ($val);
        });
        $grid->date('Date')->sortable()->display(function ($val) {
            if ($this->type == 1 || $this->type == 2) {
                return null;
            }
            return $val;
        });

        $grid->created_at('Created at');
        $grid->model()->orderby('id', 'desc');
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
        $show = new Show(GreetingcardModel::findOrFail($id));

        $show->id('ID');
        $show->title('Title');
        $show->content('Content');
        $show->date('Date');
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


        $body = $this->getTips() . $this->form()->edit($id)->render();
        return $content
            ->header('编辑贺卡')
            ->description('编辑贺卡')
            ->body($body);
    }

    private function getTips()
    {
        $box = new Box('使用提示', <<<EOT
<b>Content中可以使用的变量(变量会被替换成真实数据)</b>
<ul>
    <li>{username}：用户名，比如 Lulu.邓 </li>
    <li>{birthday-ymd}：用户完整生日，比如  1985-08-05</li>
    <li>{birthday-md}：用户生日，比如  08/05</li>
    <li>{today-ymd}：当前完整日期，比如 2018-09-19</li>
    <li>{today-md}：当前日期（仅月和日），比如 09/19</li>
    <li>{years}：入职多少年（整数）比如 3 </li>
</ul>
<b>示例：</b>
<p>
{username}<br>
在{today-md}这个特别的日子里，祝你生日快乐！
</p>
<b>会被显示成:</b>
<p>
Lulu.邓<br>
在09/19这个特别的日子里，祝你生日快乐！
</p>
EOT
        );
        $box->style('info');
        return $box->render();
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new GreetingcardModel);

        $form->display('id', 'ID');

        $form->text('title', 'Title');
        $form->editor('content', 'Content');
        $form->select('type', 'Type')->options(GreetingcardModel::getTypes());
        $form->date('date', 'Date')->help("只有节日贺卡才需要设置日期，比如指定中秋节是哪一天");
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
            ->header('新建贺卡')
            ->description('新建贺卡')
            ->body($this->getTips() . $this->form()->render());
    }
}
