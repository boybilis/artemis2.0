<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProfilePictures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilePictureController extends Controller
{
    public function current(Request $request, ProfilePictures $pictures)
    {
        return response()->json(['url' => $pictures->url($request->user())])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, ProfilePictures $pictures)
    {
        $request->validate(['profile_picture' => ['required', ...ProfilePictures::RULES]]);
        $user = $request->user();
        $pictures->save($user, $request->file('profile_picture'));

        return response()->json(['url' => $pictures->url($user), 'message' => 'Profile picture saved.']);
    }

    public function show(Request $request, User $user)
    {
        abort_unless($request->user()->is($user) || $request->user()->hasAdminAccess(), 404);
        $path = $user->profile_picture_path;
        $disk = Storage::disk('local');
        abort_unless($path && str_starts_with($path, "profile-pictures/{$user->id}/") && $disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
