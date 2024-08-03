<?php

namespace wesone\Wyne;

use wesone\Wyne\{Request, Response};

interface IController
{
    public static function execute(Request $request, Response $response);
}
