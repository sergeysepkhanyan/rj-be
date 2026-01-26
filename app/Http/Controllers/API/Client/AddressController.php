<?php

namespace App\Http\Controllers\API\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\AddressService;
use App\Services\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    public function __construct(protected AddressService $addressService) {}

    public function index(): JsonResponse
    {
        $addresses = $this->addressService->listForUser(auth()->id())->load('country');

        return ApiResponse::success([
            'addresses' => AddressResource::collection($addresses),
        ], __('success.address.listed'));
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $fillable = (new Address)->getFillable();
        $meta = ['set_default_shipping', 'set_default_billing', 'is_default'];

        $data = $request->only(array_merge($fillable, $meta));
        $data['user_id'] = auth()->id();

        $address = $this->addressService->createAddress($data)->load('country');

        return ApiResponse::success([
            'address' => new AddressResource($address),
        ], __('success.address.created'));
    }

    /**
     * @throws AuthorizationException
     */
    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $this->authorize('update', $address);

        $fillable = (new Address)->getFillable();
        $meta = ['set_default_shipping', 'set_default_billing', 'is_default'];

        $data = $request->only(array_merge($fillable, $meta));
        $address = $this->addressService->updateAddress($address, $data)->load('country');

        return ApiResponse::success([
            'address' => new AddressResource($address),
        ], __('success.address.updated'));
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Address $address): JsonResponse
    {
        $this->authorize('delete', $address);

        $this->addressService->delete($address);

        return ApiResponse::success([
            'deleted' => true,
        ], __('success.address.deleted'));
    }
}


