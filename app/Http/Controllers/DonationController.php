<?php

namespace App\Http\Controllers;

use App\Models\SiteDonation;
use App\Models\DonationGoal;
use App\Models\SelfRaisedMoney;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class DonationController extends Controller
{
    public function index()
    {
        $currentYear = now()->year;

        // Get only approved donations and self-raised money
        $donations = SiteDonation::approved()->orderBy('donation_date', 'desc')->get();
        $selfRaisedMoney = SelfRaisedMoney::orderBy('earned_date', 'desc')->get();
        $goal = DonationGoal::where('year', $currentYear)->first();
        $allGoals = DonationGoal::all()->keyBy('year'); // Get all goals indexed by year

        // Get exchange rates (cached for 1 hour)
        $rates = $this->getExchangeRates();

        return Inertia::render('Donations/Index', [
            'donations' => $donations,
            'selfRaisedMoney' => $selfRaisedMoney,
            'goal' => $goal ?? ['yearly_goal' => 1200, 'currency' => 'EUR', 'year' => $currentYear],
            'allGoals' => $allGoals,
            'currentYear' => $currentYear,
            'exchangeRates' => $rates,
        ]);
    }

    public function getProgress()
    {
        $currentYear = now()->year;

        // Get current year's approved donations and self-raised money
        $donations = SiteDonation::approved()->whereYear('donation_date', $currentYear)->get();
        $selfRaisedMoney = SelfRaisedMoney::whereYear('earned_date', $currentYear)->get();
        $goal = DonationGoal::where('year', $currentYear)->first();

        // Get exchange rates
        $rates = $this->getExchangeRates();

        // Calculate total in EUR
        $donationsEUR = 0;
        $selfRaisedEUR = 0;

        foreach ($donations as $donation) {
            $donationsEUR += $this->convertToEUR($donation->amount, $donation->currency, $rates);
        }

        foreach ($selfRaisedMoney as $money) {
            $selfRaisedEUR += $this->convertToEUR($money->amount, $money->currency, $rates);
        }

        $totalEUR = $donationsEUR + $selfRaisedEUR;
        $yearlyGoal = $goal ? $goal->yearly_goal : 1200;
        $percentage = min(($totalEUR / $yearlyGoal) * 100, 100);

        return response()->json([
            'total' => round($totalEUR, 2),
            'donations' => round($donationsEUR, 2),
            'selfRaised' => round($selfRaisedEUR, 2),
            'goal' => $yearlyGoal,
            'percentage' => round($percentage, 1),
            'currency' => 'EUR',
        ]);
    }

    private function getExchangeRates()
    {
        // Cache exchange rates for 24 hours (86400 seconds)
        return Cache::remember('exchange_rates_v4', 86400, function () {
            try {
                // Using exchangerate-api.com (free tier: 1500 requests/month)
                $response = Http::get('https://api.exchangerate-api.com/v4/latest/EUR');

                if ($response->successful()) {
                    $data = $response->json();
                    // Return all available rates from the API
                    $rates = $data['rates'] ?? [];
                    $rates['EUR'] = 1; // Ensure EUR is always 1 (base currency)
                    return $rates;
                }
            } catch (\Exception $e) {
                // Silent failure, will use fallback rates
            }

            // Fallback static rates (only most common currencies)
            return [
                'EUR' => 1,
                'USD' => 1.1,
                'GBP' => 0.85,
                'CZK' => 25,
                'PLN' => 4.3,
                'CAD' => 1.5,
                'AUD' => 1.65,
                'JPY' => 160,
                'CHF' => 0.95,
                'SEK' => 11.5,
                'NOK' => 11.8,
                'DKK' => 7.45,
            ];
        });
    }

    private function convertToEUR($amount, $currency, $rates)
    {
        if ($currency === 'EUR') {
            return $amount;
        }

        // Convert to EUR: amount / rate
        return $amount / $rates[$currency];
    }
}
