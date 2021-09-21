<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

/** Auth */
$router->group(['prefix' => 'auth', 'namespace' => 'Auth'], function () use ($router) {
    $router->post('login', 'AuthController@login');
    // $router->post('logout', 'AuthController@logout');
});

/** SKRD */
$router->group(['middleware' => 'auth'], function () use ($router) {
    $router->get('invoice', 'SKRDController@checkNoBayar');
    $router->get('invoice/{no_bayar}', 'SKRDController@invoice');
    $router->put('invoice/update/{id}', 'SKRDController@update');
});

/** CallBack */
$router->post('callback', 'CallBackController@callBack');
