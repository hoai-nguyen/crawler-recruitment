<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;
use Illuminate\Support\Facades\DB;

class TopCVCrawler extends Controller{

	const TABLE = "topcv";
	const JOB_NAME = "topcv";
	const TOPCV_DATA_PATH = 'topcv'; // CI must create directory in
	const TOPCV_DATA = 'topcv-data';
	const TOPCV_ERROR = 'topcv-error-';
	const TOPCV_LINK = 'topcv-link';
	const TOPCV_HOME = 'https://www.topcv.vn/viec-lam/moi-nhat.html?page=';
	const LABEL_SALARY = 'Mức lương:';
	const LABEL_QUANTITY = 'Số lượng cần tuyển:';
	const LABEL_DEADLINE = "Hạn nộp hồ sơ:";
	const DATE_FORMAT = "Ymd";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 500;
	const EMAIL_PATTERN = "/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i";
	const PHONE_PATTERN = "!\d+!";

	public function CrawlerStarter(){
		$start = microtime(true);

		$client = TopCVCrawler::TopCVLogin();
		while (true){
			try {
				$new_batch = TopCVCrawler::FindNewBatchToProcess("phpmyadmin", "job_metadata", self::JOB_NAME);
				if ($new_batch == null){
					break;
				}
				$return_code = TopCVCrawler::TopCVCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);

				// if ($return_code > 1) {
				// 	TopCVCrawler::ResetJobMetadata("phpmyadmin", "job_metadata", self::JOB_NAME);
				// 	break;
				// }
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				$file_name = public_path('data').self::SLASH.self::TOPCV_DATA_PATH.self::SLASH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv';
				TopCVCrawler::AppendStringToFile('Exception on starter: '.substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}
		}

		$time_elapsed_secs = microtime(true) - $start;
		echo '<b>Total Execution Time:</b> '.$time_elapsed_secs.' secs<br>';
		echo "DONE!";
	}
	
	public function TopCVCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::TOPCV_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::TOPCV_HOME.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('div.job-list > div.result-job-hover') -> filter('h4.job-title > a');
				if ($jobs -> count() <= 0) {
					TopCVCrawler::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						TopCVCrawler::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
						return 2;
					}
					$last_page_is_empty = true;
				} else{
					$last_page_is_empty = false;

					// get job links
					$jobs_links = $jobs -> each(
						function ($node) {
							try {
								$job_link = $node -> attr('href');
								if ($job_link != null){
									return $job_link;
								}
							} catch (\Exception $e) {
								$file_name = public_path('data').self::SLASH.self::TOPCV_DATA_PATH.self::SLASH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv';
								TopCVCrawler::AppendStringToFile('Exception on getting job_link: '.substr($e -> getMessage (), 0, 1000), $file_name);
							}
						}
					);

					// select duplicated records
					$existing_links = TopCVCrawler::CheckLinksExist($jobs_links, env("DATABASE"), self::TABLE);
					$duplicated_links = array();
					foreach($existing_links as $row){
						$link = $row -> link;
						array_push($duplicated_links, $link);
					}
		
					// deduplicate
					$new_links = array_diff($jobs_links, $duplicated_links);
					if (is_array($new_links) and sizeof($new_links) > 0){
						$inserted = TopCVCrawler::InsertLinks($new_links, env("DATABASE"), self::TABLE);
						if ($inserted){
							// crawl each link
							foreach ($new_links as $job_link) {
								try {
									ini_set('max_execution_time', 10000000);				
									
									if ($job_link == null){
									} else{
										$code = TopCVCrawler::CrawlJob($client, $job_link, $DATA_PATH);
										if ($code == 0)
											TopCVCrawler::AppendStringToFile($job_link
												, $DATA_PATH.self::TOPCV_LINK.'.csv');
									}
								} catch (\Exception $e) {
									TopCVCrawler::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				$file_name = public_path('data').self::SLASH.self::TOPCV_DATA_PATH.self::SLASH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv';
				TopCVCrawler::AppendStringToFile("Exception on page = ".$x.": ".substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}

			$x++;
			if ($x > self::MAX_PAGE){ // du phong
				return 3;
			}
			$page_total_time = microtime(true) - $page_start;
			echo '<b>Total execution time of page '.$x.":</b> ".$page_total_time.' secs<br>';
		} 
		return $return_code;
	}

	public function ITViecLogin(){
    	$client = new Client;
    	$client1 = new Client;

    	$crawler = $client -> request('GET', 'https://itviec.com/');
		// $crawler = $client->click($crawler->selectLink('Sign in')->link());

		$modal_crl = $crawler -> filter('a.pageMenu__link');
		$modal = $client -> click($modal_crl -> link());
		// $form = $modal -> filter('#form-login') -> form();
		$form = $modal -> selectButton('Sign in')->form();
		$page = $client -> submit($form, array('user[email]' => 'truongdinhanh.telcs@gmail.com', 'user[password]' => 'vanhoai1@'));
		dd($page -> text());
		
		
		$form = $crawler -> filter('#form-login') -> form();
		$form_crl = $client -> submit($form, array('email' => 'nguyenvanhoai.cs@gmail.com', 'password' => 'vanhoai1@'));

		$url1 = "https://www.topcv.vn/viec-lam/nhan-vien-kinh-doanh-website-ho-chi-minh/86194.html";
		$job_crawler1 = $client -> request('GET', $url1);
		$salary_crl1 = $job_crawler1 -> filter('div.job-info-item');
		echo $salary_crl1 -> text();
		echo '<br>';

		$url2 = "https://www.topcv.vn/viec-lam/ke-toan-thue/86786.html";
		$job_crawler2 = $client -> request('GET', $url2);
		$salary_crl2 = $job_crawler2 -> filter('div.job-info-item');
		dd($salary_crl2 -> text());

	}

	public function TopCVLogin(){
		$client = new Client;
		try{
			$crawler = $client -> request('GET', 'https://www.topcv.vn/login');
			// $crawler = $client->click($crawler->selectLink('Sign in')->link());
			
			$form = $crawler -> filter('#form-login') -> form();
			$form_crl = $client -> submit($form, array('email' => 'nguyenvanhoai.cs@gmail.com', 'password' => 'vanhoai1@'));

			return $client;
		} catch (\Exception $e) {
			$file_name = public_path('data').self::SLASH.self::TOPCV_DATA_PATH.self::SLASH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv';
			TopCVCrawler::AppendStringToFile('Exception on login: '.($e -> getMessage ()), $file_name);
		}
		return $client;
	}

    public function CrawlJob($client, $url, $data_path){
		$job_start = microtime(true);
		
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			TopCVCrawler::AppendStringToFile("Cannot request page: ".$url.": ".substr($e -> getMessage (), 0, 1000)
				, $data_path.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}

		if ($crawler -> count() <= 0 ) {
			TopCVCrawler::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$general_infos = $crawler -> filter('#row-job-title');
			if($general_infos -> count() <= 0){
				TopCVCrawler::AppendStringToFile("No #row-job-title: ".$url
					, $data_path.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}
			
			$job_title = $general_infos -> filter('h1.job-title') -> text();
			$company = $general_infos -> filter('div.company-title') -> text();
			$company = TopCVCrawler::RemoveTrailingChars($company);

			
			$deadline = $general_infos -> filter('div.job-deadline') -> text();
			$deadline = str_replace(self::LABEL_DEADLINE, "", $deadline); 
			$deadline = TopCVCrawler::RemoveTrailingChars($deadline);
			
			$address_crl = $general_infos -> filter('div.text-dark-gray');
			if ($address_crl -> count() <= 0){
				TopCVCrawler::AppendStringToFile("No div.text-dark-gray: ".$url
					, $data_path.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}
			$address = $address_crl -> first() -> text();
			$address = TopCVCrawler::RemoveTrailingChars($address);
			
			$job_infos_crl = $crawler -> filter('#tab-info');
			if ($job_infos_crl -> count() <= 0){
				TopCVCrawler::AppendStringToFile("No #tab-info: ".$url
					, $data_path.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}
			$job_des = $crawler -> filter('#col-job-left > div.content-tab') -> text();
			$job_des = TopCVCrawler::RemoveTrailingChars($job_des);

			$job_details_crl = $job_infos_crl -> filter('#col-job-right > #box-info-job > div.job-info-item');
			$count = 0;
			$salary = "";
			$soluong = "";
			foreach($job_details_crl as $info){
				$info_node = new Crawler($info);
				$text = $info_node -> text();
				if ($count >= 2){
					break;
				} else if (strpos($text, self::LABEL_SALARY) !== false){
					$salary = $text;
					$count++;
				} else if (strpos($text, self::LABEL_QUANTITY) !== false){ 
					$soluong = $text;
					$count++;
				}
			}
			$salary = str_replace(self::LABEL_SALARY, "", $salary);
			$salary = TopCVCrawler::RemoveTrailingChars($salary);
			$soluong = str_replace(self::LABEL_QUANTITY, "", $soluong);
			$soluong = TopCVCrawler::RemoveTrailingChars($soluong);
			
			$job_contact_crl = $crawler -> filter('#tab-info-company') -> filter('#col-job-left > div.content-tab');
			$contact = "";
			$mobile = "";
			$website = "";
			if ($job_contact_crl -> count() > 0){
				$contact = $job_contact_crl -> last() -> text();
				$contact = TopCVCrawler::RemoveTrailingChars($contact);
				$atag = $job_contact_crl -> filter('a') -> last();
				if($atag -> count() > 0){
					$website = $atag -> attr('href');
				}
				$ptag = $job_contact_crl -> filter('p');
				if($ptag -> count() > 1){
					$mobile = $ptag -> last() -> text();
					$mobile = TopCVCrawler::ExtractFirstMobile($mobile);
				}
			}
			
			$email = "";
			$created = "";
			
			$job_data = array($mobile
				, $email
				, $contact
				, $company
				, $address
				, $job_title
				, $salary
				, $job_des
                , $created
                , $deadline
				, $soluong
				, $website
				, $url);
			
			TopCVCrawler::AppendArrayToFile($job_data
				, $data_path.self::TOPCV_DATA.'.csv', "|");
			return 0;
			// echo 'write file: '.(microtime(true) - $file_start).' secs <br>';
			// echo 'Total 1 job: '.(microtime(true) - $job_start).' secs <br>';
		}
	}

	public function ExtractMobile($contact){
		if ($contact == null) return "";
		preg_match_all(self::PHONE_PATTERN, $contact, $matches);

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
			$mobiles_str = implode(",", $mobiles);
		} 
		return $mobiles_str;
	}

	public function ExtractFirstMobile($contact){
		if ($contact == null) return "";
		preg_match_all(self::PHONE_PATTERN, $contact, $matches);

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
				} 
				$mobiles_str = $mobiles[0];
			}
		} 
		if (strlen($mobiles_str) < 8 or strlen($mobiles_str) > 16) return "";
		return $mobiles_str;
	}

	public function ExtractEmailFromText($text){
		if ($text == null) return "";
		preg_match_all(self::EMAIL_PATTERN, $text, $matches);
		if (sizeof($matches[0]) > 0){
			return $matches[0][0];
		} else{
			return "";
		}
	}

	public function AppendArrayToFile($arr, $file_name, $limiter="|"){
		$fp = fopen($file_name, 'a');
		fputcsv($fp, $arr, $delimiter = $limiter);
		fclose($fp);
	}

	public function AppendStringToFile($str, $file_name){
		$fp = fopen($file_name, 'a');
		fputcsv($fp, array($str));
		fclose($fp);
	}

	public function CheckLinksExist($jobs_links, $database="phpmyadmin", $table){
		if (env("DATABASE") == null) $database="phpmyadmin";

		$select_param = "('".implode("','", $jobs_links)."')";
		$select_dialect = "select link from ".$database.".".$table." where link in ";
		$select_query = $select_dialect.$select_param;
		$existing_links = DB::select($select_query);

		return $existing_links;
	}

	public function ResetJobMetadata($database, $table, $job_name){
		DB::delete("delete from ".$database.".".$table." where job_name=? ", array($job_name));
	}

	public function InsertLinks($new_links, $database="phpmyadmin", $table){
		if (env("DATABASE") == null) $database="phpmyadmin";

		$insert_links = array();
		foreach($new_links as $el){
			array_push($insert_links, "('".$el."')");
		}
		$insert_param = implode(",", $insert_links);
		$insert_dialect = "insert into ".$database.".".$table."(link) values ";
		$insert_query = $insert_dialect.$insert_param;
		$insert_results = DB::insert($insert_query);
		
		return $insert_results;
	}

	public function FindNewBatchToProcess($database="phpmyadmin", $table, $job_name){
		try {
			// find latest batch: id, job_name, start_page, end_page, timestamp
			$select_query = "select * from ".$database.".".$table." where job_name='".$job_name."' order by end_page desc limit 1 ";
			$select_result = DB::select($select_query);

			// find new batch
			$latest_batch = null;
			$new_batch = null;
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

			return $new_batch;

		} catch (\Exception $e) {
			$file_name = public_path('data').self::SLASH.self::TOPCV_DATA_PATH.self::SLASH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv';
			TopCVCrawler::AppendStringToFile('Ex on finding new batch: '.substr($e -> getMessage (), 0, 1000), $file_name);
		}
		return null;
	}
	
	public function RemoveTrailingChars($text){
		return trim(preg_replace('!\s+!', ' ', $text), "\r\n- ");
	}

	public function GithubLogin(){
    	$client = new Client;

    	$crawler = $client->request('GET', 'https://github.com/login');
		// $crawler = $client->click($crawler->selectLink('Sign in')->link());
		
		$form = $crawler->selectButton('Sign in')->form();
		$crawler = $client->submit($form, array('login' => 'hoai-nguyen', 'password' => 'vanhoai1#'));

		// $test = $crawler->filter('h4.f5 text-bold mb-1') -> text()."<br>";
		var_dump($crawler);

		// $crawler->filter('h4.f5 text-bold mb-1')->each(function ($node) {
		//     var_dump($node->text()."<br>");
		// });
	}
}