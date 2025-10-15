<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserSettingsController extends Controller
{
    /**
     * Get user settings
     */
    public function index()
    {
        $user = Auth::user();

        // Get or create user settings with defaults
        $settings = UserSettings::firstOrCreate(
            ['user_id' => $user->id],
            [
                'notify_new_matches' => true,
                'notify_application_updates' => true,
                'email_digest_frequency' => 'daily',
                'min_salary' => 100,
                'max_salary' => 150,
                'employment_types' => ['fullTime' => true, 'contract' => false, 'partTime' => false],
                'work_arrangement' => ['remote' => true, 'hybrid' => true, 'onsite' => false],
                'willing_to_relocate' => false,
                'queue_auto_apply' => false,
                'autonomous_auto_apply' => false,
                'max_applications_per_day' => 10,
                'show_to_recruiters' => true,
                'hide_from_current_employer' => false,
            ]
        );

        return response()->json($settings);
    }

    /**
     * Update user settings
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'notify_new_matches' => 'boolean',
            'notify_application_updates' => 'boolean',
            'email_digest_frequency' => 'in:immediate,daily,weekly',
            'min_salary' => 'integer|min:0|max:1000',
            'max_salary' => 'integer|min:0|max:1000',
            'preferred_location' => 'nullable|string|max:255',
            'preferred_job_title' => 'nullable|string|max:255',
            'employment_types' => 'array',
            'employment_types.fullTime' => 'boolean',
            'employment_types.contract' => 'boolean',
            'employment_types.partTime' => 'boolean',
            'work_arrangement' => 'array',
            'work_arrangement.remote' => 'boolean',
            'work_arrangement.hybrid' => 'boolean',
            'work_arrangement.onsite' => 'boolean',
            'willing_to_relocate' => 'boolean',
            'queue_auto_apply' => 'boolean',
            'autonomous_auto_apply' => 'boolean',
            'max_applications_per_day' => 'integer|min:1|max:100',
            'show_to_recruiters' => 'boolean',
            'hide_from_current_employer' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $settings = UserSettings::updateOrCreate(
            ['user_id' => $user->id],
            $request->all()
        );

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }
}
