<?php

namespace App\Repositories;

use App\Models\BookingService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SubService;
use App\Models\SubServiceItem;
use App\Repositories\Interfaces\ReportsRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportsRepository implements ReportsRepositoryInterface
{
    public function getTodaysTurnover(?string $date = null): Collection
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::today();

        return Order::query()
            ->where('status', 'paid')
            ->whereDate('paid_at', $targetDate)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'total' => (string) $row->total,
            ])
            ->values();
    }

    public function getTopServices(int $limit = 5): Collection
    {
        $counts = BookingService::query()
            ->select([
                'bookable_type',
                'bookable_id',
                DB::raw('COUNT(*) as total_quantity'),
                DB::raw('SUM(COALESCE(final_price, price, 0)) as total_amount'),
            ])
            ->whereHas('booking.order', function ($q) {
                $q->whereIn('status', ['paid', 'fulfilled']);
            })
            ->groupBy('bookable_type', 'bookable_id')
            ->get()
            ->keyBy(fn ($row) => $row->bookable_type . '#' . $row->bookable_id);

        $services = SubService::query()->select('id', 'name', 'currency')->get()->map(function ($row) {
            return [
                'id' => $row->id,
                'type' => SubService::class,
                'name' => $row->name,
                'currency' => $row->currency ?? config('payment.default_currency', 'AED'),
            ];
        })->merge(
            SubServiceItem::query()->select('id', 'name', 'currency')->get()->map(function ($row) {
                return [
                    'id' => $row->id,
                    'type' => SubServiceItem::class,
                    'name' => $row->name,
                    'currency' => $row->currency ?? config('payment.default_currency', 'AED'),
                ];
            })
        );

        $ranked = $services->map(function ($service) use ($counts) {
            $key = $service['type'] . '#' . $service['id'];
            $row = $counts->get($key);

            return [
                'id' => $service['id'],
                'type' => $service['type'],
                'name' => $service['name'],
                'totalQuantity' => (int) ($row->total_quantity ?? 0),
                'totalAmount' => (string) ((float) ($row->total_amount ?? 0)),
                'currency' => $service['currency'],
            ];
        })
            ->filter(fn ($item) => $item['totalQuantity'] > 0)
            ->sortBy([
                ['totalAmount', 'desc'],
                ['totalQuantity', 'desc'],
            ])
            ->values();

        return $ranked->take($limit);
    }

    public function getTopProducts(int $limit = 5): Collection
    {
        $counts = OrderItem::query()
            ->select([
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(subtotal) as total_amount'),
            ])
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['paid', 'fulfilled']);
            })
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $products = Product::query()
            ->select('id', 'name', 'sku_id', 'currency', 'main_image')
            ->get()
            ->map(function ($row) use ($counts) {
                $count = $counts->get($row->id);
                $image = null;
                if ($row->main_image) {
                    $image = asset('storage/' . $row->main_image);
                }

                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'skuId' => $row->sku_id,
                    'image' => $image,
                    'totalQuantity' => (int) ($count->total_quantity ?? 0),
                    'totalAmount' => (string) ((float) ($count->total_amount ?? 0)),
                    'currency' => $row->currency ?? config('payment.default_currency', 'AED'),
                ];
            })
            ->filter(fn ($item) => $item['totalQuantity'] > 0)
            ->sortBy([
                ['totalAmount', 'desc'],
                ['totalQuantity', 'desc'],
            ])
            ->values();

        return $products->take($limit);
    }
}
