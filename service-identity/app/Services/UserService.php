<?php

namespace App\Services;

use App\Models\User;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        // Génération automatique du mot de passe si non fourni
        $plainPassword = $data['password'] ?? Str::random(10);
        $data['password'] = Hash::make($plainPassword);
        
        // Toujours forcer le changement de mot de passe pour les nouveaux utilisateurs
        $data['must_change_password'] = true;
        
        if (empty($data['statut'])) {
            $data['statut'] = 'active';
        }

        // L'identifiant est généré automatiquement dans le modèle User (méthode booted)
        $user = User::create($data);
        
        // On attache le mot de passe en clair pour l'affichage initial (non persisté)
        $user->generated_password = $plainPassword;

        // Log the activity
        ActivityLogService::log(
            ActivityLog::TYPE_CREATION,
            "Création de l'utilisateur: {$user->full_name}",
            ActivityLog::MODULE_USERS,
            "Un nouvel utilisateur avec l'identifiant {$user->identifiant} a été créé."
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
