<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

abstract class ApiController extends Controller
{
    protected function ok($data, int $status = 200)
    {
        return response()->json(['data' => $data], $status);
    }

    protected function fail(string $code, string $message, int $status = 422, array $extra = [])
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message] + $extra], $status);
    }
}
