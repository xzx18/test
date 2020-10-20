<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Auth\OAPermission;
use App\Models\ClientModel;
use App\Models\LinkCategoryModel;
use App\Models\LinkModel;
use App\Models\UserModel;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Tab;
use Illuminate\Support\Collection;

class LinkController extends Controller
{
    use HasResourceActions;


    public function display(Content $content)
    {
        $fnGetBoxTitle = function ($title, $count, $style = 'primary') {
            return <<<EOT
 <span class="badge badge-{$style}" style="font-size:16px;">{$title} ({$count})</span>
EOT;
        };
        $fnGetLinkItem = function ($href_url, $title, $display_link, $class = 'external-links', $data_script = '') {
            if (!$class) {
                $class = "external-links";
            }
            $display_link = str_replace(['https://', 'http://', 'www.'], '', $display_link);
            $block_html = <<<eot
<a href="{$href_url}" target="_blank" class="{$class}" {$data_script} style="margin: 5px;">
	<span class="badge badge-light font-weight-normal">
	<b>{$title}</b><br>
	<span style="font-size: 9px;">{$display_link}</span>
	</span>
</a>
eot;
            return $block_html;
        };


        $user = UserModel::getUser();
        $body = '';
        $is_client = isLoadedFromClient(false);
        /** @var Collection $categorys */
        $categorys = LinkCategoryModel::all();

        $items = [];
        /** @var LinkCategoryModel $category */
        foreach ($categorys as $category) {
            $urls = $category->urls;
            foreach ($urls as $link) {
                $tr_title = tr($link->category_info['title']);
                $cat_key = $fnGetBoxTitle($tr_title, "#count#", $link->category_info['style']);
                $items[$cat_key][] = $link;
            }
        }

        foreach ($items as $cat_title => $link_objs) {
            $link_html = '';
            foreach ($link_objs as $link) {
                $class = "external-links";
                $data_script = '';
                if ($is_client) {
                    $url = ClientModel::getHashedRedirectUrl($link->url);
                    if ($link->open_type == LinkModel::OPEN_TYPE_INSIDE || $link->script) {
                        $script = preg_replace("/\r|\n/", "", $link->script);
                        $script = str_replace("{username}", $user->username, $script);
                        $script = str_replace("{password}", config("app.system_password"), $script);
                        $data_script = 'data-script="' . base64_encode($script) . '"';
                    }
                } else {
                    $url = ClientModel::generateRedirectUrl($link->url);
                }
                $tr_link_title = tr($link->title);

                $display_link = $link->url;
                $link_html .= $fnGetLinkItem($url, $tr_link_title, $display_link, $class, $data_script);
            }
            $cat_title = str_replace('#count#', sizeof($link_objs), $cat_title);
            $box = new Box($cat_title, $link_html);
            $box->view("admin.widgets.box");
            $body .= $box->render();
        }

        $wp_admin_users = config('app.wp_admin_users');
        if (in_array($user->username, $wp_admin_users)) {
            $wp_html = '';
            $tab = new Tab();
            $tab->add('常规系统', $body);
            $wp_websites = config("wpwebsites");
            foreach ($wp_websites as $title => $websites) {
                $block_html = '';
                ksort($websites);
                foreach ($websites as $language => $url) {
                    $href_url = ClientModel::generateRedirectUrl("$url/wp/wp-login.php");
                    $block_html .= $fnGetLinkItem ($href_url, $language, $url, '', '');
                }
                $title = $fnGetBoxTitle($title, sizeof($websites), 'success');
                $box = new Box($title, $block_html);
                $box->view("admin.widgets.box");
                $wp_html .= $box->render();
            }

            $tab->add('WP后台', $wp_html);
            $body = $tab->render();
        }


        $style = <<<EOT
<style>
   .box-body .badge{
        border: 1px solid #b8c0ca;
        padding: 5px; 
        font-weight: normal;
        margin:2px;
    }
   .box-body .badge:hover{
        background: #ffc107;
        border: 1px solid #17a2b8;
    }
</style>
EOT;
        return $content
            ->header(tr("Private System"))
            ->description(' ')
            ->body($style . $body);

    }

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public
    function index(
        Content $content
    ) {

        return $content
            ->header('链接设置')
            ->description('顺序：数值越小越靠前')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new LinkModel());
//        $grid->disableFilter();
//        $grid->disableRowSelector();
        $grid->disableExport();

        $grid->id('ID')->sortable();
        $grid->position('排序')->sortable();
        $grid->title('标题')->display(function ($val) {
            if ($this->script) {
                return "<b>$val</b>";
            }
            return $val;
        });
        $grid->url('链接')->sortable()->display(function ($val) {
            return "<a href='$val' target='_blank'>$val</a>";
        });

        $grid->open_type('打开方式')->sortable()->display(function ($val) {
            $tyle = LinkModel::getOpenTypeStyle($val);
            $desc = LinkModel::getOpenTypeHuman($val);
            return <<<EOT
<span class="badge badge-{$tyle}">{$desc}</span>
EOT;
        });
        $grid->category_info('分组')->display(function ($val) {
            return <<<EOT
<span class="badge badge-{$val['style']}">{$val['title']}</span>
EOT;
        });

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->like('title', 'Title');
            $filter->like('url', 'Url');
            $filter->in('category_id', 'Category')->multipleSelect(LinkCategoryModel::all()->pluck('title', 'id'));
        });
        $grid->model()->orderBy('category_id')->orderBy('position');
        return $grid;
    }

    /**
     * Show interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public
    function show(
        $id, Content $content
    ) {
        return $content
            ->header('详情')
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
        $show = new Show(LinkModel::findOrFail($id));
        $show->id('ID');
        $show->title('标题');
        $show->url('链接');
        $show->tag('标签');
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
    public
    function edit(
        $id, Content $content
    ) {
        return $content
            ->header('编辑')
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
        $form = new Form(new LinkModel);

        $form->display('id', 'ID');
        $form->text('title', '标题');
        $form->url('url', '链接');
//        $form->image('icon', '图标');
        $form->select('category_id', '分组')->options(LinkCategoryModel::all()->pluck('title', 'id'));
        $form->number('position', '排序')->help("数值越小越靠前")->default(100);
        $form->select('open_type', '打开方式')->options(LinkModel::$OPEN_TYPE);
        if (OAPermission::isAdministrator()) {
            $form->textarea('script', '注入脚本')->rows(5);
        }
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
    public
    function create(
        Content $content
    ) {
        return $content
            ->header('Create')
            ->description('description')
            ->body($this->form());
    }
}
