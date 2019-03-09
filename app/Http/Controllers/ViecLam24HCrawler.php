<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;
use Illuminate\Support\Facades\DB;

class ViecLam24HCrawler extends Controller{

	const TABLE = "vieclam24h";
	const VIECLAM24H_DATA_PATH = 'vieclam24h'; // CI must create directory in
	const VIECLAM24H_DATA = 'vieclam24h-data';
	const VIECLAM24H_ERROR = 'vieclam24h-error-';
	const VIECLAM24H_LINK = 'vieclam24h-link';
	const VIECLAM24H_HOME = 'https://vieclam24h.vn';
	const VIECLAM24H_PAGE = 'https://vieclam24h.vn/tim-kiem-viec-lam-nhanh/?page=';
	const LABEL_CONTACT = "Người liên hệ";
	const LABEL_ADDRESS = "Địa chỉ liên hệ";
	const DATE_FORMAT = "Ymd";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 1000;
	const EMAIL_PATTERN = "/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i";
	const PHONE_PATTERN = "!\d+!";

	public function CrawlerStarter(){
		$start = microtime(true);

		while (true){
			try {
				$new_batch = ViecLam24HCrawler::FindNewBatchToProcess("phpmyadmin", "job_metadata", "vieclam24h");
				if ($new_batch == null){
					break;
				}
				$return_code = ViecLam24HCrawler::ViecLam24HPageCrawler($new_batch -> start_page, $new_batch -> end_page);

				// if ($return_code > 1) {
				// 	ViecLam24HCrawler::ResetJobMetadata("phpmyadmin", "job_metadata", "vieclam24h");
				// 	break;
				// }

				if ($new_batch -> end_page > 500){ // du phong
					break;
				}
			} catch (\Exception $e) {
				$file_name = public_path('data').self::SLASH.self::VIECLAM24H_DATA_PATH.self::SLASH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv';
				ViecLam24HCrawler::AppendStringToFile('Ex on starter: '.substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}
		}

		$time_elapsed_secs = microtime(true) - $start;
		echo '<b>Total Execution Time:</b> '.$time_elapsed_secs.' secs<br>';
		echo "DONE!";
	}

    public function ViecLam24HPageCrawler($start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::VIECLAM24H_DATA_PATH.self::SLASH;
        $client = new Client;
		
		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::VIECLAM24H_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('span.title-blockjob-main > a');

				if ($jobs -> count() <= 0) {
					ViecLam24HCrawler::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						$return_code = 2;
						ViecLam24HCrawler::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::VIECLAM24H_DATA_PATH.self::SLASH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv';
								ViecLam24HCrawler::AppendStringToFile('Ex when getting $job_links: '.substr($e -> getMessage (), 0, 1000), $file_name);
							}
						}
					);

					// select duplicated records
					$existing_links = ViecLam24HCrawler::CheckLinksExist($jobs_links, env("DATABASE"), $table="vieclam24h");
					$duplicated_links = array();
					foreach($existing_links as $row){
						$link = $row -> link;
						array_push($duplicated_links, $link);
					}
		
					// deduplicate
					$new_links = array_diff($jobs_links, $duplicated_links);

					if (is_array($new_links) and sizeof($new_links) > 0){
						$inserted = ViecLam24HCrawler::InsertLinks($new_links, env("DATABASE"), $table="vieclam24h");
						if ($inserted){
							// crawl each link
							foreach ($new_links as $job_link) {
								try {
									ini_set('max_execution_time', 10000000);				
									
									if ($job_link == null){
									} else{
										$full_link = self::VIECLAM24H_HOME.$job_link;
										$crawled = ViecLam24HCrawler::CrawlJob($full_link, $DATA_PATH);
										if ($crawled == 0){
											ViecLam24HCrawler::AppendStringToFile($full_link
												, $DATA_PATH.self::VIECLAM24H_LINK.'.csv');
										}
									}
								} catch (\Exception $e) {
									ViecLam24HCrawler::AppendStringToFile("Exception on link:".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				$file_name = public_path('data').self::SLASH.self::VIECLAM24H_DATA_PATH.self::SLASH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv';
				ViecLam24HCrawler::AppendStringToFile("Exception on page = ".$x.": ".substr($e -> getMessage (), 0, 1000), $file_name);
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
			ViecLam24HCrawler::AppendStringToFile('Exception on crawling job: '.$url.': '.$e -> getMessage()
				, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}
		
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';
		$content_crawler = $crawler -> filter('#cols-right');
		if ($content_crawler -> count() <= 0 ) {
			ViecLam24HCrawler::AppendStringToFile("Job expired. No content cols-right:  ".$url
				, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
			return 2;
		} else{
			$content = $content_crawler -> filter('div.box_chi_tiet_cong_viec');
			if ($content -> count() <= 0){
				ViecLam24HCrawler::AppendStringToFile("Job expired. No box_chi_tiet_cong_viec:  ".$url
					, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
				return 3;
			} else {
				$content = $content -> first();
				
				// $header_start = microtime(true);
				$job_title = "n/a";
				$title_crawler = $crawler -> filter('h1.text_blue');
				if ($title_crawler -> count() > 0 ) {
					$job_title = $title_crawler -> first() -> text();
				} else{
					ViecLam24HCrawler::AppendStringToFile("Job expired. No title:  ".$url
						, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
					return 5;
				}
				// echo 'header: '.(microtime(true) - $header_start).' secs, ';
				
				// $company_start = microtime(true);
				$company = "";
				$website = "";
				$company_crawler = $title_crawler -> siblings(); // OR dd($content -> filter('a') -> first() -> text());
				if ($company_crawler -> count() > 0) {
					$atag = $company_crawler -> filter('a');
					if ($atag -> count() > 0){
						$company = $atag -> first() -> text();
						$website = $atag -> first() -> attr('href');
					}
				}
				// echo 'company: '.(microtime(true) - $company_start).' secs, ';

				$deadline = '';
				$deadline_crl = $content -> filter('i.icon-countdown');
				if ($deadline_crl -> count() > 0 and $deadline_crl -> siblings() -> count() > 0){
					$deadline = $deadline_crl -> siblings() -> filter('span.text_pink');
					if ($deadline -> count() > 0){
						$deadline = $deadline -> first() -> text();
					}
				}
				// $posted_start = microtime(true);
				$created = "";
				try{
					$created_crl = $content -> filter('div.pull-left > p.text_grey2 > span');
					if ($created_crl -> count() > 2){
						$created = $created_crl -> last() -> text();
						if (strpos($created, ':') !== false){
							$created = trim(explode(":", $created)[1], "\r\n ");
						}
					}
				} catch (\Exception $e) {
					ViecLam24HCrawler::AppendStringToFile('Exception on getting created date: '.$url.': '.$e -> getMessage()
						, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
				}

				$job_details_crl = $content -> filter('div.job_detail');
				$salary = '';
				$soluong = '';
				try{
					if ($job_details_crl -> count() > 0){
						$salary_crl = $job_details_crl -> filter('i.icon-money');
						if ($salary_crl -> count() > 0 and $salary_crl -> siblings() -> count() > 0){
							$salary = $salary_crl -> siblings() -> filter('span.job_value') -> text();
						}
						$quanti_crl = $job_details_crl -> filter('i.icon-quantity');
						if ($quanti_crl -> count() > 0 and $quanti_crl -> siblings() -> count() > 0){
							$soluong = $quanti_crl -> siblings() -> filter('span.job_value') -> text();
						}
					}
				} catch (\Exception $e) {
					ViecLam24HCrawler::AppendStringToFile('Exception on getting salary + soluong: '.$url.': '.$e -> getMessage()
						, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
				}
			}

			//description + contact
			$detail_crl = $content_crawler -> filter('#ttd_detail > div.job_description');
			if ($detail_crl -> count() <= 0){
				ViecLam24HCrawler::AppendStringToFile('Job expired. No job_description: '.$url.': '.$e -> getMessage()
						, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
				return 4;
			}

			$job_des = "";
			try{
				if ($detail_crl -> count() > 0){
					$des_crl = $detail_crl -> first();
					$job_des = $des_crl -> filter('p.word_break') -> first() -> text();
					$job_des = trim(preg_replace("/[\r\n]*/", "", $job_des), "\r\n- ");
				}
			} catch (\Exception $e) {
				ViecLam24HCrawler::AppendStringToFile('Exception on getting job_des: '.$url.': '.$e -> getMessage()
					, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
			}

			$contact = "";
			$address = "";
			try{
				if ($detail_crl -> count() > 1){
					$contact_crl = $detail_crl -> eq(1);
					$texts = $contact_crl -> filter('p') -> each(
						function ($node){
							return $node -> text();
						}
					);
					if (sizeof($texts) > 1 and strpos($texts[0], self::LABEL_CONTACT) !== false){
						$contact = trim(preg_replace("/[\r\n]*/", "", $texts[1]), "\r\n- ");
					}
					if (sizeof($texts) > 3 and strpos($texts[2], self::LABEL_ADDRESS) !== false){
						$address = trim(preg_replace("/[\r\n]*/", "", $texts[3]), "\r\n- ");
					}
				}
			} catch (\Exception $e) {
				ViecLam24HCrawler::AppendStringToFile('Exception on getting contact + address: '.$url.': '.$e -> getMessage()
					, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
			}

			$mobile = ViecLam24HCrawler::ExtractFirstMobile($contact);
			$email = ViecLam24HCrawler::ExtractEmailFromText($contact);

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
			
			ViecLam24HCrawler::AppendArrayToFile($job_data , $data_path.self::VIECLAM24H_DATA.'.csv', "|");
			return 0;
		}
		return 1;
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
				$new_batch = (object) array("job_name" => "vieclam24h", "start_page" => 1, "end_page" => self::BATCH_SIZE);
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
			$file_name = public_path('data').self::SLASH.self::VIECLAM24H_DATA_PATH.self::SLASH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv';
			ViecLam24HCrawler::AppendStringToFile('Ex when find new batch: '.substr($e -> getMessage (), 0, 1000), $file_name);
		}
		return null;
	}
}