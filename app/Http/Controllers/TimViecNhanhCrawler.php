<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;
use Illuminate\Support\Facades\DB;

class TimViecNhanhCrawler extends Controller{

	const TABLE = "timviecnhanh";
	const TIMVIECNHANH_DATA_PATH = 'timviecnhanh'; // CI must create directory in
	const TIMVIECNHANH_DATA = 'timviecnhanh-data';
	const TIMVIECNHANH_ERROR = 'timviecnhanh-error-';
	const TIMVIECNHANH_LINK = 'timviecnhanh-link';
	const TIMVIECNHANH_HOME = 'https://www.timviecnhanh.com/vieclam/timkiem?&page=';
	const LABEL_SALARY = 'Mức lương';
	const LABEL_QUANTITY = 'Số lượng tuyển dụng';
	const LABEL_WEBSITE = 'Website';
	const DATE_FORMAT = "Ymd";
	const SLASH = DIRECTORY_SEPARATOR;
	const BATCH_SIZE = 3;
	const MAX_PAGE = 1000;
	const EMAIL_PATTERN = "/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i";
	const PHONE_PATTERN = "!\d+!";

	public function CrawlerStarter(){
		$start = microtime(true);

		while (true){
			try {
				$new_batch = TimViecNhanhCrawler::FindNewBatchToProcess("phpmyadmin", "job_metadata");
				if ($new_batch == null){
					break;
				}

				$return_code = TimViecNhanhCrawler::TimViecNhanhCrawlerFunc($new_batch -> start_page, $new_batch -> end_page);

				if ($return_code > 1) {
					TimViecNhanhCrawler::ResetJobMetadata("phpmyadmin", "job_metadata", "timviecnhanh");
					break;
				}
			} catch (\Exception $e) {
				$file_name = public_path('data').self::SLASH.self::TIMVIECNHANH_DATA_PATH.self::SLASH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv';
				TimViecNhanhCrawler::AppendStringToFile('Exception on starter: '.substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}
		}

		$time_elapsed_secs = microtime(true) - $start;
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
			echo "page = ".$x.": ";

			try{
				$pageUrl = self::TIMVIECNHANH_HOME.$x;
				$crawler = $client -> request('GET', $pageUrl);
				$jobs = $crawler -> filter('table > tbody > tr > td > a.item');

				if ($jobs -> count() <= 0) {
					TimViecNhanhCrawler::AppendStringToFile("No job found on page: ".$pageUrl
						, $DATA_PATH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
					
					// if previous page is empty and current page is empty => quit
					if ($last_page_is_empty){
						$return_code = 2;
						TimViecNhanhCrawler::AppendStringToFile("Quit because two consecutive pages are empty."
							, $DATA_PATH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
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
								$file_name = public_path('data').self::SLASH.self::TIMVIECNHANH_DATA_PATH.self::SLASH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv';
								TimViecNhanhCrawler::AppendStringToFile('Exception on getting job_link: '.substr($e -> getMessage (), 0, 1000), $file_name);
							}
						}
					);
					
					// select duplicated records
					$existing_links = TimViecNhanhCrawler::CheckLinksExist($jobs_links, env("DATABASE"), $table="timviecnhanh");
					$duplicated_links = array();
					foreach($existing_links as $row){
						$link = $row -> link;
						array_push($duplicated_links, $link);
					}
		
					// deduplicate
					$new_links = array_diff($jobs_links, $duplicated_links);

					if (is_array($new_links) and sizeof($new_links) > 0){
						$inserted = TimViecNhanhCrawler::InsertLinks($new_links, env("DATABASE"), $table="timviecnhanh");
						if ($inserted){
							// crawl each link
							foreach ($new_links as $job_link) {
								try {
									ini_set('max_execution_time', 10000000);				
									
									if ($job_link == null){
									} else{
										TimViecNhanhCrawler::CrawlJob($job_link, $DATA_PATH);

										TimViecNhanhCrawler::AppendStringToFile($job_link
										, $DATA_PATH.self::TIMVIECNHANH_LINK.'.csv');
									}
								} catch (\Exception $e) {
									TimViecNhanhCrawler::AppendStringToFile("Exception on crawling link: ".$job_link.": ".substr($e -> getMessage (), 0, 1000)
										, $DATA_PATH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
								}
							}
							// end for
						}
					}
				}
			} catch (\Exception $e) {
				$return_code = 1;
				$file_name = public_path('data').self::SLASH.self::TIMVIECNHANH_DATA_PATH.self::SLASH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv';
				TimViecNhanhCrawler::AppendStringToFile("Exception on page = ".$x.": ".substr($e -> getMessage (), 0, 1000), $file_name);
				break;
			}
			if ($x > 10) break;

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
			TimViecNhanhCrawler::AppendStringToFile("ERROR: Failed to crawl ".$url
			, $data_path.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
		} else{
			$content = $content_crawler -> first();
			
			// $header_start = microtime(true);
			$job_title = "";
			$title_crawler = $crawler -> filter('header.block-title > h1 > span');
			if ($title_crawler -> count() > 0 ) {
				$job_title = $title_crawler -> first() -> text();
			}
			// echo 'header: '.(microtime(true) - $header_start).' secs, ';

			// $posted_start = microtime(true);
			$created = "";
			$created_crawler = $content -> filter('time');
			if ($created_crawler -> count() > 0 ) {
				$created = $created_crawler -> first() -> text();
			}
			// echo 'posted time: '.(microtime(true) - $posted_start).' secs, ';
			
			// $company_start = microtime(true);
			$company = "";
			$company_crawler = $content -> filter('div > h3 > a');
			if ($company_crawler -> count() > 0 ) {
				$company = $company_crawler -> first() -> text();
			}
			$company = trim($company, "\r\n ");
			// echo 'company: '.(microtime(true) - $company_start).' secs, ';

			// $deadline_start = microtime(true);
			$deadline_crl = $content -> filter('b.text-danger');
			if ($deadline_crl -> count() > 0){
				$deadline = $deadline_crl -> first() -> text();
			} 
			$deadline = trim($deadline, "\r\n ");
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
				TimViecNhanhCrawler::AppendStringToFile('Exception on get salaray + soluong: '.$url.': '.$e -> getMessage()
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
				TimViecNhanhCrawler::AppendStringToFile('Exception on get website: '.$url.': '.$e -> getMessage()
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
							if (strpos($label, "Người liên hệ") !== false){ //self::LABEL_CONTACT
								$contact = $info_crl_td -> last() -> text();
							} else if (strpos($label, "Địa chỉ") !== false){
								$address = $info_crl_td -> last() -> text();
							} else if (strpos($label, "Email") !== false){
								$email = TimViecNhanhCrawler::ExtractEmailFromText($info_crl_td -> last() -> text());
							} else if (strpos($label, "Điện thoại") !== false or strpos($label, "Di động") !== false){
								$mobile = TimViecNhanhCrawler::ExtractFirstMobile($info_crl_td -> last() -> text());
							}
						}
					}
				}
				$contact = trim(preg_replace("/[\t\r\n]*/", "", $contact), "\t\r\n ");
				$address = trim(preg_replace("/[\t\r\n]*/", "", $address), "\t\r\n ");

			} catch (\Exception $e) {
				TimViecNhanhCrawler::AppendStringToFile('Exception on getting contact or address: '.$url.': '.$e -> getMessage()
					, $data_path.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
			}

			$job_des = "";
			try{
				$job_des = $content -> filter('td > p') -> first() -> text();
				$job_des = trim(preg_replace("/[\t\r\n]*/", "", $job_des), "\t\r\n- ");
			} catch (\Exception $e) {
				TimViecNhanhCrawler::AppendStringToFile('Exception on getting job_des: '.$url.': '.$e -> getMessage()
					, $data_path.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv');
			}

			
			// $mobile = TimViecNhanhCrawler::ExtractFirstMobile($contact);
			
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

			TimViecNhanhCrawler::AppendArrayToFile($job_data
				, $data_path.self::TIMVIECNHANH_DATA.'.csv', "|");
			// echo 'write file: '.(microtime(true) - $file_start).' secs <br>';
			// echo 'Total 1 job: '.(microtime(true) - $job_start).' secs <br>';
		}
	}

	public function ExtractMobile($contact){
		if ($contact == null) return "";
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
		if ($contact == null) return "";
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
		if ($text == null) return "";
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

	public function FindNewBatchToProcess($database="phpmyadmin", $table){
		try {
			// find latest batch: id, job_name, start_page, end_page, timestamp
			$select_query = "select * from ".$database.".".$table." where job_name='timviecnhanh' order by end_page desc limit 1 ";
			$select_result = DB::select($select_query);

			// find new batch
			$latest_batch = null;
			$new_batch = null;
			if (sizeof($select_result) < 1){
				$new_batch = (object) array("job_name" => "timviecnhanh", "start_page" => 1, "end_page" => self::BATCH_SIZE);
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
			$file_name = public_path('data').self::SLASH.self::TIMVIECNHANH_DATA_PATH.self::TIMVIECNHANH_ERROR.date(self::DATE_FORMAT).'.csv';
			TimViecNhanhCrawler::AppendStringToFile('Ex on finding new batch: '.substr($e -> getMessage (), 0, 1000), $file_name);
		}
		return null;
	}
	
	public function GithubLogin(){
    	$client = new Client;

    	$crawler = $client->request('GET', 'https://github.com/login');
		// $crawler = $client->click($crawler->selectLink('Sign in')->link());
		
		$form = $crawler->selectButton('Sign in')->form();
		$crawler = $client->submit($form, array('login' => 'hoai-nguyen', 'password' => 'vanhoai1#'));

		// $test = $crawler->filter('h4.f5 text-bold mb-1') -> text()."<br>";
		var_dump($crawler);

		// $crawler->filter('h4.f5 text-bold mb-1')->each(function ($node) {
		//     var_dump($node->text()."<br>");
		// });
	}
}