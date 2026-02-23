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
    ) {}

    public function index(): JsonResponse
    {
        $methods = $this->paymentMethodService->listForUser(auth()->id());

        return ApiResponse::success(
            PaymentMethodResource::collection($methods),
            __('success.payment_method.listed')
        );
    }

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        \Log::info('[PaymentMethodsController] store called', [
            'request_all' => $request->all(),
            'user_id' => auth()->id(),
        ]);

        $data = $request->all();
        $data = array_intersect_key($data, array_flip((new PaymentMethod)->getFillable()));
        $data['user_id'] = auth()->id();

        \Log::info('[PaymentMethodsController] filtered data', ['data' => $data]);

        $method = $this->paymentMethodService->createPaymentMethod($data);

        \Log::info('[PaymentMethodsController] payment method created', ['method_id' => $method->id]);

        return ApiResponse::success(
            [
                'method' => new PaymentMethodResource($method)
            ],
            __('success.payment_method.created')
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
            __('success.payment_method.updated')
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
            ['deleted' => true],
            __('success.payment_method.deleted')
        );
    }
}


