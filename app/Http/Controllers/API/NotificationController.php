<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends BaseController
{
    /**
     * Get all notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
    
            // Direct query without using the relationship
            $query = DatabaseNotification::query()
                ->select('id', 'data', 'read_at', 'created_at')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', get_class($user)); // Usually App\Models\User
    
            // Apply filtering, sorting, and pagination using your method
            $result = $this->getFilteredData($request, $query);
    
            // If paginated, transform each item
            if ($result instanceof \Illuminate\Pagination\LengthAwarePaginator) {
                $result->getCollection()->transform(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'title' => $notification->data['title'] ?? null,
                        'body' => $notification->data['body'] ?? null,
                        'read' => $notification->read_at !== null,
                        'created_at' => $notification->created_at,
                    ];
                });
            } else {
                // If not paginated (just a collection), map manually
                $result = $result->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'title' => $notification->data['title'] ?? null,
                        'body' => $notification->data['body'] ?? null,
                        'read' => $notification->read_at !== null,
                        'created_at' => $notification->created_at,
                    ];
                });
            }
    
            return successResponse('Notification List Retrieved', $result);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    /**
     * Mark selected notifications as read.
     */
    public function markAsRead(Request $request)
    {
        
        try {
            $user = $request->user();
    
            // Validate the incoming request
            $request->validate([
                'notification_ids' => 'array|required',
                'notification_ids.*' => 'string|exists:notifications,id',
            ]);
    
            // Retrieve the notifications
            $notifications = $user->notifications()->whereIn('id', $request->notification_ids)->get();
    
            // Mark them as read
            foreach ($notifications as $notification) {
                $notification->markAsRead();
            }
    
            return successResponse('Notifications marked as read successfully.');
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    public function clearAllNotifications(Request $request)
    {
        try {
            $user = $request->user();
    
            // Permanently delete all notifications
            $user->notifications()->delete();
    
            return successResponse('All notifications deleted successfully.');
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    
}

