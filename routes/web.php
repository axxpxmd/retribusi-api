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
$router->group(['prefix' => 'auth', 'namespace' => 'Auth'], function () use ($router) {
    $router->post('login', 'AuthController@login');
});

$router->group(['middleware' => 'auth', 'namespace' => 'BJB'], function () use ($router) {
    $router->get('invoice', 'InvoiceController@checkNoBayar');
    $router->get('invoice/{no_bayar}', 'InvoiceController@invoice');
    $router->put('invoice/update/{id}', 'InvoiceController@update');
});

$router->post('callback', 'BJB\CallBackController@callBackVA');
$router->post('callback-qris', 'BJB\CallBackController@callbackQRIS');

$router->get('no-bayar/{no_bayar}', 'Client\SKRDController@showNoBayar');
$router->get('bukti-sts/{no_bayar}', 'Client\SKRDController@showPDFSTS');

//* --------------------- Client ------------------- *//
$router->group(['namespace' => 'Client'], function () use ($router) {
    // SKRD
    $router->get('skrd', 'SKRDController@index');
    $router->post('skrd', 'SKRDController@store');
    $router->get('skrd/{id}', 'SKRDController@show');

    // Utility
    $router->get('jenis-pendapatan', 'UtilityController@getJenisPendapatan');
    $router->get('rincian-pendapatan/{jenis_pendapatan_id}', 'UtilityController@getRincianPendapatan');
    $router->get('penanda-tangan', 'UtilityController@getPenandaTangan');
    $router->get('kecamatan', 'UtilityController@getKecamatan');
    $router->get('kelurahan/{kecamatan_id}', 'UtilityController@getKelurahan');
});
