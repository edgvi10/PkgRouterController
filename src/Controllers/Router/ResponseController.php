<?php

namespace EDGVI10\Controllers\Router;

class ResponseController
{
    public $debug;

    public $headers = [];
    private $sent = false;
    public $statusCode = 200;
    public $statusMessage = "";

    public $useJson = true;
    public $contentType = "text/html; charset=utf-8";

    public function __construct($config = [])
    {
        $this->debug = $config["debug"] ?? false;
        $this->useJson = $config["useJson"] ?? true;
        if ($this->useJson) $this->contentType = "application/json; charset=utf-8";

        return $this;
    }

    public function setHeader($name = null, $value = null)
    {
        if ($name && $value) header("$name: $value");
        if ($name && !$value) header($name);

        return $this;
    }

    public function withJson($data, $code = 200)
    {
        $this->sent = true;
        http_response_code($code);
        header("Content-Type: application/json");
        foreach ($this->headers as $header) header($header);

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function withError($message = "An error occurred.", $code = 400)
    {
        $this->sent = true;
        $headers = getallheaders();
        $isJsonRequest = (
            (isset($headers["Accept"]) && strpos($headers["Accept"], "application/json") !== false)
            || (isset($headers["Content-Type"]) && strpos($headers["Content-Type"], "application/json") !== false)
            || $this->useJson
        );

        http_response_code($code);
        if (!$isJsonRequest) :
            foreach ($this->headers as $header) header($header);
            echo "<h1>Error: $message</h1>";
            echo "<p>Status Code: $code</p>";
            echo "<pre>" . print_r(debug_backtrace()[0], true) . "</pre>";
            exit;
        endif;

        header("Content-Type: application/json");
        foreach ($this->headers as $header) header($header);

        echo json_encode([
            "error" => true,
            "code" => $code,
            "message" => $message,
            "backtrace" => $this->debug ? debug_backtrace()[0] : null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function withHtml($html, $code = 200)
    {
        $this->sent = true;
        http_response_code($code);
        header("Content-Type: text/html; charset=utf-8");
        foreach ($this->headers as $header) header($header);

        echo $html;
        exit;
    }

    public function withDownload($filePath, $fileName = null)
    {
        $this->sent = true;
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo "File not found.";
            exit;
        }

        $fileName = $fileName ?? basename($filePath);
        header("Content-Description: File Transfer");
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Expires: 0");
        header("Cache-Control: must-revalidate");
        header("Pragma: public");
        header("Content-Length: " . filesize($filePath));

        readfile($filePath);
        exit;
    }

    public function withStatus($code = 200, $message = null)
    {
        http_response_code($code);
        if ($message) {
            $this->sent = true;
            echo $message;
            exit;
        }
        return $this;
    }

    public function isSent()
    {
        return $this->sent;
    }
}
