<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller {
    public function __construct(private readonly NotificationService $notifications) {}
    public function index(Request $request): JsonResponse { $data=$request->validate(['page'=>['nullable','integer','min:1'],'per_page'=>['nullable','integer','min:1','max:50'],'type'=>['nullable','in:'.implode(',',NotificationService::TYPES)],'read'=>['nullable','boolean']]); $query=UserNotification::query()->where('user_id',auth()->id()); if(isset($data['type']))$query->where('type',$data['type']); if(array_key_exists('read',$data))$data['read']?$query->whereNotNull('read_at'):$query->whereNull('read_at'); return $this->successResponse($query->latest()->paginate($data['per_page']??15)); }
    public function unreadCount(): JsonResponse { return $this->successResponse(['count'=>UserNotification::query()->where('user_id',auth()->id())->whereNull('read_at')->count()]); }
    public function markRead(int $notificationId): JsonResponse { $item=UserNotification::query()->where('user_id',auth()->id())->findOrFail($notificationId); $item->update(['read_at'=>$item->read_at??now()]); return $this->successResponse($item,'Notification marked as read.'); }
    public function markAllRead(): JsonResponse { UserNotification::query()->where('user_id',auth()->id())->whereNull('read_at')->update(['read_at'=>now()]); return $this->successResponse([],'All notifications marked as read.'); }
    public function destroy(int $notificationId): JsonResponse { UserNotification::query()->where('user_id',auth()->id())->findOrFail($notificationId)->delete(); return $this->successResponse([],'Notification deleted.'); }
    public function preferences(): JsonResponse { return $this->successResponse($this->notifications->preferences(auth()->id())); }
    public function updatePreferences(Request $request): JsonResponse { $data=$request->validate(['preferences'=>['required','array'],'preferences.*.type'=>['required','in:'.implode(',',NotificationService::TYPES)],'preferences.*.in_app_enabled'=>['required','boolean']]); return $this->successResponse($this->notifications->updatePreferences(auth()->id(),$data['preferences']),'Notification preferences saved.'); }
}
