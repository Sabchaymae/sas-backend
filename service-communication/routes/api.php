<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\BlockController;
use App\Http\Controllers\Api\V1\ArchiveController;

// Public route to serve chat files (handles proxy/symlink issues)
Route::get('chat-files/{filename}', function ($filename) {
    $path = 'chat/' . $filename;

    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }

    // Use the storage path and return a Symfony file response to satisfy
    // static analysis and ensure proper file serving.
    $fullPath = Storage::disk('public')->path($path);

    return response()->file($fullPath);
})->where('filename', '.*');

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('v1')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | CONVERSATIONS
        |--------------------------------------------------------------------------
        */
        Route::get('conversations', [ConversationController::class, 'index']);
        Route::post('conversations', [ConversationController::class, 'store']);
        Route::get('conversations/{id}', [ConversationController::class, 'show']);
        Route::delete('conversations/{id}', [ConversationController::class, 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | MESSAGES
        |--------------------------------------------------------------------------
        */
        Route::get('conversations/{id}/messages', [MessageController::class, 'index']);
        Route::post('conversations/{id}/messages', [MessageController::class, 'store']);
        Route::put('conversations/{id}/messages/{messageId}', [MessageController::class, 'update']);
        Route::delete('conversations/{id}/messages/{messageId}', [MessageController::class, 'destroy']);
        Route::post('conversations/{id}/read', [MessageController::class, 'markAsRead']);

        /*
        |--------------------------------------------------------------------------
        | MESSAGE REACTIONS
        |--------------------------------------------------------------------------
        */
        Route::get('conversations/{id}/messages/{messageId}/reactions', [\App\Http\Controllers\Api\V1\MessageReactionController::class, 'index']);
        Route::post('conversations/{id}/messages/{messageId}/reactions', [\App\Http\Controllers\Api\V1\MessageReactionController::class, 'toggle']);
        Route::delete('conversations/{id}/messages/{messageId}/reactions/{reactionId}', [\App\Http\Controllers\Api\V1\MessageReactionController::class, 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | TYPING EVENT (REAL TIME)
        |--------------------------------------------------------------------------
        */
        Route::post('conversations/{id}/typing', function (Request $request, $id) {

            try {
                broadcast(new \App\Events\UserTyping(
                    $id,
                    Auth::id(),
                    $request->input('is_typing')
                ))->toOthers();
            } catch (\Exception $e) {
                // Optionnel: logger l'erreur
                // \Log::error($e->getMessage());
            }

            return response()->json(['success' => true]);
        });

        /*
        |--------------------------------------------------------------------------
        | GROUPS
        |--------------------------------------------------------------------------
        */
        Route::post('conversations/{id}/members', [GroupController::class, 'addMembers']);
        Route::delete('conversations/{id}/members/{userId}', [GroupController::class, 'removeMember']);
        Route::post('conversations/{id}/leave', [GroupController::class, 'leave']);
        Route::delete('conversations/{id}/group', [GroupController::class, 'deleteGroup']);

        /*
        |--------------------------------------------------------------------------
        | GROUP INVITATIONS
        |--------------------------------------------------------------------------
        */
        Route::get('invitations', [\App\Http\Controllers\Api\V1\GroupInvitationController::class, 'index']);
        Route::post('conversations/{id}/invitations', [\App\Http\Controllers\Api\V1\GroupInvitationController::class, 'store']);
        Route::post('invitations/{id}/accept', [\App\Http\Controllers\Api\V1\GroupInvitationController::class, 'accept']);
        Route::post('invitations/{id}/reject', [\App\Http\Controllers\Api\V1\GroupInvitationController::class, 'reject']);

        /*
        |--------------------------------------------------------------------------
        | REJOIN REQUESTS
        |--------------------------------------------------------------------------
        */
        Route::post('conversations/{id}/rejoin-request', [\App\Http\Controllers\Api\V1\RejoinRequestController::class, 'store']);
        Route::get('rejoin-requests', [\App\Http\Controllers\Api\V1\RejoinRequestController::class, 'index']);
        Route::post('rejoin-requests/{id}/accept', [\App\Http\Controllers\Api\V1\RejoinRequestController::class, 'accept']);
        Route::post('rejoin-requests/{id}/reject', [\App\Http\Controllers\Api\V1\RejoinRequestController::class, 'reject']);

        /*
        |--------------------------------------------------------------------------
        | BLOCK SYSTEM
        |--------------------------------------------------------------------------
        */
        Route::get('blocks', [BlockController::class, 'index']);
        Route::post('blocks', [BlockController::class, 'block']);
        Route::delete('blocks/{userId}', [BlockController::class, 'unblock']);

        /*
        |--------------------------------------------------------------------------
        | ARCHIVE SYSTEM
        |--------------------------------------------------------------------------
        */
        Route::get('archives', [ArchiveController::class, 'index']);
        Route::post('conversations/{id}/archive', [ArchiveController::class, 'archive']);
        Route::delete('conversations/{id}/archive', [ArchiveController::class, 'unarchive']);

        /*
        |--------------------------------------------------------------------------
        | ANNOUNCEMENTS (Publications / Réunions / Événements)
        |--------------------------------------------------------------------------
        */
        Route::get('announcements',                                            [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'index']);
        Route::post('announcements',                                           [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'store']);
        Route::put('announcements/{id}',                                       [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'update']);
        Route::delete('announcements/{id}',                                    [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'destroy']);
        Route::post('announcements/{id}/react',                                [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'react']);
        Route::patch('announcements/{id}/pin',                                 [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'togglePin']);
        Route::post('announcements/{id}/comments',                             [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'addComment']);
        Route::post('announcements/{id}/comments/{commentId}/replies',         [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'replyToComment']);
        Route::delete('announcements/{id}/comments/{commentId}',               [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'deleteComment']);

        /*
        |--------------------------------------------------------------------------
        | HISTORY / ACTIVITY LOGS
        |--------------------------------------------------------------------------
        */
        Route::get('history', [\App\Http\Controllers\Api\V1\HistoryController::class, 'index']);
        Route::get('history/stats', [\App\Http\Controllers\Api\V1\HistoryController::class, 'stats']);
        Route::get('history/filters', [\App\Http\Controllers\Api\V1\HistoryController::class, 'getFilters']);
        Route::get('history/{activityLog}', [\App\Http\Controllers\Api\V1\HistoryController::class, 'show']);

        /*
        |--------------------------------------------------------------------------
        | SEARCH USERS
        |--------------------------------------------------------------------------
        */
        Route::get('search/users', function (Request $request) {

            $query = trim($request->get('q', ''));

            if ($query === '') {
                return response()->json([]);
            }

            return \App\Models\User::where('prenom', 'like', "%{$query}%")
                ->orWhere('nom', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->limit(10)
                ->get();
        });
    });
});