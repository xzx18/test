<?php


namespace App\Admin\Controllers;

use App\Models\FinancialPrizeRuleModel;
use App\Models\UserModel;
use App\Models\FinancialSalaryInsuranceModel;
use Carbon\Carbon;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Form;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;

use Excel;

class FinancialSalaryInsuranceImportController
{
    private static $valid_places = [1 => '深圳', 2 => '武汉', 3 => '南昌', 4 => '西安'];
    private static $place_insurace = ['深圳' => 250, '武汉' => 152, '南昌' => 167, '西安' => 90];

    public function index(Content $content)
    {
        $form = new Form();
        $form->action(route('financial.salary-insurance.upload'));
        $form->select('type', '选择类型')->options([1 => '社保', 2 => '住房公积金'])->default(1);
        $form->select('place', '地区')->options(self::$valid_places)->default($this->getDefaultPlace());
        $form->datetime('month', '月份')->format('YYYY-MM')->default(Carbon::now()->firstOfMonth());
        $form->textarea('data_text', '数据')->rows(40)->help('直接把Excel表格式的数据部分（要包含标题）复制到这里提交');

        $box = new Box('导入社保公积金', $form->render());
        $html = $box->render();

        return $content
            ->header('导入')
            ->description('从Excel表格导入社保公积金')
            ->body($html);
    }

    public function upload(Request $request)
    {
        $current_user = UserModel::getUser();
        $is_administrator = $current_user->isRole('administrator');
        $is_financial = $current_user->isRole('financial.staff');
        $is_administration = $current_user->isRole('administration.staff');
        if (!$is_administrator && !$is_financial && !$is_administration) {
            return $this->returnWithMessage('没有权限', '请联系管理员');
        }
        $type = $request->type;
        if (!$type) {
            return $this->returnWithMessage('数据不正确', '请选择类型：社保或公积金');
        }
        $place = $request->place;
        if (!$place) {
            return $this->returnWithMessage('数据不正确', '请选择地区');
        }
        $month = $request->month;
        if (!$month) {
            return $this->returnWithMessage('数据不正确', '请选择月份');
        }
        $data_text = $request->data_text;
        if (!$data_text) {
            return $this->returnWithMessage('数据不正确', '请输入表格数据');
        }

        $data_grid = [];
        $lines = explode("\r\n", $data_text);
        foreach ($lines as $line) {
            $data_grid[] = explode("\t", $line);
        }

        $data = $this->filterImportedData($data_grid, $type, $place);
        if (count($data) > 0) {
            foreach ($data as &$d) {
                $d['month'] = $month . '-01';
                $d['place'] = $place;
            }

            //同一个人扣两个月的社保或公积金处理
            $new_data = [];
            $insurance_cate = [
                'endowment_personal',
                'endowment_organization',
                'medical_personal',
                'medical_organization',
                'large_medical_personal',
                'large_medical_organization',
                'employment_injury_personal',
                'employment_injury_organization',
                'unemployment_personal',
                'unemployment_organization',
                'maternity_personal',
                'maternity_organization',
                'housing_provident_fund_personal',
                'housing_provident_fund_organization',
                'total'
            ];
            foreach ($data as $v) {
                foreach ($insurance_cate as $cate) {
                    if (isset($v[$cate])) {
                        if (isset($new_data[$v['user_id']][$cate])) {
                            $new_data[$v['user_id']][$cate] += $v[$cate];
                        } else {
                            $new_data[$v['user_id']][$cate] = $v[$cate];
                        }
                    }
                }
                $new_data[$v['user_id']]['user_id'] = $v['user_id'];
                $new_data[$v['user_id']]['month'] = $v['month'];
                $new_data[$v['user_id']]['place'] = $v['place'];
            }

            $ids = FinancialSalaryInsuranceModel::saveTemporarily($new_data, $type);
            if (empty($ids)) {
                return $this->returnWithMessage('表格选择有误或导入数据不正确！请检查后再重新导入', '如果有其他问题，请联系管理员');
            }
            $data_ids = implode(',', $ids);
        } else {
            return $this->returnWithMessage('表格选择有误或导入数据不正确！请检查后再重新导入', '如果有其他问题，请联系管理员');
        }
        $html = $this->getShebaoDataHtml($data, $data_ids, $type, $place, $month);
        return $html;
    }

    public function store(Request $request)
    {
        $ids = $request->post('resid');
        $type = $request->post('restype');
        if ($ids && $type) {
            FinancialSalaryInsuranceModel::confirmTemporary(explode(',', $ids), $type);
        }
        return ['status' => 1, 'message' => "保存成功"];
    }

    private function getDefaultPlace()
    {
        $place = array_search(UserModel::getUser()->workplace, self::$valid_places);
        return !$place ? 0 : $place;
    }

    private function returnWithMessage($title, $message)
    {
        $error = new MessageBag(['title' => $title, 'message' => $message]);
        return back()->with(compact('error'));
    }

    private function filterImportedData($data_grid, $type, $place)
    {
        if ($type == 1) { //1社保；2公积金
            if ($place == 1) { //1深圳；2武汉；3南昌；4，西安
                return $this->filterShenzhenShebao($data_grid);
            } elseif ($place == 2) {
                return $this->filterWuhanShebao($data_grid);
            } elseif ($place == 3) {
                return $this->filterNanchangShebao($data_grid);
            } elseif ($place == 4) {
                return $this->filterXianShebao($data_grid);
            }
        } elseif ($type == 2) {
            if ($place == 1) {
                return $this->filterShenzhenGongjijin($data_grid);
            } elseif ($place == 2) {
                return $this->filterWuhanGongjijin($data_grid);
            } elseif ($place == 3) {
                return $this->filterNanchangGongjijin($data_grid);
            } elseif ($place == 4) {
                return $this->filterXianGongjijin($data_grid);
            }
        }
    }

    private function filterWuhanShebao($data_grid)
    {
        /* 最好还能智能识别标题列以及各数据列，现在是按这个格式写死的
         * 姓名	应收金额			养老		失业		工伤		生育		基本医疗		大额医疗
                应收合计	单位合计	个人合计	单位应缴	个人应缴	单位应缴	个人应缴	单位应缴	个人应缴	单位应缴	个人应缴	单位应缴	个人应缴	单位应缴	个人应缴
            邓露露	567.97	175.77	392.2	0	299.18	0	11.22	0	0	26.18	0	149.59	74.8	0	7
         */
        $data = [];
        foreach ($data_grid as $row) {
            if (count($row) == 0 || $row[0] == '' || $row[0] == '姓名' || $row[0] == '合计') {
                continue;
            }
            $user = UserModel::where('workplace', '武汉')->where('truename', $row[0])->first()
                ?? UserModel::where('truename', $row[0])->withTrashed()->first();
            if (!$user) {
                continue;
            }
            for ($i = 0; $i <= 15; $i++) {
                if (!isset($row[$i])) {
                    $row[$i] = 0;
                }
            }
            $item = [
                'user_id' => $user->id,
                'user_name' => $user->truename,
                'endowment_personal' => floatval($row[5]),
                'endowment_organization' => floatval($row[4]),
                'medical_personal' => floatval($row[13]),
                'medical_organization' => floatval($row[12]),
                'large_medical_personal' => floatval($row[15]),//大额医疗保险个人
                'large_medical_organization' => floatval($row[14]),//大额医疗保险单位
                'employment_injury_personal' => floatval($row[9]),
                'employment_injury_organization' => floatval($row[8]),
                'unemployment_personal' => floatval($row[7]),
                'unemployment_organization' => floatval($row[6]),
                'maternity_personal' => floatval($row[11]),
                'maternity_organization' => floatval($row[10]),
                'total_personal' => floatval($row[5]) + floatval($row[7]) + floatval($row[9]) + floatval($row[11]) + floatval($row[13]) + floatval($row[15]),
                'total_organization' => floatval($row[4]) + floatval($row[6]) + floatval($row[8]) + floatval($row[10]) + floatval($row[12]) + floatval($row[14])
            ];
            // 重算total是为了方便校验数据，不用入库
            $item['total'] = $item['total_personal'] + $item['total_organization'];
            $data[] = $item;
        }
        return $data;
    }

    private function filterShenzhenShebao($data_grid)
    {
//        应收金额			养老保险			医疗保险			工伤保险		失业保险			生育医疗
//序号	电脑号	姓名	身份证号	应收合计	个人合计	单位合计	缴费基数	个人交	单位交	缴费基数	个人交	单位交	缴费基数	单位交	缴费基数	个人交	单位交	缴费基数	单位交
//1	650048944	牛广瑀	220203199305083014	471.75	294.30	177.45	2200.00	176.00	0.00	5585.00	111.70	167.55	2200.00	0.00	2200.00	6.60	0.00	2200.00	9.90

        $data = [];

        foreach ($data_grid as $row) {
            if (is_numeric($row[0])) {
                $user = UserModel::where('workplace', '深圳')->where('truename', $row[2])->first()
                    ?? UserModel::where('truename', $row[2])->withTrashed()->first();
                if (!$user) {
                    continue;
                }
                for ($i = 0; $i <= 19; $i++) {
                    if (!isset($row[$i])) {
                        $row[$i] = 0;
                    }
                }
                $item = [
                    'user_id' => $user->id,
                    'user_name' => $user->truename,
                    'endowment_personal' => floatval($row[8]), //养老保险个人
                    'endowment_organization' => floatval($row[9]),//养老保险单位
                    'medical_personal' => floatval($row[11]),//医疗保险个人
                    'medical_organization' => floatval($row[12]),//医疗保险单位
                    'employment_injury_personal' => floatval(0),//工伤保险个人
                    'employment_injury_organization' => floatval($row[14]),//工伤保险单位
                    'unemployment_personal' => floatval($row[16]),//失业保险个人
                    'unemployment_organization' => floatval($row[17]),//失业保险单位
                    'maternity_personal' => floatval(0),//生育保险个人
                    'maternity_organization' => floatval($row[19]),//生育保险单位
                    'total_personal' => floatval($row[8]) + floatval($row[11]) + floatval($row[16]),
                    'total_organization' => floatval($row[9]) + floatval($row[12]) + floatval($row[14]) + floatval($row[17]) + floatval($row[19])
                ];
                // 重算total是为了方便校验数据，不用入库
                $item['total'] = $item['total_personal'] + $item['total_organization'];
                $data[] = $item;
            }
        }
        return $data;
    }

    private function filterNanchangShebao($data_grid)
    {

        $item = [];
        foreach ($data_grid as $key => $row) {
            if (count($row) == 0 || $row[0] == '' || $row[0] == '个人编号') {
                continue;
            }
            $user = UserModel::where('workplace', '南昌')->where('truename', $row[1])->first()
                ?? UserModel::where('truename', $row[1])->withTrashed()->first();
            if (!$user) {
                continue;
            }
            for ($i = 0; $i <= 11; $i++) {
                if (!isset($row[$i])) {
                    $row[$i] = 0;
                }
            }
            if ($key > 1 && $row[1] == $data_grid[$key - 1][1]) { //同一个人
                $arr = [
                    'unemployment_personal',
                    'unemployment_organization',
                    'medical_personal',
                    'medical_organization',
                    'maternity_personal',
                    'maternity_organization',
                    'maternity_organization',
                    'endowment_personal',
                    'endowment_organization',
                ];
                foreach ($arr as $str) {
                    if (!isset($item[$user->id][$str])) {
                        $item[$user->id][$str] = 0;
                    }
                }
                switch ($row[4]) {
                    case '失业保险' :
                        $item[$user->id]['unemployment_personal'] += floatval($row[11]);
                        $item[$user->id]['unemployment_organization'] += floatval($row[10]);
                        break;
                    case '基本医疗保险' :
                        $item[$user->id]['medical_personal'] += floatval($row[11]);
                        $item[$user->id]['medical_organization'] += floatval($row[10]);
                        break;
                    case '生育保险' :
                        $item[$user->id]['maternity_personal'] += floatval($row[11]);
                        $item[$user->id]['maternity_organization'] += floatval($row[10]);
                        break;
                    case '企业基本养老保险' :
                        $item[$user->id]['endowment_personal'] += floatval($row[11]);
                        $item[$user->id]['endowment_organization'] += floatval($row[10]);
                        break;

                }
            } else { //同一个人的第一行：企业基本养老保险
                $item[$user->id] = [
                    'user_id' => $user->id,
                    'user_name' => $user->truename,
                    'endowment_personal' => floatval($row[11]), //养老保险个人
                    'endowment_organization' => floatval($row[10]),//养老保险单位
                ];
            }
        }
        return $item;
    }

    private function filterXianShebao($data_grid)
    {
        $data = [];
        foreach ($data_grid as $row) {
            if (count($row) == 0 || $row[0] == '' || $row[0] == '姓名' || $row[0] == '合计' || $row[0] == '4月四险') {
                continue;
            }
            $user = UserModel::where('workplace', '西安')->where('truename', $row[0])->first()
                ?? UserModel::where('truename', $row[0])->withTrashed()->first();
            if (!$user) {
                continue;
            }
            for ($i = 0; $i <= 15; $i++) {
                if (!isset($row[$i])) {
                    $row[$i] = 0;
                }
            }
            $item = [
                'user_id' => $user->id,
                'user_name' => $user->truename,
                'endowment_personal' => floatval($row[5]),
                'endowment_organization' => floatval($row[4]),
                'medical_personal' => floatval($row[7]),
                'medical_organization' => floatval($row[6]),
                'large_medical_personal' => floatval($row[9]),
                'large_medical_organization' => floatval($row[8]),
                'employment_injury_personal' => floatval($row[13]),
                'employment_injury_organization' => floatval($row[12]),
                'unemployment_personal' => floatval($row[11]),
                'unemployment_organization' => floatval($row[10]),
                'maternity_personal' => floatval($row[15]),
                'maternity_organization' => floatval($row[14]),
                'total_personal' => floatval($row[5]) + floatval($row[7]) + floatval($row[9]) + floatval($row[11]) + floatval($row[13]) + floatval($row[15]),
                'total_organization' => floatval($row[4]) + floatval($row[6]) + floatval($row[8]) + floatval($row[10]) + floatval($row[12]) + floatval($row[14])
            ];
            // 重算total是为了方便校验数据，不用入库
            $item['total'] = $item['total_personal'] + $item['total_organization'];
            $data[] = $item;
        }
        return $data;
    }


    private function filterWuhanGongjijin($data_grid)
    {
        $data = [];
        foreach ($data_grid as $row) {
            if (count($row) == 0 || $row[0] == '' || $row[0] == '姓名') {
                continue;
            }
            $user = UserModel::where('workplace', '武汉')->where('truename', $row[0])->first()
                ?? UserModel::where('truename', $row[0])->withTrashed()->first();
            if (!$user) {
                continue;
            }
            $item = [
                'user_id' => $user->id,
                'user_name' => $user->truename,
                'housing_provident_fund_personal' => floatval($row[2]),
                'housing_provident_fund_organization' => floatval($row[1]),
                'total' => floatval($row[1]) + floatval($row[1]),
            ];
            // 重算total是为了方便校验数据，不用入库
            $data[] = $item;
        }
        return $data;
    }

    private function filterShenzhenGongjijin($data_grid)
    {
        $data = [];
        foreach ($data_grid as $row) {
            if (count($row) == 0 || $row[0] == '' || $row[0] == '序号') {
                continue;
            }
            $user = UserModel::where('workplace', '深圳')->where('truename', $row[2])->first()
                ?? UserModel::where('truename', $row[2])->withTrashed()->first();
            if (!$user) {
                continue;
            }
            $item = [
                'user_id' => $user->id,
                'user_name' => $user->truename,
                'housing_provident_fund_personal' => floatval($row[4]) * floatval($row[6]),
                'housing_provident_fund_organization' => floatval($row[4]) * floatval($row[5]),
                'total' => floatval($row[4]) * floatval($row[6]) + floatval($row[4]) * floatval($row[5]),
            ];
            // 重算total是为了方便校验数据，不用入库
            $data[] = $item;
        }
        return $data;
    }

    private function filterNanchangGongjijin($data_grid)
    {
        $data = [];
        foreach ($data_grid as $row) {
            if (count($row) == 0 || $row[0] == '' || $row[0] == '序号') {
                continue;
            }
            $user = UserModel::where('workplace', '南昌')->where('truename', $row[2])->first()
                ?? UserModel::where('truename', $row[2])->withTrashed()->first();
            if (!$user) {
                continue;
            }
            $item = [
                'user_id' => $user->id,
                'user_name' => $user->truename,
                'housing_provident_fund_personal' => floatval($row[6]),
                'housing_provident_fund_organization' => floatval($row[7]),
                'total' => floatval($row[6]) + floatval($row[7]),
            ];
            // 重算total是为了方便校验数据，不用入库
            $data[] = $item;
        }
        return $data;
    }

    private function filterXianGongjijin($data_grid)
    {
        $data = [];
        foreach ($data_grid as $row) {
            if (count($row) == 0 || $row[0] == '' || $row[0] == '序号 ') {
                continue;
            }
            $user = UserModel::where('workplace', '西安')->where('truename', $row[5])->first()
                ?? UserModel::where('truename', $row[5])->withTrashed()->first();
            if (!$user) {
                continue;
            }
            $item = [
                'user_id' => $user->id,
                'user_name' => $user->truename,
                'housing_provident_fund_personal' => floatval($row[10]),
                'housing_provident_fund_organization' => floatval($row[9]),
                'total' => floatval($row[10]) + floatval($row[9]),
            ];
            // 重算total是为了方便校验数据，不用入库
            $data[] = $item;
        }
        return $data;
    }


    private static function getShebaoDataHtml(array &$data, $data_id, $data_type, $place, $month = '')
    {
        $route_finish = route('financial.salary-insurance.index');
        $route_save = route('financial.salary-insurance.store');
        $route_cancel = route('financial.salary-insurance.import');

        $valid_items_count = count($data);
        $html = <<<EOT
<h4>有效的导入({$valid_items_count}条)</h4>
EOT;
        if ($valid_items_count > 0) {
            if ($data_type == 1) {
                if ($place != 3) {
                    $html .= self::getShebaoHtmlTable($data);
                } else { //南昌
                    $html .= self::getNcShebaoHtmlTable($data);
                }
            } elseif ($data_type == 2) { //公积金
                $html .= self::getGongjijinHtmlTable($data, $place, $month);
            }
        }

        $html .= <<<EOT
<h4>是否提交保存？</h4>
<button type="button" class="btn btn-sm btn-info" style="margin-right:32px" id="btn-cancel" data-route="{$route_cancel}" data-resid="{$data_id}">返回</button>
EOT;

        if ($valid_items_count > 0) {
            $html .= <<<EOT
<button type="button" class="btn btn-sm btn-warning" id="btn-save" data-route="{$route_save}" data-route2="{$route_finish}" data-resid="{$data_id}" data-restype="{$data_type}">保存</button>
EOT;
        }

        $html .= <<<EOT
<script>
    $(function () {
        $('#btn-cancel').click(function(e) {
            var url = $(this).data('route');
            window.open(url, '_self');
        });
        $('#btn-save').click(function(e) {
            var url = $(this).data('route');
            var url2 = $(this).data('route2');
            var post_data = {
                'resid' : $(this).data('resid'),
                'restype' : $(this).data('restype'),
                _token: LA.token
                };
            $.post(url, post_data, function(response) {
                if (typeof response === 'object') {
                    if (response.status) {
                        swal(response.message, '', 'success');
                        window.open(url2, '_self');
                    } else {
                        swal(response.message, '', 'error');
                    }
                }
            });
        });
    })
</script>
EOT;

        return $html;
    }

    private static function getShebaoHtmlTable(array &$data)
    {
        $html = "<table border='1' class='finacial'>";
        $html .= "<tr><td>序号</td><td>姓名</td>"
            . "<td>应收合计</td><td>单位合计</td><td>个人合计</td>"
            . "<td>养老单位</td><td>养老个人</td>"
            . "<td>失业单位</td><td>失业个人</td>"
            . "<td>工伤单位</td><td>工伤个人</td>"
            . "<td>生育单位</td><td>生育个人</td>"
            . "<td>医疗单位</td><td>医疗个人</td>"
            . "<td>大额医疗单位</td><td>大额医疗个人</td></tr>";
        $i = 0;
        foreach ($data as $item) {
            $large_medical_organization = $item['large_medical_organization'] ?? 0;
            $large_medical_personal = $item['large_medical_personal'] ?? 0;
            $i++;
            $html .= "<tr><td>{$i}</td><td>{$item['user_name']}</td>"
                . "<td>{$item['total']}</td><td>{$item['total_organization']}</td><td>{$item['total_personal']}</td>"
                . "<td>{$item['endowment_organization']}</td><td>{$item['endowment_personal']}</td>"
                . "<td>{$item['unemployment_organization']}</td><td>{$item['unemployment_personal']}</td>"
                . "<td>{$item['employment_injury_organization']}</td><td>{$item['employment_injury_personal']}</td>"
                . "<td>{$item['maternity_organization']}</td><td>{$item['maternity_personal']}</td>"
                . "<td>{$item['medical_organization']}</td><td>{$item['medical_personal']}</td>"
                . "<td>{$large_medical_organization}</td><td>{$large_medical_personal}</td></tr>";
        }
        $html .= "</table>";
        return $html;
    }

    private static function getNcShebaoHtmlTable(array &$data)
    {
        $html = "<table border='1' class='finacial'>";
        $html .= "<td>序号</td><td>姓名</td>"
            . "<td>险种类型</td><td>单位缴费本金</td><td>个人缴费本金</td>"
            . "<td>总金额</td>";

        $i = 0;
        foreach ($data as $item) {
            $i++;
            $html .= "<tr><td>{$i}</td><td>{$item['user_name']}</td>"
                . "<td>企业基本养老保险</td><td>{$item['endowment_organization']}</td><td>{$item['endowment_personal']}</td>"
                . "<td>" . ($item['endowment_organization'] + $item['endowment_personal']) . "</td></tr>";

            $i++;
            $html .= "<tr><td>{$i}</td><td>{$item['user_name']}</td>"
                . "<td>失业保险</td><td>{$item['unemployment_organization']}</td><td>{$item['unemployment_personal']}</td>"
                . "<td>" . ($item['unemployment_organization'] + $item['unemployment_personal']) . "</td></tr>";

            $i++;
            $html .= "<tr><td>{$i}</td><td>{$item['user_name']}</td>"
                . "<td>基本医疗保险</td><td>{$item['medical_organization']}</td><td>{$item['medical_personal']}</td>"
                . "<td>" . ($item['medical_organization'] + $item['medical_personal']) . "</td></tr>";

            $i++;
            $html .= "<tr><td>{$i}</td><td>{$item['user_name']}</td>"
                . "<td>生育保险</td><td>{$item['maternity_organization']}</td><td>{$item['maternity_personal']}</td>"
                . "<td>" . ($item['maternity_organization'] + $item['maternity_personal']) . "</td></tr>";
        }
        $html .= "</table>";
        return $html;
    }

    private static function getGongjijinHtmlTable(array &$data, $place, $month = '')
    {

        $table_url = route('financial.insurance.rule.table');
        $html = "<table border='1' class='insurace'>";
        $html .= "<td>序号</td><td>姓名</td>"
            . "<td>公司缴存</td><td>个人缴存</td><td>缴存总额</td>";
        $i = 0;
        foreach ($data as $item) {
            $place_name = self::$valid_places[$place];
            if ($item['housing_provident_fund_organization'] > self::$place_insurace[$place_name] && !self::volidInsuraceRule($item['user_id'], $item['place'], $item['housing_provident_fund_organization'], $month)) {
                $style = "bgcolor = 'red'";
            } else {
                $style = '';
            }
            $i++;
            $html .= "<tr {$style}><td>{$i}</td><td>{$item['user_name']}</td>"
                . "<td>{$item['housing_provident_fund_organization']}</td><td>{$item['housing_provident_fund_personal']}</td>"
                . "<td>" . ($item['housing_provident_fund_organization'] + $item['housing_provident_fund_personal']) . "</td></tr>";
        }
        $html .= "</table>";
        $html .= <<<eot
<span class="help-block btn-warning" style="width:650px;font-size:17px">
    <i class="fa fa-info-circle"></i>&nbsp;标红的数据为员工缴纳公积金超过标准，并且在扣款规则中没有记录或者没有在规则有效期内！<a href ="{$table_url}">点击查看员工公积金扣款规则表格</a>
</span>
eot;
        return $html;
    }

    private static function volidInsuraceRule($user_id, $place_id, $total_fund, $month)
    {
        $place_insurace = self::$place_insurace;
        $insurace_rule = FinancialPrizeRuleModel::where('user_id', $user_id)->where('type', 5)->where('valid', 1)->value('content');
        $place_name = self::$valid_places[$place_id];
        if ($insurace_rule) {
            $insurace_data = json_decode($insurace_rule, true);
            $start_month = $insurace_data['plans'][0]['start'] ?? '';
            $end_month = $insurace_data['plans'][0]['end'] ?? '';
            $check_time = strtotime($month) >= strtotime($start_month) && strtotime($month) <= strtotime($end_month);
            $check_money = $total_fund === (float)$insurace_data['plans'][0]['money'] + $place_insurace[$place_name]; //$total_fund总是float类型
            if (isset($insurace_data['plans'][0]['money']) && $check_money && $check_time) {
                return true;
            }
        }
        return false;
    }

    public function table(Content $content, Request $request)
    {
        if ($request->method() == 'POST') { //保存数据
            $this->savePrizeRule($content, $request);
        }
        $headers = ['姓名', '标准扣除金额', '实际扣除金额', '差额', '起始时间', '结束时间'];
        $rules = FinancialPrizeRuleModel::where('type', 5)->where('valid', 1)->orderby('updated_at', 'desc')->get();
        foreach ($rules as $rule) {
            $user = UserModel::getUser($rule->user_id);
            $row['name'] = $user->name;
            $row['money'] = self::$place_insurace[$user->workplace_info->name] ?? 0;
            $insurace_data = json_decode($rule->content, true);
            if (isset($insurace_data['plans'][0]['money']) && $insurace_data['plans'][0]['start'] && $insurace_data['plans'][0]['end']) {
                $row['real_money'] = $insurace_data['plans'][0]['money'] + self::$place_insurace[$user->workplace_info->name];
                $row['dif_money'] = $insurace_data['plans'][0]['money'];
                $row['start'] = $insurace_data['plans'][0]['start'];
                $row['end'] = $insurace_data['plans'][0]['end'];
            } else { //规则不正确
                continue;
            }
            $rows[] = $row;
        }
        $table = new Table($headers, $rows);

        $form = new \Encore\Admin\Widgets\Form();
        $url = route('financial.insurance.rule.table');
        $form->action($url);
        $old_user_id = $request->old('user_id');
        $old_money= $request->old('money');
        $old_start_month = $request->old('start_month');
        $old_end_month = $request->old('end_month');
        $form->select('user_id', '员工')->options(UserModel::getAllUsersPluck())->default($old_user_id);
        $form->currency('money', '实际扣除金额')->rules('required')->default('0.0')->symbol('￥')->default($old_money);
        $form->datetime('start_month', '住房公积金扣款起始月份')->format("YYYY-MM")->attribute('style', 'width:300px')->default($old_start_month);
        $form->datetime('end_month', '住房公积金扣款结束月份')->format("YYYY-MM")->attribute('style', 'width:300px')->default($old_end_month);
        $form->disableReset();

        $import_url = route('financial.salary-insurance.import');
        $html = '<div class="btn-group">
                    <a href="' . $import_url . '"  style="float:right"><button type="reset" class="btn btn-warning  pull-right">返回到公积金导入页面</button></a>
                </div>';

        $html .= "<div style='background: white'>";
        $html .= $table->render();
        $html .= "</div>";

        $html .= "<h3 style=‘’ style='height:300px'>添加(或修改)公积金扣除规则</h3>";

        $html .= "<div style='background: white'>";
        $html .= $form->render();
        $html .= "</div>";
        return $content
            ->header("公积金超额扣除规则列表")
            ->description(' ')
            ->body($html);
    }

    private function savePrizeRule($content, $request)
    {
        $user_id = $request->input('user_id');
        $money = $request->input('money');
        $start_month = $request->input('start_month');
        $end_month = $request->input('end_month');
        $user = UserModel::getUser($user_id);
        $request->flash('user_id');
        $request->flash('money');
        $request->flash('start_month');
        $request->flash('end_month');//保留原始值
        $place_name = $user->workplace_info->name;
        if (isset(FinancialSalaryInsuranceImportController::$place_insurace[$place_name])) {
            $insurance_money = FinancialSalaryInsuranceImportController::$place_insurace[$place_name];
        } else {
            return admin_toastr('非深圳，武汉，南昌，西安员工，如有问题请联系管理员', 'warning', ['timeOut' => 3000]);
        }
        if (!$user_id) {
            return admin_toastr('没有选择员工', 'error', ['timeOut' => 3000]);
        } elseif ($money <= $insurance_money) {
            return admin_toastr('输入金额必须大于' . $place_name . '标准值（' . $insurance_money . '）', 'error', ['timeOut' => 3000]);
        } elseif (!$start_month || !$end_month || $start_month >= $end_month) {
            return admin_toastr('输入月份有问题','error' , ['timeOut' => 3000]);
        } else {
            $is_exist = FinancialPrizeRuleModel::where(['user_id' => $user_id, 'type' => FinancialPrizeRuleModel::TYPE_HOUSE_FUND, 'valid' => 1])->first();
            $prize_rule = FinancialPrizeRuleModel::firstOrNew(['user_id' => $user_id, 'type' => FinancialPrizeRuleModel::TYPE_HOUSE_FUND, 'valid' => 1]);
            $prize_rule->mark = '住房公积金多缴按月扣回';
            $prize_rule->content = $this->getRuleContent($money, $start_month, $end_month, $insurance_money);
            $res = $prize_rule->save();
            $message = $is_exist ? '修改成功' : '添加成功';
            if ($res) {
                return admin_toastr($message, 'success', ['timeOut' => 1000]);
            } else {
                return $content->withError('输入月份有问题', '请联系管理员');
            }
        }
    }

    private function getRuleContent($money, $start_month, $end_month, $insurance_mondey)
    {
        $content['name'] = '住房公积金扣款';
        $content['plans'][] = [
            'start' => $start_month,
            'end' => $end_month,
            'money' => $money - $insurance_mondey
        ];
        return json_encode($content, JSON_UNESCAPED_UNICODE);
    }
}
