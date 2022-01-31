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

//* --------------------- BJB ---------------------- *//
// Auth
$router->group(['prefix' => 'auth', 'namespace' => 'Auth'], function () use ($router) {
    $router->post('login', 'AuthController@login');
});
// Invoice
$router->group(['middleware' => 'auth', 'namespace' => 'BJB'], function () use ($router) {
    $router->get('invoice', 'InvoiceController@checkNoBayar');
    $router->get('invoice/{no_bayar}', 'InvoiceController@invoice');
    $router->put('invoice/update/{id}', 'InvoiceController@update');
});
// Callback
$router->post('callback', 'BJB\CallBackController@callBack');

//* --------------------- Client ------------------- *//
$router->group(['namespace' => 'Client'], function () use ($router) {
    $router->get('skrd', 'SKRDController@index');
    $router->get('skrd/store', 'SKRDController@store');
});
