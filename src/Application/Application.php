<?php

namespace Bow\Application;

use Bow\Application\Exception\ApplicationException;
use Bow\Configuration\Loader;
use Bow\Contracts\ResponseInterface;
use Bow\Http\Exception\HttpException;
use Bow\Http\Request;
use Bow\Http\Response;
use Bow\Router\Exception\RouterException;
use Bow\Router\Rest;
use Bow\Router\Route;
use Bow\Support\Capsule;

class Application
{
    /**
     * @var Capsule
     */
    private $capsule;

    /**
     * @var bool
     */
    private $booted = false;

    /**
     * @var array
     */
    private $error_code = [];

    /**
     * @var array
     */
    private $globale_middleware = [];

    /**
     * Branchement global sur un liste de route
     *
     * @var string
     */
    private $branch;

    /**
     * @var string
     */
    private $special_method;

    /**
     * Method Http courrante.
     *
     * @var array
     */
    private $current = [];

    /**
     * Patter Singleton
     *
     * @var Application
     */
    private static $instance;

    /**
     * Collecteur de route.
     *
     * @var array
     */
    private $routes = [];

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var Loader
     */
    private $config;

    /**
     * @var array
     */
    private $local = [];

    /**
     * @var bool
     */
    private $disable_x_powered_by = false;

    /**
     * Private construction
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    private function __construct(Request $request, Response $response)
    {
        $this->request = $request;

        $this->response = $response;

        $this->capsule = Capsule::getInstance();

        $this->capsule->instance('request', $request);

        $this->capsule->instance('response', $response);

        $this->capsule->instance('app', $this);
    }

    /**
     * Retourne le container
     *
     * @return Capsule
     */
    public function getContainer()
    {
        return $this->capsule;
    }

    /**
     * Association de la configuration
     *
     * @param Loader $config
     * @return void
     */
    public function bind(Loader $config)
    {
        $this->config = $config;

        $this->capsule->instance('config', $config);

        $this->boot();
    }

    /**
     * Démarrage de l'application
     *
     * @return void
     */
    private function boot()
    {
        if ($this->booted) {
            return;
        }

        $this->config->boot();

        $this->booted = true;
    }

    /**
     * Construction de l'application
     *
     * @param Request $request
     * @param Response $response
     * @return Application
     */
    public static function make(Request $request, Response $response)
    {
        if (is_null(static::$instance)) {
            static::$instance = new static($request, $response);
        }

        return static::$instance;
    }

    /**
     * Ajout un préfixe sur les routes
     *
     * @param string $branch
     * @param callable|string|array $cb
     * @return Application
     * @throws
     */
    public function prefix($branch, callable $cb)
    {
        $branch = rtrim($branch, '/');

        if (!preg_match('@^/@', $branch)) {
            $branch = '/' . $branch;
        }

        if ($this->branch !== null) {
            $this->branch .= $branch;
        } else {
            $this->branch = $branch;
        }

        call_user_func_array($cb, [$this]);

        $this->branch = '';

        return $this;
    }

    /**
     * Permet d'associer un middleware global sur une url
     *
     * @param array $middlewares
     * @return Application
     */
    public function middleware($middlewares)
    {
        $middlewares = (array) $middlewares;

        $this->globale_middleware = [];

        foreach ($middlewares as $middleware) {
            if (is_callable($middleware)) {
                $this->globale_middleware[] = $middleware;
            } elseif (class_exists($middleware, true)) {
                $this->globale_middleware[] = [new $middleware, 'process'];
            } else {
                $this->globale_middleware[] = $middleware;
            }
        }

        return $this;
    }

    /**
     * Route mapper
     *
     * @param array $definition
     * @throws RouterException
     */
    public function route(array $definition)
    {
        if (!isset($definition['path'])) {
            throw new RouterException('Le chemin non definie');
        }

        if (!isset($definition['method'])) {
            throw new RouterException('Méthode non definie');
        }

        if (!isset($definition['handler'])) {
            throw new RouterException('Controlleur non definie');
        }

        $method = $definition['method'];

        $path = $definition['path'];

        $where = $definition['where'] ?? [];

        $cb = (array) $definition['handler'];

        if (isset($cb['middleware'])) {
            unset($cb['middleware']);
        }

        if (isset($cb['controller'])) {
            unset($cb['controller']);
        }

        $route = $this->pushHttpVerbe($method, $path, $cb);

        if (isset($definition['middleware'])) {
            $route->middleware($definition['middleware']);
        }

        $route->where($where);
    }

    /**
     * Ajout une route de type GET
     *
     * @param string $path
     * @param callable|string|array $cb
     * @return Route
     */
    public function get($path, $cb)
    {
        return $this->routeLoader('GET', $path, $cb);
    }

    /**
     * Ajout une route de type POST
     *
     * @param string $path
     * @param callable|string|array $cb
     * @return Route
     */
    public function post($path, $cb)
    {
        $input = $this->request;

        if (!$input->has('_method')) {
            return $this->routeLoader('POST', $path, $cb);
        }

        $method = strtoupper($input->get('_method'));

        if (in_array($method, ['DELETE', 'PUT'])) {
            $this->special_method = $method;
        }

        return $this->pushHttpVerbe($method, $path, $cb);
    }

    /**
     * Ajout une route de tout type
     *
     * GET, POST, DELETE, PUT, OPTIONS, PATCH
     *
     * @param string $path
     * @param callable|string|array $cb
     * @return Application
     * @throws
     */
    public function any($path, $cb)
    {
        foreach (['options', 'patch', 'post', 'delete', 'put', 'get'] as $method) {
            $this->$method($path, $cb);
        }

        return $this;
    }

    /**
     * Ajout une route de type DELETE
     *
     * @param string $path
     * @param callable|string|array $cb
     * @return Route
     */
    public function delete($path, $cb)
    {
        return $this->pushHttpVerbe('DELETE', $path, $cb);
    }

    /**
     * Ajout une route de type PUT
     *
     * @param string $path
     * @param callable|string|array $cb
     * @return Route
     */
    public function put($path, $cb)
    {
        return $this->pushHttpVerbe('PUT', $path, $cb);
    }

    /**
     * Ajout une route de type PATCH
     *
     * @param string $path
     * @param callable|string|array $cb
     * @return Route
     */
    public function patch($path, $cb)
    {
        return $this->pushHttpVerbe('PATCH', $path, $cb);
    }

    /**
     * Ajout une route de type PATCH
     *
     * @param string $path
     * @param callable $cb
     * @return Route
     */
    public function options($path, callable $cb)
    {
        return $this->pushHttpVerbe('OPTIONS', $path, $cb);
    }

    /**
     * Lance une fonction de rappel pour chaque code d'erreur HTTP
     *
     * @param int $code
     * @param callable $cb
     * @return Application
     */
    public function code($code, callable $cb)
    {
        $this->error_code[$code] = $cb;

        return $this;
    }

    /**
     * Match route de tout type de method
     *
     * @param array $methods
     * @param string $path
     * @param callable|string|array $cb
     * @return Application
     */
    public function match(array $methods, $path, $cb)
    {
        foreach ($methods as $method) {
            if ($this->request->method() === strtoupper($method)) {
                $this->pushHttpVerbe(strtoupper($method), $path, $cb);
            }
        }

        return $this;
    }

    /**
     * Permet d'ajouter les autres verbes http [PUT, DELETE, UPDATE, HEAD, PATCH]
     *
     * @param string $method
     * @param string $path
     * @param callable|array|string $cb
     * @return Route
     */
    private function pushHttpVerbe($method, $path, $cb)
    {
        $input = $this->request;

        if ($input->has('_method')) {
            if ($input->get('_method') === $method) {
                $method = $input->get('_method');
            }
        }

        return $this->routeLoader($method, $path, $cb);
    }

    /**
     * Lance le chargement d'une route.
     *
     * @param string $method
     * @param string $path
     * @param Callable|string|array $cb
     * @return Route
     */
    private function routeLoader($method, $path, $cb)
    {
        // construction du path original en fonction de la Loader de l'application
        $path = $this->config['app.root'].$this->branch.$path;

        // route courante
        // methode courante
        $this->current = ['path' => $path, 'method' => $method];

        // Ajout de la nouvelle route
        $route = new Route($path, $cb);

        $route->middleware($this->globale_middleware);

        $this->routes[$method][] = $route;

        $route->middleware('trim');

        if (in_array($method, ['POST', 'DELETE', 'PUT'])) {
            $route->middleware('csrf');
        }

        return $route;
    }

    /**
     * Lanceur de l'application
     *
     * @return mixed
     * @throws RouterException
     */
    public function send()
    {
        if (php_sapi_name() == 'cli') {
            return true;
        }

        if (env('MODE') == 'down') {
            abort(503);

            return true;
        }

        // Ajout de l'entête X-Powered-By
        if (!$this->disable_x_powered_by) {
            $this->response->addHeader('X-Powered-By', 'Bow Framework');
        }

        $this->branch = '';

        $method = $this->request->method();

        // vérification de l'existance d'une methode spécial
        // de type DELETE, PUT
        if ($method == 'POST') {
            if ($this->special_method !== null) {
                $method = $this->special_method;
            }
        }

        // Vérification de l'existance de methode de la requete dans
        // la collection de route
        if (!isset($this->routes[$method])) {
            // Vérification et appel de la fonction du branchement 404
            $this->response->status(404);

            if (empty($this->error_code)) {
                $this->response->send(
                    sprintf('Cannot %s %s 404', $method, $this->request->path())
                );
            }

            return false;
        }

        $response = null;

        $error = true;

        foreach ($this->routes[$method] as $key => $route) {
            // route doit être une instance de Route
            if (!($route instanceof Route)) {
                continue;
            }

            // Lancement de la recherche de la methode qui arrivée dans la requête
            // ensuite lancement de la vérification de l'url de la requête
            if (!$route->match($this->request->path())) {
                continue;
            }

            $this->current['path'] = $route->getPath();

            // Appel de l'action associer à la route
            $response = $route->call();
            $error = false;

            break;
        }

        // Gestion de erreur
        if (!$error) {
            return $this->sendResponse($response);
        }

        // Application du code d'erreur 404
        $this->response->status(404);

        if (is_string($this->config['view.404'])) {
            $response = $this->response->render($this->config['view.404']);

            return $this->sendResponse($response);
        }

        throw new RouterException(
            sprintf('La route "%s" n\'existe pas', $this->request->path()),
            E_ERROR
        );
    }

    /**
     * Envoi la reponse au client
     *
     * @param mixed $response
     * @return null
     */
    private function sendResponse($response)
    {
        if ($response instanceof ResponseInterface) {
            $response->sendContent();
        } else {
            echo $this->response->send($response);
        }
    }

    /**
     * Permet d'active l'écriture le l'entête X-Powered-By
     * dans la réponse de la réquête.
     *
     * @return void
     */
    public function disableXpoweredBy()
    {
        $this->disable_x_powered_by = true;
    }

    /**
     * REST API Maker.
     *
     * @param string $url
     * @param string|array $controller_name
     * @param array $where
     * @return Application
     *
     * @throws ApplicationException
     */
    public function rest($url, $controller_name, array $where = [])
    {
        if (!is_string($controller_name) && !is_array($controller_name)) {
            throw new ApplicationException(
                'Le premier paramètre doit être un array ou une chaine de caractère',
                E_ERROR
            );
        }

        $ignore_method = [];

        $controller = $controller_name;

        if (is_array($controller_name)) {
            // Get controller
            if (isset($controller_name['controller'])) {
                $controller = $controller_name['controller'];

                unset($controller_name['controller']);
            }

            // Get all ignores methods
            if (isset($controller_name['ignores'])) {
                $ignore_method = $controller_name['ignores'];

                unset($controller_name['ignores']);
            }
        }

        if (is_null($controller) || !is_string($controller)) {
            throw new ApplicationException("[REST] Aucun controlleur défini !", E_ERROR);
        }

        // normalize url
        $url = preg_replace('/\/+$/', '', $url);

        Rest::make($url, $controller, $where, $ignore_method);

        return $this;
    }

    /**
     * __call fonction magic php
     *
     * @param string $method
     * @param array  $param
     * @return mixed
     *
     * @throws ApplicationException
     */
    public function __call($method, array $param)
    {
        if (method_exists($this->config, $method)) {
            return call_user_func_array([$this->config, $method], $param);
        }

        if (in_array($method, $this->local)) {
            return call_user_func_array($this->local[$method], $param);
        }

        throw new ApplicationException('La methode ' . $method . ' n\'exist pas.', E_ERROR);
    }

    /**
     * Abort application
     *
     * @param $code
     * @param $message
     * @param array $headers
     * @return void
     *
     * @throws HttpException
     */
    public function abort($code = 500, $message = '', array $headers = [])
    {
        $this->response->status($code);

        foreach ($headers as $key => $value) {
            $this->response->addHeader($key, $value);
        }

        if ($message == null) {
            $message = 'Le procéssus a été suspendu.';
        }

        throw new HttpException($message);
    }

    /**
     * Build dependance
     *
     * @param null $name
     * @param callable|null $callable
     * @return Capsule|mixed
     * @throws ApplicationException
     */
    public function container($name = null, callable $callable = null)
    {
        if (is_null($name)) {
            return $this->capsule;
        }

        if (is_null($callable)) {
            return $this->capsule->make($name);
        }

        if (!is_callable($callable)) {
            throw new ApplicationException('le deuxième paramètre doit être un callable.');
        }

        return $this->capsule->bind($name, $callable);
    }

    /**
     * __invoke
     *
     * Cette methode point sur le système container
     *
     * @param array ...$params
     * @return Capsule
     * @throws ApplicationException
     */
    public function __invoke(...$params)
    {
        if (count($params)) {
            return $this->capsule;
        }

        if (count($params) > 2) {
            throw new ApplicationException('Deuxième paramètre doit être passer.');
        }

        if (count($params) == 1) {
            return $this->capsule->make($params[0]);
        }

        return $this->capsule->bind($params[0], $params[1]);
    }
}
