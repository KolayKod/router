<?php

namespace Corviz\Router;

use Exception;

/**
 * @method Route get(string $pattern, callable|array $action)
 * @method Route post(string $pattern, callable|array $action)
 * @method Route patch(string $pattern, callable|array $action)
 * @method Route put(string $pattern, callable|array $action)
 * @method Route delete(string $pattern, callable|array $action)
 * @method Route options(string $pattern, callable|array $action)
 * @method Route head(string $pattern, callable|array $action)
 * @method Route prefix(string $pattern)
 * @method Route middleware(string|array $middleware)
 */
class Dispatcher
{
    private const SUPPORTED_HTTP_METHODS = ['get','post','put','patch','delete','options','head'];

    /**
     * @var Route|null
     */
    protected ?Route $found = null;

    /**
     * @var ExecutorInterface
     */
    protected ?ExecutorInterface $executor = null;

    /**
     * @var Route[]
     */
    protected array $routes = [];

    /**
     * @param string $pattern
     * @param callable|array $action
     * @return Route
     * @throws Exception
     */
    public function any(string $pattern, callable|array $action): Route
    {
        return $this->createRoute($pattern)->action($action);
    }

    /**
     * @param string|null $method
     * @param string|null $path
     *
     * @return mixed
     */
    public function dispatch(?string $method = null, ?string $path = null): mixed
    {
          //Prepare defaults

        $scriptName = $_SERVER['SCRIPT_NAME']; 
        $requestUri = $_SERVER['REQUEST_URI'];
// Script adını çıkararak base path'i bul (örn: /buyboxApi)
$basePath = dirname($scriptName);
// REQUEST_URI'den base path'i çıkarma
if (substr($requestUri, 0, strlen($basePath)) == $basePath) {
    $requestUri = substr($requestUri, strlen($basePath));
}
        
        is_null($method) && $method = $_SERVER['REQUEST_METHOD'] ?? null;
        is_null($path) && $path = $_SERVER['REQUEST_URI'] ?? null;

        //$path = parse_url($path, PHP_URL_PATH);
        $path =  $requestUri ;

        if (!is_null($path)) {
            $path = trim($path, '/');
        }

        $this->found = null;
        $params = [];

        foreach ($this->routes as $route) {
            if ($route->match($method, $path, $params)) {
                unset($params[0]);
                $params = array_values($params);
                $this->found = $route;
                break;
            }
        }

        if (is_null($this->found)) {
            return null;
        }

        if (is_null($this->executor)) {
            $this->executor = new RouteExecutor();
        }

        return $this->executor->execute($this->found, $params);
    }

    /**
     * @return Route|null
     */
    public function found(): Route|null
    {
        return $this->found;
    }

    /**
     * @param string $alias
     *
     * @return Route|null
     */
    public function getRouteByAlias(string $alias): ?Route
    {
        $found = null;
        foreach ($this->routes as $route) {
            if ($route->getAlias() === $alias) {
                $found = $route;
                break;
            }
        }

        return $found;
    }

    /**
     * @param string|null $method
     * @return Route
     * @throws Exception
     */
    protected function createRoute(string $pattern, ?string $method = null): Route
    {
        $route = Route::create()->pattern($pattern);
        $method && $route->method($method);

        $this->register($route);

        return $route;
    }

    /**
     * @param Route $route
     *
     * @return void
     */
    protected function register(Route $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * @param ExecutorInterface $executor
     * @return $this
     */
    public function setExecutor(ExecutorInterface $executor): static
    {
        $this->executor = $executor;
        return $this;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return Route
     * @throws Exception
     */
    public function __call(string $name, array $arguments)
    {
        if (in_array($name, self::SUPPORTED_HTTP_METHODS)) {
            return $this->createRoute($arguments[0], strtoupper($name))
                ->action($arguments[1]);
        }

        if ($name == 'prefix') {
            return Route::create()->pattern($arguments[0]);
        }

        if ($name == 'middleware') {
            return Route::create()->middleware($arguments[0]);
        }

        throw new Exception("Method is not supported: $name");
    }
}
