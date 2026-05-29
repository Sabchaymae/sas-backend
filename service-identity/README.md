# 🔐 Oriotel Identity Service — API Documentation

> Microservice d'authentification et de gestion des identités pour l'ERP Oriotel.
> Basé sur **Laravel 11 + Sanctum** avec architecture API-only.

---

## 📋 Table des Matières

1. [Architecture](#-architecture)
2. [Installation](#-installation)
3. [Endpoints API](#-endpoints-api)
4. [Flux d'Authentification](#-flux-dauthentification)
5. [Appel depuis le Frontend](#-appel-depuis-le-frontend-react)
6. [Appel depuis un autre Microservice](#-appel-depuis-un-autre-microservice)
7. [Utilisateurs de Test](#-utilisateurs-de-test)

---

## 🏗 Architecture

```
service-identity/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── AuthController.php           # Login, Register, 2FA, Logout
│   │   │   └── PasswordResetController.php  # Forgot & Reset Password
│   │   ├── Middleware/
│   │   │   ├── ForceJsonResponse.php        # Force JSON for all requests
│   │   │   ├── EnsureAccountIsActive.php    # Block inactive accounts
│   │   │   └── CheckTokenAbility.php        # Scoped token validation
│   │   └── Requests/Auth/                   # Form Request Validators
│   │       ├── LoginRequest.php
│   │       ├── RegisterRequest.php
│   │       ├── ChangePasswordRequest.php
│   │       ├── ForgotPasswordRequest.php
│   │       ├── ResetPasswordRequest.php
│   │       └── VerifyTwoFactorRequest.php
│   ├── Models/
│   │   └── User.php                         # User with roles, 2FA, locking
│   ├── Notifications/
│   │   ├── TwoFactorCodeNotification.php    # 2FA code via email
│   │   ├── TwoFactorSmsNotification.php     # 2FA code via SMS (Vonage)
│   │   └── ResetPasswordNotification.php    # Password reset link
│   ├── Services/
│   │   ├── AuthService.php                  # Core auth orchestration
│   │   └── TwoFactorService.php             # 2FA code generation & verify
│   └── Providers/
│       └── AppServiceProvider.php
├── config/
│   ├── auth.php                             # Sanctum API guard
│   ├── cors.php                             # CORS for frontend
│   └── sanctum.php                          # Token config
├── database/
│   ├── migrations/
│   └── seeders/DatabaseSeeder.php           # Test users
├── routes/
│   └── api.php                              # All API routes (v1)
└── bootstrap/app.php                        # API-only bootstrap
```

---

## 🚀 Installation

```bash
# 1. Install dependencies
docker compose exec identity-php composer install

# 2. Generate app key
docker compose exec identity-php php artisan key:generate

# 3. Run migrations
docker compose exec identity-php php artisan migrate

# 4. Seed test users
docker compose exec identity-php php artisan db:seed

# 5. Verify service health
curl http://localhost:8080/api/identity/api/v1/health
```

---

## 📡 Endpoints API

### Base URL

| Environnement | URL |
|---|---|
| **Via Gateway** | `http://localhost:8080/api/identity/api/v1/` |
| **Direct** | `http://localhost:{PORT}/api/v1/` |

### Routes Publiques (sans token)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `POST` | `/auth/login` | Connexion utilisateur |
| `POST` | `/auth/register` | Demande d'accès (inscription) |
| `POST` | `/auth/two-factor/verify` | Vérifier le code 2FA |
| `POST` | `/auth/two-factor/resend` | Renvoyer le code 2FA |
| `POST` | `/auth/forgot-password` | Demander un lien de réinitialisation |
| `POST` | `/auth/reset-password` | Réinitialiser le mot de passe |
| `GET`  | `/health` | Health check du service |

### Routes Protégées (token requis dans le header `Authorization: Bearer {token}`)

| Méthode | Endpoint | Scope | Description |
|---------|----------|-------|-------------|
| `POST` | `/auth/force-change-password` | `password:change` | Changement de mot de passe forcé |
| `POST` | `/auth/change-password` | `*` (full) | Changement volontaire de mot de passe |
| `GET`  | `/auth/me` | `*` (full) | Profil de l'utilisateur connecté |
| `POST` | `/auth/logout` | `*` (full) | Déconnexion (révoque le token) |
| `GET`  | `/users/validate/{id}` | `*` (full) | Vérifier un utilisateur (inter-service) |

---

## 📄 Détail des Endpoints

### 1. `POST /auth/login`

**Body :**
```json
{
  "email": "admin@oriotel.com",
  "password": "Oriotel@2026"
}
```

**Réponse — Login standard (200) :**
```json
{
  "success": true,
  "action": "authenticated",
  "message": "Connexion réussie.",
  "user": {
    "id": 1,
    "first_name": "Admin",
    "last_name": "Oriotel",
    "full_name": "Admin Oriotel",
    "email": "admin@oriotel.com",
    "role": "administrateur",
    "role_type": "interne",
    "status": "active",
    "two_factor_enabled": false,
    "must_change_password": false
  },
  "token": "oid_1|abc123..."
}
```

**Réponse — 2FA requis (200) :**
```json
{
  "success": true,
  "action": "two_factor_required",
  "message": "Un code de vérification a été envoyé.",
  "user_id": 1,
  "channel": "email"
}
```

**Réponse — Changement de mot de passe obligatoire (200) :**
```json
{
  "success": true,
  "action": "force_password_change",
  "message": "Vous devez changer votre mot de passe avant de continuer.",
  "user": { "..." },
  "token": "oid_2|temp_token..."
}
```

**Réponse — Erreur (401/403/429) :**
```json
{
  "success": false,
  "action": "invalid_credentials|account_locked|account_inactive",
  "message": "..."
}
```

---

### 2. `POST /auth/register`

**Body :**
```json
{
  "first_name": "Mohamed",
  "last_name": "El Amrani",
  "email": "contact@agence.com",
  "phone": "+212612345678",
  "password": "SecurePass@2026",
  "password_confirmation": "SecurePass@2026",
  "access_reason": "Gestion de réservations pour notre agence de voyage.",
  "terms": true
}
```

**Réponse (201) :**
```json
{
  "success": true,
  "action": "registration_pending",
  "message": "Votre demande d'accès a été soumise. Un administrateur l'examinera prochainement.",
  "user": { "id": 5, "status": "pending", "..." }
}
```

---

### 3. `POST /auth/two-factor/verify`

**Body :**
```json
{
  "user_id": 1,
  "code": "482916"
}
```

**Réponse succès (200) :**
```json
{
  "success": true,
  "action": "authenticated",
  "message": "Connexion réussie.",
  "user": { "..." },
  "token": "oid_1|full_access_token..."
}
```

---

### 4. `POST /auth/two-factor/resend`

**Body :**
```json
{
  "user_id": 1
}
```

**Réponse (200) :**
```json
{
  "success": true,
  "message": "Un nouveau code a été envoyé.",
  "channel": "email"
}
```

---

### 5. `POST /auth/forgot-password`

**Body :**
```json
{
  "email": "admin@oriotel.com"
}
```

**Réponse (200 — toujours, pour éviter l'énumération d'emails) :**
```json
{
  "success": true,
  "message": "Si un compte existe avec cette adresse email, un lien de réinitialisation a été envoyé."
}
```

---

### 6. `POST /auth/reset-password`

**Body :**
```json
{
  "token": "abc123...",
  "email": "admin@oriotel.com",
  "password": "NewSecure@2026",
  "password_confirmation": "NewSecure@2026"
}
```

**Réponse (200) :**
```json
{
  "success": true,
  "message": "Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter."
}
```

---

### 7. `POST /auth/force-change-password` 🔒

> **Token requis :** Le token temporaire avec scope `password:change` (reçu lors du login).

**Header :** `Authorization: Bearer oid_2|temp_token...`

**Body :**
```json
{
  "password": "MyNewPassword@2026",
  "password_confirmation": "MyNewPassword@2026"
}
```

**Réponse (200) :**
```json
{
  "success": true,
  "action": "authenticated",
  "message": "Mot de passe mis à jour avec succès.",
  "user": { "..." },
  "token": "oid_2|new_full_token..."
}
```

---

### 8. `POST /auth/change-password` 🔒

**Header :** `Authorization: Bearer oid_1|full_token...`

**Body :**
```json
{
  "current_password": "Oriotel@2026",
  "password": "NewPassword@2026",
  "password_confirmation": "NewPassword@2026"
}
```

---

### 9. `GET /auth/me` 🔒

**Header :** `Authorization: Bearer oid_1|full_token...`

**Réponse (200) :**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "first_name": "Admin",
    "last_name": "Oriotel",
    "full_name": "Admin Oriotel",
    "email": "admin@oriotel.com",
    "phone": "+212600000001",
    "role": "administrateur",
    "role_type": "interne",
    "status": "active",
    "two_factor_enabled": true,
    "must_change_password": false,
    "email_verified_at": "2026-01-01T00:00:00.000000Z",
    "last_login_at": "2026-05-01T22:00:00.000000Z",
    "created_at": "2026-01-01T00:00:00.000000Z"
  }
}
```

---

### 10. `POST /auth/logout` 🔒

**Header :** `Authorization: Bearer oid_1|full_token...`

**Réponse (200) :**
```json
{
  "success": true,
  "message": "Déconnexion réussie."
}
```

---

### 11. `GET /users/validate/{id}` 🔒 (Inter-Service)

**Header :** `Authorization: Bearer oid_1|service_token...`

**Réponse (200) :**
```json
{
  "valid": true,
  "user": {
    "id": 1,
    "first_name": "Admin",
    "last_name": "Oriotel",
    "email": "admin@oriotel.com",
    "role": "administrateur",
    "role_type": "interne",
    "status": "active"
  }
}
```

---

## 🔄 Flux d'Authentification

```
┌──────────┐      POST /auth/login      ┌──────────────────┐
│  Client  │ ─────────────────────────►  │  Identity Service │
│(Frontend)│                             │                    │
└────┬─────┘                             └────────┬───────────┘
     │                                            │
     │  ┌─────────────────────────────────────────┤
     │  │                                         │
     │  │  action: "authenticated"                │
     │  │  ─► Token complet → Dashboard           │
     │  │                                         │
     │  │  action: "two_factor_required"          │
     │  │  ─► Page 2FA → POST /two-factor/verify  │
     │  │     ─► Token complet → Dashboard        │
     │  │                                         │
     │  │  action: "force_password_change"        │
     │  │  ─► Page changement MDP                 │
     │  │     POST /force-change-password          │
     │  │     ─► Token complet → Dashboard        │
     │  │                                         │
     │  │  action: "account_inactive"             │
     │  │  ─► Message d'erreur                    │
     │  │                                         │
     │  │  action: "invalid_credentials"          │
     │  │  ─► Message d'erreur + tentatives       │
     │  │                                         │
     │  │  action: "account_locked"               │
     │  │  ─► Message verrouillage + timer        │
     └──┘
```

---

## 💻 Appel depuis le Frontend (React)

### Configuration Axios

```javascript
// src/utils/api.js
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8080/api/identity/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Interceptor: attach token to every request
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Interceptor: handle 401 (token expired)
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token');
      window.location.href = '/auth/login';
    }
    return Promise.reject(error);
  }
);

export default api;
```

### Service d'Authentification

```javascript
// src/features/auth/services/authService.js
import api from '@/utils/api';

export const authService = {

  login: async (email, password) => {
    const { data } = await api.post('/auth/login', { email, password });

    if (data.action === 'authenticated') {
      localStorage.setItem('auth_token', data.token);
    }

    return data;
  },

  register: async (formData) => {
    const { data } = await api.post('/auth/register', formData);
    return data;
  },

  verifyTwoFactor: async (userId, code) => {
    const { data } = await api.post('/auth/two-factor/verify', {
      user_id: userId,
      code,
    });

    if (data.action === 'authenticated') {
      localStorage.setItem('auth_token', data.token);
    }

    return data;
  },

  resendTwoFactor: async (userId) => {
    const { data } = await api.post('/auth/two-factor/resend', {
      user_id: userId,
    });
    return data;
  },

  forceChangePassword: async (newPassword, confirmation) => {
    const { data } = await api.post('/auth/force-change-password', {
      password: newPassword,
      password_confirmation: confirmation,
    });

    if (data.action === 'authenticated') {
      localStorage.setItem('auth_token', data.token);
    }

    return data;
  },

  forgotPassword: async (email) => {
    const { data } = await api.post('/auth/forgot-password', { email });
    return data;
  },

  resetPassword: async (token, email, password, confirmation) => {
    const { data } = await api.post('/auth/reset-password', {
      token,
      email,
      password,
      password_confirmation: confirmation,
    });
    return data;
  },

  getProfile: async () => {
    const { data } = await api.get('/auth/me');
    return data.user;
  },

  logout: async () => {
    await api.post('/auth/logout');
    localStorage.removeItem('auth_token');
  },
};
```

---

## 🔗 Appel depuis un autre Microservice

Les microservices communiquent via le **réseau Docker interne** — pas besoin de passer par le gateway.

### Exemple : Service Operations vérifie un utilisateur

```php
// Dans service-operations/app/Services/IdentityClient.php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class IdentityClient
{
    protected string $baseUrl;

    public function __construct()
    {
        // URL interne Docker (pas le gateway)
        $this->baseUrl = config('services.identity.url', 'http://identity-nginx');
    }

    /**
     * Validate a user exists and is active.
     *
     * @param int    $userId
     * @param string $token   The calling user's Sanctum token
     * @return array|null
     */
    public function validateUser(int $userId, string $token): ?array
    {
        $response = Http::withToken($token)
            ->timeout(5)
            ->get("{$this->baseUrl}/api/v1/users/validate/{$userId}");

        if ($response->successful() && $response->json('valid')) {
            return $response->json('user');
        }

        return null;
    }

    /**
     * Verify the token is valid by calling /auth/me.
     *
     * @param string $token
     * @return array|null User data or null if invalid
     */
    public function verifyToken(string $token): ?array
    {
        $response = Http::withToken($token)
            ->timeout(5)
            ->get("{$this->baseUrl}/api/v1/auth/me");

        if ($response->successful()) {
            return $response->json('user');
        }

        return null;
    }
}
```

### Configuration dans l'autre service

```php
// config/services.php (dans service-operations)
return [
    'identity' => [
        'url' => env('IDENTITY_SERVICE_URL', 'http://identity-nginx'),
    ],
];
```

### Middleware d'authentification inter-service

```php
// app/Http/Middleware/AuthenticateViaIdentity.php (dans service-operations)

namespace App\Http\Middleware;

use App\Services\IdentityClient;
use Closure;
use Illuminate\Http\Request;

class AuthenticateViaIdentity
{
    public function __construct(protected IdentityClient $identity) {}

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token manquant.'], 401);
        }

        $user = $this->identity->verifyToken($token);

        if (!$user) {
            return response()->json(['message' => 'Token invalide.'], 401);
        }

        // Attach user data to the request for downstream use
        $request->merge(['auth_user' => $user]);

        return $next($request);
    }
}
```

---

## 🧪 Utilisateurs de Test

Après `php artisan db:seed`, les utilisateurs suivants sont disponibles :

| Rôle | Email | Mot de passe | Flux déclenché |
|------|-------|-------------|----------------|
| **Administrateur** | `admin@oriotel.com` | `Oriotel@2026` | **2FA** (code envoyé par email) |
| **Animateur** (Interne) | `animateur@oriotel.com` | `Oriotel@2026` | **Changement de mot de passe forcé** |
| **Agence** (Externe) | `agence@oriotel.com` | `Oriotel@2026` | Login standard → Dashboard |
| **Pending** (Externe) | `pending@oriotel.com` | `Oriotel@2026` | Rejeté → "Compte en attente" |

---

## 🔒 Sécurité

| Mesure | Détail |
|--------|--------|
| **Hachage** | Bcrypt (12 rounds) |
| **Tokens** | Sanctum avec préfixe `oid_`, expiration 24h |
| **2FA** | Code 6 chiffres, TTL 10 min, email ou SMS |
| **Verrouillage** | 5 tentatives max → verrouillage 15 min |
| **Anti-énumération** | `/forgot-password` retourne toujours 200 |
| **CORS** | Origines configurables via `.env` |
| **Soft Delete** | Les comptes supprimés sont conservés |
| **Tokens scopés** | Token temporaire `password:change` pour MDP forcé |

---

<p align="center">
  <strong>Oriotel Identity Service</strong> — v1.0
</p>
