# 🚀 Guide d'Installation Rapide (Oriotel ERP)

Ce guide contient les étapes exactes pour mettre en place l'environnement de développement après un `git pull`.

---

## 🏗️ 1. Backend (Microservices)

### A. Configuration initiale
```bash
# Copier le fichier d'environnement à la racine
cp .env.docker .env
# build image oriotel-php-base
docker build -f docker/php/Dockerfile.base -t oriotel-php-base .

### B. Démarrage des conteneurs
```bash
# Lancer les services en arrière-plan
docker compose up -d
```
*Note : Si vous rencontrez une erreur "EOF" ou de connexion, redémarrez Docker Desktop et faites `docker system prune -f`.*

rm -rf vendor composer.lock

cd service-identity
rm -rf vendor composer.lock
composer install

cd service-subscription
rm -rf vendor composer.lock
composer install

cd service-operations
rm -rf vendor composer.lock
composer install

cd service-communication
rm -rf vendor composer.lock
composer install


cd service-identity && rm -rf vendor && composer install && cd ../service-subscription && rm -rf vendor && composer install && cd ../service-communication && rm -rf vendor && composer install && cd ../service-operations && rm -rf vendor && composer install && cd ..

### C. Installation des dépendances (PHP)
Utilisez `update` au lieu de `install` pour éviter les conflits de version du fichier lock :
```bash
docker compose exec identity-php composer update
docker compose exec operations-php composer update
docker compose exec communication-php composer update
docker compose exec subscription-php composer update
```

### D. Migrations de la base de données
```bash
docker compose exec identity-php php artisan migrate
docker compose exec operations-php php artisan migrate
docker compose exec communication-php php artisan migrate
docker compose exec subscription-php php artisan migrate
```

---

## 💻 2. Frontend (Dashboard)

### A. Installation
```bash
# Dans le dossier front-office
npm install
```

### B. Lancement
```bash
npm run dev
```

---

## ✅ 3. Liens de vérification
- **Tableau de bord :** [http://localhost:5173](http://localhost:5173)
- **API Gateway :** [http://localhost:8080](http://localhost:8080)
- **Base de données (phpMyAdmin) :** [http://localhost:8081](http://localhost:8081)

---

## 🆘 Dépannage essentiel

| Erreur | Solution |
| :--- | :--- |
| **"EOF" / Connection Error** | Redémarrer Docker Desktop + `docker system prune -f` |
| **Lock file conflict** | Utiliser `composer update` au lieu de `composer install` |
| **Permissions PHP** | `docker compose exec identity-php chmod -R 775 storage bootstrap/cache` |
| **Service not running** | `docker compose up -d` |
| **Process Timeout** | `docker compose exec identity-php composer config --global process-timeout 0` puis relancez l'update. |
