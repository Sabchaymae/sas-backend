<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\BlockController;
use App\Http\Controllers\Api\V1\ArchiveController;
use Illuminate\Support\Facades\Storage;

// Public route to serve chat files (handles proxy/symlink issues)
Route::get('chat-files/{filename}', function ($filename) {
    $path = 'chat/' . $filename;
    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }
    return Storage::disk('public')->response($path);
})->where('filename', '.*');

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1')->group(function () {
        // Conversations
        Route::get('conversations', [ConversationController::class, 'index']);
        Route::post('conversations', [ConversationController::class, 'store']);
        Route::get('conversations/{id}', [ConversationController::class, 'show']);
        Route::delete('conversations/{id}', [ConversationController::class, 'destroy']);

        // Messages
        Route::get('conversations/{id}/messages', [MessageController::class, 'index']);
        Route::post('conversations/{id}/messages', [MessageController::class, 'store']);
        Route::put('conversations/{id}/messages/{messageId}', [MessageController::class, 'update']);
        Route::delete('conversations/{id}/messages/{messageId}', [MessageController::class, 'destroy']);
        Route::post('conversations/{id}/read', [MessageController::class, 'markAsRead']);
        Route::post('conversations/{id}/typing', function(Request $request, $id) {
            try {
                broadcast(new \App\Events\UserTyping($id, Auth::id(), $request->is_typing))->toOthers();
            } catch (\Exception $e) {
                // Ignore broadcast errors
            }
            return response()->json(['success' => true]);
        });

        // Groups
        Route::post('conversations/{id}/members', [GroupController::class, 'addMembers']);
        Route::delete('conversations/{id}/members/{userId}', [GroupController::class, 'removeMember']);
        Route::post('conversations/{id}/leave', [GroupController::class, 'leave']);
        Route::delete('conversations/{id}/group', [GroupController::class, 'deleteGroup']);

        // Group Invitations
        Route::get('invitations', [\App\Http\Controllers\Api\V1\GroupInvitationController::class, 'index']);
        Route::post('conversations/{id}/invitations', [\App\Http\Controllers\Api\V1\GroupInvitationController::class, 'store']);
        Route::post('invitations/{id}/accept', [\App\Http\Controllers\Api\V1\GroupInvitationController::class, 'accept']);
        Route::post('invitations/{id}/reject', [\App\Http\Controllers\Api\V1\GroupInvitationController::class, 'reject']);

        // Blocking
        Route::get('blocks', [BlockController::class, 'index']);
        Route::post('blocks', [BlockController::class, 'block']);
        Route::delete('blocks/{userId}', [BlockController::class, 'unblock']);

        // Archiving
        Route::get('archives', [ArchiveController::class, 'index']);
        Route::post('conversations/{id}/archive', [ArchiveController::class, 'archive']);
        Route::delete('conversations/{id}/archive', [ArchiveController::class, 'unarchive']);
        
        // Search
        Route::get('search/users', function (Request $request) {
            $query = $request->get('q');
            return \App\Models\User::where(function($q) use ($query) {
                $q->where('prenom', 'like', "%$query%")
                  ->orWhere('nom', 'like', "%$query%")
                  ->orWhere('email', 'like', "%$query%");
            })
            ->limit(10)
            ->get();
        });
    });
});

