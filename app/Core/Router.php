<?php

namespace App\Core;

/**
 * Router: literal paths + {param} placeholders, middleware pipeline.
 *
 *   Router::get('/api/students', [StudentController::class, 'index'], 'auth|permission:student-list');
 *   Router::get('/student/{id}', [StudentController::class, 'show']);
 *
 * Middleware tokens:
 *   auth            must be logged in (401 JSON / redirect to /login)
 *   guest           must NOT be logged in
 *   permission:x|y  must hold at least one of the permissions
 *   no-csrf         skip CSRF verification (rare — none by default)
 *
 * CSRF is enforced for POST/PUT/PATCH/DELETE automatically.
 */
final class Router
{
    /** @var array<int,array{method:string,path:string,regex:string,params:string[],handler:callable|array,middleware:string[]}> */
    private array $routes = [];
    /** @var array<int,string> paths seen (for 405 detection) */
    private array $paths = [];

    public function add(string $method, string $path, callable|array $handler, string|array $middleware = []): void
    {
        $middleware = is_string($middleware)
            ? array_filter(explode('|', $middleware))
            : $middleware;

        $params = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $path);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => '#^' . $regex . '$#',
            'params' => $params,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
        $this->paths[$path] = true;
    }

    public static function get(string $path, callable|array $handler, string|array $m = []): void
    {
        self::router()->add('GET', $path, $handler, $m);
    }

    public static function post(string $path, callable|array $handler, string|array $m = []): void
    {
        self::router()->add('POST', $path, $handler, $m);
    }

    public static function put(string $path, callable|array $handler, string|array $m = []): void
    {
        self::router()->add('PUT', $path, $handler, $m);
    }

    public static function delete(string $path, callable|array $handler, string|array $m = []): void
    {
        self::router()->add('DELETE', $path, $handler, $m);
    }

    private static ?Router $router = null;

    public static function router(): Router
    {
        return self::$router ??= new self();
    }

    /** Current app instance for closure registration (routes.php). */
    public static function reset(): void
    {
        self::$router = null;
    }

    /**
     * Match + run middleware + invoke handler.
     * Handlers may return array (JSON 200), string (HTML 200), null
     * (controller already emitted the response), or throw HttpException.
     */
    public function dispatch(Request $request): void
    {
        $pathMatches = [];
        $methodMatch = null;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path(), $m)) {
                continue;
            }
            $pathMatches[] = $route;
            if ($route['method'] === $request->method()) {
                $methodMatch = $route;
                $matched = $m;
                break;
            }
        }

        if ($methodMatch === null) {
            if ($pathMatches !== []) {
                $this->authorize($pathMatches[0], $request); // may throw 401 before 405 (Laravel-like)
                throw new HttpException(405, 'Method not allowed');
            }
            throw new HttpException(404, 'Not found');
        }

        // Bind {param} values.
        $request->params = [];
        foreach ($methodMatch['params'] as $i => $name) {
            $request->params[$name] = rawurldecode($matched[$i + 1]);
        }

        $this->authorize($methodMatch, $request);

        // CSRF for state-changing methods.
        $safeMethod = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
        if (!$safeMethod && !in_array('no-csrf', $methodMatch['middleware'], true)) {
            $token = $request->header('X-CSRF-TOKEN') ?? $request->input('_token');
            if (!Session::checkToken($token)) {
                throw new HttpException(419, 'CSRF token mismatch');
            }
        }

        $handler = $methodMatch['handler'];
        $result = is_array($handler)
            ? (new $handler[0]())->{$handler[1]}($request)
            : $handler($request);

        if (is_array($result)) {
            Response::json($result);
        }
        if (is_string($result)) {
            Response::html($result);
        }
        // null => handler already sent the response (PDF/Excel/download/redirect).
    }

    /** Run auth + permission middleware for a route (throws on failure). */
    private function authorize(array $route, Request $request): void
    {
        foreach ($route['middleware'] as $m) {
            $arg = null;
            if (str_contains($m, ':')) {
                [$m, $arg] = explode(':', $m, 2);
            }
            switch ($m) {
                case 'auth':
                    if (!Auth::check()) {
                        throw new HttpException(401, 'Unauthenticated.');
                    }
                    break;
                case 'guest':
                    if (Auth::check()) {
                        Response::redirect('/');
                    }
                    break;
                case 'permission':
                    if (!Auth::check()) {
                        throw new HttpException(401, 'Unauthenticated.');
                    }
                    if ($arg !== null && !Auth::canAny($arg)) {
                        throw new HttpException(403, 'This action is unauthorized.');
                    }
                    break;
            }
        }
    }
}
