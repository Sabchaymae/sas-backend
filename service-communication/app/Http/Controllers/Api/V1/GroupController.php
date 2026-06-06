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

class GroupController extends Controller
{
    public function addMembers(Request $request, $conversationId)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $user = Auth::user();
        $conversation = Conversation::where('type', 'group')
            ->whereHas('participantData', function($q) use ($user) {
                $q->where('user_id', $user->id)->where('role', 'admin');
            })->findOrFail($conversationId);

        $invitedNames = [];
        foreach ($request->user_ids as $userId) {
            // Check if user is already an active participant
            $isMember = ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->exists();

            if (!$isMember) {
                // Create invitation
                $invitation = GroupInvitation::updateOrCreate([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                ], [
                    'invited_by' => $user->id,
                    'status' => 'pending',
                ]);

                $invitedUser = User::find($userId);
                if ($invitedUser) {
                    $invitedNames[] = $invitedUser->prenom . ' ' . $invitedUser->nom;
                    try {
                        $invitedUser->notify(new GroupInvitationNotification($invitation));
                    } catch (\Exception $e) {
                        \Log::error('Notification error: ' . $e->getMessage());
                    }
                }
            }
        }

        if (!empty($invitedNames)) {
            $systemMessage = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'content' => $user->prenom . ' ' . $user->nom . ' a invité ' . implode(', ', $invitedNames) . ' au groupe',
                'type' => 'system',
            ]);

            $conversation->update(['last_message_at' => now()]);

            $finalMessage = $systemMessage->load(['user']);

            // Dispatch instant real-time notifications to all other active participants
            $otherParticipantIds = $conversation->participantData()
                ->where('user_id', '!=', $user->id)
                ->where('status', 'active')
                ->pluck('user_id');
            $otherParticipants = User::whereIn('id', $otherParticipantIds)->get();
            foreach ($otherParticipants as $participant) {
                $participant->notify(new NewMessageNotification($systemMessage));
            }

            broadcast(new \App\Events\MessageSent($finalMessage))->toOthers();
        }

        return response()->json(['success' => true]);
    }

    public function removeMember($conversationId, $userId)
    {
        $user = Auth::user();
        $conversation = Conversation::where('type', 'group')
            ->whereHas('participantData', function($q) use ($user) {
                $q->where('user_id', $user->id)->where('role', 'admin');
            })->findOrFail($conversationId);

        if ($userId == $user->id) {
            return response()->json(['error' => 'Cannot remove yourself as admin'], 400);
        }

        $member = User::findOrFail($userId);

        // Update status instead of deleting
        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->update(['status' => 'removed']);

        $systemMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'content' => $user->prenom . ' ' . $user->nom . ' a retiré ' . $member->prenom . ' ' . $member->nom . ' du groupe',
            'type' => 'system',
        ]);

        $conversation->update(['last_message_at' => now()]);

        $finalMessage = $systemMessage->load(['user']);

        // Dispatch instant real-time notifications to all remaining active participants
        $otherParticipantIds = $conversation->participantData()
            ->where('user_id', '!=', $user->id)
            ->where('status', 'active')
            ->pluck('user_id');
        $otherParticipants = User::whereIn('id', $otherParticipantIds)->get();
        foreach ($otherParticipants as $participant) {
            $participant->notify(new NewMessageNotification($systemMessage));
        }

        broadcast(new \App\Events\MessageSent($finalMessage))->toOthers();

        return response()->json(['success' => true]);
    }

    public function leave($conversationId)
    {
        $user = Auth::user();
        $conversation = Conversation::where('type', 'group')
            ->whereHas('participantData', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->findOrFail($conversationId);

        $systemMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'content' => $user->prenom . ' ' . $user->nom . ' a quitté le groupe',
            'type' => 'system',
        ]);

        // Update status instead of deleting
        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['status' => 'left']);

        $conversation->update(['last_message_at' => now()]);

        $finalMessage = $systemMessage->load(['user']);

        // Dispatch instant real-time notifications to all remaining participants
        $otherParticipantIds = $conversation->participantData()
            ->where('user_id', '!=', $user->id)
            ->where('status', 'active')
            ->pluck('user_id');
        $otherParticipants = User::whereIn('id', $otherParticipantIds)->get();
        foreach ($otherParticipants as $participant) {
            $participant->notify(new NewMessageNotification($systemMessage));
        }

        broadcast(new \App\Events\MessageSent($finalMessage))->toOthers();

        return response()->json(['success' => true]);
    }

    public function deleteGroup($conversationId)
    {
        $user = Auth::user();
        $conversation = Conversation::where('type', 'group')
            ->whereHas('participantData', function($q) use ($user) {
                $q->where('user_id', $user->id)->where('role', 'admin');
            })->findOrFail($conversationId);

        $systemMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'content' => $user->prenom . ' ' . $user->nom . ' a supprimé le groupe',
            'type' => 'system',
        ]);

        $conversation->update(['last_message_at' => now()]);

        $finalMessage = $systemMessage->load(['user']);

        // Set admin's status to removed
        $conversation->participantData()
            ->where('user_id', $user->id)
            ->update(['status' => 'removed']);

        // Set all other participants' status to group_deleted
        $conversation->participantData()
            ->where('user_id', '!=', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'group_deleted']);

        // Dispatch instant real-time notifications to all other participants
        $otherParticipantIds = $conversation->participantData()
            ->where('user_id', '!=', $user->id)
            ->pluck('user_id');
        $otherParticipants = User::whereIn('id', $otherParticipantIds)->get();
        foreach ($otherParticipants as $participant) {
            try {
                $participant->notify(new NewMessageNotification($systemMessage));
            } catch (\Exception $e) {
                // Ignore
            }
        }

        try {
            broadcast(new \App\Events\MessageSent($finalMessage))->toOthers();
        } catch (\Exception $e) {
            // Ignore
        }

        return response()->json(['success' => true, 'message' => 'Groupe supprimé avec succès']);
    }
}

