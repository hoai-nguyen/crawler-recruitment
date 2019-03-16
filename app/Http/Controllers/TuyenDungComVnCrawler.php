<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class TuyenDungComVnCrawler extends Controller{

	const TABLE = "crawler_tuyendungcomvn";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "tuyendungcomvn";
	const TUYENDUNGCOMVN_DATA_PATH = 'tuyendungcomvn';
	const TUYENDUNGCOMVN_DATA = 'tuyendungcomvn-data';
	const TUYENDUNGCOMVN_DATA_NO_CONTACT = 'tuyendungcomvn-data-no-contact';
	const TUYENDUNGCOMVN_ERROR = 'tuyendungcomvn-error-';
	const TUYENDUNGCOMVN_LINK = 'tuyendungcomvn-link';
	const TUYENDUNGCOMVN_HOME = 'https://tuyendung.com.vn';
	const TUYENDUNGCOMVN_PAGE = 'https://tuyendung.com.vn/timvieclam/JobResult.aspx?page=';
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
		error_log("Start crawling TUYENDUNG.COM.VN ...");

		$client = $this->TuyenDungComvnLogin();
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->TuyenDungComVnCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				
				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::TUYENDUNGCOMVN_DATA_PATH.self::SLASH.self::TUYENDUNGCOMVN_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function TuyenDungComVnCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::TUYENDUNGCOMVN_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::TUYENDUNGCOMVN_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('#ctl00_ContentPlaceHolder1_GridView1') -> filter('a');
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::TUYENDUNGCOMVN_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::TUYENDUNGCOMVN_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::TUYENDUNGCOMVN_DATA_PATH.self::SLASH.self::TUYENDUNGCOMVN_ERROR.date(self::DATE_FORMAT).'.csv';
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
												, $DATA_PATH.self::TUYENDUNGCOMVN_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::TUYENDUNGCOMVN_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('TuyenDungComVnCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::TUYENDUNGCOMVN_DATA_PATH.self::SLASH.self::TUYENDUNGCOMVN_ERROR.date(self::DATE_FORMAT).'.csv';
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
				, $data_path.self::TUYENDUNGCOMVN_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::TUYENDUNGCOMVN_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$company = "";
			$company_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_lblCompanyName');
			if ($company_crl->count() > 0){
				$company = $company_crl -> text();
			}
			
			$address = "";
			$address_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_lblCompanyAddress');
			if ($address_crl->count() > 0){
				$address = $address_crl -> text();
			}

			$website = "";
			$website_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_hplWebsite');
			if ($website_crl->count() > 0){
				$website = $website_crl -> text();
			}

			$job_title = "";
			$job_title_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_lblJobPosition');
			if ($job_title_crl->count() > 0){
				$job_title = $job_title_crl -> text();
			} else {
				return -1;
			}

			$soluong = "";
			$soluong_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_lblReqNumber');
			if ($soluong_crl->count() > 0){
				$soluong = $soluong_crl -> text();
			}
			
			$salary = "";
			$salary_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_lblSalary');
			if ($salary_crl->count() > 0){
				$salary = $salary_crl -> text();
			}

			$deadline = "";
			$deadline_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_lblClosedDate');
			if ($deadline_crl->count() > 0){
				$deadline = $deadline_crl -> text();
			}

			$email = "";
			$email_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_hplContactEmail');
			if ($email_crl->count() > 0){
				$email = $email_crl -> text();
			}

			$mobile = "";
			$mobile_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_lblContactMobile');
			if ($mobile_crl->count() > 0){
				$mobile = $mobile_crl -> text();
				$mobile = Common::ExtractFirstMobile($mobile);
			}

			$phone = "";
			$phone_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_lblContactTel');
			if ($phone_crl->count() > 0){
				$phone = $phone_crl -> text();
				$phone = Common::ExtractFirstMobile($phone);
			}
			if ($mobile === ""){
				$mobile = $phone;
			}
			
			$job_des = "";
			$job_des_crl = $crawler -> filter('div.JobDescription > div.Contents');
			if ($job_des_crl->count() > 0){
				$job_des = $job_des_crl -> text();
				$job_des = Common::RemoveTrailingChars($job_des);
			}

			$created = "";

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
			if (Common::IsNullOrEmpty($email) and (Common::IsNullOrEmpty($mobile) or Common::isNotMobile($mobile))){
				Common::AppendArrayToFile($job_data, $data_path.self::TUYENDUNGCOMVN_DATA_NO_CONTACT.'.csv', "|");
			} else{
				Common::AppendArrayToFile($job_data, $data_path.self::TUYENDUNGCOMVN_DATA.'.csv', "|");
			}
			return 0;
		}
	}
	
	public function TuyenDungComvnLogin(){
		$client = new Client;
		try{
			$crawler = $client -> request('GET', 'https://tuyendung.com.vn/timvieclam/login.aspx');
			$button = $crawler -> selectButton('Đăng nhập');
			$form = $button -> form();
			$form['ctl00$ContentPlaceHolder1$txtEmail'] = 'itviecvietnam11@gmail.com';
			$form['ctl00$ContentPlaceHolder1$txtPassword'] = 'vanhoai1@';
			$form_crl = $client -> submit($form);
			return $client;
		} catch (\Exception $e) {
			$client = new Client;
			error_log('TimViec365Login: '.($e -> getMessage ()));
			$file_name = public_path('data').self::SLASH.self::TUYENDUNGCOMVN_DATA_PATH.self::SLASH.self::TUYENDUNGCOMVN_ERROR.date(self::DATE_FORMAT).'.csv';
			Common::AppendStringToFile('Exception on login: '.($e -> getMessage ()), $file_name);
		}
		return $client;
	}
}