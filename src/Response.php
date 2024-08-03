<?php

namespace wesone\Wyne;

class Response
{
    public bool $headersSent;

    public function __construct()
    {
        $this->headersSent = false;
    }

    public function status(int $code, string $message = null)
    {
        if ($this->headersSent)
            return;

        http_response_code($code);
        if ($message !== null)
            $this->send($message);
    }

    public function set(mixed $headers, string $value = null)
    {
        // if($this->headersSent) // do not swallow the php warning
        //     return;

        if (!is_array($headers))
            $headers = [$headers => $value];

        foreach ($headers as $header => $value)
            header($header . ': ' . $value);
    }

    public function send(string $data)
    {
        echo $data;
        if (!$this->headersSent)
            $this->headersSent = true;
    }

    public function json(mixed $data)
    {
        // a second execution of this in one request may fail
        $json = json_encode($data);
        $this->set([
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($json)
        ]);
        $this->send($json);
    }
}
