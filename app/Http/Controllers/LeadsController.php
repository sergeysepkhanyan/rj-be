<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Order;
use App\Models\User;
use App\Http\Resources\LeadResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class LeadsController extends Controller
{
    /**
     * Get paginated list of leads
     */
    public function index(Request $request): JsonResponse
    {
        $query = Lead::with('referral')->whereNull('converted_user_id');

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by source
        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 15);
        $leads = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'leads' => LeadResource::collection($leads),
                'meta' => [
                    'current_page' => $leads->currentPage(),
                    'last_page' => $leads->lastPage(),
                    'per_page' => $leads->perPage(),
                    'total' => $leads->total(),
                ],
            ],
        ]);
    }

    /**
     * Store a new lead
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255', 'regex:/^(?=.*\pL)[\pL\pM\s\'\-.]+$/u'],
            'nameAr' => 'nullable|string|max:255',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,18}$/', 'unique:leads,phone'],
            'email' => ['nullable', 'email:rfc', 'max:255', 'regex:/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/'],
            'source' => 'sometimes|in:manual,booking,order,inquiry',
            'status' => 'sometimes|in:new,contacted,qualified,converted,lost',
            'notes' => 'nullable|string',
            'referralId' => 'nullable|exists:referrals,id',
        ], [
            'name.regex' => 'Name must contain letters.',
            'phone.regex' => 'Phone must include a country code and at least 7 digits.',
            'email.regex' => 'Please enter a valid email address.',
        ]);

        $existingUserQuery = User::query()->where('mobile', $validated['phone']);
        if (!empty($validated['email'])) {
            $existingUserQuery->orWhere('email', $validated['email']);
        }
        $existingUser = $existingUserQuery->first();
        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'Existing user with this email/mobile',
                'data' => ['userId' => $existingUser->id],
            ], 422);
        }

        if (!empty($validated['email'])) {
            $existingLead = Lead::where('email', $validated['email'])->first();
            if ($existingLead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Existing user with this email/mobile',
                    'data' => ['leadId' => $existingLead->id],
                ], 422);
            }
        }

        $lead = Lead::create([
            'name' => $validated['name'],
            'name_ar' => $validated['nameAr'] ?? null,
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'source' => $validated['source'] ?? 'manual',
            'status' => $validated['status'] ?? 'new',
            'notes' => $validated['notes'] ?? null,
            'referral_id' => $validated['referralId'] ?? null,
        ]);

        $lead->load('referral');

        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully',
            'data' => ['lead' => new LeadResource($lead)],
        ], 201);
    }

    /**
     * Get a single lead
     */
    public function show(Lead $lead): JsonResponse
    {
        $lead->load(['referral', 'convertedUser']);

        return response()->json([
            'success' => true,
            'data' => ['lead' => new LeadResource($lead)],
        ]);
    }

    /**
     * Update a lead
     */
    public function update(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:255', 'regex:/^(?=.*\pL)[\pL\pM\s\'\-.]+$/u'],
            'nameAr' => 'nullable|string|max:255',
            'phone' => ['sometimes', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,18}$/', 'unique:leads,phone,' . $lead->id],
            'email' => ['nullable', 'email:rfc', 'max:255', 'regex:/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/'],
            'status' => 'sometimes|in:new,contacted,qualified,converted,lost',
            'notes' => 'nullable|string',
            'referralId' => 'nullable|exists:referrals,id',
        ], [
            'name.regex' => 'Name must contain letters.',
            'phone.regex' => 'Phone must include a country code and at least 7 digits.',
            'email.regex' => 'Please enter a valid email address.',
        ]);

        // Cross-table uniqueness on update (skip rows belonging to this lead).
        if (!empty($validated['phone']) || !empty($validated['email'])) {
            $existingUserQuery = User::query();
            if (!empty($validated['phone'])) {
                $existingUserQuery->where('mobile', $validated['phone']);
            }
            if (!empty($validated['email'])) {
                $existingUserQuery->orWhere('email', $validated['email']);
            }
            if ($existingUserQuery->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Existing user with this email/mobile',
                ], 422);
            }
        }

        $lead->update([
            'name' => $validated['name'] ?? $lead->name,
            'name_ar' => $validated['nameAr'] ?? $lead->name_ar,
            'phone' => $validated['phone'] ?? $lead->phone,
            'email' => $validated['email'] ?? $lead->email,
            'status' => $validated['status'] ?? $lead->status,
            'notes' => $validated['notes'] ?? $lead->notes,
            'referral_id' => array_key_exists('referralId', $validated) ? $validated['referralId'] : $lead->referral_id,
        ]);

        $lead->load('referral');

        return response()->json([
            'success' => true,
            'message' => 'Lead updated successfully',
            'data' => ['lead' => new LeadResource($lead)],
        ]);
    }

    /**
     * Delete a lead
     */
    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead deleted successfully',
        ]);
    }

    /**
     * Build the booking/order match constraints for a lead. Matches on phone
     * (primary) and email (fallback). Returns null when the lead has neither
     * — caller should treat as "no transactions".
     */
    private function leadMatchClause(Lead $lead): ?array
    {
        $phone = $lead->phone ? trim((string) $lead->phone) : null;
        $email = $lead->email ? trim((string) $lead->email) : null;

        if (!$phone && !$email) {
            return null;
        }

        return ['phone' => $phone, 'email' => $email];
    }

    /**
     * Lead profile: contact info + same stat tiles the client detail page
     * shows (bookings count + total, no-shows, confirmed orders + total,
     * total spent). Bookings/orders are matched by customer_phone /
     * customer_email since leads have no user_id.
     */
    public function profile(Lead $lead): JsonResponse
    {
        $lead->load(['referral', 'convertedUser', 'leadNotes.createdBy']);
        $match = $this->leadMatchClause($lead);

        $bookingsBase = Booking::query()->where('type', 'booking');
        $ordersBase = Order::query()->where('type', 'ecommerce');

        if ($match === null) {
            // Lead with no contact details — return zeros rather than match
            // every guest booking that also has nulls.
            $applyMatch = fn($q) => $q->whereRaw('1 = 0');
        } else {
            $phone = $match['phone'];
            $email = $match['email'];
            $applyMatch = function ($q) use ($phone, $email) {
                $q->where(function ($inner) use ($phone, $email) {
                    if ($phone) {
                        $inner->where('customer_phone', $phone);
                    }
                    if ($email) {
                        $inner->orWhere('customer_email', $email);
                    }
                });
            };
        }

        $confirmedBookings = (clone $bookingsBase)
            ->where($applyMatch)
            ->whereIn('status', ['confirmed', 'completed'])
            ->get();
        $bookingsCount = $confirmedBookings->count();
        $bookingsTotal = (float) $confirmedBookings->sum('final_price');

        // "Cancelled by user themselves" doesn't apply to leads — they have
        // no account and can't initiate a cancellation; any cancel on a
        // lead's booking is by an admin. Surface 0 to keep the stats shape
        // stable, and rely on $totalCancelledCount for the visible tile.
        $cancelledByUserCount = 0;

        $totalCancelledCount = (clone $bookingsBase)
            ->where($applyMatch)
            ->where('status', 'cancelled')
            ->count();

        // Mirrors ClientsController::show() exactly so list and detail
        // counts agree. 'payment_timeout' is set by ExpirePendingBookings;
        // 'no_show' is the convention for admin-marked no-shows. cancel_reason
        // is a free-text column, not an enum, so behavior is consistent only
        // because we're using the same literals as the client query.
        $noShowCount = (clone $bookingsBase)
            ->where($applyMatch)
            ->where(function ($q) {
                $q->where('cancel_reason', 'no_show')
                    ->orWhere('cancel_reason', 'payment_timeout');
            })
            ->count();

        // Orders: customer info lives in JSON meta (guest checkout) and on
        // the shipping address (mobile field). Match either.
        if ($match === null) {
            $confirmedOrders = collect();
        } else {
            $phone = $match['phone'];
            $email = $match['email'];
            $confirmedOrders = (clone $ordersBase)
                ->whereIn('status', [
                    'paid', 'fulfilled', 'processing', 'shipped',
                    'return_requested', 'return_approved', 'return_rejected',
                    'refunded',
                ])
                ->where(function ($q) use ($phone, $email) {
                    if ($phone) {
                        $q->where('meta->customer_phone', $phone);
                    }
                    if ($email) {
                        $q->orWhere('meta->customer_email', $email);
                    }
                    if ($phone) {
                        $q->orWhereHas('shippingAddress', function ($inner) use ($phone) {
                            $inner->where('mobile', $phone);
                        });
                    }
                })
                ->get();
        }

        $ordersCount = $confirmedOrders->count();
        $ordersTotal = (float) $confirmedOrders->sum('amount');

        $notes = $lead->leadNotes->map(function (LeadNote $note) {
            return [
                'id' => $note->id,
                'content' => $note->content,
                'createdAt' => $note->created_at,
                'createdBy' => $note->createdBy ? [
                    'id' => $note->createdBy->id,
                    'name' => trim(($note->createdBy->first_name ?? '') . ' ' . ($note->createdBy->last_name ?? '')) ?: ($note->createdBy->name ?? $note->createdBy->email),
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'lead' => new LeadResource($lead),
                'notes' => $notes,
                'stats' => [
                    'bookings_count' => $bookingsCount,
                    'bookings_total' => $bookingsTotal,
                    'cancelled_by_user_count' => $cancelledByUserCount,
                    'total_cancelled_count' => $totalCancelledCount,
                    'no_show_count' => $noShowCount,
                    'orders_count' => $ordersCount,
                    'orders_total' => $ordersTotal,
                    'total_spent' => $bookingsTotal + $ordersTotal,
                ],
            ],
        ]);
    }

    public function addNote(Lead $lead, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $note = LeadNote::create([
            'lead_id' => $lead->id,
            'created_by' => auth()->id(),
            'content' => $validated['content'],
        ])->load('createdBy');

        return response()->json([
            'success' => true,
            'data' => [
                'note' => [
                    'id' => $note->id,
                    'content' => $note->content,
                    'createdAt' => $note->created_at,
                    'createdBy' => $note->createdBy ? [
                        'id' => $note->createdBy->id,
                        'name' => trim(($note->createdBy->first_name ?? '') . ' ' . ($note->createdBy->last_name ?? '')) ?: ($note->createdBy->name ?? $note->createdBy->email),
                    ] : null,
                ],
            ],
        ]);
    }

    public function deleteNote(Lead $lead, LeadNote $note): JsonResponse
    {
        if ((int) $note->lead_id !== (int) $lead->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note does not belong to this lead',
            ], 404);
        }

        $note->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Bookings linked to this lead (matched by customer_phone / email).
     */
    public function bookings(Lead $lead, Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $match = $this->leadMatchClause($lead);

        if ($match === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'bookings' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                    ],
                ],
            ]);
        }

        $phone = $match['phone'];
        $email = $match['email'];

        $bookings = Booking::query()
            ->where('type', 'booking')
            ->where(function ($q) use ($phone, $email) {
                if ($phone) {
                    $q->where('customer_phone', $phone);
                }
                if ($email) {
                    $q->orWhere('customer_email', $email);
                }
            })
            ->with(['master', 'services.bookable', 'services.master'])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'bookings' => $bookings->items(),
                'meta' => [
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                    'per_page' => $bookings->perPage(),
                    'total' => $bookings->total(),
                ],
            ],
        ]);
    }

    /**
     * Orders linked to this lead. Same shape as ClientsController::orders so
     * the FE detail page can reuse the same hook / table.
     */
    public function orders(Lead $lead, Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $match = $this->leadMatchClause($lead);

        if ($match === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'orders' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                    ],
                ],
            ]);
        }

        $phone = $match['phone'];
        $email = $match['email'];

        $orders = Order::query()
            ->where('type', 'ecommerce')
            ->where(function ($q) use ($phone, $email) {
                if ($phone) {
                    $q->where('meta->customer_phone', $phone);
                }
                if ($email) {
                    $q->orWhere('meta->customer_email', $email);
                }
                if ($phone) {
                    $q->orWhereHas('shippingAddress', function ($inner) use ($phone) {
                        $inner->where('mobile', $phone);
                    });
                }
            })
            ->with(['items.product', 'shippingAddress.country', 'orderReturn'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $formattedOrders = collect($orders->items())->map(function ($order) {
            return [
                'id' => $order->id,
                'reference' => $order->reference,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'type' => $order->type,
                'status' => $order->status,
                'payment_status' => in_array($order->status, ['return_requested', 'return_approved', 'return_rejected', 'refunded', 'gift'])
                    ? $order->status
                    : ($order->paid_at ? 'paid' : ($order->status === 'cancelled' ? 'cancelled' : 'unpaid')),
                'delivery_status' => $order->delivery_status,
                'created_at' => $order->created_at,
                'paid_at' => $order->paid_at,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'quantity' => $item->quantity,
                        'price' => $item->unit_price,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'image' => $item->product->main_image
                                ? asset('storage/' . $item->product->main_image)
                                : null,
                        ] : null,
                    ];
                }),
                'shippingAddress' => $order->shippingAddress ? [
                    'city' => $order->shippingAddress->city,
                    'state' => $order->shippingAddress->country?->name ?? null,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $formattedOrders,
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
            ],
        ]);
    }

    /**
     * Export CRM data (leads + clients) to CSV
     */
    public function export(Request $request)
    {
        $type = $request->input('type', 'all'); // all, leads, clients

        $data = [];
        $headers = ['Type', 'Name', 'Phone', 'Email', 'Status', 'Source', 'Referral Level', 'Bookings', 'Orders', 'Created At'];

        // Get leads
        if ($type === 'all' || $type === 'leads') {
            $leads = Lead::with('referral')->get();
            foreach ($leads as $lead) {
                $data[] = [
                    'Lead',
                    $lead->name,
                    $lead->phone,
                    $lead->email ?? '',
                    $lead->status,
                    $lead->source,
                    $lead->referral?->name ?? '',
                    '', // No bookings for leads
                    '', // No orders for leads
                    $lead->created_at?->format('Y-m-d H:i:s'),
                ];
            }
        }

        // Get clients (registered users)
        if ($type === 'all' || $type === 'clients') {
            $clients = User::with(['referral', 'manualReferral'])
                ->withCount(['clientBookings', 'orders'])
                ->whereHas('role', fn($q) => $q->where('slug', 'client'))
                ->get();

            foreach ($clients as $client) {
                $referral = $client->manualReferral ?? $client->referral;
                $data[] = [
                    'Client',
                    $client->name ?? '',
                    $client->mobile ?? '',
                    $client->email,
                    '--',
                    '--',
                    $referral?->name ?? '',
                    $client->client_bookings_count,
                    $client->orders_count,
                    $client->created_at?->format('Y-m-d H:i:s'),
                ];
            }
        }

        // Generate CSV
        $filename = 'crm_export_' . date('Y-m-d_H-i-s') . '.csv';

        $callback = function () use ($data, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Find or create lead by phone (used by booking system)
     */
    public static function findOrCreateByPhone(string $phone, string $name, ?string $email = null, string $source = 'booking'): ?Lead
    {
        // Check if user already exists
        $existingUser = User::where('mobile', $phone)->first();
        if ($existingUser) {
            return null; // User exists, no need for lead
        }

        // Check if lead already exists
        $lead = Lead::where('phone', $phone)->first();
        if ($lead) {
            return $lead;
        }

        // Create new lead
        return Lead::create([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'source' => $source,
            'status' => 'new',
        ]);
    }
}
