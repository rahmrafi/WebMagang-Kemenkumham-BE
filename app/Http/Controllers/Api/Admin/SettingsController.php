<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    /**
     * Get all settings as a key-value object
     */
    public function index()
    {
        $settings = Cache::rememberForever('app_settings', function () {
            return Setting::all()->pluck('value', 'key');
        });
        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'pejabat_name' => 'required|string|max:150',
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Cache::forget('app_settings');

        return response()->json([
            'status' => 'success',
            'message' => 'Pengaturan berhasil diperbarui'
        ]);
    }
}
