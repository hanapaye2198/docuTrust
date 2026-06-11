<?php

namespace App\Services\Notary;

use App\Enums\UserRole;
use App\Models\NotaryCredential;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class NotarySealProfileService
{
    public function trustProfileSealSectionUrl(): string
    {
        return route('settings.trust-profile').'#notary-seal';
    }

    public function activeCredential(User $user): ?NotaryCredential
    {
        if ($user->role !== UserRole::Notary) {
            return null;
        }

        $credential = NotaryCredential::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($credential === null || ! $credential->isActive()) {
            return null;
        }

        return $credential;
    }

    public function hasSealOnFile(User $user): bool
    {
        $credential = $this->activeCredential($user);

        return $credential !== null
            && is_string($credential->seal_image_path)
            && $credential->seal_image_path !== '';
    }

    public function sealImagePath(User $user): ?string
    {
        $credential = $this->activeCredential($user);
        $path = $credential?->seal_image_path;

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function storeSeal(User $user, UploadedFile $file): NotaryCredential
    {
        if ($user->role !== UserRole::Notary) {
            throw new InvalidArgumentException('Only notaries can store a personal seal.');
        }

        $credential = $this->activeCredential($user);

        if ($credential === null) {
            throw new InvalidArgumentException('Active notary credentials are required before uploading a seal.');
        }

        $disk = (string) config('filesystems.docutrust_disk', 'local');
        $path = $file->store('notary/seals', $disk);

        if (filled($credential->seal_image_path) && Storage::disk($disk)->exists($credential->seal_image_path)) {
            Storage::disk($disk)->delete($credential->seal_image_path);
        }

        $credential->update(['seal_image_path' => $path]);

        return $credential->fresh();
    }
}
