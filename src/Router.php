<?php

namespace Dmpty\Router;

use Closure;
use Dmpty\Router\Exceptions\RouterException;
use Dmpty\Router\Exceptions\RouterHttpException;
use Opis\Closure\SerializableClosure;

/**
 * @method static group(array|Closure $options, Closure $callback = null)
 * @method static Route any($path, $action)
 * @method static Route get($path, $action)
 * @method static Route post($path, $action)
 * @method static Route head($path, $action)
 * @method static Route put($path, $action)
 * @method static Route patch($path, $action)
 * @method static Route delete($path, $action)
 * @method static Route options($path, $action)
 * @method static Route connect($path, $action)
 * @method static Route trace($path, $action)
 */
class Router
{
    private static ?Router $instance = null;

    private array $pathMap = [];

    private array $nameMap = [];

    private Group $defaultGroup;

    private function __construct()
    {
        $this->defaultGroup = new Group();
    }

    private function __clone()
    {
        //
    }

    public static function __callStatic($name, $arguments)
    {
        $instance = self::getInstance();
        return $instance->defaultGroup->$name(...$arguments);
    }

    public static function getInstance(): Router
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * @throws RouterException
     */
    public static function run()
    {
        list($route, $args) = self::getRouteByUri();
        $handler = new Handler();
        foreach ($route['middleware'] as $middleware) {
            $handler->push($middleware);
        }
        $handler->action($route['action'], $args);
        return $handler->run();
    }

    public static function updateMap(string $path, array $value, string $name = ''): void
    {
        $instance = self::getInstance();
        $instance->updatePathMap($path, $value);
        if ($name) {
            $instance->updateNameMap($name, $path);
        }
    }

    private function updatePathMap(string $path, array $value): void
    {
        $this->pathMap[$path] = $value;
    }

    private function updateNameMap(string $name, string $path): void
    {
        $this->nameMap[$name] = $path;
    }

    public static function getRouteByPath($path)
    {
        $instance = self::getInstance();
        if (isset($instance->pathMap[$path])) {
            return $instance->pathMap[$path];
        }
        return [];
    }

    /**
     * @throws RouterHttpException
     */
    public static function getPathByName($name)
    {
        $instance = self::getInstance();
        if ($path = $instance->nameMap[$name] ?? null) {
            return $path;
        }
        throw new RouterHttpException("Route named $name undefined");
    }

    /**
     * @throws RouterHttpException
     */
    private static function getRouteByUri(): bool|array
    {
        $uri = $_SERVER['REQUEST_URI'];
        $instance = self::getInstance();
        $route = null;
        $args = [];
        foreach ($instance->pathMap as $path => $value) {
            $args = $instance->pathMatch($path, $uri);
            if ($args !== false) {
                $route = $value;
                break;
            }
        }
        if (!$route) {
            throw new RouterHttpException('404 Not Found', 404);
        }
        if (isset($route['ANY'])) {
            return [$route['ANY'], $args];
        }
        $method = $_SERVER['REQUEST_METHOD'];
        if (isset($route[$method])) {
            return [$route[$method], $args];
        }
        throw new RouterHttpException('405 Method Not Allowed', 405);
    }

    private function pathMatch($path1, $path2): bool|array
    {
        if ($path1 === $path2) {
            return [];
        }
        $count = preg_match_all("/{[^{}]*}/", $path1, $params);
        if ($count) {
            return $this->parseParams($path1, $path2, $params[0]);
        }
        return false;
    }

    private function parseParams($path1, $path2, $params): bool|array
    {
        $args = [];
        foreach ($params as $param) {
            $prePos = strpos($path1, $param);
            $pre1 = substr($path1, 0, $prePos - 1);
            $pre2 = substr($path2, 0, $prePos - 1);
            if ($pre1 === $pre2) {
                $path1 = substr($path1, $prePos + strlen($param));
                $path2 = substr($path2, $prePos);
                if ($path2 === '') {
                    return false;
                }
                $slashPos = strpos($path2, '/');
                if ($slashPos === false) {
                    $args[] = $path2;
                    $path2 = '';
                } else {
                    $args[] = substr($path2, 0, $slashPos);
                    $path2 = substr($path2, $slashPos);
                }
            } else {
                return false;
            }
        }
        return $path1 === $path2 ? $args : false;
    }

    public static function cache($path, $fileName = 'route.php'): void
    {
        $instance = self::getInstance();
        $pathMap = $instance->pathMap;
        foreach ($pathMap as &$pathRoute) {
            foreach ($pathRoute as &$methodRoute) {
                if ($methodRoute['action'] instanceof Closure) {
                    $methodRoute['action'] = new SerializableClosure($methodRoute['action']);
                }
            }
        }
        $route = [
            'pathMap' => $pathMap,
            'nameMap' => $instance->nameMap,
        ];
        if (!file_exists($path)) {
            mkdir($path);
        }
        $file = $path . '/' . $fileName;
        file_put_contents($file, serialize($route));
    }

    public static function loadCache($file): void
    {
        $route = unserialize(file_get_contents($file));
        $instance = self::getInstance();
        $instance->pathMap = $route['pathMap'];
        $instance->nameMap = $route['nameMap'];
    }
}
