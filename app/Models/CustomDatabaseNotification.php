<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class CustomDatabaseNotification extends DatabaseNotification
{
    /**
     * Override delete to prevent auto-deletion of Filament notifications
     */
    public function delete()
    {
        $data = $this->data ?? [];
        $format = $data['format'] ?? null;
        $notifiableType = $this->notifiable_type ?? null;
        
        // Block deletion if it's a Filament notification for an Admin
        if ($format === 'filament' && $notifiableType === \App\Models\Admin::class) {
            \Log::warning('🔔 🚫 BLOCKING delete of Filament notification', [
                'notification_id' => $this->id,
                'format' => $format,
                'notifiable_type' => $notifiableType,
            ]);
            
            // Instead of deleting, just mark as read if not already read
            if (!$this->read_at) {
                $this->update(['read_at' => now()]);
                \Log::info('🔔 ✅ Marked notification as read instead of deleting', [
                    'notification_id' => $this->id,
                ]);
            }
            
            // Return true to indicate "success" (even though we didn't delete)
            return true;
        }
        
        // Allow deletion for other cases (explicit deletes)
        return parent::delete();
    }
}

