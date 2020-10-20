<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserModel;
use App\Models\UserPostsModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Show;
use Illuminate\Support\Collection;

class SupportPostsController extends Controller
{
    use HasResourceActions;

    /** @var Collection null */
    private static $users = null;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        $html = <<<eot
<script>

</script>

<style>
	.box .box-header.with-border:nth-child(2){
		display: none;
	}
	.box-footer.clearfix{
	display: none;
	}
</style>
eot;

        $month_count = 6;
        self::$users = UserModel::getAllUsers();
        $updated_at = UserPostsModel::getLastedUpdateTime();
        return $content
            ->header('社区互动统计排行榜')
            ->description("所有业务社区最近<b>$month_count</b>个月数据, 更新时间: <b>$updated_at</b>")
            ->row(function (Row $row) use ($month_count) {
                $now = Carbon::now();
                for ($i = 0; $i < $month_count; $i++) {
                    $row->column(12 / $month_count, $this->grid($now->firstOfMonth()->copy()->addMonth(-$i)));
                }
            })->body($html);
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid(Carbon $date)
    {
        $grid = new Grid(new UserPostsModel());
        $grid->disableRowSelector();
        $grid->disableActions();
        $grid->disableCreateButton();
        $grid->disableExport();
        $grid->disableFilter();
        $grid->disablePagination();
        $grid->paginate(1000);
        $grid->tools(function (Grid\Tools $tools) {
            $tools->disableRefreshButton();
            $tools->disableFilterButton();

        });
        $users = self::$users;
        $grid->setTitle(sprintf('%s年%s月', $date->year, $date->month));

        $grid->user_id('用户')->display(function ($val) use ($users) {
            $info = $users->where('id', $val)->first();
            if($info) {
                return $users->where('id', $val)->first()->name;
            }else{
                return '网小宝';
            }
        });
        $grid->topic_count('发帖');
        $grid->reply_count('回帖');
        $grid->total('合计');

        $grid->model()->where('date', $date->firstOfMonth()->toDateString());
        $grid->model()->where('domain', 'all');
        $grid->model()->orderBy('total', 'DESC');

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
        $show = new Show(UserPostsModel::findOrFail($id));

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
        $form = new Form(new UserPostsModel);

        $form->display('id', 'ID');
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
