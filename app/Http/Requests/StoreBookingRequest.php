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
            'date'       => ['required', 'date_format:Y-m-d'],
            'startTime'  => ['required', 'date_format:H:i'],
            'timezone'   => ['nullable', 'string', 'max:64'],
            'customerName'  => ['nullable','string','max:255'],
            'customerPhone' => ['nullable','string','max:50'],
            'customerEmail' => ['required','email'],
            'paymentMode' => ['required', 'string', 'in:pay_now,pay_later'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.serviceType' => ['required', Rule::in(['subservice', 'item'])],
            'services.*.serviceId'   => ['required', 'integer'],
            'services.*.masterId'    => ['required', 'integer', 'exists:users,id'],
            'services.*.price'       => ['required', 'numeric', 'min:0'],
            'discountType'  => ['nullable', 'in:percent,fixed,none'],
            'discountValue' => ['nullable', 'numeric', 'min:0'],
            'discountLabel' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {

            $services = $this->input('services', []);
            if (!is_array($services) || count($services) === 0) {
                $validator->errors()->add('services', 'At least one service is required.');
                return;
            }

            $minStart = null;
            $maxEnd = null;

            foreach ($services as $index => $service) {
                $type = $service['serviceType'] ?? null;
                $id   = $service['serviceId']  ??  null;
                $masterId = $service['masterId'] ?? null;

                $startTime = $service['startTime'] ?? null;
                $endTime   = $service['endTime']   ?? null;

                if (!$type || !$id) {
                    $validator->errors()->add("services.$index.serviceId", 'serviceType and serviceId are required.');
                } else {
                    $exists = match ($type) {
                        'subservice' => SubService::where('id', $id)->exists(),
                        'item'       => SubServiceItem::where('id', $id)->exists(),
                        default      => false,
                    };

                    if (!$exists) {
                        $validator->errors()->add(
                            "services.$index.serviceId",
                            'The selected service is invalid for its type.'
                        );
                    }
                }

                if (!$masterId) {
                    $validator->errors()->add("services.$index.masterId", 'masterId is required.');
                } else {
                    $master = User::with('role')->find($masterId);
                    if (!$master) {
                        $validator->errors()->add("services.$index.masterId", 'The selected master does not exist.');
                    } elseif (!$master->role || $master->role->slug !== 'master') {
                        $validator->errors()->add("services.$index.masterId", 'The selected user is not a master.');
                    }
                }

                if (!$startTime) {
                    $validator->errors()->add("services.$index.startTime", 'startTime is required.');
                }
                if (!$endTime) {
                    $validator->errors()->add("services.$index.endTime", 'endTime is required.');
                }

                if ($startTime) {
                    $minStart = $minStart === null ? $startTime : min($minStart, $startTime);
                }
                if ($endTime) {
                    $maxEnd = $maxEnd === null ? $endTime : max($maxEnd, $endTime);
                }
            }
            $rootStart = $this->input('start_time') ?? $this->input('startTime');
            $rootEnd   = $this->input('end_time')   ?? $this->input('endTime');

            if ($rootStart && $minStart && $rootStart !== $minStart) {
                $validator->errors()->add('startTime', "startTime must match first service start ({$minStart}).");
            }

            if ($rootEnd && $maxEnd && $rootEnd !== $maxEnd) {
                $validator->errors()->add('endTime', "endTime must match last service end ({$maxEnd}).");
            }
        });
    }
}
