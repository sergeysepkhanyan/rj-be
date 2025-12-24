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
        $addresses = $this->addressService->listForUser(auth()->id());
        return ApiResponse::success([
            'addresses' =>  AddressResource::collection($addresses),
        ], '');
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $fillable = (new Address)->getFillable();
        $meta = ['set_default_shipping', 'set_default_billing', 'is_default'];
        $data = $request->only(array_merge($fillable, $meta));
        $data['user_id'] = auth()->id();
        $address = $this->addressService->createAddress($data);
        return ApiResponse::success([
            'address' => new AddressResource($address),
        ], 'Address created successfully');
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
        $address = $this->addressService->updateAddress($address, $data);

        return ApiResponse::success([
            'address' => new AddressResource($address),
        ], 'Address updated successfully');
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Address $address): JsonResponse
    {
        $this->authorize('delete', $address);
        $this->addressService->delete($address);

        return ApiResponse::success([
            'success' => true,
        ], 'Address deleted successfully');
    }
}

