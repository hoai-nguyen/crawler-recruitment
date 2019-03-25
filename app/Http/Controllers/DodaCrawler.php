<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class DodaCrawler extends Controller{

	const TABLE = "crawler_doda";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "doda";
	const DODA_DATA_PATH = 'doda';
	const DODA_DATA = 'doda-data';
	const DODA_DATA_NO_CONTACT = 'doda-data-no-contact';
	const DODA_ERROR = 'doda-error-';
	const DODA_LINK = 'doda-link';
	const DODA_HOME = 'https://doda.jp';
	const DODA_PAGE = 'https://doda.jp/DodaFront/View/JobSearchList.action?page=';
	const DODA_POSTFIX = '-fm__jobdetail/-mpsc_sid__10/';
	const LABEL_SALARY = "給与";
	const LABEL_QUANTITY = "採用予定人数";
	const LABEL_DESCRIPTION = "仕事内容";
	const LABEL_MOBILE = "連絡先";
	const LABEL_ADDRESS = "勤務地";
	const LABEL_WEBSITE = "企業ホームページ";
	const DATE_FORMAT = "Ymd";
	const DATE_REGEX = '/\d{4}\/\d{1,2}\/\d{1,2}/';
	const INPUT_DATE_FORMAT = "Y/m/d";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE =  1000;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling doda ...");

		$client = new Client(); 
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this -> DodaCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				
				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;

			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::DODA_DATA_PATH.self::SLASH.self::DODA_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function DodaCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::DODA_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";
			
			try{
				$pageUrl = self::DODA_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('div.blockButon > p > a._JobListToDetail');
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::DODA_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::DODA_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::DODA_DATA_PATH.self::SLASH.self::DODA_ERROR.date(self::DATE_FORMAT).'.csv';
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

									if ($job_link != null){
										$full_link = str_replace("-tab__pr", "-tab__jd", $job_link);
										$full_link = $full_link.self::DODA_POSTFIX;
										$code = $this->CrawlJob($client, $full_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($full_link
												, $DATA_PATH.self::DODA_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::DODA_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('DodaCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::DODA_DATA_PATH.self::SLASH.self::DODA_ERROR.date(self::DATE_FORMAT).'.csv';
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
				, $data_path.self::DODA_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}
		
		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::DODA_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$company = "";
			$company_name_crl = $crawler -> filter("div.head_title > h1");
			if ($company_name_crl -> count() > 0){
				$company = $company_name_crl -> first() -> text();
			}
			$company = Common::RemoveSpaceChars($company);

			$job_title = "";
			$job_title_crl = $crawler -> filter('div.head_title > p.explain');
			if ($job_title_crl->count() > 0){
				$job_title = $job_title_crl -> first() -> text();
			} 
			$job_title = Common::RemoveSpaceChars($job_title);
			
			$created = "";
			$deadline = "";
			$period_crl = $crawler -> filter("div.head_title > p.meta_text");
			if ($period_crl -> count() > 0){
				$text = $period_crl -> first() -> text();
				preg_match_all(self::DATE_REGEX, $text, $matches);
				if (sizeof($matches) > 0){
					$created = $matches[0][0];
					$deadline = $matches[0][1];
				}
			}
			$created = Common::RemoveSpaceChars($created);
			$deadline = Common::RemoveSpaceChars($deadline);
			$created = Common::ConvertDateFormat($created, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);
			$deadline = Common::ConvertDateFormat($deadline, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);
			if (Common::IsJobExpired(Common::DEFAULT_DEADLINE, $deadline)){
				return 2;
			}

			$salary = "";
			$address = "";
			$job_des = "";
			$job_infos_crl = $crawler -> filter("#job_description_table") -> filter("tr");
			if ($job_infos_crl -> count() <= 0 ) {
				Common::AppendStringToFile("No job info: ".$url
					, $data_path.self::DODA_ERROR.date(self::DATE_FORMAT).'.csv');
				return -1;
			}
			foreach($job_infos_crl as $node){
				$node = new Crawler($node);
				$label = $node -> children() -> first() -> text();
				$value = $node -> children() -> last() -> text();	
				if (strpos($label, self::LABEL_DESCRIPTION) !== false){
					$job_des = Common::RemoveSpaceChars($value);
				} else if (strpos($label, self::LABEL_ADDRESS) !== false){
					$address = Common::RemoveSpaceChars($value);
				} else if (strpos($label, self::LABEL_SALARY) !== false){
					$salary = Common::RemoveSpaceChars($value);
				} 
			}
			
			$website = "";
			$website_crl = $crawler -> filter("#company_profile_table") -> filter("tr");
			if ($website_crl -> count() > 0 ) {
				$web_crl = $website_crl -> last() -> children("td") -> filter("a");
				if ($web_crl -> count() > 0){
					$website = $web_crl -> attr("href");
				}
			}

			$mobile = "";
			$email = "";
			$mobile_crl = $crawler -> filter("#application_method_table") -> filter("td > dl.band_title");
			if ($mobile_crl -> count() > 0 ) {
				$text = $mobile_crl -> last() -> text();
				$mobile = Common::ExtractFirstJPMobile($text);
				$email = Common::ExtractEmailFromText($text);
			}

			$soluong = "";

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

			if (Common::IsNullOrEmpty($email) and Common::IsNullOrEmpty($mobile)){
				Common::AppendArrayToFile($job_data, $data_path.self::DODA_DATA_NO_CONTACT.'.csv', "|");
			} else{
				Common::AppendArrayToFile($job_data, $data_path.self::DODA_DATA.'.csv', "|");
			}
			return 0;
		}
	}
	
}