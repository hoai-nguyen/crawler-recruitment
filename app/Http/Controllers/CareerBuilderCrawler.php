<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
// use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;
use Illuminate\Support\Facades\DB;
use \DateTime;

class CareerBuilderCrawler extends Controller{

	const TABLE = "careerbuilder";
	const JOB_NAME = "careerbuilder";
	const CAREERBUILDER_DATA_PATH = 'careerbuilder'; // CI must create directory in
	const CAREERBUILDER_DATA = 'careerbuilder-data';
	const CAREERBUILDER_ERROR = 'careerbuilder-error-';
	const CAREERBUILDER_LINK = 'careerbuilder-link';
	const CAREERBUILDER_HOME = 'https://www.careerbuilder.vn';
	const CAREERBUILDER_PAGE = 'https://careerbuilder.vn/viec-lam/tat-ca-viec-lam-trang-';
	const CAREERBUILDER_PAGE_END = '-vi.html';
	const LABEL_SALARY = "Lương:";
	const LABEL_DEADLINE = "Hết hạn nộp:";
	const DATE_FORMAT = "Ymd";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 1000;
	const EMAIL_PATTERN = "/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i";
	const PHONE_PATTERN = "!\d+!";

	public function CrawlerStarter(){
		error_log('Start crawling...');

		$start = microtime(true);
		$x = 0;
		while (true){
			try {
				$new_batch = $this->FindNewBatchToProcess("phpmyadmin", "job_metadata", self::JOB_NAME);
				if ($new_batch == null){
					break;
				}
				error_log("Batch: ".$new_batch->start_page." - ".$new_batch->end_page);
				$return_code = $this->CareerBuilderPageCrawler($new_batch -> start_page, $new_batch -> end_page);
				
				// if ($return_code > 1) {
				// 	 $this->ResetJobMetadata("phpmyadmin", "job_metadata", self::TABLE);
				// 	break;
				// }

				if ($new_batch -> end_page > self::MAX_PAGE){ // du phong
					break;
				}
			} catch (\FatalErrorException $e) {
				$file_name = public_path('data').self::SLASH.self::CAREERBUILDER_DATA_PATH.self::SLASH.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv';
				 $this->AppendStringToFile('Ex on starter: '.substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			} catch (\Throwable $e) {
				$file_name = public_path('data').self::SLASH.self::CAREERBUILDER_DATA_PATH.self::SLASH.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv';
				 $this->AppendStringToFile('Ex on starter: '.substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}
			$x++;
			if ($x > 500){
				break;
			}
		}

		$time_elapsed_secs = microtime(true) - $start;
		echo '<b>Total Execution Time:</b> '.$time_elapsed_secs.' secs<br>';
		echo "DONE!";
	}

    public function CareerBuilderPageCrawler($start_page, $end_page){
		$DATA_PATH = public_path('data').self::SLASH.self::CAREERBUILDER_DATA_PATH.self::SLASH;
		$client = new Client();
		$guzzleClient = new \GuzzleHttp\Client(array(
			'timeout' => 15,
		));
		$client->setClient($guzzleClient);
		
		$last_page_is_empty = false;
		$return_code = 0;
		$x = (int) $start_page; 
		$end_page = (int) $end_page;
        while($x <= $end_page) {
			error_log("Crawling page=".$x.": START");
			$page_start = microtime(true);
			echo "page = ".$x.": ";
			
			try{
				$pageUrl = self::CAREERBUILDER_PAGE.$x.self::CAREERBUILDER_PAGE_END;
				$crawler = $client -> request('GET', $pageUrl);
				
				$grid = $crawler -> filter('div.gird_standard');
				$jobs = null;
				if ($grid -> count() > 0){
					$jobs = $grid -> filter('h3.job > a');
				}
				if ($jobs == null or $jobs -> count() <= 0) {
					 $this->AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						$return_code = 2;
						 $this->AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv');
						break;
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
								$file_name = public_path('data').self::SLASH.self::CAREERBUILDER_DATA_PATH.self::SLASH.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv';
								 $this->AppendStringToFile('Ex when getting $job_links: '.substr($e -> getMessage (), 0, 1000), $file_name);
							}
						}
					);
					// select duplicated records
					$existing_links =  $this->CheckLinksExist($jobs_links, env("DATABASE"), $table=self::TABLE);
					$duplicated_links = array();
					foreach($existing_links as $row){
						$link = $row -> link;
						array_push($duplicated_links, $link);
					}
		
					// deduplicate
					$new_links = array_diff($jobs_links, $duplicated_links);
					error_log("Page=".$x.": new_link count=".sizeof($new_links));
					if (is_array($new_links) and sizeof($new_links) > 0){
						$inserted =  $this->InsertLinks($new_links, env("DATABASE"), $table=self::TABLE);
						if ($inserted){
							// crawl each link
							foreach ($new_links as $job_link) {
								error_log("Page=".$x.": link=".$job_link);
								try {
									ini_set('max_execution_time', 20);				
									
									if ($job_link == null){
									} else{
										$crawled =  $this->CrawlJob($client, $job_link, $DATA_PATH);
										if ($crawled == 0){
											 $this->AppendStringToFile($job_link
												, $DATA_PATH.self::CAREERBUILDER_LINK.'.csv');
										}
									}
								} catch (\FatalErrorException $e) {
									 $this->AppendStringToFile("Exception on link:".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv');
								} catch (\Throwable $e) {
									$this->AppendStringToFile("Exception on link:".$job_link.": ".substr($e -> getMessage (), 0, 1000)
									   , $DATA_PATH.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv');
							   }
							}
							// end for
						}
					}
				}
			} catch (\FatalErrorException $e) {
				$return_code = 1;
				$file_name = public_path('data').self::SLASH.self::CAREERBUILDER_DATA_PATH.self::SLASH.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv';
				 $this->AppendStringToFile("Exception on page = ".$x.": ".substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			} catch (\Throwable $e) {
				$return_code = 1;
				$file_name = public_path('data').self::SLASH.self::CAREERBUILDER_DATA_PATH.self::SLASH.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv';
				 $this->AppendStringToFile("Exception on page = ".$x.": ".substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}

			error_log("Crawling page=".$x.": END");

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
	
    public function CrawlJob($client, $url, $data_path){
		error_log("CrawlJob: START");

		$job_start = microtime(true);
	
		// $client = new Client();
		// $guzzleClient = new \GuzzleHttp\Client(array(
		// 	'timeout' => 5,
		// ));
		// $client->setClient($guzzleClient);
		
		try{
			$crawler = $client -> request('GET', $url);
		} catch (\Exception $e) {
			 $this->AppendStringToFile('Exception on crawling job: '.$url.': '.$e -> getMessage()
				, $data_path.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv');
			return -1;
		}
		// dd($crawler);
		error_log("Page requested. ");
		$after_request = microtime(true);
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';
		if ($crawler -> count() <= 0 ) {
			 $this->AppendStringToFile("Job expired. No job content:  ".$url
				, $data_path.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv');
			return 2;
		} else{
			$content = $crawler -> filter('div.LeftJobCB');

			if ($content -> count() <= 0){
				 $this->AppendStringToFile("Job expired. No div.LeftJobCB:  ".$url
				, $data_path.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv');
				return 3;
			}
			$title_crl = $content -> filter('div.top-job-info > h1');
			if ($title_crl -> count() <= 0){
				 $this->AppendStringToFile("Job expired. No title:  ".$url
				, $data_path.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv');
				return 4;
			}
			$job_title = $title_crl -> first() -> text();
			$job_title = trim($job_title, "\r\n- ");

			$company_crl = $content -> filter('div.top-job-info > div.tit_company');
			$company = "";
			if($company_crl -> count() > 0){
				$company = $company_crl -> first() -> text();
			}
			$company = trim($company, "\r\n- ");
			
			$created = "";
			$created_crl = $content -> filter('div.datepost > span');
			if($created_crl -> count() > 0){
				$created = $created_crl -> first() -> text();
			}
			$created = trim($created, "\r\n- ");
			
			$salary = "";
			$deadline = "";
			try{
				$details_crl = $content -> filter('p.fl_right');
				if ($details_crl -> count() > 1){
					foreach($details_crl as $info){
						$info_node = new Crawler($info);
						$text = $info_node -> text();
						if(strpos($text, self::LABEL_SALARY) !== false){
							$salary = $info_node -> text();
							$salary = str_replace(self::LABEL_SALARY, '', $salary);
							$salary =  $this->RemoveTrailingChars($salary);
						} else if(strpos($text, self::LABEL_DEADLINE) !== false){
							$deadline = $info_node -> text();
							$deadline = str_replace('Hết hạn nộp:', '', $deadline);
							$deadline =  $this->RemoveTrailingChars($deadline);
						}
					}
				}
			} catch (\Exception $e) {
				CareerLinkCrawler::AppendStringToFile('Ex on salary + deadline. p.fl_right: '.$url.': '.$e -> getMessage()
					, $data_path.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv');
				return -1;
			}

			$job_des_crl = $content -> filter('div.content_fck');
			if ($job_des_crl -> count() > 0){
				$job_des = $job_des_crl -> first() -> text();
			}
			$job_des =  $this->RemoveTrailingChars($job_des);

			$company_info_crl = $content -> filter('p.TitleDetailNew > label');
			$address = "";
			if ($company_info_crl -> count() > 0){
				$address = $company_info_crl -> first() -> text();
			}
			$address =  $this->RemoveTrailingChars($address);

			$contact = "";
			if ($company_info_crl -> count() > 1){
				$contact_crl = $company_info_crl -> last() -> filter('strong');
				if ($contact_crl -> count() > 0){
					$contact = $contact_crl -> text();
				}
			}

			$website_crl = $content -> filter('span.MarginRight30');
			$website = "";
			if ($website_crl -> count() > 0){
				$website = $website_crl -> text();
			}
			$website =  $this->RemoveTrailingChars($website);

			$mobile = $this->ExtractFirstMobile($contact);
			$email = $this->ExtractEmailFromText($contact);
			$soluong = "";

			// $file_start = microtime(true);
			$job_data = array($mobile
				, $email
				, $contact
				, $company
				, $address
				, $job_title
				, $salary
				, $job_des
                , $created
                , $deadline
				, $soluong
				, $website
				, $url);

			// echo 'from start: '.(microtime(true) - $job_start).' secs <br>';
			// echo 'after request: '.(microtime(true) - $after_request).' secs ';
			// dd($job_data);

			$this->AppendArrayToFile($job_data , $data_path.self::CAREERBUILDER_DATA.'.csv', "|");
			error_log("Page saved. ");
			return 0;
		}
	}

	public function RemoveTrailingChars($text){
		return trim(preg_replace('!\s+!', ' ', $text), "\r\n- ");
	}
	public function ExtractMobile($contact){
		preg_match_all(self::PHONE_PATTERN, $contact, $matches);

		$mobiles_str = "";
		$len = count($matches[0]);
		if ($len > 0){
			$nums = $matches[0];
			$mobiles = array();
			$mobile_tmp = "";
			for ($x = 0; $x < $len; $x++) {
				$num = $nums[$x];
				if (strlen($mobile_tmp.$num) <= 12){
					$mobile_tmp = $mobile_tmp.$num;
				} else {
					array_push($mobiles, $mobile_tmp);
					$mobile_tmp = $num;
				}
				if ($x == $len - 1){
					array_push($mobiles, $mobile_tmp);
				}
			} 
			$mobiles_str = implode(",", $mobiles);
		} 
		return $mobiles_str;
	}

	public function ExtractFirstMobile($contact){
		preg_match_all(self::PHONE_PATTERN, $contact, $matches);

		$mobiles_str = "";
		$len = count($matches[0]);
		if ($len > 0){
			$nums = $matches[0];
			$mobiles = array();
			$mobile_tmp = "";
			for ($x = 0; $x < $len; $x++) {
				$num = $nums[$x];
				if (strlen($mobile_tmp.$num) <= 12){
					$mobile_tmp = $mobile_tmp.$num;
				} else {
					array_push($mobiles, $mobile_tmp);
					$mobile_tmp = $num;
				}
				if ($x == $len - 1){
					array_push($mobiles, $mobile_tmp);
				}
			} 
			if (sizeof($mobiles) > 0 ){
				if (sizeof($mobiles) > 1 and strlen($mobiles[1]) < 5){
					$mobiles_str = $mobiles[0].'/'.$mobiles[1];
				} 
				$mobiles_str = $mobiles[0];
			}
		} 
		if (strlen($mobiles_str) < 10 or strlen($mobiles_str) > 16) return "";
		return $mobiles_str;
	}

	public function ExtractEmailFromText($text){
		preg_match_all(self::EMAIL_PATTERN, $text, $matches);
		if (sizeof($matches[0]) > 0){
			return $matches[0][0];
		} else{
			return "";
		}
	}

	public function AppendArrayToFile($arr, $file_name, $limiter="|"){
		$fp = fopen($file_name, 'a');
		fputcsv($fp, $arr, $delimiter = $limiter);
		fclose($fp);
	}

	public function AppendStringToFile($str, $file_name){
		$fp = fopen($file_name, 'a');
		fputcsv($fp, array($str));
		fclose($fp);
	}

	public function CheckLinksExist($jobs_links, $database="phpmyadmin", $table){
		if (env("DATABASE") == null) $database="phpmyadmin";

		$select_param = "('".implode("','", $jobs_links)."')";
		$select_dialect = "select link from ".$database.".".$table." where link in ";
		$select_query = $select_dialect.$select_param;
		$existing_links = DB::select($select_query);

		return $existing_links;
	}

	public function ResetJobMetadata($database, $table, $job_name){
		DB::delete("delete from ".$database.".".$table." where job_name=? ", array($job_name));
	}

	public function InsertLinks($new_links, $database="phpmyadmin", $table){
		if (env("DATABASE") == null) $database="phpmyadmin";

		$insert_links = array();
		foreach($new_links as $el){
			array_push($insert_links, "('".$el."')");
		}
		$insert_param = implode(",", $insert_links);
		$insert_dialect = "insert into ".$database.".".$table."(link) values ";
		$insert_query = $insert_dialect.$insert_param;
		$insert_results = DB::insert($insert_query);
		
		return $insert_results;
	}

	public function FindNewBatchToProcess($database="phpmyadmin", $table, $job_name){
		try {
			// find latest batch: id, job_name, start_page, end_page, timestamp
			$select_query = "select * from ".$database.".".$table." where job_name='".$job_name."' order by end_page desc limit 1 ";
			$select_result = DB::select($select_query);

			// find new batch
			$latest_batch = null;
			$new_batch = null;
			if (sizeof($select_result) < 1){
				$new_batch = (object) array("job_name" => self::JOB_NAME, "start_page" => 1, "end_page" => self::BATCH_SIZE);
			} else{
				$latest_batch = $select_result[0];
				$new_batch = (object) array("job_name" => $latest_batch -> job_name
					, "start_page" => $latest_batch -> end_page + 1
					, "end_page" => $latest_batch -> end_page + self::BATCH_SIZE);
			}

			// save batch to db
			$insert_query = "insert into ".$database.".".$table."(job_name, start_page, end_page) values (?, ?, ?) ";
			$insert_result = DB::insert(
				$insert_query
				, array($new_batch -> job_name, $new_batch -> start_page, $new_batch -> end_page)
			);

			return $new_batch;

		} catch (\Exception $e) {
			$file_name = public_path('data').self::SLASH.self::CAREERBUILDER_DATA_PATH.self::SLASH.self::CAREERBUILDER_ERROR.date(self::DATE_FORMAT).'.csv';
			 $this->AppendStringToFile('Ex when find new batch: '.substr($e -> getMessage (), 0, 1000), $file_name);
		}
		return null;
	}
}