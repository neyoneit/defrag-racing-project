<?php

namespace App\Notifications;

use App\Models\AliasSuggestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as LaravelNotification;
use App\Models\Notification;

class AliasSuggestionReceived extends LaravelNotification
{
    use Queueable;

    protected $suggestion;

    /**
     * Create a new notification instance.
     */
    public function __construct(AliasSuggestion $suggestion)
    {
        $this->suggestion = $suggestion;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Store notification in custom database table
     */
    public function toDatabase(object $notifiable): void
    {
        $suggester = $this->suggestion->suggestedBy;

        Notification::create([
            'user_id' => $notifiable->id,
            'type' => 'alias_suggestion',
            'before' => $suggester->name,
            'headline' => 'suggested you add the alias',
            'after' => $this->suggestion->alias,
            'url' => route('profile.index', $notifiable->id),
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'suggestion_id' => $this->suggestion->id,
            'alias' => $this->suggestion->alias,
            'suggested_by' => $this->suggestion->suggested_by_user_id,
        ];
    }
}
