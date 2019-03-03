<?php

// function valid_email($str) {
//     return (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $str)) ? FALSE : TRUE;
// }

// if(!valid_email("hoainv@fsoft.com.vn")){
//     echo "Invalid email address.";
// }else{
//     echo "Valid email address.";
// }

$string = 'Ruchika < ruchisdka@4`example.com  ruchika@g`mail.com>';
$string = "Duycamanh@gmail.com";
$pattern = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i';
preg_match_all($pattern, $string, $matches);
// var_dump($matches[0][0]);
// print_r(sizeof($matches[0]) > 0);
// echo "|".implode(',', $matches[0])."|";
if (sizeof($matches[0]) > 0){
    echo $matches[0][0];
} else{
    echo "NA";
}

$pattern = "!\d+!";
$contact = "Ms Yến -024.364554176/77";
preg_match_all($pattern, $contact, $matches);

$mobiles_str = "";
$len = count($matches[0]);
if ($len > 0){
    $nums = $matches[0];
    $mobiles = array();
    $mobile_tmp = "";
    for ($x = 0; $x < $len; $x++) {
        $num = $nums[$x];
        if (strlen($mobile_tmp.$num) <= 12){
            $mobile_tmp = $mobile_tmp.$num;
        } else {
            array_push($mobiles, $mobile_tmp);
            $mobile_tmp = $num;
        }
        if ($x == $len - 1){
            array_push($mobiles, $mobile_tmp);
        }
    } 

    if (sizeof($mobiles) > 0 ){
        if (sizeof($mobiles) > 1 and strlen($mobiles[1]) < 5){
            $mobiles_str = $mobiles[0].'/'.$mobiles[1];
        } else{
            $mobiles_str = $mobiles[0];
        }
    }
} 
if (strlen($mobiles_str) < 10 or strlen($mobiles_str) > 16) 
    $mobiles_str =  "";

echo $mobiles_str;

?>