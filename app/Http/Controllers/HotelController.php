<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HotelController extends Controller
{

    private $ch;

    public function getHotelList()
    {
        // get cURL resource
        $this->ch = curl_init();

        // 请求酒店列表
        $m = 0;
        for ($i = 1; $i < 12; $i++) {
            echo 'round' . $i;
            $url = 'https://www.jihex.cn/portal/h5/search/merchant/getHotelProductBaseList?pageno=' . $i . '&pagecnt=100&isHomeFlag=0&sortType=2&solrType=1';
            $response = $this->curl($url);
            $searchLists = json_decode($response, 1)['data']['searchList'];

            // 遍历酒店列表

            foreach ($searchLists as $key => $searchList) {
                // 请求酒店详情
                $url = 'https://www.jihex.cn/jihexmerchant/h5/v1/merchant/' . $searchList['hotelId'] . '?fields=cover,tag,facility,room,policy,traffic,recommended';
                $response = $this->curl($url);
                $hotel_info = json_decode($response, 1)['data'];
                // 判断是否有房间列表
                try {
                    if (!empty($hotel_info['roomList'])) {
                        $m++;
                        dump($m);
                        echo $m;
                        echo '<br>';
                        echo 'hotelId=>' . $searchList['hotelId'];
                        echo '<br>';

                        $hotel_id = DB::table('sline_hotel')->insertGetId([
                            'title'           => $hotel_info['hotelCname'],
                            'seotitle'        => $hotel_info['hotelCname'],
                            'content'         => $hotel_info['brief'],
                            'address'         => $hotel_info['address'],
                            'litpic'          => $hotel_info['listImg'],
                            'piclist'         => implode($hotel_info['imgList'], ','),
                            'traffic'         => isset($hotel_info['extInfo']['traffic']) ? $hotel_info['extInfo']['traffic'] : '',
                            'notice'          => isset($hotel_info['extInfo']['policy']) ? $hotel_info['extInfo']['policy'] : '',
                            'decoration_time' => isset($hotel_info['createTime']) ? $hotel_info['createTime'] : '',
                            'webid'           => 0,
                            'satisfyscore'    => 100,
                            'recommendnum'    => rand(88, 100),
                            'lng'             => $hotel_info['locLat'] ?: NULL,
                            'lat'             => $hotel_info['locLon'] ?: NULL,
                            'postcode'        => $hotel_info['hotelId'],
                            'establishment'        => '',
                        ]);

                        // 遍历所有房间
                        foreach ($hotel_info['roomList'] as $v) {
                            $imglist = '';
                            if (!empty($v['imgList'])) {
                                $array = [];
                                foreach ($v['imgList'] as $k => $img) {
                                    $array[$k]= $img['imgUrl'];
                                }
                                $imglist = implode($array, ',');
                            }

                            // 请求每一个房间的价格信息
                            $url = 'https://api.jihelife.com/jihexmerchant/client/v1/roomtype/product?fields=price,stock&ids=' . $v['roomtypeId'];
                            $response = $this->curl($url);
                            $roomtype = json_decode($response, 1)['data'] ?? NULL;

                            // 判断是否有价格套餐信息
                            if ($roomtype) {
                                $original_price = $roomtype[ $v['roomtypeId'] ][0]['productPrice']['totalPrice'] ?? 0;
                                $current_price = $roomtype[ $v['roomtypeId'] ][0]['productPrice']['showTotalPrice'] ?? 0;

                                $suitid = DB::table('sline_hotel_room')->insertGetId([
                                    'hotelid'     => $hotel_id,
                                    'webid'       => 1,
                                    'roomname'    => $v['name'],
                                    'roomstyle'   => $v['bedtypeDesc'] ?? NULL,
                                    'roomarea'    => $v['roomArea'] ?? NULL,
                                    'roomfloor'   => $v['floorDesc'] ?? NULL,
                                    'roomwindow'  => $v['windowDesc'] ?? NULL,
                                    'piclist'     => $imglist,
                                    'description' => $v['roomDesc'] ?? NULL,
                                    'litpic'      => strpos($v['roomImgs'], 'http') ? $v['roomImgs']: 'http://html.qiniu.jihelife.com/'. $v['roomImgs'],
                                    'lastoffer'      => '',
                                    'sellprice'   => $original_price / 100,
                                    'breakfirst'  => $roomtype[ $v['roomtypeId'] ][0]['breakfast'] ?? NULL,
                                    'pay_way'     => 2, // 支付方式：线下
                                ]);

                                $endtime = (int) $roomtype[ $v['roomtypeId'] ][0]['saleEndtime'] / 1000;

                                $endtime = $endtime > time() ? 1609257600 : $roomtype[ $v['roomtypeId'] ][0]['saleEndtime'];
                                $sell_days = round(($endtime - time()) / 3600 / 24);

                                for ($j = 0; $j < $sell_days; $j++) {
                                    DB::table('sline_hotel_room_price')->insert([
                                        'hotelid' => $hotel_id,
                                        'suitid'  => $suitid,
                                        'day'     => time() + $j * 86400,
                                        'price'   => $current_price / 100,
                                        'number'  => $roomtype[ $v['roomtypeId'] ][0]['tolAmount'],
                                    ]);
                                }
                            } else {
                                DB::table('sline_hotel_room')->insertGetId([
                                    'hotelid'     => $hotel_id,
                                    'webid'       => 1,
                                    'roomname'    => $v['name'],
                                    'roomstyle'   => $v['bedtypeDesc'],
                                    'roomarea'    => $v['roomArea'],
                                    'roomfloor'   => $v['floorDesc'],
                                    'roomwindow'  => $v['windowDesc'],
                                    'lastoffer'      => '',
                                    'piclist'     => $imglist,
                                    'description' => $v['roomDesc'],
                                    'litpic'      => $v['roomImgs'],
                                    'pay_way'     => 2, // 支付方式：线下
                                ]);
                            }
                        }
                    }
                    echo $searchList['hotelId'];
                    echo '----';
                    echo $searchList['hotelCname'];
                } catch (\Exception $e) {
                    dd($e->getMessage());
                }
            }
            echo 'round' . $i;
        }

        // close curl resource to free up system resources
        curl_close($this->ch);
    }

    public function curl($url)
    {
        $ch = $this->ch;

        // set url
        curl_setopt($ch, CURLOPT_URL, $url);

        // set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        // return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // set headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

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
