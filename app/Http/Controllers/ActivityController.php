<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivityController extends Controller
{
    /**
     * Get user's activities
     * Query params: type (all|services|payments)
     */
    public function index(Request $request)
    {
        $type = $request->query('type', 'all');
        $user = $request->user();

        $query = Activity::where('user_id', $user->id)
            ->with(['vehicle'])
            ->orderBy('activity_date', 'desc');

        // Filter by type
        switch ($type) {
            case 'services':
                $query->where('activity_type', 'service');
                break;
            case 'payments':
                $query->where('activity_type', 'payment');
                break;
            // 'all' - no additional filtering
        }

        $activities = $query->get();

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get a single activity
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        $activity = Activity::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['vehicle'])
            ->first();

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Activity not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $activity,
        ]);
    }
}
