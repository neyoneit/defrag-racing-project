<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

use App\Rules\MddProfile;
use App\Models\User;
use App\Models\Record;
use App\Models\RecordHistory;
use App\External\ImageGenerator;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SettingsController extends Controller
{
    public function socialmedia(Request $request) {
        $user = $request->user();

        if ($request->has('twitter_name')) {
            $user->twitter_name = $request->twitter_name;
        }

        if ($request->has('twitch_name')) {
            $user->twitch_name = $request->twitch_name;
        }

        if ($request->has('discord_name')) {
            $user->discord_name = $request->discord_name;
        }

        $user->save();
    }

    public function notifications(Request $request) {
        $request->validate([
            'records_vq3'       =>      ['required', 'string', 'in:all,wr'],
            'records_cpm'       =>      ['required', 'string', 'in:all,wr'],
            'preview_records'   =>      ['required', 'string', 'in:all,wr,none'],
            'preview_system'    =>      ['required', 'array'],
            'preview_system.*'  =>      ['string', 'in:announcement,clan,tournament']
        ]);

        $user = $request->user();

        $defrag_news = $request->input('defrag_news', false);
        $tournament_news = $request->input('tournament_news', false);
        $clan_notifications = $request->input('clan_notifications', false);

        $user->defrag_news = $defrag_news;
        $user->tournament_news = $tournament_news;
        $user->clan_notifications = $clan_notifications;

        $user->records_vq3 = $request->records_vq3;
        $user->records_cpm = $request->records_cpm;

        // Ensure announcement is always in preview_system
        $preview_system = $request->preview_system;
        if (!in_array('announcement', $preview_system)) {
            $preview_system[] = 'announcement';
        }
        $user->preview_system = $preview_system;
        $user->preview_records = $request->preview_records;

        $user->save();
    }

    public function preferences(Request $request) {
        $user = $request->user();

        if ($request->has('color')) {
            if (! preg_match('/^#[a-fA-F0-9]{6}$/', $request->color)) {
                return;
            }
            $user->color = $request->color;
        }

        if ($request->has('avatar_effect')) {
            $user->avatar_effect = $request->avatar_effect;
        }

        if ($request->has('name_effect')) {
            $user->name_effect = $request->name_effect;
        }

        if ($request->has('avatar_border_color')) {
            if (! preg_match('/^#[a-fA-F0-9]{6}$/', $request->avatar_border_color)) {
                return;
            }
            $user->avatar_border_color = $request->avatar_border_color;
        }

        $user->save();
    }

    public function generate (Request $request) {
        $request->validate([
            'profile_link' => ['required', 'string', 'url:http,https', new MddProfile]
        ]);

        $validation = $this->validate_link($request->user(), $request->profile_link);

        if ($validation !== NULL) {
            return [
                'success'   =>  false,
                'message'   =>  $validation
            ];
        }

        $id = $this->get_profile_id($request->profile_link);

        $generator = new ImageGenerator();

        return [
            'success'       =>      true,
            'image'         =>      $generator->generate($id),
            'name'          =>      $generator->get_name($id)
        ];
    }

    public function get_profile_id($profile_link) {
        $queryString = parse_url($profile_link, PHP_URL_QUERY);
        parse_str($queryString, $params);

        if (! isset($params['id']) || ! ctype_digit($params['id'])) {
            return -1;
        }

        $id = $params['id'];

        return $id;
    }

    public function validate_link($user, $profile_link) {
        if (ctype_digit($user->mdd_id)) {
            return 'Your account is already linked to ' . $user->mdd_id . ' MDD Profile.';
        }
        
        $id = $this->get_profile_id($profile_link);

        if ($id === -1) {
            return 'The link doesn\'t have a valid id.';
        }

        $user = User::where('mdd_id', $id)->first();

        if ($user) {
            return 'There is another user who linked his account to this MDD Profile.';
        }

        $client = new Client();

        $found = false;

        try {
            $response = $client->head($profile_link, ['allow_redirects' => false]);
            $statusCode = $response->getStatusCode();

            $found = $statusCode === 200;
        } catch (RequestException $e) {
            $found = false;
        }

        if (! $found) {
            return 'This profile doesnt Exist on Q3DF, are you sure you posted the correct link ?';
        }

        return null;
    }

    public function verify(Request $request) {
        $request->validate([
            'profile_link' => ['required', 'string', 'url:http,https', new MddProfile]
        ]);

        $validation = $this->validate_link($request->user(), $request->profile_link);

        if ($validation !== NULL) {
            return [
                'success'   =>  false,
                'message'   =>  $validation
            ];
        }

        $id = $this->get_profile_id($request->profile_link);

        $generator = new ImageGenerator();
        $verification = $generator->verify($id);
        
        if (! $verification) {
            return [
                'success'       =>      false
            ];
        }

        $request->user()->update([
            'mdd_id'    =>  $id
        ]);

        Record::where('mdd_id', $id)->update([
            'user_id'   =>  $request->user()->id
        ]);

        RecordHistory::where('mdd_id', $id)->update([
            'user_id'   =>  $request->user()->id
        ]);

        return [
            'success'   =>      true,
            'mdd_id'    =>      $id
        ];
    }

    public function background(Request $request) {
        $request->validate([
            'background' => ['required', 'image', 'max:10240'] // Max 10MB
        ]);

        $user = $request->user();

        // Delete old background if exists
        if ($user->profile_background_path) {
            Storage::disk('public')->delete($user->profile_background_path);
        }

        // Store new background
        $path = $request->file('background')->store('profile-backgrounds', 'public');
        $user->profile_background_path = $path;
        $user->save();
    }

    public function deleteBackground(Request $request) {
        $user = $request->user();

        if ($user->profile_background_path) {
            Storage::disk('public')->delete($user->profile_background_path);
            $user->profile_background_path = null;
            $user->save();
        }
    }
}
