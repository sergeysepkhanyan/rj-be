<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;

class CountriesController extends Controller
{
    /**
     * Get all enabled countries
     */
    public function index(): JsonResponse
    {
        $countries = Country::enabled()->get();

        return ApiResponse::success([
            'countries' => CountryResource::collection($countries),
        ]);
    }
}
