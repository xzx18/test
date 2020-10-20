<?php

namespace App\Admin\Controllers;

use App\Models\GrowthCategorysModel;
use App\Models\UserGrowthLifeModel;
use App\Http\Controllers\Controller;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Http\Request;


class UserGrowthLifeController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Request $request
     * @param Content $content
     * @param $user_id
     * @return Content
     */
    public function index(Request $request, Content $content, $user_id = 0)
    {
        $user = UserModel::getUser($user_id);
        if (!$user) {
            return $content
                ->header('个人成长')
                ->description('用户不存在')
                ->body('');
        }

        $is_self = UserModel::getCurrentUserId() == $user_id;
        $hired_month = $user->hired_date ? Carbon::parse($user->hired_date)->startOfMonth() : null;

        $growths = $user->growths->sortBy('date')->toArray();
        $growths[] = $this->createFutureGrowth();

        $index = 0;
        $data = [];
        foreach ($growths as $grow) {
            if (!$is_self && in_array($grow['type'], UserGrowthLifeModel::$not_show_to_other_types)) {
                continue;
            }

            if (isset($grow['date'])) {
                $dt = Carbon::parse($grow['date']);
                $grow['time'] = "{$dt->year}年{$dt->month}月";
                // 是入职之前发生的事情，否置灰
                $grow['gray'] = $hired_month && $hired_month > $dt;
            }

            $grow['comment'] = '';
            if ($grow['use_general_comment']) {
                $item = GrowthCategorysModel::find($grow['type']);
                if ($item) {
                    $grow['comment'] = ($item->general_comment ?? $item->name) . ' ' . ($grow['custom_comment'] ?? '');
                }
            } else {
                $grow['comment'] = $grow['custom_comment'] ?? '';
            }

            $grow['position'] = $index % 2 === 0 ? 'left' : 'right';
            $grow['icon'] = GrowthCategorysModel::getIcon($grow['type'], $grow['gray'] ?? false);

            $data[$index] = $grow;
            $index++;
        }

        $user->growths = $data;
        $view = view('oa.user-growth', ['user' => $user]);

        return $content
            ->header('个人成长')
            ->description($user->name)
            ->body($view);
    }

    private function createFutureGrowth()
    {
        $item = new UserGrowthLifeModel();
        $item->time = "未来";
        $item->use_general_comment = 0;
        $item->custom_comment = "无限可能...";
        $item->type = 9999;
        return $item->toArray();
    }

}
