<?php
namespace App\Services;
use App\Models\UserNotification;
use App\Models\UserNotificationPreference;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class NotificationService {
    public const TYPES=['activity','transaction_activity','transfer_activity','account_activity','bill_activity','budget_activity','recurring_activity','goal_activity','achievement_activity','budget_threshold','recurring_due','goal_behind','report_job','achievement_review','financial_anomaly'];
    /** Send report emails using the project's existing delivery settings. */
    public function sendEmail(string|array $to, Mailable $mail, string|array|null $cc = null, string|array|null $bcc = null): void {
        $pendingMail = Mail::to($to);
        if (! empty($cc)) $pendingMail->cc($cc);
        if (! empty($bcc)) $pendingMail->bcc($bcc);
        $pendingMail->send($mail);
    }
    public function create(int $userId, string $type, string $title, string $message, string $dedupeKey, array $data=[], ?string $actionPath=null): ?UserNotification {
        if (!in_array($type, self::TYPES, true) || !$this->isEnabled($userId,$type)) return null;
        $safePath=$this->safeActionPath($actionPath);
        return UserNotification::query()->firstOrCreate(['user_id'=>$userId,'dedupe_key'=>$dedupeKey], ['type'=>$type,'title'=>$title,'message'=>$message,'data'=>$data ?: null,'action_path'=>$safePath]);
    }
    public function preferences(int $userId): array { $saved=UserNotificationPreference::query()->where('user_id',$userId)->get()->keyBy('type'); return array_map(fn($type)=>['type'=>$type,'in_app_enabled'=>(bool)($saved[$type]->in_app_enabled ?? true)],self::TYPES); }
    public function updatePreferences(int $userId, array $preferences): array { foreach($preferences as $item) UserNotificationPreference::query()->updateOrCreate(['user_id'=>$userId,'type'=>$item['type']],['in_app_enabled'=>$item['in_app_enabled']]); return $this->preferences($userId); }
    private function isEnabled(int $userId,string $type): bool { return (bool)(UserNotificationPreference::query()->where('user_id',$userId)->where('type',$type)->value('in_app_enabled') ?? true); }
    private function safeActionPath(?string $path): ?string { if (!$path || !str_starts_with($path,'/') || str_starts_with($path,'//')) return null; return preg_match('#^/(budgets|recurring-expenses|financial-goals|achievements|reports|financial-intelligence|bills|accounts|cron-report-delivery|transactions|transfers|notifications)(/|$)#',$path) ? $path : null; }
}
