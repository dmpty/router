<?php /** @noinspection PhpUnused */

namespace Dmpty\Router;

class Route
{
    public string $name;

    public string $method;

    public string $path;

    public mixed $action;

    public array $middleware = [];

    public function __construct($method, $path, $action, $middleware)
    {
        $this->method = $method;
        $this->path = $path;
        $this->action = $action;
        $this->middleware = $middleware;
        $this->updateMap();
    }

    public function name($name): static
    {
        $this->name = $name;
        $this->updateMap($name);
        return $this;
    }

    public function middleware(string|array $middleware): static
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        }
        if (is_string($middleware)) {
            $this->middleware[] = $middleware;
        }
        $this->updateMap();
        return $this;
    }

    private function updateMap(string $name = ''): void
    {
        $route = Router::getRouteByPath($this->path);
        $route = array_merge($route, [
            $this->method => [
                'action' => $this->action,
                'middleware' => $this->middleware,
            ],
        ]);
        Router::updateMap($this->path, $route, $name);
    }
}
