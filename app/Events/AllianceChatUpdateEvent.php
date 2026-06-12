<?php

namespace App\Events;

use App\Models\AllianceChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AllianceChatUpdateEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly AllianceChatMessage $message) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('alliance.'.$this->message->alliance_id.'.chat'),
        ];
    }

    /**
     * @return array{messageId: int, allianceId: int}
     */
    public function broadcastWith(): array
    {
        return [
            'messageId' => $this->message->id,
            'allianceId' => $this->message->alliance_id,
        ];
    }
}
