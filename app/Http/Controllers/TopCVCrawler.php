<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class TopCVCrawler extends Controller{

	const TABLE = "topcv";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "topcv";
	const TOPCV_DATA_PATH = 'topcv';
	const TOPCV_DATA = 'topcv-data';
	const TOPCV_DATA_NO_CONTACT = 'topcv-data-no-contact';
	const TOPCV_ERROR = 'topcv-error-';
	const TOPCV_LINK = 'topcv-link';
	const TOPCV_HOME = 'https://www.topcv.vn/viec-lam/moi-nhat.html?page=';
	const LABEL_SALARY = 'Mức lương:';
	const LABEL_QUANTITY = 'Số lượng cần tuyển:';
	const LABEL_DEADLINE = "Hạn nộp hồ sơ:";
	const DATE_FORMAT = "Ymd";
	const INPUT_DATE_FORMAT = "d/m/Y";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 500;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling TopCV ...");

		$client = TopCVCrawler::TopCVLogin();
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->TopCVCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);

				if ($return_code > 1) break;

				if($new_batch -> start_page >= self::MAX_PAGE) break;

			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::TOPCV_DATA_PATH.self::SLASH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function TopCVCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::TOPCV_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::TOPCV_HOME.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('div.job-list > div.result-job-hover') -> filter('h4.job-title > a');
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::TOPCV_DATA_PATH.self::SLASH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv';
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
												, $DATA_PATH.self::TOPCV_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('TopCVCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::TOPCV_DATA_PATH.self::SLASH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv';
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

	public function TopCVLogin(){
		$client = new Client;
		try{
			$crawler = $client -> request('GET', 'https://www.topcv.vn/login');
			// $crawler = $client->click($crawler->selectLink('Sign in')->link());
			
			$form = $crawler -> filter('#form-login') -> form();
			$form_crl = $client -> submit($form, array('email' => 'nguyenvanhoai.cs@gmail.com', 'password' => 'vanhoai1@'));

			return $client;
		} catch (\Exception $e) {
			$client = new Client;
			error_log('TopCVLogin: '.($e -> getMessage ()));
			$file_name = public_path('data').self::SLASH.self::TOPCV_DATA_PATH.self::SLASH.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv';
			Common::AppendStringToFile('Exception on login: '.($e -> getMessage ()), $file_name);
		}
		return $client;
	}

    public function CrawlJob($client, $url, $data_path){
		$job_start = microtime(true);
		
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			Common::AppendStringToFile("Cannot request page: ".$url.": ".substr($e -> getMessage (), 0, 1000)
				, $data_path.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$general_infos = $crawler -> filter('#row-job-title');
			if($general_infos -> count() <= 0){
				Common::AppendStringToFile("No #row-job-title: ".$url
					, $data_path.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}
			
			$job_title = $general_infos -> filter('h1.job-title') -> text();
			$company = $general_infos -> filter('div.company-title') -> text();
			$company = Common::RemoveTrailingChars($company);

			
			$deadline = $general_infos -> filter('div.job-deadline') -> text();
			$deadline = str_replace(self::LABEL_DEADLINE, "", $deadline); 
			$deadline = Common::RemoveTrailingChars($deadline);
			if (Common::IsJobExpired(Common::DEFAULT_DEADLINE, $deadline)){
				return 2;
			}
			// $deadline = Common::ConvertDateFormat($deadline, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);
			
			$address_crl = $general_infos -> filter('div.text-dark-gray');
			if ($address_crl -> count() <= 0){
				Common::AppendStringToFile("No div.text-dark-gray: ".$url
					, $data_path.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}
			$address = $address_crl -> first() -> text();
			$address = Common::RemoveTrailingChars($address);
			
			$job_infos_crl = $crawler -> filter('#tab-info');
			if ($job_infos_crl -> count() <= 0){
				Common::AppendStringToFile("No #tab-info: ".$url
					, $data_path.self::TOPCV_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}
			$job_des = $crawler -> filter('#col-job-left > div.content-tab') -> text();
			$job_des = Common::RemoveTrailingChars($job_des);

			$job_details_crl = $job_infos_crl -> filter('#col-job-right > #box-info-job > div.job-info-item');
			$count = 0;
			$salary = "";
			$soluong = "";
			foreach($job_details_crl as $info){
				$info_node = new Crawler($info);
				$text = $info_node -> text();
				if ($count >= 2){
					break;
				} else if (strpos($text, self::LABEL_SALARY) !== false){
					$salary = $text;
					$count++;
				} else if (strpos($text, self::LABEL_QUANTITY) !== false){ 
					$soluong = $text;
					$count++;
				}
			}
			$salary = str_replace(self::LABEL_SALARY, "", $salary);
			$salary = Common::RemoveTrailingChars($salary);
			$soluong = str_replace(self::LABEL_QUANTITY, "", $soluong);
			$soluong = Common::RemoveTrailingChars($soluong);
			
			$job_contact_crl = $crawler -> filter('#tab-info-company') -> filter('#col-job-left > div.content-tab');
			$contact = "";
			$mobile = "";
			$website = "";
			if ($job_contact_crl -> count() > 0){
				$contact = $job_contact_crl -> last() -> text();
				$contact = Common::RemoveTrailingChars($contact);
				$atag = $job_contact_crl -> filter('a') -> last();
				if($atag -> count() > 0){
					$website = $atag -> attr('href');
				}
				$ptag = $job_contact_crl -> last() -> filter('p');
				if($ptag -> count() > 1){
					$mobile = $ptag -> last() -> text();
					$mobile = Common::ExtractFirstMobile($mobile);
				}
			}
			
			$email = "";
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
				, $website
				// , $url
			);
			if (Common::IsNullOrEmpty($email) and (Common::IsNullOrEmpty($mobile) or Common::isNotMobile($mobile))){
				Common::AppendArrayToFile($job_data, $data_path.self::TOPCV_DATA_NO_CONTACT.'.csv', "|");
			} else{
				if (Common::isNotMobile($mobile)){
					$job_data[0] = "";
				}
				Common::AppendArrayToFile($job_data, $data_path.self::TOPCV_DATA.'.csv', "|");
			}
			return 0;
		}
	}

}