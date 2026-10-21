<?php

namespace App\Controllers\Api\V1;

use CodeIgniter\RESTful\ResourceController;

abstract class BaseApiController extends ResourceController
{
    protected $format = 'json';

    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200
    ) {
        return $this->respond([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    protected function error(
        string $message,
        int $statusCode = 400,
        mixed $errors = null
    ) {
        return $this->respond([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $statusCode);
    }
}
