<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
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

        try {
            DB::beginTransaction();

            // Handle avatar upload if provided
            $avatarPath = null;
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
            }

            // Create user
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => $request->password, // Will be hashed by the model
                'address' => $request->address,
                'avatar' => $avatarPath,
                'role' => 'customer',
                'is_verified' => false,
            ]);

            // Generate verification token
            $verificationToken = VerificationToken::generateFor($user->email);

            // Send verification email
            $this->sendVerificationEmail($user, $verificationToken->token);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please check your email to verify your account.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Verify user email.
     */
    public function verifyEmail(string $token)
    {
        $verificationToken = VerificationToken::where('token', $token)->first();

        if (!$verificationToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification token.',
            ], 400);
        }

        if ($verificationToken->isExpired()) {
            $verificationToken->delete();
            return response()->json([
                'success' => false,
                'message' => 'Verification token has expired. Please request a new one.',
            ], 400);
        }

        $user = User::where('email', $verificationToken->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $user->is_verified = true;
        $user->save();

        $verificationToken->delete();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully. You can now login.',
        ]);
    }

    /**
     * Login user.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string', // Can be email or username
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find user by email or username
        $user = User::where('email', $request->login)
            ->orWhere('username', $request->login)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // Check if user is verified
        if (!$user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before logging in.',
            ], 403);
        }

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Request password reset.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Don't reveal if email exists
            return response()->json([
                'success' => true,
                'message' => 'If the email exists, a password reset link will be sent.',
            ]);
        }

        // Generate reset token
        $token = Str::random(64);

        // Delete existing tokens for this email
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Insert new token
        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Send reset email
        $this->sendPasswordResetEmail($user, $token);

        return response()->json([
            'success' => true,
            'message' => 'If the email exists, a password reset link will be sent.',
        ]);
    }

    /**
     * Reset password.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord || !Hash::check($request->token, $resetRecord->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        // Check if token is expired (1 hour)
        if (now()->diffInMinutes($resetRecord->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'success' => false,
                'message' => 'Reset token has expired. Please request a new one.',
            ], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $user->password = $request->password;
        $user->save();

        // Delete the reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now login with your new password.',
        ]);
    }

    /**
     * Change password (authenticated user).
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 400);
        }

        $user->password = $request->password;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Send verification email.
     */
    private function sendVerificationEmail(User $user, string $token): void
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
        $verificationUrl = "{$frontendUrl}/verify-email.html?token={$token}";

        try {
            Mail::send([], [], function ($message) use ($user, $verificationUrl) {
                $message->to($user->email)
                    ->subject('Verify Your Email - ATV Rental System')
                    ->html("
                        <h2>Welcome to ATV Rental System!</h2>
                        <p>Hi {$user->first_name},</p>
                        <p>Thank you for registering. Please click the link below to verify your email address:</p>
                        <p><a href='{$verificationUrl}' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verify Email</a></p>
                        <p>Or copy and paste this link in your browser:</p>
                        <p>{$verificationUrl}</p>
                        <p>This link will expire in 24 hours.</p>
                        <p>If you did not create an account, please ignore this email.</p>
                        <br>
                        <p>Best regards,<br>ATV Rental System Team</p>
                    ");
            });
        } catch (\Exception $e) {
            // Log the error but don't fail the registration
            \Log::error('Failed to send verification email: ' . $e->getMessage());
        }
    }

    /**
     * Send password reset email.
     */
    private function sendPasswordResetEmail(User $user, string $token): void
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
        $resetUrl = "{$frontendUrl}/reset-password.html?token={$token}&email={$user->email}";

        try {
            Mail::send([], [], function ($message) use ($user, $resetUrl) {
                $message->to($user->email)
                    ->subject('Reset Your Password - ATV Rental System')
                    ->html("
                        <h2>Password Reset Request</h2>
                        <p>Hi {$user->first_name},</p>
                        <p>We received a request to reset your password. Click the link below to reset it:</p>
                        <p><a href='{$resetUrl}' style='background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                        <p>Or copy and paste this link in your browser:</p>
                        <p>{$resetUrl}</p>
                        <p>This link will expire in 1 hour.</p>
                        <p>If you did not request a password reset, please ignore this email.</p>
                        <br>
                        <p>Best regards,<br>ATV Rental System Team</p>
                    ");
            });
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Failed to send password reset email: ' . $e->getMessage());
        }
    }
}

