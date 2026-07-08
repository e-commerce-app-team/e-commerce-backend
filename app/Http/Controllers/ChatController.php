<?php

namespace App\Http\Controllers;

use App\Models\AutoReply;
use App\Models\BlockedUser;
use App\Models\QuickReply;
use App\Models\UserReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    // 1. Generate Firebase Custom Token
    public function generateFirebaseToken(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $auth = app('firebase.auth');
            $customToken = $auth->createCustomToken((string) $user->id);

            return response()->json([
                'firebase_token' => $customToken->toString()
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Firebase Token Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to generate Firebase token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 2. Quick Replies CRUD
    public function getQuickReplies()
    {
        $replies = QuickReply::where('seller_id', Auth::id())->get();
        return response()->json($replies);
    }

    public function storeQuickReply(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $reply = QuickReply::create([
            'seller_id' => Auth::id(),
            'title' => $request->title,
            'message' => $request->message,
        ]);

        return response()->json(['message' => 'Quick reply created successfully', 'data' => $reply], 201);
    }

    public function updateQuickReply(Request $request, $id)
    {
        $reply = QuickReply::where('seller_id', Auth::id())->findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
        ]);

        $reply->update($request->only(['title', 'message']));

        return response()->json(['message' => 'Quick reply updated successfully', 'data' => $reply]);
    }

    public function deleteQuickReply($id)
    {
        $reply = QuickReply::where('seller_id', Auth::id())->findOrFail($id);
        $reply->delete();

        return response()->json(['message' => 'Quick reply deleted successfully']);
    }

    // 3. Auto Replies CRUD
    public function getAutoReplies()
    {
        $replies = AutoReply::where('seller_id', Auth::id())->get();
        return response()->json($replies);
    }

    public function storeAutoReply(Request $request)
    {
        $request->validate([
            'keyword' => 'nullable|string|max:255',
            'message' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $reply = AutoReply::create([
            'seller_id' => Auth::id(),
            'keyword' => $request->keyword,
            'message' => $request->message,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['message' => 'Auto reply created successfully', 'data' => $reply], 201);
    }

    public function updateAutoReply(Request $request, $id)
    {
        $reply = AutoReply::where('seller_id', Auth::id())->findOrFail($id);

        $request->validate([
            'keyword' => 'nullable|string|max:255',
            'message' => 'sometimes|string',
            'is_active' => 'boolean',
        ]);

        $reply->update($request->only(['keyword', 'message', 'is_active']));

        return response()->json(['message' => 'Auto reply updated successfully', 'data' => $reply]);
    }

    public function deleteAutoReply($id)
    {
        $reply = AutoReply::where('seller_id', Auth::id())->findOrFail($id);
        $reply->delete();

        return response()->json(['message' => 'Auto reply deleted successfully']);
    }

    // 4. Block User
    public function getBlockedUsers()
    {
        $blocked = BlockedUser::with('blocked:id,first_name,last_name,email,profile_photo')
            ->where('blocker_id', Auth::id())
            ->get();
            
        return response()->json($blocked);
    }

    public function blockUser(Request $request)
    {
        $request->validate([
            'blocked_id' => 'required|exists:users,id',
        ]);

        if ($request->blocked_id == Auth::id()) {
            return response()->json(['message' => 'You cannot block yourself'], 400);
        }

        $block = BlockedUser::firstOrCreate([
            'blocker_id' => Auth::id(),
            'blocked_id' => $request->blocked_id,
        ]);

        return response()->json(['message' => 'User blocked successfully', 'data' => $block], 201);
    }

    public function unblockUser($id)
    {
        $block = BlockedUser::where('blocker_id', Auth::id())
            ->where('blocked_id', $id)
            ->firstOrFail();
            
        $block->delete();

        return response()->json(['message' => 'User unblocked successfully']);
    }

    // 5. Report User
    public function reportUser(Request $request)
    {
        $request->validate([
            'reported_id' => 'required|exists:users,id',
            'reason' => 'required|string',
        ]);

        if ($request->reported_id == Auth::id()) {
            return response()->json(['message' => 'You cannot report yourself'], 400);
        }

        $report = UserReport::create([
            'reporter_id' => Auth::id(),
            'reported_id' => $request->reported_id,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'User reported successfully', 'data' => $report], 201);
    }
}
