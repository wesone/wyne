<?php

namespace wesone\Wyne;

use wesone\Wyne\{Request, Response, Route};

class Router
{
    private static $middlewares = [];
    private static $routes = [];
    private static $invalidPathCallback = null;
    private static $invalidMethodCallback = null;

    public static function use(mixed $path, callable ...$callbacks)
    {
        if (is_callable($path)) {
            array_unshift($callbacks, $path);
            $path = null;
        }

        array_push(self::$middlewares, [
            'path' => $path,
            'callbacks' => $callbacks
        ]);
    }

    public static function add(string $method, mixed $path, callable $callback)
    {
        array_push(self::$routes, [
            'method' => $method,
            'path' => $path,
            'callback' => $callback
        ]);
    }

    public static function all(mixed $path, callable $callback)
    {
        return self::add(__FUNCTION__, $path, $callback);
    }

    public static function get(mixed $path, callable $callback)
    {
        return self::add(__FUNCTION__, $path, $callback);
    }

    public static function head(mixed $path, callable $callback)
    {
        return self::add(__FUNCTION__, $path, $callback);
    }

    public static function post(mixed $path, callable $callback)
    {
        return self::add(__FUNCTION__, $path, $callback);
    }

    public static function put(mixed $path, callable $callback)
    {
        return self::add(__FUNCTION__, $path, $callback);
    }

    public static function delete(mixed $path, callable $callback)
    {
        return self::add(__FUNCTION__, $path, $callback);
    }

    public static function connect(mixed $path, callable $callback)
    {
        return self::add(__FUNCTION__, $path, $callback);
    }

    public static function options(mixed $path, callable $callback)
    {
        return self::add(__FUNCTION__, $path, $callback);
    }

    public static function trace(mixed $path, callable $callback)
    {
        return self::add(__FUNCTION__, $path, $callback);
    }

    public static function patch(mixed $path, callable $callback)
    {
        return self::add(__FUNCTION__, $path, $callback);
    }

    /**
     * Convenience method to allow adding multiple routes at once.
     *
     * @param Route[] $routes
     * @return void
     */
    public static function register(array $routes)
    {
        foreach ($routes as $route) {
            if (!($route instanceof Route))
                continue;
            $method = $route->method;
            $path = $route->path;
            $controller = $route->controller;

            $callback = is_callable($controller)
                ? $controller
                : [$controller, 'execute'];
            Router::$method($path, $callback);
        }
    }

    public static function setInvalidPathCallback(callable $callback)
    {
        self::$invalidPathCallback = $callback;
    }

    public static function setInvalidMethodCallback(callable $callback)
    {
        self::$invalidMethodCallback = $callback;
    }

    private static function match(mixed $path, Request $req, string $basePath)
    {
        $hasCustomBasePath = $basePath !== '' && $basePath !== '/';
        if ($hasCustomBasePath)
            $path = '(' . $basePath . ')' . $path;

        if (preg_match('#^' . $path . '$#', $req->path, $matches)) {
            array_shift($matches); // first element contains the whole string
            if ($hasCustomBasePath)
                array_shift($matches);

            $req->setParams($matches);
            return true;
        }
        return false;
    }

    public static function run(string $basePath = '/')
    {
        $req = new Request();
        $res = new Response();

        foreach (self::$middlewares as ['path' => $path, 'callbacks' => $callbacks]) {
            if ($path === null || self::match($path, $req, $basePath)) {
                foreach ($callbacks as $callback) {
                    $next = false;
                    call_user_func_array($callback, [
                        $req,
                        $res,
                        function () use (&$next) {
                            $next = true;
                        }
                    ]);
                    if (!$next)
                        return;
                }
            }
        }

        $foundPath = false;
        $foundMethod = false;
        $path = $req->path;
        $method = $req->method;

        foreach (self::$routes as ['method' => $routeMethod, 'path' => $path, 'callback' => $callback]) {
            if (self::match($path, $req, $basePath)) {
                $foundPath = true;

                // check method
                $routeMethod = strtolower($routeMethod);
                if ($routeMethod === 'all' || strtolower($method) === $routeMethod) {
                    $foundMethod = true;

                    call_user_func_array($callback, [
                        $req,
                        $res
                    ]);
                    break;
                }
            }
        }

        if ($foundMethod)
            return;

        if (!$foundPath) {
            http_response_code(404);
            if (self::$invalidPathCallback)
                call_user_func_array(self::$invalidPathCallback, [$path]);
            return;
        }

        http_response_code(405);
        if (self::$invalidMethodCallback)
            call_user_func_array(self::$invalidMethodCallback, [$path, $method]);
    }
}
