<?php

namespace App\Http\Controllers;

use App\Events\AllianceChatUpdateEvent;
use App\Models\Alliance;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AllianceChatController extends Controller
{
    public function store(Request $request, Alliance $alliance): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('sendChatMessage', $alliance);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:100'],
        ]);

        $message = trim($validated['message']);

        if ($message === '') {
            throw ValidationException::withMessages([
                'alliance_chat' => 'Message cannot be empty.',
            ]);
        }

        $chatMessage = $alliance->chatMessages()->create([
            'user_id' => $user->id,
            'message' => $message,
        ]);

        try {
            broadcast(new AllianceChatUpdateEvent($chatMessage))->toOthers();
        } catch (BroadcastException $exception) {
            Log::warning('Alliance chat broadcast failed.', [
                'alliance_id' => $alliance->id,
                'chat_message_id' => $chatMessage->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return redirect()->to(url()->previous(route('dashboard')));
    }
}
