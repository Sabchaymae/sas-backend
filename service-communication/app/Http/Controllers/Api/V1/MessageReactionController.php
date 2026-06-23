<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Events\MessageReactionUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MessageReactionController extends Controller
{
    /**
     * Get all reactions for a message.
     */
    public function index($conversationId, $messageId)
    {
        $user = Auth::user();
        
        // Verify user has access to the conversation
        $conversation = Conversation::whereHas('participantData', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        // Get the message
        $message = Message::where('conversation_id', $conversationId)
            ->findOrFail($messageId);

        // Return reactions with user info
        $reactions = $message->reactions()
            ->with('user:id,nom,prenom,email')
            ->get()
            ->map(function($reaction) {
                return [
                    'id' => $reaction->id,
                    'emoji' => $reaction->emoji,
                    'user_id' => $reaction->user_id,
                    'user' => [
                        'id' => $reaction->user->id,
                        'nom' => $reaction->user->nom,
                        'prenom' => $reaction->user->prenom,
                    ],
                    'created_at' => $reaction->created_at,
                ];
            });

        return response()->json($reactions);
    }

    /**
     * Toggle a reaction on a message.
     * If the user has already reacted with the same emoji, remove it.
     * If the user has reacted with a different emoji, update it.
     * If the user hasn't reacted, add the reaction.
     */
    public function toggle(Request $request, $conversationId, $messageId)
    {
        $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        $user = Auth::user();

        // Verify user is an active participant
        $conversation = Conversation::whereHas('participantData', function($query) use ($user) {
            $query->where('user_id', $user->id)->where('status', 'active');
        })->findOrFail($conversationId);

        // Get the message
        $message = Message::where('conversation_id', $conversationId)
            ->findOrFail($messageId);

        // Check if user already has a reaction on this message
        $existingReaction = MessageReaction::where('message_id', $messageId)
            ->where('user_id', $user->id)
            ->first();

        $action = null;
        $emoji = $request->emoji;

        if ($existingReaction) {
            if ($existingReaction->emoji === $emoji) {
                // Same emoji - remove the reaction
                $existingReaction->delete();
                $action = 'removed';
            } else {
                // Different emoji - update the reaction
                $existingReaction->update(['emoji' => $emoji]);
                $action = 'updated';
            }
        } else {
            // No existing reaction - add new one
            MessageReaction::create([
                'message_id' => $messageId,
                'user_id' => $user->id,
                'emoji' => $emoji,
            ]);
            $action = 'added';
        }

        // Broadcast the reaction update to the conversation channel
        try {
            Log::info('📤 BROADCASTING MessageReactionUpdated', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'action' => $action,
                'emoji' => $emoji,
            ]);
            broadcast(new MessageReactionUpdated($message, $action, $user->id, $emoji))->toOthers();
            Log::info('✅ MessageReactionUpdated broadcast SUCCESS');
        } catch (\Exception $e) {
            Log::error('❌ MessageReactionUpdated broadcast FAILED', ['error' => $e->getMessage()]);
        }

        // Notify the message owner (only for 'added'/'updated', not 'removed', not self-reaction)
        if (in_array($action, ['added', 'updated']) && $message->user_id !== $user->id) {
            $messageOwner = \App\Models\User::find($message->user_id);
            if ($messageOwner) {
                try {
                    $messageOwner->notify(new \App\Notifications\MessageReactionNotification(
                        $message,
                        $user,
                        $emoji,
                        $action
                    ));
                    Log::info('🔔 MessageReactionNotification sent to user ' . $messageOwner->id);
                } catch (\Exception $e) {
                    Log::error('❌ MessageReactionNotification FAILED', ['error' => $e->getMessage()]);
                }
            }
        }

        // Return updated reactions
        $message->load(['reactions.user']);
        $reactions = $message->reactions->map(function($reaction) {
            return [
                'id' => $reaction->id,
                'emoji' => $reaction->emoji,
                'user_id' => $reaction->user_id,
                'user' => [
                    'id' => $reaction->user->id,
                    'nom' => $reaction->user->nom,
                    'prenom' => $reaction->user->prenom,
                ],
                'created_at' => $reaction->created_at,
            ];
        });

        return response()->json([
            'action' => $action,
            'reactions' => $reactions,
        ]);
    }

    /**
     * Remove a specific reaction (admin or owner only).
     */
    public function destroy($conversationId, $messageId, $reactionId)
    {
        $user = Auth::user();

        // Verify user has access to conversation
        $conversation = Conversation::whereHas('participantData', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        // Get the message
        $message = Message::where('conversation_id', $conversationId)
            ->findOrFail($messageId);

        // Find the reaction
        $reaction = MessageReaction::where('message_id', $messageId)
            ->findOrFail($reactionId);

        // Only the reaction owner or message owner can delete
        if ($reaction->user_id !== $user->id && $message->user_id !== $user->id) {
            return response()->json([
                'message' => 'Vous n\'avez pas la permission de supprimer cette réaction.'
            ], 403);
        }

        $emoji = $reaction->emoji;
        $reactionUserId = $reaction->user_id;
        $reaction->delete();

        // Broadcast the reaction removal
        try {
            broadcast(new MessageReactionUpdated($message, 'removed', $reactionUserId, $emoji))->toOthers();
        } catch (\Exception $e) {
            Log::error('❌ MessageReactionUpdated broadcast FAILED', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Réaction supprimée avec succès'
        ]);
    }
}
