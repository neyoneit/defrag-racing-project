<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

use App\Models\RecordNotification;
use App\Models\Notification;

class NotificationsController extends Controller
{
    private const VALID_RECORD_FILTERS = ['all', 'beaten', 'worldrecords'];

    public function records (Request $request) {
        return $this->renderInbox($request, 'records');
    }

    public function recordsclear (Request $request) {
        $notifications = RecordNotification::where('user_id', $request->user()->id)->update([
            'read'  =>  true
        ]);
    }

    public function recordsMarkAllUnread (Request $request) {
        RecordNotification::where('user_id', $request->user()->id)->update([
            'read'  =>  false
        ]);
        return response()->json(['success' => true]);
    }

    public function recordsToggle (Request $request, $id) {
        $notification = RecordNotification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->read = !$notification->read;
        $notification->save();

        return response()->json(['success' => true, 'read' => $notification->read]);
    }

    public function system (Request $request) {
        return $this->renderInbox($request, 'system');
    }

    private function renderInbox(Request $request, string $activeTab)
    {
        $userId = $request->user()->id;

        $recordFilter = $request->query('record_filter', 'all');
        if (! in_array($recordFilter, self::VALID_RECORD_FILTERS, true)) {
            $recordFilter = 'all';
        }

        $query = RecordNotification::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC');

        if ($recordFilter === 'beaten') {
            $query->where('worldrecord', 0);
        } elseif ($recordFilter === 'worldrecords') {
            $query->where('worldrecord', 1);
        }

        $recordNotificationsPage = $query->paginate(20, ['*'], 'records_page')->withQueryString();

        $systemNotificationsPage = Notification::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->paginate(20, ['*'], 'system_page');

        // Per-tab unread counts in a single aggregation query (covered by
        // the (user_id, worldrecord, created_at) index).
        $counts = DB::table('record_notifications')
            ->selectRaw('
                SUM(CASE WHEN `read` = 0 THEN 1 ELSE 0 END) AS unread_all,
                SUM(CASE WHEN `read` = 0 AND worldrecord = 0 THEN 1 ELSE 0 END) AS unread_beaten,
                SUM(CASE WHEN `read` = 0 AND worldrecord = 1 THEN 1 ELSE 0 END) AS unread_worldrecords,
                COUNT(*) AS total_all
            ')
            ->where('user_id', $userId)
            ->first();

        $systemUnread = Notification::where('user_id', $userId)->where('read', 0)->count();

        return Inertia::render('NotificationsView')->with([
            'recordNotificationsPage' => $recordNotificationsPage,
            'systemNotificationsPage' => $systemNotificationsPage,
            'activeTab' => $activeTab,
            'recordFilter' => $recordFilter,
            'recordCounts' => [
                'unread_all'          => (int) ($counts->unread_all ?? 0),
                'unread_beaten'       => (int) ($counts->unread_beaten ?? 0),
                'unread_worldrecords' => (int) ($counts->unread_worldrecords ?? 0),
                'total'               => (int) ($counts->total_all ?? 0),
            ],
            'systemUnreadCount' => $systemUnread,
        ]);
    }

    public function systemclear (Request $request) {
        $notifications = Notification::where('user_id', $request->user()->id)->update([
            'read'  =>  true
        ]);
    }

    public function systemMarkAllUnread (Request $request) {
        Notification::where('user_id', $request->user()->id)->update([
            'read'  =>  false
        ]);
        return response()->json(['success' => true]);
    }

    public function systemToggle (Request $request, $id) {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->read = !$notification->read;
        $notification->save();

        return response()->json(['success' => true, 'read' => $notification->read]);
    }
}
