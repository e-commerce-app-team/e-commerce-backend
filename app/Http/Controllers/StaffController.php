<?php

namespace App\Http\Controllers;

use App\Mail\StaffInvitationMail;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    // =========================================================================
    // 📌 API للتاجر (Seller)
    // =========================================================================

    /**
     * عرض جميع الموظفين الحاليين التابعين للتاجر
     */
    public function index(Request $request)
    {
        $sellerId = Auth::id();
        
        $activeStaff = User::where('seller_id', $sellerId)
            ->where('role', 'staff')
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'status', 'permissions', 'created_at')
            ->get();

        $pendingInvites = StaffInvitation::where('seller_id', $sellerId)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->get()
            ->map(function ($invite) {
                return [
                    'id' => $invite->id * -1, // Use negative ID for pending invites to avoid conflicts
                    'name' => $invite->name ?? explode('@', $invite->email)[0],
                    'email' => $invite->email,
                    'role' => $invite->role,
                    'status' => 'pending',
                    'permissions' => $invite->permissions,
                    'created_at' => $invite->created_at,
                ];
            });

        // Merge active staff and pending invites
        $staff = $activeStaff->concat($pendingInvites);

        return response()->json([
            'success' => true,
            'data' => $staff
        ], 200);
    }

    /**
     * إرسال دعوة لموظف جديد
     */
    public function invite(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string'
        ]);

        $seller = Auth::user();

        // التأكد أن الإيميل غير مستخدم في النظام مسبقاً
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already registered as a user.'
            ], 400);
        }

        // إنشاء أو تحديث دعوة لنفس الإيميل من نفس التاجر
        $token = Str::random(60);
        $expiresAt = now()->addDays(3); // الدعوة صالحة لـ 3 أيام

        $invitation = StaffInvitation::updateOrCreate(
            ['seller_id' => $seller->id, 'email' => $request->email],
            [
                'permissions' => $request->permissions ?? [],
                'token' => $token,
                'expires_at' => $expiresAt,
                'accepted_at' => null, // إعادة التعيين في حال كانت دعوة قديمة
            ]
        );

        // إرسال الإيميل
        try {
            $storeName = $seller->store_name ?? ($seller->first_name . ' ' . $seller->last_name);
            Mail::to($request->email)->send(new StaffInvitationMail($invitation, $storeName));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation saved but failed to send email. ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully.',
            'data' => $invitation
        ], 200);
    }

    /**
     * عرض الدعوات المعلقة (التي لم يتم قبولها بعد)
     */
    public function pendingInvitations(Request $request)
    {
        $sellerId = Auth::id();
        
        $invitations = StaffInvitation::where('seller_id', $sellerId)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->get();

        return response()->json([
            'success' => true,
            'data' => $invitations
        ], 200);
    }

    /**
     * إلغاء دعوة معلقة
     */
    public function cancelInvitation($id)
    {
        $sellerId = Auth::id();
        
        $invitation = StaffInvitation::where('id', $id)->where('seller_id', $sellerId)->first();
        if (!$invitation) {
            return response()->json(['success' => false, 'message' => 'Invitation not found.'], 404);
        }

        $invitation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invitation cancelled successfully.'
        ], 200);
    }

    /**
     * تعديل صلاحيات موظف حالي
     */
    public function updatePermissions(Request $request, $id)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string'
        ]);

        $sellerId = Auth::id();
        
        $staff = User::where('id', $id)->where('seller_id', $sellerId)->where('role', 'staff')->first();
        if (!$staff) {
            return response()->json(['success' => false, 'message' => 'Staff member not found.'], 404);
        }

        $staff->permissions = $request->permissions;
        $staff->save();

        return response()->json([
            'success' => true,
            'message' => 'Permissions updated successfully.',
            'data' => $staff
        ], 200);
    }

    /**
     * إيقاف / تفعيل حساب موظف (تعطيل مؤقت)
     */
    public function toggleStatus($id)
    {
        $sellerId = Auth::id();
        
        $staff = User::where('id', $id)->where('seller_id', $sellerId)->where('role', 'staff')->first();
        if (!$staff) {
            return response()->json(['success' => false, 'message' => 'Staff member not found.'], 404);
        }

        // استخدام حقل status الموجود مسبقاً (approved = نشط, suspended = معطل)
        $newStatus = ($staff->status === 'approved') ? 'suspended' : 'approved';
        $staff->status = $newStatus;
        $staff->save();

        // إذا تم تعطيله، نقوم بتسجيل خروجه (اختياري حسب التطبيق)
        if ($newStatus === 'suspended') {
            $staff->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Staff status updated successfully.',
            'status' => $staff->status
        ], 200);
    }

    /**
     * حذف الموظف نهائياً (Hard Delete)
     */
    public function removeStaff($id)
    {
        $sellerId = Auth::id();
        
        $staff = User::where('id', $id)->where('seller_id', $sellerId)->where('role', 'staff')->first();
        if (!$staff) {
            return response()->json(['success' => false, 'message' => 'Staff member not found.'], 404);
        }

        // حذف الحساب بشكل كامل
        $staff->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff member deleted permanently.'
        ], 200);
    }


    // =========================================================================
    // 📌 API للموظف (Staff) - Public (لا يحتاج Token لأنه يسجل حساب لأول مرة)
    // =========================================================================

    /**
     * قبول الدعوة وإنشاء كلمة المرور
     */
    public function acceptInvite(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $invitation = StaffInvitation::where('token', $request->token)->first();

        // 1. التحقق من وجود الدعوة
        if (!$invitation) {
            return response()->json(['success' => false, 'message' => 'Invalid invitation token.'], 400);
        }

        // 2. التحقق من انتهاء الصلاحية
        if ($invitation->isExpired()) {
            return response()->json(['success' => false, 'message' => 'This invitation has expired.'], 400);
        }

        // 3. التحقق من أنها لم تُقبل مسبقاً
        if ($invitation->isAccepted()) {
            return response()->json(['success' => false, 'message' => 'This invitation has already been accepted.'], 400);
        }

        // 4. التأكد أن الإيميل ما زال متاحاً
        if (User::where('email', $invitation->email)->exists()) {
            return response()->json(['success' => false, 'message' => 'This email is already registered.'], 400);
        }

        // 5. إنشاء حساب الموظف الفعلي
        $staffUser = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $invitation->email,
            'password' => Hash::make($request->password),
            'role' => 'staff',
            'seller_id' => $invitation->seller_id,
            'permissions' => $invitation->permissions,
            'status' => 'approved', // تفعيل مباشر
            'email_verified_at' => now(), // يعتبر موثق بما أنه جاء من الإيميل
        ]);

        // 6. تحديث حالة الدعوة
        $invitation->update([
            'accepted_at' => now(),
            'staff_user_id' => $staffUser->id,
        ]);

        // 7. توليد Token لتسجيل دخوله فوراً
        $token = $staffUser->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Invitation accepted successfully.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $staffUser->id,
                'name' => $staffUser->first_name . ' ' . $staffUser->last_name,
                'email' => $staffUser->email,
                'role' => $staffUser->role,
                'permissions' => $staffUser->permissions,
            ]
        ], 200);
    }
}
