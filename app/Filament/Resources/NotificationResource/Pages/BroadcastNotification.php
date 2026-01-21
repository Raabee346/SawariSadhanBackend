<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use App\Services\FCMNotificationService;
use App\Models\BroadcastNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;

class BroadcastNotification extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = NotificationResource::class;

    protected string $view = 'filament.resources.notification-resource.pages.broadcast-notification';

    public ?array $data = [];

    protected ?FCMNotificationService $fcmService = null;

    public function mount(FCMNotificationService $fcmService): void
    {
        $this->fcmService = $fcmService;
        $this->data = [
            'target' => 'users',
            'notification_title' => '',
            'notification_message' => '',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('target')
                    ->label('Send To')
                    ->options([
                        'users' => 'All Users',
                        'vendors' => 'All Vendors (Riders)',
                    ])
                    ->required()
                    ->helperText('Select whether to send notification to all users or all vendors')
                    ->default('users')
                    ->live(),
                TextInput::make('notification_title')
                    ->label('Notification Title')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Enter notification title')
                    ->helperText('This will be shown as the notification title'),
                Textarea::make('notification_message')
                    ->label('Notification Message')
                    ->required()
                    ->rows(6)
                    ->maxLength(1000)
                    ->placeholder('Enter notification message')
                    ->helperText('This will be shown as the notification body'),
            ])
            ->statePath('data');
    }

    public function sendNotification(): void
    {
        // Ensure FCM service is available
        if ($this->fcmService === null) {
            $this->fcmService = app(FCMNotificationService::class);
        }

        $data = $this->form->getState();

        $target = $data['target'] ?? 'users';
        $title = $data['notification_title'] ?? '';
        $message = $data['notification_message'] ?? '';

        if (empty($title) || empty($message)) {
            Notification::make()
                ->title('Validation Error')
                ->danger()
                ->body('Please fill in both title and message fields.')
                ->send();
            return;
        }

        try {
            // First, save notification to database
            $broadcastNotification = BroadcastNotification::create([
                'title' => $title,
                'message' => $message,
                'target_type' => $target,
                'type' => 'admin_broadcast',
            ]);

            Log::info('Broadcast notification saved to database', [
                'notification_id' => $broadcastNotification->id,
                'target' => $target,
            ]);

            // Then send via FCM
            if ($target === 'users') {
                // Send as data-only message to ensure onMessageReceived() is called even in background
                $success = $this->fcmService->sendToAllUsers($title, $message, [
                    'type' => 'admin_broadcast',
                    'target' => 'users',
                    'notification_id' => (string)$broadcastNotification->id,
                ], true); // true = data-only message
            } else {
                $success = $this->fcmService->sendToAllVendors($title, $message, [
                    'type' => 'admin_broadcast',
                    'target' => 'vendors',
                    'notification_id' => (string)$broadcastNotification->id,
                ], true); // true = data-only message
            }

            if ($success) {
                Notification::make()
                    ->title('Notification Sent')
                    ->success()
                    ->body("Notification successfully sent to all {$target} and saved to database.")
                    ->send();

                Log::info('Admin broadcast notification sent via FCM', [
                    'notification_id' => $broadcastNotification->id,
                    'target' => $target,
                    'title' => $title,
                ]);

                // Reset form
                $this->data = [
                    'target' => 'users',
                    'notification_title' => '',
                    'notification_message' => '',
                ];
                $this->form->fill($this->data);
            } else {
                Notification::make()
                    ->title('Partially Successful')
                    ->warning()
                    ->body('Notification saved to database but FCM delivery may have failed.')
                    ->send();
            }
        } catch (\Exception $e) {
            Log::error('Error broadcasting notification', [
                'error' => $e->getMessage(),
                'target' => $target,
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title('Error')
                ->danger()
                ->body('Failed to send notification: ' . $e->getMessage())
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
