# Optimisation des Performances Docker sur Windows

Si vous remarquez que Docker est lent sur votre ordinateur, voici les raisons principales et comment améliorer la situation.

---

## 🚀 1. Le Problème du Système de Fichiers (Cause n°1)

**Le problème :** Votre projet est situé sur le disque Windows (`C:\Users\hp\Desktop\...`). Docker (via WSL2) doit "traverser" la frontière entre Linux et Windows pour lire chaque fichier PHP. 
- Pour un framework comme Laravel avec des milliers de fichiers dans `vendor`, cela crée une latence énorme.

**La Solution :** Déplacez le dossier du projet **directement dans le système de fichiers WSL2**.
- Au lieu de `C:\Users\...`, utilisez le chemin `\\wsl$\Ubuntu\home\votre_nom\projets`.
- La vitesse de lecture/écriture sera multipliée par **10x**.

---

## ⚙️ 2. Allocation des Ressources (Docker Desktop)

**Le problème :** Par défaut, Docker Desktop peut consommer trop ou pas assez de ressources.
- **CPU :** Si Docker utilise 100% du CPU, Windows ralentit.
- **RAM :** Notre stack (13 conteneurs) nécessite au moins **4 Go de RAM** dédiés.

**La Solution :**
1. Allez dans **Settings > Resources**.
2. Limitez l'utilisation de la mémoire (ex: 4GB ou 6GB) pour éviter que Docker ne "vole" toute la RAM de Windows.

---

## 🛠️ 3. L'Architecture Microservices

**Le problème :** Vous faites tourner **13 conteneurs simultanément**.
- Chaque service PHP-FPM, chaque serveur Nginx, MySQL, Redis, et l'API Gateway consomment de la mémoire et des cycles CPU, même au repos.

**La Solution :** N'allumez que ce dont vous avez besoin.
- Si vous travaillez uniquement sur l'Identity, faites :
  `docker compose up -d identity-php identity-nginx mysql redis`

---

## 🔍 4. Indexation de l'IDE (VS Code / PHPStorm)

**Le problème :** Votre éditeur de code essaie d'indexer les fichiers à l'intérieur des conteneurs ou les dossiers `vendor` géants, ce qui sature le disque.

**La Solution :** Excluez le dossier `vendor` et `node_modules` de l'indexation de votre antivirus (Windows Defender) et de la recherche de votre IDE.

---

## 🔄 5. Boucles de Health Check

**Le problème :** Lorsque nous avions des erreurs de "Health Check", Docker passait son temps à redémarrer les conteneurs en boucle. Le démarrage (Boot) est la phase la plus lourde pour le processeur.

**La Solution :** Maintenant que les Health Checks sont fixés (`kill -0`), les conteneurs restent stables (Up) et la consommation CPU devrait baisser après 2-3 minutes de fonctionnement.

---

## 💡 Astuce Rapide : Nettoyage
De temps en temps, Docker accumule des "déchets" (images orphelines, volumes inutilisés).
Lancez cette commande pour libérer de l'espace et de la RAM :
```bash
docker system prune -f
```
