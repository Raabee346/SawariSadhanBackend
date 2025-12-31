<x-filament-panels::page>
    <form wire:submit.prevent="sendNotification">
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>

        <x-filament::section>
            <div class="flex justify-end">
                <x-filament::button 
                    type="submit" 
                    color="success" 
                    icon="heroicon-o-paper-airplane">
                    Send Notification
                </x-filament::button>
            </div>
        </x-filament::section>
    </form>
</x-filament-panels::page>
