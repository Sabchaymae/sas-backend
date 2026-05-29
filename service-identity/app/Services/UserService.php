<?php

namespace App\Services;

use App\Models\User;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserService
{
    /**
     * Get all users with optional filtering.
     */
    public function getAllUsers(array $filters = [])
    {
        $query = User::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        return $query->latest()->get();
    }

    /**
     * Create a new user.
     */
    public function createUser(array $data)
    {
        if (isset($data['photo']) && $data['photo'] instanceof \Illuminate\Http\UploadedFile) {
            $data['photo'] = $data['photo']->store('avatars', 'public');
        }

        $data['password'] = Hash::make($data['password']);
        
        if (empty($data['statut'])) {
            $data['statut'] = 'active';
        }

        $user = User::create($data);

        // Log the activity
        ActivityLogService::log(
            ActivityLog::TYPE_CREATION,
            "Création de l'utilisateur: {$user->full_name}",
            ActivityLog::MODULE_USERS,
            "Un nouvel utilisateur avec l'email {$user->email} a été créé par le système."
        );

        return $user;
    }

    /**
     * Update an existing user.
     */
    public function updateUser(User $user, array $data)
    {
        if (isset($data['photo']) && $data['photo'] instanceof \Illuminate\Http\UploadedFile) {
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            $data['photo'] = $data['photo']->store('avatars', 'public');
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Si le rôle change, on réinitialise les permissions spécifiques
        if (isset($data['role']) && $data['role'] !== $user->role) {
            $user->permissions()->detach();
        }

        $user->update($data);

        // Log the activity
        ActivityLogService::log(
            ActivityLog::TYPE_MODIFICATION,
            "Modification de l'utilisateur: {$user->full_name}",
            ActivityLog::MODULE_USERS,
            "Les informations de l'utilisateur {$user->email} ont été mises à jour."
        );

        return $user;
    }

    /**
     * Delete a user.
     */
    public function deleteUser(User $user)
    {
        $userName = $user->full_name;
        $userEmail = $user->email;

        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        $result = $user->delete();

        // Log the activity
        ActivityLogService::log(
            ActivityLog::TYPE_SUPPRESSION,
            "Suppression de l'utilisateur: {$userName}",
            ActivityLog::MODULE_USERS,
            "L'utilisateur avec l'email {$userEmail} a été supprimé du système."
        );

        return $result;
    }
}
