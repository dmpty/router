<?php

namespace Dmpty\Router;

use Closure;

abstract class Middleware
{
    abstract public function handle(Closure $next);
}
