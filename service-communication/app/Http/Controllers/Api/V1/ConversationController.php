<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\GroupInvitation;
use App\Models\User;
use App\Models\BlockedUser;
<<<<<<< HEAD
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
=======
use App\Models\DeletedConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
>>>>>>> import/master

class ConversationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        $blockedByMe = BlockedUser::where('blocker_id', $user->id)->pluck('blocked_id')->toArray();
        $blockedMe = BlockedUser::where('blocked_id', $user->id)->pluck('blocker_id')->toArray();
        
        $conversations = Conversation::whereHas('participantData', function($query) use ($user) {
            $query->where('user_id', $user->id); // Include all statuses (active, left, removed)
        })
        ->whereDoesntHave('archives', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })
<<<<<<< HEAD
=======
        ->whereDoesntHave('deletions', function($query) use ($user) {
            $query->where('user_id', $user->id)->where('is_hidden', true);
        })
>>>>>>> import/master
        ->with(['lastMessage', 'participants', 'participantData'])
        ->orderBy('last_message_at', 'desc')
        ->select('conversations.*')
        ->selectSub(function($query) use ($user) {
            $query->selectRaw('count(*)')
                ->from('messages')
                ->join('conversation_participants', 'messages.conversation_id', '=', 'conversation_participants.conversation_id')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->where('conversation_participants.user_id', $user->id)
                ->where('conversation_participants.status', 'active')
                ->whereRaw('messages.created_at > coalesce(conversation_participants.last_read_at, conversations.created_at)')
                ->where('messages.user_id', '!=', $user->id);
        }, 'unread_count')
        ->get();
        
        $conversations->transform(function($conversation) use ($user, $blockedByMe, $blockedMe) {
            return $this->formatConversation($conversation, $user, $blockedByMe, $blockedMe);
        });

        return response()->json($conversations);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:private,group',
            'user_ids' => 'required|array',
            'name' => 'nullable|string|required_if:type,group',
        ]);

        $user = Auth::user();

        if ($request->type === 'private') {
            $userIds = array_unique(array_merge($request->user_ids, [$user->id]));
            if (count($userIds) === 2) {
                $existing = Conversation::where('type', 'private')
                    ->whereHas('participantData', function($q) use ($userIds) {
<<<<<<< HEAD
                        $q->whereIn('user_id', $userIds)->where('status', 'active');
=======
                        $q->whereIn('user_id', $userIds);
>>>>>>> import/master
                    }, '=', 2)
                    ->with(['lastMessage', 'participants', 'participantData'])
                    ->first();

                if ($existing) {
                    $blockedByMe = BlockedUser::where('blocker_id', $user->id)->pluck('blocked_id')->toArray();
                    $blockedMe = BlockedUser::where('blocked_id', $user->id)->pluck('blocker_id')->toArray();
                    return response()->json($this->formatConversation($existing, $user, $blockedByMe, $blockedMe));
                }
            }
        }

        $conversation = DB::transaction(function() use ($request, $user) {
            $conversation = Conversation::create([
                'name' => $request->name,
                'type' => $request->type,
                'last_message_at' => now(),
            ]);

            // Add the creator as admin
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'role' => $request->type === 'group' ? 'admin' : 'member',
                'status' => 'active',
                'last_read_at' => now(),
            ]);

            // For private conversations, add the other user directly
            if ($request->type === 'private') {
                foreach ($request->user_ids as $id) {
                    if ($id != $user->id) {
                        ConversationParticipant::create([
                            'conversation_id' => $conversation->id,
                            'user_id' => $id,
                            'role' => 'member',
                            'status' => 'active',
                            'last_read_at' => now(),
                        ]);
                    }
                }
            }
            // For groups, send invitations instead of adding directly
            else {
                foreach ($request->user_ids as $id) {
                    if ($id != $user->id) {
                        // Create or update invitation
                        $invitation = GroupInvitation::updateOrCreate([
                            'conversation_id' => $conversation->id,
                            'user_id' => $id,
                        ], [
                            'invited_by' => $user->id,
                            'status' => 'pending',
                        ]);

                        // Send notification
                        $invitedUser = User::find($id);
                        if ($invitedUser) {
                            try {
<<<<<<< HEAD
                                \Log::info("🔔 Sending GroupInvitationNotification to user {$id} for conversation {$conversation->id}");
                                $invitedUser->notify(new \App\Notifications\GroupInvitationNotification($invitation));
                            } catch (\Exception $e) {
                                \Log::error('❌ Notification error for user ' . $id . ': ' . $e->getMessage());
=======
                                Log::info("🔔 Sending GroupInvitationNotification to user {$id} for conversation {$conversation->id}");
                                $invitedUser->notify(new \App\Notifications\GroupInvitationNotification($invitation));
                            } catch (\Exception $e) {
                                Log::error('❌ Notification error for user ' . $id . ': ' . $e->getMessage());
>>>>>>> import/master
                            }
                        }
                    }
                }
            }

            return $conversation;
        });

        $blockedByMe = BlockedUser::where('blocker_id', $user->id)->pluck('blocked_id')->toArray();
        $blockedMe = BlockedUser::where('blocked_id', $user->id)->pluck('blocker_id')->toArray();

        return response()->json($this->formatConversation($conversation->load(['lastMessage', 'participants', 'participantData']), $user, $blockedByMe, $blockedMe), 201);
    }

    public function show($id)
    {
        $user = Auth::user();
        $blockedByMe = BlockedUser::where('blocker_id', $user->id)->pluck('blocked_id')->toArray();
        $blockedMe = BlockedUser::where('blocked_id', $user->id)->pluck('blocker_id')->toArray();
        $conversation = Conversation::whereHas('participantData', function($query) use ($user) {
            $query->where('user_id', $user->id); // Include all statuses
        })
        ->with(['participants', 'participantData', 'messages' => function($q) {
            $q->latest()->limit(50);
        }])
        ->findOrFail($id);

        return response()->json($this->formatConversation($conversation, $user, $blockedByMe, $blockedMe));
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $conversation = Conversation::whereHas('participantData', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($id);

<<<<<<< HEAD
        // Optional: Only admins can delete group conversations
        if ($conversation->type === 'group') {
            $participant = $conversation->participantData->where('user_id', $user->id)->first();
            if ($participant->role !== 'admin') {
                return response()->json(['message' => 'Only admins can delete group conversations.'], 403);
            }
        }

        $conversation->delete();
=======
        if ($conversation->type === 'group') {
            $participant = $conversation->participantData->where('user_id', $user->id)->first();

            // Admin can hard-delete the group for everyone
            if ($participant && $participant->role === 'admin') {
                $conversation->delete();
                return response()->json(['message' => 'Conversation deleted successfully.']);
            }

            // Non-admin members: soft-delete (hide) for themselves only
            $now = now();
            DeletedConversation::updateOrCreate(
                [
                    'user_id'         => $user->id,
                    'conversation_id' => $conversation->id,
                ],
                [
                    'deleted_at' => $now,
                    'is_hidden'  => true,
                ]
            );

            return response()->json(['message' => 'Conversation deleted successfully.']);
        }

        // Private conversations: soft-delete per user
        $now = now();
        DeletedConversation::updateOrCreate(
            [
                'user_id'         => $user->id,
                'conversation_id' => $conversation->id,
            ],
            [
                'deleted_at' => $now,
                'is_hidden'  => true,
            ]
        );
>>>>>>> import/master

        return response()->json(['message' => 'Conversation deleted successfully.']);
    }

    private function formatConversation($conversation, $user, $blockedByMe = [], $blockedMe = [])
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
        
        $conversation->user_status = $participantInfo->status; // Add user's status to the returned object
            
        if ($conversation->type === 'private') {
            $otherParticipant = $conversation->participants->where('id', '!=', $user->id)->first();
            $conversation->other_user = $otherParticipant;
            if ($otherParticipant) {
                $conversation->is_blocked_by_me = in_array($otherParticipant->id, $blockedByMe);
                $conversation->has_blocked_me = in_array($otherParticipant->id, $blockedMe);
            }
        }
        
        return $conversation;
    }
}
