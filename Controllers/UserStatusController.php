<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApproveModel;
use App\Models\AuditModel;
use App\Models\Department;
use App\Models\UserDepartment;
use App\Models\UserModel;
use App\Models\WorkplaceModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Tab;

class UserStatusController extends Controller
{
    use HasResourceActions;
    private $china_tz;
    private $current_time;
    private $approves;
    private $online_users;

    public function index(Content $content)
    {


        $body = <<<eot
        <style>
   .badge{
        margin:2px;
        font-weight: normal;
    }
    .department-name, .workplace-name{
        font-weight: bold;
    }
</style>
eot;
        $this->china_tz = config("app.timezone_china");
        $this->current_time = UserModel::getUserTime();
        $this->current_time->setTimezone($this->china_tz);
        $this->approves = ApproveModel::getApproves($this->current_time->year, $this->current_time->month);
        $this->online_users = AuditModel::getOnlineUsers();


        $tr_online = tr("A green color indicates that the user is online");
        $tr_offline = tr("A red color indicates that the user is offline");

        $box_tips = <<<eot
<span class="badge badge-success">{$tr_online}</span><span class="badge badge-danger">{$tr_offline}</span>
eot;

        $box = new Box(tr('Tips'), $box_tips);


        $tab = new Tab();
        $tab->add(tr("Department"), $this->renderDepartment());
        $tab->add(tr("Workplace"), $this->renderWorkplace());
        $body .= $box->render();
        $body .= $tab->render();

        return $content
            ->header(tr("Online Status"))
            ->description(sprintf(tr('Current online users count: %s'), sizeof($this->online_users)))
            ->body($body);
    }

    public function renderDepartment()
    {
        $tree = Department::getNavTree()->getTree();
        $relation = UserDepartment::all()->toArray();
        $all_users = UserModel::getAllUsers();
        $department_users = [];
        foreach ($relation as $r) {
            $department_users[$r['department_id']][] = $r['user_id'];
        }

        $fnGetUsers = function ($department_id) use ($department_users, $all_users, &$fnIsUserOnline) {
            $list = '';
            if (isset($department_users[$department_id])) {
                $users = $department_users[$department_id];
                foreach ($users as $user_id) {
                    $user = $all_users->where('id', $user_id)->first();
                    $list .= $this->getApproveStatus($user);
                }
            }
            return $list;
        };

        $html = '';
        $fnRender = function ($nodes) use (&$html, &$fnRender, &$fnGetUsers) {
            $html .= sprintf("<ul class='parent'>");
            foreach ($nodes as $node) {
                $html .= sprintf("<li class='child'><span class='department-name badge badge-light'>%s</span>", $node['name']);
                $user_html = $fnGetUsers($node['id']);
                $html .= sprintf('<span class="department-user">%s</span>', $user_html);
                if (isset($node['children'])) {
                    $fnRender($node['children']);
                }
                $html .= "</li>";
            }
            $html .= "</ul>";
        };
        $html .= '<nav class="nav-tree">';
        $html .= $fnRender($tree);
        $html .= '</nav>';
        return $html;
    }

    private function getApproveStatus($user)
    {
        $fnIsUserOnline = function ($user_id) {
            $count = $this->online_users->where('user_id', $user_id)->count();
            return $count > 0;
        };
        $list = '';
        if ($user) {
            $is_online = $fnIsUserOnline($user->id);
            $class = 'badge-success';
            if (!$is_online) {
                $class = 'badge-danger';
            }

            $approve_status = '';
            $approve_style = '';
            $filterd_approves = $this->approves->where('user_id', $user->id);
            if ($filterd_approves && $filterd_approves->count() > 0) {
                foreach ($filterd_approves as $filterd_approve) {
                    $_start_time = Carbon::parse($filterd_approve->start_time, $this->china_tz);
                    $_end_time = Carbon::parse($filterd_approve->end_time, $this->china_tz);

                    if ($this->current_time->greaterThanOrEqualTo($_start_time) && $this->current_time->lessThanOrEqualTo($_end_time)) {
                        $approve_status = ApproveModel::getEventTypeHuman($filterd_approve->event_type);
                        $approve_style = ApproveModel::getEventTypeCssStyle($filterd_approve->event_type);
                    }
                }
            }
            if ($approve_status) {
                $list .= sprintf('<span class="badge %s">%s <span style="%s">%s</span></span>', $class, $user->name, $approve_style, $approve_status);
            } else {
                $list .= sprintf('<span class="badge %s">%s</span>', $class, $user->name);
            }
        }
        return $list;
    }

    public function renderWorkplace()
    {
        $html = '';
        $workplaces = WorkplaceModel::all();
        $html .= sprintf("<ul class='parent'>");
        foreach ($workplaces as $workplace) {
            $users = $workplace->users;
            $html .= sprintf("<li class='child'><span class='workplace-name badge badge-light'>%s</span>", $workplace->title);
            $list = '';
            foreach ($users as $user) {
                $list .= $this->getApproveStatus($user);
            }
            $html .= sprintf('<span class="users">%s</span>', $list);
            $html .= "</li>";
        }
        $html .= "</ul>";
        return $html;
    }
}
