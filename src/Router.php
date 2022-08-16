<?php
require_once 'Parameter.php';

/**
 * Checks if given path is an absolute path
 * I took this function from <a href="https://stackoverflow.com/questions/23570262/how-to-determine-if-a-file-path-is-absolute">here</a>
 * @param $path string File or directory path you want to check if it's an absolute path
 * @return bool True if path is an absolute path, false if not
 */
function isAbsolutePath(string $path): bool
{
    if (!ctype_print($path)) {
        $mess = 'Path can NOT have non-printable characters or be empty';
        throw new \DomainException($mess);
    }
    // Optional wrapper(s).
    $regExp = '%^(?<wrappers>(?:[[:print:]]{2,}://)*)';
    // Optional root prefix.
    $regExp .= '(?<root>(?:[[:alpha:]]:/|/)?)';
    // Actual path.
    $regExp .= '(?<path>(?:[[:print:]]*))$%';
    $parts = [];
    if (!preg_match($regExp, $path, $parts)) {
        $mess = sprintf('Path is NOT valid, was given %s', $path);
        throw new \DomainException($mess);
    }
    if ('' !== $parts['root']) {
        return true;
    }
    return false;
}

/**
 * Basic router that support dynamic routing and required parameter matching
 */
class Router
{
    private static string $views_folder = 'views';
    private string $prefix = '/';
    private array $routes = array();

    /**
     * @var Closure Callback that will be called when 4xx errors occur
     */
    public ?Closure $onError = null;

    /**
     * @param $file_or_path string View path to include. Can be absolute or relative path.
     * @param $_v array Data you want to use inside the view
     */
    public static function view(string $file_or_path, array $_v = []) {
        $file_or_path = str_replace('\\', '/', $file_or_path);

        $isAbsolute = isAbsolutePath($file_or_path);
        $hasViewFolder = strpos($file_or_path, self::$views_folder) === 0;
        $extension = strpos($file_or_path, '.php');

        if(!$hasViewFolder)
            $file_or_path = self::$views_folder . DIRECTORY_SEPARATOR . $file_or_path;

        if(!$isAbsolute)
            $file_or_path = __DIR__ . DIRECTORY_SEPARATOR . $file_or_path;

        if(!$extension)
            $file_or_path .= '.php';

        if(file_exists($file_or_path)) {
            include $file_or_path;
        }
    }

    /**
     * @throws Exception if provided $views_folder cannot be found.
     */
    public function __construct($views_folder = null) {
        $filepath = dirname(__FILE__);
        $this->prefix .= pathinfo($filepath)['basename'];
        if($views_folder) {
            if(!file_exists($views_folder) && !file_exists($_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.$views_folder)) {
                throw new Exception("Provided views folder cannot be found ($views_folder)");
            }
            self::$views_folder = $views_folder;
        }
    }

    /**
     * Starts the router (simple, right?)
     * @return void
     */
    public function run() {
        // You didn't add a single route? Shame on you!
        if(empty($this->routes)) return;

        $matchRoute = null;
        $route = str_replace($this->prefix, '', strtok($_SERVER["REQUEST_URI"], '?'));
        $roadmap = preg_split('/\//', $route,-1, PREG_SPLIT_NO_EMPTY);
        $routeParameters = [];

        // Add root node as the first key
        array_unshift($roadmap, '\\\\root');

        // Oh, god please no
        if(isset($this->routes[$roadmap[0]])) {
            $lastItem = $this->routes[array_key_first($this->routes)];

            foreach ($roadmap as $i => $item) {
                if(array_key_last($roadmap) === $i) {
                    if($i === 0) {
                        if(isset($this->routes[$roadmap[0]][$_SERVER['REQUEST_METHOD']])) {
                            $matchRoute = $this->routes[$roadmap[0]][$_SERVER['REQUEST_METHOD']];
                            break;
                        }

                        $isRouteParam = self::CheckRouteParam($item, $param);
                        if($isRouteParam) {
                            $routeParameters[$param] = $item;
                            $matchRoute = $this->routes[$roadmap[0]][$_SERVER['REQUEST_METHOD']];
                            break;
                        }

                        break;
                    }

                    if(isset($lastItem[$item][$_SERVER['REQUEST_METHOD']])) {
                        $matchRoute = $lastItem[$item][$_SERVER['REQUEST_METHOD']];
                    }
                    else {
                        foreach ($lastItem as $key => $tempMap) {
                            $isRouteParam = self::CheckRouteParam($key, $param);
                            if($isRouteParam) {
                                $routeParameters[$param] = $item;
                                $matchRoute = $tempMap[$_SERVER['REQUEST_METHOD']];
                                break;
                            }
                        }
                    }
                    break;
                }

                if($i === 0) {
                    $isRouteParam = self::CheckRouteParam($roadmap[0], $param);
                    if($isRouteParam) {
                        $lastItem = &$this->routes['[' . $param . ']'];
                        $routeParameters[$param] = $roadmap[0];
                    }
                    else $lastItem = &$this->routes[$roadmap[0]];
                    continue;
                }

                if(isset($lastItem[$item])) {
                    $lastItem = $lastItem[$item];
                }
                else {
                    foreach ($lastItem as $key => $tempMap) {
                        $isRouteParam = self::CheckRouteParam($key, $param);
                        if($isRouteParam) {
                            $routeParameters[$param] = $item;
                            $lastItem = &$tempMap;
                            break;
                        }
                    }
                }
            }
        }

        if($matchRoute === null) {
            http_response_code(404);

            // If user defined an error callback
            if($this->onError != null && is_callable($this->onError))
                call_user_func($this->onError, 404);

            return;
        }

        $getParams = [];
        $postParams = [];

        // Fill non-empty GET parameters
        foreach ($_GET as $n => $g)
            if(!empty($g)) $getParams[] = $n;
        // Fill non-empty POST parameters
        foreach ($_POST as $n => $p)
            if(!empty($p)) $postParams[] = $n;

        $exists = true;
        $callback = null;

        // Check for required parameters
        foreach ($matchRoute as $c) {
            $callback = $c;
            foreach ($c['params'] as $p) {
                if($p['type'] === 'GetParam') {
                    if(!in_array($p['name'], $getParams)) {
                        $exists = false;
                        break;
                    }
                }
                else if($p['type'] === 'PostParam') {
                    if(!in_array($p['name'], $postParams)) {
                        $exists = false;
                        break;
                    }
                }
            }
        }

        if(!$exists || !$callback) {
            http_response_code(400);

            // If user defined an error callback
            if($this->onError != null && is_callable($this->onError))
                call_user_func($this->onError, 400);

            return;
        }

        // Build function arguments from callback
        $args = array();
        foreach ($callback['params'] as $param) {
            switch ($param['type']) {
                case 'GetParam':
                    $args[] = new GetParam($param['name'], $_GET[$param['name']]);
                    break;
                case 'PostParam':
                    $args[] = new PostParam($param['name'], $_POST[$param['name']]);
                    break;
                case 'RouteParam':
                    $args[] = new RouteParam($param['name'], $routeParameters[$param['name']]);
                    break;
                default:
                    break;
            }
        }

        // Uh, finally. Call the callback with recently built arguments
        $callback['handle'](...$args);
    }

    /**
     * Adds a route with request method `GET`. Examples:
     * - /
     * - /admin
     * - /user/[id]
     * - /user/[id]/summary
     * @param $route string Route path (ex: /users/[id]/summary
     * @param $callback Closure The function that will run when requested route matches with this route
     * Add function parameters that you strictly require ( ex: function(GetParam $id, PostParam $content) )
     * @throws ReflectionException if callback is not a function
     */
    public function get(string $route, Closure $callback) {
        $this->add('GET', $route, $callback);
    }

    /**
     * Adds a route with request method `POST`. Examples:
     * - /
     * - /admin
     * - /user/[id]
     * - /user/[id]/summary
     * @param $route string Route path (ex: /users/[id]/summary
     * @param $callback Closure The function that will run when requested route matches with this route
     * Add function parameters that you strictly require ( ex: function(GetParam $id, PostParam $content) )
     * @throws ReflectionException if callback is not a function
     */
    public function post(string $route, Closure $callback) {
        $this->add('POST', $route, $callback);
    }

    /**
     * Adds a route with request method `DELETE`. Examples:
     * - /
     * - /admin
     * - /user/[id]
     * - /user/[id]/summary
     * @param $route string Route path (ex: /users/[id]/summary
     * @param $callback Closure The function that will run when requested route matches with this route
     * Add function parameters that you strictly require ( ex: function(GetParam $id, PostParam $content) )
     * @throws ReflectionException if callback is not a function
     */
    public function delete(string $route, Closure $callback) {
        $this->add('DELETE', $route, $callback);
    }

    /**
     * Adds a route with request method `PATCH`. Examples:
     * - /
     * - /admin
     * - /user/[id]
     * - /user/[id]/summary
     * @param $route string Route path (ex: /users/[id]/summary
     * @param $callback Closure The function that will run when requested route matches with this route
     * Add function parameters that you strictly require ( ex: function(GetParam $id, PostParam $content) )
     * @throws ReflectionException if callback is not a function
     */
    public function patch(string $route, Closure $callback) {
        $this->add('PATCH', $route, $callback);
    }

    /**
     * Adds a route with request method `OPTIONS`. Examples:
     * - /
     * - /admin
     * - /user/[id]
     * - /user/[id]/summary
     * @param $route string Route path (ex: /users/[id]/summary
     * @param $callback Closure The function that will run when requested route matches with this route.
     * Add function parameters that you strictly require ( ex: function(GetParam $id, PostParam $content) )
     * @throws ReflectionException if callback is not a function
     */
    public function options(string $route, Closure $callback) {
        $this->add('OPTIONS', $route, $callback);
    }

    /**
     * Adds an endpoint to this router. Examples:
     * - /
     * - /admin
     * - /user/[id]
     * - /user/[id]/summary
     * @param $method string Must be one of GET, POST, DELETE, PATCH or OPTIONS
     * @param $route string Route path (ex: /users/[id]/summary
     * @param $callback Closure The function that will run when requested route matches with this route.
     * Add function parameters that you strictly require ( ex: function(GetParam $id, PostParam $content) )
     * @throws ReflectionException if callback is not a function
     */
    private function add(string $method, string $route, Closure $callback) {
        // Remove duplicated slashes
        $route = preg_replace("/(\/)+/", "$1", strtolower($route));
        // Check route string if matches with the rule (ex: /, /admin, /test/route, /test/[param] etc.)
        if(!preg_match("/^\/[\w\/.\[\]]*$/", $route)) return;

        // This will hold all parameters of the route
        $params = array();

        // Callback's route params
        $f_routeParams = [];
        // Route's route params
        $routeParameters = [];

        // Add callback's required parameters
        $f = new ReflectionFunction($callback);
        foreach ($f->getParameters() as $parameter) {
            $param = array(
                'name' => $parameter->getName(),
                'type' => $parameter->getType() ? $parameter->getType()->getName() : 'GetParam'
            );

            // Find callback's RouteParams
            if($param['type'] === 'RouteParam') $f_routeParams[] = $param;

            // Now add this to params
            $params[] = $param;
        }

        // Find route parameters (ex: /user/[id]/jobs/[category] => name and category)
        $has_route_params = preg_match_all("/\[(\w+)]/", $route, $_rp);

        // If route parameters exists, collect group matches
        if($has_route_params)
            $routeParameters = $_rp[1];

        // If callback's RouteParam count doesn't match with route's RouteParam count, don't add this route
        if(count($routeParameters) != count($f_routeParams))
            return;

        // Callback's route params and route's route params must be exactly the same
        for ($i = 1; $i < count($routeParameters); $i++) {
            $param = array(
                'name' => $routeParameters[$i],
                'type' => 'RouteParam'
            );

            // Order must be the same too
            if($param != $f_routeParams[$i])
                return;
        }

        // Begin building roadmap
        $roadmap = preg_split('/\//', $route,-1, PREG_SPLIT_NO_EMPTY);
        // Let's add the root key too
        array_unshift($roadmap, '\\\\root');

        // If root key doesn't exists, create it
        if(!isset($this->routes[$roadmap[0]]))
            $this->routes[$roadmap[0]] = array();

        // Now the fun part begins, we will create a variable that holds the root key's pointer
        $lastAdded = &$this->routes[$roadmap[0]];

        foreach ($roadmap as $i => $item) {
            // If this is the last endpoint on roadmap
            if($i === array_key_last($roadmap))
            {
                // But also, if this is the first endpoint
                if($i === array_key_first($roadmap)) {
                    if(!isset($lastAdded[$method]))
                        $lastAdded[$method] = array();

                    // Simply add the method because this is the root
                    $lastAdded[$method][] = array('handle' => &$callback, 'params' => $params);
                }
                // If this is really the last but not first endpoint...
                else {
                    // Create if not exists
                    if(!isset($lastAdded[$item][$method]))
                        $lastAdded[$item][$method] = array();
                    // Then register the callback
                    $lastAdded[$item][$method][] = array('handle' => &$callback, 'params' => $params);
                }
            }
            // If not first and last, but somewhere between
            else if ($i !== 0) {
                // Create if not exists
                if(!isset($lastAdded[$item]))
                    $lastAdded[$item] = array();
                // Then point to this new item
                $lastAdded = &$lastAdded[$item];
            }
            // If this is the first endpoint on roadmap
            else {
                // $item will be \\root, so we are creating the root endpoint
                if(!isset($this->routes[$item]))
                    $this->routes[$item] = array();
                // Now point to this first item (\\root)
                $lastAdded = &$this->routes[array_key_last($this->routes)];
            }
        }
    }

    /**
     * Checks if given string contains a route parameter (ex: /user/[id] contains but /admin/summary doesn't)
     * @param $endpoint string Route path that could contain [param] strings
     * @param $routeParameter string|null First match of parameter name or null
     * @return bool True if matched a parameter name, false if not
     */
    private static function CheckRouteParam(string $endpoint, ?string &$routeParameter): bool
    {
        $found = $routeParameter = null;
        $result = preg_match_all("/\[(\w+)]/", $endpoint, $found);

        if(!$result) return false;

        $routeParameter = $found[1][0];
        return true;
    }
}