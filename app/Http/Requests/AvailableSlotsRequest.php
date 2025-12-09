<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvailableSlotsRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'masterId' => 'master_id',
        'subServiceId' => 'sub_service_id',
        'subServiceItemId' => 'sub_service_item_id'
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'masterId' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'subServiceId' => ['nullable', 'integer', 'exists:sub_services,id', 'required_without:subServiceItemId'],
            'subServiceItemId' => ['nullable', 'integer', 'exists:sub_service_items,id', 'required_without:subServiceId'],
        ];
    }
}
