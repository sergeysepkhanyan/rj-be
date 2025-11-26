<?php

namespace App\Http\Requests;

use App\Services\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): true
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'nullable|integer',
            'master_id' => 'required|integer',
            'payment_type' => 'required|string|max:50',
            'discount_type' => 'nullable|string|max:50',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'payment_amount' => 'required|numeric|min:0',
            'payment_currency' => 'required|string|max:10',
            'payment_status' => 'required|in:pay_now,pay_later',
            'sub_service_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'required|string|max:50',
            'notes' => 'nullable|string',
            'type' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:50',
        ];
    }

    /**
     * Override failed validation to use custom API response
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error($validator->errors(), 'Validation failed', 422)
        );
    }
}
