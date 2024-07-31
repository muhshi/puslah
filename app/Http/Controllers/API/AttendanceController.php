<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    public function getAttendanceToday()
    {
        $userId = Auth::user()->id;
        $today = now()->toDateString();
        $currentMonth = now()->month;

        $attendanceToday = Attendance::select('start_time', 'end_time')
            ->where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->first();

        $attendanceThisMonth = Attendance::select('start_time', 'end_time', 'created_at')
            ->where('user_id', $userId)
            ->whereMonth('created_at', $currentMonth)
            ->get()
            ->map(function ($attendance) {
                return [
                    'start_time' => $attendance->start_time,
                    'end_time' => $attendance->end_time,
                    'date' => $attendance->created_at->toDateString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'today' => $attendanceToday,
                'this_month' => $attendanceThisMonth,
            ],
            'message' => 'Attendance retrieved successfully.',
        ]);
    }

    public function getSchedule()
    {

        $schedule = Schedule::with('office', 'shift')->where('user_id', Auth::user()->id)->first();

        if ($schedule->is_banned) {
            return response()->json([
                'success' => false,
                'message' => 'You are banned.',
                'data' => null
            ]);
        }
        return response()->json([
            'success' => true,
            'data' => $schedule,
            'message' => 'Schedule retrieved successfully.'
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()
            ], 400);
        }

        $schedule = Schedule::where('user_id', Auth::user()->id)->first();

        if ($schedule) {
            $attendance = Attendance::where('user_id', Auth::user()->id)->whereDate('created_at', date('Y-m-d'))->first();
            if (!$attendance) {
                $attendance = Attendance::create([
                    'user_id' => Auth::user()->id,
                    'schedule_latitude' => $schedule->office->latitude,
                    'schedule_longitude' => $schedule->office->longitude,
                    'schedule_start_time' => $schedule->shift->start_time,
                    'schedule_end_time' => $schedule->shift->end_time,
                    'start_latitude' => $request->latitude,
                    'start_longitude' => $request->longitude,
                    'start_time' => Carbon::now('Asia/Jakarta')->toTimeString(),
                    'end_time' => Carbon::now('Asia/Jakarta')->toTimeString(),
                ]);
            } else {
                $attendance->update([
                    'end_latitude' => $request->latitude,
                    'end_longitude' => $request->longitude,
                    'end_time' => Carbon::now('Asia/Jakarta')->toTimeString(),
                ]);
            }
            return response()->json([
                'success' => true,
                'data' => $attendance,
                'message' => 'Attendance created successfully.',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Schedule not found.',
            ]);
        }
    }

    public function getAttendanceByMonthAndYear($month, $year)
    {
        $validator = Validator::make(['month' => $month, 'year' => $year], [
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2023|max:' . date('Y'),
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()
            ], 400);
        }

        $EuserId = Auth::user()->id;
        $attendanceList = Attendance::select('start_time', 'end_time', 'created_at')
            ->where('user_id', $EuserId)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->get()
            ->map(function ($attendance) {
                return [
                    'start_time' => $attendance->start_time,
                    'end_time' => $attendance->end_time,
                    'date' => $attendance->created_at->toDateString(),
                ];
            });
        return response()->json([
            'success' => true,
            'data' => $attendanceList,
            'message' => 'Attendance retrieved successfully.',
        ]);
    }

    public function banned()
    {
        $schedule = Schedule::where('user_id', Auth::user()->id)->first();
        if ($schedule) {
            $schedule->update([
                'is_banned' => true,
            ]);
            return response()->json([
                'success' => true,
                'data' => $schedule,
                'message' => 'Schedule banned successfully.',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Schedule not found.',
            ]);
        }
    }
}
