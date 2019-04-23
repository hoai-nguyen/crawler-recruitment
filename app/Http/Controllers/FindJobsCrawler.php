<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class FindJobsCrawler extends Controller{

	const TABLE = "findjobs";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "findjobs";
	const FINDJOBS_DATA_PATH = 'findjobs';
	const FINDJOBS_DATA = 'findjobs-data';
	const FINDJOBS_DATA_NO_CONTACT = 'findjobs-data-no-contact';
	const FINDJOBS_ERROR = 'findjobs-error-';
	const FINDJOBS_LINK = 'findjobs-link';
	const FINDJOBS_HOME = 'https://www.findjobs.vn/viec-lam-vi?page=';
	const LABEL_ADDRESS = 'address';
	const DATE_FORMAT = "Ymd";
	const INPUT_DATE_FORMAT = "d-m-Y";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 1000;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling FindJobs ...");

		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->FindJobsCrawlerFunc($new_batch -> start_page, $new_batch -> end_page);

				if ($return_code > 1) break;

				if($new_batch -> start_page >= self::MAX_PAGE) break;

			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::FINDJOBS_DATA_PATH.self::SLASH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv';
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

    public function FindJobsCrawlerFunc($start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::FINDJOBS_DATA_PATH.self::SLASH;
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
				$pageUrl = self::FINDJOBS_HOME.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('#job_list > li.row');

				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv');
						return 2;
					}
					$last_page_is_empty = true;
				} else{
					$last_page_is_empty = false;
					
					foreach($jobs as $node){
						$node = new Crawler($node);
						$DATA_PATH = public_path('data').self::SLASH.self::FINDJOBS_DATA_PATH.self::SLASH;
						try {
							$link_node = $node -> filter('a') -> each(
								function ($node){
									if ($node -> attr('itemprop') != null 
											and strpos($node -> attr('itemprop'), 'title') !== false
											and $node -> attr('href') != null)
										return $node -> attr('href');
								}
							);
							$job_link = current(array_filter($link_node));
							
							if ($job_link == null or strcmp($job_link, 'https://www.findjobs.vn') < 0){
							} else if(strcmp('https://www.findjobs.vn/viec-lam-vi', $job_link) == 0) {
								$return_code = 4;
								Common::AppendStringToFile("Running out of job: ", $DATA_PATH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv');
								break;
							} else{
								// select duplicated records
								$existing_links = Common::CheckLinksExist(array($job_link), env("DB_DATABASE"), self::TABLE);
								$duplicated_links = array();
								foreach($existing_links as $row){
									$link = $row -> link;
									array_push($duplicated_links, $link);
								}
					
								// deduplicate
								$new_links = array_diff(array($job_link), $duplicated_links);

								if (is_array($new_links) and sizeof($new_links) > 0){
									error_log(sizeof($new_links)." new links.");
									
									$inserted = Common::InsertLinks($new_links, env("DB_DATABASE"), self::TABLE);
									if ($inserted){
										foreach ($new_links as $link) {
											ini_set('max_execution_time', 10000000);		
											try {
												$created = $node -> filter("span.activedate") -> text();
												$created = trim($created, "\r\n\t");

												$this->CrawlJob($link, $created, $DATA_PATH);
												
												Common::AppendStringToFile($link , $DATA_PATH.self::FINDJOBS_LINK.'.csv');
											} catch (\Exception $e) {
												Common::AppendStringToFile("Exception on link:".$link.": ".substr($e -> getMessage (), 0, 1000)
													, $DATA_PATH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv');
											}
										}
										// end for
									}
								}
							}
						} catch (\Exception $e) {
							Common::AppendStringToFile('Exception on $node: '.substr($e -> getMessage (), 0, 1000)
								, $DATA_PATH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv');
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 2;
				error_log('FindJobsCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::FINDJOBS_DATA_PATH.self::SLASH.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
    public function CrawlJob($url, $created, $data_path){
		// $job_start = microtime(true);
		$client = new Client;
		// echo 'create client: '.(microtime(true) - $job_start).' secs, ';
		$crawler = $client -> request('GET', $url);
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';

		$content_crawler = $crawler -> filter('#job_detail');
		if ($content_crawler -> count() <= 0 ) {
			Common::AppendStringToFile("ERROR: Failed to crawl ".$url
			, $data_path.self::FINDJOBS_ERROR.date(self::DATE_FORMAT).'.csv');
		} else{
			$content = $content_crawler -> first();
			// $header_start = microtime(true);
			$job_title = "";
			$title_crawler = $crawler -> filter('h1.title');
			if ($title_crawler -> count() > 0 ) {
				$job_title = $title_crawler -> first() -> text();
			}
			$job_title = Common::RemoveTrailingChars($job_title);
			// echo 'header: '.(microtime(true) - $header_start).' secs, ';

			// $company_start = microtime(true);
            $company = "";
			$company_crawler = $content -> filter('h2.company');
			if ($company_crawler -> count() > 0 ) {
				$company = $company_crawler -> first() -> text();
			}
			$company = Common::RemoveTrailingChars($company);
			// echo 'company: '.(microtime(true) - $company_start).' secs, ';

			$company_details = $crawler -> filter('div.detail > dl.dl-horizontal > dd');
			$address = "";
			$website = "";
			if ($company_details -> count() > 0){
				foreach($company_details as $node){
					$comd_crawler = new Crawler($node);
					$itemprop = $comd_crawler -> attr('itemprop');
					$atag = $comd_crawler -> filter('a');
					if (strpos($itemprop, self::LABEL_ADDRESS) !== false){ 
						$address = $comd_crawler -> text();
					} 
					if ($atag -> count() > 0){
						$website = $atag -> attr('href');
					}
				}
			}
			$address = Common::RemoveTrailingChars($address);
			
            // $salary_start = microtime(true);
            $salary = '';
            $salary_crawler = $content -> filter('ol.job_attribs > li > span');
            if ($salary_crawler -> count() > 0 ) {
				$salary = $salary_crawler -> last() -> text();
			}
            // echo 'salary: '.(microtime(true) - $salary_start).' secs, ';

			// $job_des 
			$jds = $content -> filter('ul') -> first();
			$job_des = "";
			if ($jds -> count() > 0){
				$job_des = $jds -> text();
			}
			$job_des = Common::RemoveTrailingChars($job_des);

			// $mobile = FindJobsCrawler::ExtractMobile($contact);
			$mobile = "";
			$email = "";
			$soluong = "";
			$deadline = "";
			$contact = "";
			$created = Common::ConvertDateFormat($created, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);

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
				Common::AppendArrayToFile($job_data, $data_path.self::FINDJOBS_DATA_NO_CONTACT.'.csv', "|");
			} else{
				if (Common::isNotMobile($mobile)){
					$job_data[0] = "";
				}
				Common::AppendArrayToFile($job_data, $data_path.self::FINDJOBS_DATA.'.csv', "|");
				Common::AppendArrayToFile($job_data, $data_path.self::FINDJOBS_DATA.'-'.date(self::DATE_FORMAT).'.csv', "|");
			}
		}
	}
	
}