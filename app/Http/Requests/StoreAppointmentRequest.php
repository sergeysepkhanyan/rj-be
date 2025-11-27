<?php

namespace App\Http\Requests;

use App\Services\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAppointmentRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'clientId' => 'client_id',
        'masterId' => 'master_id',
        'subServiceId' => 'sub_service_id',
        'paymentType' => 'payment_type',
        'discountType' => 'discount_type',
        'discountAmount' => 'discount_amount',
        'paymentAmount' => 'payment_amount',
        'paymentCurrency' => 'payment_currency',
        'paymentStatus' => 'payment_status',
        'endTime' => 'end_time',
    ];
    public function authorize(): true
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clientId' => 'nullable|integer',
            'masterId' => 'required|integer',
            'paymentType' => 'required|string|max:50',
            'discountType' => 'nullable|string|max:50',
            'discountAmount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'paymentAmount' => 'required|numeric|min:0',
            'paymentCurrency' => 'required|string|max:10',
            'paymentStatus' => 'required|in:pay_now,pay_later',
            'subServiceId' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
            'endTime' => 'required|date_format:H:i',
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
