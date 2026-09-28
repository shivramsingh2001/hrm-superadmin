<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An upload/storage failure whose message is safe to show to the caller
 * ("File type not allowed", "The file could not be saved"). The underlying
 * cloud/driver error is logged by FileStorageService, never echoed back.
 *
 * Controllers that already catch \Exception keep working unchanged; the ones
 * that want the precise message catch this first and use httpStatus().
 */
class FileStorageException extends RuntimeException
{
    public function __construct(string $message, private int $httpStatus = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /** Laravel calls this when the exception is not caught by the controller. */
    public function render($request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => $this->getMessage(),
            ], $this->httpStatus);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
