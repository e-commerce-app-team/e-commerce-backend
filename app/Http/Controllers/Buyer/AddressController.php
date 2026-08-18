<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if ($user->role !== 'buyer') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $addresses = BuyerAddress::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $addresses,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role !== 'buyer') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:120',
            'details'      => 'required|string|max:1000',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'driver_notes' => 'nullable|string|max:500',
            'is_default'   => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($user, $validated) {
            $makeDefault = $validated['is_default'] ?? false;
            $hasAny      = BuyerAddress::where('user_id', $user->id)->exists();

            if (!$hasAny) {
                $makeDefault = true;
            }

            if ($makeDefault) {
                BuyerAddress::where('user_id', $user->id)->update(['is_default' => false]);
            }

            $address = BuyerAddress::create([
                'user_id'      => $user->id,
                'title'        => $validated['title'],
                'details'      => $validated['details'],
                'latitude'     => $validated['latitude'] ?? null,
                'longitude'    => $validated['longitude'] ?? null,
                'driver_notes' => $validated['driver_notes'] ?? null,
                'is_default'   => $makeDefault,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Address saved successfully.',
                'data'    => $address,
            ], 201);
        });
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if ($user->role !== 'buyer') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $address = BuyerAddress::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'title'        => 'sometimes|required|string|max:120',
            'details'      => 'sometimes|required|string|max:1000',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'driver_notes' => 'nullable|string|max:500',
            'is_default'   => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($address, $validated) {
            if (!empty($validated['is_default'])) {
                BuyerAddress::where('user_id', $address->user_id)->update(['is_default' => false]);
            }

            $address->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Address updated successfully.',
                'data'    => $address->fresh(),
            ]);
        });
    }

    public function destroy($id)
    {
        $user = auth()->user();
        if ($user->role !== 'buyer') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $address = BuyerAddress::where('user_id', $user->id)->findOrFail($id);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $next = BuyerAddress::where('user_id', $user->id)->latest()->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.',
        ]);
    }

    public function setDefault($id)
    {
        $user = auth()->user();
        if ($user->role !== 'buyer') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $address = BuyerAddress::where('user_id', $user->id)->findOrFail($id);

        DB::transaction(function () use ($user, $address) {
            BuyerAddress::where('user_id', $user->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Default address updated.',
            'data'    => $address->fresh(),
        ]);
    }
}
