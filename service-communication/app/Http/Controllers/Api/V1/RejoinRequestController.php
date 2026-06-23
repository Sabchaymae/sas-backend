<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\RejoinRequest;
use App\Models\User;
use App\Notifications\RejoinRequestNotification;
use App\Notifications\RejoinRequestResponseNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class RejoinRequestController extends Controller
{
    // ── POST /v1/conversations/{id}/rejoin-request ────────────────────────────
    // User who left sends a request to the group admin
    public function store(int $conversationId): JsonResponse
    {
        $user = Auth::user();

        $conversation = Conversation::where('type', 'group')->findOrFail($conversationId);

        // Must have previously been a member (left or removed)
        $participant = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->whereIn('status', ['left', 'removed'])
            ->first();

        if (!$participant) {
            return response()->json(['message' => 'Vous n\'avez pas quitté ce groupe.'], 403);
        }

        // Upsert: if there's already a pending request keep it, otherwise create/reset
        $rejoinRequest = RejoinRequest::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$rejoinRequest) {
            // Create a fresh pending request (previous accepted/rejected ones are kept for history)
            $rejoinRequest = RejoinRequest::create([
                'conversation_id' => $conversationId,
                'user_id'         => $user->id,
                'status'          => 'pending',
            ]);
        }

        $rejoinRequest->load(['user', 'conversation']);

        // Notify the group admin(s)
        $admins = $conversation->participantData()
            ->where('role', 'admin')
            ->where('status', 'active')
            ->pluck('user_id');

        User::whereIn('id', $admins)->each(function ($admin) use ($rejoinRequest) {
            try {
                $admin->notify(new RejoinRequestNotification($rejoinRequest));
                Log::info("🔔 RejoinRequest sent to admin {$admin->id} for conversation {$rejoinRequest->conversation_id}");
            } catch (\Exception $e) {
                Log::error('❌ RejoinRequestNotification failed: ' . $e->getMessage());
            }
        });

        return response()->json([
            'id'              => $rejoinRequest->id,
            'conversation_id' => $rejoinRequest->conversation_id,
            'status'          => $rejoinRequest->status,
        ], 201);
    }

    // ── GET /v1/rejoin-requests ───────────────────────────────────────────────
    // Admin fetches pending requests for their groups
    public function index(): JsonResponse
    {
        $user = Auth::user();

        // Groups where this user is admin
        $adminGroupIds = ConversationParticipant::where('user_id', $user->id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->pluck('conversation_id');

        $requests = RejoinRequest::with(['user', 'conversation'])
            ->whereIn('conversation_id', $adminGroupIds)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => [
                'id'               => $r->id,
                'conversation_id'  => $r->conversation_id,
                'conversation_name'=> $r->conversation->name,
                'user_id'          => $r->user_id,
                'user_name'        => trim("{$r->user->prenom} {$r->user->nom}"),
                'status'           => $r->status,
                'created_at'       => $r->created_at->toISOString(),
            ]);

        return response()->json($requests);
    }

    // ── POST /v1/rejoin-requests/{id}/accept ─────────────────────────────────
    public function accept(int $id): JsonResponse
    {
        $admin = Auth::user();

        $rejoinRequest = RejoinRequest::with(['user', 'conversation'])
            ->where('status', 'pending')
            ->findOrFail($id);

        // Verify the responder is admin of this group
        $isAdmin = ConversationParticipant::where('conversation_id', $rejoinRequest->conversation_id)
            ->where('user_id', $admin->id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->exists();

        if (!$isAdmin) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        // Attempt to mark request accepted, but guard against races where an "accepted" row already exists
        $alreadyAccepted = RejoinRequest::where('conversation_id', $rejoinRequest->conversation_id)
            ->where('user_id', $rejoinRequest->user_id)
            ->where('status', 'accepted')
            ->exists();

        if (! $alreadyAccepted) {
            try {
                $rejoinRequest->update(['status' => 'accepted', 'admin_id' => $admin->id]);
            } catch (\Illuminate\Database\QueryException $qe) {
                // If unique constraint triggered (another accept raced and created an accepted row), treat as already accepted
                if ((int) $qe->getCode() === 23000) {
                    Log::warning('RejoinRequest accept race detected, treating as already accepted: ' . $qe->getMessage());
                    $alreadyAccepted = true;
                } else {
                    throw $qe;
                }
            }
        } else {
            // ensure admin_id is set on the pending request for audit, but avoid changing status to accepted (would violate unique constraint)
            try {
                $rejoinRequest->update(['admin_id' => $admin->id]);
            } catch (\Throwable $ex) {
                // non-fatal
            }
        }

        $rejoinRequest->load('admin');

        // Re-activate or create the participant row
        $participant = ConversationParticipant::updateOrCreate(
            [
                'conversation_id' => $rejoinRequest->conversation_id,
                'user_id'         => $rejoinRequest->user_id,
            ],
            [
                'role'         => 'member',
                'status'       => 'active',
                'last_read_at' => now(),
            ]
        );

        // Only send a system "joined" message if the participant was not already active
        $sendSystemMessage = ($participant->wasRecentlyCreated || $participant->status !== 'active' || ! $alreadyAccepted);

        if ($sendSystemMessage) {
            $conversation = $rejoinRequest->conversation;
            $userName     = trim("{$rejoinRequest->user->prenom} {$rejoinRequest->user->nom}");
            $systemMessage = Message::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $rejoinRequest->user_id,
                'content'         => "{$userName} a rejoint le groupe",
                'type'            => 'system',
            ]);
            $conversation->update(['last_message_at' => now()]);

            try {
                broadcast(new \App\Events\MessageSent($systemMessage->load('user')))->toOthers();
            } catch (\Exception $e) {
                Log::error('❌ MessageSent broadcast failed: ' . $e->getMessage());
            }
        }

        // Notify the requester
        try {
            $rejoinRequest->user->notify(new RejoinRequestResponseNotification($rejoinRequest));
        } catch (\Exception $e) {
            Log::error('❌ RejoinRequestResponseNotification failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'status' => 'accepted']);
    }

    // ── POST /v1/rejoin-requests/{id}/reject ──────────────────────────────────
    public function reject(int $id): JsonResponse
    {
        $admin = Auth::user();

        $rejoinRequest = RejoinRequest::with(['user', 'conversation'])
            ->where('status', 'pending')
            ->findOrFail($id);

        $isAdmin = ConversationParticipant::where('conversation_id', $rejoinRequest->conversation_id)
            ->where('user_id', $admin->id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->exists();

        if (!$isAdmin) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        // Guard against races / duplicate-status unique constraint.
        $alreadyRejected = RejoinRequest::where('conversation_id', $rejoinRequest->conversation_id)
            ->where('user_id', $rejoinRequest->user_id)
            ->where('status', 'rejected')
            ->exists();

        if (! $alreadyRejected) {
            try {
                $rejoinRequest->update(['status' => 'rejected', 'admin_id' => $admin->id]);
            } catch (QueryException $qe) {
                // If unique constraint triggered (another reject raced), treat as already rejected
                if ((int) $qe->getCode() === 23000) {
                    Log::warning('RejoinRequest reject race detected, treating as already rejected: ' . $qe->getMessage());
                    $alreadyRejected = true;
                } else {
                    throw $qe;
                }
            }
        } else {
            // ensure admin_id is set for audit if possible
            try {
                $rejoinRequest->update(['admin_id' => $admin->id]);
            } catch (\Throwable $ex) {
                // non-fatal
            }
        }

        $rejoinRequest->load('admin');

        // Notify the requester (best-effort)
        try {
            $rejoinRequest->user->notify(new RejoinRequestResponseNotification($rejoinRequest));
        } catch (\Exception $e) {
            Log::error('❌ RejoinRequestResponseNotification failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'status' => 'rejected']);
    }
}
