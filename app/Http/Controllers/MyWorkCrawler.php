<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

class MyWorkCrawler extends Controller{

    public function MyWorkCrawler($start_page, $step){
		$start = microtime(true);
        $client = new Client;
        
        $x = (int) $start_page; 
        $end = (int) $start_page + (int) $step;
        while($x < $end) {
			$page_start = microtime(true);
			echo "i = ".$x.": ";
            $pageUrl = 'https://mywork.com.vn/tuyen-dung/trang/'.$x;
    		$crawler = $client -> request('GET', $pageUrl);
			$jobs = $crawler -> filter('div.item-list') -> first() -> filter('a.item');
			if ($jobs -> count() <= 0) {
                echo "NA";
				break;
            } 
			$jobs -> each(
		    	function ($node) {
					try {
						ini_set('max_execution_time', 10000000);				
                        $job_link = $node -> attr('href');
                        
                        if ($job_link == null){
                        } else{
                            $full_link = 'https://mywork.com.vn'.$job_link;
							
							//todo separate
                            $fp = fopen('mywork-links.csv', 'a');
                            fputcsv($fp, array($full_link));
                            fclose($fp);
                            
                            MyWorkCrawler::TimJob($full_link);
                        }
                        
					} catch (Exception $e) {
						// echo 'Caught exception: ',  $e -> getMessage(), "\n";
						$fp = fopen('mywork-error.csv', 'a');
						fputcsv($fp, array("ERROR: ", $e -> getMessage()), $delimiter = "|");
						fclose($fp);
					}
				}
			);
			$x++;
			$page_total_time = microtime(true) - $page_start;
			echo '<b>Total execution time of page '.$x.":</b> ".$page_total_time.' secs<br>';
		} 
		$time_elapsed_secs = microtime(true) - $start;
		echo '<b>Total Execution Time:</b> '.$time_elapsed_secs.' secs<br>';
		echo "\nDONE!";
	}
	
    public function TimJob($url){
		// $job_start = microtime(true);
		$client = new Client;
		// echo 'create client: '.(microtime(true) - $job_start).' secs, ';
		$crawler = $client -> request('GET', $url);
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';

		$content_crawler = $crawler -> filter('div.detail_job');
		if ($content_crawler -> count() <= 0 ) {
			$fp = fopen('mywork-error.csv', 'a');
			fputcsv($fp, array("ERROR: ", $url), $delimiter = "|");
			fclose($fp);
		} else{
			$content = $content_crawler -> first();
			// $header_start = microtime(true);
			$job_title = "n/a";
			$title_crawler = $crawler -> filter('h1.main-title > span');
			if ($title_crawler -> count() > 0 ) {
				$job_title = $title_crawler -> first() -> text();
            }
			// echo 'header: '.(microtime(true) - $header_start).' secs, ';

			// $posted_start = microtime(true);
			$created = "n/a";
			$created_infos = $content -> filter('div.content > p');
			if ($created_infos -> count() > 0 ) {
                foreach ($created_infos as $node) {
                    $created_crawler = new Crawler($node);
					$label_crawler = $created_crawler -> filter('strong');
					if ($label_crawler -> count() > 0){
						$label = $label_crawler -> first() -> text();
						if (strpos($label, 'Ngày duyệt') !== false){
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
            $company = "n/a";
			$company_crawler = $content -> filter('p.company-name > a > strong');
			if ($company_crawler -> count() > 0 ) {
				$company = $company_crawler -> first() -> text();
            }
			// echo 'company: '.(microtime(true) - $company_start).' secs, ';

			// $address_start = microtime(true);
			$address = "n/a";
			$address_crawler = $content -> filter('p.address > span');
			if ($address_crawler -> count() > 0 ) {
				$address = $address_crawler -> first() -> text();
            }
			// echo 'address: '.(microtime(true) - $address_start).' secs, ';

            // $salary_start = microtime(true);
            $salary = 'n/a';
            $salary_crawler = $content -> filter('span.text_red');
            if ($salary_crawler -> count() > 0 ) {
				$salary = $salary_crawler -> first() -> text();
            }
            $salary = trim(preg_replace("/[\r\n]*/", "", $salary), " ");
            $salary = str_replace("  ", "", $salary);
            // echo 'salary: '.(microtime(true) - $salary_start).' secs, ';
            
            // $website_start = microtime(true);
			$website = 'n/a';
			$website_crawler = $content -> filter('p.company-name > a');
			if ($website_crawler -> count() > 0 ) {
                $ref = $website_crawler -> first() -> attr('href');
                $website = 'https://mywork.com.vn'.$ref;
            }
            // echo 'website: '.(microtime(true) - $website_start).' secs, ';
            
            // $soluong_start = microtime(true);
            $soluong = "n/a";
			$general_infos = $content -> filter('div.job_detail_general > div.item1 > p');
			if ($general_infos -> count() > 0 ) {
                foreach ($general_infos as $node) {
                    $soluong_crawler = new Crawler($node);
                    $label = $soluong_crawler -> filter('strong') -> first() -> text();
                    if (strpos($label, 'Số lượng cần tuyển') !== false){
                        $soluong = $soluong_crawler -> first() -> text();
                        break;
                    }
                }
			}
			if (strpos($soluong, ':') !== false){
				$soluong = trim(explode(":", $soluong)[1], "\r\n ");
			}
			// echo 'soluong: '.(microtime(true) - $soluong_start).' secs, ';

            // $deadline_start = microtime(true);
            $contact = "n/a";
            $deadline = 'n/a';
            $contact_infos = $content -> filter('div.box-contact > div.row');
			if ($contact_infos -> count() > 0 ) {
                foreach ($contact_infos as $node) {
                    $contact_crawler = new Crawler($node);
                    
                    $label = $contact_crawler -> filter('div.label-contact');
                    if ($label -> count() > 0){
                        $label = $label -> first() -> text();
                        if (strpos($label, 'Người liên hệ') !== false){
                            $contact = $contact_crawler -> filter('div') -> last() -> text();
                            // dd($contact);
                        } else if (strpos($label, 'Hạn nộp hồ sơ') !== false){
                            $deadline = $contact_crawler -> filter('div') -> last() -> text();
                            $deadline = preg_replace("/[\r\n ]*/", "", $deadline);
                        }
                    }
                }
            }
			// echo 'deadline: '.(microtime(true) - $deadline_start).' secs, ';

			// $job_des 
			$jds = $content -> filter('div.multiple > div.mw-box-item');
			$job_des = "n/a";
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
			$job_des = trim($job_des, "\r\n -");

			$mobile = MyWorkCrawler::ExtractMobile($contact);
			$email = "";

			// $file_start = microtime(true);
			$line = array($mobile
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
            
			$fp = fopen('mywork.csv', 'a');
			fputcsv($fp, $line, $delimiter = "|");
			fclose($fp);
			// echo 'write file: '.(microtime(true) - $file_start).' secs <br>';
			// echo 'Total 1 job: '.(microtime(true) - $job_start).' secs <br>';
		}
	}


	public function ExtractMobile($contact){
		preg_match_all('!\d+!', $contact, $matches);

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
}