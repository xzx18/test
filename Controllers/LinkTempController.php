<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientModel;
use App\Models\LinkModel;
use App\Models\LinkTempModel;
use App\Models\UserModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class LinkTempController extends Controller
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
            ->header('业务系统授权')
            ->description('临时授权给外部用户使用内部系统')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new LinkTempModel());

        $grid->id('ID')->sortable();
        $grid->link_info('Url')->display(function ($val) {
            $data = [
                'user_id' => 0,
                'user_email' => $this->user_email,
                'user_nick' => $this->user_nick,
                'user_avatar' => $this->user_avatar ?? '',
            ];
            $url = ClientModel::getHashedRedirectUrl($val['url'], $data);
            return sprintf("<a href='%s' target='_blank'>%s (%s)</a>", $url, $val['title'], $val['url']);
        });

        $grid->user_email('Email');
        $grid->user_nick('Nickname');
        $grid->user('创建者')->display(function ($val) {
            return $val['name'];
        });

        $grid->created_at('Created at');
        $grid->updated_at('Updated at');

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
        $show = new Show(LinkTempModel::findOrFail($id));

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
        $user = UserModel::getUser();
        $form = new Form(new LinkTempModel);

        $form->display('id', 'ID');
        $form->hidden('created_by', 'created_by')->value($user->id);
        $form->email('user_email', 'Email');
        $form->text('user_nick', 'Nickname');

        $form->select('link_id', '授权系统')->options(LinkModel::all()->pluck('title', 'id'));
        $form->textarea('remark', 'Remark');
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
