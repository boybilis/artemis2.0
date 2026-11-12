<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfilePictures
{
    public const RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'];

    public function url(User $user): ?string
    {
        return $user->profile_picture_path ? route('profile.picture.show', $user, false) : null;
    }

    public function save(User $user, UploadedFile $picture, array $attributes = []): void
    {
        $previous = $user->profile_picture_path;
        $path = $picture->store("profile-pictures/{$user->id}", 'local');
        if (!$path) {
            throw ValidationException::withMessages(['profile_picture' => 'Unable to save the profile picture. Please try again.']);
        }

        try {
            $user->fill($attributes);
            $user->profile_picture_path = $path;
            $user->saveOrFail();
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($previous && str_starts_with($previous, "profile-pictures/{$user->id}/")) {
            Storage::disk('local')->delete($previous);
        }
    }
}
