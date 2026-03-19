<?php

namespace App\Http\Requests;

use App\Models\SubService;
use App\Models\SubServiceItem;
use App\Models\User;
use App\Support\BookingOverlap;
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
            'date' => ['required', 'date_format:Y-m-d'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],

            'timezone' => ['nullable', 'string', 'max:64'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:50', 'regex:/^[+\-0-9]+$/'],
            'customerEmail' => ['required', 'email'],

            'paymentMode' => ['required', 'string', Rule::in(['pay_now', 'pay_later'])],
            'paymentProvider' => ['sometimes', 'string', Rule::in(['stripe'])],

            'services' => ['required', 'array', 'min:1'],

            'services.*.serviceType' => ['required', Rule::in(['subservice', 'item'])],
            'services.*.serviceId' => ['required', 'integer'],
            'services.*.date' => ['nullable', 'date_format:Y-m-d'],
            'services.*.startTime' => ['required', 'date_format:H:i'],
            'services.*.endTime' => ['required', 'date_format:H:i'],

            'services.*.anyMaster' => ['sometimes', 'boolean'],
            'services.*.masterId' => ['nullable', 'integer', 'exists:users,id'],

            'services.*.price' => ['required', 'numeric', 'min:0'],

            'discountType' => ['nullable', Rule::in(['percent', 'fixed', 'none'])],
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
            if (! is_array($services) || count($services) === 0) {
                return;
            }

            $tz = (string) ($this->input('timezone') ?: 'UTC');
            $defaultDate = (string) $this->input('date');

            foreach ($services as $index => $service) {
                $type = $service['serviceType'] ?? null;
                $id = $service['serviceId'] ?? null;
                $anyMaster = (bool) ($service['anyMaster'] ?? false);
                $masterId = $service['masterId'] ?? null;

                if (! $type || ! $id) {
                    $validator->errors()->add(
                        "services.$index.serviceId",
                        __('validation.booking.service_type_and_id_required')
                    );
                } else {
                    $exists = match ($type) {
                        'subservice' => SubService::where('id', $id)->exists(),
                        'item' => SubServiceItem::where('id', $id)->exists(),
                        default => false,
                    };

                    if (! $exists) {
                        $validator->errors()->add(
                            "services.$index.serviceId",
                            __('validation.booking.service_invalid_for_type')
                        );
                    }
                }
                if ($anyMaster) {
                    if (! empty($masterId)) {
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

                        if (! $master) {
                            $validator->errors()->add(
                                "services.$index.masterId",
                                __('validation.booking.master_not_found')
                            );
                        } elseif (! $master->role || $master->role->slug !== 'master') {
                            $validator->errors()->add(
                                "services.$index.masterId",
                                __('validation.booking.user_not_master')
                            );
                        }
                    }
                }
            }

            $count = count($services);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $services[$i];
                    $b = $services[$j];

                    $typeA = $a['serviceType'] ?? null;
                    $typeB = $b['serviceType'] ?? null;
                    $idA = (int) ($a['serviceId'] ?? 0);
                    $idB = (int) ($b['serviceId'] ?? 0);

                    $startA = $a['startTime'] ?? null;
                    $endA = $a['endTime'] ?? null;
                    $startB = $b['startTime'] ?? null;
                    $endB = $b['endTime'] ?? null;

                    if (! $startA || ! $endA || ! $startB || ! $endB) {
                        continue;
                    }

                    $dateA = isset($a['date']) && is_string($a['date']) && $a['date'] !== ''
                        ? $a['date']
                        : $defaultDate;
                    $dateB = isset($b['date']) && is_string($b['date']) && $b['date'] !== ''
                        ? $b['date']
                        : $defaultDate;

                    // Same service (same type + id) cannot overlap in time — different services may overlap (parallel).
                    if ($typeA && $typeB && $typeA === $typeB && $idA === $idB && $idA > 0) {
                        if (BookingOverlap::intervalsOverlap(
                            $dateA,
                            (string) $startA,
                            (string) $endA,
                            $dateB,
                            (string) $startB,
                            (string) $endB,
                            $tz
                        )) {
                            $msg = __('validation.booking.same_service_same_time_not_allowed');
                            $validator->errors()->add("services.$i.serviceId", $msg);
                            $validator->errors()->add("services.$j.serviceId", $msg);
                        }
                    }

                    $anyA = (bool) ($a['anyMaster'] ?? false);
                    $anyB = (bool) ($b['anyMaster'] ?? false);
                    $masterA = (int) ($a['masterId'] ?? 0);
                    $masterB = (int) ($b['masterId'] ?? 0);

                    if ($anyA || $anyB) {
                        continue;
                    }

                    if ($masterA > 0 && $masterB > 0 && $masterA === $masterB) {
                        if (BookingOverlap::intervalsOverlap(
                            $dateA,
                            (string) $startA,
                            (string) $endA,
                            $dateB,
                            (string) $startB,
                            (string) $endB,
                            $tz
                        )) {
                            $msg = __('validation.booking.master_overlap_same_timeslot');
                            $validator->errors()->add("services.$i.masterId", $msg);
                            $validator->errors()->add("services.$j.masterId", $msg);
                        }
                    }
                }
            }
        });
    }
}
