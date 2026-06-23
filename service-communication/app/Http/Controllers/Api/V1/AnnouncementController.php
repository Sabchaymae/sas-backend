<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\AnnouncementCreated;
use App\Events\AnnouncementCommentAdded;
use App\Events\AnnouncementCommentReplied;
use App\Events\AnnouncementReactionUpdated;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\AnnouncementReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    // ── Helper: format a single comment (with its replies) ──────────────────
    private function formatComment(AnnouncementComment $c): array
    {
        return [
            'id'         => $c->id,
            'parent_id'  => $c->parent_id,
            'user_id'    => $c->user_id,
            'user_name'  => $c->user_name,
            'body'       => $c->body,
            'created_at' => $c->created_at->toISOString(),
            'replies'    => $c->replies
                ->map(fn($r) => [
                    'id'         => $r->id,
                    'parent_id'  => $r->parent_id,
                    'user_id'    => $r->user_id,
                    'user_name'  => $r->user_name,
                    'body'       => $r->body,
                    'created_at' => $r->created_at->toISOString(),
                    'replies'    => [],          // one level of nesting is enough
                ])
                ->values(),
        ];
    }

    // ── Helper: format one announcement ─────────────────────────────────────
    private function format(Announcement $a, ?int $userId = null): array
    {
        $a->loadMissing(['reactions', 'comments.replies']);

        // Group reactions by emoji
        $groupedReactions = $a->reactions->groupBy('emoji')->map(fn($group) => [
            'emoji'    => $group->first()->emoji,
            'count'    => $group->count(),
            'user_ids' => $group->pluck('user_id')->toArray(),
        ])->values();

        $myReaction = $userId
            ? $a->reactions->firstWhere('user_id', $userId)?->emoji
            : null;

        return [
            'id'          => $a->id,
            'author_id'   => $a->author_id,
            'author_name' => $a->author_name,
            'title'       => $a->title,
            'body'        => $a->body,
            'type'        => $a->type,
            'color'       => $a->color,
            'pinned'      => $a->pinned,
            'expires_at'  => $a->expires_at->toISOString(),
            'is_expired'  => $a->isExpired(),
            'created_at'  => $a->created_at->toISOString(),
            'reactions'   => $groupedReactions,
            'my_reaction' => $myReaction,
            'comments'    => $a->comments
                ->whereNull('parent_id')
                ->map(fn($c) => $this->formatComment($c))
                ->values(),
        ];
    }

    // ── GET /v1/announcements ────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $showExpired = $request->boolean('show_expired', false);
        $userId      = Auth::id();

        $query = Announcement::with(['reactions', 'comments'])->ordered();

        if (!$showExpired) {
            $query->active();
        }

        $announcements = $query->get()->map(fn($a) => $this->format($a, $userId));

        return response()->json($announcements);
    }

    // ── POST /v1/announcements ───────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'      => 'required|string|max:200',
            'body'       => 'required|string',
            'type'       => 'required|in:event,meeting,notice,urgent',
            'color'      => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'pinned'     => 'nullable|boolean',
            'expires_at' => 'required|date|after:now',
        ]);

        $user = Auth::user();

        $announcement = Announcement::create([
            ...$data,
            'author_id'   => $user->id,
            'author_name' => trim("{$user->prenom} {$user->nom}"),
            'color'       => $data['color'] ?? '#1428C9',
            'pinned'      => $data['pinned'] ?? false,
        ]);

        Log::info('📢 Announcement created', ['id' => $announcement->id, 'by' => $user->id]);

        $formatted = $this->format($announcement, $user->id);

        try {
            broadcast(new AnnouncementCreated($formatted));
        } catch (\Exception $e) {
            Log::error('❌ AnnouncementCreated broadcast failed: ' . $e->getMessage());
        }

        return response()->json($formatted, 201);
    }

    // ── PUT /v1/announcements/{id} ───────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $user = Auth::user();

        // Admin OR author can edit
        $isAdmin = in_array($user->role ?? '', ['admin', 'Admin', 'super_admin']);
        if (!$isAdmin && $announcement->author_id !== $user->id) {
            return response()->json(['message' => 'Interdit.'], 403);
        }

        $data = $request->validate([
            'title'      => 'sometimes|string|max:200',
            'body'       => 'sometimes|string',
            'type'       => 'sometimes|in:event,meeting,notice,urgent',
            'color'      => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'pinned'     => 'nullable|boolean',
            'expires_at' => 'sometimes|date|after:now',
        ]);

        $announcement->update($data);

        return response()->json($this->format($announcement->fresh(['reactions', 'comments']), $user->id));
    }

    // ── DELETE /v1/announcements/{id} ────────────────────────────────────────
    public function destroy(int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $user         = Auth::user();

        // Admin OR author can delete
        $isAdmin = in_array($user->role ?? '', ['admin', 'Admin', 'super_admin']);
        if (!$isAdmin && $announcement->author_id !== $user->id) {
            return response()->json(['message' => 'Interdit.'], 403);
        }

        $announcement->delete();

        return response()->json(['success' => true]);
    }

    // ── POST /v1/announcements/{id}/react ────────────────────────────────────
    public function react(Request $request, int $id): JsonResponse
    {
        $request->validate(['emoji' => 'required|string|max:10']);

        $announcement = Announcement::findOrFail($id);
        $userId       = Auth::id();
        $emoji        = $request->input('emoji');

        $user = Auth::user();

        $existing = AnnouncementReaction::where('announcement_id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if ($existing->emoji === $emoji) {
                // Same emoji → toggle off
                $existing->delete();
                $myReaction = null;
                $action = 'removed';
            } else {
                // Different emoji → update
                $existing->update(['emoji' => $emoji]);
                $myReaction = $emoji;
                $action = 'changed';
            }
        } else {
            AnnouncementReaction::create([
                'announcement_id' => $id,
                'user_id'         => $userId,
                'emoji'           => $emoji,
            ]);
            $myReaction = $emoji;
            $action = 'added';
        }

        // Return fresh grouped reactions
        $announcement->load('reactions');
        $groupedReactions = $announcement->reactions->groupBy('emoji')->map(fn($group) => [
            'emoji'    => $group->first()->emoji,
            'count'    => $group->count(),
            'user_ids' => $group->pluck('user_id')->toArray(),
        ])->values();

        $reactorName = trim("{$user->prenom} {$user->nom}");

        try {
            broadcast(new AnnouncementReactionUpdated($id, $groupedReactions->toArray(), $userId, $reactorName, $emoji, $action));
        } catch (\Exception $e) {
            Log::error('❌ AnnouncementReactionUpdated broadcast failed: ' . $e->getMessage());
        }

        return response()->json([
            'reactions'   => $groupedReactions,
            'my_reaction' => $myReaction,
        ]);
    }
    public function addComment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'body'      => 'required|string|max:1000',
            'parent_id' => 'nullable|integer|exists:announcement_comments,id',
        ]);

        Announcement::findOrFail($id); // ensure exists

        $user    = Auth::user();
        $comment = AnnouncementComment::create([
            'announcement_id' => $id,
            'parent_id'       => $request->input('parent_id'),
            'user_id'         => $user->id,
            'user_name'       => trim("{$user->prenom} {$user->nom}"),
            'body'            => $request->input('body'),
        ]);

        $formatted = $this->formatComment($comment->load('replies'));

        try {
            broadcast(new AnnouncementCommentAdded($id, $formatted));
        } catch (\Exception $e) {
            Log::error('❌ AnnouncementCommentAdded broadcast failed: ' . $e->getMessage());
        }

        return response()->json($formatted, 201);
    }

    // ── POST /v1/announcements/{id}/comments/{commentId}/replies ─────────────
    public function replyToComment(Request $request, int $id, int $commentId): JsonResponse
    {
        $request->validate(['body' => 'required|string|max:1000']);

        Announcement::findOrFail($id);
        $parent = AnnouncementComment::where('announcement_id', $id)->findOrFail($commentId);

        $user  = Auth::user();
        $reply = AnnouncementComment::create([
            'announcement_id' => $id,
            'parent_id'       => $parent->id,
            'user_id'         => $user->id,
            'user_name'       => trim("{$user->prenom} {$user->nom}"),
            'body'            => $request->input('body'),
        ]);

        $formatted = $this->formatComment($reply->load('replies'));

        try {
            broadcast(new AnnouncementCommentReplied($id, $parent->id, $formatted));
        } catch (\Exception $e) {
            Log::error('❌ AnnouncementCommentReplied broadcast failed: ' . $e->getMessage());
        }

        return response()->json($formatted, 201);
    }

    // ── PATCH /v1/announcements/{id}/pin ─────────────────────────────────────
    public function togglePin(int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $user         = Auth::user();

        // Admin OR author can pin
        $isAdmin = in_array($user->role ?? '', ['admin', 'Admin', 'super_admin']);
        if (!$isAdmin && $announcement->author_id !== $user->id) {
            return response()->json(['message' => 'Interdit.'], 403);
        }

        $announcement->update(['pinned' => !$announcement->pinned]);

        return response()->json(['pinned' => $announcement->pinned]);
    }

    // ── DELETE /v1/announcements/{id}/comments/{commentId} (admin override) ─
    // Admin can also delete any comment
    public function deleteComment(int $id, int $commentId): JsonResponse
    {
        $comment = AnnouncementComment::where('announcement_id', $id)->findOrFail($commentId);
        $user    = Auth::user();

        $isAdmin = in_array($user->role ?? '', ['admin', 'Admin', 'super_admin']);
        if (!$isAdmin && $comment->user_id !== $user->id) {
            return response()->json(['message' => 'Interdit.'], 403);
        }

        $comment->delete();

        return response()->json(['success' => true]);
    }
}
