<?php

namespace App\Http\Requests;

use App\Models\SubService;
use App\Models\SubServiceItem;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'discountValue' => 'discount_value',
        'discountType' => 'discount_type',
        'discountLabel' => 'discount_label',
        'startTime' => 'start_time',
        'endTime' => 'end_time',
        'customerName' => 'customer_name',
        'customerPhone' => 'customer_phone',
        'customerEmail' => 'customer_email',
        'paymentMode' => 'payment_mode',
        'paymentProvider' => 'payment_provider',
        'guestSessionId' => 'guest_session_id',
    ];

    public function rules(): array
    {
        return [
            'date'       => ['required', 'date_format:Y-m-d'],
            'startTime'  => ['required', 'date_format:H:i'],
            'endTime'    => ['required', 'date_format:H:i'],

            'timezone'      => ['nullable', 'string', 'max:64'],
            'customerName'  => ['required', 'string', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:50', 'regex:/^[+\-0-9]+$/'],
            'customerEmail' => ['required', 'email'],

            'paymentMode' => ['required', 'string', Rule::in(['pay_now', 'pay_later'])],
            'paymentProvider' => ['sometimes', 'string', Rule::in(['stripe'])],

            'services' => ['required', 'array', 'min:1'],

            'services.*.serviceType' => ['required', Rule::in(['subservice', 'item'])],
            'services.*.serviceId'   => ['required', 'integer'],
            'services.*.startTime' => ['required', 'date_format:H:i'],
            'services.*.endTime'   => ['required', 'date_format:H:i'],

            'services.*.anyMaster' => ['sometimes', 'boolean'],
            'services.*.masterId' => ['nullable', 'integer', 'exists:users,id'],

            'services.*.price' => ['required', 'numeric', 'min:0'],

            'discountType'  => ['nullable', Rule::in(['percent', 'fixed', 'none'])],
            'discountValue' => ['nullable', 'numeric', 'min:0'],
            'discountLabel' => ['nullable', 'string', 'max:255'],

            'notes' => ['nullable', 'string', 'max:1000'],
            'guestSessionId' => ['nullable', 'string', 'max:64'],
        ];
    }


    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {

            $services = $this->input('services', []);

            $services = $this->input('services', []);
            if (!is_array($services) || count($services) === 0) {
                return;
            }

            $minStart = null;
            $maxEnd   = null;

            foreach ($services as $index => $service) {
                $type      = $service['serviceType'] ?? null;
                $id        = $service['serviceId'] ?? null;
                $anyMaster = (bool)($service['anyMaster'] ?? false);
                $masterId  = $service['masterId'] ?? null;

                $startTime = $service['startTime'] ?? null;
                $endTime   = $service['endTime'] ?? null;

                if (!$type || !$id) {
                    $validator->errors()->add(
                        "services.$index.serviceId",
                        __('validation.booking.service_type_and_id_required')
                    );
                } else {
                    $exists = match ($type) {
                        'subservice' => SubService::where('id', $id)->exists(),
                        'item'       => SubServiceItem::where('id', $id)->exists(),
                        default      => false,
                    };

                    if (!$exists) {
                        $validator->errors()->add(
                            "services.$index.serviceId",
                            __('validation.booking.service_invalid_for_type')
                        );
                    }
                }
                if ($anyMaster) {
                    if (!empty($masterId)) {
                        $validator->errors()->add(
                            "services.$index.masterId",
                            __('validation.booking.master_forbidden_when_any_true')
                        );
                    }
                } else {
                    if (empty($masterId)) {
                        $validator->errors()->add(
                            "services.$index.masterId",
                            __('validation.booking.master_required_when_any_false')
                        );
                    } else {
                        $master = User::with('role')->find($masterId);

                        if (!$master) {
                            $validator->errors()->add(
                                "services.$index.masterId",
                                __('validation.booking.master_not_found')
                            );
                        } elseif (!$master->role || $master->role->slug !== 'master') {
                            $validator->errors()->add(
                                "services.$index.masterId",
                                __('validation.booking.user_not_master')
                            );
                        }
                    }
                }

                if ($startTime) {
                    $minStart = $minStart === null ? $startTime : min($minStart, $startTime);
                }
                if ($endTime) {
                    $maxEnd = $maxEnd === null ? $endTime : max($maxEnd, $endTime);
                }
            }

        });
    }
}
