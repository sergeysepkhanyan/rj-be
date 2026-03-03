<?php

namespace App\Http\Controllers;

use App\Models\Lead;
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
        $query = Lead::with('referral');

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
            'name' => 'required|string|max:255',
            'nameAr' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20|unique:leads,phone',
            'email' => 'nullable|email|max:255',
            'source' => 'sometimes|in:manual,booking,order,inquiry',
            'status' => 'sometimes|in:new,contacted,qualified,converted,lost',
            'notes' => 'nullable|string',
            'referralId' => 'nullable|exists:referrals,id',
        ]);

        // Check if user already exists with this phone
        $existingUser = User::where('mobile', $validated['phone'])->first();
        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'A user with this phone number already exists',
                'data' => ['userId' => $existingUser->id],
            ], 422);
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
            'name' => 'sometimes|string|max:255',
            'nameAr' => 'nullable|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:leads,phone,' . $lead->id,
            'email' => 'nullable|email|max:255',
            'status' => 'sometimes|in:new,contacted,qualified,converted,lost',
            'notes' => 'nullable|string',
            'referralId' => 'nullable|exists:referrals,id',
        ]);

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
