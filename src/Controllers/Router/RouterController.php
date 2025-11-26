<?php

namespace EDGVI10\Controllers\Router;

use EDGVI10\Controllers\Router\RequestController;
use EDGVI10\Controllers\Router\ResponseController;

class RouterController extends RequestController
{
    public $basePath = '';
    public $useJson = true;
    public $debug = false;
    public $logErrors = false;
    public $logPath = '';

    public $route = null;
    public $routes = [];
    public $params = [];

    public $request = null;
    public $response = null;

    public $middlewares = [];
    public $groupMiddlewares = [];

    public function __construct($config = [])
    {
        $this->basePath = rtrim($config["basePath"] ?? "");
        $this->useJson = $config["useJson"] ?? true;
        $this->debug = $config["debug"] ?? false;
        $this->logErrors = $config["logErrors"] ?? false;
        $this->logPath = $config["logPath"] ?? __DIR__ . "/../../logs/";

        $this->route = $this->getRoute();
        $this->request = new RequestController();
        $this->response = new ResponseController(["debug" => $this->debug]);

        return $this;
    }

    public function getRoute()
    {
        $route = explode("?", $_SERVER["REQUEST_URI"])[0];
        $route = rtrim($route, "/");

        return $route;
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function getBasePath()
    {
        return $this->basePath;
    }

    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }

    public function options($route, $callback, $middlewares = [])
    {
        $this->addRoute("OPTIONS", $route, $callback, $middlewares);
    }

    public function get($route, $callback, $middlewares = [])
    {
        $this->addRoute("GET", $route, $callback, $middlewares);
    }

    public function post($route, $callback, $middlewares = [])
    {
        $this->addRoute("POST", $route, $callback, $middlewares);
    }

    public function put($route, $callback, $middlewares = [])
    {
        $this->addRoute("PUT", $route, $callback, $middlewares);
    }

    public function patch($route, $callback, $middlewares = [])
    {
        $this->addRoute("PATCH", $route, $callback, $middlewares);
    }

    public function delete($route, $callback, $middlewares = [])
    {
        $this->addRoute("DELETE", $route, $callback, $middlewares);
    }

    public function getParams($key = null)
    {
        if ($key) return $this->params[$key];
        return $this->params;
    }

    public function setParam($param)
    {
        $this->params[] = $param;
    }

    public function addRoute($method, $path, $callback, $middlewares = [])
    {
        $path = $this->basePath . "/" . trim($path, "/");

        $this->routes[] = [
            "method" => $method,
            "path" => trim($path),
            "callback" => $callback,
            "middlewares" => array_merge($this->groupMiddlewares, $middlewares), // attach current group middlewares and route-specific middlewares
        ];
        return $this;
    }

    // create group of routes with prefix
    public function group($prefix, $callback, $middlewares = [])
    {
        $prefix = trim($prefix, "/");
        $previousBasePath = $this->basePath;
        $previousMiddlewares = $this->groupMiddlewares;

        $this->basePath = rtrim($this->basePath, "/") . "/" . $prefix;
        $this->groupMiddlewares = array_merge($this->groupMiddlewares, $middlewares);

        call_user_func($callback, $this);

        // restore previous state
        $this->basePath = $previousBasePath;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    public function addMiddleware($middleware)
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    private function executeMiddlewares($middlewares, $request, $response, $params = [])
    {
        foreach ($middlewares as $middleware) {
            $result = call_user_func($middleware, $request, $response, $params);

            // If middleware returns false or response was sent, stop execution
            if ($result === false || $response->isSent()) {
                return false;
            }
        }
        return true;
    }

    public function run()
    {
        $matches = [];
        $method = $this->request->getMethod();
        $path = $this->route;

        foreach ($this->routes as $route) {
            $routePath = rtrim($route["path"], "/");
            $routeMethod = $route["method"];
            $routeCallback = $route["callback"];
            $routeMiddlewares = $route["middlewares"] ?? [];

            $pattern = preg_replace('/:\w+\((.*?)\)/', '($1)', $routePath);
            $pattern = preg_replace('/:\w+/', '([^\/]+)', $pattern);
            $pattern = str_replace('/', '\/', $pattern);
            $pattern = '/^' . $pattern . '$/';

            if ($method === $routeMethod && preg_match($pattern, $path, $matches)) {
                array_shift($matches); // remove full match

                // extract named parameters
                preg_match_all('/:([\w]+)(\((.*?)\))?/', $routePath, $paramNames);
                $params = [];
                foreach ($paramNames[1] as $index => $name) {
                    if (isset($matches[$index])) {
                        $params[$name] = $matches[$index];
                        $this->setParam($matches[$index]);
                    }
                }

                // Execute global middlewares first
                if (!$this->executeMiddlewares($this->middlewares, $this->request, $this->response, $params)) {
                    return;
                }

                // Execute route-specific middlewares
                if (!$this->executeMiddlewares($routeMiddlewares, $this->request, $this->response, $params)) {
                    return;
                }

                // call the route callback
                return call_user_func($routeCallback, $this->request, $this->response, $params);
            }
        }

        $this->response->withError("Route not found", 404);
    }
}
