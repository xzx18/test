<?php
/**
 * Created by PhpStorm.
 * User: harry
 * Date: 3/30/2019
 * Time: 09:03
 */

namespace App\Admin\Controllers;

use Encore\Admin\Layout\Content;


class OtherTableRecordController
{
    public function index(Content $content)
    {
        return $content
            ->header('电子表格')
            ->description(' ')
            ->body('<div class="alert alert-danger" role="alert">开发中，敬请期待</div>');
    }
}
