<?php
/**
 * Created by PhpStorm.
 * User: harry
 * Date: 3/30/2019
 * Time: 09:08
 */

namespace App\Admin\Controllers;

use Encore\Admin\Layout\Content;

class FinancialMyPointController
{
    public function index(Content $content)
    {
        return $content
            ->header('我的积分')
            ->description(' ')
            ->body('<div class="alert alert-danger" role="alert">开发中，敬请期待</div>');
    }
}
