<?php

require_once __DIR__.'/../vendor/autoload.php';

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

$app = new Application();

$app['debug'] = true;

$app['$tmhOAuth'] = new tmhOAuth(array(
    'consumer_key' => 'NEEDS_VALUE',
    'consumer_secret' => 'NEEDS_VALUE',
    'user_token' => 'NEEDS_VALUE',
    'user_secret' => 'NEEDS_VALUE'
));

$app->register(new \CHH\Silex\CacheServiceProvider, array(
    'cache.options' => array(
        'default' => array(
            'driver' => 'filesystem',
            'directory' => __DIR__.'/cache/'
        )
    )
));

$app->get('/', function(Request $request) use ($app){
    $queryString = $_SERVER['QUERY_STRING'];
    if($queryString != ''){
        $tmhOAuth = $app['$tmhOAuth'];
        $params = $request->query->all();
        $requestParams = array();
        $requestkey = '';
        $callback = '';
        
        if(count($params)>0){
            foreach ($params as $key => $value) {
                if($key == 'callback'){
                    $callback = $value;
                }else if($key == '_'){
                    //skip
                }else{
                    $requestkey .= $key.$value;
                    if($key == 'rpp'){
                        $requestParams['count'] = $value;
                    }else{
                        $requestParams[$key] = $value;
                    }
                }
            }
        }
        $response = false;
        $code = 0;
        if ($app['cache']->contains($requestkey)) {
            $response = $app['cache']->fetch($requestkey);
        } else {
            $code = $tmhOAuth->request('GET', 'https://api.twitter.com/1.1/search/tweets.json',$requestParams);
            if($code == 200){
                $response = $tmhOAuth->response['response'];
                $app['cache']->save($requestkey, $response, 300);
            }            
        }
        if($callback != '' && $response != false){
            $response = $callback.'('.$response.')';
        }else if($callback != '' && $response == false){
            $response = $callback.'('.  json_encode(array('error' => true, 'code' => $code)).')';
        }else{
            $response = json_encode(array('error' => true, 'code' => $code));
        }
        return $response;     
    }else{
        return $app->json(array('message' => 'empty'),200);
    }

});

$app->run();