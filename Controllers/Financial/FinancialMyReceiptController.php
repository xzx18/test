<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FinancialReceiptModel;
use App\Models\UserModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class FinancialMyReceiptController extends Controller
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
            ->header('票据管理')
            ->description('为了财务自动化核算工资，个人报销票据将在这里进行提交审核')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new FinancialReceiptModel());

        $grid->id('ID')->sortable();


        $grid->type('票据类型')->sortable()->display(function ($val) {
            return FinancialReceiptModel::getReceiptTypeHuman($val);
        });
        $grid->status('状态')->sortable()->display(function ($val) {
            $display = FinancialReceiptModel::getReceiptStatusHuman($val);
            $style = FinancialReceiptModel::getReceiptStatusStyle($val);

            return "<span class='badge badge-$style font-weight-normal'>$display</span>";
        });
        $grid->receipt_time('票据时间')->sortable();
        $grid->amount_money('票据金额')->sortable();
        $grid->filepath('票据照片')->display(function ($val) {
            $filename = pathinfo($val, PATHINFO_BASENAME);
            $image_url = "/storage/receipts/$filename";
            $html = "<a href='$image_url' target='_blank'><img src='$image_url'  style='max-width: 100px; max-height: 100px;' /></a>";
            return $html;
        });
        $grid->related_users('关联人员')->display(function ($users) {
            $content = '';
            if ($users && sizeof($users) > 0) {
                $staff_items = [];
                foreach ($users as $v) {
                    $staff_items[] = "<span class='badge badge-pill badge-info' style='font-weight: normal;'>{$v['name']}</span>";
                }
                $list = implode(" ", $staff_items);
                $content = "<div style=\"max-width: 500px\">{$list}</div>";
            }
            return $content;
        });
        $grid->column('confirmedBy.name', '审核者');

        $grid->mark('备注');

        $grid->created_at(trans('admin.created_at'));
        $grid->updated_at(trans('admin.updated_at'));

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableView();
            $row = $actions->row;
            if ($row->status == FinancialReceiptModel::RECEIPT_STATUS_CONFIRMED) {
                $actions->disableDelete();
                $actions->disableEdit();
            }
        });
        $grid->disableRowSelector();
        $grid->disableFilter();

        $grid->tools(function (Grid\Tools $tools) {
            $tools->disableBatchActions();
        });

        $grid->model()->orderBy('id', 'desc');
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
        $show = new Show(FinancialReceiptModel::findOrFail($id));

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
            ->header('编辑票据')
            ->description(' ')
            ->body($this->form()->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $user_id = UserModel::getCurrentUserId();
        $form = new Form(new FinancialReceiptModel);

        $form->display('id', 'ID');
        $form->hidden('user_id', 'user_id')->default($user_id)->value($user_id);
        $form->select('type', '票据类型')->options(FinancialReceiptModel::$RECEIPT_TYPES)->default(FinancialReceiptModel::RECEIPT_TAXI_INVOICE);
        $form->datetime('receipt_time', '票据时间')->help('票据产生的时间，比如打车发票上面的时间，<b style="color: red;">核算工资审核将以该时间为准<b>');
        $form->decimal('amount_money', '票据金额');
        $form->image('filepath', '票据照片')->uniqueName()->move('public/receipts');
        $form->multipleSelect('related_users', '关联人员')->options(UserModel::getAllUsersPluck())->help('比如多人拼车，随行人员即为关联人员');
        $form->textarea('mark', '备注');

        $form->display('created_at', trans('admin.created_at'));
        $form->display('updated_at', trans('admin.updated_at'));

        $form->disableEditingCheck();
        $form->disableViewCheck();
        $form->tools(function (Form\Tools $tools) {
            $tools->disableView();
            $tools->disableDelete();
        });
        $form->saving(function ($form) use ($user_id) {
            $form->user_id = $user_id;
        });
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
            ->header('添加票据')
            ->description(' ')
            ->body($this->form());
    }
}
