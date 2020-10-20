<?php

namespace App\Admin\Controllers;

use App\Models\FinancialPrizeModel;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use App\Models\UserModel;
use Encore\Admin\Widgets\Tab;
use Carbon\Carbon;

class FinancialPrizeContributionController extends Controller
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
        $current_user = UserModel::getUser();
        $is_administrator = $current_user->isRole('administrator');
        $is_hr = $current_user->isRole('hr');
        $is_financial = $current_user->isRole('financial.staff');

        $tab = new Tab();

        if ($is_administrator || $is_hr || $is_financial) {
            $tab->addLink('季度奖', route('financial.prize-season.index'), false);
        }
        $tab->add('上季度贡献奖', $this->grid()->render(), true);
        if ($is_administrator || $is_hr || $is_financial) {
            $tab->addLink('上季度绩效工资', route('financial.prize.appraisal'), false);
        }
        if ($is_administrator || $is_financial) {
            $tab->addLink('上季度分红', route('financial.share'), false);
        }

        $html = $tab->render();

        return $content
            ->header('贡献奖')
            ->description('PO与中台贡献奖')
            ->body($html);
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

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new FinancialPrizeModel);

        $grid->column('id', '编号');
        $grid->column('user.name', '用户名');
        $grid->money('金额');
        $grid->date('季度')->display(function ($val) {
            return sprintf('%s Q%s', $this->year, $this->quarter);
        });
        $grid->mark('说明');
        $grid->column('editor_id', '编辑人')->display(function ($val) {
            return is_null($val) ? '' : ($val > 0 ? UserModel::getUser($val)->name : 'OA');
        });
        $grid->column('updated_at', '编辑时间')->display(function ($val) {
            return Carbon::parse($val)->toDateTimeString();
        });

        $grid->disableExport();
        $grid->disableRowSelector();

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->in('user_id', '用户名')->multipleSelect(UserModel::getAllUsersPluck());
            $filter->where(function ($query) {
                $val = $this->input;
                $query->whereRaw("'{$val}' = concat(year , ' Q' ,quarter)");
            }, '季度', 'quar')->Select(FinancialPrizeModel::getGridFilterDate());
        });

        $grid->model()->where('type', FinancialPrizeModel::TYPE_PO_CONTRIBUTION)->orderBy('updated_at', 'desc');

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
        $show = new Show(FinancialPrizeModel::findOrFail($id));

        $show->user_id('用户名')->as(function ($user_id) {
            return UserModel::find($user_id)->name ?? ('User' . $user_id);
        });
        $show->type('类型')->as(function ($type) {
            return FinancialPrizeModel::getTypeName($type);
        });
        $show->date('季度')->as(function ($date) {
            $dt = Carbon::parse($date);
            return $dt->year . ' Q' . $dt->quarter;
        });
        $show->money('金额');
        $show->mark('简要说明');
        $show->ext_mark('详细说明'); //会根据内容自动调整高度
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
        $dt = Carbon::now()->subQuarter();

        $form = new Form(new FinancialPrizeModel);

        $form->display('id', 'ID');
        $form->select('user_id', '用户名')->options(UserModel::getAllUsersPluck());
        $form->year('year', '年度')->value($dt->year);
        $form->number('quarter', '季度')->max(4)->min(1)->value($dt->quarter);
        $form->decimal('money', '金额');
        $form->text('mark', '简要说明')->help('奖项具体名称等，显示在列表中，最多输入100字');
        $form->textarea('ext_mark', '详细说明')->help('得奖的具体事迹等，最多输入1000字');
        $form->hidden('date', 'date');
        $form->hidden('type', 'type');

        $form->tools(function (Form\Tools $tools) {
            // 去掉`删除`按钮
            $tools->disableDelete();
        });

        $form->saving(function (Form $f) {
            $year = $f->year;
            $quarter = $f->quarter;
            $month = $quarter * 3;
            $f->date = Carbon::create($year, $month, 1)->toDateString();
            $f->type = FinancialPrizeModel::TYPE_PO_CONTRIBUTION;
            $f->model()->editor_id = UserModel::getUser()->id;
        });

        return $form;
    }
}
