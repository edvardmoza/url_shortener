url_shortener
=============

Lightweight file based url shortener in PHP

- Create customized shortened URLs like "yourdomain.com/link"
  - It is more personalized than using any public url-shorteners
  - You can make the shortened url self speaking, unlike with the public url shorteners
- Optional password protection
- Click statistics
- easy integration in existing website
  - standard configuration for admin interface is "yourdomain.com/short"
  - you can also make a dedicated website for this service

Installation
------------
1. Copy the files into your root directory
  a If you already have a directory called s you can rename the storage folder to anything you like
2. Congratulations, you're done!

Configuration
-------------

- Enable Password Protection
  - Set "$enablePasswordProtection = true;" in the shortener.php file
  - Default password is "url_shortener" replace it with your md5 encrypted passwort
    - To enrypt a passwort google for "md5 generator"
- Dedicated Service
  - To use the website only for this script, uncomment the corresponding line in the .htaccess file
- Change the storage directory
  - define('STORAGE_DIR','s/'); change s/ to any directory you want the url and logfiles to be stored

Feel free to change the script to your needs and notify me if you have some improvements!
