# 🐳 Oriotel ERP — Docker Troubleshooting & Build Failure Reference

> Toutes les raisons possibles qui peuvent empêcher le **lancement** ou le **build** des containers Docker dans ce projet.

---

## 📋 Table des Matières

1. [Problèmes de Build (Image)](#1-problèmes-de-build-image)
2. [Problèmes de Démarrage des Containers](#2-problèmes-de-démarrage-des-containers)
3. [Healthcheck Failures](#3-healthcheck-failures)
4. [Problèmes de Réseau](#4-problèmes-de-réseau)
5. [Problèmes de Volumes & Permissions](#5-problèmes-de-volumes--permissions)
6. [Problèmes de Variables d'Environnement](#6-problèmes-de-variables-denvironnement)
7. [Problèmes de Dépendances entre Services](#7-problèmes-de-dépendances-entre-services)
8. [Problèmes PHP-FPM / Laravel](#8-problèmes-php-fpm--laravel)
9. [Problèmes Nginx (Sidecar & Gateway)](#9-problèmes-nginx-sidecar--gateway)
10. [Problèmes MySQL / Redis](#10-problèmes-mysql--redis)
11. [Problèmes OCR (PaddleOCR / Python)](#11-problèmes-ocr-paddleocr--python)
12. [Problèmes de Ressources Système](#12-problèmes-de-ressources-système)
13. [Commandes de Diagnostic](#13-commandes-de-diagnostic)

---

## 1. Problèmes de Build (Image)

### 1.1 Dockerfile introuvable ou chemin incorrect
```
ERROR: failed to solve: failed to read dockerfile
```
**Cause :** Le champ `dockerfile:` dans `docker-compose.yml` pointe vers un chemin inexistant.  
**Fix :** Vérifier que `docker/php/Dockerfile` existe bien à la racine du projet.

---

### 1.2 Fichier COPY manquant dans le contexte de build
```
ERROR: COPY failed: file not found in build context
```
**Cause :** Le Dockerfile essaie de copier un fichier (ex: `docker/php/fpm-pool.conf`) qui n'existe pas dans le contexte.  
**Fix :**
```bash
ls docker/php/fpm-pool.conf
ls docker/entrypoint.sh
```

---

### 1.3 Échec `apk add` / `apt-get` (pas d'internet)
```
ERROR: network: unable to resolve host packages.alpine.org
```
**Cause :** Docker Desktop n'a pas accès à internet (proxy d'entreprise, DNS, VPN).  
**Fix :** Configurer le proxy dans Docker Desktop > Settings > Resources > Proxies.

---

### 1.4 Échec `composer install` pendant le build
```
[RuntimeException] Could not load package ...
```
**Cause :** Pas de connexion Packagist ou `composer.json` invalide.  
**Fix :**
```bash
cd service-identity
composer validate
composer install --dry-run
```

---

### 1.5 Version PHP incompatible avec les dépendances
```
Your lock file does not contain a compatible set of packages.
```
**Cause :** Le `composer.lock` a été généré avec une version PHP différente de `8.2`.  
**Fix :**
```bash
docker compose exec identity-php php --version
composer update --no-interaction
```

---

### 1.6 Extension PHP manquante
```
PHP Fatal error: Class 'PDO' not found
```
**Cause :** L'extension `pdo_mysql`, `redis`, ou `gd` n'est pas compilée dans le Dockerfile.  
**Fix :** Vérifier que `docker-php-ext-install pdo pdo_mysql ...` est bien dans le Dockerfile.

---

### 1.7 `pecl install redis` échoue
```
ERROR: 'pecl' is not recognized or compilation failed
```
**Cause :** Les outils `autoconf`, `g++`, `make` sont absents au moment du `pecl`.  
**Fix :** Le Dockerfile installe ces outils avant `pecl` et les supprime après — ne pas modifier cet ordre.

---

## 2. Problèmes de Démarrage des Containers

### 2.1 Container en état `Exited (1)` immédiatement
**Causes possibles :**
- Script `entrypoint.sh` échoue (`set -e` arrête à la première erreur)
- `php artisan migrate --force` échoue (MySQL pas encore prêt)
- `vendor/autoload.php` absent et `composer install` échoue

**Diagnostic :**
```bash
docker compose logs identity-php --tail=50
```

---

### 2.2 Container en boucle `Restarting`
**Cause :** `restart: unless-stopped` + crash répété.  
**Fix :** Désactiver `restart` temporairement pour voir le vrai log :
```bash
docker compose stop identity-php
docker compose run --rm identity-php sh
```

---

### 2.3 `entrypoint.sh` : erreur de fin de ligne Windows (CRLF)
```
/bin/sh: bad interpreter: No such file or directory
```
**Cause :** Le fichier `entrypoint.sh` a des fins de ligne Windows (`\r\n`).  
**Fix :**
```bash
git config core.autocrlf false
dos2unix docker/entrypoint.sh
```
Ou dans `.gitattributes` :
```
docker/entrypoint.sh text eol=lf
```

---

### 2.4 `WORKDIR` n'appartient pas à `www-data`
```
PHP Warning: file_put_contents(...): failed to open stream: Permission denied
```
**Cause :** Les fichiers montés via volume appartiennent à l'utilisateur Windows.  
**Fix :** Le Dockerfile fait `chown -R www-data:www-data /var/www/html` — mais cette commande n'affecte pas les bind mounts.  
Solution : Utiliser `user: www-data` ou ajouter dans `entrypoint.sh` :
```bash
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
```

---

## 3. Healthcheck Failures

### 3.1 PHP-FPM healthcheck échoue (`cgi-fcgi`)
```
health: unhealthy
```
**Cause :** PHP-FPM n'écoute pas sur le port 9000, ou `fpm-pool.conf` ne définit pas le endpoint `/ping`.  
**Fix :** Vérifier `docker/php/fpm-pool.conf` :
```ini
pm.status_path = /status
ping.path = /ping
ping.response = pong
```

---

### 3.2 `start_period` trop court
**Cause :** `composer install` + migrations prennent plus de 60s.  
**Fix :** Augmenter `start_period: 120s` dans le healthcheck.

---

### 3.3 MySQL healthcheck échoue
```
mysqladmin: connect to server at 'localhost' failed
```
**Cause :** MySQL démarre mais n'accepte pas encore les connexions.  
**Fix :** Augmenter `retries: 15` et `interval: 10s`.

---

### 3.4 OCR healthcheck échoue (modèle pas encore chargé)
**Cause :** PaddleOCR télécharge le modèle au premier démarrage (peut prendre 2-3 min).  
**Fix :** Augmenter `start_period: 180s` pour le service `ocr-python`.

---

## 4. Problèmes de Réseau

### 4.1 Services ne se trouvent pas par nom DNS
```
502 Bad Gateway / curl: (6) Could not resolve host: identity-nginx
```
**Cause :** Les services ne sont pas sur le même réseau Docker (`oriotel-network`).  
**Fix :** Vérifier que **tous** les services ont :
```yaml
networks:
  - oriotel-network
```

---

### 4.2 Port déjà utilisé sur l'hôte
```
Bind for 0.0.0.0:8080 failed: port is already allocated
```
**Cause :** Un autre processus (WAMP, IIS, autre Docker stack) utilise le port.  
**Fix :**
```powershell
netstat -ano | findstr :8080
# Tuer le processus ou changer le port dans .env
```

---

### 4.3 `api-gateway` ne peut pas joindre les upstreams Nginx
```
upstream timed out (110: Connection timed out)
```
**Cause :** Le container `identity-nginx` n'est pas démarré ou son port 80 n'est pas accessible depuis `oriotel-network`.  
**Fix :** Vérifier `docker compose ps` et les logs de `identity-nginx`.

---

## 5. Problèmes de Volumes & Permissions

### 5.1 Volume bind mount vide (chemin inexistant)
```
Cannot start service: path does not exist: ./service-identity
```
**Cause :** Le dossier du service n'existe pas localement.  
**Fix :** S'assurer que tous les dossiers sont clonés/présents.

---

### 5.2 `storage/` non writable — erreurs Laravel
```
UnexpectedValueException: The stream or file ".../storage/logs/laravel.log" could not be opened
```
**Cause :** Permissions incorrectes sur `storage/` et `bootstrap/cache/`.  
**Fix :**
```bash
docker compose exec identity-php chmod -R 775 storage bootstrap/cache
docker compose exec identity-php chown -R www-data:www-data storage bootstrap/cache
```

---

### 5.3 Volume MySQL corrompu
```
InnoDB: Database page corruption or incomplete read
```
**Fix :** Supprimer et recréer le volume :
```bash
docker compose down -v
docker compose up -d
```
> ⚠️ Ceci supprime toutes les données MySQL.

---

## 6. Problèmes de Variables d'Environnement

### 6.1 Fichier `.env` absent
```
ERROR: No such file or directory: .env
```
**Fix :**
```bash
cp .env.docker .env
```

---

### 6.2 `APP_KEY` non défini ou invalide
```
RuntimeException: No application encryption key has been specified.
```
**Cause :** `APP_KEY` est vide ou le placeholder est invalide (doit être base64 32 bytes).  
**Fix :**
```bash
docker compose exec identity-php php artisan key:generate --force
```

---

### 6.3 Variable non interpolée dans docker-compose.yml
```
invalid interpolation format
```
**Cause :** Syntaxe incorrecte `${VAR}` ou variable absente du `.env`.  
**Fix :** Tester avec `docker compose config` pour voir la config résolue.

---

### 6.4 `DB_DATABASE` non créée dans MySQL
```
SQLSTATE[HY000] [1049] Unknown database 'oriotel_identity'
```
**Cause :** Le script `docker/mysql/init.sql` n'a pas créé la base.  
**Fix :** Vérifier le contenu de `init.sql` — chaque service a besoin de sa base :
```sql
CREATE DATABASE IF NOT EXISTS oriotel_identity;
CREATE DATABASE IF NOT EXISTS oriotel_operations;
CREATE DATABASE IF NOT EXISTS oriotel_communication;
CREATE DATABASE IF NOT EXISTS oriotel_subscription;
```

---

## 7. Problèmes de Dépendances entre Services

### 7.1 `depends_on` avec `condition: service_healthy` bloque indéfiniment
**Cause :** Le service dépendant attend que MySQL/Redis soit `healthy`, mais leur healthcheck échoue.  
**Diagnostic :**
```bash
docker inspect oriotel-mysql | grep -A 10 Health
```

---

### 7.2 `api-gateway` démarre avant que les nginx sidecars soient prêts
**Cause :** `depends_on` de `api-gateway` n'utilise pas `condition: service_healthy` pour les nginx.  
**Conséquence :** Le gateway reçoit des requêtes avant que les upstreams soient disponibles → 502.  
**Fix :** Ajouter des healthchecks aux nginx sidecars ou augmenter le timeout de reconnexion upstream.

---

### 7.3 Nginx sidecar démarre avant PHP-FPM est prêt
**Cause :** `depends_on: - identity-php` sans condition `service_healthy`.  
**Fix :**
```yaml
depends_on:
  identity-php:
    condition: service_healthy
```

---

## 8. Problèmes PHP-FPM / Laravel

### 8.1 `php artisan migrate` échoue au démarrage
```
SQLSTATE[42000]: Syntax error — Table already exists
```
**Cause :** Migration déjà exécutée, ou schéma corrompu.  
**Fix :**
```bash
docker compose exec identity-php php artisan migrate:status
docker compose exec identity-php php artisan migrate --force
```

---

### 8.2 Routes non trouvées (404 sur des endpoints qui existent)
```
404 Not Found
```
**Cause :** Cache des routes obsolète.  
**Fix :**
```bash
docker compose exec identity-php php artisan route:clear
docker compose exec identity-php php artisan cache:clear
```

---

### 8.3 `composer.lock` désynchronisé
```
Your lock file does not contain a compatible set of packages.
```
**Fix :**
```bash
docker compose exec identity-php composer install --no-interaction
```

---

### 8.4 Sanctum / CORS bloqué
```
Access-Control-Allow-Origin header missing
```
**Cause :** `SANCTUM_STATEFUL_DOMAINS` ou `CORS_ALLOWED_ORIGINS` ne correspondent pas à l'origine frontend.  
**Fix :** Vérifier les variables d'environnement du service identity.

---

## 9. Problèmes Nginx (Sidecar & Gateway)

### 9.1 Template Nginx non trouvé
```
nginx: [emerg] open() "/etc/nginx/templates/default.conf.template" failed
```
**Cause :** Le volume `./docker/nginx/default.conf.template` n'est pas monté correctement.  
**Fix :** Vérifier que le fichier template existe localement.

---

### 9.2 Variable `PHP_FPM_HOST` non substituée
**Cause :** L'image Nginx alpine utilise `envsubst` via `/etc/nginx/templates/`. Si le template utilise une variable non définie dans `environment:`, la valeur sera vide.  
**Fix :** S'assurer que `PHP_FPM_HOST: identity-php` est bien dans `environment:` du service nginx.

---

### 9.3 502 Bad Gateway
**Causes :**
- PHP-FPM non démarré ou crashé
- Mauvais `fastcgi_pass` dans le template Nginx
- Socket vs TCP mal configuré

**Diagnostic :**
```bash
docker compose logs identity-nginx
docker compose logs identity-php
```

---

### 9.4 504 Gateway Timeout
**Cause :** PHP-FPM met trop de temps à répondre (migration longue, requête lente).  
**Fix :** Augmenter `proxy_read_timeout` dans `nginx.conf` et `fastcgi_read_timeout` dans le template sidecar.

---

## 10. Problèmes MySQL / Redis

### 10.1 Connexion MySQL refusée depuis PHP
```
SQLSTATE[HY000] [2002] Connection refused
```
**Cause :** `DB_HOST=mysql` mais le container MySQL n'est pas encore démarré ou pas sur le bon réseau.

---

### 10.2 Authentification MySQL échoue
```
SQLSTATE[HY000] [1045] Access denied for user 'oriotel'@'%'
```
**Cause :** `MYSQL_USER` / `MYSQL_PASSWORD` dans `.env` ne correspondent pas à ce qui a été créé lors de l'init.  
**Fix :** Recréer les volumes MySQL :
```bash
docker compose down -v
docker compose up -d mysql
```

---

### 10.3 Redis connexion échoue
```
Connection refused [tcp://redis:6379]
```
**Cause :** Container Redis non démarré ou `REDIS_HOST=redis` ne résout pas.

---

## 11. Problèmes OCR (PaddleOCR / Python)

### 11.1 Build échoue — dépendances lourdes
```
ERROR: Could not find a version that satisfies the requirement paddlepaddle
```
**Cause :** Plateforme non supportée (ex: Windows ARM) ou pip timeout.  
**Fix :** Augmenter le timeout pip dans le Dockerfile OCR.

---

### 11.2 Mémoire insuffisante (OOM Kill)
```
Killed (signal 9)
```
**Cause :** PaddleOCR nécessite ~2 Go de RAM. Limite de 2G dans `docker-compose.yml`.  
**Fix :** Augmenter la limite ou réduire les autres services.

---

### 11.3 Healthcheck OCR échoue pendant le chargement du modèle
**Cause :** Le modèle prend 60-120s à charger au premier démarrage.  
**Fix :** `start_period: 180s` dans le healthcheck OCR.

---

## 12. Problèmes de Ressources Système

### 12.1 Docker Desktop manque de RAM
**Symptôme :** Containers `unhealthy` ou `OOMKilled` aléatoirement.  
**Fix :** Settings > Resources > Memory ≥ 6 Go pour cette stack (13 containers).

---

### 12.2 Disque plein
```
no space left on device
```
**Fix :**
```bash
docker system prune -a --volumes
```

---

### 12.3 CPU 100% — containers lents à démarrer
**Cause :** Build en parallèle + `composer install` sur tous les services simultanément.  
**Fix :**
```bash
docker compose up -d mysql redis
docker compose up -d identity-php identity-nginx
# Démarrer service par service
```

---

## 13. Commandes de Diagnostic

```bash
# Voir le statut de tous les containers (health inclus)
docker compose ps

# Logs d'un service spécifique
docker compose logs <service-name> --tail=100 -f

# Inspecter le healthcheck
docker inspect oriotel-identity-php | python -c "import sys,json; h=json.load(sys.stdin)[0]['State']['Health']; [print(l['Output']) for l in h['Log']]"

# Entrer dans un container pour déboguer
docker compose exec identity-php sh

# Vérifier la config résolue (variables .env)
docker compose config

# Reconstruire un service sans cache
docker compose build --no-cache identity-php

# Supprimer et recréer complètement
docker compose down -v --remove-orphans
docker compose up -d --build

# Tester les endpoints depuis l'hôte
curl -s http://localhost:8080/health | python -m json.tool
curl -s http://localhost:8080/api/identity/api/v1/health | python -m json.tool
```

---

> 📝 **Document généré pour le projet Oriotel ERP** — Stack: PHP 8.2-FPM, Nginx 1.25-alpine, MySQL 8.0, Redis 7, PaddleOCR (Python), Docker Compose v2.
