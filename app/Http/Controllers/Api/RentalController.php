<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Atv;
use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RentalController extends Controller
{
    /**
     * List rentals (filtered by role).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Rental::with(['user', 'atv']);

        // Customers can only see their own rentals
        if ($user->isCustomer()) {
            $query->forUser($user->id);
        }

        // Status filter
        if ($request->has('status') && in_array($request->status, Rental::getStatuses())) {
            $query->status($request->status);
        }

        // ATV filter
        if ($request->has('atv_id')) {
            $query->where('atv_id', $request->atv_id);
        }

        // User filter (for admin/manager)
        if ($request->has('user_id') && !$user->isCustomer()) {
            $query->where('user_id', $request->user_id);
        }

        // Date range filter
        if ($request->has('start_date')) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('end_time', '<=', $request->end_date);
        }

        $rentals = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        // Transform data
        $items = collect($rentals->items())->map(function ($rental) {
            return [
                'id' => $rental->id,
                'user' => [
                    'id' => $rental->user->id,
                    'name' => $rental->user->full_name,
                    'username' => $rental->user->username,
                    'email' => $rental->user->email,
                ],
                'atv' => [
                    'id' => $rental->atv->id,
                    'name' => $rental->atv->name,
                    'type' => $rental->atv->type,
                    'image' => $rental->atv->image ? asset('storage/' . $rental->atv->image) : null,
                ],
                'status' => $rental->status,
                'status_label' => $rental->status_label,
                'start_time' => $rental->start_time->format('Y-m-d'),  // Send date only (YYYY-MM-DD)
                'end_time' => $rental->end_time->format('Y-m-d'),      // Send date only (YYYY-MM-DD)
                'duration_days' => $rental->duration_days,
                'duration_hours' => $rental->duration_hours,
                'total_price' => $rental->total_price,
                'notes' => $rental->notes,
                'created_at' => $rental->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $rentals->currentPage(),
                'last_page' => $rentals->lastPage(),
                'per_page' => $rentals->perPage(),
                'total' => $rentals->total(),
            ],
        ]);
    }

    /**
     * Get a specific rental.
     */
    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $rental = Rental::with(['user', 'atv'])->find($id);

        if (!$rental) {
            return response()->json([
                'success' => false,
                'message' => 'Rental not found.',
            ], 404);
        }

        // Customers can only see their own rentals
        if ($user->isCustomer() && $rental->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this rental.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $rental->id,
                'user' => [
                    'id' => $rental->user->id,
                    'name' => $rental->user->full_name,
                    'username' => $rental->user->username,
                    'email' => $rental->user->email,
                ],
                'atv' => [
                    'id' => $rental->atv->id,
                    'name' => $rental->atv->name,
                    'type' => $rental->atv->type,
                    'serial_number' => $rental->atv->serial_number,
                    'hourly_price' => $rental->atv->hourly_price,
                    'image' => $rental->atv->image ? asset('storage/' . $rental->atv->image) : null,
                ],
                'status' => $rental->status,
                'status_label' => $rental->status_label,
                'start_time' => $rental->start_time->format('Y-m-d'),  // Send date only (YYYY-MM-DD)
                'end_time' => $rental->end_time->format('Y-m-d'),      // Send date only (YYYY-MM-DD)
                'duration_days' => $rental->duration_days,
                'duration_hours' => $rental->duration_hours,
                'total_price' => $rental->total_price,
                'notes' => $rental->notes,
                'created_at' => $rental->created_at->toIso8601String(),
                'updated_at' => $rental->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create a new rental request (Customer).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'atv_id' => 'required|exists:atvs,id',
            'start_time' => 'required|date|after_or_equal:today',
            'end_time' => 'required|date|after_or_equal:start_time',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $atv = Atv::find($request->atv_id);

        // Check if ATV exists
        if (!$atv) {
            return response()->json([
                'success' => false,
                'message' => 'ATV not found.',
            ], 404);
        }

        // Check if ATV is available
        if (!$atv->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'This ATV is not available for rental.',
            ], 422);
        }

        // Parse dates and normalize to start/end of day
        // Extract date part (YYYY-MM-DD) to avoid timezone conversion issues
        // This ensures Dec 13 stays Dec 13 regardless of server timezone
        $startDateString = null;
        $endDateString = null;
        
        try {
            // Extract date part (YYYY-MM-DD) from input
            // Handle both ISO string format (2025-12-13T00:00:00Z) and date-only format (2025-12-13)
            $startDateString = is_string($request->start_time) ? substr($request->start_time, 0, 10) : $request->start_time;
            $endDateString = is_string($request->end_time) ? substr($request->end_time, 0, 10) : $request->end_time;
            
            // Parse as date-only (YYYY-MM-DD) to avoid timezone conversion
            // Use createFromDate to create date in UTC timezone to prevent shifting
            [$startYear, $startMonth, $startDay] = explode('-', $startDateString);
            [$endYear, $endMonth, $endDay] = explode('-', $endDateString);
            
            // Create dates in UTC timezone to prevent any timezone shifting
            $startTime = \Carbon\Carbon::createFromDate((int)$startYear, (int)$startMonth, (int)$startDay, 'UTC')->startOfDay();
            $endTime = \Carbon\Carbon::createFromDate((int)$endYear, (int)$endMonth, (int)$endDay, 'UTC')->endOfDay();
            
            // Validate dates are valid
            if (!$startTime || !$endTime) {
                throw new \Exception('Invalid date format');
            }
        } catch (\Exception $e) {
            \Log::error('Date parsing error: ' . $e->getMessage(), [
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'startDateString' => $startDateString,
                'endDateString' => $endDateString,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Please select valid dates.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 422);
        }
        
        // Check if the same user already has a rental for this ATV on the same dates
        // Prevent duplicate requests from the same user
        $userDuplicate = Rental::where('atv_id', $request->atv_id)
            ->where('user_id', $request->user()->id)
            ->whereNotIn('status', [
                Rental::STATUS_DENIED,
                Rental::STATUS_CANCELLED,
                Rental::STATUS_RETURNED
            ])
            ->where(function ($query) use ($startTime, $endTime) {
                // Check for any date overlap/conflict using day-based comparison
                $query->whereRaw('DATE(start_time) <= ?', [$endTime->toDateString()])
                      ->whereRaw('DATE(end_time) >= ?', [$startTime->toDateString()]);
            })
            ->exists();

        if ($userDuplicate) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a rental request for this ATV on the selected dates.',
            ], 422);
        }
        
        // Check for overlapping rentals on this ATV from other users
        // Option 3: Allow multiple pending requests for same dates (from different users)
        // But block if there's an approved/active rental (approved, pending_pickup, rented, pending_return)
        // This allows competition for popular ATVs while preventing double-booking
        // Overlap check: existing rental overlaps if:
        // - existing start is between requested start and end, OR
        // - existing end is between requested start and end, OR
        // - existing rental completely contains the requested period
        $overlapping = Rental::where('atv_id', $request->atv_id)
            ->where('user_id', '!=', $request->user()->id)  // Exclude current user (already checked above)
            ->whereNotIn('status', [
                Rental::STATUS_DENIED, 
                Rental::STATUS_CANCELLED, 
                Rental::STATUS_RETURNED,
                Rental::STATUS_PENDING  // Allow multiple pending requests for same dates
            ])
            ->where(function ($query) use ($startTime, $endTime) {
                // Check for any date overlap/conflict using day-based comparison
                // Two date ranges overlap if: start1 <= end2 AND end1 >= start2
                $query->whereRaw('DATE(start_time) <= ?', [$endTime->toDateString()])
                      ->whereRaw('DATE(end_time) >= ?', [$startTime->toDateString()]);
            })
            ->exists();

        if ($overlapping) {
            return response()->json([
                'success' => false,
                'message' => 'This ATV is already booked for the selected time period.',
            ], 422);
        }

        // Calculate total price based on days
        // For inclusive rental days: Dec 13-14 = 2 days (Dec 13 AND Dec 14)
        // Calculate difference in days and add 1 for inclusive counting
        try {
            // Use diffInDays and add 1 for inclusive counting (Dec 13 to Dec 14 = 2 days)
            $days = $startTime->copy()->startOfDay()->diffInDays($endTime->copy()->startOfDay()) + 1;
            $days = max(1, $days); // Minimum 1 day
            
            // Check if daily_price exists, fallback to hourly_price if needed (for backward compatibility)
            $pricePerDay = $atv->daily_price ?? ($atv->hourly_price ?? 0);
            if ($pricePerDay <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ATV pricing is not configured. Please contact administrator.',
                ], 422);
            }
            
            $totalPrice = $days * $pricePerDay;
        } catch (\Exception $e) {
            \Log::error('Price calculation error: ' . $e->getMessage(), [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'atv_id' => $atv->id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate rental price. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        try {
            $rental = Rental::create([
                'user_id' => $request->user()->id,
                'atv_id' => $request->atv_id,
                'status' => Rental::STATUS_PENDING,
                'start_time' => $startTime,  // Use normalized start time (start of day)
                'end_time' => $endTime,      // Use normalized end time (end of day)
                'total_price' => $totalPrice,
                'notes' => $request->notes,
            ]);

            $rental->load(['user', 'atv']);
        } catch (\Exception $e) {
            \Log::error('Rental creation error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id,
                'atv_id' => $request->atv_id,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create rental. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rental request submitted successfully.',
            'data' => [
                'id' => $rental->id,
                'atv' => [
                    'id' => $rental->atv->id,
                    'name' => $rental->atv->name,
                ],
                'status' => $rental->status,
                'status_label' => $rental->status_label ?? ucfirst($rental->status),
                'start_time' => $rental->start_time ? $rental->start_time->format('Y-m-d') : null,  // Send date only
                'end_time' => $rental->end_time ? $rental->end_time->format('Y-m-d') : null,      // Send date only
                'total_price' => $rental->total_price,
            ],
        ], 201);
    }

    /**
     * Update rental status (Manager/Admin only).
     * This is the key method that handles the rental workflow.
     */
    public function updateStatus(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:' . implode(',', Rental::getStatuses()),
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $rental = Rental::with('atv')->find($id);

        if (!$rental) {
            return response()->json([
                'success' => false,
                'message' => 'Rental not found.',
            ], 404);
        }

        $newStatus = $request->status;
        $currentStatus = $rental->status;

        // Validate status transitions
        $validTransitions = $this->getValidStatusTransitions($currentStatus);
        if (!in_array($newStatus, $validTransitions)) {
            return response()->json([
                'success' => false,
                'message' => "Cannot change status from '{$currentStatus}' to '{$newStatus}'.",
                'valid_transitions' => $validTransitions,
            ], 422);
        }

        // If approving a rental, check for conflicts with existing approved/active rentals
        if ($newStatus === Rental::STATUS_APPROVED) {
            $startTime = $rental->start_time->copy()->startOfDay();
            $endTime = $rental->end_time->copy()->endOfDay();
            
            $conflicting = Rental::where('atv_id', $rental->atv_id)
                ->where('id', '!=', $rental->id) // Exclude the current rental
                ->whereNotIn('status', [
                    Rental::STATUS_DENIED,
                    Rental::STATUS_CANCELLED,
                    Rental::STATUS_RETURNED,
                    Rental::STATUS_PENDING  // Pending rentals don't block approval
                ])
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->whereRaw('DATE(start_time) <= ?', [$endTime->toDateString()])
                          ->whereRaw('DATE(end_time) >= ?', [$startTime->toDateString()]);
                })
                ->exists();

            if ($conflicting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot approve this rental. There is already an approved/active rental for the selected time period.',
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $rental->status = $newStatus;
            if ($request->has('notes')) {
                $rental->notes = $request->notes;
            }
            $rental->save();

            // If approving a rental, automatically deny all other pending rentals for the same ATV and dates
            if ($newStatus === Rental::STATUS_APPROVED) {
                $startTime = $rental->start_time->copy()->startOfDay();
                $endTime = $rental->end_time->copy()->endOfDay();
                
                Rental::where('atv_id', $rental->atv_id)
                    ->where('id', '!=', $rental->id) // Exclude the current rental
                    ->where('status', Rental::STATUS_PENDING)
                    ->where(function ($query) use ($startTime, $endTime) {
                        // Only deny pending rentals that overlap with the approved rental dates
                        $query->whereRaw('DATE(start_time) <= ?', [$endTime->toDateString()])
                              ->whereRaw('DATE(end_time) >= ?', [$startTime->toDateString()]);
                    })
                    ->update(['status' => Rental::STATUS_DENIED]);
            }

            // Update ATV status based on rental status
            $this->updateAtvStatus($rental);

            DB::commit();

            $rental->refresh();
            $rental->load('atv');

            return response()->json([
                'success' => true,
                'message' => 'Rental status updated successfully.',
                'data' => [
                    'id' => $rental->id,
                    'status' => $rental->status,
                    'status_label' => $rental->status_label,
                    'atv_status' => $rental->atv->status,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update rental status.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Customer cancels rental (pending or approved only).
     */
    public function cancel(Request $request, int $id)
    {
        $user = $request->user();
        $rental = Rental::with('atv')->find($id);

        if (!$rental) {
            return response()->json([
                'success' => false,
                'message' => 'Rental not found.',
            ], 404);
        }

        // Verify ownership
        if ($rental->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to cancel this rental.',
            ], 403);
        }

        // Can only cancel when status is 'pending' or 'approved'
        if (!in_array($rental->status, [Rental::STATUS_PENDING, Rental::STATUS_APPROVED])) {
            return response()->json([
                'success' => false,
                'message' => 'Can only cancel rentals with status "pending" or "approved".',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $rental->status = Rental::STATUS_CANCELLED;
            $rental->save();

            // Update ATV status if needed
            $this->updateAtvStatus($rental);

            DB::commit();

            $rental->refresh();
            $rental->load('atv');

            return response()->json([
                'success' => true,
                'message' => 'Rental cancelled successfully.',
                'data' => [
                    'id' => $rental->id,
                    'status' => $rental->status,
                    'status_label' => $rental->status_label,
                    'atv_status' => $rental->atv->status,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel rental.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Customer requests pickup.
     */
    public function requestPickup(Request $request, int $id)
    {
        $user = $request->user();
        $rental = Rental::find($id);

        if (!$rental) {
            return response()->json([
                'success' => false,
                'message' => 'Rental not found.',
            ], 404);
        }

        // Verify ownership
        if ($rental->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to modify this rental.',
            ], 403);
        }

        // Can only request pickup when status is 'approved'
        if ($rental->status !== Rental::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Can only request pickup when rental status is "approved".',
            ], 422);
        }

        $rental->status = Rental::STATUS_PENDING_PICKUP;
        $rental->save();

        return response()->json([
            'success' => true,
            'message' => 'Pickup request submitted. Please wait for manager confirmation.',
            'data' => [
                'id' => $rental->id,
                'status' => $rental->status,
                'status_label' => $rental->status_label,
            ],
        ]);
    }

    /**
     * Customer requests return.
     */
    public function requestReturn(Request $request, int $id)
    {
        $user = $request->user();
        $rental = Rental::find($id);

        if (!$rental) {
            return response()->json([
                'success' => false,
                'message' => 'Rental not found.',
            ], 404);
        }

        // Verify ownership
        if ($rental->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to modify this rental.',
            ], 403);
        }

        // Can only request return when status is 'rented'
        if ($rental->status !== Rental::STATUS_RENTED) {
            return response()->json([
                'success' => false,
                'message' => 'Can only request return when rental status is "rented".',
            ], 422);
        }

        $rental->status = Rental::STATUS_PENDING_RETURN;
        $rental->save();

        return response()->json([
            'success' => true,
            'message' => 'Return request submitted. Please wait for manager confirmation.',
            'data' => [
                'id' => $rental->id,
                'status' => $rental->status,
                'status_label' => $rental->status_label,
            ],
        ]);
    }

    /**
     * Get valid status transitions.
     */
    private function getValidStatusTransitions(string $currentStatus): array
    {
        return match ($currentStatus) {
            Rental::STATUS_PENDING => [Rental::STATUS_APPROVED, Rental::STATUS_DENIED, Rental::STATUS_CANCELLED],
            Rental::STATUS_APPROVED => [Rental::STATUS_PENDING_PICKUP, Rental::STATUS_DENIED, Rental::STATUS_CANCELLED],
            Rental::STATUS_PENDING_PICKUP => [Rental::STATUS_RENTED, Rental::STATUS_CANCELLED],
            Rental::STATUS_RENTED => [Rental::STATUS_PENDING_RETURN, Rental::STATUS_RETURNED],
            Rental::STATUS_PENDING_RETURN => [Rental::STATUS_RETURNED],
            Rental::STATUS_DENIED => [],
            Rental::STATUS_CANCELLED => [],
            Rental::STATUS_RETURNED => [],
            default => [],
        };
    }

    /**
     * Update ATV status based on rental status.
     * This is the key business logic that ties rental status to ATV availability.
     */
    private function updateAtvStatus(Rental $rental): void
    {
        $atv = $rental->atv;

        switch ($rental->status) {
            case Rental::STATUS_RENTED:
                // Mark ATV as rented when rental is marked as picked up
                $atv->status = 'rented';
                $atv->save();
                break;

            case Rental::STATUS_RETURNED:
                // Mark ATV as available when rental is returned
                $atv->status = 'available';
                $atv->save();
                break;

            case Rental::STATUS_DENIED:
            case Rental::STATUS_CANCELLED:
                // If denied or cancelled, ensure ATV stays/becomes available
                // (only if no other active rentals exist)
                $hasOtherActiveRentals = Rental::where('atv_id', $atv->id)
                    ->where('id', '!=', $rental->id)
                    ->whereIn('status', [Rental::STATUS_RENTED])
                    ->exists();

                if (!$hasOtherActiveRentals && $atv->status === 'rented') {
                    $atv->status = 'available';
                    $atv->save();
                }
                break;
        }
    }
}

