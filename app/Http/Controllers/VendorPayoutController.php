<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Models\RenewalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VendorPayoutController extends Controller
{
    /**
     * Per‑completed‑request earning for vendors.
     */
    private const PER_REQUEST_EARNING = 250.0;

    /**
     * Get payout summary for the authenticated vendor.
     *
     * Response:
     * - total_earned: total lifetime earnings (completed * 250)
     * - this_month_earned: earnings in current AD month
     * - total_paid: sum of completed payouts
     * - payout_pending: total_earned - total_paid
     * - completed_requests: count of completed renewal requests
     */
    public function summary(Request $request)
    {
        /** @var Vendor $vendor */
        $vendor = $request->user();

        if (!$vendor instanceof Vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Only vendors can access payout summary.',
            ], 403);
        }

        $vendorId = $vendor->id;

        // All completed renewal requests for this vendor
        $completedQuery = RenewalRequest::where('vendor_id', $vendorId)
            ->where('status', 'completed');

        $completedCount = (clone $completedQuery)->count();

        // Earnings are based on completed count * fixed rate
        $totalEarned = $completedCount * self::PER_REQUEST_EARNING;

        // This month earnings (AD)
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $thisMonthCompleted = (clone $completedQuery)
            ->whereBetween('completed_at', [$monthStart, $monthEnd])
            ->count();

        $thisMonthEarned = $thisMonthCompleted * self::PER_REQUEST_EARNING;

        // Total paid to vendor via payouts
        $totalPaid = VendorPayout::where('vendor_id', $vendorId)
            ->where('status', 'paid')
            ->sum('amount');

        $payoutPending = max(0, $totalEarned - $totalPaid);

        return response()->json([
            'success' => true,
            'data' => [
                'per_request_earning' => self::PER_REQUEST_EARNING,
                'completed_requests' => $completedCount,
                'total_earned' => $totalEarned,
                'this_month_earned' => $thisMonthEarned,
                'total_paid' => (float) $totalPaid,
                'payout_pending' => $payoutPending,
                'current_month' => $now->format('F'),
                'current_year' => $now->year,
            ],
        ]);
    }

    /**
     * List payouts for the authenticated vendor.
     */
    public function index(Request $request)
    {
        /** @var Vendor $vendor */
        $vendor = $request->user();

        if (!$vendor instanceof Vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Only vendors can access payouts.',
            ], 403);
        }

        $payouts = VendorPayout::where('vendor_id', $vendor->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payouts,
        ]);
    }

    /**
     * ADMIN: List pending payouts summary per vendor.
     */
    public function adminPending()
    {
        // Simple aggregated view for admin: one row per vendor with pending amount
        $vendors = Vendor::all();
        $rows = [];

        foreach ($vendors as $vendor) {
            $completedCount = RenewalRequest::where('vendor_id', $vendor->id)
                ->where('status', 'completed')
                ->count();

            $totalEarned = $completedCount * self::PER_REQUEST_EARNING;

            $totalPaid = VendorPayout::where('vendor_id', $vendor->id)
                ->where('status', 'paid')
                ->sum('amount');

            $pending = max(0, $totalEarned - $totalPaid);

            if ($completedCount === 0 && $pending <= 0) {
                continue;
            }

            $rows[] = [
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'completed_requests' => $completedCount,
                'total_earned' => $totalEarned,
                'total_paid' => (float) $totalPaid,
                'payout_pending' => $pending,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    /**
     * ADMIN: Initiate a payout for a vendor (creates VendorPayout record).
     * In a real setup this would integrate with Khalti's payout APIs.
     */
    public function adminInitiate(Request $request, Vendor $vendor)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'nullable|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Compute pending amount
        $completedCount = RenewalRequest::where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->count();

        $totalEarned = $completedCount * self::PER_REQUEST_EARNING;
        $totalPaid = VendorPayout::where('vendor_id', $vendor->id)
            ->where('status', 'paid')
            ->sum('amount');
        $pending = max(0, $totalEarned - $totalPaid);

        if ($pending <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No pending payout for this vendor.',
            ], 400);
        }

        $amount = $request->input('amount', $pending);
        if ($amount > $pending) {
            $amount = $pending;
        }

        $now = now();

        $payout = VendorPayout::create([
            'vendor_id' => $vendor->id,
            'amount' => $amount,
            'status' => 'pending',
            'month' => (int) $now->format('n'),
            'year' => (int) $now->format('Y'),
            'currency' => 'NPR',
            'notes' => 'Admin-initiated payout for completed renewal requests at rate NPR ' . self::PER_REQUEST_EARNING . ' per task.',
        ]);

        // NOTE: At this point, you would typically:
        // - Redirect admin to Khalti sandbox web checkout OR
        // - Call Khalti payout API (if available) and update $payout->status accordingly.
        // For now we leave status as "pending" and expect a later adminMarkPaid call.

        return response()->json([
            'success' => true,
            'message' => 'Payout created. Process via Khalti sandbox and then mark as paid.',
            'data' => $payout,
        ], 201);
    }

    /**
     * ADMIN: Mark a payout as paid (after manual confirmation from Khalti test sandbox).
     */
    public function adminMarkPaid(Request $request, VendorPayout $payout)
    {
        if ($payout->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Payout is already marked as paid.',
            ], 400);
        }

        $payout->status = 'paid';
        $payout->paid_at = now();

        if ($request->filled('notes')) {
            $payout->notes = trim(($payout->notes ?? '') . PHP_EOL . $request->input('notes'));
        }

        $payout->save();

        return response()->json([
            'success' => true,
            'message' => 'Payout marked as paid.',
            'data' => $payout,
        ]);
    }
}

