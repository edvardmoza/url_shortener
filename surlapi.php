<?php
require_once(dirname(__FILE__) . '/core.php');

//Load the configuration from the surl.json config file
Config::Load();

/**
 * Interface iApiController
 */
interface iApiController
{
    public function handleRequest(array $request);
}

/**
 * Class VersionController
 * Return the current Version of the API
 */
class version implements iApiController
{
    // surlapi/version GET
    public function handleRequest(array $request)
    {
        header("Content-Type: application/json");
        die(json_encode(array("V" => 2.0)));
    }
}

/**
 * Class Md5Controller
 * Return the current Version of the API
 */
class md5 implements iApiController
{
    // surlapi/md5/[VALUE] GET
    public function handleRequest(array $request)
    {
        header("Content-Type: application/json");
        die(json_encode(array("md5" => md5($request[1]))));
    }
}

/**
 * Class LogController
 * Returns the content of the Logfile
 */
class surl implements iApiController
{
    private $shorten;

    // surlapi/surl/[NAME]/[optional:Attribute] GET
    // surlapi/surl/ POST
    public function handleRequest(array $request)
    {
        try
        {
            //decide whether its an action or its a specific shorten
            switch($_SERVER['REQUEST_METHOD'])
            {
                // surlapi/surl POST -> Create new
                case 'POST':
                    $this->create();
                    break;
                case 'GET':
                    // surlapi/surl/[NAME]/[optional:Action] GET
                    //identify the shorten
                    $this->shorten = new Shorten($request[1]);
                    //call the action method of the shorten if none or not existing call redirect action
                    if($request[2] && method_exists($this,$request[2]))
                        $this->$request[2]();
                    else
                        $this->redirect();
                    break;
            }
        }
        catch(Exception $e)
        {
            //Convert to APIException (default Bad Request) to add corresponding Header
            if(!is_a($e,'APIException'))
                $e = new BadRequestException($e->getMessage());

            die($e->getMessage());
        }
    }

    /**
     * Create a new shorten
     */
    private function create()
    {
        if(($url = Helper::Get('url',$_POST)))
        {
            if(Config::$passwordProtected && Helper::get('auth',$_POST) != Config::$passwordMD5Encrypted)
                throw new UnauthorizedException();
            Helper::ValidateURL($url);
            if(!Config::$choosableShorten && Helper::Get('surl',$_POST))
                throw new ForbiddenException('Choosable shorten is deactivated on this server');
            $name = Config::$choosableShorten ? Helper::Get('surl',$_POST,Shorten::GetRandomShortenName()) : Shorten::GetRandomShortenName();
            $shorten = Shorten::Create($name, $url);
            header('HTTP/1.0 201 Created');
            header("Content-Type: application/json");
            die(json_encode($shorten));
        }
    }

    private function redirect()
    {
        $this->shorten->redirectToUrl();
    }

    /**
     * Return the logContent as JSON Object
     */
    private function log()
    {
        header("Content-Type: application/json");
        die($this->shorten->getStatisticsJSON());
    }
}

//Entry point, handle request
try
{
    if($request = Helper::Get('request',$_GET))
    {
        //split the request by slash
        $params = explode('/', $request);
        //find a controller for the first parameter of the request
        if(!class_exists($params[0]))
            throw new BadRequestException();
        $controller = new $params[0]();
        $controller->handleRequest($params);
    }
}
catch(Exception $e)
{
    die($e->getMessage());
}

//  -- Exceptions --
/**
 * Class APIException
 * Make sure every APIException adds a corresponding HTTP Header Status Code
 */
abstract class APIException extends Exception
{
    public function __construct($message = null)
    {
        parent::__construct($message);
    }
}

/**
 * Class BadRequestException
 */
class BadRequestException extends APIException
{
    public function __construct($message = null)
    {
        header('HTTP/1.0 400 Bad Request');
        parent::__construct($message);
    }
}

/**
 * Class BadRequestException
 */
class UnauthorizedException extends APIException
{
    public function __construct($message = null)
    {
        header('HTTP/1.0 401 Unauthorized');
        parent::__construct($message);
    }
}

/**
 * Class BadRequestException
 */
class ForbiddenException extends APIException
{
    public function __construct($message = null)
    {
        header('HTTP/1.0 403 Forbidden');
        parent::__construct($message);
    }
}