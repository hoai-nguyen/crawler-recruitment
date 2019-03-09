<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;
use Illuminate\Support\Facades\DB;

class MFindJobsCrawler extends Controller{

	const TABLE = "findjobs";
	const FINDJOBS_DATA_PATH = 'findjobs'; // CI must create directory in
	const FINDJOBS_DATA = 'findjobs-data';
	const FINDJOBS_ERROR = 'findjobs-error-';
	const FINDJOBS_LINK = 'findjobs-link';
	const FINDJOBS_HOME = 'https://www.findjobs.vn/viec-lam-vi?page=';
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
				$new_batch = MFindJobsCrawler::FindNewBatchToProcess("phpmyadmin", "job_metadata");
				if ($new_batch == null){
					break;
				}
				$return_code = MFindJobsCrawler::MFindJobsCrawler($new_batch -> start_page, $new_batch -> end_page);

				// if ($return_code > 1) {
				// 	MFindJobsCrawler::ResetJobMetadata("phpmyadmin", "job_metadata", "findjobs");
				// 	break;
				// }
			} catch (\Exception $e) {
				$file_name = public_path('data').self::SLASH.self::FINDJOBS_DATA_PATH.self::SLASH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv';
				MFindJobsCrawler::AppendStringToFile(substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}
		}

		$time_elapsed_secs = microtime(true) - $start;
		echo '<b>Total Execution Time:</b> '.$time_elapsed_secs.' secs<br>';
		echo "DONE!";
	}

    public function MFindJobsCrawler($start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::FINDJOBS_DATA_PATH.self::SLASH;
        $client = new Client;
		
		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::FINDJOBS_HOME.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('#job_list > li.row');

				if ($jobs -> count() <= 0) {
					MFindJobsCrawler::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						$return_code = 2;
						MFindJobsCrawler::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv');
						break;
					}
					$last_page_is_empty = true;
				} else{
					$last_page_is_empty = false;
					
					foreach($jobs as $node){
						$node = new Crawler($node);
						$DATA_PATH = public_path('data').self::SLASH.self::FINDJOBS_DATA_PATH.self::SLASH;
						try {
							$link_node = $node -> filter('a') -> each(
								function ($node){
									if ($node -> attr('itemprop') != null 
											and strpos($node -> attr('itemprop'), 'title') !== false
											and $node -> attr('href') != null)
										return $node -> attr('href');
								}
							);
							$job_link = current(array_filter($link_node));
							
							if ($job_link == null or strcmp($job_link, 'https://www.findjobs.vn') < 0){
							} else if(strcmp('https://www.findjobs.vn/viec-lam-vi', $job_link) == 0) {
								$return_code = 4;
								MFindJobsCrawler::AppendStringToFile("Running out of job: ", $DATA_PATH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv');
								break;
							} else{
								// select duplicated records
								$existing_links = MFindJobsCrawler::CheckLinksExist(array($job_link), env("DATABASE"), $table="findjobs");
								$duplicated_links = array();
								foreach($existing_links as $row){
									$link = $row -> link;
									array_push($duplicated_links, $link);
								}
					
								// deduplicate
								$new_links = array_diff(array($job_link), $duplicated_links);

								if (is_array($new_links) and sizeof($new_links) > 0){
									
									$inserted = MFindJobsCrawler::InsertLinks($new_links, env("DATABASE"), $table="findjobs");
									if ($inserted){
										foreach ($new_links as $link) {
											ini_set('max_execution_time', 10000000);		
											try {
												$created = $node -> filter("span.activedate") -> text();
												$created = trim($created, "\r\n\t");

												MFindJobsCrawler::CrawlJob($link, $created, $DATA_PATH);
												
												MFindJobsCrawler::AppendStringToFile($link , $DATA_PATH.self::FINDJOBS_LINK.'.csv');
											} catch (\Exception $e) {
												MFindJobsCrawler::AppendStringToFile("Exception on link:".$link.": ".substr($e -> getMessage (), 0, 1000)
													, $DATA_PATH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv');
											}
										}
										// end for
									}
								}
							}
						} catch (\Exception $e) {
							MFindJobsCrawler::AppendStringToFile('Exception on $node: '.substr($e -> getMessage (), 0, 1000)
								, $DATA_PATH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv');
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 2;
				$file_name = public_path('data').self::SLASH.self::FINDJOBS_DATA_PATH.self::SLASH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv';
				MFindJobsCrawler::AppendStringToFile("Exception on page = ".$x.": ".substr($e -> getMessage (), 0, 1000), $file_name);
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
	
    public function CrawlJob($url, $created, $data_path){
		// $job_start = microtime(true);
		$client = new Client;
		// echo 'create client: '.(microtime(true) - $job_start).' secs, ';
		$crawler = $client -> request('GET', $url);
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';

		$content_crawler = $crawler -> filter('#job_detail');
		if ($content_crawler -> count() <= 0 ) {
			MFindJobsCrawler::AppendStringToFile("ERROR: Failed to crawl ".$url
			, $data_path.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv');
		} else{
			$content = $content_crawler -> first();
			// $header_start = microtime(true);
			$job_title = "";
			$title_crawler = $crawler -> filter('h1.title');
			if ($title_crawler -> count() > 0 ) {
				$job_title = $title_crawler -> first() -> text();
			}
			// echo 'header: '.(microtime(true) - $header_start).' secs, ';

			// $company_start = microtime(true);
            $company = "";
			$company_crawler = $content -> filter('h2.company');
			if ($company_crawler -> count() > 0 ) {
				$company = $company_crawler -> first() -> text();
			}
			// echo 'company: '.(microtime(true) - $company_start).' secs, ';

			$company_details = $crawler -> filter('div.detail > dl.dl-horizontal > dd');
			$address = "";
			$website = "";
			if ($company_details -> count() > 0){
				foreach($company_details as $node){
					$comd_crawler = new Crawler($node);
					$itemprop = $comd_crawler -> attr('itemprop');
					$atag = $comd_crawler -> filter('a');
					if (strpos($itemprop, 'address') !== false){
						$address = $comd_crawler -> text();
					} 
					if ($atag -> count() > 0){
						$website = $atag -> attr('href');
					}
				}
			}
			$address = trim($address, "\r\n ");
			
            // $salary_start = microtime(true);
            $salary = '';
            $salary_crawler = $content -> filter('ol.job_attribs > li > span');
            if ($salary_crawler -> count() > 0 ) {
				$salary = $salary_crawler -> last() -> text();
			}
            // echo 'salary: '.(microtime(true) - $salary_start).' secs, ';

			// $job_des 
			$jds = $content -> filter('ul') -> first();
			$job_des = "";
			if ($jds -> count() > 0){
				$job_des = $jds -> text();
			}
			$job_des = trim($job_des, "\r\n -");
			$job_des = preg_replace("/[\r\n]/", " ", $job_des);

			// $mobile = FindJobsCrawler::ExtractMobile($contact);
			$mobile = "";
			$email = "";
			$soluong = "";
			$deadline = "";
			$contact = "";

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
			
			MFindJobsCrawler::AppendArrayToFile($job_data, $data_path.self::FINDJOBS_DATA.'.csv', "|");
			// echo 'write file: '.(microtime(true) - $file_start).' secs <br>';
			// echo 'Total 1 job: '.(microtime(true) - $job_start).' secs <br>';
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

	public function FindNewBatchToProcess($database="phpmyadmin", $table){
		try {
			// find latest batch: id, job_name, start_page, end_page, timestamp
			$select_query = "select * from ".$database.".".$table." where job_name='findjobs' order by end_page desc limit 1 ";
			$select_result = DB::select($select_query);

			// find new batch
			$latest_batch = null;
			$new_batch = null;
			if (sizeof($select_result) < 1){
				$new_batch = (object) array("job_name" => "findjobs", "start_page" => 1, "end_page" => self::BATCH_SIZE);
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
			$file_name = public_path('data').self::SLASH.self::FINDJOBS_DATA_PATH.self::SLASH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv';
			MFindJobsCrawler::AppendStringToFile(substr($e -> getMessage (), 0, 1000), $file_name);
		}
		return null;
	}
}