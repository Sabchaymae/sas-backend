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
use Illuminate\Support\Facades\Log;

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
        // with valid IDs and return details about any invalid ones.
        $request->validate([
            'user_ids' => 'required|array',
        ]);

        try {
            $user = Auth::user();

            $conversation = Conversation::where('type', 'group')
                ->whereHas('participantData', function($q) use ($user) {
                    $q->where('user_id', $user->id)->where('role', 'admin');
                })->findOrFail($conversationId);

            $requested = array_values(array_unique(array_map('intval', $request->input('user_ids', []))));
            if (empty($requested)) {
                return response()->json(['message' => 'No user_ids provided or invalid format', 'invalid_ids' => $request->input('user_ids', [])], 422);
            }

            $existingIds = User::whereIn('id', $requested)->pluck('id')->map(function($i){ return (int)$i; })->toArray();
            $invalidIds = array_values(array_diff($requested, $existingIds));

            if (empty($existingIds)) {
                return response()->json(['message' => 'No valid user IDs found', 'invalid_ids' => $invalidIds], 422);
            }

            $invitedCount = 0;
            foreach ($existingIds as $userId) {
                // Skip if already active member
                $isMember = ConversationParticipant::where('conversation_id', $conversation->id)
                    ->where('user_id', $userId)
                    ->where('status', 'active')
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
                    } catch (\Throwable $notifE) {
                        Log::error('Notification error: ' . $notifE->getMessage() . ' in ' . $notifE->getFile() . ':' . $notifE->getLine());
                        Log::error($notifE->getTraceAsString());
                    }
                }

                $invitedCount++;
            }

            return response()->json(['success' => true, 'invited' => $invitedCount, 'invalid_ids' => $invalidIds]);
        } catch (\Throwable $e) {
            Log::error('GroupInvitation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            Log::error($e->getTraceAsString());
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


        // Add (or re-activate) the user in the conversation
        $existingParticipant = ConversationParticipant::where('conversation_id', $invitation->conversation_id)
            ->where('user_id', $user->id)
            ->first();

        $wasActive = $existingParticipant && $existingParticipant->status === 'active';

        $participant = ConversationParticipant::updateOrCreate(
            [
                'conversation_id' => $invitation->conversation_id,
                'user_id'         => $user->id,
            ],
            [
                'role'        => 'member',
                'status'      => 'active',
                'last_read_at' => now(),
            ]
        );

        $conversation = Conversation::find($invitation->conversation_id);

        // Only create a system "joined" message if the participant wasn't already active
        $finalMessage = null;
        if (! $wasActive) {
            $systemMessage = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'content' => $user->prenom . ' ' . $user->nom . ' a rejoint le groupe',
                'type' => 'system',
            ]);

            $conversation->update(['last_message_at' => now()]);
            $finalMessage = $systemMessage->load(['user']);
        }

        // If we created a system message, dispatch notifications and broadcast it
        if ($finalMessage) {
            $otherParticipantIds = $conversation->participantData()
                ->where('user_id', '!=', $user->id)
                ->pluck('user_id');
            $otherParticipants = User::whereIn('id', $otherParticipantIds)->get();
            foreach ($otherParticipants as $participant) {
                try {
                    $participant->notify(new NewMessageNotification($finalMessage));
                } catch (\Throwable $e) {
                    Log::error('NewMessageNotification failed: ' . $e->getMessage());
                    Log::error($e->getTraceAsString());
                }
            }

            try {
                broadcast(new \App\Events\MessageSent($finalMessage))->toOthers();
            } catch (\Throwable $e) {
                Log::error('MessageSent broadcast failed: ' . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
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
