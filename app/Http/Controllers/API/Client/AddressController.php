<?php

namespace App\Http\Controllers\API\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\AddressService;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @method authorize(string $string, Address $address)
 */
class AddressController extends Controller
{
    public function __construct(protected AddressService $addressService)
    {
    }

    public function index(): JsonResponse
    {
        try {
            $addresses = $this->addressService->listForUser(auth()->id());
            return ApiResponse::success([
                'addresses' =>  AddressResource::collection($addresses),
            ], '');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        try {
            $fillable = (new Address)->getFillable();
            $meta = ['set_default_shipping', 'set_default_billing', 'is_default'];
            $data = $request->only(array_merge($fillable, $meta));
            $data['user_id'] = auth()->id();
            $address = $this->addressService->createAddress($data);
            return ApiResponse::success([
                'address' => new AddressResource($address),
            ], 'Address created successfully');

        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        try {
            $this->authorize('update', $address);
            $fillable = (new Address)->getFillable();
            $meta = ['set_default_shipping', 'set_default_billing', 'is_default'];
            $data = $request->only(array_merge($fillable, $meta));
            $address = $this->addressService->updateAddress($address, $data);

            return ApiResponse::success([
                'address' => new AddressResource($address),
            ], 'Address updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function destroy(Address $address): JsonResponse
    {
        try {
            $this->authorize('delete', $address);
            $this->addressService->delete($address);

            return ApiResponse::success([
                'success' => true,
            ], 'Address deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }
}

