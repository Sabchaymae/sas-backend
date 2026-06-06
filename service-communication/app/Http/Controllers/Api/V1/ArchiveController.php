<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArchivedConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ArchiveController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $archived = ArchivedConversation::where('user_id', $user->id)
            ->with(['conversation' => function($query) use ($user) {
                $query->with(['lastMessage', 'participants', 'participantData'])
                    ->select('conversations.*')
                    ->selectSub(function($query) use ($user) {
                        $query->selectRaw('count(*)')
                            ->from('messages')
                            ->join('conversation_participants', 'messages.conversation_id', '=', 'conversation_participants.conversation_id')
                            ->whereColumn('messages.conversation_id', 'conversations.id')
                            ->where('conversation_participants.user_id', $user->id)
                            ->whereRaw('messages.created_at > coalesce(conversation_participants.last_read_at, conversations.created_at)')
                            ->where('messages.user_id', '!=', $user->id);
                    }, 'unread_count');
            }])
            ->get();

        $formatted = $archived->map(function($archive) use ($user) {
            $conversation = $archive->conversation;
            if ($conversation) {
                $conversation = $this->formatConversation($conversation, $user);
                $archive->conversation = $conversation;
            }
            return $archive;
        });

        return response()->json($formatted);
    }

    public function archive($conversationId)
    {
        $user = Auth::user();
        ArchivedConversation::firstOrCreate([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
        ]);

        return response()->json(['success' => true]);
    }

    public function unarchive($conversationId)
    {
        $user = Auth::user();
        ArchivedConversation::where('user_id', $user->id)
            ->where('conversation_id', $conversationId)
            ->delete();

        return response()->json(['success' => true]);
    }

    private function formatConversation($conversation, $user)
    {
        $participantInfo = $conversation->participantData->where('user_id', $user->id)->first();
        
        if (!isset($conversation->unread_count)) {
            $conversation->unread_count = $conversation->messages()
                ->where('created_at', '>', $participantInfo->last_read_at ?? $conversation->created_at)
                ->where('user_id', '!=', $user->id)
                ->count();
        } else {
            $conversation->unread_count = (int) $conversation->unread_count;
        }
            
        if ($conversation->type === 'private') {
            $otherParticipant = $conversation->participants->where('id', '!=', $user->id)->first();
            $conversation->other_user = $otherParticipant;
        }
        
        return $conversation;
    }
}
