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

$string = "123: 456,78,000"; 
$str_arr = explode(":", $string)[1]; 
print_r($str_arr); 

?>