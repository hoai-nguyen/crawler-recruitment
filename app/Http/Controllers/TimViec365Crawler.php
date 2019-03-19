<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class TimViec365Crawler extends Controller{

	const TABLE = "crawler_timviec365";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "timviec365";
	const TIMVIEC365_DATA_PATH = 'timviec365';
	const TIMVIEC365_DATA = 'timviec365-data';
	const TIMVIEC365_DATA_NO_CONTACT = 'timviec365-data-no-contact';
	const TIMVIEC365_ERROR = 'timviec365-error-';
	const TIMVIEC365_LINK = 'timviec365-link';
	const TIMVIEC365_HOME = 'https://timviec365.vn';
	const TIMVIEC365_PAGE = 'https://timviec365.vn/tin-tuyen-dung-viec-lam.html?page=';
	const LABEL_SALARY = 'Mức lương:';
	const LABEL_QUANTITY = 'Số lượng cần tuyển:';
	const LABEL_DEADLINE = "Hạn nộp hồ sơ:";
	const LABEL_CREATED = "Ngày cập nhật:"; 
	const LABEL_PHONE = "Số điện thoại:";
	const LABEL_EMAIL = "Email:";
	const LABEL_ADDRESS = "Địa chỉ:";
	const DATE_FORMAT = "Ymd";
	const DATA_DATE_FORMAT = "Y-m-d";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 500;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling TIMVIEC365 ...");

		$client = $this->TimViec365Login();
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->TimViec365CrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				if ($return_code > 1) {
					break;
				}
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::TIMVIEC365_DATA_PATH.self::SLASH.self::TIMVIEC365_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function TimViec365CrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::TIMVIEC365_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::TIMVIEC365_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('div.main_cate') -> filter('a.title_cate');
				
				if ($jobs -> count() <= 0) {
					$client = $this->TimViec365Login();
					$crawler = $client -> request('GET', $pageUrl);
					$jobs = $crawler -> filter('div.main_cate') -> filter('a.title_cate');
				}

				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::TIMVIEC365_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::TIMVIEC365_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::TIMVIEC365_DATA_PATH.self::SLASH.self::TIMVIEC365_ERROR.date(self::DATE_FORMAT).'.csv';
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
										$full_link = self::TIMVIEC365_HOME.$job_link;
										$code = $this->CrawlJob($client, $full_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($full_link
												, $DATA_PATH.self::TIMVIEC365_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::TIMVIEC365_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('TimViec365CrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::TIMVIEC365_DATA_PATH.self::SLASH.self::TIMVIEC365_ERROR.date(self::DATE_FORMAT).'.csv';
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
				, $data_path.self::TIMVIEC365_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::TIMVIEC365_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$general_info_crl = $crawler -> filter('div.box_tit_detail');
			if($general_info_crl -> count() <= 0){
				Common::AppendStringToFile("No box_tit_detail: ".$url
					, $data_path.self::TIMVIEC365_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}

			$job_title = "";
			$job_title_crl = $general_info_crl -> filter('div.right_tit > h1');
			if ($job_title_crl->count() > 0){
				$job_title = $job_title_crl -> text();
			}
			$job_title = Common::RemoveTrailingChars($job_title);

			$company = "";
			$website = "";
			$company_crl = $general_info_crl -> filter('h2 > a');
			if ($company_crl->count() > 0){
				$company = $company_crl -> first() -> text();
				$website = $company_crl -> first() -> attr('href');
				$website = self::TIMVIEC365_HOME.$website;
			}
			$company = Common::RemoveTrailingChars($company);
			
			$salary = "";
			$salary_crl = $general_info_crl -> filter('div.right_tit > p.lv_luong');
			if ($salary_crl->count() > 0){
				$salary = $salary_crl -> first() -> text();
			}
			$salary = str_replace(self::LABEL_SALARY, '', $salary);
			$salary = Common::RemoveTrailingChars($salary);

			$deadline = "";
			$deadline_crl = $general_info_crl -> filter('div.right_tit > p');
			if ($deadline_crl -> count() > 0){
				$text = $deadline_crl -> last() -> text();
				if(strpos($text, self::LABEL_DEADLINE) !== false){
					$deadline = $text;
					$deadline = str_replace(self::LABEL_DEADLINE, '', $deadline);
				}
			}
			$deadline = Common::RemoveTrailingChars($deadline);
			if (Common::IsJobExpired(Common::DEFAULT_DEADLINE, $deadline)){
				return 2;
			}

			$created = "";
			$created_crl = $general_info_crl -> filter('div.xacthuc_tit > p');
			if ($created_crl -> count() > 0){
				$text = $created_crl -> last() -> text();
				if(strpos($text, self::LABEL_CREATED) !== false){
					$created = $text;
					$created = str_replace(self::LABEL_CREATED, '', $created);
				}
			}
			$created = Common::RemoveTrailingChars($created);

			$mobile = "";
			$email = "";
			$tomtat_crl = $crawler -> filter('div.box_tomtat > div.form_control');
			$count = 0;
			foreach($tomtat_crl as $item){
				if ($count > 1) break;
				$node = new Crawler($item);
				$text = $node -> text();
				if (strpos($text, self::LABEL_EMAIL) !== false){ 
					$email = Common::ExtractEmailFromText($text);
					$count++;
				} else if(strpos($text, self::LABEL_PHONE) !== false){
					$mobile = Common::ExtractFirstMobile($text);
					$count++;
				}
			}

			$job_des_crl = $crawler -> filter('div.box_mota');
			$job_des = "";
			if ($job_des_crl -> count() > 0){
				$job_des = $job_des_crl -> text();
			}
			$job_des = str_replace("Mô tả công việc", '', $job_des);
			$job_des = Common::RemoveTrailingChars($job_des);

			
			$address = "";
			$address_crl = $crawler -> filter('div.tt_com > p');
			if ($address_crl -> count() > 0){
				$address = $address_crl -> first() -> text();
			}
			$address = str_replace(self::LABEL_ADDRESS, '', $address);
			$address = Common::RemoveTrailingChars($address);

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
			if (Common::IsNullOrEmpty($email) and (Common::IsNullOrEmpty($mobile) or Common::isNotMobile($mobile))){
				Common::AppendArrayToFile($job_data, $data_path.self::TIMVIEC365_DATA_NO_CONTACT.'.csv', "|");
			} else{
				if (Common::isNotMobile($mobile)){
					$job_data[0] = "";
				}
				Common::AppendArrayToFile($job_data, $data_path.self::TIMVIEC365_DATA.'.csv', "|");
			}
			return 0;
		}
	}
	
	public function TimViec365Login(){
		$client = new Client;
		try{
			$crawler = $client -> request('GET', 'https://timviec365.vn/dang-nhap-ung-vien.html');
			$form = $crawler -> selectButton('Đăng nhập')->form();
			$form['email'] = 'itviecvietnam11@gmail.com';
			$form['password_first'] = 'vanhoai1@';
			$form_crl = $client -> submit($form);
			return $client;
		} catch (\Exception $e) {
			$client = new Client;
			error_log('TimViec365Login: '.($e -> getMessage ()));
			$file_name = public_path('data').self::SLASH.self::TIMVIEC365_DATA_PATH.self::SLASH.self::TIMVIEC365_ERROR.date(self::DATE_FORMAT).'.csv';
			Common::AppendStringToFile('Exception on login: '.($e -> getMessage ()), $file_name);
		}
		return $client;
	}

}