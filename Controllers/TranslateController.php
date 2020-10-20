<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TranslateModel;
use App\Models\UserModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class TranslateController extends Controller
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
//        $file = sprintf("%s/%s/oa.php", App::langPath(), "zh-CN");  //"E:\Apowersoft\Source\web\o.wangxutech.com\resources\lang"
//        $keys = include $file;
////        dd($keys);
//        $user = DingtalkUser::getUser();
//        foreach ($keys as $k => $v) {
//            $row = new TranslateModel();
//            $row->lang_key = $k;
//            $row->zh_cn = $v;
//            $row->en = $k;
//            $row->updated_userid = $user->id;
//            $row->updated_username = $user->name;
//            $row->save();
//        }

        return $content
            ->header('翻译管理')
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
        $getFormattedValue = function ($val) {
            return sprintf('<div style="background: #f5f5f5; color:#333; border: 1px solid #ccc; padding: 4px 10px; border-radius: 5px; max-width: 300px;">%s</div>', htmlentities($val));
        };
        $grid = new Grid(new TranslateModel());
        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->like('lang_key', 'Key');
            $filter->like('zh_cn', 'zh-CN');
            $filter->like('en', 'en-US');
        });
        $grid->id('ID')->sortable();
        $grid->lang_key('Key')->sortable()->display(function ($val) use ($getFormattedValue) {
            return sprintf('<div style="max-width: 300px; color: gray">%s</div>', htmlentities($val));
        });
        $grid->zh_cn('zh-CN')->display(function ($val) use ($getFormattedValue) {
            return $getFormattedValue($val);
        });
        $grid->en('en-US')->display(function ($val) use ($getFormattedValue) {
            return $getFormattedValue($val);
        });
        $grid->note(tr("Remark"))->display(function ($val) use ($getFormattedValue) {
            return sprintf('<div style="max-width: 300px; color: gray">%s</div>', htmlentities($val));
        });
        $grid->updated_username('修改人');
        $grid->updated_at(trans("admin.updated_at"))->sortable();
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
            ->header('翻译列表')
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
        $show = new Show(TranslateModel::findOrFail($id));

        $show->id('ID');
        $show->lang_key('Key');
        $show->zh_cn('zh-CN');
        $show->en('en');
        $show->note(tr("Remark"));

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
            ->body($this->form($id)->edit($id));
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($edit_id = null)
    {
        $user = UserModel::getUser();

        $form = new Form(new TranslateModel);

        $form->display('id', 'ID');
        if ($edit_id) {
            $form->text('lang_key', 'Key')->disable();
        } else {
            $form->text('lang_key', 'Key');
        }
        $form->hidden('updated_userid')->value($user->id);
        $form->hidden('updated_username')->value($user->name);

        $form->textarea('zh_cn', 'zh-CN')->help('%s 这样的是占位字符，会被实际数据所替换，不可修改');
        $form->textarea('en', 'en-US')->help('%s 这样的是占位字符，会被实际数据所替换，不可修改');
        $form->textarea('note', tr("Remark"));

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
