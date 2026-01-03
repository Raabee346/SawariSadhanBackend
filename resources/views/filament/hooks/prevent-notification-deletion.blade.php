<script>
// Prevent Filament from auto-deleting notifications when clicked
// Intercept Livewire calls to markAsRead and prevent deletion
(function() {
    'use strict';
    
    let componentIntercepted = false;
    
    // Wait for Livewire to be ready
    document.addEventListener('livewire:init', function() {
        if (!window.Livewire) return;
        
        // Intercept all Livewire method calls
        Livewire.hook('morph.updated', ({ el, component }) => {
            if (!component || !component.__instance || componentIntercepted) return;
            
            // Check if this is the database notifications component
            const componentName = component.__instance.name || component.__instance.constructor?.name || '';
            const componentId = component.__instance.id || '';
            
            if (componentName.includes('database-notifications') || 
                componentName.includes('DatabaseNotifications') ||
                componentId.includes('database-notifications')) {
                
                console.log('[Notifications] Found database notifications component:', componentName, componentId);
                
                // Mark as intercepted to avoid multiple overrides
                componentIntercepted = true;
                
                // Override the markAsRead method
                const originalCall = component.__instance.call.bind(component.__instance);
                component.__instance.call = function(method, ...params) {
                    console.log('[Notifications] Livewire method called:', method, params);
                    
                    if (method === 'markAsRead' && params.length > 0) {
                        const notificationId = params[0];
                        console.log('[Notifications] 🚫 Intercepting markAsRead to use our custom method:', notificationId);
                        
                        // Call our custom component's markAsRead method
                        // This will use our override which doesn't delete
                        if (component && component.__instance && component.__instance.$wire) {
                            // Use Livewire's call method to invoke our custom markAsRead
                            // This bypasses the parent's method
                            return component.__instance.$wire.call('markAsRead', notificationId)
                                .then(() => {
                                    console.log('[Notifications] ✅ Custom markAsRead completed (notification NOT deleted)');
                                    // Refresh component to update UI
                                    setTimeout(() => {
                                        if (component && component.__instance && component.__instance.$wire) {
                                            component.__instance.$wire.$refresh();
                                        }
                                    }, 100);
                                })
                                .catch(err => {
                                    console.error('[Notifications] ❌ Error in custom markAsRead:', err);
                                });
                        }
                        
                        // Fallback: if component not available, just return (don't call original)
                        console.warn('[Notifications] ⚠️ Component not available, blocking original call');
                        return Promise.resolve();
                    }
                    
                    // For other methods, call the original
                    return originalCall(method, ...params);
                };
            }
        });
        
        // Also hook into component initialization
        Livewire.hook('component.initialized', (component) => {
            const componentName = component.name || component.constructor?.name || '';
            if (componentName.includes('database-notifications') || componentName.includes('DatabaseNotifications')) {
                console.log('[Notifications] Component initialized:', componentName);
            }
        });
    });
    
    // Don't intercept clicks - let the component handle it
    // Our custom component's markAsRead() method will prevent deletion
    // Navigation will still work because Filament handles that separately
})();
</script>
