<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class TenshokuExCrawler extends Controller{

	const TABLE = "crawler_tenshokuex";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "tenshokuex";
	const TENSHOKU_DATA_PATH = 'tenshokuex';
	const TENSHOKU_DATA = 'tenshokuex-data';
	const TENSHOKU_DATA_NO_CONTACT = 'tenshokuex-data-no-contact';
	const TENSHOKU_ERROR = 'tenshokuex-error-';
	const TENSHOKU_LINK = 'tenshokuex-link';
	const TENSHOKU_HOME = 'https://tenshoku-ex.jp/';
	const TENSHOKU_PAGE = 'https://tenshoku-ex.jp/new?page=';
	const LABEL_SALARY = "給与";
	const LABEL_QUANTITY = "採用予定人数";
	const LABEL_DESCRIPTION = "仕事内容";
	const LABEL_MOBILE = "電話番号";
	const LABEL_ADDRESS = "勤務地";
	const LABEL_WEBSITE = "URL";
	const LABEL_COMPANY = "会社名称";
	const DATE_FORMAT = "Ymd";
	const DATE_REGEX = '/\d{4}\/\d{2}\/\d{2}/';
	const INPUT_DATE_FORMAT = "Y/m/d";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 500;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling tenshokuex.mynavi.jp ...");

		$client = new Client(); 
		// $DATA_PATH = public_path('data').self::SLASH.self::TENSHOKU_DATA_PATH.self::SLASH;
		// $this -> CrawlJob($client, "", $DATA_PATH);
		// dd("STOP");
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this -> TenshokuCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				
				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;

			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::TENSHOKU_DATA_PATH.self::SLASH.self::TENSHOKU_ERROR.date(self::DATE_FORMAT).'.csv';
				Common::AppendStringToFile('Exception on starter: '.substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}
		}
		
		$time_elapsed_secs = microtime(true) - $start;
		error_log('Total Execution Time: '.$time_elapsed_secs.' secs');
		error_log("DONE!");

		echo '<b>Total Execution Time:</b> '.$time_elapsed_secs.' secs<br>';
		echo "DONE!";
	}
	
	public function TenshokuCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::TENSHOKU_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::TENSHOKU_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('div.job_box_btn_unit > a.btn_action');
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::TENSHOKU_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::TENSHOKU_ERROR.date(self::DATE_FORMAT).'.csv');
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
									$pieces = explode("/", $job_link);
									if (sizeof($pieces) > 1){
										return $pieces[1];
									} else{
										return $job_link;
									}
								}
							} catch (\Exception $e) {
								$file_name = public_path('data').self::SLASH.self::TENSHOKU_DATA_PATH.self::SLASH.self::TENSHOKU_ERROR.date(self::DATE_FORMAT).'.csv';
								Common::AppendStringToFile('Exception on getting job_link: '.substr($e -> getMessage (), 0, 1000), $file_name);
							}
						}
					);
					// select duplicated records
					$existing_links = Common::CheckLinksExist($jobs_links, env("DB_DATABASE"), self::TABLE);
					$duplicated_links = array();
					if ($existing_links != null){
						foreach($existing_links as $row){
							$link = $row -> link;
							array_push($duplicated_links, $link);
						}
					}
					
					// deduplicate
					$new_links = array_diff($jobs_links, $duplicated_links);
					if (is_array($new_links) and sizeof($new_links) > 0){
						error_log(sizeof($new_links)." new links.");

						$inserted = Common::InsertLinks($new_links, env("DB_DATABASE"), self::TABLE);
						if ($inserted){
							// crawl each link
							foreach ($new_links as $job_link) {
								try {
									ini_set('max_execution_time', 10000000);				

									if ($job_link != null){
										$full_link = self::TENSHOKU_HOME.$job_link;
										$code = $this->CrawlJob($client, $full_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($full_link
												, $DATA_PATH.self::TENSHOKU_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::TENSHOKU_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('TenshokuCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::TENSHOKU_DATA_PATH.self::SLASH.self::TENSHOKU_ERROR.date(self::DATE_FORMAT).'.csv';
				Common::AppendStringToFile("Exception on page = ".$x.": ".substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}
			$x++;
			if ($x > self::MAX_PAGE){ 
				return 3;
			}
			$page_total_time = microtime(true) - $page_start;
			echo '<b>Total execution time of page '.$x.":</b> ".$page_total_time.' secs<br>';
		} 
		return $return_code;
	}

    public function CrawlJob($client, $url, $data_path){
		// $url = "https://tenshoku-ex.jp/job_19694752";

		$page_start = microtime(true);
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			Common::AppendStringToFile("Cannot request page: ".$url.": ".substr($e -> getMessage (), 0, 1000)
				, $data_path.self::TENSHOKU_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::TENSHOKU_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$job_title = "";
			$job_title_crl = $crawler -> filter('h1.box_job_ttl');
			if ($job_title_crl->count() > 0){
				$job_title = $job_title_crl -> first() -> text();
			} else {
				return -1;
			}
			$job_title = Common::RemoveTrailingChars($job_title);

			$job_des = "";
			$job_des_crl = $crawler -> filter("div.box_job_section_main");
			if ($job_des_crl->count() > 0){
				$job_des = $job_des_crl -> first() -> text();
				$job_des = Common::RemoveTrailingChars($job_des);
			}

			$salary = "";
			$address = "";
			$soluong = "";
			$website = "";
			$company = "";
			$job_infos_crl = $crawler -> filter("table.table_job_data") -> filter("tr");
			foreach($job_infos_crl as $node){
				$node = new Crawler($node);
				$label = $node -> filter("th") -> text();
				$value = $node -> filter("td") -> text();	
				if (strpos($label, self::LABEL_ADDRESS) !== false){
					$address = $value;
				} else if (strpos($label, self::LABEL_SALARY) !== false){
					$salary = Common::RemoveTrailingChars($value);
				} else if (strpos($label, self::LABEL_COMPANY) !== false){
					$company = Common::RemoveTrailingChars($value);
				} else if (strpos($label, self::LABEL_WEBSITE) !== false){
					$website = Common::RemoveTrailingChars($value);
				}
			}

			$email = "";
			$soluong = "";
			$mobile = "";
			$created = "";
			$deadline = "";

			$job_data = array($mobile
				, $email
				, $company
				, $address
				, $job_title
				, $salary
				, $job_des
                , $created
                , $deadline
				, $soluong
				, $website
				// , $url
			);
			if (Common::IsNullOrEmpty($email) and Common::IsNullOrEmpty($mobile)){
				Common::AppendArrayToFile($job_data, $data_path.self::TENSHOKU_DATA_NO_CONTACT.'.csv', "|");
			} else{
				Common::AppendArrayToFile($job_data, $data_path.self::TENSHOKU_DATA.'.csv', "|");
				Common::AppendArrayToFile($job_data, $data_path.self::TENSHOKU_DATA.'-'.date(self::DATE_FORMAT).'.csv', "|");
			}
			return 0;
		}
	}
	
}