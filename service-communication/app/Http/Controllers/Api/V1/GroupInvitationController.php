<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\GroupInvitation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\GroupInvitationNotification;
use App\Notifications\NewMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupInvitationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $invitations = GroupInvitation::with(['conversation', 'inviter'])
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->get();

        return response()->json($invitations);
    }

    public function store(Request $request, $conversationId)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        try {
            $user = Auth::user();

            $conversation = Conversation::where('type', 'group')
                ->whereHas('participantData', function($q) use ($user) {
                    $q->where('user_id', $user->id)->where('role', 'admin');
                })->findOrFail($conversationId);

            foreach ($request->user_ids as $userId) {
                $isMember = ConversationParticipant::where('conversation_id', $conversation->id)
                    ->where('user_id', $userId)
                    ->exists();

                if ($isMember) continue;

                $invitation = GroupInvitation::updateOrCreate([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                ], [
                    'invited_by' => $user->id,
                    'status' => 'pending',
                ]);

                $invitedUser = User::find($userId);
                if ($invitedUser) {
                    try {
                        $invitedUser->notify(new GroupInvitationNotification($invitation));
                    } catch (\Exception $notifE) {
                        \Log::error('Notification error: ' . $notifE->getMessage() . ' in ' . $notifE->getFile() . ':' . $notifE->getLine());
                    }
                }
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('GroupInvitation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function accept($id)
    {
        $user = Auth::user();
        $invitation = GroupInvitation::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $invitation->update(['status' => 'accepted']);

        // Add user to the conversation
        ConversationParticipant::firstOrCreate([
            'conversation_id' => $invitation->conversation_id,
            'user_id' => $user->id,
        ], [
            'role' => 'member',
            'status' => 'active',
            'last_read_at' => now(),
        ]);

        $conversation = Conversation::find($invitation->conversation_id);

        // Create the system message "X a rejoint le groupe"
        $systemMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'content' => $user->prenom . ' ' . $user->nom . ' a rejoint le groupe',
            'type' => 'system',
        ]);

        $conversation->update(['last_message_at' => now()]);
        $finalMessage = $systemMessage->load(['user']);

        // Dispatch instant real-time notifications to all other participants
        $otherParticipantIds = $conversation->participantData()
            ->where('user_id', '!=', $user->id)
            ->pluck('user_id');
        $otherParticipants = User::whereIn('id', $otherParticipantIds)->get();
        foreach ($otherParticipants as $participant) {
            try {
                $participant->notify(new NewMessageNotification($systemMessage));
            } catch (\Exception $e) {
                // Ignore notification errors
            }
        }

        try {
            broadcast(new \App\Events\MessageSent($finalMessage))->toOthers();
        } catch (\Exception $e) {
            // Ignore broadcast errors
        }

        return response()->json(['success' => true]);
    }

    public function reject($id)
    {
        $user = Auth::user();
        $invitation = GroupInvitation::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $invitation->update(['status' => 'rejected']);

        return response()->json(['success' => true]);
    }
}
