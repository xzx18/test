<?php

namespace App\Admin\Controllers;

use App\Models\WorkplaceModel;
use App\Models\FinancialPrizeModel;
use Encore\Admin\Show;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Widgets\Box;
use Illuminate\Http\Request;
use Encore\Admin\Admin;
use Encore\Admin\Widgets\Tab;
use App\Models\Auth\OAPermission;

class FinancialPrizeMarketController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    protected $request;
    protected static $prize_type = [
        'customer' => '客服奖',
        'ads' => '广告奖',
        'special' => '特殊奖',
    ];

    public function __construct(Request $request){
        $this->request = $request->all();
    }

    private function getTable($tab, $input, &$html)
    {
        $current_user = UserModel::getUser();
        $is_administrator = $current_user->isRole('administrator');
        $is_financial = $current_user->isRole('financial.staff');
        $is_ads_manager = $current_user->isRole('ads.manager');//广告奖
        $is_market_manager = $current_user->isRole('market.manager');//流量奖和客服奖

        $customer_flow_permission = $is_administrator || $is_financial || $is_market_manager;
        $ads_permission = $is_administrator || $is_financial || $is_ads_manager;
        $special_permission = $is_administrator || $is_financial;

        $permissions = [
            'customer' => $customer_flow_permission,
            'ads' => $ads_permission,
            'special' => $special_permission,
        ];

        if (isset($input['prize'])) {
            $tab->addLink('流量奖', route('financial.prize-market.index'), false);
            foreach (self::$prize_type as $type => $name) {
                if ($input['prize'] == $type) {
                    if ($permissions[$type]) {
                        $tab->add($name, $this->grid($input['prize'])->render(), true);
                    } else {
                        $html = OAPermission::ERROR_HTML;
                    }
                } else {
                    $tab->addLink($name, route('financial.prize-market.index') . "?prize={$type}", false);
                }
            }
        } else {
            if ($customer_flow_permission) { //默认打开这个
                $tab->add('流量奖', $this->grid()->render(), true);
                $tab->addLink('客服奖', route('financial.prize-market.index') . '?prize=customer', false);
                if ($ads_permission) {
                    $tab->addLink('广告奖', route('financial.prize-market.index') . '?prize=ads', false);
                }
                if ($special_permission) {
                    $tab->addLink('特殊奖', route('financial.prize-market.index') . '?prize=special', false);
                }
            } elseif ($ads_permission) { //没有权限自动跳转到有权限的tab
                $url = route('financial.prize-market.index') . '?prize=ads';
                header('Location:'.$url);
            } elseif ($special_permission) {
                $url = route('financial.prize-market.index') . '?prize=special';
                header('Location :'.$url);
            } else {
                $html = OAPermission::ERROR_HTML;
            }
        }
        return $tab;
    }

    public function index(Content $content)
    {
        $tab = new Tab();
        $input = $this->request;
        $html = '';
        $tab = $this->getTable($tab ,$input ,$html);
        return $content
            ->header($this->getTitle($input))
            ->description(' ')
            ->body($html ? $html : $tab->render());
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
            ->header($this->getTitle())
            ->description(' ')
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
            ->header($this->getTitle())
            ->description(' ')
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
            ->header($this->getTitle())
            ->description(' ')
            ->body($this->form()->edit($id));
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid($prize = '')
    {
        $grid = new Grid(new FinancialPrizeModel());

        $grid->column('id', '编号');
        $grid->column('user.name', '用户名');
        $grid->column('type', '类型')->display(function ($v) {
            return FinancialPrizeModel::getTypeName($v);
        });
        $grid->money('金额');
        $grid->column('date', '所属月份')->display(function ($val) {
            return Carbon::parse($val)->format('Y-m');
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
        $grid->disableCreateButton();
        $grid->actions(function ($actions) use ($prize, &$grid) {
            $id = $actions->row->id;
            $edit_url = route('financial.prize-market.index') . "/{$id}/edit?prize={$prize}";
            $detail_url = route('financial.prize-market.index') . "/{$id}?prize={$prize}";
            // 去掉编辑
            $actions->disableEdit();
            // 去掉查看
            $actions->disableView();
            // append一个
            // prepend一个操作
            $actions->prepend(' <a href="' . $detail_url . '"><i class="fa fa-eye"></i></a>');
            $actions->prepend(' <a href="' . $edit_url . '"><i class="fa fa-edit"></i></a>');
        });

        $grid->filter(function (Grid\Filter $filter) use ($prize) {
            $filter->disableIdFilter();
            $filter->in('user_id', '用户名')->multipleSelect(UserModel::getAllUsersPluck());
            $filter->like('date', '所属月份')->datetime(['format' => 'YYYY-MM']);
            $types = $prize == "special" ? FinancialPrizeModel::getCommonPluck()  : FinancialPrizeModel::getMarketPluck();
            $filter->equal('type', '类型')->select($types);
        });

        if ($prize == "customer") { //客服奖
            $grid->model()->where('type', FinancialPrizeModel::TYPE_CUSTOMER_SERVICE)->orderBy('updated_at', 'desc');;
            $grid->tools(function (Grid\Tools $tools) use (&$grid) {
                $url = route('financial.prize.customer.import.store');
                $import_html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$url}"  class="btn btn-sm btn-warning"  title="导入">
        <i class="fa fa-upload"></i><span class="hidden-xs">&nbsp;&nbsp;导入</span>
    </a>
</div>
eot;
                $tools->append($import_html);

            });
        } elseif ($prize == "ads") {
            $grid->model()->where('type', FinancialPrizeModel::TYPE_ADS)->orderBy('updated_at', 'desc');//广告奖
        } elseif ($prize == "special") {
            $grid->model()->whereIn('type', FinancialPrizeModel::getCommonTypes())->orderBy('updated_at', 'desc');//特殊奖
        } else {
            $grid->model()->where('type', FinancialPrizeModel::TYPE_FLOW)->orderBy('updated_at', 'desc');//流量
        }

        $grid->tools(function (Grid\Tools $tools) use (&$grid, $prize) {
            $url = route('financial.prize-market.index') . "/create?prize={$prize}";
            $import_html = <<<eot
<div class="btn-group pull-right btn-group-import" style="margin-right: 10px">
    <a href="{$url}"  class="btn btn-sm btn-success"  title="新增">
        </i><span class="hidden-xs">&nbsp;&nbsp;新增</span>
    </a>
</div>
eot;
            $tools->append($import_html);

        });

        $grid->model()->orderBy('id', 'desc');

        return $grid;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $input = $this->request;
        $prize = $input['prize'] ?? '';
        switch ($prize) {
            case 'customer' :
                $prize = '客服奖';
                break;
            case 'ads' :
                $prize = '广告奖';
                break;
            case 'special' :
                $prize = '特殊奖';
                break;
            default :
                $prize = '流量奖';
        }
        $prizes = FinancialPrizeModel::getMarketPluck();
        $default_key = array_search($prize, $prizes);
        $form = new Form(new FinancialPrizeModel);
        $form->display('id', 'ID');
        $form->select('user_id', '用户名')->options(UserModel::getMonthPrizeUsersPluck())->rules('required');
        if ($prize != '特殊奖') {
            $form->select('type',
                '类型')->options(FinancialPrizeModel::getMarketPluck())->default($default_key)->rules('required');
        }
        else {
            $form->select('type',
                '类型')->options(FinancialPrizeModel::getCommonPluck())->default($default_key)->rules('required');
        }
        $form->month('date', '所属月份')->format('YYYY-MM')->rules('required');
        $form->number('money', '金额');
        $form->text('mark', '简要说明')->help('奖项具体名称等，最多输入100字');
        $form->textarea('ext_mark', '详细说明')->help('得奖的具体事迹等，最多输入1000字');
        $form->hidden('year', 'year');

        $form->tools(function (Form\Tools $tools) {
            // 去掉`删除`按钮
            $tools->disableDelete();
            // 去掉`查看`按钮
            $tools->disableView();
        });

        $form->saving(function (Form $f) {
            $f->year = substr($f->date, 0, 4);
            $f->date = $f->date . '-01';
            $f->model()->editor_id = UserModel::getUser()->id;
        });
        $form->saved(function (Form $form) {
            $type = $form->type;
            switch ($type) {
                case '1' :
                    $prize = '?prize=customer';
                    break;
                case '13' :
                    $prize = '?prize=ads';
                    break;
                case '14' :
                    $prize = '?prize=special';
                    break;
                default :
                    $prize = '';
            }
            $return_url = route('financial.prize-market.index') . $prize;
            return redirect($return_url);
        });

        return $form;
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
        $show->date('月')->as(function ($date) {
            return Carbon::parse($date)->format('Y-m');
        });;
        $show->money('金额');
        $show->mark('简要说明');
        $show->ext_mark('详细说明');
        $show->updated_at('更新时间');

        return $show;
    }

    public function customerServiveImport(Content $content)
    {
        $previous_dt = Carbon::now()->addMonth(-1);
        $form = new \Encore\Admin\Widgets\Form();
        $form->action(route('financial.prize.customer.import.store'));
        $form->date('date', '工资所属期')->format('YYYY-MM')->default($previous_dt->format('Y-m'));
        $form->file('file', 'EXCEL文件');
        $box = new Box('导入客服奖', $form->render());
        $html = $box->render();
        Admin::script($this->script());
        return $content
            ->header('导入客服奖')
            ->description('导入excel格式的客服奖')
            ->body($html);
    }

    public function customerServiveStore(Request $request)
    {
        $date = $request->post('date');
        $file = $request->file('file');
        if (!$file) {
            admin_error('导入失败', '未上传文件');
        } else {
            $dt = Carbon::now();
            $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            $file_name = sprintf('import-%s.%s', $dt->format('YmdHis'), $extension);
            $result = $file->storeAs('salaries', $file_name);
            $file_path = storage_path('app/' . $result);
            if (file_exists($file_path)) {
                $return_url = route('financial.prize-market.index') . '?prize=customer';
                $return_html = <<<eot
<div class="btn-group btn-group-import"  style="margin-right: 10px">
    <a href="{$return_url}" class="btn btn-sm btn-warning"  title="返回">
        <i class="fa fa-refresh"></i><span class="hidden-xs" style="margin-left: 10px;">返回</span>
    </a>
</div>
eot;
                $result = (new FinancialPrizeModel())->customerServiveImport($date, $file_path);
                @unlink($file_path);
                if ($result['status']) {
                    admin_success('导入成功', $result['message'] . $return_html);
                } else {
                    admin_error('导入失败', $result['message'] . $return_html);
                }
            } else {
                admin_error('文件上传失败', sprintf('文件不存在: %s', $file_path));
            }
        }
    }

    public function getTitle()
    {
        $input = $this->request;
        if (isset($input['prize']) && $input['prize'] == 'customer') {
            $title = '客服奖';
        } elseif (isset($input['prize']) && $input['prize'] == 'ads') {
            $title = '广告奖';
        } elseif (isset($input['prize']) && $input['prize'] == 'special') {
            $title = '特殊奖';
        } else {
            $title = '流量奖';
        }
        return $title;
    }

    public function script()
    {
        return $js = <<<EOT
 $('form').submit(function () {
            swal({
                title: '确定上传该文件吗？',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '确定！',
                cancelButtonText: '取消！',
                confirmButtonClass: 'btn btn-success',
                cancelButtonClass: 'btn btn-danger',
                buttonsStyling: false
            }).then(function(isConfirm) {
                if (isConfirm.value === true) {
                   return true;
                }else{
                    return false;
                    }
            });
    });
EOT;
    }


}
