<?php

namespace App\Http\Controllers;

use App\Models\SiteDonation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    /**
     * Handle PayPal IPN (Instant Payment Notification) webhook
     *
     * To set up:
     * 1. Go to PayPal Developer Dashboard
     * 2. Navigate to your app settings
     * 3. Add webhook URL: https://yourdomain.com/api/paypal/webhook
     * 4. Subscribe to event: PAYMENT.SALE.COMPLETED
     */
    public function handleWebhook(Request $request)
    {
        // Log the incoming webhook for debugging
        Log::info('PayPal Webhook Received', $request->all());

        try {
            // Extract payment data
            $eventType = $request->input('event_type');

            // Handle payment completion
            if ($eventType === 'PAYMENT.SALE.COMPLETED') {
                $payment = $request->input('resource');

                // Create donation record with pending status
                SiteDonation::create([
                    'donor_name' => $payment['payer']['payer_info']['email'] ?? 'Anonymous',
                    'amount' => $payment['amount']['total'],
                    'currency' => $payment['amount']['currency'],
                    'donation_date' => now(),
                    'note' => 'PayPal donation - Transaction ID: ' . ($payment['id'] ?? 'unknown'),
                    'status' => 'pending',
                ]);

                Log::info('PayPal donation created', [
                    'amount' => $payment['amount']['total'],
                    'currency' => $payment['amount']['currency'],
                ]);
            }

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('PayPal Webhook Error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
