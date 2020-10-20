<?php

namespace App\Admin\Controllers;

use App\Models\AchievementModel;
use App\Models\Department;
use App\Models\UserAchievementModel;
use App\Models\UserModel;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Layout\Row;
use Encore\Admin\Layout\Column;
use Encore\Admin\Widgets\Table;
use Encore\Admin\Widgets\Tab;
use Encore\Admin\Admin;


class UserAchievementController extends Controller
{
    use HasResourceActions;

    /**
     * description 个人成就页面.
     *
     * @param Content $content
     * @return Content
     */
    public function description($id = null, Content $content)
    {
        Admin::css('/css/admin/achievement.css');
        $content->header('个人成就');
        $user = UserModel::getUser($id);
        $content->description($user->name);

        $content->row(function (Row $row) use ($user) {
            $row->column(3, self::getUserDiv($user));
            $row->column(9, function (Column $column) use ($user) {
                $column->row(self::getWorkYearAchievementDiv($user));
            });
        });
        return $content;
    }

    private static function getUserDiv($user)
    {
        //成就
        $achievemets = UserAchievementModel::where('user_id', $user->id)->get();
        $achievement_count = count($achievemets);
        $achive_point_count = 0;
        foreach ($achievemets as $achivement) {
            $achive_point_count += $achivement->achievement->points;
        }
        //部门
        $departments = Department::getUserDepartmentTree($user->id);
        $department = '未知部门';
        if (count($departments[0]) > 1) {
            $department = $departments[0][1]['name'];
        } elseif (count($departments[0]) === 1) {
            $department = $departments[0][0]['name'];
        }

        $userDiv = '<div class="achivement-user">';
        $userDiv .= "<div class='achivement-user-image'><img src='{$user->avatar}' width='120px' height='120px' ></div>";
        $userDiv .= "<div class='achivement-user-name'>{$user->name}</div>";
        $userDiv .= "<div class='achivement-user-department'>{$department}</div>";
        $userDiv .= "<div class='achivement-user-department'>称号：{$achievement_count}</div>";
        $userDiv .= "<div class='achivement-user-department'>成就点：{$achive_point_count}</div>";
        $userDiv .= "</div>";
        return $userDiv;
    }

    private static function getWorkYearAchievementDiv($user)
    {
        $table_div = '';
        $headers = [];
        if ($user->achievements->isNotEmpty()) {
            foreach ($user->achievements->sortBy('type') as $achievement) { //一个用户一个TABLE
                $icon_url = '/image/achievement-' . $achievement->id . '.jpg';  //图标命名 achievement-{achievement_id}.jpg
                $rows[$achievement->type][] = "<div class='icon'><div><img src='$icon_url' width='80px'></div><span>{$achievement->name}({$achievement->points})</span></div>"; //徽章
                $headers[$achievement->type] = AchievementModel::$types[$achievement->type] . '(' . count($rows[$achievement->type]) . '枚）';
            }
            foreach ($headers as $k => $v) {
                $count = count($rows[$k]);
                if ($count < 10) {
                    for ($i = $count; $i < 10; $i++) {  //一行10个
                        $rows[$k][$i] = '';     //为了控制样式
                    }
                    $table = new Table([$v], [$rows[$k]]);
                } else {
                    //todo 样式处理
                    $table = new Table([$v], array_chunk($rows[$k], 10)); //超过10个的
                }
                $table_div .= $table->render() . '<hr />';
            }

            return $table_div;
        } else {
            return '暂无数据...';
        }
    }

    public function sort(Content $content, $type = '')
    {
        Admin::css('/css/admin/achievement.css');

        $content->header('个人成就排行榜');
        $user = UserModel::getUser();
        $content->description($user->name);

        $year = '';
        if ($type === 'year') {
            $tz = UserModel::getUserTimezone();
            $year = Carbon::now($tz)->$type;
        }

        $sorts = UserAchievementModel::getSort($year); //获取数据源

        $tab = new Tab();
        $tab->addLink('总排行榜', route('achievement.sort'), $type === '');
        $tab->addLink('年度排行榜', route('achievement.sort', ['type' => 'year']), $type === 'year');
        $content->body($tab->render());
        //个人排名
        $content->row(function (Row $row) use ($sorts) {
            $row->column(1, '我的排名');
            $row->column(9, self::getSelfSort($sorts));
            $row->column(1, '');
        });
        //排行榜
        return $content->row(function (Row $row) use ($sorts) {
            $row->column(1, '当前战况');
            $row->column(9, self::getSortDiv($sorts));
            $row->column(1, '');
        });
    }

    private static function getSortDiv($sorts)
    {
        $sort_str = '';
        $data = UserAchievementModel::getSortData($sorts);
        foreach ($data as $user_id => $v) {
            $table = new Table([], [$v]);
            $table_str = $table->render();
            $sort_str .= $table_str;
        }
        return $sort_str;
    }

    private static function getSelfSort($sorts)
    {
        $data = UserAchievementModel::getSortData($sorts);
        $user = UserModel::getUser();
        $table = new Table([], [$data[$user->id]]);
        $use_sort_str = $table->render();
        return $use_sort_str;
    }

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
            ->header('称号获取记录')
            ->description(' ')
            ->body($this->grid());
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
            ->header('称号记录详情')
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
            ->header('编辑称号')
            ->description(' ')
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
            ->header('创建称号')
            ->description(' ')
            ->body($this->form());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new UserAchievementModel);

//        $grid->id('Id');
        $grid->column('user.name', '员工');
        $grid->column('achievement.name', '称号');
        $grid->column('achievement.points', '成就点');
        $grid->date('获取称号日期');

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->equal('user_id', '员工')->select(UserModel::all()->pluck('name', 'id'));
            $filter->equal('achievement_id', '称号')->select(AchievementModel::all()->pluck('name', 'id'));
            $filter->equal('date', '日期')->date();
        });

        $grid->disableExport();

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
        $show = new Show(UserAchievementModel::findOrFail($id));

        $show->field('user_id', '员工')->as(function ($val) {
            $name = UserModel::where('id', $val)->value('name');
            return $name;
        });
        $show->field('achievement_id', '称号')->as(function ($val) {
            $name = AchievementModel::where('id', $val)->value('name');
            return $name;
        });
        $show->date('日期');
        $show->updated_at('更新时间');
        $show->created_at('创建时间');
        $show->deleted_at('删除时间');

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new UserAchievementModel);

        $form->select('user_id', '员工')->options(UserModel::all()->pluck('name', 'id'));
        $form->select('achievement_id', '称号')->options(AchievementModel::all()->pluck('name', 'id'));
        $form->date('date', '日期')->default(date('Y-m-d'));

        return $form;
    }
}
