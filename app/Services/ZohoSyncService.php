<?php

namespace App\Services;

use App\Integrations\Zoho\ZohoBooksClient;
use App\Models\Order;
use App\Models\Product;
use App\Models\ZohoRecord;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZohoSyncService
{
    public function __construct(
        protected ZohoBooksClient $booksClient,
    ) {}

    // -------------------------------------------------------------------------
    // Invoices + Payments — triggered when an order is paid
    // -------------------------------------------------------------------------

    public function syncOrder(Order $order): void
    {
        try {
            $customerId = $this->resolveOrCreateBooksCustomer($order);

            if (!$customerId) {
                Log::warning('[zoho][sync_order] Could not resolve Zoho Books customer', ['order_id' => $order->id]);
                return;
            }

            $order->loadMissing('items');

            $lineItems = $order->items->map(fn ($item) => [
                'name'       => $item->product?->name ?? "Product #{$item->product_id}",
                'quantity'   => $item->quantity,
                'rate'       => $item->unit_price,
            ])->values()->toArray();

            // Booking orders have no items — create a single line
            if (empty($lineItems)) {
                $lineItems = [[
                    'name'     => "Booking #{$order->reference}",
                    'quantity' => 1,
                    'rate'     => $order->amount,
                ]];
            }

            $invoicePayload = [
                'customer_id' => $customerId,
                'reference_number' => $order->reference,
                'date' => now()->format('Y-m-d'),
                'line_items' => $lineItems,
            ];

            $record = $this->findRecord($order, 'invoices');

            if ($record) {
                $this->booksClient->updateInvoice($record->zoho_id, $invoicePayload);
                $this->markSynced($record);
                $invoiceId = $record->zoho_id;
            } else {
                $res = $this->booksClient->createInvoice($invoicePayload);
                $invoiceId = data_get($res, 'invoice.invoice_id');

                if ($invoiceId) {
                    $this->upsertRecord($order, 'invoices', $invoiceId);
                }
            }

            // Record the payment against the invoice
            if ($invoiceId) {
                $this->booksClient->createPayment([
                    'customer_id'  => $customerId,
                    'payment_mode' => 'cash',
                    'amount'       => $order->amount,
                    'date'         => now()->format('Y-m-d'),
                    'invoices'     => [[
                        'invoice_id'     => $invoiceId,
                        'amount_applied' => $order->amount,
                    ]],
                ]);
            }
        } catch (Throwable $e) {
            $this->logError('sync_order', $order->id, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Items — triggered on product create / update
    // -------------------------------------------------------------------------

    public function syncProduct(Product $product): void
    {
        try {
            $payload = [
                'name'           => $product->name,
                'description'    => $product->description ?? '',
                'rate'           => $product->price,
                'purchase_rate'  => $product->cost_price ?? $product->price,
                'sku'            => $product->sku_id ?? null,
                'stock_on_hand'  => $product->max_quantity ?? 0,
                'reorder_level'  => $product->reorder_point ?? 0,
            ];

            $record = $this->findRecord($product, 'items');

            if ($record) {
                $this->booksClient->updateItem($record->zoho_id, $payload);
                $this->markSynced($record);
            } else {
                $res = $this->booksClient->createItem($payload);
                $zohoId = data_get($res, 'item.item_id');

                if ($zohoId) {
                    $this->upsertRecord($product, 'items', $zohoId);
                }
            }
        } catch (Throwable $e) {
            $this->logError('sync_product', $product->id, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function resolveOrCreateBooksCustomer(Order $order): ?string
    {
        $email = $this->resolveOrderEmail($order);

        if (!$email) {
            return null;
        }

        // Check if user has a Zoho Books customer record
        if ($order->user_id) {
            $order->loadMissing('user');
            $user = $order->user;

            if ($user) {
                $record = $this->findRecord($user, 'books_customer');

                if ($record) {
                    return $record->zoho_id;
                }
            }
        }

        // Search by email in Zoho Books
        $existing = $this->booksClient->findCustomerByEmail($email);
        $customerId = data_get($existing, 'contact_id');

        if (!$customerId) {
            $name = $this->resolveOrderCustomerName($order);
            $res = $this->booksClient->createCustomer([
                'contact_name' => $name,
                'email'        => $email,
                'contact_type' => 'customer',
            ]);
            $customerId = data_get($res, 'contact.contact_id');
        }

        // Store the Books customer ID on the user if we have one
        if ($customerId && $order->user_id) {
            $order->loadMissing('user');
            if ($order->user) {
                $this->upsertRecord($order->user, 'books_customer', $customerId);
            }
        }

        return $customerId;
    }

    private function resolveOrderEmail(Order $order): ?string
    {
        if ($order->user_id && $order->relationLoaded('user') && $order->user) {
            return $order->user->email;
        }

        return data_get($order->meta ?? [], 'customer_email');
    }

    private function resolveOrderCustomerName(Order $order): string
    {
        if ($order->user_id && $order->relationLoaded('user') && $order->user) {
            return trim("{$order->user->first_name} {$order->user->last_name}");
        }

        return data_get($order->meta ?? [], 'customer_name', 'Guest');
    }

    private function findRecord(object $model, string $module): ?ZohoRecord
    {
        return ZohoRecord::where('syncable_type', get_class($model))
            ->where('syncable_id', $model->id)
            ->where('module', $module)
            ->first();
    }

    private function upsertRecord(object $model, string $module, string $zohoId): void
    {
        ZohoRecord::updateOrCreate(
            [
                'syncable_type' => get_class($model),
                'syncable_id'   => $model->id,
                'module'        => $module,
            ],
            [
                'zoho_id'    => $zohoId,
                'synced_at'  => now(),
                'last_error' => null,
            ]
        );
    }

    private function markSynced(ZohoRecord $record): void
    {
        $record->update(['synced_at' => now(), 'last_error' => null]);
    }

    private function logError(string $action, int $entityId, Throwable $e): void
    {
        Log::channel('payments')->error("[zoho][{$action}] FAILED", [
            'entity_id' => $entityId,
            'error'     => $e->getMessage(),
        ]);
    }
}
