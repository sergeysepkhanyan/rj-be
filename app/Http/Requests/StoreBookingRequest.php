<?php

namespace App\Http\Requests;

use App\Models\SubService;
use App\Models\SubServiceItem;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'masterId' => 'master_id',
        'discountValue' => 'discount_value',
        'discountType' => 'discount_type',
        'discountLabel' => 'discount_label',
        'startTime' => 'start_time',
        'endTime' => 'end_time',
        'customerName' => 'customer_name',
        'customerPhone' => 'customer_phone',
        'customerEmail' => 'customer_email',
        'paymentMode' => 'payment_mode',
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
            'masterId'   => ['required', 'integer', 'exists:users,id'],
            'customerName'  => 'nullable|string|max:255',
            'customerPhone' => 'nullable|string|max:50',
            'customerEmail' => 'nullable|email',
            'paymentMode'   => 'required|string|in:pay_now,pay_later',
            'date'       => 'required|date_format:Y-m-d',
            'startTime' => 'required|date_format:H:i',
            'endTime'   => 'required|date_format:H:i|after:start_time',
            'services'                      => 'required|array|min:1',
            'services.*.serviceType'       => ['required', Rule::in(['subservice', 'item'])],
            'services.*.serviceId'         => ['required', 'integer'],
            'services.*.price'              => 'required|numeric|min:0',
            'discountType'  => 'nullable|in:percent,amount',
            'discountValue' => 'nullable|numeric|min:0',
            'discountLabel' => ['nullable', 'string', 'max:255'],
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $services = $this->input('services', []);
            foreach ($services as $index => $service) {
                if (!isset($service['service_type'], $service['service_id'])) {
                    continue;
                }

                $type = $service['service_type'];
                $id   = $service['service_id'];

                $exists = false;

                if ($type === 'subservice') {
                    $exists = SubService::where('id', $id)->exists();
                } elseif ($type === 'item') {
                    $exists = SubServiceItem::where('id', $id)->exists();
                }

                if (! $exists) {
                    $validator->errors()->add(
                        "services.$index.service_id",
                        'The selected service is invalid for its type.'
                    );
                }
            }

            $master = User::find($this->input('master_id'));

            if ($master && $master->role && $master->role->name !== 'master') {
                $validator->errors()->add('master_id', 'The selected user is not a master.');
            }
        });
    }
}
