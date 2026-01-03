@php
    use Illuminate\Support\Facades\Auth;
    $admin = Auth::guard('admin')->user();
@endphp

<div x-data="adminFcmToken()" x-init="init()" style="display: none !important; height: 0; width: 0; overflow: hidden;">
    <!-- Hidden widget - FCM token is captured by admin-fcm-meta-tags.blade.php -->
</div>

<script>
function adminFcmToken() {
    return {
        init() {
            // Token capture is handled by admin-fcm-meta-tags.blade.php
            // This widget is kept for compatibility but does nothing
        }
    }
}
</script>
