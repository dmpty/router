<?php

namespace Dmpty\Router;

use Closure;
use Dmpty\Router\Exceptions\RouterException;

class Handler
{
    private array $middlewares = [];

    private int $index = -1;

    private mixed $action;

    private array $args;

    /**
     * @throws RouterException
     */
    public function push($middleware)
    {
        if (is_string($middleware)) {
            if (!class_exists($middleware)) {
                throw new RouterException('Invalid middleware');
            }
            $middleware = new $middleware();
        }
        if (!($middleware instanceof Middleware)) {
            throw new RouterException('Invalid middleware');
        }
        $this->middlewares[] = $middleware;
    }

    /**
     * @throws RouterException
     */
    public function action($action, array $args)
    {
        if (is_array($action) && isset($action[0]) && is_string($action[0]) && class_exists($action[0])) {
            $action[0] = new $action[0];
        }
        if (!is_callable($action)) {
            throw new RouterException('Invalid action');
        }
        $this->action = $action;
        $this->args = $args;
    }

    public function run()
    {
        if (!$next = $this->getNext()) {
            return call_user_func($this->action, ...$this->args);
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
