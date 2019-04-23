<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class HatalikeCrawler extends Controller{

	const TABLE = "crawler_hatalike";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "hatalike";
	const HATALIKE_DATA_PATH = 'hatalike';
	const HATALIKE_DATA = 'hatalike-data';
	const HATALIKE_DATA_NO_CONTACT = 'hatalike-data-no-contact';
	const HATALIKE_ERROR = 'hatalike-error-';
	const HATALIKE_LINK = 'hatalike-link';
	const HATALIKE_HOME = 'https://www.hatalike.jp';
	const HATALIKE_PAGE = 'https://www.hatalike.jp/PBL_05';
	const HATALIKE_POSTFIX = '/?SCA=05&st=P03';
	const LABEL_SALARY = "給与";
	const LABEL_QUANTITY = "採用予定人数";
	const LABEL_DESCRIPTION = "職種/仕事内容";
	const LABEL_MOBILE = "連絡先/担当者";
	const LABEL_ADDRESS = "勤務地";
	const LABEL_WEBSITE = "企業ホームページ";
	const DATE_FORMAT = "Ymd";
	const DATE_REGEX = '/\d{4}\/\d{2}\/\d{2}/';
	const INPUT_DATE_FORMAT = "Y/m/d";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE =  1000;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling hatalike.mynavi.jp ...");

		$client = new Client(); 
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this -> HatalikeCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				
				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;

			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::HATALIKE_DATA_PATH.self::SLASH.self::HATALIKE_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function HatalikeCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::HATALIKE_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$step = 30*($x - 1);
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";
			
			try{
				if ($step  == 0){
					$pageUrl = self::HATALIKE_PAGE.self::HATALIKE_POSTFIX;
				} else{
					$pageUrl = self::HATALIKE_PAGE.'/'.$step.self::HATALIKE_POSTFIX;
				}
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('#leftCol') -> filter('p.lead > a');
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::HATALIKE_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::HATALIKE_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::HATALIKE_DATA_PATH.self::SLASH.self::HATALIKE_ERROR.date(self::DATE_FORMAT).'.csv';
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
										$code = $this->CrawlJob($client, $job_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($job_link
												, $DATA_PATH.self::HATALIKE_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::HATALIKE_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('HatalikeCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::HATALIKE_DATA_PATH.self::SLASH.self::HATALIKE_ERROR.date(self::DATE_FORMAT).'.csv';
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
		$page_start = microtime(true);
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			Common::AppendStringToFile("Cannot request page: ".$url.": ".substr($e -> getMessage (), 0, 1000)
				, $data_path.self::HATALIKE_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::HATALIKE_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$created = "";
			$deadline = "";
			$period_crl = $crawler -> filter("p.period");
			if ($period_crl -> count() > 0){
				$text = $period_crl -> first() -> text();
				preg_match_all(self::DATE_REGEX,$text,$matches);
				if (sizeof($matches) > 0){
					$created = $matches[0][0];
					$deadline = $matches[0][1];
				}
			}
			$created = Common::RemoveTrailingChars($created);
			$deadline = Common::RemoveTrailingChars($deadline);
			$created = Common::ConvertDateFormat($created, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);
			$deadline = Common::ConvertDateFormat($deadline, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);
			if (Common::IsJobExpired(Common::DEFAULT_DEADLINE, $deadline)){
				return 2;
			}

			$job_title = "";
			$job_title_crl = $crawler -> filter('p.em01');
			if ($job_title_crl->count() > 0){
				$job_title = $job_title_crl -> first() -> text();
			} 
			$job_title = Common::RemoveTrailingChars($job_title);
			
			$salary = "";
			$mobile = "";
			$address = "";
			$job_des = "";
			$job_infos_crl = $crawler -> filter("table.design01") -> filter("tr");
			if ($job_infos_crl -> count() <= 0 ) {
				Common::AppendStringToFile("No job info: ".$url
					, $data_path.self::HATALIKE_ERROR.date(self::DATE_FORMAT).'.csv');
				return -1;
			}
			foreach($job_infos_crl as $node){
				$node = new Crawler($node);
				$label = $node -> children() -> first() -> text();
				$value = $node -> children() -> last() -> text();	

				if (strpos($label, self::LABEL_DESCRIPTION) !== false){
					$job_des = Common::RemoveTrailingChars($value);
				} else if (strpos($label, self::LABEL_SALARY) !== false){
					$salary = Common::RemoveTrailingChars($value);
				} else if (strpos($label, self::LABEL_ADDRESS) !== false){
					$address = Common::RemoveTrailingChars($value);
				} else if (strpos($label, self::LABEL_MOBILE) !== false){
					$mobile = Common::ExtractFirstMobile($value);
				} 
			}
			
			$company = "";
			$website = "";
			$company_name_crl = $crawler -> filter("div.rightCol > dl.companyInfo > dd");
			if ($company_name_crl -> count() > 0){
				$company = $company_name_crl -> first() -> text();
				$web_crl = $company_name_crl -> filter("a");
				if ($web_crl -> count() > 0){
					$website = $web_crl -> first() -> attr("href");
				}
			}

			$email = "";
			$soluong = "";

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
				Common::AppendArrayToFile($job_data, $data_path.self::HATALIKE_DATA_NO_CONTACT.'.csv', "|");
			} else{
				Common::AppendArrayToFile($job_data, $data_path.self::HATALIKE_DATA.'.csv', "|");
				Common::AppendArrayToFile($job_data, $data_path.self::HATALIKE_DATA.'-'.date(self::DATE_FORMAT).'.csv', "|");
			}
			return 0;
		}
	}
	
}