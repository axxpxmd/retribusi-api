<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Http\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        $rendered = parent::render($request, $exception);

        if ($exception instanceof NotFoundHttpException) {
            //You can do any change as per your requrement here for the not found exception
            $message = $exception->getMessage() ? $exception->getMessage() : Response::$statusTexts[$rendered->getStatusCode()];
            $exception = new NotFoundHttpException($message, $exception);
        } elseif ($exception instanceof HttpException) {
            $message = $exception->getMessage() ? $exception->getMessage() : Response::$statusTexts[$rendered->getStatusCode()];
            $exception = new HttpException($rendered->getStatusCode(), $message);
        } else if ($exception instanceof ValidationException) {
            $messages = [];
            foreach ($exception->errors() as $key => $value) {
                $messages += [$key => $value[0]];
            }
            return response()->json([
                'status'  => $rendered->getStatusCode(),
                'message' => $messages[$key],
                'error'   => [
                    'message'   => $messages,
                    'parameter' => $request->all(),
                ]
            ], $rendered->getStatusCode());
        } else {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
            $message = env('APP_DEBUG', false) ? $exception->getMessage() : Response::$statusTexts[$statusCode];
            $exception = new HttpException($statusCode, $message);
        }

        // Resonse 
        return response()->json([
            'status'  => $rendered->getStatusCode(),
            'message' => $exception->getMessage()
        ], $rendered->getStatusCode());
    }
}
