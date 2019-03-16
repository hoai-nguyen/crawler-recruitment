<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class ITGuruCrawler extends Controller{

	const TABLE = "crawler_itguru";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "itguru";
	const ITGURU_DATA_PATH = 'itguru';
	const ITGURU_DATA = 'itguru-data';
	const ITGURU_DATA_NO_CONTACT = 'itguru-data-no-contact';
	const ITGURU_ERROR = 'itguru-error-';
	const ITGURU_LINK = 'itguru-link';
	const ITGURU_HOME = 'https://itguru.vn';
	const ITGURU_PAGE = 'https://itguru.vn/search-results-jobs/?page=';
	const LABEL_SALARY = 'Lương:';
	const LABEL_DEADLINE = "Ngày hết hạn:";
	const LABEL_DESCRIPTION = "Mô tả công việc:";
	const DATE_FORMAT = "Ymd";
	const INPUT_DATE_FORMAT = "d-m-Y";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 50;
	const PATTERN_DATE = '/\d{2}\-\d{2}\-\d{4}/';

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling ITGURU.VN ...");

		$client = new Client(); 
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->ITGuruCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				
				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::ITGURU_DATA_PATH.self::SLASH.self::ITGURU_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function ITGuruCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::ITGURU_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::ITGURU_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('#new_list__IT') -> filter('div.name_company > h3 > a');

				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::ITGURU_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::ITGURU_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::ITGURU_DATA_PATH.self::SLASH.self::ITGURU_ERROR.date(self::DATE_FORMAT).'.csv';
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

									if ($job_link == null){
									} else{
										$code = $this->CrawlJob($client, $job_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($job_link
												, $DATA_PATH.self::ITGURU_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::ITGURU_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('ITGuruCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::ITGURU_DATA_PATH.self::SLASH.self::ITGURU_ERROR.date(self::DATE_FORMAT).'.csv';
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
				, $data_path.self::ITGURU_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::ITGURU_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$job_title = "";
			$job_title_crl = $crawler -> filter('div.listingInfo > h2');
			if ($job_title_crl->count() > 0){
				$job_title = $job_title_crl -> first() -> text();
			} else {
				return -1;
			}

			$salary = "";
			$deadline = "";
			$job_des = "";
			$job_infos_crl = $crawler -> filter("div.displayFieldBlock");
			if ($job_infos_crl->count() > 0){
				foreach($job_infos_crl as $item){
					$node = new Crawler($item);
					$label_crl = $node -> filter('h3');
					if ($label_crl -> count() > 0){
						$label = $label_crl -> first() -> text();
						$value = $node -> filter('div.displayField') -> first() -> text();
						if (strpos($label, self::LABEL_SALARY) !== false){
							$salary = $value;
							$salary = Common::RemoveTrailingChars($salary);
						} else if (strpos($label, self::LABEL_DEADLINE) !== false){
							$deadline = $value;
							$deadline = Common::RemoveTrailingChars($deadline);
						} else if (strpos($label, self::LABEL_DESCRIPTION) !== false){
							$job_des = $value;
							$job_des = Common::RemoveTrailingChars($job_des);
							break;
						}
					}
				}
			}
			
			$company = "";
			$mobile = "";
			$address = "";
			$company_info_crl = $crawler -> filter("div.compProfileInfo > div.comp-profile-content");
			if ($company_info_crl->count() > 0){
				$company_crl = $company_info_crl -> filter("span");
				if ($company_crl -> count() > 0){
					$company = $company_crl -> first() -> text();
				}
				if ($company_crl -> count() > 2){
					$mobile = $company_crl -> last() -> text();
					$mobile = Common::ExtractFirstMobile($mobile);
				}
			
				$address = "";
				$company_info_crl -> filter('span')->each(
					function (Crawler $crawler) {
						foreach ($crawler as $node) {
							$node->parentNode->removeChild($node);
						}
					}
				);
				$address = $company_info_crl -> text();
				$address = Common::RemoveTrailingChars($address);
			}
			
			$email = "";
			$created = "";
			$soluong = "";
			$website = "";

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
				, $url
			);
			if (Common::IsNullOrEmpty($email) and (Common::IsNullOrEmpty($mobile) or Common::isNotMobile($mobile))){
				Common::AppendArrayToFile($job_data, $data_path.self::ITGURU_DATA_NO_CONTACT.'.csv', "|");
			} else{
				Common::AppendArrayToFile($job_data, $data_path.self::ITGURU_DATA.'.csv', "|");
			}
			return 0;
		}
	}
	
}