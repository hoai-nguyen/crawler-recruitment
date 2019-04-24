<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class UVMyWorkCrawler extends Controller{

	const TABLE = "crawler_uv_mywork";
	const TABLE_METADATA = "job_metadata";
	const TABLE_FILE_METADATA = "job_file_index";
	const JOB_NAME = "uv_mywork";
	const MYWORK_DATA_PATH = 'candidates/mywork'; 
	const MYWORK_DATA = 'mywork-data';
	const MYWORK_DATA_NO_CONTACT = 'mywork-data-no-contact';
	const MYWORK_ERROR = 'mywork-error-';
	const MYWORK_LINK = 'mywork-link';
	const MYWORK_HOME = 'https://mywork.com.vn';
	const MYWORK_PAGE = 'https://mywork.com.vn/ung-vien/trang/';
	const LABEL_CREATED = "Ngày cập nhật:";
	const LABEL_SALARY = "Mức lương mong muốn:";
	const DATE_FORMAT = "Ymd";
	const INPUT_DATE_FORMAT = "d/m/Y";
	const PATTERN_DATE = '/\d{1,2}\/\d{1,2}\/\d{4}/';
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 1000;

	static $file_index = 0;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling candidats of MyWork ...");

		$database = env("DB_DATABASE");
		if ($database == null)  $database = Common::DB_DEFAULT;
		self::$file_index = Common::GetFileIndexToProcess($database, self::TABLE_FILE_METADATA, self::JOB_NAME);

		while (true){
			try {
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;

				$return_code = $this->MyWorkPageCrawler($new_batch -> start_page, $new_batch -> end_page);

				if ($return_code > 1) break;

				if($new_batch -> start_page >= self::MAX_PAGE) break;

			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::MYWORK_DATA_PATH.self::SLASH.self::MYWORK_ERROR.date(self::DATE_FORMAT).'.csv';
				Common::AppendStringToFile('Exception on starter: '.substr($e -> getMessage(), 0, 1000), $file_name);
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

    public function MyWorkPageCrawler($start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::MYWORK_DATA_PATH.self::SLASH;
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
				$pageUrl = self::MYWORK_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('#idCandidateNew') -> first() -> filter('h5.item-title > a.capitalize');
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::MYWORK_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::MYWORK_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::MYWORK_DATA_PATH.self::SLASH.self::MYWORK_ERROR.date(self::DATE_FORMAT).'.csv';
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
										$full_link = self::MYWORK_HOME.$job_link;
										$this->CrawlJob($full_link, $DATA_PATH);
										
										Common::AppendStringToFile($full_link
										, $DATA_PATH.self::MYWORK_LINK.'.csv');
			
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::MYWORK_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('MyWorkCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::MYWORK_DATA_PATH.self::SLASH.self::MYWORK_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
    public function CrawlJob($url, $data_path){
		// $url = "https://mywork.com.vn/ho-so/2345396/nhan-vien-ban-hang-nhan-vien-ky-thuat.html";
		$job_start = microtime(true);
		$client = new Client;
		$crawler = $client -> request('GET', $url);

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("ERROR: Failed to crawl ".$url
			, $data_path.self::MYWORK_ERROR.date(self::DATE_FORMAT).'.csv');
		} else{
			$basic_info_crl = $crawler -> filter('div.info-basic > div');
			$jobname = "";
			$title_crawler = $basic_info_crl -> filter('h1.sub-title');
			if ($title_crawler -> count() > 0 ) {
				$jobname = $title_crawler -> first() -> text();
			}
			$jobname = Common::RemoveSpaceChars($jobname);
			
			$fullname = "";
			$fullname_crl = $basic_info_crl -> filter('h2.title');
			if ($fullname_crl -> count() > 0 ) {
				$fullname = $fullname_crl -> first() -> text();
			}
			$fullname = Common::RemoveSpaceChars($fullname);

			$birthyear = "";
			$address = "";
			$gender = "";
			$birthyear_crl = $basic_info_crl -> filter('div.row') -> filter("p");
			if ($birthyear_crl -> count() >= 3 ) {
				$birthyear = $birthyear_crl -> eq(0) -> text();
				$birthyear = Common::RemoveSpaceChars($birthyear);
				$birthyear = Common::ConvertDateFormat($birthyear, self::INPUT_DATE_FORMAT, "Y");
				$address = $birthyear_crl -> eq(1) -> text();
				$gender = $birthyear_crl -> eq(2) -> text();
			}
			$birthyear = Common::RemoveSpaceChars($birthyear);
			$address = Common::RemoveSpaceChars($address);
			$gender = Common::RemoveSpaceChars($gender);

			$created = "";
			$salary = "";
			$created_infos = $crawler -> filter('div.common-info > div.content') -> first() -> filter("li");
			if ($created_infos -> count() > 0 ) {
                foreach ($created_infos as $node) {
                    $created_crawler = new Crawler($node);
					$label = $created_crawler -> first() -> text();
					if (strpos($label, self::LABEL_SALARY) !== false){
						$text = $created_crawler -> first() -> text();
						$text = Common::RemoveSpaceChars($text);
						if (strpos($text, ':') !== false){
							$salary = explode(":", $text)[1];
						}
						$salary = Common::RemoveSpaceChars($salary);
					} else if (strpos($label, self::LABEL_CREATED) !== false){
						$text = $created_crawler -> first() -> text();
						$created = Common::ExtractDateFromText(self::PATTERN_DATE, $text);
					}
                }
			}
			
			$description = "";
			$description_crl = $crawler -> filter('div.common-info');
			if ($description_crl -> count() > 0 ) {
				$idx = 0;
                foreach ($description_crl as $node) {
					if ($idx > 0 and $idx < 5){
						$des = new Crawler($node);
						$label = $des -> filter("h3.head-title") -> text();
						$content = $des -> filter("div.content") -> text();
						$label = Common::RemoveSpaceChars($label);
						$content = Common::RemoveSpaceChars($content);
						$description = $description.$label.": ".$content.". ";
					}
					$idx++;
				}
			}
			$description = Common::RemoveTrailingChars($description);

			$mobile = "";
			$email = "";

			$candidate_data = array($mobile
				, $email
				, $fullname
				, $address
				, $jobname
				, $salary
				, $birthyear
                , $gender
                , $description
				, $created
				, $url
			);
			if (Common::IsNullOrEmpty($email) and (Common::IsNullOrEmpty($mobile) or Common::isNotMobile($mobile))){
				Common::AppendArrayToFile($candidate_data, $data_path.self::MYWORK_DATA_NO_CONTACT.'.csv', "|");
			} else{
				if (Common::isNotMobile($mobile)){
					$candidate_data[0] = "";
				}
				Common::AppendArrayToFile($candidate_data, $data_path.self::MYWORK_DATA.'.csv', "|");
				Common::AppendArrayToFile($candidate_data, $data_path.self::MYWORK_DATA.'-'.self::$file_index.'.csv', "|");
			}
		}
	}

}