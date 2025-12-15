<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get current user's profile.
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'address' => $user->address,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Update current user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:users,username,' . $user->id,
            'address' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update basic fields
        if ($request->has('first_name')) {
            $user->first_name = $request->first_name;
        }
        if ($request->has('last_name')) {
            $user->last_name = $request->last_name;
        }
        if ($request->has('username')) {
            $user->username = $request->username;
        }
        if ($request->has('address')) {
            $user->address = $request->address;
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'address' => $user->address,
            ],
        ]);
    }

    /**
     * List all users (Admin only).
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($request->has('role') && in_array($request->role, ['admin', 'manager', 'customer'])) {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        // Transform data to include full avatar URL
        $items = collect($users->items())->map(function ($user) {
            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'is_verified' => $user->is_verified,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'address' => $user->address,
                'created_at' => $user->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Create a new user (Admin only).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,manager,customer',
            'address' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Handle avatar upload if provided
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        // Create user - always verified when created by admin
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password, // Will be hashed by the model
            'role' => $request->role,
            'address' => $request->address,
            'avatar' => $avatarPath,
            'is_verified' => true, // Always verified when created by admin
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'is_verified' => $user->is_verified,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ],
        ], 201);
    }

    /**
     * Get a specific user (Admin only).
     */
    public function show(int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'is_verified' => $user->is_verified,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'address' => $user->address,
                'created_at' => $user->created_at,
                'rentals_count' => $user->rentals()->count(),
            ],
        ]);
    }

    /**
     * Update a user (Admin only).
     */
    public function update(Request $request, int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:users,username,' . $id,
            'role' => 'sometimes|in:admin,manager,customer',
            'is_verified' => 'sometimes|boolean',
            'address' => 'nullable|string',
            'password' => 'nullable|string|min:8',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Prevent admin from changing their own role
        if ($request->user()->id === $user->id && $request->has('role')) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change your own role.',
            ], 400);
        }

        $user->fill($request->only([
            'first_name',
            'last_name',
            'username',
            'role',
            'is_verified',
            'address',
        ]));

        // Handle password change if provided
        // Use plain password - Laravel's 'hashed' cast will automatically hash it
        // This matches the pattern used in AuthController::changePassword()
        if ($request->filled('password')) {
            $user->password = $request->password;
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            // Store new avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user,
        ]);
    }

    /**
     * Delete a user (Admin only).
     */
    public function destroy(Request $request, int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Prevent admin from deleting themselves
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 400);
        }

        // Check for active rentals (pending, approved, rented, pending_return)
        $activeRentals = $user->rentals()
            ->active()
            ->exists();

        if ($activeRentals) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete user with active rentals. Please complete, cancel, or deny all pending/active rentals first.',
            ], 422);
        }

        // Delete avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }
}

