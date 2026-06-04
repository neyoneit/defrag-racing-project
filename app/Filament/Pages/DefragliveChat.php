<?php

namespace App\Filament\Pages;

use App\Models\DefragliveChatMessage;
use App\Models\DefragliveServerState;
use App\Support\Q3Color;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Live DefragLive chat monitor + moderation. A scrollable chat feed (newest
 * first) with Quake colours rendered, not the raw ^codes. Removing a message
 * soft-deletes it, which (via the model's deleted hook) broadcasts the
 * delete_message the extension honours, so it disappears from the overlay too.
 */
class DefragliveChat extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Live Chat';

    protected static ?string $navigationGroup = 'DefragLive';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.defraglive-chat';

    public bool $showRemoved = false;

    // Only the conversational actions belong in the feed (skip serverstate etc).
    private const FEED_ACTIONS = [
        'message', 'command', 'afk_notification', 'afk_help', 'server_record_celebration',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function toggleRemoved(): void
    {
        $this->showRemoved = ! $this->showRemoved;
    }

    public function remove(int $id): void
    {
        $msg = DefragliveChatMessage::find($id);
        if ($msg) {
            $msg->delete(); // soft delete -> broadcasts delete_message to overlays
            Notification::make()->title('Message removed')->success()->send();
        }
    }

    public function restore(int $id): void
    {
        $msg = DefragliveChatMessage::withTrashed()->find($id);
        if ($msg && $msg->trashed()) {
            $msg->restore();
            Notification::make()->title('Message restored')->success()->send();
        }
    }

    protected function getViewData(): array
    {
        $base = $this->showRemoved
            ? DefragliveChatMessage::withTrashed()
            : DefragliveChatMessage::query();

        $messages = $base
            ->whereIn('action', self::FEED_ACTIONS)
            ->with('resolvedUser')
            ->orderByDesc('id')
            ->limit(250)
            ->get()
            ->map(function (DefragliveChatMessage $m) {
                return [
                    'id' => $m->id,
                    'time' => optional($m->created_at)->format('H:i:s'),
                    'action' => $m->action,
                    'author_html' => Q3Color::toHtml($m->author),
                    'content_html' => Q3Color::toHtml($m->content),
                    'trashed' => $m->trashed(),
                    'user' => $m->resolvedUser ? [
                        'id' => $m->resolvedUser->id,
                        'name' => $m->resolvedUser->plain_name ?: $m->resolvedUser->name,
                    ] : null,
                ];
            });

        $state = DefragliveServerState::find(1)?->payload ?? [];

        return [
            'messages' => $messages,
            'showRemoved' => $this->showRemoved,
            'mapname' => $state['mapname'] ?? null,
            'numPlayers' => $state['num_players'] ?? null,
        ];
    }
}
