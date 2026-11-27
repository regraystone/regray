<?php

namespace App\Actions\Profile;

use App\Data\WrittenFiles;
use App\Models\User;
use App\Support\PublicImageStore;
use Illuminate\Http\UploadedFile;

/**
 * Set or remove a client's profile photo, kept on their profile
 * (user_profiles.avatar_path) on the public disk under a random name. A
 * client with no profile yet gets one with the photo alone, its phone and
 * address blank, as UpdateClientCompanyAction does for a company. The photo
 * it replaces is deleted once the new one is saved; removing one opens no
 * profile where none is.
 */
class UpdateClientPhotoAction
{
    public function __construct(
        public readonly PublicImageStore $images,
    ) {}

    public function execute(User $client, ?UploadedFile $photo): void
    {
        $profile = $client->profile;
        $previous = $profile?->avatar_path;

        if ($photo === null) {
            if ($profile !== null && $previous !== null) {
                $profile->update(['avatar_path' => null]);
                $this->images->delete([$previous]);
            }

            return;
        }

        $this->images->writing(function (WrittenFiles $written) use ($client, $profile, $photo): void {
            $path = $this->images->store($photo, config('profile.photo_directory'), $written);

            if ($profile !== null) {
                $profile->update(['avatar_path' => $path]);
            } else {
                $client->setRelation('profile', $client->profile()->create(['phone' => '', 'address' => '', 'avatar_path' => $path]));
            }
        });

        $this->images->delete([$previous]);
    }
}
