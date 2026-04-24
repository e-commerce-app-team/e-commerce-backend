<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{

    public function approve($id)
    {
        $user = User::findOrFail($id);
        $user->status = 'approved';
        $user->save();
        return response()->json(['message' => 'User approved']);
    }

    public function reject($id)
    {
        $user = User::findOrFail($id);
        $user->status = 'rejected';
        $user->save();
        return response()->json(['message' => 'User rejected']);
    }

    public function block($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'blocked']);

        return response()->json([
            'message' => 'User has been blocked successfully.'
        ]);
    }

    public function unblock($id)
    {
        $user = User::findOrFail($id);

        // نتحقق أولاً إذا كان فعلاً محظوراً
        if ($user->status !== 'blocked') {
            return response()->json(['message' => 'User is not blocked.'], 400);
        }

        $user->update(['status' => 'approved']);

        return response()->json([
            'message' => 'User has been unblocked and set to approved.'
        ]);
    }

    public function allUsers()
    {
        return response()->json(User::all());
    }

    // 2. تابع يرجع فقط المستخدمين الذين في حالة انتظار (Pending)
    public function pendingUsers()
    {
        $users = User::where('status', 'pending')->get();
        return response()->json($users);
    }

    // 3. تابع يرجع فقط المستخدمين المقبولين (Approved)
    public function approvedUsers()
    {
        $users = User::where('status', 'approved')->get();
        return response()->json($users);
    }

    // 4. تابع يرجع فقط المستخدمين المرفوضين (Rejected)
    public function rejectedUsers()
    {
        $users = User::where('status', 'rejected')->get();
        return response()->json($users);
    }

    public function blockedUsers()
    {
        $users = User::where('status', 'blocked')->latest()->get();
        return response()->json($users);
    }

}
