<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\RecordNotification;
use App\Models\Notification;

class NotificationsController extends Controller
{
    public function records (Request $request) {
        // Get only the 30 most recent record notifications
        $recordNotificationsPage = RecordNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'DESC')
            ->limit(30)
            ->get()
            ->values()
            ->toArray();

        // Delete any record notifications older than the 30 most recent
        $oldestKeptId = RecordNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->offset(29)
            ->value('id');

        if ($oldestKeptId) {
            RecordNotification::query()
                ->where('user_id', $request->user()->id)
                ->where('id', '<', $oldestKeptId)
                ->delete();
        }

        $systemNotificationsPage = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'DESC')
            ->paginate(30);

        return Inertia::render('NotificationsView')->with([
            'recordNotificationsPage' => $recordNotificationsPage,
            'systemNotificationsPage' => $systemNotificationsPage,
            'activeTab' => 'records'
        ]);
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
        // Get only the 30 most recent record notifications
        $recordNotificationsPage = RecordNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'DESC')
            ->limit(30)
            ->get()
            ->values()
            ->toArray();

        // Delete any record notifications older than the 30 most recent
        $oldestKeptId = RecordNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->offset(29)
            ->value('id');

        if ($oldestKeptId) {
            RecordNotification::query()
                ->where('user_id', $request->user()->id)
                ->where('id', '<', $oldestKeptId)
                ->delete();
        }

        $systemNotificationsPage = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'DESC')
            ->paginate(30);

        return Inertia::render('NotificationsView')->with([
            'recordNotificationsPage' => $recordNotificationsPage,
            'systemNotificationsPage' => $systemNotificationsPage,
            'activeTab' => 'system'
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
