<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DingTalk\Crypto\DingtalkCrypt;
use App\Models\DingTalk\DingtalkApplication;
use App\Models\DingTalk\Providers\Callback\Client;
use App\Models\UserModel;
use Encore\Admin\Controllers\HasResourceActions;
use Illuminate\Http\Request;

class DingtalkController extends Controller
{
    use HasResourceActions;

    public function syncDingtalkData(Request $request)
    {

        $o['state'] = 0;
        $o['message'] = "";
        if (Department::reBuild()) {
            if (UserModel::reBuild()) {
                $o['status'] = 1;
                $o['message'] = "同步成功";
            } else {
                $o['message'] = "同步用户信息失败";
            }
        } else {
            $o['message'] = "同步部门信息失败";
        }
        return $o;
    }


    public function registerDingtalkCallback(Request $request)
    {
        return;
        $oa_conf = config('dingtalk.oa');
        $callback_auth_conf = config('dingtalk.callback_auth');

        $response = <<<eot
{"encrypt":"Oyd3VxsyKPOz0K5zCVnOKHFjP90D4EbXl6kvuHhATtwtRhNmM1D+nWrwQh87eH5UW8s1Q\/zJJRx\/0Th1chUzElhgMyCCd0+AaRyW+W8nZ8U2b726CmUrjp53Q72pvXxU","signature":"09f9dc203a514d9ca58d4916c45f99583a626860","timestamp":"1539858637937","nonce":"QoKIkqhU"}
eot;

        $response = <<<eot
{"encrypt":"yvAAMo68pBTGPtbp9ckcdrI9tMhRSUsFJFd8VHAK8Trs5thqwWMTZekNVzbBStSIBW6HQVDfHGcYxYIJEOvetByPby\/ije+QO8Wa3mzixM+rZVfs1L\/QV3ACnvY9VdLUdlxsehJwFHPAsYb4lsXn4WRkglHV4Sk+uRC\/En2+AQjU8R29kKb6bez2GtYHzLzj82MdbYUwzDmADLaj41gAPokrbAk5VgpF\/LExq3MaDYs+sczcBKtJ735BvhRc6uMqXbxjXHT6sAiIM6zzLEf\/+QXmeTNp1FYWte+7HMOuNVTStYJznHCQjOOY7O7217jEgUDskNl1PnU7ase\/i5CN6VhSvfYERfGSSbw3ORg+NUWjfvxdJdOKsjJfzeQkIRp\/Ha7z5TrFLL6jyWzSorXdGx6F9vczGMtR7JRsCgeEHXv+j30nxPR0YpDQ2QeRjZZ3ti\/O1\/QnsGvi8Dv8ky4gGto13R2x99Ba00DCIQzkQL8RYAQB0H5C2oBoxc7qWbaOJgnzYLW6zwm8UQgaNpxD4jn25MsaxysBuzz0Pf5nS287X\/tbV\/7Hu\/qj7ux4KK8HY+keWWR2J8qwY7w8ZD7o9g==","signature":"8ed04e606b2f01da4116998605d8ead1771683b6","timestamp":"1539868974396","nonce":"4ILvuLqp"}
eot;

        $post = json_decode($response, true);
        $crypt = new  DingtalkCrypt($callback_auth_conf['token'], $callback_auth_conf['aes_key'], $oa_conf['corp_id']);
        $crypt->DecryptMsg($post['signature'], $post['timestamp'], $post['nonce'], $post['encrypt'], $decryptMsg);
        exit($decryptMsg);

        //"{"msg_signature":"bf390c59c14d45f22e0a672a1d4a40af08678fd0","encrypt":"PbCdKDtIDH03bSbyJ87d15z9VDcks+rPixaNh86oUGVR98YzRMFAP8FdAGfcdAYeGeMyiN1x2Kv2NTgMZXQdIj2su\/DKak1iDAgJ39FvvzJWrR7W62ji0W21QPqk6RMU"


        /*
{
signature": "dfd4eb382ec5372ee5460df3f3b1660596a25d8c",
"encrypt": "2yeR338ePO9najlT4df2dZ5+KOMH1u7ww8YxTB1MV258dKyMnid2KT26+dHSsBMyaFCJHLEaevNZ+Z6lHVdHrUZ/LlLJZFt9mT77RNz/xNk6Iq9/YUFCy7mAWKjVxJvz",
"timeStamp": "1539858637937",
"nonce": "QoKIkqhU"
}
         */
        $crypt->EncryptMsg("success", $post['timestamp'], $post['nonce'], $encryptMsg);
//        dd($encryptMsg);

        $callback = new Client(new DingtalkApplication($oa_conf));
//        $callback->updateCallback($callback_auth_conf, "https://o.wangxutech.com/api/callback/dingtalk/callback");
        $callback->getCallback($callback_auth_conf);

    }
}
