<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class UVTuyenDungComVnCrawler extends Controller{

	const TABLE = "crawler_uv_tuyendungcomvn";
	const TABLE_METADATA = "job_metadata";
	const TABLE_FILE_METADATA = "job_file_index";	
	const JOB_NAME = "uv_tuyendungcomvn";
	const TUYENDUNGCOMVN_DATA_PATH = 'candidates/tuyendungcomvn';
	const TUYENDUNGCOMVN_DATA = 'tuyendungcomvn-data';
	const TUYENDUNGCOMVN_DATA_NO_CONTACT = 'tuyendungcomvn-data-no-contact';
	const TUYENDUNGCOMVN_ERROR = 'tuyendungcomvn-error-';
	const TUYENDUNGCOMVN_LINK = 'tuyendungcomvn-link';
	const TUYENDUNGCOMVN_HOME = 'https://tuyendung.com.vn';
	const TUYENDUNGCOMVN_PAGE = 'https://tuyendung.com.vn/tuyendung/resumeresult.aspx?page=';
	const LABEL_SALARY = 'Mức lương';
	const LABEL_QUANTITY = 'Số lượng';
	const LABEL_DEADLINE = "Hạn nộp hồ sơ";
	const LABEL_PHONE = "Điện thoại";
	const LABEL_MOBILE = "Điện thoại riêng";
	const LABEL_ADDRESS = "Địa chỉ";
	const LABEL_DESCRIPTION = "Chi tiết công việc";
	const LABEL_COMPANY = "Nhà tuyển dụng";
	const DATE_FORMAT = "Ymd";
	const INPUT_DATE_FORMAT = "d M Y";
	const INPUT_DATE_FORMAT_CREATED = "m/d/Y";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 500;
	const PATTERN_DATE = '/\d{2}\-\d{2}\-\d{4}/';

	static $file_index = 0;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling candidates of TUYENDUNG.COM.VN ...");

		$database = env("DB_DATABASE");
		if ($database == null)  $database = Common::DB_DEFAULT;
		self::$file_index = Common::GetFileIndexToProcess($database, self::TABLE_FILE_METADATA, self::JOB_NAME);

		$client = $this->TuyenDungComvnLogin();

		while (true){
			try {
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

		Common::UpdateFileIndexAfterProcess($database, self::TABLE_FILE_METADATA, self::JOB_NAME);

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
			$fullname = "";
			$fullname_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_lblFullName');
			if ($fullname_crl->count() > 0){
				$fullname = $fullname_crl -> text();
			}
			
			$created = "";
			$created_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_lblUpdateDate');
			if ($created_crl->count() > 0){
				$created = $created_crl -> text();
			}
			$created = Common::RemoveSpaceChars($created);
			$created = Common::ConvertDateFormat($created, self::INPUT_DATE_FORMAT_CREATED, Common::DATE_DATA_FORMAT);

			$address = "";
			$address_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_lblAddress');
			if ($address_crl->count() > 0){
				$address = $address_crl -> text();
			}
			$address = Common::RemoveSpaceChars($address);

			$birthyear = "";
			$birthyear_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_lblDOB');
			if ($birthyear_crl->count() > 0){
				$birthyear = $birthyear_crl -> text();
				$birthyear = Common::RemoveSpaceChars($birthyear);
				$birthyear = Common::ConvertDateFormat($birthyear, self::INPUT_DATE_FORMAT, "Y");
			}

			$gender = "";
			$gender_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_lblGender');
			if ($gender_crl->count() > 0){
				$gender = $gender_crl -> text();
			}
			$gender = Common::RemoveSpaceChars($gender);

			$mobile = "";
			$mobile_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_lblMobile');
			if ($mobile_crl->count() > 0){
				$mobile = $mobile_crl -> text();
				$mobile = Common::ExtractFirstMobile($mobile);
			}

			$phone = "";
			$phone_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_lblTelephone');
			if ($phone_crl->count() > 0){
				$phone = $phone_crl -> text();
				$phone = Common::ExtractFirstMobile($phone);
			}
			if ($mobile === ""){
				$mobile = $phone;
			}
			$mobile = Common::UpdateMobilePrefix($mobile);

			$email = "";
			$email_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_lblEmail');
			if ($email_crl->count() > 0){
				$email = $email_crl -> text();
			}
			$email = Common::RemoveSpaceChars($email);

			$jobname = "";
			$jobname_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_lblJobPosition');
			if ($jobname_crl->count() > 0){
				$jobname = $jobname_crl -> text();
			} 
			$jobname = Common::RemoveTrailingChars($jobname);
	
			$salary = "";
			$salary_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_lblExpectedsalary');
			if ($salary_crl->count() > 0){
				$salary = $salary_crl -> text();
			}

			$description = "";
			$edu = "";
			$edu_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_divEducation');
			if ($edu_crl->count() > 0){
				$edu = $edu_crl -> text();
				$edu = Common::RemoveSpaceChars($edu);
			}
			$goal = "";
			$goal_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_divObjective');
			if ($goal_crl->count() > 0){
				$goal = $goal_crl -> text();
				$goal = Common::RemoveSpaceChars($goal);
			}
			$exp = "";
			$exp_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_divWorkingExp');
			if ($exp_crl->count() > 0){
				$exp = $exp_crl -> text();
				$exp = Common::RemoveSpaceChars($exp);
			}
			$skill = "";
			$skill_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_divSkills');
			if ($skill_crl->count() > 0){
				$skill = $skill_crl -> text();
				$skill = Common::RemoveSpaceChars($skill);
			}
			$description = $goal.". ".$edu.". ".$exp.". ".$skill.". ";
			$description = Common::RemoveTrailingChars($description);

			$type_of_work = "Toàn thời gian";
			$type_of_work_crl = $crawler -> filter('#ctl00_ContentPlaceHolder1_ctl26_lblWorkingType');
			if ($type_of_work_crl->count() > 0 and !Common::IsEmptyStr( $type_of_work_crl -> text())){
				$type_of_work = $type_of_work_crl -> text();
				$type_of_work = Common::RemoveSpaceChars($type_of_work);
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
				, $type_of_work
				, $url
			);
			if (Common::IsNullOrEmpty($email) and (Common::IsNullOrEmpty($mobile) or Common::isNotMobile($mobile))){
				Common::AppendArrayToFile($candidate_data, $data_path.self::TUYENDUNGCOMVN_DATA_NO_CONTACT.'.csv', "|");
			} else{
				if (Common::isNotMobile($mobile)){
					$candidate_data[0] = "";
				}
				Common::AppendArrayToFile($candidate_data, $data_path.self::TUYENDUNGCOMVN_DATA.'.csv', "|");
				Common::AppendArrayToFile($candidate_data, $data_path.self::TUYENDUNGCOMVN_DATA.'-'.self::$file_index.'.csv', "|");
			}
			return 0;
		}
	}
	
	public function TuyenDungComvnLogin(){
		$client = new Client;
		try{
			$crawler = $client -> request('GET', 'https://tuyendung.com.vn/tuyendung/login.aspx?');
			$button = $crawler -> selectButton('Đăng nhập');
			$form = $button -> form();
			$form['ctl00$ContentPlaceHolder1$txtEmail'] = 'itviecvietnam11@gmail.com';
			$form['ctl00$ContentPlaceHolder1$txtPassword'] = 'vanhoai1@';
			$form_crl = $client -> submit($form);
			return $client;
		} catch (\Exception $e) {
			$client = new Client;
			error_log('TuyenDungComvnLogin: '.($e -> getMessage ()));
			$file_name = public_path('data').self::SLASH.self::TUYENDUNGCOMVN_DATA_PATH.self::SLASH.self::TUYENDUNGCOMVN_ERROR.date(self::DATE_FORMAT).'.csv';
			Common::AppendStringToFile('Exception on login: '.($e -> getMessage ()), $file_name);
		}
		return $client;
	}
}