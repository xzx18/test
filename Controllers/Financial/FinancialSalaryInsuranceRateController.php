<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FinancialCategoryModel;
use App\Models\FinancialInsuranceRatioModel;
use App\Models\WorkplaceModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class FinancialSalaryInsuranceRateController extends Controller
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
            ->header('缴费比率')
            ->description('缴费比率设置，单位为 %')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
//			$info = FinancialInsuranceRatioModel::getInsuranceByWorkplace('深圳');
//			dd($info->toArray());

//			$result = FinancialInsuranceRatioModel::getInsurancePaymentInfo('深圳', true, 2200, 5009, 2200, 2200, 2200, 5000);
//			dd($result);

        $grid = new Grid(new FinancialInsuranceRatioModel());
        $grid->setView('oa.financial-insurance-radio-table');

        $grid->id('ID')->sortable();
        $grid->workplace('地区')->sortable();
        $grid->endowment_personal_native_rate('本地个人')->sortable();
        $grid->endowment_personal_strangers_rate('外地个人')->sortable();
        $grid->endowment_organization_native_rate('本地单位')->sortable();
        $grid->endowment_organization_strangers_rate('本地单位')->sortable();
        $grid->medical_personal_rate('个人')->sortable();
        $grid->medical_personal_surcharge('个人附加费')->sortable();
        $grid->medical_organization_rate('单位')->sortable();
        $grid->medical_organization_surcharge('单位附加费')->sortable();

        $grid->supplementary_medical_personal_surcharge('个人附加费')->sortable();
        $grid->supplementary_medical_organization_surcharge('单位附加费')->sortable();

        $grid->employment_injury_personal_rate('个人')->sortable();
        $grid->employment_injury_organization_rate('单位')->sortable();
        $grid->unemployment_personal_rate('个人')->sortable();
        $grid->unemployment_organization_rate('单位')->sortable();
        $grid->maternity_personal_rate('个人')->sortable();
        $grid->maternity_organization_rate('单位')->sortable();
        $grid->housing_provident_fund_personal_rate('个人')->sortable();
        $grid->housing_provident_fund_organization_rate('单位')->sortable();

        $grid->mark('备注');
//			$grid->created_at(trans('admin.created_at'));
        $grid->updated_at(trans('admin.updated_at'));

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
        $show = new Show(FinancialCategoryModel::findOrFail($id));

        $show->id('ID');
        $show->name('类目名称');
        $show->tax('扣税');
        $show->mark('备注');
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
            ->header('编辑五险一金缴费比率')
            ->description(' ')
            ->body($this->form($id)->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($id = null)
    {
        $form = new Form(new FinancialInsuranceRatioModel);
        $form->disableViewCheck();
        $form->disableEditingCheck();

        $form->display('id', 'ID');
        if ($id) {
            $form->display('workplace', '地区');
        } else {
            $form->select('workplace', '地区')->options(WorkplaceModel::all()->pluck('title', 'name'));
        }

        $form->decimal('endowment_personal_native_rate', '养老本地个人比率');
        $form->decimal('endowment_personal_strangers_rate', '养老外地个人比率');
        $form->decimal('endowment_organization_native_rate', '养老本地单位比率');
        $form->decimal('endowment_organization_strangers_rate', '养老本地单位比率');
        $form->decimal('medical_personal_rate', '医疗个人比率');
        $form->decimal('medical_personal_surcharge', '医疗个人附加费')->help('主要针对西安');
        $form->decimal('medical_organization_rate', '医疗单位比率');
        $form->decimal('medical_organization_surcharge', '医疗单位附加费')->help('主要针对西安');

        $form->decimal('supplementary_medical_personal_surcharge', '补充医疗个人附加费');
        $form->decimal('supplementary_medical_organization_surcharge', '补充医疗单位附加费');


        $form->decimal('employment_injury_personal_rate', '工伤个人比率');
        $form->decimal('employment_injury_organization_rate', '工伤单位比率');
        $form->decimal('unemployment_personal_rate', '失业个人比率');
        $form->decimal('unemployment_organization_rate', '失业单位比率');
        $form->decimal('maternity_personal_rate', '生育个人比率');
        $form->decimal('maternity_organization_rate', '生育单位比率');
        $form->decimal('housing_provident_fund_personal_rate', '公积金个人比率');
        $form->decimal('housing_provident_fund_organization_rate', '公积金单位比率');
        $form->textarea('mark', '备注');

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
            ->header('新建')
            ->description('按地区新建五险一金缴费比率')
            ->body($this->form(null));
    }
}
