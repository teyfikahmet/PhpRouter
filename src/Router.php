<?php

/**
 * @author Teyfikahmet
 */

namespace Teyfikahmet\PhpRouter;

use Closure;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Router
{
    protected array $config = [
        "controller_namespace" => "",
        "middleware_namespace" => "",
    ];
    protected $patterns = [
        ':all' => '(.*)',
        ':any' => '([^/]+)',
        ':id' => '(\d+)',
        ':int' => '(\d+)',
        ':number' => '([+-]?([0-9]*[.])?[0-9]+)',
        ':float' => '([+-]?([0-9]*[.])?[0-9]+)',
        ':bool' => '(true|false|1|0)',
        ':string' => '([\w\-_]+)',
        ':slug' => '([\w\-_]+)',
        ':uuid' => '([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})',
        ':date' => '([0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]))',
    ];
    protected array $routes = [];
    protected $request;
    protected $response;
    protected $noFoundCallBack = null;
    protected $errorCallBack = null;
    public function __construct(array $config = [])
    {
        $this->config = $config ? array_merge($this->config, $config) : $this->config;
        $this->request = Request::createFromGlobals();
        $this->response = new Response();
    }

    protected function addRoute(string $method, string $path, string | array | Closure $callback, $name, string $middleware) : void
    {
        if(array_key_exists($name, $this->routes)) {
            throw new \Exception("Route already exists");
        }
        $this->routes[$path] = [
            "name" => $name,
            "path" => $path,
            "callback" => $callback,
            "middleware" => $middleware,
            "method" => $method,
        ];
    }

    /**
     * @param string $path
     * @param string | array | Closure $callback
     */
    public function group(string $path, Closure $callback) : void
    {
        $router = new Router($this->config);
        $callback($router);
        $routes = $router->getRoutes();
        foreach($routes as $route) {
            $name = $route['name'];
            $this->addRoute($route["method"], $route["path"] == '/' ? $path  : $path . $route["path"] , $route["callback"], $name, $route["middleware"]);
        }
        unset($router);
    }

    /**
     * @param string $name
     * @param string $path
     * @param string | array | Closure $callback
     * @param string $middleware
     * @return Router
     */
    public function any(string $name, string $path, Closure | string | array $callback, string $middleware = "") : Router
    {
        $this->addRoute('ANY', $path, $callback, $name, $middleware);
        return $this;
    }

    /**
     * @param string $name
     * @param string $path
     * @param string | array | Closure $callback
     * @param string $middleware
     * @return Router
     */
    public function get(string $name, string $path, Closure | string | array $callback, string $middleware = "") : Router
    {
        $this->addRoute('GET', $path, $callback, $name, $middleware);
        return $this;
    }

    /**
     * @param string $name
     * @param string $path
     * @param string | array | Closure $callback
     * @param string $middleware
     * @return Router
     */
    public function post(string $name, string $path, Closure | string | array $callback, string $middleware = "") : Router
    {
        $this->addRoute('POST', $path, $callback, $name, $middleware);
        return $this;
    }

    /**
     * @param string $name
     * @param string $path
     * @param string | array | Closure $callback
     * @param string $middleware
     * @return Router
     */
    public function put(string $name, string $path, Closure | string | array $callback, string $middleware = "") : Router
    {
        $this->addRoute('PUT', $path, $callback, $name, $middleware);
        return $this;
    }

    /**
     * @param string $name
     * @param string $path
     * @param string | array | Closure $callback
     * @param string $middleware
     * @return Router
     */
    public function delete(string $name, string $path, Closure | string | array $callback, string $middleware = "") : Router
    {
        $this->addRoute('DELETE', $path, $callback, $name, $middleware);
        return $this;
    }

    /**
     * @param string $name
     * @param string $path
     * @param string | array | Closure $callback
     * @param string $middleware
     * @return Router
     */
    public function patch(string $name, string $path, Closure | string | array $callback, string $middlewares = "") : Router
    {
        $this->addRoute('PATCH', $path, $callback, $name, $middlewares);
        return $this;
    }

    /**
     * @param Closure | string | array $callback
     * @return Router
     */
    public function notFound(Closure | string | array $callback) : Router
    {
        $this->noFoundCallBack = $callback;
        return $this;
    }

    /**
     * @param Closure | string | array $callback
     * @return Router
     */
    public function error(Closure | string | array $callback) : Router
    {
        $this->errorCallBack = $callback;
        return $this;
    }

    protected function match(string $routePath, string $path) : array | bool
    {
        $routePath = str_replace(array_keys($this->patterns), array_values($this->patterns), $routePath);
        if(preg_match("#^$routePath(/)?$#", $path, $matches)) {
            return $matches;
        }
        return false;
    }

    protected function runMiddleware(string $middleware, Closure $next) : void
    {
        try
        {
            if(empty($middleware) || strlen($middleware) == 0) {
                $next();
                return;
            }
            else if((strpos($middleware, '\\') !== false) || strpos($middleware, '/') !== false){}
            else
                $middleware = $this->config["middleware_namespace"] . $middleware;
            if(!class_exists($middleware)){
                throw new \Exception("Middleware class not found");
                return;
            }
            $middleware = new $middleware();
            if(!method_exists($middleware, 'handle')){
                throw new \Exception("Middleware class must have handle method");
                return;
            }
            $params = [$this->request, $next];
            call_user_func_array([$middleware, 'handle'], $params);
        }
        catch(\Exception $e)
        {
            $this->runError($e);
        }
    }

    protected function runNotFound() : void
    {
        if($this->noFoundCallBack)
            $this->runCallback($this->noFoundCallBack);
        else
            echo "404 Not Found";
    }

    protected function runError(Exception $e): void
    {   
        if($this->errorCallBack)
            $this->runCallback($this->errorCallBack, [$e]);
        else
            echo "Error: " . $e->getMessage();
    }

    protected function runCallback(string | array | Closure $callback, $params = []) : void
    {
        try
        {
            if(is_string($callback)) {
                $callback = explode('@', $callback);
                $controller = $callback[0];
                $method = $callback[1];
                $controller = str_replace('/', '\\', $controller);
                $controller = $this->config['controller_namespace'] . $controller;
                if(!class_exists($controller)) {
                    throw new \Exception("Class $controller not found");
                }
                $controller = new $controller();
                if(!method_exists($controller, $method)) {
                    throw new \Exception("Method $method not found in ". $controller::class);
                }
                $params = array_merge([$this->request], $params);
                echo call_user_func_array([$controller, $method], $params);
            }
            else if(is_array($callback))
            {
                $controller = $callback[0];
                $method = $callback[1];
                $controller = str_replace('/', '\\', $controller);
                if(!class_exists($controller)) {
                    throw new \Exception("Class $controller not found");
                }
                $controller = new $controller();
                if(!method_exists($controller, $method)) {
                    throw new \Exception("Method $method not found in ". $controller::class);
                }
                $params = array_merge([$this->request], $params);
                echo call_user_func_array([$controller, $method], $params);
            }
            else if($callback instanceof Closure)
            {
                $params = array_merge([$this->request], $params);
                echo call_user_func_array($callback, $params);
            }
        }
        catch(\Exception $e)
        {
            $this->runError($e);
        }
    }

    /**
     * @return void
     */
    public function run() : void
    {
        try {
            $path = $this->request->server->get('PATH_INFO');
            if(!$path)
                $path = '/';
            $method = $this->request->server->get('REQUEST_METHOD');
            foreach($this->routes as $route)
            {
                $routePath = $route['path'];
                $matches = $this->match($routePath, $path);
                if(($route['method'] == $method || $route['method'] == 'ANY') && $matches)
                {
                    array_shift($matches);
                    $this->runMiddleware($route['middleware'], function() use ($route, $matches) {
                        $this->runCallback($route['callback'], $matches);
                    });
                    return;
                }
            }
            $this->runNotFound();
            return;

        } catch (\Exception $e) {
            $this->runError($e);
        }
    }

    /**
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @param string $name
     * @return string | bool
     */
    public function getUrlByName(string $name, array $params = []) : string | bool
    {
        foreach($this->routes as $route)
        {
            if($route['name'] == $name)
            {
                if(!empty($params))
                {
                    $routePath = $route['path'];
                    if(!preg_match_all('/:([a-zA-Z0-9]+)/', $routePath, $matches))
                        return $routePath;
                    if(count($matches[0]) == 0)
                        return $routePath;
                    if(count($params) != count($matches[0]))
                        return $routePath;
                    $patterns = [];
                    for($i = 0; $i < count($matches[0]); $i++)
                    {
                        $patterns[$i] = $matches[0][$i];
                    }
                    $routePath = str_replace(array_values($patterns), array_values($params), $routePath);
                    return $routePath;
                }
                else
                    return $route['path'];
            }
        }
        return false;
    }
}