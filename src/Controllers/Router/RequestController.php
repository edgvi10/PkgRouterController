<?php

namespace EDGVI10\Controllers\Router;

class RequestController
{
    public $method = '';
    public $route = '';
    public $contentType = '';


    public function __construct()
    {
        $this->method = $this->getMethod();
        $this->route = $this->getRoute();
        $this->contentType = $_SERVER["CONTENT_TYPE"] ?? 'application/json';

        return $this;
    }

    public function getRoute()
    {
        $route = $_SERVER["REQUEST_URI"];
        $route = parse_url($route, PHP_URL_PATH);
        $route = urldecode($route);
        $route = rtrim($route, "/");
        return $route;
    }

    public function getMethod()
    {
        return $_SERVER["REQUEST_METHOD"];
    }

    public function getHost()
    {
        $ssl = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") || $_SERVER["SERVER_PORT"] == 443;
        $protocol = $ssl ? "https://" : "http://";
        return $protocol . $_SERVER["HTTP_HOST"];
    }

    public function getContentType()
    {
        return $_SERVER["CONTENT_TYPE"] ?? 'application/json';
    }

    public function getUserAgent()
    {
        return $_SERVER["HTTP_USER_AGENT"] ?? null;
    }

    public function getAuthencation($headerName = "Authorization")
    {
        $header = getallheaders();
        if (!isset($header[$headerName])) return null;

        if ($headerName !== "Authorization") return $header[$headerName]; // Return custom header value

        if ($headerName === "Authorization" && strpos($header[$headerName], "Bearer ") !== false) :
            $auth = trim(str_replace("Bearer ", "", $header[$headerName]));
            return (isset($auth) && !empty($auth)) ? $auth : null;
        elseif ($headerName === "Authorization" && strpos($header[$headerName], "Basic ") !== false) :
            $auth = trim(str_replace("Basic ", "", $header[$headerName]));
            return (isset($auth) && !empty($auth)) ? $auth : null;
        endif;

        return null;
    }

    public function getHeaders()
    {
        return getallheaders();
    }

    public function getHeader($key = null)
    {
        $headers = $this->getHeaders();
        if ($key && isset($headers[$key])) return $headers[$key];
        return null;
    }

    public function getAuth()
    {
        $header = $this->getHeaders();
        if (!isset($header["Authorization"])) return null;
        $auth = trim(str_replace("Bearer ", "", $header["Authorization"]));
        return (isset($auth) && !empty($auth)) ? $auth : null;
    }

    public function getParams($key = null)
    {
        $params = [];
        $params = explode("/", $this->getRoute());
        $params = array_filter($params, function ($param) {
            return !empty($param);
        });
        if ($key) return $params[$key];
        return $params;
    }

    public function getQueryParams($key = null)
    {
        if ($key) return $_GET[$key];
        return $_GET;
    }

    public function getPostParams($key = null)
    {
        if ($key) return $_POST[$key];
        return $_POST;
    }

    public function getFiles($key = null)
    {
        if ($key) return $_FILES[$key];
        return $_FILES;
    }

    public function getBody()
    {
        $data = json_decode(file_get_contents("php://input"), true);
        if (json_last_error() != JSON_ERROR_NONE) return $_POST;

        return $data;
    }
}
