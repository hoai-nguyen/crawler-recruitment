<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class UVKenhTimViecCrawler extends Controller{

	const TABLE = "crawler_uv_kenhtimviec";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "uv_kenhtimviec";
	const KENHTIMVIEC_DATA_PATH = 'candidates/kenhtimviec';
	const KENHTIMVIEC_DATA = 'kenhtimviec-data';
	const KENHTIMVIEC_DATA_NO_CONTACT = 'kenhtimviec-data-no-contact';
	const KENHTIMVIEC_ERROR = 'kenhtimviec-error-';
	const KENHTIMVIEC_LINK = 'kenhtimviec-link';
	const KENHTIMVIEC_HOME = 'http://kenhtimviec.com';
	const KENHTIMVIEC_PAGE = 'http://kenhtimviec.com/tim-ung-vien/';
	
	const LABEL_ADDRESS = "Địa chỉ";
	const LABEL_DESCRIPTION = "Kỹ năng bản thân";
	const LABEL_FULLNAME = "Họ tên";
	const LABEL_BIRTHYEAR = "Ngày sinh";
	const LABEL_UPDATED = "Ngày cập nhật";
	const LABEL_MOBILE = "Điện thoại";
	const LABEL_GENDER = "Giới tính";
	const LABEL_SALARY = 'Mức lương mong muốn';
	const LABEL_JOBNAME = 'Công việc mong muốn';

	const DATE_FORMAT = "Ymd";
	const INPUT_DATE_FORMAT = "d-m-Y";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 150;
	const PATTERN_DATE = '/\d{2}\-\d{2}\-\d{4}/';

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling candidates of KENHTIMVIEC.COM ...");

		$client = $this->KenhTimViecLogin();
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->KenhTimViecCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				
				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::KENHTIMVIEC_DATA_PATH.self::SLASH.self::KENHTIMVIEC_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function KenhTimViecCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::KENHTIMVIEC_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::KENHTIMVIEC_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('#listPage > div.jobinfo > div.rightuser') -> filter('ul') -> filter('a');
				
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::KENHTIMVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::KENHTIMVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::KENHTIMVIEC_DATA_PATH.self::SLASH.self::KENHTIMVIEC_ERROR.date(self::DATE_FORMAT).'.csv';
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
										$full_link = self::KENHTIMVIEC_HOME.$job_link;
										$code = $this->CrawlJob($client, $full_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($full_link
												, $DATA_PATH.self::KENHTIMVIEC_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::KENHTIMVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('KenhTimViecCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::KENHTIMVIEC_DATA_PATH.self::SLASH.self::KENHTIMVIEC_ERROR.date(self::DATE_FORMAT).'.csv';
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
				, $data_path.self::KENHTIMVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::KENHTIMVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$info_crl = $crawler -> filter("div.company") -> filter('div.blockinfo') -> filter("ul");
			if ($info_crl->count() <= 0){
				Common::AppendStringToFile("Cannot request page: ".$url
					, $data_path.self::KENHTIMVIEC_ERROR.date(self::DATE_FORMAT).'.csv');
				return -1;
			}

			$personal_info = $info_crl -> first() -> filter("li.c2 p");
			$created = "";
			$fullname = "";
			$birthyear = "";
			$mobile = "";
			$gender = "";
			$address = "";
			foreach($personal_info as $node){
				$node_crl = new Crawler($node);
				$text = $node_crl -> text();
				$value_crl = $node_crl -> filter("strong");
				$value = "";
				if ($value_crl -> count() > 0){
					$value = $value_crl -> text();
				}

				if(strpos($text, self::LABEL_UPDATED) !== false){
					$created = $value;
					$created = Common::ConvertDateFormat($created, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);
				} else if (strpos($text, self::LABEL_FULLNAME) !== false){
					$fullname = $value;
					$fullname = Common::RemoveSpaceChars($fullname);
				} else if (strpos($text, self::LABEL_BIRTHYEAR) !== false){
					$birthyear = $value;
					$birthyear = Common::ConvertDateFormat($birthyear, Common::DATE_DATA_FORMAT, "Y");
				} else if (strpos($text, self::LABEL_GENDER) !== false){
					$gender = $value;
					$gender = Common::RemoveSpaceChars($gender);
				} else if (strpos($text, self::LABEL_ADDRESS) !== false){
					$address = $value;
					$address = Common::RemoveSpaceChars($address);
				} else if (strpos($text, self::LABEL_MOBILE) !== false){
					$mobile = $value;
					$mobile = Common::ExtractFirstMobile($mobile);
					$mobile = Common::UpdateMobilePrefix($mobile);
				}
			}
			
			$des_crl = $crawler -> filter("div.listGJ");
			$job_des = $des_crl -> eq(1) -> filter("div.blockinfo > ul");
			$jobname= "";
			$salary = "";
			foreach($job_des as $node){
				$node_crl = new Crawler($node);
				$text = $node_crl -> filter("li.c1") -> text();
				$value = $node_crl -> filter("li.c2") -> text();
				if(strpos($text, self::LABEL_JOBNAME) !== false){
					$jobname = $value;
					$jobname = Common::RemoveSpaceChars($jobname);
				} else if (strpos($text, self::LABEL_SALARY) !== false){
					$salary = $value;
					$salary = Common::RemoveSpaceChars($salary);
					break;
				}
			}

			$description_crl = $crawler -> filter("div.boxCT-in");
			$description = "";
			foreach($description_crl as $node){
				$node_crl = new Crawler($node);
				$h3 = $node_crl -> filter("h3") -> first() -> text();
				if (strpos($h3, self::LABEL_DESCRIPTION) !== false){
					$skill_label = $node_crl -> filter("li.c1") -> text();
					$skill_text = $node_crl -> filter("li.c2") -> text();
					$skill_text = Common::RemoveSpaceChars($skill_text);
					$description = $skill_label.": ".$skill_text;
					break;
				}
			}
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
				Common::AppendArrayToFile($candidate_data, $data_path.self::KENHTIMVIEC_DATA_NO_CONTACT.'.csv', "|");
			} else{
				if (Common::isNotMobile($mobile)){
					$candidate_data[0] = "";
				}
				Common::AppendArrayToFile($candidate_data, $data_path.self::KENHTIMVIEC_DATA.'.csv', "|");
			}
			return 0;
		}
	}
	
	public function KenhTimViecLogin(){
		$client = new Client;
		try{
			$crawler = $client -> request('GET', 'http://kenhtimviec.com/nha-tuyen-dung.html');
			$button = $crawler -> selectButton('Đăng nhập');
			$form = $button -> form();
			$form['user'] = 'itviecvietnam11@gmail.com';
			$form['pass'] = 'vanhoai1@';
			$form_crl = $client -> submit($form);
			return $client;
		} catch (\Exception $e) {
			$client = new Client;
			error_log('KenhTimViecLogin: '.($e -> getMessage ()));
			$file_name = public_path('data').self::SLASH.self::KENHTIMVIEC_DATA_PATH.self::SLASH.self::KENHTIMVIEC_ERROR.date(self::DATE_FORMAT).'.csv';
			Common::AppendStringToFile('Exception on login: '.($e -> getMessage ()), $file_name);
		}
		return $client;
	}
}