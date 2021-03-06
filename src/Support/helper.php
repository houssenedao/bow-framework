<?php

use Bow\Auth\Auth;
use Bow\Database\Database as DB;
use Bow\Event\Event;
use Bow\Mail\Mail;
use Bow\Security\Hash;
use Bow\Security\Tokenize;
use Bow\Session\Cookie;
use Bow\Session\Session;
use Bow\Storage\Storage;
use Bow\Support\Capsule;
use Bow\Support\Collection;
use Bow\Support\Env;
use Bow\Support\Faker;
use Bow\Support\Util;
use Bow\Translate\Translator;

if (!function_exists('app')) {
    /**
     * Application container
     *
     * @param  null  $key
     * @param  array $setting
     * @return \Bow\Support\Capsule|mixed
     */
    function app($key = null, array $setting = [])
    {
        $capsule = Capsule::getInstance();

        if ($key == null && $setting == null) {
            return $capsule;
        }

        if (count($setting) == 0) {
            return $capsule->make($key);
        }

        return $capsule->makeWith($key, $setting);
    }
}

if (!function_exists('config')) {
    /**
     * Application configuration
     *
     * @param  string|array $key
     * @param  mixed        $setting
     * @return \Bow\Configuration\Loader|mixed
     * @throws
     */
    function config($key = null, $setting = null)
    {
        $config = \Bow\Configuration\Loader::getInstance();

        if (is_null($key)) {
            return $config;
        }

        if (is_null($setting)) {
            return $config[$key];
        }

        return $config[$key] = $setting;
    }
}

if (!function_exists('response')) {
    /**
     * response, manipule une instance de Response::class
     *
     * @param  string $content, le message a envoyer
     * @param  int    $code,     le code d'erreur
     * @return \Bow\Http\Response
     */
    function response($content = '', $code = 200)
    {
        $response = app('response');

        $response->status($code);

        if (is_null($content)) {
            return $response;
        }

        $response->setContent($content);

        return $response;
    }
}

if (!function_exists('request')) {
    /**
     * répresente le classe Request
     *
     * @return \Bow\Http\Request
     */
    function request()
    {
        return app('request');
    }
}

if (!function_exists('db')) {
    /**
     * permet de se connecter sur une autre base de donnée
     * et retourne l'instance de la DB
     *
     * @param string   $name le nom de la configuration de la db
     * @param callable $cb   la fonction de rappel
     *
     * @return DB, the DB reference
     * @throws
     */
    function db($name = null, callable $cb = null)
    {
        if (func_num_args() == 0) {
            return DB::getInstance();
        }

        if (!is_string($name)) {
            throw new InvalidArgumentException('Erreur sur le parametre 1. Type string attendu.');
        }

        $last_connection = DB::getConnectionName();

        if ($last_connection !== $name) {
            DB::connection($name);
        }

        if (is_callable($cb)) {
            return $cb();
        }

        return DB::connection($last_connection);
    }
}

if (!function_exists('view')) {
    /**
     * view aliase sur Response::view
     *
     * @param string    $template
     * @param array|int $data
     * @param int       $code
     *
     * @return mixed
     */
    function view($template, $data = [], $code = 200)
    {
        if (is_int($data)) {
            $code = $data;

            $data = [];
        }

        response()->status($code);

        return Bow\View\View::parse($template, $data);
    }
}

if (!function_exists('table')) {
    /**
     * table aliase DB::table
     *
     * @param  string $name
     * @param  string $connexion
     * @return Bow\Database\QueryBuilder
     */
    function table($name, $connexion = null)
    {
        if (is_string($connexion)) {
            db($connexion);
        }

        return DB::table($name);
    }
}

if (!function_exists('last_insert_id')) {
    /**
     * Retourne le dernier ID suite a une requete INSERT sur un table dont ID est
     * auto_increment.
     *
     * @param  string $name
     * @return int
     */
    function last_insert_id($name = null)
    {
        return DB::lastInsertId($name);
    }
}

if (!function_exists('select')) {
    /**
     * statement lance des requete SQL de type SELECT
     *
     * select('SELECT * FROM users');
     *
     * @param string   $sql
     * @param array    $data
     *
     * @return int|array|StdClass
     */
    function select($sql, $data = [])
    {
        return DB::select($sql, $data);
    }
}

if (!function_exists('select_one')) {
    /**
     * statement lance des requete SQL de type SELECT
     *
     * @param string   $sql
     * @param array    $data
     *
     * @return int|array|StdClass
     */
    function select_one($sql, $data = [])
    {
        return DB::selectOne($sql, $data);
    }
}

if (!function_exists('insert')) {
    /**
     * insert lance des requete SQL de type INSERT
     *
     * @param string   $sql
     * @param array    $data
     *
     * @return int
     */
    function insert($sql, array $data = [])
    {
        return DB::insert($sql, $data);
    }
}

if (!function_exists('delete')) {
    /**
     * statement lance des requete SQL de type DELETE
     *
     * @param string   $sql
     * @param array    $data
     *
     * @return int
     */
    function delete($sql, $data = [])
    {
        return DB::delete($sql, $data);
    }
}

if (!function_exists('update')) {
    /**
     * update lance des requete SQL de type UPDATE
     *
     * @param string   $sql
     * @param array    $data
     *
     * @return int
     */
    function update($sql, array $data = [])
    {
        return DB::update($sql, $data);
    }
}

if (!function_exists('statement')) {
    /**
     * statement lance des requete SQL de type CREATE TABLE|ALTER TABLE|RENAME|DROP TABLE
     *
     * @param string $sql
     *
     * @return int
     */
    function statement($sql)
    {
        return DB::statement($sql);
    }
}

if (!function_exists('debug')) {
    /**
     * debug, fonction de debug de variable
     * elle vous permet d'avoir un coloration
     * synthaxique des types de donnée.
     */
    function debug()
    {
        array_map(function ($x) {
            call_user_func_array([Util::class, 'debug'], [$x]);
        }, secure(func_get_args()));

        die;
    }
}

if (!function_exists('create_csrf_token')) {
    /**
     * create_csrf, fonction permetant de récupérer le token généré
     *
     * @param  int $time [optional]
     * @return \StdClass
     */
    function create_csrf_token($time = null)
    {
        return Tokenize::csrf($time);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * csrf_token, fonction permetant de récupérer le token généré
     *
     * @return string
     */
    function csrf_token()
    {
        $csrf = create_csrf_token();

        return $csrf['token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * csrf_field, fonction permetant de récupérer un input généré
     *
     * @return string
     */
    function csrf_field()
    {
        $csrf = create_csrf_token();

        return $csrf['field'];
    }
}

if (!function_exists('method_field')) {
    /**
     * method_field, fonction permetant de récupérer un input généré
     *
     * @param  string $method
     * @return string
     */
    function method_field($method)
    {
        return '<input type="hidden" name="_method" value="'.$method.'">';
    }
}

if (!function_exists('generate_token_csrf')) {
    /**
     * csrf, fonction permetant de générer un token
     *
     * @return string
     */
    function gen_csrf_token()
    {
        return Tokenize::make();
    }
}

if (!function_exists('verify_csrf')) {
    /**
     * verify_token_csrf, fonction permetant de vérifier un token
     *
     * @param  string $token  l'information sur le token
     * @param  bool   $strict vérifie le token et la date de création avec à la valeur
     *                        time()
     * @return string
     */
    function verify_csrf($token, $strict = false)
    {
        return Tokenize::verify($token, $strict);
    }
}

if (!function_exists('csrf_time_is_expirate')) {
    /**
     * csrf, fonction permetant de générer un token
     *
     * @param  string $time
     * @return string
     */
    function csrf_time_is_expirate($time = null)
    {
        return Tokenize::csrfExpirated($time);
    }
}

if (!function_exists('json')) {
    /**
     * json, permet de lance des reponses server de type json
     *
     * @param  mixed $data
     * @param  int   $code
     * @param  array $headers
     * @return mixed
     */
    function json($data, $code = 200, array $headers = [])
    {
        return response()->json($data, $code, $headers);
    }
}

if (!function_exists('download')) {
    /**
     * download, permet de lancer le téléchargement d'un fichier.
     *
     * @param string      $file
     * @param null|string $filename
     * @param array       $headers
     * @param string      $disposition
     * @return string
     */
    function download($file, $filename = null, array $headers = [], $disposition = 'attachment')
    {
        return response()->download($file, $filename, $headers, $disposition);
    }
}

if (!function_exists('status_code')) {
    /**
     * statuscode, permet de changer le code de la reponse du server
     *
     * @param  int $code=200
     * @return mixed
     */
    function status_code($code)
    {
        return response()->status($code);
    }
}

if (!function_exists('sanitaze')) {
    /**
     * sanitaze, épure un variable d'information indésiration
     * eg. sanitaze('j\'ai') => j'ai
     *
     * @param  mixed $data
     * @return mixed
     */
    function sanitaze($data)
    {
        if (is_numeric($data)) {
            return $data;
        }

        return \Bow\Security\Sanitize::make($data);
    }
}

if (!function_exists('secure')) {
    /**
     * secure, échape les anti-slashes, les balises html
     * eg. secure('j'ai') => j\'ai
     *
     * @param  mixed $data
     * @return mixed
     */
    function secure($data)
    {
        if (is_numeric($data)) {
            return $data;
        }

        return \Bow\Security\Sanitize::make($data, true);
    }
}

if (!function_exists('set_header')) {
    /**
     * modifie les entêtes HTTP
     *
     * @param string $key   le nom de l'entête
     *                      http
     * @param string $value la valeur à assigner
     */
    function set_header($key, $value)
    {
        response()->addHeader($key, $value);
    }
}

if (!function_exists('get_header')) {
    /**
     * modifie les entêtes HTTP
     *
     * @param  string $key le nom de l'entête http
     * @return string|null
     */
    function get_header($key)
    {
        return request()->getHeader($key);
    }
}

if (!function_exists('redirect')) {
    /**
     * modifie les entêtes HTTP
     *
     * @param  string|array $path Le path de rédirection
     * @return \Bow\Http\Redirect
     */
    function redirect($path = null)
    {
        $redirect = \Bow\Http\Redirect::getInstance();

        if ($path !== null) {
            $redirect->to($path);
        }

        return $redirect;
    }
}

if (!function_exists('send')) {
    /**
     * alias de echo avec option auto die
     *
     * @param  string $data
     * @return mixed
     */
    function send($data)
    {
        return response()->send($data);
    }
}

if (!function_exists('curl')) {
    /**
     * curl lance un requete vers une autre source de resource
     *
     * @param  string $method
     * @param  string $url
     * @param  array  $params
     * @param  bool   $return
     * @param  string $header
     * @return array|null
     */
    function curl($method, $url, array $params = [], $return = false, & $header = null)
    {
        $ch = curl_init($url);

        $options = [
            'CURLOPT_POSTFIELDS' => http_build_query($params)
        ];

        if ($return == true) {
            if (!curl_setopt($ch, CURLOPT_RETURNTRANSFER, true)) {
                curl_close($ch);

                return null;
            }
        }

        if ($method == 'POST') {
            $options['CURLOPT_POST'] = 1;
        }

        // Set curl option
        curl_setopt_array($ch, $options);

        // Execute curl
        $data = curl_exec($ch);

        if ($header !== null) {
            $header = curl_getinfo($ch);
        }

        curl_close($ch);
        return $data;
    }
}

if (!function_exists('url')) {
    /**
     * url retourne l'url courant
     *
     * @param string|null $url
     * @param array       $parameters
     *
     * @return string
     */
    function url($url = null, array $parameters = [])
    {
        $current = trim(request()->url(), '/');

        if (is_array($url)) {
            $parameters = $url;

            $url = '';
        }

        if (is_string($url)) {
            $current .= '/'.trim($url, '/');
        }

        if (count($parameters) > 0) {
            $current .= '?' . http_build_query($parameters);
        }

        return $current;
    }
}

if (!function_exists('pdo')) {
    /**
     * pdo retourne l'instance de la connection PDO
     *
     * @return PDO
     */
    function pdo()
    {
        return DB::getPdo();
    }
}

if (!function_exists('set_pdo')) {
    /**
     * Modifie l'instance de la connection PDO
     *
     * @param  PDO $pdo
     * @return PDO
     */
    function set_pdo(PDO $pdo)
    {
        DB::setPdo($pdo);

        return pdo();
    }
}

if (!function_exists('collect')) {

    /**
     * retourne une instance de collection
     *
     * @param  array $data [optional]
     * @return \Bow\Support\Collection
     */
    function collect(array $data = [])
    {
        return new Collection($data);
    }
}

if (!function_exists('encrypt')) {
    /**
     * Permet de crypt les données passés en paramètre
     *
     * @param  string $data
     * @return string
     */
    function encrypt($data)
    {
        return \Bow\Security\Crypto::encrypt($data);
    }
}

if (!function_exists('decrypt')) {
    /**
     * permet de decrypter des données crypté par la function crypt
     *
     * @param  string $data
     * @return string
     */
    function decrypt($data)
    {
        return \Bow\Security\Crypto::decrypt($data);
    }
}

if (!function_exists('db_transaction')) {
    /**
     * Debut un transaction. Désactive l'auto commit
     *
     * @param callable $cb
     */
    function db_transaction(callable $cb = null)
    {
        DB::startTransaction($cb);
    }
}

if (!function_exists('db_transaction_started')) {
    /**
     * Vérifie l'existance d"une transaction en cours
     *
     * @return bool
     */
    function db_transaction_started()
    {
        return DB::getPdo()->inTransaction();
    }
}

if (!function_exists('db_rollback')) {
    /**
     * annuler un rollback
     */
    function db_rollback()
    {
        DB::rollback();
    }
}

if (!function_exists('db_commit')) {
    /**
     * valider une transaction
     */
    function db_commit()
    {
        DB::commit();
    }
}

if (!function_exists('add_event')) {
    /**
     * Alias de la class Event::on
     *
     * @param  string                $event
     * @param  callable|array|string $fn
     * @return Event;
     * @throws \Bow\Event\EventException
     */
    function add_event($event, $fn)
    {
        if (!is_string($event)) {
            throw new \Bow\Event\EventException('Le premier paramètre doit être une chaine de caractère.', 1);
        }

        return call_user_func_array([emitter(), 'on'], [$event, $fn]);
    }
}

if (!function_exists('add_event_once')) {
    /**
     * Alias de la class Event::once
     *
     * @param  string                $event
     * @param  callable|array|string $fn
     * @return Event;
     * @throws \Bow\Event\EventException
     */
    function add_event_once($event, $fn)
    {
        if (!is_string($event)) {
            throw new \Bow\Event\EventException('Le premier paramètre doit être une chaine de caractère.', 1);
        }

        return call_user_func_array([emitter(), 'once'], [$event, $fn]);
    }
}

if (!function_exists('add_transmisson_event')) {
    /**
     * Alias de la class Event::once
     *
     * @param  string                $event
     * @param  array|string $fn
     * @return Event
     * @throws \Bow\Event\EventException
     */
    function add_transmisson_event($event, $fn)
    {
        if (!is_string($event)) {
            throw new \Bow\Event\EventException('Le premier paramètre doit être une chaine de caractère.', 1);
        }

        return call_user_func_array([emitter(), 'onTransmission'], [$event, $fn]);
    }
}

if (!function_exists('emitter')) {
    /**
     * Alias de la class Event::on
     *
     * @return Event
     */
    function emitter()
    {
        return Event::getInstance();
    }
}

if (!function_exists('emit_event')) {
    /**
     * Alias de la class Event::emit
     *
     * @param  string $event
     * @throws \Bow\Event\EventException
     */
    function emit_event($event)
    {
        if (!is_string($event)) {
            throw new \Bow\Event\EventException('Le premier paramètre doit être une chaine de caractère.', 1);
        }

        call_user_func_array([emitter(), 'emit'], func_get_args());
    }
}

if (!function_exists('flash')) {
    /**
     * Permet ajouter un nouveau flash
     * e.g flash('error', 'An error occured');
     *
     * @param string $key     Le nom du niveau soit ('error', 'info', 'warn', 'danger','success')
     * @param string $message Le message du flash
     *
     * @return mixed
     */
    function flash($key, $message)
    {
        return Session::getInstance()->flash($key, $message);
    }
}

if (!function_exists('email')) {
    /**
     * Alias sur SimpleMail et Smtp
     *
     * @param null|string $view     la view
     * @param array       $data     la view
     * @param callable    $cb
     * @return \Bow\Mail\Driver\SimpleMail|\Bow\Mail\Driver\Smtp|bool
     * @throws
     */
    function email($view = null, $data = [], callable $cb = null)
    {
        if ($view === null) {
            return Mail::getInstance();
        }

        return Mail::send($view, $data, $cb);
    }
}

if (!function_exists('raw_email')) {
    /**
     * Alias sur SimpleMail et Smtp
     *
     * @param  string array $to
     * @param  string       $subject
     * @param  string       $message
     * @param  array        $headers
     * @return Mail|mixed
     */
    function raw_email($to, $subject, $message, array $headers = [])
    {
        return Mail::raw($to, $subject, $message, $headers);
    }
}

if (!function_exists('session')) {
    /**
     * session
     *
     * @param  mixed $value
     * @param  mixed $default
     * @return mixed
     */
    function session($value = null, $default = null)
    {
        if ($value == null) {
            return Session::getInstance();
        }

        if (!is_array($value)) {
            return Session::getInstance()->get($value, $default);
        }
        foreach ($value as $key => $item) {
            Session::getInstance()->add($key, $item);
        }

        return $value;
    }
}

if (!function_exists('cookie')) {
    /**
     * aliase sur la classe Cookie.
     *
     * @param  null       $key
     * @param  null       $data
     * @param  int        $expirate
     * @param  null       $path
     * @param  null       $domain
     * @param  bool|false $secure
     * @param  bool|true  $http
     * @return null|string
     */
    function cookie(
        $key = null,
        $data = null,
        $expirate = 3600,
        $path = null,
        $domain = null,
        $secure = false,
        $http = true
    ) {
        if ($key === null) {
            return Cookie::all();
        }

        if ($key !== null && $data == null) {
            return Cookie::get($key);
        }

        if ($key !== null && $data !== null) {
            return Cookie::set($key, $data, $expirate, $path, $domain, $secure, $http);
        }

        return null;
    }
}

if (!function_exists('validator')) {
    /**
     * Elle permet de valider les inforations sur le critère bien définie
     *
     * @param  array $inputs Les données a validé
     * @param  array $rules  Les critaires de validation
     * @return \Bow\Validation\Validate
     */
    function validator(array $inputs, array $rules)
    {
        return \Bow\Validation\Validator::make($inputs, $rules);
    }
}

if (!function_exists('route')) {
    /**
     * Get Route by name
     *
     * @param  string $name
     * @param  array  $data
     * @return string
     */
    function route($name, array $data = [])
    {
        $routes = config('app.routes');

        if (!isset($routes[$name])) {
            throw new \InvalidArgumentException($name .' n\'est pas un nom définie.', E_USER_ERROR);
        }

        $url = $routes[$name];

        foreach ($data as $key => $value) {
            $url = str_replace(':'. $key, $value, $url);
        }

        return rtrim(env('APP_URL'), '/').'/'.ltrim($url, '/');
    }
}

if (!function_exists('e')) {
    /**
     * Echape les tags HTML dans la chaine.
     *
     * @param  string $value
     * @return string
     */
    function e($value)
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
    }
}

if (!function_exists('ftp')) {
    /**
     * Alias sur le connection FTP.
     *
     * @return \Bow\Storage\Ftp\FTP
     */
    function ftp()
    {
        return Storage::service('ftp');
    }
}

if (!function_exists('s3')) {
    /**
     * Alias sur le connection S3.
     *
     * @return \Bow\Storage\AWS\AwsS3Client
     */
    function s3()
    {
        return Storage::service('s3');
    }
}

if (!function_exists('mount')) {
    /**
     * Alias sur la methode mount
     *
     * @param string $mount
     * @return \Bow\Storage\MountFilesystem
     * @throws \Bow\Storage\Exception\ResourceException
     */
    function mount($mount)
    {
        return Storage::mount($mount);
    }
}

if (!function_exists('cache')) {
    /**
     * Alias sur le connection FTP.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return mixed
     */
    function cache($key = null, $value = null)
    {
        if ($key !== null && $value === null) {
            return \Bow\Cache\Cache::get($key);
        }

        return \Bow\Cache\Cache::add($key, $value);
    }
}

if (!function_exists('back')) {
    /**
     * Make redirection to back
     *
     * @param int $status
     * @return Bow\Http\Redirect
     */
    function back($status = 302)
    {
        return redirect()->back($status);
    }
}

if (!function_exists('bhash')) {
    /**
     * Alias sur la class Hash.
     *
     * @param  string $data
     * @param  mixed  $hash_value
     * @return mixed
     */
    function bhash($data, $hash_value = null)
    {
        if (!is_null($hash_value)) {
            return Hash::check($data, $hash_value);
        }

        return Hash::make($data);
    }
}

if (!function_exists('faker')) {
    /**
     * Alias sur la class Filler.
     *
     * @param  string $type
     * @return mixed
     */
    function faker($type = null)
    {
        if (is_null($type)) {
            return new Faker();
        }

        $params = array_slice(func_get_args(), 1);

        if (method_exists(Faker::class, $type)) {
            return call_user_func_array([Faker::class, $type], $params);
        }

        return null;
    }
}

if (!function_exists('trans')) {
    /**
     * Make translation
     *
     * @param string $key
     * @param array $data
     * @param bool $choose
     * @return string | Bow\Translate\Translator
     */
    function trans($key = null, $data = [], $choose = false)
    {
        if (is_null($key)) {
            return Translator::getInstance();
        }

        if (is_bool($data)) {
            $choose = $data;

            $data = [];
        }

        return Translator::translate($key, $data, $choose);
    }
}

if (!function_exists('t')) {
    /**
     * Alise de trans
     *
     * @param  $key
     * @param  $data
     * @param  bool $choose
     * @return string
     */
    function t($key, $data = [], $choose = false)
    {
        return trans($key, $data, $choose);
    }
}

if (!function_exists('env')) {
    /**
     * Gets the app environement variable
     *
     * @param $key
     * @param $default
     * @return string
     */
    function env($key, $default = null)
    {
        if (Env::isLoaded()) {
            return Env::get($key, $default);
        }

        return $default;
    }
}

if (!function_exists('app_env')) {
    /**
     * Gets the app environement variable
     *
     * @param $key
     * @param $default
     * @return string
     */
    function app_env($key, $default = null)
    {
        if (Env::isLoaded()) {
            return Env::get($key, $default);
        }

        return $default;
    }
}

if (!function_exists('abort')) {
    /**
     * Abort bow execution
     *
     * @param int    $code
     * @param string $message
     * @return \Bow\Http\Response
     */
    function abort($code = 500, $message = '')
    {
        return response($message, $code);
    }
}

if (!function_exists('abort_if')) {
    /**
     * Abort bow execution if condiction is true
     *
     * @param boolean $boolean
     * @param int     $code
     * @param string $message
     * @return \Bow\Http\Response|null
     */
    function abort_if($boolean, $code, $message = '')
    {
        if ($boolean) {
            return abort($code, $message);
        }

        return null;
    }
}

if (!function_exists('app_mode')) {
    /**
     * Get app enviroment mode
     *
     * @return string
     */
    function app_mode()
    {
        return app_env('MODE');
    }
}

if (!function_exists('client_locale')) {
    /**
     * Get client request language
     *
     * @return string
     */
    function client_locale()
    {
        return request()->lang();
    }
}

if (!function_exists('old')) {
    /**
     * Get old request valude
     *
     * @param string $key
     * @return mixed
     */
    function old($key)
    {
        return request()->old($key);
    }
}

if (!function_exists('format_validation_errors')) {
    /**
     * Formate validation erreur.
     *
     * @param  array $errors
     * @return array
     */
    function format_validation_errors(array $errors)
    {
        $validations = [];

        foreach ($errors as $key => $error) {
            $validations[$key] = $error[0];
        }

        return $validations;
    }
}

if (!function_exists('auth')) {
    /**
     * Récupération du guard
     *
     * @param string $guard
     * @return Bow\Auth\Auth
     * @throws
     */
    function auth($guard = null)
    {
        $auth = Auth::getInstance();

        if (is_null($guard)) {
            return $auth;
        }

        return $auth->guard($guard);
    }
}

if (!function_exists('log')) {
    /**
     * Log error message
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return bool
     */
    function log($level, $message, array $context = [])
    {
        if (!in_array($level, ['info', 'warning', 'error', 'critical', 'debug'])) {
            return false;
        }

        return app('logger')->${$level}($message, $context);
    }
}

if (!function_exists('slugify')) {
    /**
     * slugify, transforme un chaine de caractère en slug
     * eg. la chaine '58 comprendre bow framework' -> '58-comprendre-bow-framework'
     *
     * @param  string $str
     * @param  string $sep
     * @return string
     */
    function slugify($str, $sep = '-')
    {
        return str()->slugify($str, $sep);
    }
}

if (!function_exists('str_slug')) {
    /**
     * slugify, transforme un chaine de caractère en slug
     * eg. la chaine '58 comprendre bow framework' -> '58-comprendre-bow-framework'
     *
     * @param  string $str
     * @param  string $sep
     * @return string
     */
    function str_slug($str, $sep = '-')
    {
        return slugify($str, $sep);
    }
}

if (!function_exists('str')) {
    /**
     * Alis for \Bow\Support\Str class
     *
     * @return \Bow\Support\Str
     */
    function str()
    {
        return new \Bow\Support\Str();
    }
}

if (!function_exists('is_mail')) {
    /**
     * Check if the email is valid
     *
     * @param string $email
     * @return bool
     */
    function is_mail($email)
    {
        return \Bow\Support\Str::isMail($email);
    }
}

if (!function_exists('is_domain')) {
    /**
     * Check if the string is domain
     *
     * @param string $domain
     * @return bool
     * @throws
     */
    function is_domain($domain)
    {
        return \Bow\Support\Str::isDomain($domain);
    }
}

if (!function_exists('is_slug')) {
    /**
     * Check if string is slug
     *
     * @param string $slug
     * @return bool
     * @throws
     */
    function is_slug($slug)
    {
        return \Bow\Support\Str::isSlug($slug);
    }
}

if (!function_exists('is_alpha')) {
    /**
     * Check if the string is alpha
     *
     * @param string $string
     * @return bool
     * @throws
     */
    function is_alpha($string)
    {
        return \Bow\Support\Str::isAlpha($string);
    }
}

if (!function_exists('is_lower')) {
    /**
     * Check if the string is lower
     *
     * @param string $string
     * @return bool
     */
    function is_lower($string)
    {
        return \Bow\Support\Str::isLower($string);
    }
}

if (!function_exists('is_upper')) {
    /**
     * Check if the string is upper
     *
     * @param string $string
     * @return bool
     */
    function is_upper($string)
    {
        return \Bow\Support\Str::isUpper($string);
    }
}

if (!function_exists('is_alpha_num')) {
    /**
     * Check if string is alpha numeric
     *
     * @param string $slug
     * @return bool
     * @throws
     */
    function is_alpha_num($slug)
    {
        return \Bow\Support\Str::isAlphaNum($slug);
    }
}

if (!function_exists('str_shuffle_words')) {
    /**
     * Shuffle words
     *
     * @param string $words
     * @return string
     */
    function str_shuffle_words($words)
    {
        return \Bow\Support\Str::shuffleWords($words);
    }
}

if (!function_exists('str_wordify')) {
    /**
     * Check if string is slug
     *
     * @param string $words
     * @param string $sep
     * @return array
     */
    function str_wordify($words, $sep = '')
    {
        return \Bow\Support\Str::wordify($words, $sep);
    }
}

if (!function_exists('str_plurial')) {
    /**
     * Transform text to plurial
     *
     * @param string $slug
     * @return string
     */
    function str_plurial($slug)
    {
        return \Bow\Support\Str::plurial($slug);
    }
}

if (!function_exists('str_camel')) {
    /**
     * Transform text to camel case
     *
     * @param string $slug
     * @return string
     */
    function str_camel($slug)
    {
        return \Bow\Support\Str::camel($slug);
    }
}

if (!function_exists('str_snake')) {
    /**
     * Transform text to snake case
     *
     * @param string $slug
     * @return string
     */
    function str_snake($slug)
    {
        return \Bow\Support\Str::snake($slug);
    }
}

if (!function_exists('str_contains')) {
    /**
     * Check if string contain an other string
     *
     * @param string $search
     * @param string $string
     * @return bool
     */
    function str_contains($search, $string)
    {
        return \Bow\Support\Str::contains($search, $string);
    }
}

if (!function_exists('str_capitalize')) {
    /**
     * Capitalize
     *
     * @param string $slug
     * @return string
     */
    function str_capitalize($slug)
    {
        return \Bow\Support\Str::capitalize($slug);
    }
}

if (!function_exists('str_random')) {
    /**
     * Random string
     *
     * @param string $string
     * @return string
     */
    function str_random($string)
    {
        return \Bow\Support\Str::randomize($string);
    }
}

if (!function_exists('str_force_in_utf8')) {
    /**
     * Force output string to utf8
     *
     * @param string $string
     * @return void
     */
    function str_force_in_utf8($string)
    {
        return \Bow\Support\Str::forceInUTF8($string);
    }
}
