<?php

namespace App\Http\Controllers;

use App\Services\FCMNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AdminNotificationController extends Controller
{
    protected $fcmService;

    public function __construct(FCMNotificationService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Broadcast notification to all users or all vendors
     */
    public function broadcast(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target' => 'required|in:users,vendors',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $target = $request->input('target');
        $title = $request->input('title');
        $message = $request->input('message');
        $data = $request->input('data', []);

        try {
            if ($target === 'users') {
                $success = $this->fcmService->sendToAllUsers($title, $message, $data);
            } else {
                $success = $this->fcmService->sendToAllVendors($title, $message, $data);
            }

            if ($success) {
                Log::info('Admin broadcast notification sent', [
                    'target' => $target,
                    'title' => $title,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Notification sent to all {$target} successfully",
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send notification. FCM service may not be available.',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error broadcasting notification', [
                'error' => $e->getMessage(),
                'target' => $target,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
            ], 500);
        }
    }
}

