<?php

namespace App\Traits;

trait ResponseAction
{
    protected function failure($message, $status)
    {
        return response([
            'status'  => $status,
            'message' => $message
        ], $status);
    }
}
