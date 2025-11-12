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

        // Get or create user settings with defaults (no filter defaults)
        $settings = UserSettings::firstOrCreate(
            ['user_id' => $user->id],
            [
                'notify_new_matches' => true,
                'notify_application_updates' => true,
                'email_digest_frequency' => 'daily',
                'min_salary' => null,
                'max_salary' => null,
                'employment_types' => null,
                'work_arrangement' => null,
                'willing_to_relocate' => false,
                'queue_auto_apply' => false,
                'autonomous_auto_apply' => false,
                'max_applications_per_day' => 10,
                'show_to_recruiters' => true,
                'hide_from_current_employer' => false,
                'auto_apply_enabled' => false,
                'auto_apply_frequency' => 'weekly',
                'auto_apply_max_per_period' => 10,
                'auto_apply_relevance' => 'high',
            ]
        );

        // Include user-level preferences
        return response()->json([
            'settings' => $settings,
            'user' => [
                'timezone' => $user->timezone,
                'match_email_frequency' => $user->match_email_frequency,
                'last_match_email_sent_at' => $user->last_match_email_sent_at,
                'match_email_count' => $user->match_email_count,
            ]
        ]);
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
            'auto_apply_enabled' => 'boolean',
            'auto_apply_frequency' => 'in:hourly,daily,weekly',
            'auto_apply_max_per_period' => 'integer|min:1|max:25',
            'auto_apply_relevance' => 'in:high,medium,broad',
            'notification_frequency' => 'in:realtime,daily,weekly,none',
            'timezone' => 'nullable|string|timezone',
            'match_email_frequency' => 'in:daily,weekly,never',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        // Update UserSettings
        $settingsData = $request->except(['timezone', 'match_email_frequency']);

        // Always force auto_apply_frequency to 'hourly'
        $settingsData['auto_apply_frequency'] = 'hourly';

        $settings = UserSettings::updateOrCreate(
            ['user_id' => $user->id],
            $settingsData
        );

        // Update User model for timezone and email preferences
        if ($request->has('timezone')) {
            $user->timezone = $request->timezone;
        }
        if ($request->has('match_email_frequency')) {
            $user->match_email_frequency = $request->match_email_frequency;
        }
        $user->save();

        return response()->json([
            'success' => true,
            'settings' => $settings,
            'user' => [
                'timezone' => $user->timezone,
                'match_email_frequency' => $user->match_email_frequency,
            ]
        ]);
    }
}
