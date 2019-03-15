<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class TuyenDungSinhVienCrawler extends Controller{

	const TABLE = "crawler_tuyendungsinhvien";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "tuyendungsinhvien";
	const TUYENDUNGSINHVIEN_DATA_PATH = 'tuyendungsinhvien';
	const TUYENDUNGSINHVIEN_DATA = 'tuyendungsinhvien-data';
	const TUYENDUNGSINHVIEN_ERROR = 'tuyendungsinhvien-error-';
	const TUYENDUNGSINHVIEN_LINK = 'tuyendungsinhvien-link';
	const TUYENDUNGSINHVIEN_HOME = 'http://tuyendungsinhvien.com';
	const TUYENDUNGSINHVIEN_PAGE = 'http://tuyendungsinhvien.com/tim-viec-lam-';
	const LABEL_SALARY = 'Mức lương';
	const LABEL_QUANTITY = 'Số lượng';
	const LABEL_DEADLINE = "Hạn nộp hồ sơ";
	const LABEL_PHONE = "Điện thoại";
	const LABEL_MOBILE = "Điện thoại riêng";
	const LABEL_ADDRESS = "Địa chỉ";
	const LABEL_DESCRIPTION = "Chi tiết công việc";
	const LABEL_COMPANY = "Nhà tuyển dụng";
	const DATE_FORMAT = "Ymd";
	const INPUT_DATE_FORMAT = "d-m-Y";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 500;
	const PATTERN_DATE = '/\d{2}\-\d{2}\-\d{4}/';

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling TUYENDUNGSINHVIEN ...");

		$client = new Client();
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->TuyenDungSinhVienCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				
				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::TUYENDUNGSINHVIEN_DATA_PATH.self::SLASH.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function TuyenDungSinhVienCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::TUYENDUNGSINHVIEN_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::TUYENDUNGSINHVIEN_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('#joblist') -> filter('span.job_list_title > a.job_list_title');
				
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::TUYENDUNGSINHVIEN_DATA_PATH.self::SLASH.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv';
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
										// $full_link = self::TUYENDUNGSINHVIEN_HOME.$job_link;
										$code = $this->CrawlJob($client, $job_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($job_link
												, $DATA_PATH.self::TUYENDUNGSINHVIEN_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('TuyenDungSinhVienCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::TUYENDUNGSINHVIEN_DATA_PATH.self::SLASH.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv';
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
				, $data_path.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}
		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			if ($crawler -> filter('#jd') -> count() <= 0){
				Common::AppendStringToFile("No content: ".$url
					, $data_path.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv');
				return -1;
			}

			$header_crl = $crawler -> filter('div.yui-gc > div.yui-u');
			$job_title = "";
			$job_title_crl = $header_crl -> filter('div.jobTitle');
			if ($job_title_crl->count() > 0){
				$job_title = $job_title_crl -> text();
			}

			$created = "";
			$created_crl = $header_crl -> filter('#jd-date');
			if ($created_crl->count() > 0){
				$created = $created_crl -> text();
			}
			$created = Common::ExtractDateFromText(self::PATTERN_DATE, $created);
			$created = Common::ConvertDateFormat($created, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);

			$company = "";
			$website = "";
			$company_crl = $header_crl -> filter("span.empTitle > a");
			if ($company_crl -> count() > 0){
				$company = $company_crl -> text();
				$company = Common::RemoveTrailingChars($company);
				$website =  $company_crl -> attr("href");
			}
			
			$job_des = "";
			$salary = "";
			$deadline = "";
			$soluong = "";
			$mobile = "";
			$address = "";
			$job_details_crl = $crawler -> filter('#dynamic_form') -> filter('tr');
			if ($job_details_crl -> count() > 0){
				foreach($job_details_crl as $item){
					$node = new Crawler($item);
					$label = $node -> filter('td.dynamic_form_field');
					if ($label -> count() > 0){
						$text = $label -> first() -> text();
						$value = $node -> filter('td.dynamic_form_value') -> text();
					
						if (strpos($text, self::LABEL_DESCRIPTION) !== false){
							$job_des = $value;
							$job_des = Common::RemoveTrailingChars($job_des);
						} else if (strpos($text, self::LABEL_SALARY) !== false){
							$salary = $value;
							$salary = Common::RemoveTrailingChars($salary);
						} else if (strpos($text, self::LABEL_QUANTITY) !== false){
							$soluong =$value;
							$soluong = Common::RemoveTrailingChars($soluong);
						} else if (strpos($text, self::LABEL_DEADLINE) !== false){
							$deadline = $value;
							$deadline = Common::RemoveTrailingChars($deadline);
							$deadline = Common::ConvertDateFormat($deadline, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);
						} else if (strpos($text, self::LABEL_COMPANY) !== false and $company === ""){
							$company = $value;
							$company = Common::RemoveTrailingChars($company);
						} else if (strpos($text, self::LABEL_ADDRESS) !== false){
							$address = $value;
							$address = Common::RemoveTrailingChars($address);
						} else if(strpos($text, self::LABEL_PHONE) !== false){
							$phone_text = $value;
							$phone = Common::ExtractFirstMobile($phone_text);
							if ($mobile === ""){
								$mobile = $phone;
							}
						} else if (strpos($text, self::LABEL_MOBILE) !== false){
							$mobile_txt = Common::ExtractFirstMobile($value);
							if ($mobile_txt !== ""){
								$mobile = $mobile_txt;
							}
						} 
					}
				}
			}

			$email = ""; //TODO 

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
			Common::AppendArrayToFile($job_data, $data_path.self::TUYENDUNGSINHVIEN_DATA.'.csv', "|");
			return 0;
		}
	}
	
}