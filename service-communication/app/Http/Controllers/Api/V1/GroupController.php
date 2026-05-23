<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
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

        $addedNames = [];
        foreach ($request->user_ids as $userId) {
            $participant = ConversationParticipant::firstOrCreate([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
            ], [
                'role' => 'member',
                'last_read_at' => now(),
            ]);

            if ($participant->wasRecentlyCreated) {
                $member = User::find($userId);
                if ($member) {
                    $addedNames[] = $member->prenom . ' ' . $member->nom;
                }
            }
        }

        if (!empty($addedNames)) {
            $systemMessage = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'content' => $user->prenom . ' ' . $user->nom . ' a ajouté ' . implode(', ', $addedNames) . ' au groupe',
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

        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->delete();

        $systemMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'content' => $user->prenom . ' ' . $user->nom . ' a retiré ' . $member->prenom . ' ' . $member->nom . ' du groupe',
            'type' => 'system',
        ]);

        $conversation->update(['last_message_at' => now()]);

        $finalMessage = $systemMessage->load(['user']);

        // Dispatch instant real-time notifications to all remaining participants
        $otherParticipants = $conversation->participants()->where('users.id', '!=', $user->id)->get();
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

        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->delete();

        $conversation->update(['last_message_at' => now()]);

        $finalMessage = $systemMessage->load(['user']);

        // Dispatch instant real-time notifications to all remaining participants
        $otherParticipantIds = $conversation->participantData()
            ->where('user_id', '!=', $user->id)
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

        // Dispatch instant real-time notifications to all participants before deleting
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

        // Delete all related data
        $conversation->participantData()->delete();
        $conversation->messages()->delete();
        $conversation->delete();

        return response()->json(['success' => true, 'message' => 'Groupe supprimé avec succès']);
    }
}

