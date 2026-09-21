<?php

use App\Http\Controllers\Api\V1\AdminPlatformController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BranchOperatingHourController;
use App\Http\Controllers\Api\V1\CounterController;
use App\Http\Controllers\Api\V1\CounterWorkflowController;
use App\Http\Controllers\Api\V1\CustomerBranchController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\PublicDisplayController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\StaffAssignmentController;
use App\Http\Controllers\Api\V1\StaffCounterController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketServiceFlowController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        try {
            DB::select('SELECT 1');
        } catch (Throwable) {
            return response()->json([
                'error' => [
                    'code' => 'database_unavailable',
                    'message' => 'The database is unavailable.',
                    'details' => (object) [],
                ],
            ], 503);
        }

        return response()->json(['data' => ['status' => 'ok', 'database' => 'up']]);
    });

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/public/branches/{branch}/display', [PublicDisplayController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/customer/branches', [CustomerBranchController::class, 'index']);
        Route::get('/customer/branches/{branch}', [CustomerBranchController::class, 'show']);
        Route::get('/tickets/active', [TicketController::class, 'active']);
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::post('/tickets', [TicketController::class, 'store']);
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
        Route::post('/tickets/{ticket}/cancel', [TicketController::class, 'cancel']);
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::post('/tickets/{ticket}/check-in', [TicketServiceFlowController::class, 'checkIn']);
        Route::post('/tickets/{ticket}/transfer', [TicketServiceFlowController::class, 'transfer']);
        Route::post('/tickets/{ticket}/priority', [TicketServiceFlowController::class, 'priority']);
        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::apiResource('branches', BranchController::class);
        Route::apiResource('branches.services', ServiceController::class);
        Route::apiResource('branches.counters', CounterController::class);
        Route::apiResource('branches.operating-hours', BranchOperatingHourController::class)
            ->parameters(['operating-hours' => 'operatingHour']);
        Route::apiResource('branches.staff-assignments', StaffAssignmentController::class)
            ->except(['update'])
            ->parameters(['staff-assignments' => 'staffAssignment']);
        Route::get('/staff/counters', [StaffCounterController::class, 'index']);
        Route::get('/staff/counters/{counter}/queue', [CounterWorkflowController::class, 'show']);
        Route::post('/staff/counters/{counter}/call-next', [CounterWorkflowController::class, 'callNext']);
        Route::post('/staff/counters/{counter}/pause', [CounterWorkflowController::class, 'pause']);
        Route::post('/staff/counters/{counter}/tickets/{ticket}/{action}', [CounterWorkflowController::class, 'transition'])
            ->whereIn('action', ['recall', 'serve', 'skip', 'restore', 'complete']);
        Route::get('/dashboard/{branch}', [DashboardController::class, 'show']);
        Route::get('/reports/{branch}', [ReportController::class, 'show']);
        Route::get('/reports/{branch}/csv', [ReportController::class, 'csv']);
        Route::get('/reports/{branch}/pdf', [ReportController::class, 'pdf']);
        Route::get('/admin/overview', [AdminPlatformController::class, 'overview']);
        Route::get('/admin/users', [AdminPlatformController::class, 'users']);
        Route::post('/admin/users', [AdminPlatformController::class, 'store']);
        Route::patch('/admin/users/{user}', [AdminPlatformController::class, 'update']);
        Route::get('/admin/activity-logs', [AdminPlatformController::class, 'activity']);
        Route::get('/admin/branches', [AdminPlatformController::class, 'branches']);
        Route::post('/admin/branches', [AdminPlatformController::class, 'storeBranch']);
        Route::patch('/admin/branches/{branch}', [AdminPlatformController::class, 'updateBranch']);
        Route::get('/admin/operations', [AdminPlatformController::class, 'operations']);
        Route::post('/admin/tickets/{ticket}/cancel', [AdminPlatformController::class, 'cancelTicket']);
        Route::post('/admin/appointments/{appointment}/cancel', [AdminPlatformController::class, 'cancelAppointment']);
    });
});
