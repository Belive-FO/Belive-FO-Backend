<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    /**
     * Return a success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     */
    protected function success($data = null, string $message = 'Operation successful.', int $statusCode = 200): JsonResponse
    {
        $response = ['message' => $message];

        if ($data instanceof ResourceCollection) {
            $resourceResponse = $data->response()->getData(true);
            $response['data'] = $resourceResponse['data'];

            if (isset($resourceResponse['meta'])) {
                $response['meta'] = $resourceResponse['meta'];
            }
            if (isset($resourceResponse['links'])) {
                $response['links'] = $resourceResponse['links'];
            }
        } elseif ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error response.
     *
     * @param string $error  Machine-readable error code (e.g. INVALID_CREDENTIALS)
     * @param string $message Human-readable message
     * @param int $statusCode
     * @param array|null $fields Validation field errors
     */
    protected function error(string $error, string $message, int $statusCode = 400, ?array $fields = null): JsonResponse
    {
        $response = [
            'error'   => $error,
            'message' => $message,
            'status'  => $statusCode,
        ];

        if ($fields !== null) {
            $response['fields'] = $fields;
        }

        return response()->json($response, $statusCode);
    }
}
