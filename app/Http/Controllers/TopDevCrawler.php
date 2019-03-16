<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class TopDevCrawler extends Controller{

	const TABLE = "topdev";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "topdev";
	const TOPDEV_DATA_PATH = 'topdev';
	const TOPDEV_DATA = 'topdev-data';
	const TOPDEV_DATA_NO_CONTACT = 'topdev-data-no-contact';
	const TOPDEV_ERROR = 'topdev-error-';
	const TOPDEV_LINK = 'topdev-link';
	const TOPDEV_HOME = 'https://topdev.vn';
	const TOPDEV_PAGE = 'https://topdev.vn/it-jobs/?page=';
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
		error_log("Start crawling TOPDEV ...");

		$client = new Client();
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->TopDevCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				if ($return_code > 1) {
					break;
				}
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::TOPDEV_DATA_PATH.self::SLASH.self::TOPDEV_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function TopDevCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::TOPDEV_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::TOPDEV_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('#job-list') -> filter('a.job-title');
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::TOPDEV_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::TOPDEV_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::TOPDEV_DATA_PATH.self::SLASH.self::TOPDEV_ERROR.date(self::DATE_FORMAT).'.csv';
								Common::AppendStringToFile('Exception on getting job_link: '.substr($e -> getMessage (), 0, 1000), $file_name);
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
										$code = $this->CrawlJob($client, $job_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($job_link
												, $DATA_PATH.self::TOPDEV_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::TOPDEV_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('TopCVCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::TOPDEV_DATA_PATH.self::SLASH.self::TOPDEV_ERROR.date(self::DATE_FORMAT).'.csv';
				Common::AppendStringToFile("Exception on page = ".$x.": ".substr($e -> getMessage (), 0, 1000), $file_name);
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

    public function CrawlJob($client, $url, $data_path){
		$job_start = microtime(true);
		
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			Common::AppendStringToFile("Cannot request page: ".$url.": ".substr($e -> getMessage (), 0, 1000)
				, $data_path.self::TOPDEV_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::TOPDEV_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$job_details_crl = $crawler -> filter('#image-employer');
			if($job_details_crl -> count() <= 0){
				Common::AppendStringToFile("No #image-employer: ".$url
					, $data_path.self::TOPDEV_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}

			$job_title = $job_details_crl -> filter('h1.job-title') -> text();
			
			$company = $job_details_crl -> filter('div.job-header-info > span.company-name') -> text();
			$company = Common::RemoveTrailingChars($company);

			$address_crl = $job_details_crl -> filter('span.company-address');
			if ($address_crl -> count() <= 0){
				Common::AppendStringToFile("No div.text-dark-gray: ".$url
					, $data_path.self::TOPDEV_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}
			$address = $address_crl -> first() -> text();
			$address = Common::RemoveTrailingChars($address);

			$salary = "";
			$salary_crl = $job_details_crl -> filter('div.row-salary') -> filter('span.orange');
			if ($salary_crl -> count() <= 0){
				Common::AppendStringToFile("No div.row-salary: ".$url
					, $data_path.self::TOPDEV_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}
			$salary = $salary_crl -> first() -> text();
			$salary = Common::RemoveTrailingChars($salary);
			
			$job_des_crl = $crawler -> filter('#job-description');
			$job_des = "";
			if ($job_des_crl -> count() > 0){
				$job_des = $job_des_crl -> text();
				$job_des = Common::RemoveTrailingChars($job_des);
			}

			$company_info_crl = $crawler -> filter('div.basic-info');
			$website = "";
			if ($company_info_crl -> count() > 0){
				$company_info = $company_info_crl -> first() -> text();
				$website = Common::ExtractWebsiteFromText($company_info);
			}

			$created = "";
			$mobile = "";
			$email = "";
			$soluong = "";
			$deadline = "";
			// $contact = "";
			
			
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
			if (Common::IsNullOrEmpty($email) and (Common::IsNullOrEmpty($mobile) or Common::isNotMobile($mobile))){
				Common::AppendArrayToFile($job_data, $data_path.self::TOPDEV_DATA_NO_CONTACT.'.csv', "|");
			} else{
				Common::AppendArrayToFile($job_data, $data_path.self::TOPDEV_DATA.'.csv', "|");
			}
			return 0;
		}
	}
	
}