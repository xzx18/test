<?php

namespace App\Admin\Controllers;

use App\Models\Department;
use App\Models\UserModel;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserDepartmentsController extends Controller
{
    //

    public function index(Content $content)
    {
        return $content->header('用户部门(数据检测)')
            ->description('加粗的为首选部门；当一个人在多个职能部门（非PO线部门）中时，应设置其首选部门；否则显示为红色')
            ->body($this->showUserDepartments());
    }

    private function showUserDepartments()
    {
        $data = [];
        $users = UserModel::getAllUsers();
        foreach ($users as $user) {
            $data[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'departments' => Department::getUserDepartmentTree($user->id)
            ];
        }

        $data_quit = [];
        $users_quit = UserModel::getRecentLeaveUsersPluck();
        foreach ($users_quit as $id => $name) {
            $data_quit[] = [
                'user_id' => $id,
                'user_name' => $name,
                'departments' => Department::getUserDepartmentTree($id)
            ];
        }

        $content = "<h3>在职员工</h3>";
        $content .= $this->getDepartmentsDetails($data);

        $content .= "<h3>近期离职员工</h3>";
        $content .= $this->getDepartmentsDetails($data_quit);

        return $content;
    }

    private function getDepartmentsDetails(array &$data)
    {
        $details = '';
        foreach ($data as $datum) {
            $has_prefered = false;
            $multi = count($datum['departments']) > 1;

            $dep_str = '';
            foreach ($datum['departments'] as $deps) {
                $str2 = '';
                $this_prefered = false;
                foreach ($deps as $dep) {
                    $this_prefered |= $dep->prefered ?? false;
                }
                $has_prefered |= $this_prefered;

                $sub_dep = end($deps);
                if ($this_prefered) {
                    $str2 .= "<span style='color: #3C8DBC;font-weight: bold'>{$sub_dep->name}({$sub_dep->id})</span>";
                }
                else {
                    $str2 .= "{$sub_dep->name}({$sub_dep->id})";
                }
                $dep_str .= $str2 . '; ';
            }

            $user_str = "{$datum['user_id']} {$datum['user_name']}: ";
            if ($multi) {
                $color = $has_prefered ? 'green' : 'red';
                $user_str = "<span style='color: {$color}'>{$user_str}</span>";
            }

            $details .= $user_str . $dep_str . '<br/>';
        }
        return $details;
    }
}
