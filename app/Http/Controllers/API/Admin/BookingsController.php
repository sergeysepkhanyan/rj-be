<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserBookingService;
use Illuminate\Http\Request;

class BookingsController extends Controller
{
    protected UserBookingService $userBookingService;

    public function __construct(UserBookingService $userBookingService)
    {
        $this->userBookingService = $userBookingService;
    }

    public function store(Request $request)
    {

    }
}
