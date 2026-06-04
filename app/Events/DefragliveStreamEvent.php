<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One live DefragLive stream item, broadcast to the public `defraglive`
 * channel over Reverb. Carries the exact broadcast object the Python bridge
 * used to fan out over its raw WebSocket ({action, message:{...}}), so the new
 * extension can listen for `.stream` and switch on payload.action - a direct
 * parity replacement for the bridge's onConsoleMessage relay.
 *
 * ShouldBroadcastNow (not queued) so chat is instant and the realtime path
 * doesn't depend on a queue worker draining.
 */
class DefragliveStreamEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $payload)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('defraglive');
    }

    public function broadcastAs(): string
    {
        return 'stream';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
