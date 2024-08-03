<?php

namespace wesone\Wyne;

class Route
{
    public function __construct(
        public string $method,
        public string $path,
        public mixed $controller,
    ) {
    }
}
