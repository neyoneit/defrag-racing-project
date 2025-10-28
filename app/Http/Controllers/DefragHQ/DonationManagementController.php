<?php

namespace App\Http\Controllers\DefragHQ;

use App\Http\Controllers\Controller;
use App\Models\SiteDonation;
use App\Models\DonationGoal;
use App\Models\SelfRaisedMoney;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DonationManagementController extends Controller
{
    public function index()
    {
        $currentYear = now()->year;

        $donations = SiteDonation::orderBy('donation_date', 'desc')->get();
        $selfRaisedMoney = SelfRaisedMoney::orderBy('earned_date', 'desc')->get();
        $goal = DonationGoal::where('year', $currentYear)->first();

        return Inertia::render('DefragHQ/DonationManagement/Index', [
            'donations' => $donations,
            'selfRaisedMoney' => $selfRaisedMoney,
            'goal' => $goal,
            'currentYear' => $currentYear,
        ]);
    }

    // Store donation
    public function storeDonation(Request $request)
    {
        $validated = $request->validate([
            'donor_name' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|in:EUR,USD,CZK',
            'donation_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        SiteDonation::create($validated);

        return redirect()->back()->with('success', 'Donation added successfully!');
    }

    // Update donation
    public function updateDonation(Request $request, SiteDonation $donation)
    {
        $validated = $request->validate([
            'donor_name' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|in:EUR,USD,CZK',
            'donation_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        $donation->update($validated);

        return redirect()->back()->with('success', 'Donation updated successfully!');
    }

    // Delete donation
    public function deleteDonation(SiteDonation $donation)
    {
        $donation->delete();

        return redirect()->back()->with('success', 'Donation deleted successfully!');
    }

    // Store self-raised money
    public function storeSelfRaised(Request $request)
    {
        $validated = $request->validate([
            'source' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|in:EUR,USD,CZK',
            'earned_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        SelfRaisedMoney::create($validated);

        return redirect()->back()->with('success', 'Self-raised money added successfully!');
    }

    // Update self-raised money
    public function updateSelfRaised(Request $request, SelfRaisedMoney $selfRaised)
    {
        $validated = $request->validate([
            'source' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|in:EUR,USD,CZK',
            'earned_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        $selfRaised->update($validated);

        return redirect()->back()->with('success', 'Self-raised money updated successfully!');
    }

    // Delete self-raised money
    public function deleteSelfRaised(SelfRaisedMoney $selfRaised)
    {
        $selfRaised->delete();

        return redirect()->back()->with('success', 'Self-raised money deleted successfully!');
    }

    // Update donation goal
    public function updateGoal(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer',
            'yearly_goal' => 'required|numeric|min:0',
            'currency' => 'required|in:EUR,USD,CZK',
        ]);

        DonationGoal::updateOrCreate(
            ['year' => $validated['year']],
            $validated
        );

        return redirect()->back()->with('success', 'Donation goal updated successfully!');
    }
}
