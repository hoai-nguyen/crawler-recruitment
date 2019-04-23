<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class ITViecCrawler extends Controller{

	const TABLE = "itviec";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "itviec";
	const ITVIEC_DATA_PATH = 'itviec';
	const ITVIEC_DATA = 'itviec-data';
	const ITVIEC_DATA_NO_CONTACT = 'itviec-data-no-contact';
	const ITVIEC_ERROR = 'itviec-error-';
	const ITVIEC_LINK = 'itviec-link';
	const ITVIEC_HOME = 'https://itviec.com';
	const ITVIEC_PAGE = 'https://itviec.com/it-jobs?page=';
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
		error_log("Start crawling ITViec ...");

		$client = new Client();
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				$return_code = $this->ITViecCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::ITVIEC_DATA_PATH.self::SLASH.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function ITViecCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::ITVIEC_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::ITVIEC_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('div.first-group') -> filter('h2.title > a');
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::ITVIEC_DATA_PATH.self::SLASH.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv';
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
										$full_link = self::ITVIEC_HOME.$job_link;
										$code = $this->CrawlJob($client, $full_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($full_link
												, $DATA_PATH.self::ITVIEC_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('ITViecCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::ITVIEC_DATA_PATH.self::SLASH.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv';
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

	public function ITViecLogin(){
    	$client = new Client;
    	$crawler = $client -> request('GET', 'https://itviec.com/');
		// $crawler = $client->click($crawler->selectLink('Sign in')->link());

		$modal_crl = $crawler -> filter('a.pageMenu__link');
		$modal = $client -> click($modal_crl -> link());
		// $form = $modal -> filter('#form-login') -> form();
		$form = $modal -> selectButton('Sign in')->form();
		$page = $client -> submit($form, array('user[email]' => 'truongdinhanh.telcs@gmail.com', 'user[password]' => 'vanhoai1@'));
		
		$form = $crawler -> filter('#form-login') -> form();
		$form_crl = $client -> submit($form, array('email' => 'nguyenvanhoai.cs@gmail.com', 'password' => 'vanhoai1@'));

		$url1 = "https://www.topcv.vn/viec-lam/nhan-vien-kinh-doanh-website-ho-chi-minh/86194.html";
		$job_crawler1 = $client -> request('GET', $url1);
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
			error_log('TopCVLogin: '.($e -> getMessage ()));
			$file_name = public_path('data').self::SLASH.self::ITVIEC_DATA_PATH.self::SLASH.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv';
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
				, $data_path.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$job_details_crl = $crawler -> filter('div.job-detail');
			if($job_details_crl -> count() <= 0){
				Common::AppendStringToFile("No div.job-detail: ".$url
					, $data_path.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}
			
			$job_title = $job_details_crl -> filter('h1.job_title') -> text();
			$job_title = Common::RemoveTrailingChars($job_title);
			
			$address_crl = $job_details_crl -> filter('div.address__full-address');
			if ($address_crl -> count() <= 0){
				Common::AppendStringToFile("No div.text-dark-gray: ".$url
					, $data_path.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}
			$address = $address_crl -> first() -> text();
			$address = Common::RemoveTrailingChars($address);
			
			$job_des_crl = $crawler -> filter('div.job_description > div.description');
			if ($job_des_crl -> count() <= 0){
				Common::AppendStringToFile("No job_description: ".$url
					, $data_path.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}
			$job_des = $job_des_crl -> first() -> text();
			$job_des = Common::RemoveTrailingChars($job_des);

			
			$company_crl = $crawler -> filter('div.employer-info > h3.name');
			if ($company_crl -> count() <= 0){
				Common::AppendStringToFile("No employer-info: ".$url
					, $data_path.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
				return 1;
			}
			$company = $company_crl -> first() -> text();
			$company = Common::RemoveTrailingChars($company);
			
			$website = $company_crl -> filter('a') -> first() -> attr('href');
			$website = self::ITVIEC_HOME.$website;

			$created = "";
			$created_crl = $job_details_crl -> filter('div.distance-time-job-posted');
			if ($created_crl -> count() > 0){
				$date_text = $created_crl -> first() -> text();
				$date_text = Common::RemoveTrailingChars($date_text);
				$created = $this->GetDateFromInterval($date_text);
			}

			$mobile = "";
			$email = "";
			$soluong = "";
			$deadline = "";
			// $contact = "";
			$salary = "";
			
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
				Common::AppendArrayToFile($job_data, $data_path.self::ITVIEC_DATA_NO_CONTACT.'.csv', "|");
			} else{
				if (Common::isNotMobile($mobile)){
					$job_data[0] = "";
				}
				Common::AppendArrayToFile($job_data, $data_path.self::ITVIEC_DATA.'.csv', "|");
				Common::AppendArrayToFile($job_data, $data_path.self::ITVIEC_DATA.'-'.date(self::DATE_FORMAT).'.csv', "|");
			}
			return 0;
		}
	}

	public function GetDateFromInterval($interval){
		try{
			return date(Common::DATE_DATA_FORMAT, strtotime($interval, strtotime("now")));
		} catch (\Exception $e) {
			$file_name = public_path('data').self::SLASH.self::ITVIEC_DATA_PATH.self::ITVIEC_ERROR.date(self::DATE_FORMAT).'.csv';
			ITViecCrawler::AppendStringToFile('Ex on get date: '.substr($e -> getMessage (), 0, 1000), $file_name);
		}
		return "";
	}
}