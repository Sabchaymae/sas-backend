<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Notifications\NewMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function index($conversationId)
    {
        $user = Auth::user();
        $conversation = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        $messages = $conversation->messages()
            ->with(['attachments', 'user'])
            ->latest()
            ->paginate(50);

        return response()->json($messages);
    }

    public function store(Request $request, $conversationId)
    {
        $request->validate([
            'content' => 'nullable|string|required_without:files',
            'files.*' => 'nullable|file|max:10240', // 10MB limit
            'type' => 'nullable|in:text,file,system',
        ]);

        $user = Auth::user();
        $conversation = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        if ($conversation->type === 'private') {
            $otherParticipant = $conversation->participants->where('id', '!=', $user->id)->first();
            if ($otherParticipant) {
                $isBlocked = \App\Models\BlockedUser::where(function($query) use ($user, $otherParticipant) {
                    $query->where('blocker_id', $user->id)->where('blocked_id', $otherParticipant->id)
                          ->orWhere('blocker_id', $otherParticipant->id)->where('blocked_id', $user->id);
                })->exists();

                if ($isBlocked) {
                    return response()->json(['message' => 'Impossible d\'envoyer un message. La conversation est bloquée.'], 403);
                }
            }
        }

        return DB::transaction(function() use ($request, $conversation, $user) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'content' => $request->content,
                'type' => $request->hasFile('files') ? 'file' : ($request->type ?? 'text'),
            ]);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('attachments/' . $conversation->id, 'public');
                    MessageAttachment::create([
                        'message_id' => $message->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            $conversation->update(['last_message_at' => now()]);
            
            // Update last_read_at for the sender
            $conversation->participantData()
                ->where('user_id', $user->id)
                ->update(['last_read_at' => now()]);

            $finalMessage = $message->load(['attachments', 'user']);
            
            // Dispatch instant real-time notifications to all other participants
            $otherParticipants = $conversation->participants()->where('users.id', '!=', $user->id)->get();
            foreach ($otherParticipants as $participant) {
                try {
                    $participant->notify(new NewMessageNotification($message));
                } catch (\Exception $e) {
                    // Ignore notification errors
                }
            }
            
            try {
                Log::info('📤 BROADCASTING MessageSent', [
                    'message_id' => $finalMessage->id,
                    'conversation_id' => $finalMessage->conversation_id,
                    'channel' => 'chat.' . $finalMessage->conversation_id
                ]);
                broadcast(new \App\Events\MessageSent($finalMessage))->toOthers();
                Log::info('✅ MessageSent broadcast SUCCESS');
            } catch (\Exception $e) {
                Log::error('❌ MessageSent broadcast FAILED', ['error' => $e->getMessage()]);
            }

            return response()->json($finalMessage, 201);
        });
    }

    public function markAsRead($conversationId)
    {
        $user = Auth::user();
        $conversation = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        $conversation->participantData()
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        try {
            broadcast(new \App\Events\ConversationRead($conversationId, $user->id))->toOthers();
        } catch (\Exception $e) {
            // Ignore broadcast errors
        }

        return response()->json(['success' => true]);
    }
}
