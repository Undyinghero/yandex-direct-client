<?php

namespace YandexDirectClient;

use Buzz\Browser;
use YandexDirectClient\ClientErrorException;
use YandexDirectClient\YandexErrorException;

/**
 * Main class.
 * 
 * @author Bubnov Mihail <bubnov.mihail@gmail.com>.
 */
class Client {
    
    const URL = 'https://api.direct.yandex.ru/v4/json/'; 
    
    /**
     * Auth token for each request
     * @var String 
     */
    private $token;
    
    /**
     * Request locale
     * @var String 
     */
    private $locale;
    
    /**
     * Http transport
     * @var \Buzz\Browser 
     */
    private $buzz;
    
    /**
     * Request headers
     * @var Array 
     */
    private static $headers = ['Content-Type', 'application/json'];
    
    /**
     * 
     * @param String $token
     * @param String $locale
     */
    public function __construct($token, $locale = 'ru') {
        $this->token = $token;
        $this->buzz = new Browser(new \Buzz\Client\Curl());
        $this->buzz->getClient()->setVerifyPeer(false);
        $this->locale = $locale;
    }
    
    /**
     * Gateway for any requests
     * @param String $name
     * @param Array $arguments
     */
    public function __call($name, $arguments) {
        $method = '\YandexDirectClient\Methods\\' . $name;
        if(!class_exists($method)){
            throw new ClientErrorException('Unknown method "'.$name.'";', 500);
        }
        
        return $this->request(new $method($arguments));
    }
    
    /**
     * Make request to Yandex Direct API
     * @param \YandexDirectClient\Methods\AbstractMethod $method
     */
    private function request(\YandexDirectClient\Methods\AbstractMethod $method) {
        $payload = [
            'locale' => $this->locale,
            'method' => $method::METHOD,
            'token' => $this->token
        ];
        if($param = $method->getParam()){
            $payload['param'] = $param;
        }
        try {
            $response = $this->buzz->post(self::URL, self::$headers, json_encode($payload))->getContent();
        }
        catch (\Exception $e) {
            throw new ClientErrorException($e->getMessage(), $e->getCode());
        }
        
        $responseJson = json_decode($response, true);
        if(empty($responseJson)){
            throw new YandexErrorException('Response is not json encoded string, but: ' . $response);
        }
        $this->checkResponseIsError($responseJson);
        
        return $method->createResponse($responseJson['data']);
    }
    
    /**
     * Check if Yandex API responsed with error
     * @throws \YandexDirectClient\YandexErrorException
     */
    private function checkResponseIsError(array $responseJson) {
        if(isset($responseJson['error_code'])){
            $yError = new YandexErrorException($responseJson['error_str'], $responseJson['error_code']);
            $yError->setErrorDetail($responseJson['error_detail']);
            
            throw $yError;
        }
        if(!isset($responseJson['data'])){
            throw new YandexErrorException('Response does not have "data" key');
        }
    }
}