<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;
use Illuminate\Support\Facades\DB;
use \DateTime;

class CareerLinkCrawler extends Controller{

	const TABLE = "careerlink";
	const JOB_NAME = "careerlink";
	const CAREERLINK_DATA_PATH = 'careerlink'; // CI must create directory in
	const CAREERLINK_DATA = 'careerlink-data';
	const CAREERLINK_ERROR = 'careerlink-error-';
	const CAREERLINK_LINK = 'careerlink-link';
	const CAREERLINK_HOME = 'https://www.careerlink.vn';
	const CAREERLINK_PAGE = 'https://www.careerlink.vn/vieclam/list?page=';
	const LABEL_CONTACT = "Người liên hệ";
	const LABEL_ADDRESS = "Địa chỉ liên hệ";
	const DATE_FORMAT = "Ymd";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 500;
	const EMAIL_PATTERN = "/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i";
	const PHONE_PATTERN = "!\d+!";

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling CareerLink ...");

		while (true){
			try {
				$new_batch = CareerLinkCrawler::FindNewBatchToProcess("phpmyadmin", "job_metadata", self::TABLE);
				if ($new_batch == null){
					break;
				}
				$return_code = CareerLinkCrawler::CareerLinkPageCrawler($new_batch -> start_page, $new_batch -> end_page);

				if ($return_code > 1) {
					// CareerLinkCrawler::ResetJobMetadata("phpmyadmin", "job_metadata", self::TABLE);
					break;
				}

				if ($new_batch -> end_page > self::MAX_PAGE){ // du phong
					break;
				}
			} catch (\Exception $e) {
				$file_name = public_path('data').self::SLASH.self::CAREERLINK_DATA_PATH.self::SLASH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv';
				CareerLinkCrawler::AppendStringToFile('Ex on starter: '.substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}
		}

		$time_elapsed_secs = microtime(true) - $start;
		error_log('Total Execution Time: '.$time_elapsed_secs.' secs');
		error_log("DONE!");

		echo '<b>Total Execution Time:</b> '.$time_elapsed_secs.' secs<br>';
		echo "DONE!";
	}

    public function CareerLinkPageCrawler($start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::CAREERLINK_DATA_PATH.self::SLASH;
        $client = new Client;
		
		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			echo "page = ".$x.": ";
			error_log("Page = ".$x);
			
			try{
				$pageUrl = self::CAREERLINK_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				
				$jobs = $crawler -> filter('div.list-search-result-group > div.list-group-item > h2.list-group-item-heading > a');
				if ($jobs -> count() <= 0) {
					CareerLinkCrawler::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						$return_code = 2;
						CareerLinkCrawler::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
						break;
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
								$file_name = public_path('data').self::SLASH.self::CAREERLINK_DATA_PATH.self::SLASH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv';
								CareerLinkCrawler::AppendStringToFile('Ex when getting $job_links: '.substr($e -> getMessage (), 0, 1000), $file_name);
							}
						}
					);

					// select duplicated records
					$existing_links = CareerLinkCrawler::CheckLinksExist($jobs_links, env("DATABASE"), $table=self::TABLE);
					$duplicated_links = array();
					foreach($existing_links as $row){
						$link = $row -> link;
						array_push($duplicated_links, $link);
					}
		
					// deduplicate
					$new_links = array_diff($jobs_links, $duplicated_links);
					
					if (is_array($new_links) and sizeof($new_links) > 0){
						error_log(sizeof($new_links)." new links.");
						
						$inserted = CareerLinkCrawler::InsertLinks($new_links, env("DATABASE"), $table=self::TABLE);
						if ($inserted){
							// crawl each link
							foreach ($new_links as $job_link) {
								try {
									ini_set('max_execution_time', 10000000);				
									
									if ($job_link == null){
									} else{
										$full_link = self::CAREERLINK_HOME.$job_link;
										$crawled = CareerLinkCrawler::CrawlJob($full_link, $DATA_PATH);
										if ($crawled == 0){
											CareerLinkCrawler::AppendStringToFile($full_link
												, $DATA_PATH.self::CAREERLINK_LINK.'.csv');
										}
									}
								} catch (\Exception $e) {
									CareerLinkCrawler::AppendStringToFile("Exception on link:".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				$file_name = public_path('data').self::SLASH.self::CAREERLINK_DATA_PATH.self::SLASH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv';
				CareerLinkCrawler::AppendStringToFile("Exception on page = ".$x.": ".substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}
			$x++;
			
			if ($x > self::MAX_PAGE){ // du phong
				$return_code = 3;
				break;
			}

			$page_total_time = microtime(true) - $page_start;
			echo '<b>Total execution time of page '.$x.":</b> ".$page_total_time.' secs<br>';
		} 

		return $return_code;
	}
	
    public function CrawlJob($url, $data_path){
		// $job_start = microtime(true);
		$client = new Client;
		// echo 'create client: '.(microtime(true) - $job_start).' secs, ';
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			CareerLinkCrawler::AppendStringToFile('Exception on crawling job: '.$url.': '.$e -> getMessage()
				, $data_path.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}
		
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';
		// $content_crawler = $crawler -> filter('div.body-container');
		if ($crawler -> count() <= 0 ) {
			CareerLinkCrawler::AppendStringToFile("Job expired. No job content:  ".$url
				, $data_path.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
			return 2;
		} else{
			$header_crl = $crawler -> filter('div.body-container > div.job-header');
			if ($header_crl -> count() <= 0){
				CareerLinkCrawler::AppendStringToFile("Job expired. No div.job-header:  ".$url
				, $data_path.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
				return 3;
			}
			$title_crl = $header_crl -> filter('h1 > span');
			if ($title_crl -> count() <= 0){
				CareerLinkCrawler::AppendStringToFile("Job expired. No title:  ".$url
				, $data_path.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
				return 4;
			}
			$job_title = $title_crl -> first() -> text();
			$job_title = trim($job_title, "\r\n- ");
			
			$job_data_crl = $crawler -> filter('div.job-data');
			if($job_data_crl -> count() <= 0){
				CareerLinkCrawler::AppendStringToFile("No job-data:  ".$url
				, $data_path.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
				return 5;
			}

			$critical_job_data_crl = $job_data_crl -> filter('ul > li');
			$company = "";
			$website = "";
			if($critical_job_data_crl -> count() > 0){
				$company = $critical_job_data_crl -> first() -> text();
				$website_crl = $critical_job_data_crl -> first() -> filter('a.text-accent');
				if ($website_crl -> count() > 0){
					$website = $website_crl -> first() -> attr('href');
				}
			}
			$company = trim($company, "\r\n- ");
			
			$address = "";
			if($critical_job_data_crl -> count() > 1){
				$address = $critical_job_data_crl -> eq(1) -> text();
			}
			$address = preg_replace('!\s+!', ' ', $address);
			$address = trim($address, "\r\n- ");

			$salary = "";
			if($critical_job_data_crl -> count() > 2){
				$salary = $critical_job_data_crl -> eq(2) -> text();
			}
			$salary = str_replace('MONTH', '', $salary);
			$salary = str_replace('Lương:', '', $salary);
			$salary = preg_replace('!\s+!', ' ', $salary);
			$salary = trim($salary, "\r\n- ");

			$des_crl = $job_data_crl -> filter('div');
			$job_des = "";
			foreach($des_crl as $div_node){
				$node = new Crawler($div_node);
				if ($node -> attr('itemprop') != null and strpos($node -> attr('itemprop'), 'description') !== false){
					$job_des = $node -> first() -> text();
				}
			}
			$job_des = preg_replace('!\s+!', ' ', $job_des);
			$job_des = trim($job_des, "\r\n- ");

			$contact_crl = $job_data_crl -> filter('ul.list-unstyled');
			$contact = "";
			$email = "";
			$mobile = "";
			if ($contact_crl -> count() > 0){
				$contact = $contact_crl -> last() -> text();
				$li_contact_crl = $contact_crl -> last() -> filter('li');
				if ($li_contact_crl -> count() > 0){
					foreach($li_contact_crl as $li_node){
						$li_crl = new Crawler($li_node);
						$li_text = $li_crl -> text();
						if (strpos($li_text, 'Email') !== false){
							$email = CareerLinkCrawler::ExtractEmailFromText($li_text);
						} else if (strpos($li_text, 'Điện thoại') !== false){
							$mobile = CareerLinkCrawler::ExtractFirstMobile($li_text);
						}
					}
				}
			}
			// $contact = str_replace('Tên liên hệ:', '', $contact); //todo separate
			$contact = preg_replace('!\s+!', ' ', $contact);
			$contact = trim($contact, "\r\n- ");
			if (strcmp($email,"") == 0){
				$email = CareerLinkCrawler::ExtractEmailFromText($contact);
			}

			$date_crl = $job_data_crl -> filter('dl > dd > span');
			$created = "";
			$deadline = "";
			if ($date_crl -> count() > 1){
				$created = $date_crl -> first() -> text();
				$deadline = $date_crl -> eq(1) -> text();
			}
			if (strcmp($deadline,"") != 0){
				$deadline = explode("T", $deadline)[0];
			}

			$soluong = "";

			// $file_start = microtime(true);
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

			CareerLinkCrawler::AppendArrayToFile($job_data , $data_path.self::CAREERLINK_DATA.'.csv', "|");
			return 0;
		}
	}

	public function ExtractMobile($contact){
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
		if (strlen($mobiles_str) < 10 or strlen($mobiles_str) > 16) return "";
		return $mobiles_str;
	}

	public function ExtractEmailFromText($text){
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
				$new_batch = (object) array("job_name" => self::JOB_NAME, "start_page" => 1, "end_page" => self::BATCH_SIZE);
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
			$file_name = public_path('data').self::SLASH.self::CAREERLINK_DATA_PATH.self::SLASH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv';
			CareerLinkCrawler::AppendStringToFile('Ex when find new batch: '.substr($e -> getMessage (), 0, 1000), $file_name);
		}
		return null;
	}
}
