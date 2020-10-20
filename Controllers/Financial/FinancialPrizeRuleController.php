<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FinancialPrizeModel;
use App\Models\FinancialPrizeRuleModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class FinancialPrizeRuleController extends Controller
{
    use HasResourceActions;

    static $valid_states = [
        'on' => ['value' => 1, 'text' => '有效', 'color' => 'success'],
        'off' => ['value' => 0, 'text' => '无效', 'color' => 'danger'],
    ];

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('奖项与扣款')
            ->description('按一定规则每月发放的奖项或者扣款，在此配置')
            ->body($this->grid());
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
            ->description('奖项规则详情')
            ->body($this->form());
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
            ->description('详细信息')
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
            ->header('编辑')
            ->description('奖项规则详情')
            ->body($this->form()->edit($id));
    }

    public function createNYearPrizes($year, $month)
    {
        FinancialPrizeRuleModel::createNYearPrizesOfMonth($year, $month);
        return redirect(Route('financial.prize-rule.index'));
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new FinancialPrizeRuleModel());

        $grid->column('id', 'ID');
        $grid->column('user.name', '用户名');
        $grid->column('type', '类型')->display(function ($val) {
            return FinancialPrizeRuleModel::getTypeName($val);
        });
        $grid->mark('说明');
        $grid->column('valid', '是否生效')->display(function ($val) {
            $color = $val > 0 ? 'Black' : 'Red';
            $text = $val > 0 ? '是' : '否';
            return "<span style='color: $color'>$text</span>";
        });

        $grid->content('开始(发放/生效)时间')->display(function ($val) {
            $content = json_decode($val, true);
            $start = $content['plans'][0]['start'] ?? null;
            if ($start) {
                //格式化
                $start_dt = Carbon::parse($start);
                $start_month = $start_dt->format('Y-m');
                return $start_month;
            }
            return '';
        });

        $grid->column('json', '规则')->display(function ($val) {
            return $this->content;
        });

        $grid->column('editor_id', '编辑人')->display(function ($val) {
            return is_null($val) ? '' : ($val > 0 ? UserModel::getUser($val)->name : 'OA');
        });
        $grid->created_at('创建时间')->display(function ($val) {
            return $val ? substr($val, 0, 10) : '';
        });

        $grid->disableExport();
        $grid->disableRowSelector();

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->in('user_id', '用户名')->multipleSelect(UserModel::getAllUsersPluck());
            $filter->equal('type', '类型')->select(FinancialPrizeRuleModel::getTypePluck());
            $filter->where(function ($query) {
                if ($this->input) {
                    $user_ids = UserModel::where('workplace', $this->input)->pluck('id');
                    $query->whereIn('user_id', $user_ids);
                }
            }, '地区', 'place')->select(
                [
                    '深圳' => '深圳',
                    '武汉' => '武汉',
                    '南昌' => '南昌',
                    '西安' => '西安',
                ]
            );
            $filter->equal('valid', '是否生效')->select([1 => '是', 0 => '否']);
        });

        $grid->tools(function (Grid\Tools $tools) {
            $dt = Carbon::now();
            $url = route('financial.prize-rule.createnyear', [$dt->year, $dt->month]);
            $html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$url}" target="_self"  class="btn btn-sm btn-primary"  title="生成本月N年奖规则，然后手动启用">
        <i class="fa fa-money"></i><span class="hidden-xs">&nbsp;&nbsp;生成本月N年奖</span>
    </a>
</div>
eot;
            $tools->append($html);
        });

        $grid->model()->orderBy('id', 'desc');

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
        $show = new Show(FinancialPrizeRuleModel::findOrFail($id));

        $show->user_id('用户名')->as(function ($user_id) {
            return UserModel::find($user_id)->name ?? ('User' . $user_id);
        });
        $show->type('类型')->as(function ($type) {
            return FinancialPrizeRuleModel::getTypeName($type);
        });
        $show->valid('是否生效')->using([1 => '是', 0 => '否']);
        $show->mark('简要说明');
        $show->content('规则内容');
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
        $form = new Form(new FinancialPrizeRuleModel);

        $form->display('id', 'ID');
        $form->select('user_id', '用户名')->options(UserModel::getAllUsersPluck())->rules('required');
        $form->select('type', '类型')->options(FinancialPrizeRuleModel::getTypePluck())->rules('required');
        $form->switch('valid', '是否生效')->states(self::$valid_states);
        $form->text('mark', '简要说明')->help('设置原因、方案等，最多输入200字');
        $form->textarea('content', '规则内容')->help('规则内容，以Json格式，最多输入1500字')->rows(20);;

        $form->tools(function (Form\Tools $tools) {
            // 去掉`删除`按钮
            $tools->disableDelete();
            // 去掉`查看`按钮
            $tools->disableView();
        });

        $form->saving(function (Form $form) {
            $form->model()->editor_id = UserModel::getUser()->id;
        });

        return $form;
    }
}
