<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class TimViecNhanhCrawler extends Controller{

	const TABLE = "timviecnhanh";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "timviecnhanh";
	const TIMVIECNHANH_DATA_PATH = 'timviecnhanh';
	const TIMVIECNHANH_DATA = 'timviecnhanh-data';
	const TIMVIECNHANH_DATA_NO_CONTACT = 'timviecnhanh-data-no-contact';
	const TIMVIECNHANH_ERROR = 'timviecnhanh-error-';
	const TIMVIECNHANH_LINK = 'timviecnhanh-link';
	const TIMVIECNHANH_HOME = 'https://www.timviecnhanh.com/vieclam/timkiem?&page=';
	const LABEL_SALARY = 'Mức lương';
	const LABEL_QUANTITY = 'Số lượng tuyển dụng';
	const LABEL_WEBSITE = 'Website';
	const LABEL_CONTACT = "Người liên hệ";
	const LABEL_ADDRESS = "Địa chỉ";
	const LABEL_EMAIL = "Email";
	const LABEL_PHONE = "Điện thoại";
	const LABEL_MOBILE = "Di động";

	const DATE_FORMAT = "Ymd";
	const INPUT_DATE_FORMAT = "d-m-Y";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 700;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling TimViecNhanh ...");

		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;

				$return_code = $this->TimViecNhanhCrawlerFunc($new_batch -> start_page, $new_batch -> end_page);

				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;

			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::TIMVIECNHANH_DATA_PATH.self::SLASH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function TimViecNhanhCrawlerFunc($start_page, $end_page){

		$DATA_PATH = public_path('data').self::SLASH.self::TIMVIECNHANH_DATA_PATH.self::SLASH;
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
				$pageUrl = self::TIMVIECNHANH_HOME.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('table > tbody > tr > td > a.item');

				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::TIMVIECNHANH_DATA_PATH.self::SLASH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv';
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
										$this->CrawlJob($job_link, $DATA_PATH);

										Common::AppendStringToFile($job_link
											, $DATA_PATH.self::TIMVIECNHANH_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('TimViecNhanhCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::TIMVIECNHANH_DATA_PATH.self::SLASH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv';
				Common::AppendStringToFile("Exception on page = ".$x.": ".substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}

			$x++;
			if ($x > self::MAX_PAGE){ // du phong
				$return_code = 3;
				break;
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
		$crawler = $client -> request('GET', $url);
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';

		$content_crawler = $crawler -> filter('article.block-content');

		if ($content_crawler -> count() <= 0 ) {
			Common::AppendStringToFile("ERROR: Failed to crawl ".$url
			, $data_path.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
		} else{
			$content = $content_crawler -> first();
			
			// $header_start = microtime(true);
			$job_title = "";
			$title_crawler = $crawler -> filter('header.block-title > h1 > span');
			if ($title_crawler -> count() > 0 ) {
				$job_title = $title_crawler -> first() -> text();
			}
			$job_title = Common::RemoveTrailingChars($job_title);
			// echo 'header: '.(microtime(true) - $header_start).' secs, ';

			// $posted_start = microtime(true);
			$created = "";
			$created_crawler = $content -> filter('time');
			if ($created_crawler -> count() > 0 ) {
				$created = $created_crawler -> first() -> text();
			}
			$created = Common::ConvertDateFormat($created, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);
			// echo 'posted time: '.(microtime(true) - $posted_start).' secs, ';
			
			// $company_start = microtime(true);
			$company = "";
			$company_crawler = $content -> filter('div > h3 > a');
			if ($company_crawler -> count() > 0 ) {
				$company = $company_crawler -> first() -> text();
			}
			$company = Common::RemoveTrailingChars($company);
			// echo 'company: '.(microtime(true) - $company_start).' secs, ';

			// $deadline_start = microtime(true);
			$deadline_crl = $content -> filter('b.text-danger');
			if ($deadline_crl -> count() > 0){
				$deadline = $deadline_crl -> first() -> text();
			} 
			$deadline = trim($deadline, "\r\n ");
			$deadline = Common::ConvertDateFormat($deadline, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);
			if (Common::IsJobExpired(Common::DEFAULT_DEADLINE, $deadline)){
				return 2;
			}
			// echo 'deadline: '.(microtime(true) - $deadline_start).' secs, ';

			// $salary_start = microtime(true);
			$salary = '';
			$soluong = '';
			try{
				$quanti_info = $content -> filter('div > ul > li');
				$reach = 0;
				foreach ($quanti_info as $node) {
					if ($reach > 1) break;
					$quanti_crawler = new Crawler($node);
					$label = $quanti_crawler -> filter('b') -> first() -> text();
					if (strpos($label, self::LABEL_SALARY) !== false){
						$salary = $quanti_crawler -> text();
						$reach += 1;
					} else if (strpos($label, self::LABEL_QUANTITY) !== false){
						$soluong = $quanti_crawler -> text();
						$reach += 1;
					}
				}
				$salary = trim(explode("\n", $salary)[2], "\r\n ");
				$soluong = trim(explode("\n", $soluong)[2], "\r\n ");
				// echo 'salary + soluong: '.(microtime(true) - $salary_start).' secs, ';
			} catch (\Exception $e) {
				Common::AppendStringToFile('Exception on get salaray + soluong: '.$url.': '.$e -> getMessage()
					, $data_path.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
			}
			
			// $website_start = microtime(true);
			$website = '';
			try {
				$side_bar = $crawler -> filter('div.block-sidebar > div > p');
				if ($side_bar -> count() > 0){
					foreach ($side_bar as $node) {
						$node_crl = new Crawler($node);
						$label = $node_crl -> filter('b');
						if ($label -> count() > 0){
							$label_text = $label -> text();
							
							if (strpos($label_text, self::LABEL_WEBSITE) !== false){
								$text = $node_crl -> text();
								$website = trim(explode("\n", $text)[2], "\r\n ");
								break;
							}
						}
					}
				}
			} catch (\Exception $e) {
				Common::AppendStringToFile('Exception on get website: '.$url.': '.$e -> getMessage()
					, $data_path.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
			}
			$website = trim(preg_replace("/[\r\n]*/", "", $website), "-");
			
			$contact = "";
			$address = "";
			$email = "";
			$mobile = "";
			try{
				$lienhe = $content -> filter('div.block-info-company > div.block-content') -> first() -> filter('tr');
				if($lienhe -> count() > 0){
					foreach($lienhe as $info){
						$info_crl = new Crawler($info);
						$info_crl_td = $info_crl -> filter('td');
						if ($info_crl_td -> count() > 1){
							$label = $info_crl_td -> first() -> text();
							if (strpos($label, self::LABEL_CONTACT) !== false){ 
								$contact = $info_crl_td -> last() -> text();
							} else if (strpos($label, self::LABEL_ADDRESS) !== false){
								$address = $info_crl_td -> last() -> text();
							} else if (strpos($label, self::LABEL_EMAIL) !== false){
								$email = Common::ExtractEmailFromText($info_crl_td -> last() -> text());
							} else if (strpos($label, self::LABEL_PHONE) !== false or strpos($label, self::LABEL_MOBILE) !== false){
								$mobile = Common::ExtractFirstMobile($info_crl_td -> last() -> text());
							}
						}
					}
				}
				$contact = Common::RemoveTrailingChars($contact);
				$address = Common::RemoveTrailingChars($address);

			} catch (\Exception $e) {
				Common::AppendStringToFile('Exception on getting contact or address: '.$url.': '.$e -> getMessage()
					, $data_path.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
			}

			$job_des = "";
			try{
				$job_des = $content -> filter('td > p') -> first() -> text();
				$job_des = trim(preg_replace("/[\t\r\n]*/", "", $job_des), "\t\r\n- ");
			} catch (\Exception $e) {
				Common::AppendStringToFile('Exception on getting job_des: '.$url.': '.$e -> getMessage()
					, $data_path.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
			}
			$job_des = Common::RemoveTrailingChars($job_des);
			
			// $mobile = Common::ExtractFirstMobile($contact);
			
			// $file_start = microtime(true);
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
				Common::AppendArrayToFile($job_data, $data_path.self::TIMVIECNHANH_DATA_NO_CONTACT.'.csv', "|");
			} else{
				if (Common::isNotMobile($mobile)){
					$job_data[0] = "";
				}
				Common::AppendArrayToFile($job_data, $data_path.self::TIMVIECNHANH_DATA.'.csv', "|");
				Common::AppendArrayToFile($job_data, $data_path.self::TIMVIECNHANH_DATA.'-'.date(self::DATE_FORMAT).'.csv', "|");
			}
		}
	}

}
