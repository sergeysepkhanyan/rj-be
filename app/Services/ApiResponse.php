<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Success response
     *
     * @param mixed  $data
     * @param string $message
     * @param int    $status
     * @return JsonResponse
     */
    public static function success(mixed $data = [], string $message = 'Success', int $status = 200): JsonResponse
    {
        $payload = [
            'status'  => $status,
            'success' => true,
            'message' => $message,
            'data'    => $data === [] ? (object)[] : $data,
            'errors'  => (object)[],
        ];

        return response()->json($payload, $status);
    }

    /**
     * Error response
     *
     * @param mixed $errors array|string|null
     * @param string|null $message
     * @param int $status
     * @return JsonResponse
     */
    public static function error(mixed $errors = null, string $message = null, int $status = 500): JsonResponse
    {
        $message = $message ?? 'Something went wrong. Please try again later.';
        $status = $status ?? 500;

        $normalizedErrors = (object)[];

        if (!is_null($errors)) {
            if (is_string($errors)) {
                $normalizedErrors = ['message' => $errors];
            } elseif (is_array($errors) || is_object($errors)) {
                $normalizedErrors = $errors;
            } else {
                $normalizedErrors = ['message' => (string)$errors];
            }
        }

        if (is_array($normalizedErrors) && count($normalizedErrors) === 0) {
            $normalizedErrors = (object)[];
        }

        $payload = [
            'status'  => $status,
            'success' => false,
            'message' => $message,
            'errors'  => $normalizedErrors,
            'data'    => (object)[],
        ];

        return response()->json($payload, $status);
    }

}


