<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class Common extends Controller{

	const DATE_FORMAT = "Ymd";
	const DATE_DATA_FORMAT = "d/m/Y";
	const DEFAULT_DEADLINE = "-1 day";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const EMAIL_PATTERN = "/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i";
	const WEBSITE_PATTERN = "#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#";
	const MOBILE_PREFIX = array("032", "033", "034", "035", "036", "037", "038", "039", "096", "097", "098", "086", "070", "079", "077", "076", "078", "089", "090", "093", "081", "082", "083", "084", "085", "088", "091", "094", "056", "058", "092", "059", "099");
	const MOBILE_PREFIX_CONVERSION = array(
		"0162"=>"032"
		, "0163"=>"033"
		, "0164"=>"034"
		, "0165"=>"035"
		, "0166"=>"036"
		, "0167"=>"037"
		, "0168"=>"038"
		, "0169"=>"039"
		, "0120"=>"070"
		, "0121"=>"079"
		, "0122"=>"077"
		, "0126"=>"076"
		, "0128"=>"078"
		, "0123"=>"083"
		, "0124"=>"084"
		, "0125"=>"085"
		, "0127"=>"081"
		, "0129"=>"082"
		, "0186"=>"056"
		, "0188"=>"058"
		, "0199"=>"059"
	  );
	const PHONE_PATTERN = "!\d+!";
	const PHONE_PATTERN_JP = "/[0-9]{2,4}\s*-\s*[0-9]{2,4}\s*-\s*[0-9]{2,4}/";
	const TRIM_SET = "\r\n- =*+. –נ●•âƢẢܔ֠Ȓƪܨۨ®";
	const TRIM_SET_JP = "\r\n- =*+. –";
	const PHONE_CODE_VN = "84";
	const PHONE_START = "0";
	const DB_DEFAULT = "phpmyadmin";

	public static function ExtractMobile($text){
		if ($text == null) return "";
		$mobiles_str = "";
		try{
			preg_match_all(self::PHONE_PATTERN, $text, $matches);
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
				$mobiles_str = implode(",", $mobiles);
			} 
		} catch (\Throwable $e) {
			// error_log('Exception on ExtractMobile: '.($e -> getMessage ()));
		}
		return $mobiles_str;		
	}

	public static function IsEmptyStr($text){
		return strlen(preg_replace('!\s+!', '', $text)) == 0;
	}

	public static function ExtractFirstMobile($text){
		if ($text == null) return "";
		$mobiles_str = "";
		try{
			preg_match_all(self::PHONE_PATTERN, $text, $matches);
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
					} 
					$mobiles_str = $mobiles[0];
				}
			} 
			if (strlen($mobiles_str) < 8 or strlen($mobiles_str) > 16) return "";
			return Common::PhoneCleasing($mobiles_str);
		} catch (\Throwable $e) {
			// error_log('Exception on ExtractFirstMobile: '.($e -> getMessage ()));
		}
		return $mobiles_str;		
	}

	public static function ExtractFirstJPMobile($contact){
		if ($contact == null) return "";
		$mobiles_str = "";
		try{
			preg_match_all(self::PHONE_PATTERN_JP, $contact, $matches);
			if (sizeof($matches[0]) > 0){
				$mobiles_str = $matches[0][0];
				$mobiles_str = preg_replace('!-!', '', $mobiles_str);
			} else{
				$mobiles_str = "";
			}
			if (strlen($mobiles_str) < 10 or strlen($mobiles_str) > 11) return "";
			if (strlen($mobiles_str) == 11 
					and ! in_array(substr($mobiles_str, 0, 3), array("070", "080", "090"))){
				$mobiles_str =	"";
			}
			return $mobiles_str;
		} catch (\Throwable $e) {
			// error_log('Exception on ExtractFirstMobile: '.($e -> getMessage ()));
		}
		return $mobiles_str;		
	}

	static function startsWith($string, $startString) { 
		try{
			$len = strlen($startString); 
			return (substr($string, 0, $len) === $startString); 
		} catch (\Throwable $e) {
			// error_log('Exception on startsWith: '.($e -> getMessage ()));
		}
		return false;
	} 
	
	public static function PhoneCleasing($phone){
		if ($phone == null) return null;
		$cleaned_phone = $phone;
		try{
			if (Common::startsWith($cleaned_phone, "84") and strlen($cleaned_phone) > 10){
				$cleaned_phone = substr($cleaned_phone, 2);
				if (!Common::startsWith($cleaned_phone, "0")){
					$cleaned_phone = "0".$cleaned_phone;
				}
			}
			if (!Common::startsWith($cleaned_phone, "0") and strlen($cleaned_phone) > 8){
				$cleaned_phone = "0".$cleaned_phone;
			}
		} catch (\Throwable $e) {
			// error_log('Exception on PhoneCleasing: '.($e -> getMessage ()));
		}
		return $cleaned_phone;
	}

	public static function ExtractEmailFromText($text){
		if ($text == null) return "";
		try{
			preg_match_all(self::EMAIL_PATTERN, $text, $matches);
			if (sizeof($matches[0]) > 0){
				return $matches[0][0];
			} else{
				return "";
			}
		} catch (\Throwable $e) {
			// error_log('Exception on ExtractEmailFromText: '.($e -> getMessage ()));
		}
		return "";
	}

	public static function AppendArrayToFile($arr, $file_name, $limiter="|"){
		try{
			$fp = fopen($file_name, 'a');
			fputcsv($fp, $arr, $delimiter = $limiter);
			fclose($fp);
		} catch (\Throwable $e) {
			error_log('Exception on AppendArrayToFile: '.($e -> getMessage ()));
		}
	}

	public static function AppendStringToFile($str, $file_name){
		try{
			$fp = fopen($file_name, 'a');
			fputcsv($fp, array($str));
			fclose($fp);
		} catch (\Throwable $e) {
			error_log('Exception on AppendStringToFile: '.($e -> getMessage ()));
		}
	}

	public static function CheckLinksExist($jobs_links, $database, $table){
		if ($database == null) $database=self::DB_DEFAULT;
		$existing_links = array();
		try{
			$select_param = "('".implode("','", $jobs_links)."')";
			$select_dialect = "select link from ".$database.".".$table." where link in ";
			$select_query = $select_dialect.$select_param;
			$existing_links = DB::select($select_query);
		} catch (\Throwable $e) {
			error_log('Exception on CheckLinksExist: '.($e -> getMessage ()));
		}
		return $existing_links;
	}

	public static function ResetJobMetadata($database, $table, $job_name){
        if ($database == null) $database=self::DB_DEFAULT;
		DB::delete("delete from ".$database.".".$table." where job_name=? ", array($job_name));
	}

	public static function InsertLinks($new_links, $database, $table){
		if ($database == null) $database=self::DB_DEFAULT;
		$insert_results = false;
		try{
			$insert_links = array();
			foreach($new_links as $el){
				array_push($insert_links, "('".$el."')");
			}
			$insert_param = implode(",", $insert_links);
			$insert_dialect = "insert into ".$database.".".$table."(link) values ";
			$insert_query = $insert_dialect.$insert_param;
			$insert_results = DB::insert($insert_query);
		} catch (\Throwable $e) {
			error_log('Exception on InsertLinks: '.($e -> getMessage ()));
		}
		return $insert_results;
	}
	
	public static function RemoveTrailingChars($text){
		return trim(preg_replace('!\s+!', ' ', $text), self::TRIM_SET);
	}
	
	public static function RemoveSpaceChars($text){
		return trim(preg_replace('!\s+!', ' ', $text), self::TRIM_SET_JP);
    }
    
	public static function FindNewBatchToProcess($database, $table, $job_name){
        if ($database == null) $database=self::DB_DEFAULT;

		$new_batch = null;
		try{
			// find latest batch: id, job_name, start_page, end_page, job_deadline
			$select_query = "select * from ".$database.".".$table." where job_name='".$job_name."' order by end_page desc limit 1 ";
			$select_result = DB::select($select_query);
	
			// find new batch
			$latest_batch = null;
			if (sizeof($select_result) < 1){
				$new_batch = (object) array("job_name" => $job_name, "start_page" => 1, "end_page" => self::BATCH_SIZE);
			} else{
				$latest_batch = $select_result[0];
				$new_batch = (object) array("job_name" => $latest_batch -> job_name
					, "start_page" => $latest_batch -> end_page + 1
					, "end_page" => $latest_batch -> end_page + self::BATCH_SIZE);
			}
	
			// save batch to db
			$insert_query = "insert into ".$database.".".$table."(job_name, start_page, end_page) values (?, ?, ?) ";
			$insert_result = DB::insert(
				$insert_query
				, array($new_batch -> job_name, $new_batch -> start_page, $new_batch -> end_page)
			);
		} catch (\Throwable $e) {
			error_log('Exception on FindNewBatchToProcess: '.($e -> getMessage ()));
		}
        return $new_batch;
	}

	public static function ConvertDateFormat($input_date, $input_format, $output_format){
		if ($input_date == null or $input_date === "") return "";
		$new_date_str = "";
		try{
			$new_date = \DateTime::createFromFormat($input_format, $input_date);
			$new_date_str = $new_date->format($output_format);
		} catch (\Throwable $e) {
			// error_log('Exception on ConvertDateFormat: '.($e -> getMessage()));
		}
		return $new_date_str;
	}

	public static function ExtractWebsiteFromText($text){
		if ($text == null) return "";
		try{
			preg_match_all(self::WEBSITE_PATTERN, $text, $matches);
			if (sizeof($matches[0]) > 0){
				return $matches[0][0];
			} else{
				return "";
			}
		} catch (\Throwable $e) {
			// error_log('Exception on ExtractWebsiteFromText: '.($e -> getMessage()));
		}
		return "";
	}

	public static function ExtractDateFromText($regex, $text){
		if ($text == null) return "";
		try{
			preg_match_all($regex, $text, $matches);
			if (sizeof($matches[0]) > 0){
				return $matches[0][0];
			} else{
				return "";
			}
		} catch (\Throwable $e) {
			// error_log('Exception on ExtractDateFromText: '.($e -> getMessage()));
		}
		return "";
	}

	public static function IsNullOrEmpty($str){
		return strlen($str) == 0;
	}

	public static function isNotMobile($mobile){
		if (strlen($mobile) != 10) 
        	return true;
		return ! in_array(substr($mobile, 0, 3), self::MOBILE_PREFIX);
	}

	public static function IsJobExpired($interval, $date_string){
		try{
			if (strlen($date_string) == 0) return false;
			if (strlen($interval) == 0) $interval = "0 day";
			$current = strtotime($interval, strtotime("now"));
			$job_deadline = \DateTime::createFromFormat('d/m/Y', $date_string)->getTimestamp();
			return $job_deadline < $current;
		} catch(\Throwable $ex){
			error_log($ex -> getMessage());
		}
		return false;
	}

	public static function EndWithUpper($str) {
        if (strlen($str) < 1) return false;
        
        $chr = substr($str, -1);
        return mb_strtolower($chr, "UTF-8") != $chr;
	}

	public static function UpdateMobilePrefix($mobile){
		$prefix = substr($mobile, 0, 4);
		$new_mobile = $mobile;
		if (array_key_exists($prefix, self::MOBILE_PREFIX_CONVERSION)){
		  $new_prefix = self::MOBILE_PREFIX_CONVERSION[$prefix];
		  $new_mobile = $new_prefix.substr($mobile, 4);
		}
		return $new_mobile;
	}


	public static function ExtractCreatedDateFromText($text){
		if ($text == null) return "";
		$date_str = "";
		try{
			preg_match_all("/[0-9]{1,2}\s*-\s*[0-9]{1,2}\s*-\s*[0-9]{4}/", $text, $matches);
			if (sizeof($matches[0]) > 0){
				$date_str = $matches[0][0];
			} 
			return $date_str;
		} catch (\Throwable $e) {
			// error_log('Exception on ExtractFirstMobile: '.($e -> getMessage ()));
		}
		return $date_str;		
	}

	public static function GetFileIndexToProcess($database, $table, $job_name){
		if ($database == null) $database=self::DB_DEFAULT;
		try{
			$select_query = "select * from $database.$table where job_name='$job_name' order by created_date desc limit 1 ";
			$latest_index = DB::select($select_query);
			if (sizeof($latest_index) < 1){ 
				$insert_query = "insert into $database.$table (file_index, job_name, status, running_instance) values (0, '$job_name', 'PROGRESS', 1) ";
				$insert_results = DB::insert($insert_query);
				return 0;
			} 
			$index = (object) $latest_index[0];
			
			if ($index->status == "DONE"){
				$file_index = $index->file_index + 1;
				$insert_query = "insert into $database.$table (file_index, job_name, status, running_instance) values ($file_index, '$job_name', 'PROGRESS', 1) ";
				$insert_result = DB::insert($insert_query);
				return $file_index;
			} else {
				$running_instance = $index->running_instance + 1;
				$id = $index->id;
				$update_query = "update $database.$table set running_instance=$running_instance where id=$id ";
				$update_result = DB::update($update_query);
				return $index->file_index;
			}
		} catch (\Throwable $e) {
			error_log('Exception on GetFileName: '.($e -> getMessage ()));
		}
		return 0;
	}

	public static function UpdateFileIndexAfterProcess($database, $table, $job_name){
		if ($database == null) $database=self::DB_DEFAULT;
		try{
			$select_query = "select * from $database.$table where job_name='$job_name' order by created_date desc limit 1 ";
			$latest_index = DB::select($select_query);
			if (sizeof($latest_index) < 1){
				return -1;
			} 
			$index = (object) $latest_index[0];
			$number_instances = $index->running_instance;
			$id = $index->id;
			if ($number_instances > 1){
				$number_instances--;
				$update_query = "update $database.$table set running_instance=$number_instances where id=$id ";
				$update_result = DB::update($update_query);
				return $update_result;
			} else if ($number_instances == 1) {
				$update_query = "update $database.$table set running_instance=0, status='DONE' where id=$id ";
				$update_result = DB::update($update_query);
				return $update_result;
			}
		} catch (\Throwable $e) {
			error_log('Exception on GetFileName: '.($e -> getMessage ()));
		}
		return -1;
	}
}
