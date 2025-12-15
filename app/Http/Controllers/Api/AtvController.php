<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Atv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AtvController extends Controller
{
    /**
     * List all ATVs (Public).
     */
    public function index(Request $request)
    {
        $query = Atv::query();

        // Search by name or type
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['available', 'rented', 'maintenance'])) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        // Price range filter
        if ($request->has('min_price')) {
            $query->where('daily_price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('daily_price', '<=', $request->max_price);
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['name', 'type', 'daily_price', 'status', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $atvs = $query->paginate($request->get('per_page', 12));

        // Transform data to include image URL
        $items = collect($atvs->items())->map(function ($atv) {
            return [
                'id' => $atv->id,
                'name' => $atv->name,
                'type' => $atv->type,
                'serial_number' => $atv->serial_number,
                'daily_price' => $atv->daily_price,
                'status' => $atv->status,
                'image' => $atv->image ? asset('storage/' . $atv->image) : null,
                'description' => $atv->description,
                'created_at' => $atv->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $atvs->currentPage(),
                'last_page' => $atvs->lastPage(),
                'per_page' => $atvs->perPage(),
                'total' => $atvs->total(),
            ],
        ]);
    }

    /**
     * Get ATV types for filtering.
     */
    public function types()
    {
        $types = Atv::distinct()->pluck('type')->sort()->values();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Get a specific ATV (Public).
     */
    public function show(int $id)
    {
        $atv = Atv::find($id);

        if (!$atv) {
            return response()->json([
                'success' => false,
                'message' => 'ATV not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $atv->id,
                'name' => $atv->name,
                'type' => $atv->type,
                'serial_number' => $atv->serial_number,
                'daily_price' => $atv->daily_price,
                'status' => $atv->status,
                'image' => $atv->image ? asset('storage/' . $atv->image) : null,
                'description' => $atv->description,
                'created_at' => $atv->created_at,
                'updated_at' => $atv->updated_at,
            ],
        ]);
    }

    /**
     * Create a new ATV (Admin/Manager only).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'serial_number' => 'required|string|max:100|unique:atvs',
            'daily_price' => 'required|numeric|min:0',
            'status' => 'sometimes|in:available,rented,maintenance',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'name',
            'type',
            'serial_number',
            'daily_price',
            'description',
        ]);

        $data['status'] = $request->get('status', 'available');

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('atvs', 'public');
            $data['image'] = $path;
        }

        $atv = Atv::create($data);

        return response()->json([
            'success' => true,
            'message' => 'ATV created successfully.',
            'data' => [
                'id' => $atv->id,
                'name' => $atv->name,
                'type' => $atv->type,
                'serial_number' => $atv->serial_number,
                'daily_price' => $atv->daily_price,
                'status' => $atv->status,
                'image' => $atv->image ? asset('storage/' . $atv->image) : null,
                'description' => $atv->description,
            ],
        ], 201);
    }

    /**
     * Update an ATV (Admin/Manager only).
     */
    public function update(Request $request, int $id)
    {
        $atv = Atv::find($id);

        if (!$atv) {
            return response()->json([
                'success' => false,
                'message' => 'ATV not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:100',
            'serial_number' => 'sometimes|string|max:100|unique:atvs,serial_number,' . $id,
            'daily_price' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:available,rented,maintenance',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if trying to change status when ATV is currently rented
        if ($request->has('status') && $atv->status === 'rented' && $request->status !== 'rented') {
            $hasActiveRental = $atv->rentals()
                ->where('status', 'rented')
                ->exists();

            if ($hasActiveRental) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot change status while ATV has an active rental. Complete the rental first.',
                ], 422);
            }
        }

        // Update fields
        $atv->fill($request->only([
            'name',
            'type',
            'serial_number',
            'daily_price',
            'status',
            'description',
        ]));

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($atv->image) {
                Storage::disk('public')->delete($atv->image);
            }

            $path = $request->file('image')->store('atvs', 'public');
            $atv->image = $path;
        }

        $atv->save();

        return response()->json([
            'success' => true,
            'message' => 'ATV updated successfully.',
            'data' => [
                'id' => $atv->id,
                'name' => $atv->name,
                'type' => $atv->type,
                'serial_number' => $atv->serial_number,
                'daily_price' => $atv->daily_price,
                'status' => $atv->status,
                'image' => $atv->image ? asset('storage/' . $atv->image) : null,
                'description' => $atv->description,
            ],
        ]);
    }

    /**
     * Delete an ATV (Admin/Manager only).
     */
    public function destroy(int $id)
    {
        $atv = Atv::find($id);

        if (!$atv) {
            return response()->json([
                'success' => false,
                'message' => 'ATV not found.',
            ], 404);
        }

        // Check for active rentals
        if ($atv->hasActiveRentals()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete ATV with active rentals. Complete or cancel all rentals first.',
            ], 422);
        }

        // Delete image if exists
        if ($atv->image) {
            Storage::disk('public')->delete($atv->image);
        }

        $atv->delete();

        return response()->json([
            'success' => true,
            'message' => 'ATV deleted successfully.',
        ]);
    }
}

