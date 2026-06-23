<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index()
    {
        return response()->json(Device::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'ip_address' => 'required|ip',
            'port' => 'required|integer',
            'serial_number' => 'nullable|string|unique:devices',
        ]);

        $device = Device::create($validated);

        return response()->json($device, 201);
    }

    public function show(Device $device)
    {
        return response()->json($device);
    }

    public function update(Request $request, Device $device)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'ip_address' => 'required|ip',
            'port' => 'required|integer',
            'status' => 'nullable|string|in:online,offline',
        ]);

        $device->update($validated);

        return response()->json($device);
    }

    public function destroy(Device $device)
    {
        $device->delete();
        return response()->json(null, 204);
    }
}
