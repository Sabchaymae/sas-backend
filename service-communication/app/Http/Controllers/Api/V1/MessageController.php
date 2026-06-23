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
        $conversation = Conversation::whereHas('participantData', function($query) use ($user) {
<<<<<<< HEAD
            $query->where('user_id', $user->id); // Allow all statuses to read messages
        })->findOrFail($conversationId);

        $messages = $conversation->messages()
            ->with(['attachments', 'user'])
=======
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        // Si l'utilisateur a supprimé cette conversation, ne montrer que
        // les messages postérieurs à la date de suppression (horizon).
        $deletionHorizon = \App\Models\DeletedConversation::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->value('deleted_at');

        $messages = $conversation->messages()
            ->withTrashed()
            ->with(['attachments', 'user', 'reactions.user'])
            ->when($deletionHorizon, fn($q) => $q->where('created_at', '>', $deletionHorizon))
>>>>>>> import/master
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
        $conversation = Conversation::whereHas('participantData', function($query) use ($user) {
            $query->where('user_id', $user->id)->where('status', 'active');
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
                    $path = $file->store('chat', 'public');
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

<<<<<<< HEAD
            $finalMessage = $message->load(['attachments', 'user']);
=======
            // Si un participant avait soft-supprimé cette conversation,
            // on la marque is_hidden=false pour qu'elle réapparaisse avec
            // uniquement les nouveaux messages (l'horizon deleted_at est conservé).
            \App\Models\DeletedConversation::where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $user->id)
                ->where('is_hidden', true)
                ->update(['is_hidden' => false]);

            $finalMessage = $message->load(['attachments', 'user', 'reactions.user']);
>>>>>>> import/master
            
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
        $conversation = Conversation::whereHas('participantData', function($query) use ($user) {
            $query->where('user_id', $user->id); // Allow all statuses
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

    public function update(Request $request, $conversationId, $messageId)
    {
        $request->validate([
            'content' => 'nullable|string|required_without:files',
            'files.*' => 'nullable|file|max:10240',
        ]);

        $user = Auth::user();

        // Verify user is active participant
        $conversation = Conversation::whereHas('participantData', function($query) use ($user) {
            $query->where('user_id', $user->id)->where('status', 'active');
        })->findOrFail($conversationId);

        // Find the message and verify ownership
        $message = Message::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->findOrFail($messageId);

        if ($message->type === 'system') {
            return response()->json(['message' => 'Vous ne pouvez pas modifier un message système.'], 403);
        }

        return DB::transaction(function() use ($request, $message) {
            // Update message content
            $message->update([
                'content' => $request->content,
                'is_edited' => true,
            ]);

            // Handle attachments if any
            if ($request->hasFile('files')) {
                // Delete old attachments first
                foreach ($message->attachments as $attachment) {
                    Storage::disk('public')->delete($attachment->file_path);
                    $attachment->delete();
                }

                // Add new attachments
                foreach ($request->file('files') as $file) {
                    $path = $file->store('chat', 'public');
                    MessageAttachment::create([
                        'message_id' => $message->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

<<<<<<< HEAD
            $finalMessage = $message->load(['attachments', 'user']);
=======
            $finalMessage = $message->load(['attachments', 'user', 'reactions.user']);
>>>>>>> import/master

            try {
                Log::info('📤 BROADCASTING MessageUpdated', [
                    'message_id' => $finalMessage->id,
                    'conversation_id' => $finalMessage->conversation_id,
                    'channel' => 'chat.' . $finalMessage->conversation_id
                ]);
                broadcast(new \App\Events\MessageSent($finalMessage))->toOthers();
            } catch (\Exception $e) {
                Log::error('❌ MessageUpdated broadcast FAILED', ['error' => $e->getMessage()]);
            }

            return response()->json($finalMessage);
        });
    }

    public function destroy($conversationId, $messageId)
    {
        $user = Auth::user();

<<<<<<< HEAD
        // Verify user is active participant
=======
>>>>>>> import/master
        $conversation = Conversation::whereHas('participantData', function($query) use ($user) {
            $query->where('user_id', $user->id)->where('status', 'active');
        })->findOrFail($conversationId);

<<<<<<< HEAD
        // Find the message
=======
>>>>>>> import/master
        $message = Message::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->findOrFail($messageId);

        if ($message->type === 'system') {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer un message système.'], 403);
        }

        DB::transaction(function() use ($message) {
<<<<<<< HEAD
            // Delete attachments
=======
            // Supprimer les fichiers du storage
>>>>>>> import/master
            foreach ($message->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
                $attachment->delete();
            }

<<<<<<< HEAD
            // Soft delete the message
            $message->delete();

            $finalMessage = $message->load(['attachments', 'user']);

            try {
                Log::info('📤 BROADCASTING MessageDeleted', [
                    'message_id' => $finalMessage->id,
                    'conversation_id' => $finalMessage->conversation_id,
                    'channel' => 'chat.' . $finalMessage->conversation_id
                ]);
                broadcast(new \App\Events\MessageDeleted($finalMessage))->toOthers();
=======
            // Vider le contenu et marquer comme supprimé AVANT le soft-delete
            // pour que le broadcast porte les bonnes données
            $message->content = null;
            $message->is_deleted = true;
            $message->save();

            // Soft delete
            $message->delete();

            // Recharger pour le broadcast (deleted_at sera présent)
            $message->refresh();
            $deletedMessage = $message->load(['user']);

            try {
                Log::info('📤 BROADCASTING MessageDeleted', [
                    'message_id' => $deletedMessage->id,
                    'conversation_id' => $deletedMessage->conversation_id,
                ]);
                broadcast(new \App\Events\MessageDeleted($deletedMessage))->toOthers();
>>>>>>> import/master
            } catch (\Exception $e) {
                Log::error('❌ MessageDeleted broadcast FAILED', ['error' => $e->getMessage()]);
            }
        });

<<<<<<< HEAD
        return response()->json(['success' => true, 'message' => 'Message supprimé avec succès']);
=======
        return response()->json([
            'success' => true,
            'message_id' => $messageId,
            'is_deleted' => true,
        ]);
>>>>>>> import/master
    }
}
