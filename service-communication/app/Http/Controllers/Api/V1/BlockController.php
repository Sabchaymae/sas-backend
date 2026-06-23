<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BlockedUser;
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
        $request->validate(['user_id' => 'required|exists:users,id']);
        
        $user = Auth::user();
        if ($user->id == $request->user_id) {
            return response()->json(['error' => 'Cannot block yourself'], 400);
        }

        BlockedUser::firstOrCreate([
            'blocker_id' => $user->id,
            'blocked_id' => $request->user_id,
        ]);

        return response()->json(['success' => true]);
    }

    public function unblock($userId)
    {
        $user = Auth::user();
        BlockedUser::where('blocker_id', $user->id)
            ->where('blocked_id', $userId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
