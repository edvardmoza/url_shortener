<?php
define('SERVER',$_SERVER['SERVER_NAME']);

//Helper Class
class Helper
{
    public static function Get($index, $scope, $default = null)
    {
        if (is_object($scope))
        {
            return isset($scope->$index) ? $scope->$index : $default;
        }
        else if (is_array($scope))
        {
            return array_key_exists($index, $scope) && !is_null($scope[$index]) && !(empty($scope[$index]) && !is_numeric($scope[$index])) ? $scope[$index] : $default;
        }
        return $default;
    }

    public static function RandString($length, $charset='abcdefghijklmnopqrstuvwxyz')
    {
        $str = '';
        $count = strlen($charset);
        while ($length--) {
            $str .= $charset[mt_rand(0, $count-1)];
        }
        return $str;
    }

    public static function UrlGetContents ($Url) {
        if (!function_exists('curl_init')){
            throw new Exception('CURL is not installed!');
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $Url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public static function Redirect($url)
    {
        header('Location: ' . $url);
    }
}

//Configuration Class
class Config
{
    public static $storageDir;
    public static $deletionEnabled;
    public static $passwordProtected;
    public static $passwordMD5Encrypted;
    public static $loadStatsAsynchronous;
    public static $sortAlphabetically;
}


class Shorten
{
    /* Static */
    public static $shorteners = array();

    public static function getAllShorteners()
    {
        if(count(self::$shorteners) == 0)
        {
            $files = glob(Config::$storageDir . '[a-z]*');
            //filter out the logfiles, because glob is not able to return files according to REGEX properly
            $files = array_filter($files, create_function('$item', 'return !strpos($item,".");'));

            //Sort the array of Files, newest first
            if(!Config::$sortAlphabetically)
                usort($files, create_function('$a,$b', 'return filemtime($b) - filemtime($a);'));

            foreach($files as $file)
            {
                $name = substr($file,strlen(Config::$storageDir));
                self::$shorteners[] = new Shorten($name);
            }
        }
        return self::$shorteners;
    }

    public static function Create($name, $url)
    {
        return (new Shorten($name))->save($url);
    }

    public static function Redirect($name)
    {
        try
        {
            (new Shorten($name))->redirectToUrl();
        }
        catch(Exception $e)
        {
            Helper::Redirect('http://' . SERVER);
        }
    }

    public static function getRandomShorten()
    {
        return Helper::RandString(4);
    }

    /* Class members */
    public $name;
    public $shortenedLink;
    private $filename;
    private $logFilename;
    private $url;
    private $statistic;

    public function __construct($name)
    {
        if(preg_match('/^[a-z ]+$/',$name) != 1)
            throw new IllegalCharacterException("The shortened url {$name} contains illegal characters. Only a-z is allowed.");

        $this->name = $name;
        $this->shortenedLink = 'http://' . SERVER . '/' . $name;
        $this->filename = Config::$storageDir . $name;
        $this->logFilename = $this->filename . '.log';
    }

    public function delete()
    {
        unlink($this->logFilename);
        unlink($this->filename);
    }

    public function getUrl()
    {
        if(!$this->url)
        {
            $this->url = file_get_contents($this->filename);
        }
        return $this->url;
    }

    public function getStatistics()
    {
        if(!$this->statistic)
        {
            $this->statistic = new Statistic($this->logFilename);
        }
        return $this->statistic;
    }

    public function getStatisticsJSON()
    {
        return (json_encode($this->getStatistics()));
    }

    protected function redirectToUrl()
    {
        if(!file_exists($this->filename))
            throw new ShortenNotExistsException();

        $this->track();
        Helper::Redirect($this->getUrl());
    }

    private function track()
    {
        $ip = $_SERVER['REMOTE_ADDR'];

        $statistics =
            '"' . date('d.m.Y H:i') . '";' .
            '"' . $ip . '";' .
            '"' .$_SERVER['HTTP_USER_AGENT'] . '";';

        //Location tracking; See http://ipinfo.io
        try{
            $geoLocation = json_decode(Helper::UrlGetContents("http://ipinfo.io/{$ip}/json"));
            if(is_object($geoLocation))
            {
                $statistics .=
                    '"' . $geoLocation->country . '";' .
                    '"' . $geoLocation->region . '";' .
                    '"' . $geoLocation->city . '";' .
                    '"' . $geoLocation->org . '";' .
                    '"' . $geoLocation->loc . '";' .
                    '"' . $geoLocation->hostname . '";';
            }
        }catch(Exception $e){}

        file_put_contents($this->logFilename, $statistics . "\n", FILE_APPEND);
    }

    private function save($url)
    {
        if(file_exists($this->filename))
            throw new ShortenAlreadyExistsException("Shorten url {$this->getUrl()} already exists.");

        file_put_contents($this->filename,$url);
        file_put_contents($this->logFilename,'');
        return $this;
    }
}

class Statistic
{
    public $numberOfHits;
    public $entries;

    public function __construct($logFilename)
    {
        $this->entries = str_replace("\n",'<br>',file_get_contents($logFilename));
        $this->numberOfHits = substr_count($this->entries,'<br>');
    }
}

//Exceptions
class ShortenNotExistsException extends Exception{}
class ShortenAlreadyExistsException extends Exception{}
class IllegalCharacterException extends Exception{}