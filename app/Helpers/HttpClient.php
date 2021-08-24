<?php

namespace App\Helpers;

use Throwable;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HttpClient
{
    static function get($host, $url, Request $request)
    {

        // dd($host, $url, $request);
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->get($host . $url, $request->all());

            // Determine if the status code is >= 200 and < 300
            if ($response->successful()) {
                return $response->object();
            }

            // Determine if the status code is >= 400
            if ($response->failed()) {
                throw new HttpException($response->status(), $response->object()->message);;
            }

            // Determine if the response has a 400 level status code
            if ($response->clientError()) {
                throw new HttpException($response->status(), $response->object()->message);;
            }

            // Determine if the response has a 500 level status code...
            if ($response->serverError()) {
                throw new HttpException($response->status(), $response->object()->message);;
            }
        } catch (Throwable $th) {
            throw $th;
        }
    }
}
