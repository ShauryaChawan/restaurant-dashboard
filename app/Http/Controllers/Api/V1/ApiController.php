<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * ApiController
 *
 * Base controller for all API endpoints.
 * Provides a consistent JSON response structure across the application.
 * All V1 controllers MUST extend this class.
 */
abstract class ApiController extends Controller
{
    /**
     * Return a 200 success response.
     *
     * @param  mixed  $data  The payload to return.
     * @param  string  $message  Human-readable success message.
     * @param  int  $code  HTTP status code.
     */
    protected function success(mixed $data = null, string $message = 'Success', int $code = Response::HTTP_OK): JsonResponse
    {
        if ($data instanceof LengthAwarePaginator) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
            ], $code);
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Return a 201 created response.
     */
    protected function created(mixed $data = null, string $message = 'Resource created'): JsonResponse
    {
        return $this->success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Return a 404 not found response.
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => null,
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Return a 422 validation error response.
     *
     * @param  mixed  $errors  Validation error bag.
     */
    protected function validationError(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Return a generic error response.
     *
     * @param  int  $code  HTTP status code.
     * @param  mixed  $errors  Optional additional error context.
     */
    protected function error(string $message = 'Something went wrong', int $code = Response::HTTP_INTERNAL_SERVER_ERROR, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Return a 401 unauthorized response.
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a 403 forbidden response.
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, Response::HTTP_FORBIDDEN);
    }
}
