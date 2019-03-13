<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;
use App\Http\Controllers\Common;
use \DateTime;

class CareerLinkCrawler extends Controller{

	const TABLE = "careerlink";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "careerlink";
	const CAREERLINK_DATA_PATH = 'careerlink';
	const CAREERLINK_DATA = 'careerlink-data';
	const CAREERLINK_ERROR = 'careerlink-error-';
	const CAREERLINK_LINK = 'careerlink-link';
	const CAREERLINK_HOME = 'https://www.careerlink.vn';
	const CAREERLINK_PAGE = 'https://www.careerlink.vn/vieclam/list?page=';
	const LABEL_CONTACT = "Người liên hệ";
	const LABEL_ADDRESS = "Địa chỉ liên hệ";
	const DATE_FORMAT = "Ymd";
	const INPUT_DATE_FORMAT = "Y-m-d";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 500;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling CareerLink ...");

		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->CareerLinkPageCrawler($new_batch -> start_page, $new_batch -> end_page);

				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::CAREERLINK_DATA_PATH.self::SLASH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv';
				Common::AppendStringToFile('Exception on starter: '.substr($e -> getMessage(), 0, 1000), $file_name);
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
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::CAREERLINK_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				
				$jobs = $crawler -> filter('div.list-search-result-group > div.list-group-item > h2.list-group-item-heading > a');
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::CAREERLINK_DATA_PATH.self::SLASH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv';
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
										$full_link = self::CAREERLINK_HOME.$job_link;
										$crawled = $this->CrawlJob($full_link, $DATA_PATH);
										if ($crawled == 0){
											Common::AppendStringToFile($full_link
												, $DATA_PATH.self::CAREERLINK_LINK.'.csv');
										}
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('CareerLinkCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::CAREERLINK_DATA_PATH.self::SLASH.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
    public function CrawlJob($url, $data_path){
		// $job_start = microtime(true);
		$client = new Client;
		// echo 'create client: '.(microtime(true) - $job_start).' secs, ';
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			Common::AppendStringToFile('Exception on crawling job: '.$url.': '.$e -> getMessage()
				, $data_path.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}
		
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';
		// $content_crawler = $crawler -> filter('div.body-container');
		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Job expired. No job content:  ".$url
				, $data_path.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
			return 2;
		} else{
			$header_crl = $crawler -> filter('div.body-container > div.job-header');
			if ($header_crl -> count() <= 0){
				Common::AppendStringToFile("Job expired. No div.job-header:  ".$url
				, $data_path.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
				return 3;
			}
			$title_crl = $header_crl -> filter('h1 > span');
			if ($title_crl -> count() <= 0){
				Common::AppendStringToFile("Job expired. No title:  ".$url
				, $data_path.self::CAREERLINK_ERROR.date(self::DATE_FORMAT).'.csv');
				return 4;
			}
			$job_title = $title_crl -> first() -> text();
			$job_title = trim($job_title, "\r\n- ");
			
			$job_data_crl = $crawler -> filter('div.job-data');
			if($job_data_crl -> count() <= 0){
				Common::AppendStringToFile("No job-data:  ".$url
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
							$email = Common::ExtractEmailFromText($li_text);
						} else if (strpos($li_text, 'Điện thoại') !== false){
							$mobile = Common::ExtractFirstMobile($li_text);
						}
					}
				}
			}
			// $contact = str_replace('Tên liên hệ:', '', $contact); //todo separate
			$contact = preg_replace('!\s+!', ' ', $contact);
			$contact = trim($contact, "\r\n- ");
			if (strcmp($email,"") == 0){
				$email = Common::ExtractEmailFromText($contact);
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
			$created = Common::ConvertDateFormat($created, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);
			$deadline = Common::ConvertDateFormat($deadline, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);

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

			Common::AppendArrayToFile($job_data , $data_path.self::CAREERLINK_DATA.'.csv', "|");
			return 0;
		}
	}

}
