# Wyne

A basic HTTP request handler for PHP.

## Table of Contents
1. [Router](#router) - Handles the request
    - [Adding routes](#adding-routes)
    - [Middlewares](#middlewares)
    - [Error handlers](#error-handlers)
2. [Route](#route) - Define a route that will be used by the router
3. [IController](#icontroller) - Controller interface for the route
4. [Request](#request) - A request instance will be passed to the controller
5. [Response](#response) - A response instance will be passed to the controller

## Router 
The router handles the HTTP request and calls the desired controller. 

After routes were added to the router, simply execute the `run` method.

```php
use wesone\Wyne\Router;

//TODO add routes

Router::run();
```

### Adding routes
There are different ways to add a route to the router.

- Use the `add` method that takes a HTTP method (`string`), the request path (`string`) and a callback (`callable`).
- For each HTTP method there is a method that takes the request path (`string`) and a callback (`callable`).
- Use the `register` method that takes an array of [`Route`](#route) instances

```php
use wesone\Wyne\{Router, Route};

Router::add('POST', '/something/add', ['MyClass', 'addMethod']);
Router::get('/something/get/([a-z]+)', ['MyClass', 'getMethod']);
Router::register([
    new Route(
        'PATCH', 
        '/something/patch/([a-z]+)', 
        ['MyClass', 'patchMethod']
    ),
    new Route(
        'DELETE', 
        '/something/delete/([a-z]+)', 
        ['MyClass', 'deleteMethod']
    )
]);
```

Available HTTP methods are:
```
all // matches every HTTP method
get
head
post
put
delete
connect
options
trace
patch
```

### Middlewares
Wyne also supports middlewares.

Middlewares are just callbacks that take a [request](#request) and a [response](#response) instance just like route callbacks. However the middlewares will all run before any route callback.

As the third parameter there is the `$next` callable that has to be called to execute the next middleware or the route callback.

To register a middleware, execute the `use` method that takes as many callback (`callable`) parameters as you like. Optionally the first parameter can be a path (`string`), if the middleware should only be executed for a specific path. The path can also be a regular expression.

```php
// index.php

require_once __DIR__ . '/Authenticator.php';

use wesone\Wyne\Router;

Router::use(['Authenticator', 'auth']);
Router::use('/something/([a-z]+)', ['Authenticator', 'sendSomeHeader']);

Router::post('/something/add', ['MyClass', 'addMethod']);
Router::get('/something/get', ['MyClass', 'getMethod']);

Router::run();
```
```php
// Authenticator.php

use wesone\Wyne\{Request, Response};

class Authenticator
{
    public static function auth(
        Request $req, 
        Response $res, 
        callable $next
    )
    {
        if (!$isAuthenticated/* TODO check if user is authenticated */) {
            $res->status(401, 'You are not authenticated :(');
            return;
        }
        $next();
    }

    public static function sendSomeHeader(
        Request $req, 
        Response $res, 
        callable $next
    )
    {
        $res->set('X-Requested-Something', $req->params[0]);
        $next();
    }
}
```

### Error handlers
If a path was requested that does not match any of the available routes or if the request method does not match, the router will send a `404` or `405` HTTP status code. With `setInvalidPathCallback` and `setInvalidMethodCallback`, You can register callbacks for those cases if needed.

```php
use wesone\Wyne\Router;

Router::setInvalidPathCallback(['Logger', 'logNotFound']);
Router::setInvalidMethodCallback(['Logger', 'logMethodNotAllowed']);

//TODO add routes

Router::run();
```

## Route
A route instance can be used for the router's route registration.

It has 3 parameters 
1. `string $method` - The request method for this route.
2. `string $path` - The request path. This can also be a regular expression.
3. `mixed $controller` - The controller can be a `callable` or a class that implements the [`IController`](#icontroller) interface.

```php
// index.php

require_once __DIR__ . '/MyClass.php';

use wesone\Wyne\{Router, Route, Request, Response};

Router::register([
    new Route(
        'POST', 
        '/something/add', 
        function(Request $req, Response $res) {
            $res->send("Creating something");
        }
    ),
    new Route(
        'GET',
        '/something/get/([a-z]+)', 
        'MyClass' // MyClass implements the IController
    ),
    new Route(
        'PATCH',
        '/something/patch/([a-z]+)', 
        ['MyClass', 'patchMethod']
    )
]);
```
```php
// MyClass.php

use wesone\Wyne\{IController, Request, Response};

class MyClass implements IController
{
    public static function execute(Request $req, Response $res)
    {
        $id = $req->params[0];
        $res->json([
            'id' => $id
        ]);
    }

    public static function patchMethod(
        Request $req, 
        Response $res
    )
    {
        $id = $req->params[0];
        $values = $req->body;
        $res->send("Patching some values for ID: $id");
    }
}
```

## IController
The `IController` interface can be used to force a class to have an `execute` method.

For [routes](#route) that have a string as controller instead of a callable, the [router](#router) will handle that string as class name and uses the `execute` method of that class as callback.

```php
use wesone\Wyne\{IController, Request, Response};

class MyClass implements IController
{
    public static function execute(Request $req, Response $res)
    {
        $res->status(200);
    }
}
```

## Request
Every callback will receive a request instance as first parameter.

It has the following properties:
- `string $method` - The requested method.
- `string $path` - The requested path.
- `array $query` - The query parameters of the request (equivalent to `$_GET`).
- `mixed $body` - The parsed request body. The parsing is based on the content type of the request 
- `array $params` - If the route's path is a regular expression that contains capture groups this array holds all matches

You can add dynamic properties to the request with the `set` method. This is useful for middlewares to enrich the request with data that the route callback can use.

```php
use wesone\Wyne\{IController, Request, Response};

class MyMiddleware implements IController
{
    public static function execute(
        Request $req, 
        Response $res, 
        callable $next
    )
    {
        $req->set('isAuthenticated', true);
        $next();
    }
}

class MyRoute implements IController
{
    public static function execute(
        Request $req, 
        Response $res
    )
    {
        $responseMessage = $req->isAuthenticated
            ? 'You are authenticated :)'
            : 'You are not authenticated :(';
        $req->send($responseMessage);
    }
}
```

## Response
Every callback will receive a response instance as second parameter.

It has the following methods to construct a response for the HTTP request:
- `status(int $code, string $message = null)` - To set the HTTP status code and an optional message.
- `set(string $key, string $value)` - To send a header (e.g. `set('Content-Type', 'application/json')`).
- `set(array $headers)` - To send multiple headers (e.g. `set(['Content-Type' => 'application/json', 'Content-Length' => 42])`).
- `send(string $data)` - Add data to the response body.
- `json(mixed $data)` - Send json encoded data as response body. This will also set the content type of the response to `application/json`