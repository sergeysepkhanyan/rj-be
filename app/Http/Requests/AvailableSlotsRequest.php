<?php

namespace App\Http\Requests;

class AvailableSlotsRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'masterId' => 'master_id',
        'subServiceId' => 'sub_service_id',
        'subServiceItemId' => 'sub_service_item_id',
        'excludeBookingId' => 'exclude_booking_id',
    ];

    /**
     * Coerce `anyMaster` to a real boolean before validation. Axios serializes
     * the JS boolean `true` to the query string "true", which Laravel's
     * `boolean` rule rejects (it only accepts true|false|1|0|"1"|"0").
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('anyMaster')) {
            $this->merge([
                'anyMaster' => filter_var(
                    $this->input('anyMaster'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ) ?? false,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'masterId' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'anyMaster' => ['sometimes', 'boolean'],
            'date' => ['required', 'date_format:Y-m-d'],
            'subServiceId' => ['nullable', 'integer', 'exists:sub_services,id', 'required_without:subServiceItemId'],
            'subServiceItemId' => ['nullable', 'integer', 'exists:sub_service_items,id', 'required_without:subServiceId'],
            'excludeBookingId' => ['nullable', 'integer', 'exists:bookings,id'],
        ];
    }
}
