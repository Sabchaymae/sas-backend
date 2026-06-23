<?php

namespace App\Http\Controllers\Api\V1;

<<<<<<< HEAD
use App\Http\Controllers\Controller;
use App\Models\BlockedUser;
=======
use App\Events\UserBlocked;
use App\Http\Controllers\Controller;
use App\Models\BlockedUser;
use App\Models\Conversation;
>>>>>>> import/master
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $blocked = BlockedUser::where('blocker_id', $user->id)->with('blocked')->get();
        return response()->json($blocked);
    }

    public function block(Request $request)
    {
<<<<<<< HEAD
        $request->validate(['user_id' => 'required|exists:users,id']);
=======
        $request->validate(['user_id' => 'required|integer|min:1']);
>>>>>>> import/master
        
        $user = Auth::user();
        if ($user->id == $request->user_id) {
            return response()->json(['error' => 'Cannot block yourself'], 400);
        }

        BlockedUser::firstOrCreate([
            'blocker_id' => $user->id,
            'blocked_id' => $request->user_id,
        ]);

<<<<<<< HEAD
=======
        try {
            broadcast(new UserBlocked($user->id, (int) $request->user_id, true));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('❌ UserBlocked broadcast failed: ' . $e->getMessage());
        }

>>>>>>> import/master
        return response()->json(['success' => true]);
    }

    public function unblock($userId)
    {
        $user = Auth::user();
        BlockedUser::where('blocker_id', $user->id)
            ->where('blocked_id', $userId)
            ->delete();
<<<<<<< HEAD
=======
        
        // Find any private conversation between the two users and ensure both participants are active
        $conversation = Conversation::where('type', 'private')
            ->whereHas('participantData', function($q) use ($user, $userId) {
                $q->whereIn('user_id', [$user->id, $userId]);
            }, '=', 2)
            ->first();
        
        if ($conversation) {
            $conversation->participantData()
                ->whereIn('user_id', [$user->id, $userId])
                ->whereNotIn('status', ['active'])
                ->update(['status' => 'active']);
        }

        try {
            broadcast(new UserBlocked($user->id, (int) $userId, false));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('❌ UserUnblocked broadcast failed: ' . $e->getMessage());
        }
>>>>>>> import/master

        return response()->json(['success' => true]);
    }
}
