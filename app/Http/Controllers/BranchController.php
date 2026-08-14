<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        $branches = Branch::where('user_id', auth()->id())->get();
        return response()->json([
            'success' => true,
            'data' => $branches
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'phone' => 'nullable|string|max:50',
            'manager_name' => 'nullable|string|max:255',
            'working_hours' => 'nullable|array',
            'is_active' => 'boolean',
            'product_count' => 'integer',
        ]);

        $validated['user_id'] = auth()->id();

        $branch = Branch::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully.',
            'data' => $branch
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $branch = Branch::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        return response()->json([
            'success' => true,
            'data' => $branch
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $branch = Branch::where('id', $id)->where('user_id', auth()->id())->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'phone' => 'nullable|string|max:50',
            'manager_name' => 'nullable|string|max:255',
            'working_hours' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $branch->update($validated);
        $branch->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully.',
            'data' => $branch
        ]);
    }

    public function toggleActive(Request $request, $id): JsonResponse
    {
        $branch = Branch::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        
        $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $branch->update(['is_active' => $request->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Branch status toggled successfully.',
            'data' => $branch
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $branch = Branch::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted successfully.'
        ]);
    }
}
