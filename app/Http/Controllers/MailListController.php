<?php

namespace App\Http\Controllers;

use App\Models\BetaUserMailingList;
use App\Models\BetaAccessToken;
use App\Mail\BetaWelcomeEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class MailListController extends Controller
{
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'unique:beta_users_mailing_list,email'],
            'name' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'additional_data' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The email address is already subscribed or invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Store in database
            $additionalData = $request->additional_data;
            if (is_string($additionalData)) {
                $additionalData = json_decode($additionalData, true);
            }

            $betaUser = BetaUserMailingList::create([
                'email' => $request->email,
                'name' => $request->name,
                'organization' => $request->organization,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'additional_data' => $additionalData,
            ]);

            // Generate beta access token
            $accessToken = BetaAccessToken::generateToken($request->email);

            // Send welcome email with access token to user
            Mail::to($request->email)->send(new BetaWelcomeEmail($accessToken, null));

            // Send notification email to admins
            $this->notifyAdmins($betaUser);

            return response()->json([
                'message' => 'Successfully subscribed to early access!',
                'data' => [
                    'email' => $betaUser->email,
                    'name' => $betaUser->name,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Beta mailing list subscription failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }

    private function notifyAdmins(BetaUserMailingList $betaUser)
    {
        try {
            $adminEmails = explode(',', env('ADMIN_EMAILS', ''));
            $adminEmails = array_map('trim', $adminEmails);

            foreach ($adminEmails as $adminEmail) {
                if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                    $isEnterprise = isset($betaUser->additional_data['formType']) && $betaUser->additional_data['formType'] === 'enterprise';
                    $subject = $isEnterprise ? '[AppliFlow] New Enterprise Lead: ' : '[AppliFlow] New Beta User: ';

                    $emailBody = ($isEnterprise ? "New enterprise lead!\n\n" : "New beta user signup!\n\n") .
                        "Email: {$betaUser->email}\n" .
                        "Name: " . ($betaUser->name ?: 'Not provided') . "\n";

                    if ($betaUser->organization) {
                        $emailBody .= "Organization: {$betaUser->organization}\n";
                    }

                    if ($isEnterprise && isset($betaUser->additional_data['pricingTier'])) {
                        $emailBody .= "Interested in: {$betaUser->additional_data['pricingTier']} Plan\n";
                    }

                    if ($isEnterprise && isset($betaUser->additional_data['additionalDetails']) && !empty($betaUser->additional_data['additionalDetails'])) {
                        $emailBody .= "Additional Details: {$betaUser->additional_data['additionalDetails']}\n";
                    }

                    $emailBody .= "IP: {$betaUser->ip_address}\n" .
                        "User Agent: {$betaUser->user_agent}\n" .
                        "Signed up at: {$betaUser->created_at->format('Y-m-d H:i:s T')}\n\n" .
                        "Total signups: " . BetaUserMailingList::count() . "\n" .
                        "Enterprise leads: " . BetaUserMailingList::whereJsonContains('additional_data->formType', 'enterprise')->count();

                    Mail::raw(
                        $emailBody,
                        function ($message) use ($adminEmail, $betaUser, $subject) {
                            $message->to($adminEmail)
                                    ->subject($subject . $betaUser->email);
                        }
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send admin notification for beta signup', [
                'beta_user_id' => $betaUser->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
