<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class UVLaoDongCrawler extends Controller{

	const TABLE = "crawler_uv_laodong";
	const TABLE_METADATA = "job_metadata";
	const TABLE_FILE_METADATA = "job_file_index";
	const JOB_NAME = "uv_laodong";
	const LAODONG_DATA_PATH = 'candidates/laodong';
	const LAODONG_DATA = 'laodong-data';
	const LAODONG_DATA_NO_CONTACT = 'laodong-data-no-contact';
	const LAODONG_ERROR = 'laodong-error-';
	const LAODONG_LINK = 'laodong-link';
	const LAODONG_HOME = 'http://vieclam.laodong.com.vn';
	const LAODONG_PAGE = 'http://vieclam.laodong.com.vn/ung-vien/?page=';
	const LABEL_SALARY = 'Mức lương:';
	const LABEL_QUANTITY = 'Số lượng cần tuyển:';
	const LABEL_DEADLINE = "Hạn nộp hồ sơ:";
	const LABEL_DEAL = "Thỏa thuận";
	const DATE_FORMAT = "Ymd";
	const DATA_DATE_FORMAT = "Y-m-d";
	const INPUT_DATE_FORMAT = "d/m/Y";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 500;

	static $file_index = 0;

	public function CrawlerStarter(){
		$start = microtime(true);
		error_log("Start crawling candidates of LAODONG ...");

		$database = env("DB_DATABASE");
		if ($database == null)  $database = Common::DB_DEFAULT;
		self::$file_index = Common::GetFileIndexToProcess($database, self::TABLE_FILE_METADATA, self::JOB_NAME);

		$client = new Client();
		
		while (true){
			try {
				$new_batch = Common::FindNewBatchToProcess($database, self::TABLE_METADATA, self::JOB_NAME);
				if ($new_batch == null) break;
				
				$return_code = $this->LaoDongCrawlerFunc($client, $new_batch -> start_page, $new_batch -> end_page);
				if ($return_code > 1) break;
				if($new_batch -> start_page >= self::MAX_PAGE) break;
			} catch (\Exception $e) {
				error_log($e -> getMessage());
				$file_name = public_path('data').self::SLASH.self::LAODONG_DATA_PATH.self::SLASH.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv';
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
	
	public function LaoDongCrawlerFunc($client, $start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::LAODONG_DATA_PATH.self::SLASH;

		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			$page_start = microtime(true);
			error_log("Page = ".$x);
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::LAODONG_PAGE.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('section.search-list-zone > div.zone-content') -> filter("h3.name > a");
				if ($jobs -> count() <= 0) {
					Common::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						Common::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::LAODONG_DATA_PATH.self::SLASH.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv';
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
										$full_link = self::LAODONG_HOME.$job_link;
										$code = $this->CrawlJob($client, $full_link, $DATA_PATH);
										if ($code == 0)
											Common::AppendStringToFile($full_link
												, $DATA_PATH.self::LAODONG_LINK.'.csv');
									}
								} catch (\Exception $e) {
									error_log('Crawl each link: '.($e -> getMessage ()));
									Common::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				error_log('LaoDongCrawlerFunc: '.($e -> getMessage ()));
				$file_name = public_path('data').self::SLASH.self::LAODONG_DATA_PATH.self::SLASH.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv';
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
		// $url = "http://vieclam.laodong.com.vn/nguoi-lao-dong/nguyen-thi-thanh-tuyen-12502.html";
		$job_start = microtime(true);
		
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			Common::AppendStringToFile("Cannot request page: ".$url.": ".substr($e -> getMessage (), 0, 1000)
				, $data_path.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}
		echo 'Time= '.(microtime(true) - $job_start).' secs<br>';

		if ($crawler -> count() <= 0 ) {
			Common::AppendStringToFile("Cannot request page: ".$url
				, $data_path.self::LAODONG_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		} else{
			$fullname = "";
			$fullname_crl = $crawler -> filter('#cphMainContent_lblFullName');
			if ($fullname_crl->count() > 0){
				$fullname = $fullname_crl -> text();
			}
			
			$address = "";
			$address_crl = $crawler -> filter('#cphMainContent_lblAddress');
			if ($address_crl->count() > 0){
				$address = $address_crl -> text();
			}
			$address = Common::RemoveSpaceChars($address);

			$birthyear = "";
			$birthyear_crl = $crawler -> filter('#cphMainContent_lblDOB');
			if ($birthyear_crl->count() > 0){
				$birthyear = $birthyear_crl -> text();
				$birthyear = Common::RemoveSpaceChars($birthyear);
				$birthyear = Common::ConvertDateFormat($birthyear, self::INPUT_DATE_FORMAT, "Y");
			}

			$gender = "";
			$gender_crl = $crawler -> filter('#cphMainContent_lblGender');
			if ($gender_crl->count() > 0){
				$gender = $gender_crl -> text();
			}
			$gender = Common::RemoveSpaceChars($gender);

			$mobile = "";
			$mobile_crl = $crawler -> filter('#cphMainContent_lblPhone');
			if ($mobile_crl->count() > 0){
				$mobile = $mobile_crl -> text();
				$mobile = Common::ExtractFirstMobile($mobile);
			}
			$mobile = Common::UpdateMobilePrefix($mobile);

			$email = "";
			$email_crl = $crawler -> filter('#cphMainContent_lblEmail');
			if ($email_crl->count() > 0){
				$email = $email_crl -> text();
			}
			$email = Common::RemoveSpaceChars($email);

			$jobname = "";
			$jobname_crl = $crawler -> filter('#cphMainContent_lblExpectedPosition');
			if ($jobname_crl->count() > 0){
				$jobname = $jobname_crl -> text();
			} 
			$jobname = Common::RemoveTrailingChars($jobname);
	
			$salary = "";
			$salary_crl = $crawler -> filter('#cphMainContent_lblExpectedSalaryRangeID');
			if ($salary_crl->count() > 0){
				$salary = $salary_crl -> text();
			}

			$description = "";
			$edu = "";
			$edu_crl = $crawler -> filter('#cphMainContent_lblSpecialized');
			if ($edu_crl->count() > 0){
				$edu = $edu_crl -> text();
				$edu = Common::RemoveSpaceChars($edu);
			}
			$more_info = "";
			$more_info_crl = $crawler -> filter('#cphMainContent_lblMoreInfo');
			if ($more_info_crl->count() > 0){
				$more_info = $more_info_crl -> text();
				$more_info = Common::RemoveSpaceChars($more_info);
			}
			$exp = "";
			$exp_crl = $crawler -> filter('#cphMainContent_lblExperiences');
			if ($exp_crl->count() > 0){
				$exp = $exp_crl -> text();
				$exp = Common::RemoveSpaceChars($exp);
			}
			$skill = "";
			$skill_crl = $crawler -> filter('#cphMainContent_lblQualificationID');
			if ($skill_crl->count() > 0){
				$skill = $skill_crl -> text();
				$skill = Common::RemoveSpaceChars($skill);
			}
			if (strlen($edu) > 0){
				$description = $description."Ngành nghề đào tạo: ".$edu.". ";
			}
			if (strlen($skill) > 0){
				$description = $description."Trình độ chuyên môn - kỹ thuật: ".$skill.". ";
			}
			if (strlen($exp) > 0){
				$description = $description."Kinh nghiệm làm việc: ".$exp.". ";
			}
			if (strlen($more_info) > 0){
				$description = $description."Mô tả thêm: ".$more_info.". ";
			}
			
			$description = Common::RemoveTrailingChars($description);
			
			$created = "";
			$type_of_work = "Toàn thời gian";

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
			// dd($candidate_data);
			if (Common::IsNullOrEmpty($email) and (Common::IsNullOrEmpty($mobile) or Common::isNotMobile($mobile))){
				Common::AppendArrayToFile($candidate_data, $data_path.self::LAODONG_DATA_NO_CONTACT.'.csv', "|");
			} else{
				if (Common::isNotMobile($mobile)){
					$candidate_data[0] = "";
				}
				Common::AppendArrayToFile($candidate_data, $data_path.self::LAODONG_DATA.'.csv', "|");
				Common::AppendArrayToFile($candidate_data, $data_path.self::LAODONG_DATA.'-'.self::$file_index.'.csv', "|");
			}
			return 0;
		}
	}
	
}