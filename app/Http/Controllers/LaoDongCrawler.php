<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class LaoDongCrawler extends Controller{

	const TABLE = "crawler_laodong";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "laodong";
	const LAODONG_DATA_PATH = 'laodong';
	const LAODONG_DATA = 'laodong-data';
	const LAODONG_ERROR = 'laodong-error-';
	const LAODONG_LINK = 'laodong-link';
	const LAODONG_HOME = 'http://vieclam.laodong.com.vn';
	const LAODONG_PAGE = 'http://vieclam.laodong.com.vn/tim-kiem-ky-tuyen-dung.html?page=';
	const LABEL_SALARY = 'Mức lương:';
	const LABEL_QUANTITY = 'Số lượng cần tuyển:';
	const LABEL_DEADLINE = "Hạn nộp hồ sơ:";
	const DATE_FORMAT = "Ymd";
	const DATA_DATE_FORMAT = "Y-m-d";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 500;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling LAODONG ...");

		$client = new Client();
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->LaoDongCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				if ($return_code > 1) {
					break;
				}
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::LAODONG_DATA_PATH.self::SLASH.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function LaoDongCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::LAODONG_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::LAODONG_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('header.job > h3.name > a');
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::LAODONG_DATA_PATH.self::SLASH.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv';
								Common::AppendStringToFile('Exception on getting job_link: '.substr($e -> getMessage (), 0, 1000), $file_name);
							}
						}
					);
					// select duplicated records
					$existing_links = Common::CheckLinksExist($jobs_links, env("DATABASE"), self::TABLE);
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
										$full_link = self::LAODONG_HOME.$job_link;
										$code = $this->CrawlJob($client, $full_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($full_link
												, $DATA_PATH.self::LAODONG_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('LaoDongCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::LAODONG_DATA_PATH.self::SLASH.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv';
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
		$job_start = microtime(true);
		
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			Common::AppendStringToFile("Cannot request page: ".$url.": ".substr($e -> getMessage (), 0, 1000)
				, $data_path.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}
		echo 'Time= '.(microtime(true) - $job_start).' secs<br>';

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			//featured-info
			$featured_info_crl = $crawler -> filter('div.featured-info-content > div.info');
			if($featured_info_crl -> count() <= 0){
				Common::AppendStringToFile("No featured-info-content: ".$url
					, $data_path.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}

			$job_title = "";
			$job_title_crl = $featured_info_crl -> filter('#cphMainContent_lblTitle');
			if ($job_title_crl->count() > 0){
				$job_title = $job_title_crl -> text();
			}

			$company = "";
			$company_crl = $featured_info_crl -> filter('p.building > strong');
			if ($company_crl->count() > 0){
				$company = $company_crl -> text();
			}
			$company = Common::RemoveTrailingChars($company);

			$address = "";
			$address_crl = $featured_info_crl -> filter('p.addres > span');
			if ($address_crl->count() > 0){
				$address = $address_crl -> text();
			}
			$address = Common::RemoveTrailingChars($address);

			$mobile = "";
			$mobile_crl = $featured_info_crl -> filter('p.phone > span');
			if ($mobile_crl->count() > 0){
				$mobile = $mobile_crl -> text();
			}
			$mobile = Common::RemoveTrailingChars($mobile);
			$mobile = Common::ExtractFirstMobile($mobile);

			$website = "";
			$website_crl = $featured_info_crl -> filter('p.website > a');
			if ($website_crl->count() > 0){
				$website = $website_crl -> text();
			}

			$basic_info_crl = $crawler -> filter('div.basic-info');
			if($basic_info_crl -> count() <= 0){
				Common::AppendStringToFile("No basic-info: ".$url
					, $data_path.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}

			$job_des_crl = $basic_info_crl -> filter('#cphMainContent_lblDescription');
			$job_des = "";
			if ($job_des_crl -> count() > 0){
				$job_des = $job_des_crl -> first() -> text();
				$job_des = Common::RemoveTrailingChars($job_des);
			}

			$salary = "";
			$salary_min = $basic_info_crl -> filter('#cphMainContent_lblMinSalary');
			$salary_max = $basic_info_crl -> filter('#cphMainContent_lblMaxSalary');
			if ($salary_min -> count() > 0){
				$salary_min = $salary_min -> first() -> text();
				$salary = $salary_min;
			}
			if ($salary_max -> count() > 0){
				$salary_max = $salary_max -> first() -> text();
				$salary = $salary.' - '.$salary_max;
			}
			$salary = Common::RemoveTrailingChars($salary);
			if ($salary === ""){
				$salary = $basic_info_crl -> filter('#cphMainContent_pnNegotiableSalary') -> filter('span.pull-right');
				if ($salary -> count() > 0){
					$salary = Common::RemoveTrailingChars($salary -> text());
				}
			}

			$mobile = "";
			$mobile = $basic_info_crl -> filter('#cphMainContent_lblContactPhone');
			if ($mobile -> count() > 0){
				$mobile = $mobile -> first() -> text();
				$mobile = Common::ExtractFirstMobile($mobile);
			}

			$email = "";
			$email = $basic_info_crl -> filter('#cphMainContent_lblContactEmail');
			if ($email -> count() > 0){
				$email = $email -> first() -> text();
			}
			$email = Common::RemoveTrailingChars($email);
			
			$deadline = "";
			$deadline = $basic_info_crl -> filter('#cphMainContent_lblExpiryDate');
			if ($deadline -> count() > 0){
				$deadline = $deadline -> first() -> text();
			}
			
			$created = "";
			$soluong = "";
			
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
			Common::AppendArrayToFile($job_data
				, $data_path.self::LAODONG_DATA.'.csv', "|");
			return 0;
		}
	}
	
}