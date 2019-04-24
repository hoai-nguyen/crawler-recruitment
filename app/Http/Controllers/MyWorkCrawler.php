<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class MyWorkCrawler extends Controller{

	const TABLE = "mywork";
	const TABLE_METADATA = "job_metadata";
	const TABLE_FILE_METADATA = "job_file_index";
	const JOB_NAME = "mywork";
	const MYWORK_DATA_PATH = 'mywork'; 
	const MYWORK_DATA = 'mywork-data';
	const MYWORK_DATA_NO_CONTACT = 'mywork-data-no-contact';
	const MYWORK_ERROR = 'mywork-error-';
	const MYWORK_LINK = 'mywork-link';
	const MYWORK_HOME = 'https://mywork.com.vn';
	const LABEL_CONTACT = 'Người liên hệ';
	const LABEL_DEADLINE = 'Hạn nộp hồ sơ';
	const LABEL_QUANTITY = 'Số lượng cần tuyển';
	const LABEL_APPROVER = 'Ngày duyệt';
	const DATE_FORMAT = "Ymd";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 1000;

	static $file_index = 0;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling MyWork ...");

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
				$pageUrl = self::MYWORK_HOME.'/tuyen-dung/trang/'.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('div.box-body > div.item-list') -> first() -> filter('a.item');

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
		// $job_start = microtime(true);
		$client = new Client;
		// echo 'create client: '.(microtime(true) - $job_start).' secs, ';
		$crawler = $client -> request('GET', $url);
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';

		$content_crawler = $crawler -> filter('div.detail_job');
		if ($content_crawler -> count() <= 0 ) {
			Common::AppendStringToFile("ERROR: Failed to crawl ".$url
			, $data_path.self::MYWORK_ERROR.date(self::DATE_FORMAT).'.csv');
		} else{
			$content = $content_crawler -> first();
			// $header_start = microtime(true);
			$job_title = "";
			$title_crawler = $crawler -> filter('h1.main-title > span');
			if ($title_crawler -> count() > 0 ) {
				$job_title = $title_crawler -> first() -> text();
			}
			$job_title = Common::RemoveTrailingChars($job_title);
			// echo 'header: '.(microtime(true) - $header_start).' secs, ';

			// $posted_start = microtime(true);
			$created = "";
			$created_infos = $content -> filter('div.content > p');
			if ($created_infos -> count() > 0 ) {
                foreach ($created_infos as $node) {
                    $created_crawler = new Crawler($node);
					$label_crawler = $created_crawler -> filter('strong');
					if ($label_crawler -> count() > 0){
						$label = $label_crawler -> first() -> text();
						if (strpos($label, self::LABEL_APPROVER) !== false){
							$created = $created_crawler -> first() -> text();
							break;
						}
					}
                }
			}
			if (strpos($created, ':') !== false){
				$created = trim(explode(":", $created)[1], "\r\n ");
			}
			// echo 'posted time: '.(microtime(true) - $posted_start).' secs, ';
			
			// $company_start = microtime(true);
            $company = "";
			$company_crawler = $content -> filter('p.company-name > a > strong');
			if ($company_crawler -> count() > 0 ) {
				$company = $company_crawler -> first() -> text();
			}
			$company = Common::RemoveTrailingChars($company);
			// echo 'company: '.(microtime(true) - $company_start).' secs, ';

			// $address_start = microtime(true);
			$address = "";
			$address_crawler = $content -> filter('p.address > span');
			if ($address_crawler -> count() > 0 ) {
				$address = $address_crawler -> first() -> text();
			}
			$address = Common::RemoveTrailingChars($address);
			// echo 'address: '.(microtime(true) - $address_start).' secs, ';

            // $salary_start = microtime(true);
            $salary = '';
            $salary_crawler = $content -> filter('span.text_red');
            if ($salary_crawler -> count() > 0 ) {
				$salary = $salary_crawler -> first() -> text();
            }
            $salary = trim(preg_replace("/[\r\n]*/", "", $salary), " ");
            $salary = str_replace("  ", "", $salary);
            // echo 'salary: '.(microtime(true) - $salary_start).' secs, ';
            
            // $website_start = microtime(true);
			$website = '';
			$website_crawler = $content -> filter('p.company-name > a');
			if ($website_crawler -> count() > 0 ) {
                $ref = $website_crawler -> first() -> attr('href');
                $website = self::MYWORK_HOME.$ref;
            }
            // echo 'website: '.(microtime(true) - $website_start).' secs, ';
            
            // $soluong_start = microtime(true);
            $soluong = "";
			$general_infos = $content -> filter('div.job_detail_general > div.item1 > p');
			if ($general_infos -> count() > 0 ) {
                foreach ($general_infos as $node) {
                    $soluong_crawler = new Crawler($node);
                    $label = $soluong_crawler -> filter('strong') -> first() -> text();
                    if (strpos($label, self::LABEL_QUANTITY) !== false){
                        $soluong = $soluong_crawler -> first() -> text();
                        break;
                    }
                }
			}
			if (strpos($soluong, ':') !== false){
				$soluong = trim(explode(":", $soluong)[1], "\r\n ");
			}
			// echo 'soluong: '.(microtime(true) - $soluong_start).' secs, ';

            // $deadjob_data_start = microtime(true);
            $contact = "";
            $deadline = '';
            $contact_infos = $content -> filter('div.box-contact > div.row');
			if ($contact_infos -> count() > 0 ) {
                foreach ($contact_infos as $node) {
                    $contact_crawler = new Crawler($node);
                    
                    $label = $contact_crawler -> filter('div.label-contact');
                    if ($label -> count() > 0){
                        $label = $label -> first() -> text();
                        if (strpos($label, self::LABEL_CONTACT) !== false){
                            $contact = $contact_crawler -> filter('div') -> last() -> text();
                        } else if (strpos($label, self::LABEL_DEADLINE) !== false){
                            $deadline = $contact_crawler -> filter('div') -> last() -> text();
                            $deadline = preg_replace("/[\r\n ]*/", "", $deadline);
                        }
                    }
                }
			}
			$deadline = substr($deadline, 0, 10);
			if (Common::IsJobExpired(Common::DEFAULT_DEADLINE, $deadline)){
				return 2;
			}

			// $job_des 
			$jds = $content -> filter('div.multiple > div.mw-box-item');
			$job_des = "";
			$idx = 0;
			if ($jds -> count() > 0){
				foreach ($jds as $node) {
					$jd = new Crawler($node);
					if ($idx == 2){
						$job_des = $jd -> text();
						break;
					}
					$idx++;
				}
			}
			$email = Common::ExtractEmailFromText($contact);
			if ($email == ""){
				$email = Common::ExtractEmailFromText($job_des);
			}
			if (Common::EndWithUpper($email)){
				$email = substr($email, 0, -1);
			}
			$job_des =Common::RemoveTrailingChars($job_des);

			$mobile = Common::ExtractFirstMobile($contact);

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
				Common::AppendArrayToFile($job_data, $data_path.self::MYWORK_DATA_NO_CONTACT.'.csv', "|");
			} else{
				if (Common::isNotMobile($mobile)){
					$job_data[0] = "";
				}
				Common::AppendArrayToFile($job_data, $data_path.self::MYWORK_DATA.'.csv', "|");
				Common::AppendArrayToFile($job_data, $data_path.self::MYWORK_DATA.'-'.self::$file_index.'.csv', "|");
			}
		}
	}

}