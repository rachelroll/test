<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LandController extends Controller
{
    private $ch;

    public function __construct()
    {
        // get cURL resource
        $this->ch = curl_init();
    }

    public function land()
    {
        // 获取农场列表
        $list_url = 'https://pub-gateway.tuliu.com/apigateway/api/app/search/soil/list?current_page=1&page_size=10&sort=1&request_source=2&type=1&soil_types=2,11,6,15,76,87,55&rent_type=0&soil_area_range=0&soil_age_range=0&user_id=1075543';
        $response = $this->curl($this->ch, $list_url);
        $landsList = json_decode($response, 1)['data']['soilList'];

        foreach ($landsList as $land) {
            $landId = $land['soilId']; // 农场 ID
            $typeArr = explode($land['typeName'], '|');  // 类型
            $service = preg_replace('/[\w:]*/', '', $typeArr[0]); // 农场服务名称
            $tag = preg_replace('/[\w:]*/', '', $typeArr[1]); // 详情页标签

            $attrId1 = DB::table('sline_car_attr')->insertGetId([
                    'attrname' => $tag,
                    'webid' => 0,
                    'isopen' => 1,
            ]);

            $attrId2 = DB::table('sline_car_attr')->insertGetId([
                'attrname' => $land['rentTypeName'],
                'webid' => 0,
                'isopen' => 1,
            ]);

            $attrId = $attrId1 .','. $attrId2; // 标签属性 ID 值

            $detail_url = 'https://svr.tuliu.com/center/front/app/tuliu/soil/detail?soil_id='. $landId. '&src=1&version_app=6.1.0&apptype=1&uid=1075543';
            $response = $this->curl($detail_url);

            $detail = json_decode($response, 1)['data']; // 土地详情

            $type = ''; // 分类

            $detailId = DB::table('sline_car')->insertGetId([
                'webid' => 0,
                'title' => $detail['title'],
                'litpic' => $detail['firstFullPic'],
                'content' => $detail['detail'],
                'seotitle' => $detail['title'],
                'attrid' => $attrId,
                'kindid' => '', // 四大分类选其一
                'piclist' => '',
                'position' => $detail['locationName'], // 地址
                'contact_name' => $detail['brokInfo']['brokName'], // 联系人姓名
                'situation' => $detail['rentTypeName'], // 经营状况
                'area' => $detail['mjNumFormat'] . $detail['mjTypeStr'], // 面积
                'type' => $type, //
                'recommendnum' => rand(100, 900),
                'phone' => $detail['phone400Number']['bind_number'],
            ]);

            $price = $land['soilPrice'] . $land['soilPriceUnitStr']; // 价格
            $serviceId = DB::table('sline_car_suit')->insertGetId([
                'suitname' => $service,
                'carid' => $detailId,
            ]);

            for ($j = 0; $j < 200; $j++) {
                DB::table('sline_car_suit_price')->insert([
                    'carid' => $detailId,
                    'suitid' => $serviceId,
                    'day' => time() + $j * 86400,
                ]);
            }


        }



        // close curl resource to free up system resources
        curl_close($this->ch);

    }

    private function curl($url)
    {
        $ch = $this->ch;
        // set url
        curl_setopt($ch, CURLOPT_URL, $url);

        // set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

        // return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // send the request and save response to $response
        $response = curl_exec($ch);

        // stop if fails
        if (!$response) {
            die('Error: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch));
        }

        //echo 'HTTP Status Code: ' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
        //echo 'Response Body: ' . $response . PHP_EOL;
        return $response;
    }
}
