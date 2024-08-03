<?php

namespace wesone\Wyne;

use AllowDynamicProperties;

#[AllowDynamicProperties]
class Request
{
    public string $method;
    public string $path;
    public array $query;
    public mixed $body;
    public array $params;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];

        $parsed_url = parse_url($_SERVER['REQUEST_URI']);
        $this->path = $parsed_url['path'] ?? '/';

        if (strlen($this->path) > 1) // "/myroute/" -> "/myroute"; "/" -> "/"
            $this->path = rtrim($this->path, '/');

        $this->query = $_GET;
        $this->body = $this->parseBody();

        $this->params = [];
    }

    private function parseBody()
    {
        $body = null;
        switch (@$_SERVER['CONTENT_TYPE']) {
            case 'application/x-www-form-urlencoded':
                parse_str(file_get_contents('php://input'), $body);
                break;
            case 'application/json':
                $body = json_decode(file_get_contents('php://input'), true);
                break;
            default:
                $body = $_POST;
        };
        return is_array($body)
            ? $body
            : [];
    }

    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function set(string $key, $value)
    {
        $this->$key = $value;
    }
}
