<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KioskAccessController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();

        $deviceUuid = trim((string) $request->query('device_uuid', ''));

        $deviceQuery = DB::table('access_devices')
            ->leftJoin('areas', 'areas.id', '=', 'access_devices.area_id')
            ->where('access_devices.school_id', $user->school_id)
            ->whereIn('access_devices.device_type', ['kiosk', 'scanner', 'door_controller'])
            ->where('access_devices.status', 'active');

        if ($deviceUuid !== '') {
            $deviceQuery->where('access_devices.device_uuid', $deviceUuid);
        }

        if (! in_array($user->role, ['superadmin', 'school_admin', 'director'], true)) {
            $deviceQuery->where(function ($query) use ($user) {
                $query->where('access_devices.assigned_to_user_id', $user->id)
                    ->orWhereNull('access_devices.assigned_to_user_id');
            });
        }

        $device = $deviceQuery
            ->select(
                'access_devices.*',
                'areas.name as area_name',
                'areas.type as area_type'
            )
            ->orderBy('access_devices.id')
            ->first();

        return view('kiosk.access', compact('device'));
    }
}