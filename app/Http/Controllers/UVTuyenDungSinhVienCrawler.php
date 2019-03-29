<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class UVTuyenDungSinhVienCrawler extends Controller{

	const TABLE = "crawler_uv_tuyendungsinhvien";
	const TABLE_METADATA = "job_metadata";
	const JOB_NAME = "uvtuyendungsinhvien";
	const TUYENDUNGSINHVIEN_DATA_PATH = 'candidates/tuyendungsinhvien';
	const TUYENDUNGSINHVIEN_DATA = 'tuyendungsinhvien-data';
	const TUYENDUNGSINHVIEN_DATA_NO_CONTACT = 'tuyendungsinhvien-data-no-contact';
	const TUYENDUNGSINHVIEN_ERROR = 'tuyendungsinhvien-error-';
	const TUYENDUNGSINHVIEN_LINK = 'tuyendungsinhvien-link';
	const TUYENDUNGSINHVIEN_HOME = 'http://tuyendungsinhvien.com';
	const TUYENDUNGSINHVIEN_PAGE = 'http://tuyendungsinhvien.com/doanh-nghiep-tuyen-dung/search.php?offset=';
	const LABEL_FULLNAME = 'Họ và Tên';
	const LABEL_JOBNAME = 'Vị Trí Công Việc';
	const LABEL_EMAIL = 'Email';
	const LABEL_SALARY = 'Mức lương mong muốn';
	const LABEL_BIRTHYEAR = 'Ngày sinh';
	const LABEL_GENDER = "Giới tính";
	const LABEL_MOBILE = "Điện thoại di động";
	const LABEL_ADDRESS = "Tìm việc tại";
	const LABEL_DESCRIPTION = "Kinh nghiệm";
	const LABEL_SCHOOL = "Tên trường";
	const DATE_FORMAT = "Ymd";
	const INPUT_DATE_FORMAT = "d-m-Y";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 500;
	const PATTERN_DATE = '/\d{2}\-\d{2}\-\d{4}/';

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling UV-TUYENDUNGSINHVIEN ...");

		$client = $this -> TuyenDungSinhVienLogin();
		while (true){
			try {
				$database = env("DB_DATABASE");
				if ($database == null)  $database = Common::DB_DEFAULT;
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->TuyenDungSinhVienCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				
				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::TUYENDUNGSINHVIEN_DATA_PATH.self::SLASH.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function TuyenDungSinhVienCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::TUYENDUNGSINHVIEN_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$offset = 12*($x-1);
				$pageUrl = self::TUYENDUNGSINHVIEN_PAGE.$offset;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('#resumelist') -> filter('tr');
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv');
						return 2;
					}
					$last_page_is_empty = true;
				} else{
					$last_page_is_empty = false;
				
					$job_infos = $jobs -> each(
						function ($job){
							$tds = $job -> filter("td");
							if ($tds -> count() > 4){
								$code = $tds -> eq(1) -> text();
								$code = Common::RemoveSpaceChars($code);
								$created_text = $tds -> eq(2) -> text();
								$created = Common::ExtractCreatedDateFromText($created_text);
								$link = $tds -> eq(3) -> filter("a") -> attr("href");
								if (strlen($created) > 0){
									$created = Common::ConvertDateFormat($created, self::INPUT_DATE_FORMAT, Common::DATE_DATA_FORMAT);
									return (object) array("job_code"=>$code, "created"=>$created, "link"=>$link);
								}
							}
						}
					);
					$job_infos = array_filter($job_infos, function($value) { return $value != null; });
					$job_codes = array();
					foreach($job_infos as $job_info){
						array_push($job_codes, $job_info -> job_code);
					}
					$existing_codes = Common::CheckLinksExist($job_codes, env("DB_DATABASE"), self::TABLE);
					$duplicated_codes = array();
					if ($existing_codes != null){
						foreach($existing_codes as $row){
							$link = $row -> link;
							array_push($duplicated_codes, $link);
						}
					}
					
					// deduplicate
					$new_codes = array_diff($job_codes, $duplicated_codes);
					if (is_array($new_codes) and sizeof($new_codes) > 0){
						error_log(sizeof($new_codes)." new links.");

						$inserted = Common::InsertLinks($new_codes, env("DB_DATABASE"), self::TABLE);
						if ($inserted){
							// crawl each link
							foreach ($job_infos as $job_info) {
								try {
									ini_set('max_execution_time', 10000000);				
									$code = $job_info->job_code;
									$job_link = $job_info->link;
									$created = $job_info->created;
									if (in_array($code, $new_codes) and $job_link != null){
										$full_link = self::TUYENDUNGSINHVIEN_HOME.$job_link;
										$code = $this -> CrawlJob($client, $created, $full_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($full_link
												, $DATA_PATH.self::TUYENDUNGSINHVIEN_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('TuyenDungSinhVienCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::TUYENDUNGSINHVIEN_DATA_PATH.self::SLASH.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv';
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

    public function CrawlJob($client, $created, $url, $data_path){
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			Common::AppendStringToFile("Cannot request page: ".$url.": ".substr($e -> getMessage (), 0, 1000)
				, $data_path.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}
		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			if ($crawler -> filter('#dynamic_form') -> count() <= 0){
				Common::AppendStringToFile("No content: ".$url
					, $data_path.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv');
				return -1;
			}

			$fullname = "";
			$jobname = "";
			$gender = "";
			$birthyear = "";
			$email = "";
			$salary = "";
			$mobile = "";
			$address = "";
			$description = "";
			$school = "";
			$job_details_crl = $crawler -> filter('#dynamic_form') -> filter('tr');
			if ($job_details_crl -> count() > 0){
				foreach($job_details_crl as $item){
					$node = new Crawler($item);
					$label = $node -> filter('td.dynamic_form_field');
					if ($label -> count() > 0){
						$label_text = $label -> first() -> text();
						$value = $node -> filter('td.dynamic_form_value') -> text();
						if (strpos($label_text, self::LABEL_FULLNAME) !== false){
							$fullname = Common::RemoveSpaceChars($value);
						} else if (strpos($label_text, self::LABEL_JOBNAME) !== false){
							$jobname = Common::RemoveSpaceChars($value);
						} else if (strpos($label_text, self::LABEL_GENDER) !== false){
							$gender = Common::RemoveSpaceChars($value);
						} else if (strpos($label_text, self::LABEL_BIRTHYEAR) !== false){
							$birthyear = Common::RemoveSpaceChars($value);
							$birthyear = Common::ConvertDateFormat($birthyear, self::INPUT_DATE_FORMAT, "Y");
						} else if(strpos($label_text, self::LABEL_EMAIL) !== false){
							$value = Common::RemoveSpaceChars($value);
							if (strpos($value, "gmail") !== false){
								$parts = explode("gmail", $value);
								$email = $parts[0]."@gmail.com"; 
							} else{
								$email = $value;
							}
						} else if (strpos($label_text, self::LABEL_MOBILE) !== false){
							$mobile = Common::ExtractFirstMobile($value);
							$mobile = Common::UpdateMobilePrefix($mobile);
						} else if (strpos($label_text, self::LABEL_SCHOOL) !== false){
							$school = Common::RemoveSpaceChars($value);
						} else if (strpos($label_text, self::LABEL_DESCRIPTION) !== false){
							$description = Common::RemoveSpaceChars($value);
							$description = self::LABEL_DESCRIPTION.": ".$description;
						} else if (strpos($label_text, self::LABEL_SALARY) !== false){
							$salary = Common::RemoveSpaceChars($value);
						} else if (strpos($label_text, self::LABEL_ADDRESS) !== false){
							$address = Common::RemoveSpaceChars($value);
						} 
					}
				}
			}
			if (strlen($school) > 0){
				$description = $description.". ".$school;
			}
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
				Common::AppendArrayToFile($candidate_data, $data_path.self::TUYENDUNGSINHVIEN_DATA_NO_CONTACT.'.csv', "|");
			} else{
				if (Common::isNotMobile($mobile)){
					$candidate_data[0] = "";
				}
				Common::AppendArrayToFile($candidate_data, $data_path.self::TUYENDUNGSINHVIEN_DATA.'.csv', "|");
			}
			
			return 0;
		}
	}
	
	public function TuyenDungSinhVienLogin(){
		$client = new Client;
		try{
			$crawler = $client -> request('GET', 'http://tuyendungsinhvien.com/doanh-nghiep-tuyen-dung/index.php');
			$form = $crawler -> selectButton('Đăng nhập') -> form();
			$form['username'] = 'benblack1368';
			$form['password'] = 'vanhoai1@';
			$form_crl = $client -> submit($form);
			return $client;
		} catch (\Exception $e) {
			$client = new Client;
			error_log('TuyenDungSinhVien: '.($e -> getMessage ()));
			$file_name = public_path('data').self::SLASH.self::TUYENDUNGSINHVIEN_DATA_PATH.self::SLASH.self::TUYENDUNGSINHVIEN_ERROR.date(self::DATE_FORMAT).'.csv';
			Common::AppendStringToFile('Exception in login: '.($e -> getMessage ()), $file_name);
		}
		return $client;
	}

}