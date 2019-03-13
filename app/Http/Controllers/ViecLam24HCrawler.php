<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class ViecLam24HCrawler extends Controller{

	const TABLE = "vieclam24h";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "vieclam24h";
	const VIECLAM24H_DATA_PATH = 'vieclam24h'; 
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
	const MAX_PAGE = 150;
	const EMAIL_PATTERN = "/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i";
	const PHONE_PATTERN = "!\d+!";

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling ViecLam24H ...");

		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;

				$return_code = $this->ViecLam24HPageCrawler($new_batch -> start_page, $new_batch -> end_page);

				if ($return_code > 1) break;

				if($new_batch -> start_page >= self::MAX_PAGE) break;

			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::VIECLAM24H_DATA_PATH.self::SLASH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv';
				Common::AppendStringToFile('Ex on starter: '.substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}
		}

		$time_elapsed_secs = microtime(true) - $start;
		error_log('Total Execution Time: '.$time_elapsed_secs.' secs');
		error_log("DONE!");

		echo '<b>Total Execution Time:</b> '.$time_elapsed_secs.' secs<br>';
		echo "DONE!";
	}

    public function ViecLam24HPageCrawler($start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::VIECLAM24H_DATA_PATH.self::SLASH;
        	$client = new Client;
		
		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::VIECLAM24H_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('span.title-blockjob-main > a');

				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::VIECLAM24H_DATA_PATH.self::SLASH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv';
								Common::AppendStringToFile('Ex when getting $job_links: '.substr($e -> getMessage (), 0, 1000), $file_name);
							}
						}
					);

					// select duplicated records
					$existing_links = Common::CheckLinksExist($jobs_links, env("DB_DATABASE"), self::TABLE);
					$duplicated_links = array();
					foreach($existing_links as $row){
						$link = $row -> link;
						array_push($duplicated_links, $link);
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
									
									if ($job_link == null){
									} else{
										$full_link = self::VIECLAM24H_HOME.$job_link;
										$crawled = $this->CrawlJob($full_link, $DATA_PATH);
										if ($crawled == 0){
											Common::AppendStringToFile($full_link
												, $DATA_PATH.self::VIECLAM24H_LINK.'.csv');
										}
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on link:".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('TopCVCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::VIECLAM24H_DATA_PATH.self::SLASH.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
    public function CrawlJob($url, $data_path){
		// $job_start = microtime(true);
		$client = new Client;
		// echo 'create client: '.(microtime(true) - $job_start).' secs, ';
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			Common::AppendStringToFile('Exception on crawling job: '.$url.': '.$e -> getMessage()
				, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}
		
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';
		$content_crawler = $crawler -> filter('#cols-right');
		if ($content_crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Job expired. No content cols-right:  ".$url
				, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
			return 2;
		} else{
			$content = $content_crawler -> filter('div.box_chi_tiet_cong_viec');
			if ($content -> count() <= 0){
				Common::AppendStringToFile("Job expired. No box_chi_tiet_cong_viec:  ".$url
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
					Common::AppendStringToFile("Job expired. No title:  ".$url
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
				// $deadline = Common::ConvertDateFormat($deadline, "d/m/y", Common::DATE_DATA_FORMAT);

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
					Common::AppendStringToFile('Exception on getting created date: '.$url.': '.$e -> getMessage()
						, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
				}
				// $created = Common::ConvertDateFormat($created, "d/m/Y", Common::DATE_DATA_FORMAT);

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
					Common::AppendStringToFile('Exception on getting salary + soluong: '.$url.': '.$e -> getMessage()
						, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
				}
			}

			//description + contact
			$detail_crl = $content_crawler -> filter('#ttd_detail > div.job_description');
			if ($detail_crl -> count() <= 0){
				Common::AppendStringToFile('Job expired. No job_description: '.$url.': '.$e -> getMessage()
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
				Common::AppendStringToFile('Exception on getting job_des: '.$url.': '.$e -> getMessage()
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
				Common::AppendStringToFile('Exception on getting contact + address: '.$url.': '.$e -> getMessage()
					, $data_path.self::VIECLAM24H_ERROR.date(self::DATE_FORMAT).'.csv');
			}

			$mobile = Common::ExtractFirstMobile($contact);
			$email = Common::ExtractEmailFromText($contact);

			// $file_start = microtime(true);
			$job_data = array($mobile
				, $email
				// , $contact
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
			
			Common::AppendArrayToFile($job_data , $data_path.self::VIECLAM24H_DATA.'.csv', "|");
			return 0;
		}
		return 1;
	}
	
}
