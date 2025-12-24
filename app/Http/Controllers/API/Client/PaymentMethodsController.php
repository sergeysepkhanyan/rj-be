<?php

namespace App\Http\Controllers\API\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentMethodRequest;
use App\Http\Requests\UpdatePaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use App\Services\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class PaymentMethodsController extends Controller
{
    public function __construct(
        protected PaymentMethodService $paymentMethodService
    )
    {
    }

    public function index(): JsonResponse
    {
        $methods = $this->paymentMethodService->listForUser(auth()->id());
        return ApiResponse::success(
            PaymentMethodResource::collection($methods)
        );
    }

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        $data = $request->all();
        $data = array_intersect_key($data, array_flip((new PaymentMethod)->getFillable()));
        $data['user_id'] = auth()->id();
        $method = $this->paymentMethodService->createPaymentMethod($data);

        return ApiResponse::success(
            [
                'method' => new PaymentMethodResource($method)
            ],
            'Payment method added successfully'
        );
    }

    /**
     * @throws AuthorizationException
     */
    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $this->authorize('update', $paymentMethod);
        $data = $request->all();
        $data = array_intersect_key($data, array_flip((new PaymentMethod)->getFillable()));
        $method = $this->paymentMethodService->updatePaymentMethod($paymentMethod, $data);
        return ApiResponse::success(
            [
                'method' => new PaymentMethodResource($method)
            ],
            'Payment method updated successfully'
        );
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        $this->authorize('delete', $paymentMethod);
        $this->paymentMethodService->delete($paymentMethod);
        return ApiResponse::success(
            ['success' => true],
            'Payment method deleted successfully'
        );
    }
}


