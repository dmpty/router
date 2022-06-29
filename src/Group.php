<?php

namespace Dmpty\Router;

use Closure;

/**
 * @method Route any($path, $action)
 * @method Route get($path, $action)
 * @method Route post($path, $action)
 * @method Route head($path, $action)
 * @method Route put($path, $action)
 * @method Route patch($path, $action)
 * @method Route delete($path, $action)
 * @method Route options($path, $action)
 * @method Route connect($path, $action)
 * @method Route trace($path, $action)
 */
class Group
{
    public string $prefix = '';

    public array $middleware = [];

    public function __construct(array $options = [])
    {
        if (!empty($options['prefix']) && is_string($options['prefix'])) {
            $this->prefix = $this->pathFormat($options['prefix']);
        }
        if (!empty($options['middleware']) && is_array($options['middleware'])) {
            $this->middleware = $options['middleware'];
        }
    }

    public function __call(string $name, array $arguments)
    {
        $name = strtoupper($name);
        if (in_array($name, [
            'ANY',
            'GET',
            'POST',
            'HEAD',
            'PUT',
            'PATCH',
            'DELETE',
            'OPTIONS',
            'CONNECT',
            'TRACE',
        ])) {
            return $this->add($name, ...$arguments);
        }
        return null;
    }

    public function add($method, $path, $action): Route
    {
        $path = $this->prefix . $this->pathFormat($path);
        return new Route($method, $path, $action, $this->middleware);
    }

    public function group(array|Closure $options, Closure $callback = null)
    {
        if ($options instanceof Closure) {
            $callback = $options;
            $options = [];
        }
        if (!empty($options['prefix']) && is_string($options['prefix'])) {
            $options['prefix'] = $this->prefix . $this->pathFormat($options['prefix']);
        }
        if (!empty($options['middleware']) && is_array($options['middleware'])) {
            $options['middleware'] = array_merge($this->middleware, $options['middleware']);
        }
        $child = new static($options);
        $callback($child);
    }

    private function pathFormat($path)
    {
        if (!$path) {
            return '/';
        }
        return $this->formatPathEnd($this->formatPathFront($path));
    }

    private function formatPathFront($path)
    {
        if (!str_starts_with($path, '/')) {
            return '/' . $path;
        }
        if (substr($path, 1, 1) !== '/') {
            return $path;
        }
        return $this->formatPathFront(substr($path, 1));
    }

    private function formatPathEnd($path)
    {
        if (!str_ends_with($path, '/')) {
            return $path;
        }
        return $this->formatPathEnd(substr($path, 0, strlen($path) - 1));
    }
}
