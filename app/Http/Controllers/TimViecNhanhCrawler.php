<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;


class TimViecNhanhCrawler extends Controller{

    public function DemoCrawlerFunc(){

    	$url = 'https://itviec.com/it-jobs/java';
    	$client = new Client;
		$crawler = $client->request('GET',$url);
		$linkDetail = $crawler->filter('h2.title > a')->attr('href');
		$orgLink = 'https://itviec.com/'.$linkDetail;

		$crawler1 = $client->request('GET',$orgLink);

		$company = $crawler1->filter('h3.name > a')->text();
		$location = $crawler1->filter('div.address__full-address > span')->text();
		$job_title = $crawler1->filter('h1.job_title')->text();
		$description = $crawler1->filter('div.description > ul');
		//$salary = $crawler1->filter('span.salary-text') -> text()


		$description_el = $description -> filter('li') -> each(
			function($node){
		    	return  $node->text();
			}
		);
		var_dump($company);
		var_dump($location);
		var_dump($job_title);
		//var_dump($salary);
		var_dump($description_el);
    }

    public function ManyNodeCrawlerFunc(){
    	$client = new Client;
    	$url = 'https://itviec.com/it-jobs/java?page=';

    	$x = 1; 
		while($x <= 3) {
			var_dump('x = '.$x.'<br>');
			$pageUrl = 'https://itviec.com/it-jobs/java?page='.$x;
    		$crawler = $client->request('GET',$pageUrl);
		    
		    $crawler->filter('h2 > a')->each(function ($node) {
		    	ini_set('max_execution_time', 10);
				$client1 = new Client;

				$linkDetail = $node -> attr('href');
				$job_link = 'https://itviec.com/'.$linkDetail;
				var_dump($job_link.': ');
				$job_crawler = $client1->request('GET',$job_link);
			    $company = $job_crawler->filter('h3.name > a')->text()."<br>";
				$location = $job_crawler->filter('div.address__full-address > span')->text()."<br>";
				$job_title = $job_crawler->filter('h1.job_title')->text()."<br>";
				// $description = $job_crawler->filter('div.description > ul');
				var_dump($company);
				var_dump($location);
				var_dump($job_title);
				// var_dump($job_title);
				// var_dump($description_el);
			});

		    $x++;
		} 
		
    }

    public function ManyNodeCrawlerFuncOpt(){
    	$client = new Client;
    	$url = 'https://itviec.com/it-jobs/java?page=';

    	$x = 1; 
		while($x <= 15) {
			var_dump('x = '.$x.'<br>');
			$pageUrl = 'https://itviec.com/it-jobs?page='.$x;
    		$crawler = $client->request('GET',$pageUrl);
		    
		    $crawler->filter('h2 > a')->each(function ($node) {
		    	ini_set('max_execution_time', 10000000);
				$client1 = new Client;

				$linkDetail = $node -> attr('href');
				$job_link = 'https://itviec.com/'.$linkDetail;
				var_dump($job_link.': ');
				$job_crawler = $client1->request('GET',$job_link);
			    $company = $job_crawler->filter('h3.name > a')->text()."<br>";
				$location = $job_crawler->filter('div.address__full-address > span')->text()."<br>";
				$job_title = $job_crawler->filter('h1.job_title')->text()."<br>";
				// $description = $job_crawler->filter('div.description > ul');
				var_dump($company);
				var_dump($location);
				var_dump($job_title);
				// var_dump($job_title);
				// var_dump($description_el);
			});

		    $x++;
		} 
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
	
	const TIM_VIEC_NHANH_PAGE = 'https://www.timviecnhanh.com/vieclam/timkiem?&page=';
	const TIM_VIEC_NHANH_FILE_DATA = 'timviecnhanh-';
	const TIM_VIEC_NHANH_FILE_LINK = 'timviecnhanh-links-';
	const TIM_VIEC_NHANH_FILE_ERROR = 'timviecnhanh-errors-';
	const LABEL_SALARY = 'Mức lương';
	const LABEL_QUANTITY = 'Số lượng tuyển dụng';
	const LABEL_WEBSITE = 'Website';

    public function TimViecNhanh($start_page, $step){
		$start = microtime(true);
        $client = new Client;
		
        $x = (int) $start_page; 
        $end = (int) $start_page + (int) $step;
        while($x < $end) { //todo for in range
			$page_start = microtime(true);
			echo "i = ".$x.": ";
			$pageUrl = self::TIM_VIEC_NHANH_PAGE.$x;
			
    		$crawler = $client -> request('GET', $pageUrl);
			$jobs = $crawler -> filter('table > tbody > tr > td > a.item');
			
			if ($jobs -> count() <= 0) {
				break;
			}
			$jobs -> each(
		    	function ($node) {
					try {
						ini_set('max_execution_time', 10000000);				
                        $job_link = $node -> attr('href');
                        
						//todo separate
						TimViecNhanhCrawler::AppendLineToFile($job_link, self::TIM_VIEC_NHANH_FILE_LINK.date('Ymd').'.csv');
						
						TimViecNhanhCrawler::TimJob($job_link);

					} catch (Exception $e) {
						TimViecNhanhCrawler::AppendArrayToFile(array("ERROR: ", $e -> getMessage()), self::TIM_VIEC_NHANH_FILE_ERROR.date('Ymd').'.csv', self::LIMITER);
						$fp = fopen(self::TIM_VIEC_NHANH_FILE_ERROR.date('Ymd').'.csv', 'a');
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

		$content_crawler = $crawler -> filter('article.block-content');
		if ($content_crawler -> count() <= 0 ) {
			//self::TIM_VIEC_NHANH_FILE_ERROR.date('Ymd').'.csv'

			$fp = fopen('timviecnhanh-error.csv', 'a');
			fputcsv($fp, array("ERROR: ", $url), $delimiter = "|");
			fclose($fp);
		} else{
			$content = $content_crawler -> first();
			
			// $header_start = microtime(true);
			$job_title = "n/a";
			$title_crawler = $crawler -> filter('header.block-title > h1 > span');
			if ($title_crawler ->count() > 0 ) {
				$job_title = $title_crawler -> first() -> text();
			}
			// echo 'header: '.(microtime(true) - $header_start).' secs, ';

			// $posted_start = microtime(true);
			$created = "n/a";
			$created_crawler = $content -> filter('time');
			if ($created_crawler ->count() > 0 ) {
				$created = $created_crawler -> first() -> text();
			}
			// echo 'posted time: '.(microtime(true) - $posted_start).' secs, ';
			
			// $company_start = microtime(true);
			$company = "n/a";
			$company_crawler = $content -> filter('div > h3 > a');
			if ($company_crawler -> count() > 0 ) {
				$company = $company_crawler -> first() -> text();
			}
			// echo 'company: '.(microtime(true) - $company_start).' secs, ';

			// $address_start = microtime(true);
			$address = "n/a";
			$address_crawler = $content -> filter('div.col-xs-6 > span');
			if ($address_crawler -> count() > 0 ) {
				$address = $address_crawler -> first() -> text();
			}
			// echo 'address: '.(microtime(true) - $address_start).' secs, ';

			// $deadline_start = microtime(true);
			$deadline = $content -> filter('b.text-danger') -> first() -> text();
			$deadline = trim($deadline, "\r\n ");
			// echo 'deadline: '.(microtime(true) - $deadline_start).' secs, ';

			// $salary_start = microtime(true);
			$quanti_info = $content -> filter('div > ul > li');
			$salary = 'n/a';
			$soluong = 'n/a';
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

			// $website_start = microtime(true);
			$website = 'n/a';
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
			} catch (Exception $e) {
				echo 'Caught exception: ',  $e -> getMessage(), "\n";
				$fp = fopen('timviecnhanh-error.csv', 'a');
				fputcsv($fp, array("ERROR: ", $e -> getMessage()), $delimiter = "|");
				fclose($fp);
			}
			// echo 'website: '.(microtime(true) - $website_start).' secs, ';

			$job_des = $content -> filter('td > p') -> first() -> text();
			$website = trim(preg_replace("/[\r\n]*/", "", $job_des), "-");

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
			
			$fp = fopen('timviecnhanh.csv', 'a');
			fputcsv($fp, $line, $delimiter = "|");
			fclose($fp);
			// echo 'write file: '.(microtime(true) - $file_start).' secs <br>';

			// echo 'Total 1 job: '.(microtime(true) - $job_start).' secs <br>';
		}
	}

	public function TimJob2(){
    	$client = new Client;

    	$url = "https://www.timviecnhanh.com/tuyen-nhan-vien-theo-doi-don-hang-bo-phan-kinh-doanh-ho-chi-minh-4329844.html";
    	$crawler = $client->request('GET',$url);
    	$job_title = $crawler -> filter('header.block-title > h1 > span') -> text();
		$time = $crawler -> filter('#left-content') -> text();
		$line = array($job_title, $time, '789');
		dd($line);
	}

	public function AppendLineToFile($line, $file){
		$fp = fopen($file, 'a');
		fputcsv($fp, array($line));
		fclose($fp);
	}

	public function AppendArrayToFile($arr, $file, $limiter){
		$fp = fopen($file, 'a');
		fputcsv($fp, $arr, $delimiter=$limiter);
		fclose($fp);
	}

	public function Test(){
		$client = new Client;
    	$url = "https://www.timviecnhanh.com/tuyen-nhan-vien-theo-doi-don-hang-bo-phan-kinh-doanh-ho-chi-minh-4329844.html";
    	$crawler = $client->request('GET',$url);
		$job_title = $crawler -> filter('header.block-title > h1 > span') -> text();
		
		dd($job_title);
    }
    
    public function TestParameter($startPage, $step){
        echo $startPage+$step;
    }
}