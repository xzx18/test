<?php

namespace App\Admin\Controllers;

use App\Models\FinancialTaxDeductionModel;
use App\Models\UserModel;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;

class FinancialTaxDeductionController extends Controller
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
            ->header('税务专项扣除列表')
            ->description(' ')
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
            ->header('添加专项扣除')
            ->description(' ')
            ->body($this->form());
    }

    public function grid()
    {
        $grid = new Grid(new FinancialTaxDeductionModel);
        $grid->model()->select('user_id')->groupBy('user_id');

        $grid->column('user.name', '用户名');
        $grid->column('user.truename', '真实名');
        $grid->column('证件类型')->display(function ($val) {
            return '居民身份证';
        });
        $grid->column('证件号码')->display(function () {
            $user = UserModel::find($this->user_id);
            return $user ? $user->roster->cert_no : '';
        });
        foreach (FinancialTaxDeductionModel::$tax_type as $type => $tax) {
            $grid->column($tax)->display(function () use ($type, $tax) {
                $current_date = date("Y-m-d");
                return FinancialTaxDeductionModel::where('tax_type', $type)
                    ->where('start_month', '<=', $current_date)
                    ->where('end_month', '>=', $current_date)
                    ->where('user_id', $this->user_id)->sum('money'); //金额
            });
        }

        $grid->column('总金额')->display(function () {
            $current_date = date("Y-m-d");
            $sum = FinancialTaxDeductionModel::where('start_month', '<=', $current_date)
                ->where('end_month', '>=', $current_date)
                ->where('user_id', $this->user_id)->sum('money'); //总金额
            return "<b>{$sum}</b>";
        });

        $grid->disableRowSelector();
        $grid->disableCreateButton();
        $grid->disableExport();
        $grid->actions(function ($actions) {
            $user_id = $actions->row->user_id;
            $detail_url = route('financail.Tax.detail.index');
            $actions->disableEdit();
            $actions->disableView();
            $actions->disableDelete();
            $actions->prepend("<a href='{$detail_url}?user_id={$user_id}'><i class='fa fa-eye'></i></a>");
        });

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->equal('user_id', '姓名')->select(self::getUserPluck());
            $filter->where(function ($query) {
                if ($this->input) {
                    $user_ids = UserModel::where('workplace', $this->input)->pluck('id');
                    $query->whereIn('user_id', $user_ids);
                }
            }, '地区', 'place')->select(
                ['深圳' => '深圳', '武汉' => '武汉', '南昌' => '南昌', '西安' => '西安',]
            );
        });
        return $grid;
    }

    private static function getUserPluck()
    {
        $users = [];
        foreach (UserModel::all() as $user) {
            if ($user->name && $user->truename) {
                $users[$user->id] = "{$user->name} ({$user->truename})";
            }
        }
        return $users;
    }

}
