# 🏨 Oriotel ERP — Microservices Architecture

> Plateforme ERP SaaS multi-tenant pour la gestion hôtelière, construite en **microservices Laravel** orchestrés par **Docker** et routés via un **API Gateway Nginx**.

---

## 📐 Architecture Globale

```
                         ┌──────────────────────────────────┐
                         │           CLIENT                 │
                         │   (Front-Office / Mobile App)    │
                         └───────────────┬──────────────────┘
                                         │
                                    port 8080
                                         │
                         ┌───────────────▼──────────────────┐
                         │        API GATEWAY (Nginx)       │
                         │     oriotel-api-gateway :80      │
                         │                                  │
                         │  /api/identity/*      ──────►    │
                         │  /api/operations/*    ──────►    │
                         │  /api/communication/* ──────►    │
                         │  /api/subscription/*  ──────►    │
                         └──┬──────┬──────┬──────┬──────────┘
                            │      │      │      │
              ┌─────────────▼─┐  ┌─▼──────▼─┐  ┌▼────────────────┐
              │   IDENTITY    │  │OPERATIONS │  │ COMMUNICATION   │
              │  nginx + php  │  │nginx + php│  │  nginx + php    │
              └───────┬───────┘  └─────┬─────┘  └───────┬─────────┘
                      │                │                 │
              ┌───────▼────────────────▼─────────────────▼─────────┐
              │                                                    │
              │  ┌────────────┐    ┌────────────┐                  │
              │  │  MySQL 8.0 │    │ Redis 7    │   SUBSCRIPTION   │
              │  │  4 schemas │    │  (cache)   │   nginx + php    │
              │  └────────────┘    └────────────┘                  │
              └────────────────────────────────────────────────────┘
                              oriotel-network (bridge)
```

---

## 📁 Structure du Projet

```
oriotel-erp/
│
├── api-gateway/                  # Reverse proxy Nginx
│   ├── Dockerfile                # Image Nginx Alpine
│   └── nginx.conf                # Routage vers les microservices
│
├── docker/                       # Configuration Docker partagée
│   ├── php/
│   │   └── Dockerfile            # Image PHP 8.2-FPM (partagée)
│   ├── nginx/
│   │   └── default.conf.template # Template Nginx pour Laravel (envsubst)
│   ├── mysql/
│   │   └── init.sql              # Création des 4 bases de données
│   └── entrypoint.sh             # Script d'initialisation Laravel
│
├── service-identity/             # 🔐 Authentification, Utilisateurs, Rôles
│   └── (Laravel 11)
│
├── service-operations/           # 🏨 Chambres, Réservations, Facturation
│   └── (Laravel 11)
│
├── service-communication/        # 💬 Chat, Notifications
│   └── (Laravel 11)
│
├── service-subscription/         # 📋 Plans, Abonnements, Tenants
│   └── (Laravel 11)
│
├── docker-compose.yml            # Orchestration de tous les services
├── .env.docker                   # Variables d'environnement Docker
├── .dockerignore                 # Exclusions pour le build
└── README.md                     # Ce fichier
```

---

## 🐳 Services Docker

| Service              | Container               | Port exposé | Description                          |
|----------------------|-------------------------|-------------|--------------------------------------|
| **API Gateway**      | `oriotel-api-gateway`   | `8080`      | Reverse proxy / routeur              |
| **Identity PHP**     | `oriotel-identity-php`  | —           | PHP-FPM pour le service Identity     |
| **Identity Nginx**   | `oriotel-identity-nginx`| —           | Sidecar Nginx pour Identity          |
| **Operations PHP**   | `oriotel-operations-php`| —           | PHP-FPM pour le service Operations   |
| **Operations Nginx** | `oriotel-operations-nginx`| —         | Sidecar Nginx pour Operations        |
| **Communication PHP**| `oriotel-communication-php`| —        | PHP-FPM pour le service Communication|
| **Communication Nginx**| `oriotel-communication-nginx`| —    | Sidecar Nginx pour Communication     |
| **Subscription PHP** | `oriotel-subscription-php`| —         | PHP-FPM pour le service Subscription |
| **Subscription Nginx**| `oriotel-subscription-nginx`| —      | Sidecar Nginx pour Subscription      |
| **MySQL**            | `oriotel-mysql`         | `3306`      | Base de données (4 schémas)          |
| **Redis**            | `oriotel-redis`         | `6379`      | Cache, sessions, queues              |
| **phpMyAdmin**       | `oriotel-phpmyadmin`    | `8081`      | Interface web pour MySQL             |

---

## 🗄️ Bases de Données

Chaque microservice possède sa propre base de données pour garantir l'**isolation des données** :

| Service         | Base de données          |
|-----------------|--------------------------|
| Identity        | `oriotel_identity`       |
| Operations      | `oriotel_operations`     |
| Communication   | `oriotel_communication`  |
| Subscription    | `oriotel_subscription`   |

---

## 🚀 Démarrage Rapide

### Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) ≥ 4.x
- [Docker Compose](https://docs.docker.com/compose/) ≥ 2.x (inclus dans Docker Desktop)

### 1. Cloner le projet

```bash
git clone <repository-url>
cd oriotel-erp
```

### 2. Configurer l'environnement

```bash
cp .env.docker .env
```

> Modifiez `.env` si nécessaire (ports, mots de passe, clés Laravel).

### 3. Lancer tous les services

```bash
docker compose up -d --build
```

### 4. Vérifier le déploiement

```bash
# Statut de tous les conteneurs
docker compose ps

# Health check du gateway
curl http://localhost:8080/health
```

### 5. Accéder aux services

| URL                                    | Service                |
|----------------------------------------|------------------------|
| `http://localhost:8080/api/identity/`   | Service Identity       |
| `http://localhost:8080/api/operations/` | Service Operations     |
| `http://localhost:8080/api/communication/` | Service Communication |
| `http://localhost:8080/api/subscription/`  | Service Subscription  |
| `http://localhost:8081`                 | phpMyAdmin             |
| `http://localhost:8080/health`          | Gateway Health Check   |

---

## 🔧 Commandes Essentielles

### Docker Compose

```bash
# Démarrer tous les services (en arrière-plan)
docker compose up -d --build

# Voir les logs en temps réel
docker compose logs -f

# Logs d'un service spécifique
docker compose logs -f identity-php

# Arrêter tous les services
docker compose down

# Arrêter et supprimer les volumes (RESET complet)
docker compose down -v

# Reconstruire un service spécifique
docker compose build identity-php --no-cache
docker compose up -d identity-php

# Redémarrer un service
docker compose restart identity-php identity-nginx
```

### Laravel (Artisan) — Exécuter dans un conteneur

```bash
# Accéder au shell d'un service
docker compose exec identity-php sh

# Lancer les migrations
docker compose exec identity-php php artisan migrate

# Créer un seeder
docker compose exec identity-php php artisan db:seed

# Vider le cache
docker compose exec identity-php php artisan cache:clear
docker compose exec identity-php php artisan config:clear
docker compose exec identity-php php artisan route:clear

# Générer une nouvelle clé d'application
docker compose exec identity-php php artisan key:generate --show

# Lancer les tests
docker compose exec identity-php php artisan test
```

> **Tip :** Remplacez `identity-php` par `operations-php`, `communication-php`, ou `subscription-php` pour cibler un autre service.

### Composer (Dépendances)

```bash
# Installer les dépendances
docker compose exec identity-php composer install

# Ajouter un package
docker compose exec identity-php composer require laravel/sanctum

# Mettre à jour les dépendances
docker compose exec identity-php composer update
```

### Base de Données

```bash
# Accéder au CLI MySQL
docker compose exec mysql mysql -u oriotel -poriotel_secret

# Lister les bases de données
docker compose exec mysql mysql -u root -poriotel_root -e "SHOW DATABASES;"

# Backup d'une base
docker compose exec mysql mysqldump -u root -poriotel_root oriotel_identity > backup_identity.sql

# Restaurer un backup
docker compose exec -T mysql mysql -u root -poriotel_root oriotel_identity < backup_identity.sql
```

### Debugging

```bash
# Vérifier la config Nginx du gateway
docker compose exec api-gateway nginx -t

# Voir la config Nginx résolue d'un sidecar
docker compose exec identity-nginx cat /etc/nginx/conf.d/default.conf

# Inspecter le réseau Docker
docker network inspect oriotel-network

# Vérifier la connectivité entre services
docker compose exec identity-php ping -c 3 mysql
docker compose exec identity-php ping -c 3 redis
```

---

## 🔄 Workflow de Développement

```
┌─────────────────────────────────────────────────────────────────┐
│                     WORKFLOW DE DÉVELOPPEMENT                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. MODIFIER LE CODE                                            │
│     └─► Éditez les fichiers dans service-*/                     │
│         Les volumes Docker synchronisent automatiquement        │
│                                                                 │
│  2. TESTER L'API                                                │
│     └─► curl http://localhost:8080/api/{service}/               │
│         Ou utilisez Postman / Insomnia                          │
│                                                                 │
│  3. VOIR LES LOGS                                               │
│     └─► docker compose logs -f {service}-php                    │
│                                                                 │
│  4. MIGRATIONS                                                  │
│     └─► docker compose exec {service}-php php artisan migrate   │
│                                                                 │
│  5. AJOUTER UN PACKAGE                                          │
│     └─► docker compose exec {service}-php composer require xxx  │
│                                                                 │
│  6. REBUILD SI NÉCESSAIRE                                       │
│     └─► docker compose build {service}-php --no-cache           │
│         docker compose up -d {service}-php                      │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🌐 Routage API Gateway

L'API Gateway (Nginx) route les requêtes vers le bon microservice en se basant sur le **préfixe d'URL** :

| Préfixe URL             | Service cible          | Container cible          |
|--------------------------|------------------------|--------------------------|
| `/api/identity/*`        | Identity (Auth)        | `identity-nginx:80`      |
| `/api/operations/*`      | Operations (Hôtel)     | `operations-nginx:80`    |
| `/api/communication/*`   | Communication (Chat)   | `communication-nginx:80` |
| `/api/subscription/*`    | Subscription (Plans)   | `subscription-nginx:80`  |
| `/health`                | Health Check            | Réponse directe 200 OK   |

### Headers transmis aux services

| Header               | Description                                |
|----------------------|--------------------------------------------|
| `X-Real-IP`         | IP réelle du client                        |
| `X-Forwarded-For`   | Chaîne de proxies traversés                |
| `X-Forwarded-Proto` | Protocole original (http/https)            |
| `X-Forwarded-Prefix`| Préfixe URL du service (`/api/identity`)   |

---

## ⚙️ Configuration des Ports

| Port  | Service        | Modifiable via         |
|-------|----------------|------------------------|
| 8080  | API Gateway    | `GATEWAY_PORT` (.env)  |
| 8081  | phpMyAdmin     | `PMA_PORT` (.env)      |
| 3306  | MySQL          | `MYSQL_PORT` (.env)    |
| 6379  | Redis          | `REDIS_PORT` (.env)    |

---

## 🔐 Sécurité

- Les conteneurs PHP-FPM et Nginx des services ne sont **pas exposés** sur le host — tout le trafic passe par l'API Gateway
- Chaque service possède sa propre base de données (isolation)
- Headers de sécurité ajoutés au niveau du Gateway (`X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`)
- Les variables sensibles sont externalisées dans `.env` (ne jamais committer)

---

## 🛠️ Dépannage

| Problème | Solution |
|----------|----------|
| `502 Bad Gateway` | Vérifier que les conteneurs PHP-FPM sont démarrés : `docker compose ps` |
| MySQL non prêt | Attendre le healthcheck : `docker compose logs mysql` |
| Permissions Laravel | `docker compose exec {service}-php chmod -R 775 storage bootstrap/cache` |
| Port déjà utilisé | Modifier le port dans `.env` |
| Migrations échouent | Vérifier la connexion DB : `docker compose exec {service}-php php artisan db:monitor` |
| Package manquant | `docker compose exec {service}-php composer install` |

---

## 📋 Technologies

| Composant       | Technologie          | Version |
|-----------------|----------------------|---------|
| Backend         | Laravel              | 11.x    |
| PHP             | PHP-FPM              | 8.2     |
| Web Server      | Nginx                | 1.25    |
| Base de données | MySQL                | 8.0     |
| Cache           | Redis                | 7.x     |
| Conteneurs      | Docker + Compose     | Latest  |

---

<p align="center">
  <strong>Oriotel ERP</strong> — OSM
</p>
