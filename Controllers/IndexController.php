<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Auth\OAPermission;
use App\Models\ClientModel;
use App\Models\Department;
use App\Models\StarUserModel;
use App\Models\GreetingcardModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Tab;
use Encore\Admin\Widgets\Table;
use App\Libs\Lunar;
use App\Models\AttendanceWorkDaysModel;
use Encore\Admin\Facades\Admin;

/** 首页 */
class IndexController extends Controller
{
    public function index(Content $content)
    {

//        getTest($a = 5);
//        $n = 4;
//        $a = [4,3,2,1];
//        $b = $c = array();
//        getHanNuoTa($n,$a,$b ,$c );
//        dd($c);
        /**
         * 测试数据
         */
//        $arrCate = array(  //待排序数组
//            array( 'id'=>1, 'name' =>'顶级栏目一', 'parent_id'=>0),
//            array( 'id'=>2, 'name' =>'顶级栏目二', 'parent_id'=>0),
//            array( 'id'=>3, 'name' =>'栏目三', 'parent_id'=>1),
//            array( 'id'=>4, 'name' =>'栏目四', 'parent_id'=>3),
//            array( 'id'=>5, 'name' =>'栏目五', 'parent_id'=>4),
//            array( 'id'=>6, 'name' =>'栏目六', 'parent_id'=>2),
//            array( 'id'=>7, 'name' =>'栏目七', 'parent_id'=>6),
//            array( 'id'=>8, 'name' =>'栏目八', 'parent_id'=>6),
//            array( 'id'=>9, 'name' =>'栏目九', 'parent_id'=>7),
//        );
//
//        header('Content-type:text/html; charset=utf-8'); //设置utf-8编码
//        echo '<pre>';
//        dd(getMenuTree($arrCate, 0, 0));
//        echo '</pre>';
        $body = '';
        $current_user = UserModel::getUser();

        $all_users = [
            // 今天生日
            4 => $this->getBirthdayUsers(),
            // 入职满N周年
            5 => $this->getEntryAgeUsers(),
            // 本月新星
            6 => $this->getNewUsers(),
            // 客服之星
            7 => $this->getCustomerServiceUsers()
        ];

        $has_users = false;
        foreach ($all_users as $type => $users) {
            if ($users) {
                $has_users |= true;
                $body .= $this->getUserModule($type, $users, $current_user);
            }
        }

        if ($has_users) {
            $body .= $this->getJs();
        }

        $this->getFestivalModule($body, $current_user);

        $comment_list_url = route('workingage.comments.user', $current_user->id);

        return $content
            ->header(tr('Dashboard'))
            ->description("<a href='{$comment_list_url}'><span class='text-primary text-bold'>我的消息</span></a>")
            ->body($body);
    }


    private function getBirthdayUsers()
    {
        $users = [];
        $current_dt = UserModel::getUserTime();
        do {
            $users = array_merge($users, UserModel::getBirthdayUsers($current_dt->toDateString()));
        } while (!AttendanceWorkDaysModel::isWorkday($current_dt->subDays(1)->toDateString()));
        return $users;
    }

    private function getEntryAgeUsers()
    {
        $users = [];
        $current_dt = UserModel::getUserTime();
        do {
            $users = array_merge($users, UserModel::getEntryAgeUsers($current_dt));
        } while (!AttendanceWorkDaysModel::isWorkday($current_dt->subDays(1)->toDateString()));
        return $users;
    }

    private function getNewUsers()
    {
        $users = [];
        $dt_now = Carbon::now();
        if ($dt_now->day >= 27) {
            // 从27号起，第一个工作日，连续2天
            $day = $this->getStartDay(27);
            if ($day && in_array(Carbon::now()->day, [$day, $day + 1])) {
                $users = UserModel::getNewUsers();
            }
        }
        return $users;
    }

    private function getCustomerServiceUsers()
    {
        $users = [];

        $dt_now = Carbon::now();
        if ($dt_now->day >= 13 && $dt_now->day < 21) {
            // 从13号起，第一个工作日，连续2天
            $day = $this->getStartDay(13);
            if ($day && in_array(Carbon::now()->day, [$day, $day + 1])) {
                $users = StarUserModel::getStarUsers(StarUserModel::STAR_TYPE_CUSTOMER_SERVICE);
            }
        }

        return $users;
    }

    private function getStartDay($try_day)
    {
        $dt_now = Carbon::now();
        $day = $try_day;
        while ($day < $try_day + 7) {
            $dt = Carbon::create($dt_now->year, $dt_now->month, $day);
            if (AttendanceWorkDaysModel::isWorkday($dt->toDateString())) {
                return $day;
            }
            $day++;
        }
        return false;
    }

    private function getUserModule($type, $users, $current_user)
    {
        $body = '';

        $per_page = 3;
        $count = count($users);
        if ($count == 0) {
            return $body;
        }

        // 随机排序，防上某人总在前面或者总在后面
        if ($count > $per_page) {
            shuffle($users);
        }

        // 总是把自己放在前面
        $current_user_key = -1;
        foreach ($users as $key => $user) {
            $user_id = is_array($user) ? $user['id'] : $user->id;
            if ($user_id == $current_user->id) {
                $current_user_key = $key;
                break;
            }
        }
        $has_current_user = $current_user_key >= 0;

        switch ($type) {
            case 4 :
                $title = $has_current_user ? '特别的祝福，送给特别的你' : '生日祝福';
                $hidden_class = 'birthday-turn-play';
                $attr = "[data-timer='birthday-user']";
                $turn_play_class = '.birthday-turn-play';
                $type_box = 'birthday-box';
                $years = Carbon::now()->year;
                break;
            case 5 :
                $title = '感谢有你，一路相随';
                $hidden_class = 'turn-play';
                $attr = "[data-timer='entryage-user']";
                $turn_play_class = '.turn-play';
                $type_box = 'entryage-box';
                break;
            case 6 :
                $title = '每月新星';
                $hidden_class = 'new-turn-play';
                $attr = "[data-timer='new-user']";
                $turn_play_class = '.new-turn-play';
                $type_box = 'new-user-box';
                break;
            case 7 :
                $title = '客服之星';
                $hidden_class = 'star-turn-play';
                $attr = "[data-timer='star-user']";
                $turn_play_class = '.star-turn-play';
                $type_box = 'star-user-box';
                break;
            default:
                $title = $hidden_class = $attr = $turn_play_class = $type_box = $type_box = '';
                break;
        }

        if (!$title) {
            return $body;
        }

        /*if ($has_current_user) {
            $title = "<span style='color: #EA6AA4'>{$title}</span>";
        }*/

        if ($current_user_key > 0) {
            // 把自己放到最前面
            $tmp = $users[0];
            $users[0] = $users[$current_user_key];
            $users[$current_user_key] = $tmp;
        }

        $user_module = '';
        for ($i = 0; $i < $count; $i++) {
            $user = $users[$i];
            if (!is_array($user)) {
                $user = $user->toArray();
            }

            $user_module .= $this->getUserDiv($user, $type, $user['id'] == $current_user->id);
            if (($i + 1) % $per_page == 0 || $i == $count - 1) {
                //每页最后一个或者最后一页的最后一个
                $card_content = "<div class='{$type_box}'>{$user_module}</div>";
                $user_module = '';

                $card_box = new Box($title, $card_content);
                $card_box->solid();
                if (intdiv($i, $per_page) == 0) {
                    // 第一页
                    $card_box->class("{$hidden_class} box");
                } else {
                    $card_box->class("{$hidden_class} box turn-hide");
                }
                $body .= $card_box->render();
            }
        }

        $pages = ceil($count / $per_page);
        if ($pages > 1) {
            $timer_js = <<<eot
<script>
            $(function () {
                attr = "{$attr}";
                user_class = "{$turn_play_class}";
                n = "{$pages}";
                setUserIntval(attr ,user_class ,n);

          });
</script>
eot;
            $body .= $timer_js;
        }

        return $body;
    }

    private function getFestivalModule(&$body, $current_user)
    {
        $current_user_language = $current_user->getLanguage();
        //节日祝福
        $card = GreetingcardModel::getFestivalCard();
        if ($card) {
            $festival_box = new Box($card->title, $card->content);
            $festival_box->style('danger');
            $festival_box->removable();
            $body .= $festival_box->render();
        }

        $post_count = (int)config('oa.index.post.count');
        if (!$post_count || $post_count < 0) {
            $post_count = 15;
        }
        $tab = new Tab();
        $languages = config("app.languages");
        if ($current_user_language == 'zh-CN') {
            $languages = array_reverse($languages);
        }

        foreach ($languages as $lang_key => $lang_displayname) {
            $posts = PostModel::getTop($post_count, $lang_key);
            $headers = [tr('Title'), tr('Author'), tr('Post Time')];
            $rows = [];
            $is_client = isLoadedFromClient(false);
            foreach ($posts as $post) {
                if ($is_client) {
                    $link = ClientModel::getHashedRedirectUrl($post->link);
                } else {
                    $link = ClientModel::generateRedirectUrl($post->link);
                }
                /** @var UserModel $user_item */
                $user_item = $post->user;// $all_users->where('name', $post->author)->first();
                $avatar_img = '';
                if ($user_item) {
                    $avatar_url = $user_item->getAvatarUrl(64);
                    $avatar_img = sprintf('<img src="%s" style="width: 24px;height: 24px;border-radius: 50%%" />',
                        $avatar_url);
                }
                $title = "<a href='{$link}' target='_blank' class='external-links'>{$post->title}</a>";
                $time = Carbon::parse($post->post_time)->diffForHumans();
                $author = sprintf('%s %s', $avatar_img, $user_item ? $user_item->name : '');
                $rows[] = [$title, $author, $time];
            }
            $more_link = ClientModel::getHashedRedirectUrl(PostModel::getAllPostUrl($lang_key));
            $rows[] = [
                '<a href="' . $more_link . '"  target="_blank" class="external-links"><button type="button" class="btn btn-primary">' . tr("More") . '...</button></a>',
                '',
                ''
            ];
            $table = new Table($headers, $rows);
            $tab->add($lang_displayname, $table);
        }

        $box = new Box(tr(tr('Latest Posts')), $tab);
        $body .= $box;
    }

    private function getEmotionHtml()
    {
        $emotions = [];
        $avaliable_emotions = [1, 2, 3, 5, 24, 25, 33, 70, 71, 73, 76, 77, 78, 96, 104, 106, 108];
        foreach ($avaliable_emotions as $emotion_id) {
            $emotion_id = sprintf("%03d", $emotion_id);
            $emotions[] = "<img src='/image/emotion/emotion_$emotion_id.png' width='24' height='24'/>";
        }
        $emotion_images = implode("", $emotions);
        $emotion_html = <<<eot
<div style="background-color: #f9fafc;" class="emotions">{$emotion_images}</div>
eot;
        return $emotion_html;
    }

    private function getUserDiv($user, $type, $hidden_send_message = 0)
    {
        $emotion_html = $this->getEmotionHtml();
        $comment_save_url = route('workingage.comments.save');
        $comment_list_url = route('workingage.comments.user', $user['id']) . '?type=' . $type;
        $button_style = '';
        $botton_content = '送上祝福';
        $img_title = '';
        $user['name'] = $this->getUserNameTitle($user, $type);

        if ($type == 4) {
            $years = Carbon::now()->year;
            $type_name = 'birthday';
            $icon_url = '../image/birthday-icon.png';
            $model_title = <<<eot
 <h4 class="modal-title" id="myModalLabel">祝福 <b>{$user['name']}</b>生日快乐</h4>
eot;
            $icon_div = "<img src='{$icon_url}' alt=''></div>
                    <div class='{$type_name}-user-name'><span >{$user['name']}</span>";
            $img_title = $this->getImgTitle($user, $type);
        } elseif ($type == 5) {
            $years = $user['years'];
            $type_name = 'entryage';
            $icon_url = "../image/{$years}.png";
            $model_title = <<<eot
 <h4 class="modal-title" id="myModalLabel">祝福 <b>{$user['name']}</b> 入司满 <b>{$years}</b> 周年</h4>
eot;
            $icon_div = "<img src='{$icon_url}' alt=''></div>
                    <div class='{$type_name}-user-name'><span >{$user['name']}</span>";

        } elseif ($type == 6) {
            $user['workplace'] = UserModel::getUser($user['id'])->workplace_info->name;
            $years = 0;
            $type_name = 'new';
            $model_title = <<<eot
<h4 class="modal-title" id="myModalLabel">祝福 <b>{$user['name']}</b> 加入网旭</h4>
eot;
            $desc = $this->getDescText($user, 6);
            $icon_div = "<div class='{$type_name}-user-name'>{$user['name']}</div><div class='new-user-desc'><span>{$desc}</span></div>";
            $button_style = 'style="background-color:#ffa200"';
            $botton_content = '打个招呼';
            $img_title = $this->getImgTitle($user, $type);
        } elseif ($type == 7) {
            $years = 0;
            $type_name = 'star';
            $icon_url = '../image/star-icon.png';
            $model_title = <<<eot
<h4 class="modal-title" id="myModalLabel">祝贺 <b>{$user['name']}</b> 成为本月客服之星</h4>
eot;
            $icon_div = "<img class='{$type_name}-user-icon-img' src='{$icon_url}' alt=''></div>
                    <div class='{$type_name}-user-name'><span >{$user['name']}</span>";
            $button_style = 'style="background-color:#ffa200"';
            $botton_content = '发个祝贺';
        }

        $send_message_button = '';
        if (!$hidden_send_message) {
            $send_message_button = "<button type='button' class='btn btn-danger btn-message-oper send-message' data-toggle='modal'  data-timer='{$type_name}-user' data-target='#myModal-{$user['id']}-{$type}' {$button_style}>
{$botton_content}</button>";
        }
        $user_module = <<<eot
            <div class="user_module {$type_name}-users">
                <div class="{$type_name}-user-image-div">
                    <img  class="{$type_name}-user-image" {$img_title} src="{$user['avatar']}" style="width: 119px;height: 119px">
                    <div class="{$type_name}-user-icon">{$icon_div}</div>
                </div>
                <p style="">
                    {$send_message_button}
                    <a href="{$comment_list_url}" class="btn btn-info btn-message-oper view-message">查看祝福</a>
                </p>
            </div>

eot;

        $user_module .= $this->getModal($user, $type, $model_title, $emotion_html, $years, $comment_save_url);
        return $user_module;
    }

    private function getImgTitle($user, $type)
    {
        if ($type == 4) {
            $user_birthday = UserModel::getUserBirthday($user['id']);
            //比如生日是阳历的，就显示 8月17日；是阴历的，就显示 “农历7月初7 ”，“农历7月17 ”这样的
            $birth_carbon = Carbon::parse($user_birthday['birthday']);
            if ($user_birthday['is_lunar']) { //农历为1，阳历为0
                $user_birthday_info = '农历' . Lunar::getCapitalNum($birth_carbon->month,
                        true) . Lunar::getCapitalNum($birth_carbon->day, false);
            } else {
                $user_birthday_info = $birth_carbon->month . '月' . $birth_carbon->day . '日';
            }
            $user_birthday_info = '生日：' . $user_birthday_info;
            return "title = '{$user_birthday_info}'";
        } elseif ($type == 6) {
            $deps = Department::getUserDepartmentTree($user['id']);
            $dep_names = '';
            if ($deps) {
                $main_deps = $deps[0];
                if (count($main_deps) > 1) {
                    $dep_names .= $main_deps[1]->name;
                } elseif (count($main_deps) > 0) {
                    $dep_names .= $main_deps[0]->name;
                }
            }
            if ($dep_names) {
                return "title = '{$user['name']} {$user['workplace']}: {$dep_names}'";
            } else {
                return "title = '{$user['name']}({$user['workplace']})'";
            }
        }
        return '';
    }

    private function getUserNameTitle($user)
    {
        //国外加tips
        $user_info = UserModel::getUser($user['id']);
        $workplace = $user_info->workplace_info->name;
        $is_foreign = true;
        switch ($workplace) {
            case  '马达加斯加' :
                $name = 'MG';
                $country = 'Madagascar';
                break;
            case  '英国' :
                $name = 'GB';
                $country = 'United Kiongdom';
                break;
            case  '菲律宾' :
                $name = 'PH';
                $country = 'Philippines';
                break;
            default :
                $is_foreign = false;
        }
        $current_user = UserModel::getUser();
        $is_local_country = $current_user->workplace_info->name === $workplace;
        if ($is_foreign && !$is_local_country) {
            $user['name'] .= "<span title='{$country}'>({$name})</span>";
        }
        return $user['name'];
    }

    private function getDescText($user, $type)
    {
        if ($type == 6) {
            $desc = $user['workplace'] . ' ';
            $dep = Department::getUserTopDepartment($user['id']);
            if ($dep) {
                $desc .= ($dep->short_name ?? $dep->name);
            }
            return $desc;
        }
        return '';
    }

    private function getModal($user, $type, $model_title, $emotion_html, $years, $comment_save_url)
    {
        $modal_html = <<<eot
                    <!-- 模态框（Modal） -->
                    <div class="modal fade myModal" id="myModal-{$user['id']}-{$type}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                                    {$model_title}
                                </div>
                                <div class="modal-body">
                                    <div id="comment-user-{$user['id']}-{$type}" class="comment-editable-div" style="border: 1px solid lightgrey; margin-top: 5px;">
                                    {$emotion_html}
                                        <div class="contenteditable-div" contenteditable="true"></div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">关闭</button>
                                    <button type="button" class="btn btn-primary btn-save-comment" data-user-id="{$user['id']}" data-user-year="{$years}" data-user-type="{$type}" data-save-url="{$comment_save_url}" data-user-year="{$years}">送出</button>
                                </div>
                            </div><!-- /.modal-content -->
                        </div><!-- /.modal -->
                    </div>
                    <style tyle="text/css">
                        [contenteditable]:focus {
                            outline: 0px solid transparent;
                        }

                        [contenteditable] {
                            width: 100%;
                            height: 100px;
                            margin-top: 3px;
                            padding: 8px;
                            font-weight: bold;
                            color: blue;
                            max-height: 100px;
                            overflow: auto;
                        }
                    </style>
eot;
        return $modal_html;
    }

    private function getJs()
    {
        $script = <<<eot
<script>
    $('[contenteditable]').on('paste', function (e) {
        e.preventDefault();
        var text = '';
        if (e.clipboardData || e.originalEvent.clipboardData) {
            text = (e.originalEvent || e).clipboardData.getData('text/plain');
        } else if (window.clipboardData) {
            text = window.clipboardData.getData('Text');
        }
        if (document.queryCommandSupported('insertText')) {
            document.execCommand('insertText', false, text);
        } else {
            document.execCommand('paste', false, text);
        }
    });

    $('.myModal').on('show.bs.modal', function (e) {
        $(this).css('display', 'block');
        var modalHeight = $(window).height() * (1 - 0.618) - $(this).find('.modal-dialog').height() / 2;
        $(this).find('.modal-dialog').css({
            'margin-top': modalHeight
        });
        $(this).find('.contenteditable-div').focus();
    });

    function pasteHtmlAtFocus(html) {
        var sel,
        range;
        if (window.getSelection) {
            // IE9 and non-IE
            sel = window.getSelection();
            if (sel.getRangeAt && sel.rangeCount) {
                range = sel.getRangeAt(0);
                range.deleteContents();

                // Range.createContextualFragment() would be useful here but is
                // non-standard and not supported in all browsers (IE9, for one)
                var el = document.createElement("div");
                el.innerHTML = html;
                var frag = document.createDocumentFragment(),
                node,
                lastNode;
                while ((node = el.firstChild)) {
                    lastNode = frag.appendChild(node);
                }
                range.insertNode(frag);

                // Preserve the selection
                if (lastNode) {
                    range = range.cloneRange();
                    range.setStartAfter(lastNode);
                    range.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            }
        } else if (document.selection && document.selection.type != "Control") {
            // IE < 9
            document.selection.createRange().pasteHTML(html);
        }
    }

    function setUserIntval(attr, user_class, i) {
        var paused1 = false;
        var paused2 = false;
        var paused3 = false;
        var index = 0;
        //定时器
        var entryage_users_timer = setInterval(function () {
                if (!paused1 && !paused2 && !paused3) {
                    index = (index == i - 1) ? 0 : index + 1;
                    //某个div显示，其他的隐藏11
                    $(user_class).hide().eq(index).show();
                }
            }, 120000);

        $('.myModal').on('hidden.bs.modal', function () {
            paused1 = false;
        });
        $('.myModal').on('hide.bs.modal', function () {
            paused3 = false;
        });
        $('.myModal').on('shown.bs.modal', function () {
            paused1 = true;
        });
        $('.myModal').on('show.bs.modal', function () {
            paused3 = true;
        });
        $('.user_module').on({
            mouseover: function () {
                paused2 = true;
            },
            mouseout: function () {
                paused2 = false;
            }
        });
    }

    $(function () {
        $('.btn-save-comment').click(function (e) {
            var user_id = $(this).data('user-id');
            var url = $(this).data('save-url');
            var user_year = $(this).data('user-year');
            var type = $(this).data('user-type');
            var comment = $("#comment-user-" + user_id + "-" + type + " .contenteditable-div").html().trim();
            if (!comment) {
                swal('哎呀，你好像什么都没有说呢！', '', 'error');
                return false;
            }
            $('#myModal-' + user_id).modal('hide')
            var post_data = {
                user_id: user_id,
                type: type,
                user_year: user_year,
                comment: comment,
                _token: LA.token
            };
            console.log(post_data);

            $.post(url, post_data, function (response) {
                if (typeof response === 'object') {
                    if (response.status) {
                        $("#comment-user-" + user_id + "-" + type + " .contenteditable-div").html('');
                        $('.comment-editable-div').hide();
                        swal(response.message, '', 'success');
                        window.location.reload();
                    } else {
                        swal(response.message, '', 'error');
                    }
                }
            })
        });

        $('.emotions img').click(function (e) {
            $(".contenteditable-div:visible").focus();
            var html = "<img width='24' height='24' src='" + $(this).attr('src') + "'/>";
            pasteHtmlAtFocus(html);
        });

        $("[data-dismiss='modal']").click(function (timer) {
            $(this).parent('[class=""]')

        })
    });
</script>

eot;

        return $script;
    }

}
