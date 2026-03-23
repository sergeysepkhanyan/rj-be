<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Mail\ReturnApprovedMail;
use App\Mail\ReturnRejectedMail;
use App\Mail\ReturnRequestedMail;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\ReturnPolicySetting;
use App\Models\User;
use App\Support\OrderStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderReturnService
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function getReturnPolicy(): ReturnPolicySetting
    {
        return ReturnPolicySetting::first() ?? new ReturnPolicySetting([
            'return_window_days' => 30,
            'is_active' => true,
        ]);
    }

    public function updateReturnPolicy(array $data): ReturnPolicySetting
    {
        $policy = ReturnPolicySetting::first();

        if ($policy) {
            $policy->update($data);
        } else {
            $policy = ReturnPolicySetting::create($data);
        }

        return $policy;
    }

    public function canRequestReturn(Order $order): array
    {
        // Must be an ecommerce (product) order
        if ($order->getTypeValue() !== OrderType::Ecommerce->value) {
            return ['can' => false, 'reason' => 'Only product orders are eligible for returns.'];
        }

        // Must be in a returnable status
        $returnableStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Fulfilled->value,
        ];

        if (!in_array($order->status, $returnableStatuses, true)) {
            return ['can' => false, 'reason' => 'Order is not in a returnable status.'];
        }

        // Check if there's already a return request
        if ($order->orderReturn()->exists()) {
            return ['can' => false, 'reason' => 'A return request already exists for this order.'];
        }

        // Check return window
        $policy = $this->getReturnPolicy();

        if (!$policy->is_active) {
            return ['can' => false, 'reason' => 'Returns are currently not accepted.'];
        }

        $purchaseDate = $order->paid_at ?? $order->created_at;
        $windowEnd = $purchaseDate->copy()->addDays($policy->return_window_days);

        if (now()->gt($windowEnd)) {
            return ['can' => false, 'reason' => 'The return window of ' . $policy->return_window_days . ' days has expired.'];
        }

        return ['can' => true, 'reason' => null];
    }

    public function requestReturn(Order $order, string $reason, ?int $pickupAddressId = null): OrderReturn
    {
        return DB::transaction(function () use ($order, $reason, $pickupAddressId) {
            // Snapshot the pickup address if provided
            $addressSnapshot = null;
            if ($pickupAddressId) {
                $address = Address::with('country')->find($pickupAddressId);
                if ($address) {
                    $addressSnapshot = [
                        'id' => $address->id,
                        'name' => $address->name,
                        'last_name' => $address->last_name,
                        'mobile' => $address->mobile,
                        'address' => $address->address,
                        'additional_address' => $address->additional_address,
                        'city' => $address->city,
                        'country' => $address->country?->name,
                        'zip_code' => $address->zip_code,
                    ];
                }
            }

            $orderReturn = OrderReturn::create([
                'order_id' => $order->id,
                'reason' => $reason,
                'pickup_address_id' => $pickupAddressId,
                'pickup_address_snapshot' => $addressSnapshot,
                'status' => 'pending',
            ]);

            // Transition order status to return_requested
            OrderStateMachine::assertTransition($order->status, OrderStatus::ReturnRequested->value);
            $order->update(['status' => OrderStatus::ReturnRequested->value]);

            // Notify admin
            $this->sendReturnRequestedNotification($order, $orderReturn);

            return $orderReturn;
        });
    }

    public function approveReturn(OrderReturn $orderReturn, ?string $adminNotes = null): OrderReturn
    {
        return DB::transaction(function () use ($orderReturn, $adminNotes) {
            $orderReturn->update([
                'status' => 'approved',
                'admin_notes' => $adminNotes,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $order = $orderReturn->order;

            // Transition order to return_approved
            OrderStateMachine::assertTransition($order->status, OrderStatus::ReturnApproved->value);
            $order->update(['status' => OrderStatus::ReturnApproved->value]);

            // Now refund the order (this also restores stock for ecommerce orders)
            $this->orderService->refund($order, ['return_approved' => true]);

            // Update delivery status to canceled
            $order->update(['delivery_status' => 'canceled']);

            // Notify customer
            $this->sendReturnApprovedNotification($order, $orderReturn);

            return $orderReturn->fresh();
        });
    }

    public function rejectReturn(OrderReturn $orderReturn, ?string $adminNotes = null): OrderReturn
    {
        return DB::transaction(function () use ($orderReturn, $adminNotes) {
            $orderReturn->update([
                'status' => 'rejected',
                'admin_notes' => $adminNotes,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
            ]);

            $order = $orderReturn->order;

            // Transition order to return_rejected
            OrderStateMachine::assertTransition($order->status, OrderStatus::ReturnRejected->value);
            $order->update(['status' => OrderStatus::ReturnRejected->value]);

            // Notify customer
            $this->sendReturnRejectedNotification($order, $orderReturn);

            return $orderReturn->fresh();
        });
    }

    protected function sendReturnRequestedNotification(Order $order, OrderReturn $orderReturn): void
    {
        // Send to admin
        $superAdmin = User::whereHas('role', fn($q) => $q->where('slug', 'superadmin'))->first();
        if ($superAdmin && $superAdmin->email) {
            Mail::to($superAdmin->email)->queue(new ReturnRequestedMail($order, $orderReturn));
        }
    }

    protected function sendReturnApprovedNotification(Order $order, OrderReturn $orderReturn): void
    {
        $email = $this->orderService->getCustomerEmail($order);
        if ($email) {
            Mail::to($email)->queue(new ReturnApprovedMail($order, $orderReturn));
        }
    }

    protected function sendReturnRejectedNotification(Order $order, OrderReturn $orderReturn): void
    {
        $email = $this->orderService->getCustomerEmail($order);
        if ($email) {
            Mail::to($email)->queue(new ReturnRejectedMail($order, $orderReturn));
        }
    }
}
