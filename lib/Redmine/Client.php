<?php

namespace Redmine;

use SimpleXMLElement;

/**
 * Simple PHP Redmine client
 * @author Kevin Saliou <kevin at saliou dot name>
 * Website: http://github.com/kbsali/php-redmine-api
 */
class Client
{
    /**
     * @var array
     */
    private static $defaultPorts = array(
        'http'  => 80,
        'https' => 443,
    );

    /**
     * @var int
     */
    private $port;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $apikey;

    /**
     * @var boolean
     */
    private $checkSslCertificate = false;

    /**
     * @var boolean
     */
    private $checkSslHost = false;

    /**
     *
     * Flag to determine authentication method
     *
     * @var boolean
     */
    private $useHttpAuth = true;

    /**
     * @var array APIs
     */
    private $apis = array();
    
    /**
     * @var int|null Redmine response code, null if request is not still completed
     */
    private $responseCode = null;

    /**
     * Error strings if json is invalid
     */
    private static $json_errors = array(
        JSON_ERROR_NONE => 'No error has occurred',
        JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
        JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
        JSON_ERROR_SYNTAX => 'Syntax error',
    );

    /**
     * @param string $url
     * @param string $apikey
     */
    public function __construct($url, $apikey)
    {
        $this->url    = $url;
        $this->apikey = $apikey;
    }

    /**
     * @param  string                    $name
     * @return Api\AbstractApi
     * @throws \InvalidArgumentException
     */
    public function api($name)
    {
        if (!isset($this->apis[$name])) {
            switch ($name) {
                case 'attachment':
                    $api = new Api\Attachment($this);
                    break;
                // @todo finish implementation!
                case 'group':
                    $api = new Api\Group($this);
                    break;
                case 'custom_fields':
                    $api = new Api\CustomField($this);
                    break;
                case 'issue':
                    $api = new Api\Issue($this);
                    break;
                case 'issue_category':
                    $api = new Api\IssueCategory($this);
                    break;
                case 'issue_priority':
                    $api = new Api\IssuePriority($this);
                    break;
                // @todo finish implementation!
                case 'issue_relation':
                    $api = new Api\IssueRelation($this);
                    break;
                case 'issue_status':
                    $api = new Api\IssueStatus($this);
                    break;
                // @todo finish implementation!
                case 'membership':
                    $api = new Api\Membership($this);
                    break;
                case 'news':
                    $api = new Api\News($this);
                    break;
                case 'project':
                    $api = new Api\Project($this);
                    break;
                case 'query':
                    $api = new Api\Query($this);
                    break;
                case 'role':
                    $api = new Api\Role($this);
                    break;
                case 'time_entry':
                    $api = new Api\TimeEntry($this);
                    break;
                case 'time_entry_activity':
                    $api = new Api\TimeEntryActivity($this);
                    break;
                case 'tracker':
                    $api = new Api\Tracker($this);
                    break;
                case 'user':
                    $api = new Api\User($this);
                    break;
                case 'version':
                    $api = new Api\Version($this);
                    break;
                case 'wiki':
                    $api = new Api\Wiki($this);
                    break;
                default:
                    throw new \InvalidArgumentException();
            }
            $this->apis[$name] = $api;
        }

        return $this->apis[$name];
    }

    /**
     * Returns Url
     * @return string
     */
    public function getUrl()
    {
       return $this->url;
    }

    /**
     * HTTP GETs a json $path and tries to decode it
     * @param  string $path
     * @return array
     */
    public function get($path)
    {
        if (false === $json = $this->runRequest($path, 'GET')) {
            return false;
        }

        return $this->decode($json);
    }

    /**
     * Decodes json response
     * @param  string $json
     * @return array
     */
    public function decode($json)
    {
        $decoded = json_decode($json, true);
        if (null !== $decoded) {
            return $decoded;
        }
        if (JSON_ERROR_NONE === json_last_error()) {
            return $json;
        }

        return self::$json_errors[json_last_error()];
    }

    /**
     * HTTP POSTs $params to $path
     * @param  string $path
     * @param  string $data
     * @return mixed
     */
    public function post($path, $data)
    {
        return $this->runRequest($path, 'POST', $data);
    }

    /**
     * HTTP PUTs $params to $path
     * @param  string $path
     * @param  string $data
     * @return array
     */
    public function put($path, $data)
    {
        return $this->runRequest($path, 'PUT', $data);
    }

    /**
     * HTTP PUTs $params to $path
     * @param  string $path
     * @return array
     */
    public function delete($path)
    {
        return $this->runRequest($path, 'DELETE');
    }

    /**
     * Turns on/off ssl certificate check
     * @param  boolean $check
     * @return Client
     */
    public function setCheckSslCertificate($check = false)
    {
        $this->checkSslCertificate = $check;

        return $this;
    }

    /**
     * Turns on/off ssl host certificate check
     * @param  boolean $check
     * @return Client
     */
    public function setCheckSslHost($check = false)
    {
        $this->checkSslHost = $check;

        return $this;
    }

    /**
     * Turns on/off http auth
     * @param  bool   $use
     * @return Client
     */
    public function setUseHttpAuth($use = true)
    {
        $this->useHttpAuth = $use;

        return $this;
    }

    /**
     * Set the port of the connection
     * @param  int    $port
     * @return Client
     */
    public function setPort($port = null)
    {
        if (null !== $port) {
            $this->port = (int) $port;
        }

        return $this;
    }
    
    /**
     * Returns Redmine response code
     * @return int
     */
    public function getResponseCode() 
    {
        return $this->responseCode;
    }

    /**
     * Returns the port of the current connection,
     * if not set, it will try to guess the port
     * from the given $urlPath
     * @param  string $urlPath the url called
     * @return int    the port number
     */
    public function getPort($urlPath = null)
    {
        if (null === $urlPath) {
            return $this->port;
        }
        if (null !== $this->port) {
            return $this->port;
        }
        $tmp = parse_url($urlPath);

        if (isset($tmp['port'])) {
            $this->setPort($tmp['port']);

            return $this->port;
        }
        $this->setPort(self::$defaultPorts[$tmp['scheme']]);

        return $this->port;
    }

    /**
     * @param  string                        $path
     * @param  string                        $method
     * @param  string                        $data
     * @return false|SimpleXMLElement|string
     * @throws \Exception                    If anything goes wrong on curl request
     */
    private function runRequest($path, $method = 'GET', $data = '')
    {
        $this->responseCode = null;
        $this->getPort($this->url.$path);

        $curl = curl_init();
        if (isset($this->apikey) && $this->useHttpAuth) {
            curl_setopt($curl, CURLOPT_USERPWD, $this->apikey.':'.rand(100000, 199999) );
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }
        curl_setopt($curl, CURLOPT_URL, $this->url.$path);
        curl_setopt($curl, CURLOPT_VERBOSE, 0);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_PORT , $this->port);
        if (80 !== $this->port) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->checkSslCertificate);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->checkSslHost);
        }

        $tmp = parse_url($this->url.$path);
        if ('xml' === substr($tmp['path'], -3)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: text/xml',
                'X-Redmine-API-Key: '.$this->apikey
            ));
        }
        if ('json' === substr($tmp['path'], -4)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'X-Redmine-API-Key: '.$this->apikey
            ));
        }

        if ('/uploads.json' === $path || '/uploads.xml' === $path) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/octet-stream',
                'X-Redmine-API-Key: '.$this->apikey
            ));
        }

        switch ($method) {
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, 1);
                if (isset($data)) {curl_setopt($curl, CURLOPT_POSTFIELDS, $data);}
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (isset($data)) {curl_setopt($curl, CURLOPT_POSTFIELDS, $data);}
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default: // GET
                break;
        }
        $response = curl_exec($curl);
        $this->responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            $e = new \Exception(curl_error($curl), curl_errno($curl));
            curl_close($curl);
            throw $e;
        }
        curl_close($curl);

        if ($response) {
            if ('<' === substr($response, 0, 1)) {
                return new \SimpleXMLElement($response);
            }

            return $response;
        }

        return true;
    }
}
