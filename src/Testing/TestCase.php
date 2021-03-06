<?php

namespace Bow\Testing;

use Bow\Http\Client\HttpClient;
use Bow\Http\Client\Parser;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase
{
    /**
     * @var array
     */
    private $attach = [];

    /**
     * @var string
     */
    protected $url = '';

    /**
     * Format url
     *
     * @param  $url
     * @return string
     */
    private function formatUrl($url)
    {
        return rtrim($this->url, '/').$url;
    }

    /**
     * @param $url
     * @param $param
     * @return Parser
     */
    public function get($url, array $param = [])
    {
        $http = new HttpClient($this->formatUrl($url));

        return $http->get($url, $param);
    }

    /**
     * @param $url
     * @param $param
     * @return Parser
     */
    public function post($url, array $param = [])
    {
        $http = new HttpClient($this->formatUrl($url));

        if (!empty($this->attach)) {
            $http->addAttach($this->attach);
        }

        return $http->post($url, $param);
    }

    /**
     * @param array $attach
     */
    public function attach(array $attach)
    {
        $this->attach = $attach;
    }

    /**
     * @param $url
     * @param $param
     * @return Parser
     */
    public function put($url, array $param = [])
    {
        $http = new HttpClient($this->formatUrl($url));

        return $http->put($url, $param);
    }

    /**
     * @param $url
     * @param array $param
     * @return Parser
     */
    public function delete($url, array $param = [])
    {
        $param = array_merge([
            '_method' => 'DELETE'
        ], $param);

        return $this->put($url, $param);
    }

    /**
     * @param $url
     * @param $param
     * @return Parser
     */
    public function patch($url, array $param = [])
    {
        $param = array_merge([
            '_method' => 'PATCH'
        ], $param);

        return $this->put($url, $param);
    }

    /**
     * @param $method
     * @param $url
     * @param array  $params
     * @return Behavior
     */
    public function visit($method, $url, array $params = [])
    {
        $method = strtolower($method);

        if (!method_exists($this, $method)) {
            throw new \BadMethodCallException('La methode ' . $method . ' n\'exist pas');
        }

        return new Behavior($this->$method($url, $params));
    }
}
