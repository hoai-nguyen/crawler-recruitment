<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class TuyenCongNhanCrawler extends Controller{

	const TABLE = "crawler_tuyencongnhan";
	const TABLE_METADATA = "job_metadata";
	const TABLE_FILE_METADATA = "job_file_index";
	const JOB_NAME = "tuyencongnhan";
	const TUYENCONGNHAN_DATA_PATH = 'tuyencongnhan';
	const TUYENCONGNHAN_DATA = 'tuyencongnhan-data';
	const TUYENCONGNHAN_DATA_NO_CONTACT = 'tuyencongnhan-data-no-contact';
	const TUYENCONGNHAN_ERROR = 'tuyencongnhan-error-';
	const TUYENCONGNHAN_LINK = 'tuyencongnhan-link';
	const TUYENCONGNHAN_HOME = 'https://tuyencongnhan.vn';
	const TUYENCONGNHAN_PAGE = 'https://tuyencongnhan.vn/tim-viec?page=';
	const LABEL_SALARY = 'Mức lương';
	const LABEL_TYPE_OF_WORK = 'Giờ làm việc';
	const LABEL_QUANTITY = 'Số lượng';
	const LABEL_DEADLINE = "Hạn nộp";
	const LABEL_PHONE = "Điện thoại:";
	const LABEL_ADDRESS = "Địa chỉ:";
	const LABEL_WEBSITE = "Website:";
	const DATE_FORMAT = "Ymd";
	const DATA_DATE_FORMAT = "Y-m-d";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 500;

	static $file_index = 0;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling TUYENCONGNHAN ...");

		$database = env("DB_DATABASE");
		if ($database == null)  $database = Common::DB_DEFAULT;
		self::$file_index = Common::GetFileIndexToProcess($database, self::TABLE_FILE_METADATA, self::JOB_NAME);
		
		$client = new Client();
		while (true){
			try {
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->TimViec365CrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				
				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::TUYENCONGNHAN_DATA_PATH.self::SLASH.self::TUYENCONGNHAN_ERROR.date(self::DATE_FORMAT).'.csv';
				Common::AppendStringToFile('Exception on starter: '.substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}
		}
		
		Common::UpdateFileIndexAfterProcess($database, self::TABLE_FILE_METADATA, self::JOB_NAME);

		$time_elapsed_secs = microtime(true) - $start;
		error_log('Total Execution Time: '.$time_elapsed_secs.' secs');
		error_log("DONE!");

		echo '<b>Total Execution Time:</b> '.$time_elapsed_secs.' secs<br>';
		echo "DONE!";
	}
	
	public function TimViec365CrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::TUYENCONGNHAN_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::TUYENCONGNHAN_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('#search-job') -> filter('p.job-title > a');
				
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::TUYENCONGNHAN_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::TUYENCONGNHAN_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::TUYENCONGNHAN_DATA_PATH.self::SLASH.self::TUYENCONGNHAN_ERROR.date(self::DATE_FORMAT).'.csv';
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
										$full_link = self::TUYENCONGNHAN_HOME.$job_link;
										$code = $this->CrawlJob($client, $full_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($full_link
												, $DATA_PATH.self::TUYENCONGNHAN_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::TUYENCONGNHAN_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('TimViec365CrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::TUYENCONGNHAN_DATA_PATH.self::SLASH.self::TUYENCONGNHAN_ERROR.date(self::DATE_FORMAT).'.csv';
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
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			Common::AppendStringToFile("Cannot request page: ".$url.": ".substr($e -> getMessage (), 0, 1000)
				, $data_path.self::TUYENCONGNHAN_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::TUYENCONGNHAN_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$job_title = "";
			$job_title_crl = $crawler -> filter('div.main-heading > h1');
			if ($job_title_crl->count() > 0){
				$job_title = $job_title_crl -> text();
			}
			$job_title = Common::RemoveTrailingChars($job_title);

			$job_des_crl = $crawler -> filter('div.mo-ta-cong-viec');
			$job_des = "";
			if ($job_des_crl -> count() > 0){
				$job_des = $job_des_crl -> text();
				$job_des = Common::RemoveTrailingChars($job_des);
			}

			$job_overview_crl = $crawler -> filter("div.job-overview > div.job-overview-item") -> filter("p");
			$salary = "";
			$deadline = "";
			$soluong = "";
			if ($job_overview_crl->count() > 0){
				$count = 0;
				foreach($job_overview_crl as $item){
					if ($count > 3) break;
					$node = new Crawler($item);
					$text = $node -> text();
					if (strpos($text, self::LABEL_DEADLINE) !== false){
						$deadline = $text;
						$deadline = str_replace(self::LABEL_DEADLINE, '', $deadline);
						$deadline = Common::RemoveTrailingChars($deadline);
						$count++;
					} else if (strpos($text, self::LABEL_SALARY) !== false){
						$salary = $text;
						$salary = str_replace(self::LABEL_SALARY, '', $salary);
						$salary = Common::RemoveTrailingChars($salary);
						$count++;
					} else if (strpos($text, self::LABEL_QUANTITY) !== false){
						$soluong = $text;
						$soluong = str_replace(self::LABEL_QUANTITY, '', $soluong);
						$soluong = Common::RemoveTrailingChars($soluong);
						$count++;
					} else if (strpos($text, self::LABEL_TYPE_OF_WORK) !== false){
						$type_of_work = str_replace(self::LABEL_TYPE_OF_WORK, '', $text);
						$type_of_work = Common::RemoveTrailingChars($type_of_work);
						$count++;
					} 
				}
			}
			if (Common::IsJobExpired(Common::DEFAULT_DEADLINE, $deadline)){
				return 2;
			}
			
			$employer_crl = $crawler -> filter("#tab-employer-info > div.tab-body > div.text-left");
			if ($employer_crl -> count() <= 0){
				Common::AppendStringToFile("No job-detail: ".$url
					, $data_path.self::TUYENCONGNHAN_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}

			$company = "";
			$company_crl = $employer_crl -> filter("h4");
			if ($company_crl -> count() > 0){
				$company = $company_crl -> text();
				$company = Common::RemoveTrailingChars($company);
			}

			$contact_crl = $employer_crl -> filter("p");
			$website = "";
			$mobile = "";
			$address = "";
			if ($contact_crl -> count() > 0){
				foreach($contact_crl as $item){
					$node = new Crawler($item);
					$text = $node -> text();
					if (strpos($text, self::LABEL_ADDRESS) !== false){
						$address = str_replace(self::LABEL_ADDRESS, '', $text);
						$address = Common::RemoveTrailingChars($address);
					} else if (strpos($text, self::LABEL_PHONE) !== false){
						$mobile = Common::ExtractFirstMobile($text);
					} else if (strpos($text, self::LABEL_WEBSITE) !== false){
						$website = Common::ExtractWebsiteFromText($text);
					}				
				}
			}

			$mobile_crl = $crawler -> filter("div.other");
			if ($mobile_crl -> count() > 0){
				$mobile_text = $mobile_crl -> text();
				$sentences = explode("\n", $mobile_text);
				foreach($sentences as $sentence){
					if(strpos($sentence, self::LABEL_PHONE) !== false){
						$mobile = Common::ExtractFirstMobile($sentence);
						break;
					}
				}
			}

			$email = "";
			$email_crl = $crawler -> filter("div.content-job-detail");
			foreach($email_crl as $item){
				$node = new Crawler($item);
				$text = $node -> text();
				$email = Common::ExtractEmailFromText($text);
				if ($email !== "") break;
			}

			$created = "";
			
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
				, $type_of_work
				, $website
				// , $url
			);
			if (Common::IsNullOrEmpty($email) and (Common::IsNullOrEmpty($mobile) or Common::isNotMobile($mobile))){
				Common::AppendArrayToFile($job_data, $data_path.self::TUYENCONGNHAN_DATA_NO_CONTACT.'.csv', "|");
			} else{
				if (Common::isNotMobile($mobile)){
					$job_data[0] = "";
				}
				Common::AppendArrayToFile($job_data, $data_path.self::TUYENCONGNHAN_DATA.'.csv', "|");
				Common::AppendArrayToFile($job_data, $data_path.self::TUYENCONGNHAN_DATA.'-'.self::$file_index.'.csv', "|");
			}
			return 0;
		}
	}
	
}