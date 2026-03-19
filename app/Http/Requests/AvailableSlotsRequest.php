<?php

namespace App\Http\Requests;

class AvailableSlotsRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'masterId' => 'master_id',
        'subServiceId' => 'sub_service_id',
        'subServiceItemId' => 'sub_service_item_id',
    ];

    public function rules(): array
    {
        return [
            'masterId' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'anyMaster' => ['sometimes', 'boolean'],
            'date' => ['required', 'date_format:Y-m-d'],
            'subServiceId' => ['nullable', 'integer', 'exists:sub_services,id', 'required_without:subServiceItemId'],
            'subServiceItemId' => ['nullable', 'integer', 'exists:sub_service_items,id', 'required_without:subServiceId'],
        ];
    }
}
