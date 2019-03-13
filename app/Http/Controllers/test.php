<?php

// function startsWith ($string, $startString) { 
//     $len = strlen($startString); 
//     return (substr($string, 0, $len) === $startString); 
// } 

// function phoneCleasing($phone){
//     if ($phone == null) return null;

//     $cleaned_phone = $phone;
//     if (startsWith($cleaned_phone, "84") and strlen($cleaned_phone) > 10){
//         $cleaned_phone = substr($cleaned_phone, 2);
//         if (!startsWith($cleaned_phone, "0")){
//             $cleaned_phone = "0".$cleaned_phone;
//         }
//     }
//     if (!startsWith($cleaned_phone, "0") and strlen($cleaned_phone) > 8){
//         $cleaned_phone = "0".$cleaned_phone;
//     }
//     return $cleaned_phone;
// }

// $phone1 = "841659038941";
// $phone2 = "84989204813";
// $phone3 = "84904813";
// $phone4 = "19904813";
// $phone5 = "840979016572";

// echo PhoneCleasing($phone1)."\n";
// echo PhoneCleasing($phone2)."\n";
// echo PhoneCleasing($phone3)."\n";
// echo PhoneCleasing($phone4)."\n";
// echo PhoneCleasing($phone5)."\n";

// $database = getenv("DB_DATABASE") or "phpmyadmin";
// echo "|".$database."|";

$datetime = "20130409163705"; 
$d = DateTime::createFromFormat("YmdHis", $datetime);
echo $d->format("Y-m-d"); 

?>