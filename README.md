<p align="center"><img src="https://laravel.com/assets/img/components/logo-laravel.svg"></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/d/total.svg" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/license.svg" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel attempts to take the pain out of development by easing common tasks used in the majority of web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, yet powerful, providing tools needed for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of any modern web application framework, making it a breeze to get started learning the framework.

If you're not in the mood to read, [Laracasts](https://laracasts.com) contains over 1100 video tutorials on a range of topics including Laravel, modern PHP, unit testing, JavaScript, and more. Boost the skill level of yourself and your entire team by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for helping fund on-going Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell):

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[British Software Development](https://www.britishsoftware.co)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- [UserInsights](https://userinsights.com)
- [Fragrantica](https://www.fragrantica.com)
- [SOFTonSOFA](https://softonsofa.com/)
- [User10](https://user10.com)
- [Soumettre.fr](https://soumettre.fr/)
- [CodeBrisk](https://codebrisk.com)
- [1Forge](https://1forge.com)
- [TECPRESSO](https://tecpresso.co.jp/)
- [Runtime Converter](http://runtimeconverter.com/)
- [WebL'Agence](https://weblagence.com/)
- [Invoice Ninja](https://www.invoiceninja.com)
- [iMi digital](https://www.imi-digital.de/)
- [Earthlink](https://www.earthlink.ro/)
- [Steadfast Collective](https://steadfastcollective.com/)
- [We Are The Robots Inc.](https://watr.mx/)
- [Understand.io](https://www.understand.io/)

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


## User guide

A. ENVIRONMENT SETTING UP FOR LINUX USER

Step 1. Install xampp; set xampp to environment variable; link xampp php: <br>
- download xampp-linux-x64-7.3.2-1-installer.run
- chmod +x xampp-linux-x64-7.3.2-1-installer.run
- sudo ./xampp-linux-x64-7.3.2-1-installer.run
- echo 'export PATH="$PATH:/opt/lampp/bin"' >> ~/.bashrc
- source ~/.bashrc
- sudo ln -s /opt/lampp/bin/php /usr/bin/php


Step 2. Install composer => copy to /usr/local/bin/composer; require php, net-tools: <br>
- cd ~
- sudo apt install net-tools
- curl -sS https://getcomposer.org/installer -o composer-setup.php
- sudo mv composer.phar /usr/local/bin/composer


Step 3. Clone repository; require git, php, composer: <br>
- git clone https://github.com/hoai-nguyen/crawler-recruitment.git
- cd crawler-recruitment
- composer install
- cp .env.example .env 
- php artisan key:generate


B. USAGES <br>

UC0. Start application server From project directory, execute: <br> 
- chmod +x uc_0_start_app_servers.sh
- ./uc_0_start_app_servers.sh

UC1. From very start, init or reset data for all pages: From project directory, execute:  <br>
- chmod +X uc_1_init_or_reset_data_all_pages.sh
- ./uc_1_init_or_reset_data_all_pages.sh
	
UC2. Crawl one page from begin or continue to crawl that page from last run: From project directory, execute: <br>
- chmod +x uc_2_crawl_one_page.sh
- ./uc_2_crawl_one_page.sh page_name
    + Where page_name in: topdev, topcv, itviec, mywork, timviecnhanh, vieclam24h, findjobs, careerlink
    + Example: ./uc_2_crawl_one_page.sh topdev
- Output data will be placed in public/data/page_name. For example: public/data/topdev/topdev-data.csv

UC3. If we want to crawl a page from start, we reset data of the page: From project directory, execute: 
- chmod +x uc_2_crawl_one_page.sh
- ./uc_3_reset_one_page.sh page_name
    + Where page_name in: topdev, topcv, itviec, mywork, timviecnhanh, vieclam24h, findjobs, careerlink
    + Example: ./uc_3_reset_one_page.sh topdev
	
UC4. Merge data of all pages into one file. From project directory, execute: 
- chmod +x uc_4_merge_all_pages.sh
- ./uc_4_merge_all_pages.sh
- Output data will be placed in public/data/recruitment_data_<datetime>.csv. For example: recruitment_data_2019-03-09_23:48:50.csv

UC5: Monitor crawling. We can see logs from application servers or data written to public/data/page_name. From project directory, execute: <br>
- chmod +x uc_5_monitor_crawling_one_page.sh
- ./uc_5_monitor_crawling_one_page.sh page_name
    + Where page_name in: topdev, topcv, itviec, mywork, timviecnhanh, vieclam24h, findjobs, careerlink
    + Example: ./uc_5_monitor_crawling_one_page.sh topdev
	

