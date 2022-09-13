<?php

namespace Dmpty\Router;

use Closure;
use Dmpty\Container\Container;
use Dmpty\Router\Exceptions\RouterException;

class Handler
{
    private array $middlewares = [];

    private int $index = -1;

    private mixed $action;

    private array $args;

    private Container $app;

    public function __construct()
    {
        $container = Container::getInstance();
        if (!$container->has('app')) {
            $container->singleton('app', function () {
                return Container::getInstance();
            });
        }
        $this->app = $container->make('app');
    }

    /**
     * @throws RouterException
     */
    public function push($middleware): void
    {
        if (is_string($middleware)) {
            if (!class_exists($middleware)) {
                throw new RouterException('Invalid middleware');
            }
            $middleware = $this->app->make($middleware);
        }
        if (!($middleware instanceof Middleware)) {
            throw new RouterException('Invalid middleware');
        }
        $this->middlewares[] = $middleware;
    }

    public function action($action, array $args): void
    {
        $this->action = $action;
        $this->args = $args;
    }

    /**
     * @throws RouterException
     */
    public function run()
    {
        if (!$next = $this->getNext()) {
            $action = $this->action;
            if (is_array($action) && isset($action[0]) && is_string($action[0]) && class_exists($action[0])) {
                $action[0] = $this->app->make($action[0]);
            }
            if (!is_callable($action)) {
                throw new RouterException('Invalid action');
            }
            return call_user_func($action, ...$this->args);
        }
        return $next->handle($this->closure());
    }

    private function closure(): Closure
    {
        return function () {
            return $this->run();
        };
    }

    private function getNext(): ?Middleware
    {
        $this->index++;
        return $this->middlewares[$this->index] ?? null;
    }
}
