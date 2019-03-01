<?php

// $list = array (
//     array('aaa', 'bbb', 'ccc', 'dddd'),
//     array('Nhân Viên Tư Vấn Bán Hàng- Thiết Bị Văn Phòng Đi Làm Ngay', 'CT TNHH TM VA PT CÔNG NGHỆ QUANG MINH', '26-02-2019', 'Địa chỉ: Số 11 Ngõ 1197 Giải Phóng Hoàng Mai Hà Nội'),
//     array('"aaa"', '"bbb"')
// );

// $fp = fopen('newfile.csv', 'a');

// foreach ($list as $fields) {
//     fputcsv($fp, $fields, $delimiter = "|");
// }

// fclose($fp);

// $soluong = "- Mức lương:                                          7-10 triệu";
// preg_replace("/[\r\n]*/", "", trim($soluong, "\r\n "));
// preg_replace("  ", "", $soluong);
// var_dump($soluong);

// $string = "123: 456,78,000"; 
// $str_arr = explode(":", $string)[1]; 
// print_r($str_arr); 

// echo str_replace("  ","","Hello     world!");

// $str = "Ms Nhung - 08888.92.600 Ms Nhung - 08888.92.600 Mr Toàn ( 0909 391771 ), //Ms Như ( 0909.65363.8 ), Ms Nga ( 09.025001.48 )";
// $str = "Mr Toàn ( 0909391771); Ms Như 0909653638; Ms Nga 0902500148";
// // $str = "Ms Nhung 088";

// preg_match_all('!\d+!', $str, $matches);
// print_r($matches[0]);

// $len = count($matches[0]);
// if ($len > 0){
//     $nums = $matches[0];
//     $mobiles = array();
//     $mobile_tmp = "";
//     for ($x = 0; $x < $len; $x++) {
//         $num = $nums[$x];
//         if (strlen($mobile_tmp.$num) <= 12){
//             $mobile_tmp = $mobile_tmp.$num;
//         } else {
//             array_push($mobiles, $mobile_tmp);
//             $mobile_tmp = $num;
//         }
//         if ($x == $len - 1){
//             array_push($mobiles, $mobile_tmp);
//         }
//     } 
//     $mobiles_str = implode(",", $mobiles);
//     echo $mobiles_str;
//     // print_r($mobiles);
// } 

// $mobiles = array();
// $mobile_tmp = "";
// foreach ($matches[0] as $num) {
//     if (strlen($mobile_tmp.$num) < 12){
//         $mobile_tmp = $mobile_tmp.$num;
//     } else {
//         array_push($mobiles, $mobile_tmp);
//         $mobile_tmp = $num;
//     }
// }
// $mobiles_str = implode(",", $mobiles);
// echo $mobiles_str
// print_r($mobiles);

// $arr = array('Hello','World!','Beautiful','Day!');
// echo implode(",",$mobiles);
// echo implode("",$matches[0]);


// $job_link = 'https://www.findjobs.vn/viec-lam-ke-toan-kho-j19748vi.html';
// $job_link = "https://www.findjobs.vn2";
// echo ($job_link == null)."1<br>";
// echo (strcmp($job_link, 'https://www.findjobs.vn') < 0)."2<br>";
// echo (strcmp('https://www.findjobs.vn/viec-lam-vi', $job_link) == 0)."3<br>";

// echo date_format($date,"YmdHis");

// echo 'findjobs-error-'.date('YmdH').'.csv<br>';
// echo 'findjobs-links-'.date('YmdH').'.csv<br>';
// echo 'findjobs-error-'.date('YmdH').'.csv<br>';
// echo 'findjobs-'.date('YmdH').'.csv<br>';

// $arr = array('1266938', '12', 'fsdfsdf');
// $idx = 0;
// foreach($arr as $el){
//     $arr[$idx] = "(".$el.")";
//     $idx++;
// }
// $str = implode(",", $arr);
// echo $str;


// echo 'mywork-'.date('Ymd').'.csv';
// echo 'mywork-error-'.date('Ymd').'.csv';

$jobs_links = array("1", "2", "3");
$select_param = "('".implode("','", $jobs_links)."')";
echo $select_param;

$tempalate = "select %s from %s ";

echo sprintf($tempalate, "a", "b");

?>