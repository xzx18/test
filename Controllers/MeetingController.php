<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MeetingModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Tab;

class MeetingController extends Controller
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
        $tr_HostMeeting = tr("Host Meeting");
        $tr_JoinMeeting = tr("Join Meeting");
        $tr_Username = tr("Username");
        $tr_Password = tr("Password");

        $host_url = "https://wx.webex.com.cn/mw3200/mywebex/default.do?siteurl=wx&service=1";
        $joni_url = "https://wx.webex.com.cn/mc3200/meetingcenter/joinmeeting/unlist.do?siteurl=wx";

        $info_host_meeting = new Box($tr_HostMeeting, sprintf('%s: wx<br>%s: Wangxu1818<br><br><a  class="btn btn-primary btn-lg external-links" href="%s" target="_blank">%s</a>', $tr_Username, $tr_Password, $host_url, $tr_HostMeeting));
        $info_host_meeting->style('success');

        $info_joni_meeting = new Box($tr_JoinMeeting, sprintf('请找主持人要会议号<br>参会密码: %s<br><br><a class="btn btn-success btn-lg external-links" href="%s" target="_blank">%s</a>', "1818", $joni_url, $tr_JoinMeeting));
        $info_joni_meeting->style('warning');

        $html_host = $info_host_meeting->render();
        $html_joni = $info_joni_meeting->render();

        $grid = $this->grid()->render();

        $body = <<<eot
<div class="row">
<div class="col-md-6">
{$html_host}
</div>
<div class="col-md-6">
{$html_joni}
</div>
</div>
{$grid}
eot;

        $tab = new Tab();
        $tab->add('Cisco', $body);

        return $content
            ->header(tr("Meeting System"))
            ->description(tr("Meeting System"))
            ->body($tab);
    }

    protected function grid()
    {
        $now = Carbon::now();
        $timezone = UserModel::getUserTimezone();

        $grid = new Grid(new MeetingModel());
        $grid->disableExport();
        $grid->disableCreateButton();
        $grid->disableActions();
        $grid->disableFilter();
        $grid->disableRowSelector();
        $grid->model()->orderBy('time', 'desc');

        $grid->id('ID')->sortable();
        $grid->code('参会码');
        $grid->subject('会议主题');
        $grid->time('会议时间')->sortable()->display(function ($val) use ($now, $timezone) {
            $dt = Carbon::parse($val);
            $style = "success";
            if ($now->greaterThan($dt)) {
                $style = "danger";
            }
            return "<span class='badge badge-$style font-weight-normal'>" . $dt->setTimezone($timezone)->toDateTimeString() . "</span>";
        });
        $grid->password('会议密码');
        $grid->updated_at('Updated at');

        return $grid;
    }
}
