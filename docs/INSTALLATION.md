# Installer MeriMap sur un serveur interne

Ce guide couvre une installation sur un serveur Linux de l'intranet (Ubuntu 24.04 LTS recommandé),
avec MySQL ou MariaDB et Apache ou Nginx. Une variante Windows est décrite à la fin.

Durée : environ une heure la première fois.

---

## 1. Vue d'ensemble

```
Poste avec Internet                         Serveur interne (sans Internet possible)
──────────────────                          ────────────────────────────────────────
deploy/build-release.sh   ──archive.tar.gz──▶  deploy/deploy.sh
 (Composer + Node : dépendances                 (extrait, migre la base, bascule
  et frontend compilés)                          la version active)
```

- **Le serveur n'a besoin ni d'Internet, ni de Composer, ni de Node.js** : tout est préparé dans l'archive.
- Chaque version est installée dans son propre dossier (`/var/www/merimap/releases/…`) ; un lien
  `current` pointe sur la version active. Revenir en arrière = repointer ce lien.
- La configuration (`.env`) et les fichiers (`storage/`) sont partagés entre les versions.

---

## 2. Prérequis du serveur

| Élément          | Version                            | Remarque                                                                 |
| ---------------- | ---------------------------------- | ------------------------------------------------------------------------ |
| PHP (FPM + CLI)  | **8.3 ou plus**                    | Extensions : `mysql`, `mbstring`, `xml`, `curl`, `zip`, `intl`, `bcmath` |
| Base de données  | MySQL 8.0+ ou MariaDB 10.6+        | La base Kerberos existante peut être sur le même serveur                 |
| Serveur web      | Apache 2.4 ou Nginx                | Fichiers prêts dans `deploy/`                                            |
| Certificat HTTPS | délivré par votre autorité interne | Requis pour les passkeys et les cookies sécurisés                        |
| Disque           | ~200 Mo par version                | 5 versions sont conservées                                               |

### Ubuntu 24.04 (PHP 8.3 fourni par défaut)

```bash
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath \
    mariadb-server apache2 unzip
```

### Debian 12

Debian 12 fournit PHP 8.2, trop ancien. Ajoutez le dépôt de référence de PHP pour Debian
(packages.sury.org), puis installez les mêmes paquets `php8.3-*`.

### Vérification

```bash
php -v                     # doit afficher 8.3.x ou plus
php -m | grep -E 'pdo_mysql|mbstring|intl|zip'
```

---

## 3. Base de données

```bash
sudo mysql
```

```sql
CREATE DATABASE merimap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'merimap'@'localhost' IDENTIFIED BY 'un-mot-de-passe-long-et-unique';
GRANT ALL PRIVILEGES ON merimap.* TO 'merimap'@'localhost';

-- Pour la reprise des données Kerberos : un accès en LECTURE SEULE suffit
CREATE USER 'merimap_import'@'localhost' IDENTIFIED BY 'un-autre-mot-de-passe';
GRANT SELECT ON carto.* TO 'merimap_import'@'localhost';
FLUSH PRIVILEGES;
```

---

## 4. Préparer l'archive (sur un poste avec Internet)

Prérequis du poste : PHP 8.3, Composer, Node.js 22, et un terminal bash
(Linux, macOS, ou WSL / Git Bash sous Windows).

```bash
cd merimap
./deploy/build-release.sh
# → merimap-release-AAAAMMJJ-HHMM.tar.gz
```

Copiez l'archive et le script `deploy/deploy.sh` sur le serveur (clé USB, `scp`, partage réseau…).

---

## 5. Première installation sur le serveur

### 5.1 Arborescence et configuration

```bash
sudo mkdir -p /var/www/merimap/shared
sudo tar -xzf merimap-release-*.tar.gz -O merimap/.env.example | sudo tee /var/www/merimap/shared/.env > /dev/null
sudo nano /var/www/merimap/shared/.env
```

Valeurs à renseigner :

```ini
APP_NAME=MeriMap
APP_ENV=production
APP_DEBUG=false                       # ne jamais laisser true en production
APP_URL=https://merimap.intranet.local
APP_LOCALE=fr

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=merimap
DB_USERNAME=merimap
DB_PASSWORD=un-mot-de-passe-long-et-unique

SESSION_SECURE_COOKIE=true            # cookies uniquement en HTTPS
MERIMAP_REGISTRATION=false            # pas d'inscription libre

# Envoi des e-mails (mot de passe oublié) : serveur SMTP interne
MAIL_MAILER=smtp
MAIL_HOST=smtp.intranet.local
MAIL_PORT=25
MAIL_FROM_ADDRESS=merimap@votre-domaine.cg
MAIL_FROM_NAME=MeriMap

# Reprise des données de l'ancienne application Kerberos
KERBEROS_DB_HOST=127.0.0.1
KERBEROS_DB_DATABASE=carto
KERBEROS_DB_USERNAME=merimap_import
KERBEROS_DB_PASSWORD=un-autre-mot-de-passe
```

Sans serveur SMTP, laissez `MAIL_MAILER=log` : la fonction « mot de passe oublié » ne pourra pas
envoyer d'e-mail, et un administrateur réinitialisera les mots de passe avec la commande du §7.

Générez la clé de chiffrement (une seule fois, **à conserver** : la changer déconnecte tout le monde) :

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)" | sudo tee -a /var/www/merimap/shared/.env
sudo sed -i '0,/^APP_KEY=$/{/^APP_KEY=$/d}' /var/www/merimap/shared/.env
sudo chmod 640 /var/www/merimap/shared/.env
sudo chown root:www-data /var/www/merimap/shared/.env
```

### 5.2 Déployer

```bash
sudo ./deploy.sh merimap-release-AAAAMMJJ-HHMM.tar.gz
```

Le script crée les tables, charge les référentiels (environnements, technologies et dates de fin de vie),
met en cache la configuration et active la version.

---

## 6. Serveur web et HTTPS

### Apache

```bash
sudo cp /var/www/merimap/current/deploy/apache-merimap.conf /etc/apache2/sites-available/merimap.conf
sudo nano /etc/apache2/sites-available/merimap.conf      # ServerName, chemins du certificat
sudo a2enmod proxy_fcgi setenvif rewrite headers ssl
sudo a2enconf php8.3-fpm
sudo a2ensite merimap
sudo apachectl configtest && sudo systemctl reload apache2
```

### Nginx (alternative)

```bash
sudo cp /var/www/merimap/current/deploy/nginx-merimap.conf /etc/nginx/sites-available/merimap
sudo ln -s /etc/nginx/sites-available/merimap /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

> **Point de sécurité essentiel** : la racine web doit être le dossier `public/`.
> Si le serveur web pointe sur la racine du projet, le fichier `.env` (mots de passe) devient téléchargeable.

Ajoutez enfin le nom `merimap.intranet.local` dans votre DNS interne.

---

## 7. Comptes utilisateurs

L'inscription libre est désactivée : les comptes sont créés en ligne de commande.

```bash
cd /var/www/merimap/current

# Premier administrateur (le mot de passe est demandé)
sudo -u www-data php artisan merimap:user dsi@votre-domaine.cg --name="Direction SI" --role=admin

# Autres comptes
sudo -u www-data php artisan merimap:user agent@votre-domaine.cg --role=editor   # contributeur
sudo -u www-data php artisan merimap:user lecteur@votre-domaine.cg --role=reader # lecture seule

# Changer un rôle ou réinitialiser un mot de passe : même commande sur un compte existant
sudo -u www-data php artisan merimap:user agent@votre-domaine.cg --role=admin
```

Les mots de passe doivent faire au moins 10 caractères.

---

## 8. Reprendre les données de Kerberos

```bash
cd /var/www/merimap/current
sudo -u www-data php artisan kerberos:import --dry-run     # simulation : rien n'est enregistré
sudo -u www-data php artisan kerberos:import               # import réel (peut être relancé sans doublon)
```

Lisez les avertissements affichés : doublons de noms, adresses IP invalides, et comptes dont le mot de
passe était stocké en clair (à réinitialiser avec la commande du §7). Les comptes Kerberos reçoivent
une adresse `identifiant@kerberos.invalid`, à remplacer par leur vraie adresse.

Une fois MeriMap validé, **arrêtez Kerberos**, ou au minimum supprimez `public/create_users.php`,
`public/check.php` et `public/carto_api.php`, accessibles sans authentification.

---

## 9. Mises à jour et retour arrière

Mise à jour : préparez une nouvelle archive (§4), puis sur le serveur :

```bash
sudo ./deploy.sh merimap-release-NOUVELLE.tar.gz
```

Retour à la version précédente en cas de problème :

```bash
ls -1t /var/www/merimap/releases/                      # la 2e ligne est la version précédente
sudo ln -sfn /var/www/merimap/releases/<version-précédente> /var/www/merimap/current
sudo systemctl reload php8.3-fpm
```

Attention : si la nouvelle version a modifié la base de données, restaurez aussi la sauvegarde
faite juste avant la mise à jour (§10).

---

## 10. Sauvegardes

À programmer chaque nuit (`sudo crontab -e`) :

```cron
# Base MeriMap, conservée 30 jours
30 1 * * * mysqldump --single-transaction merimap | gzip > /var/backups/merimap-$(date +\%F).sql.gz && find /var/backups -name 'merimap-*.sql.gz' -mtime +30 -delete
```

Sauvegardez aussi `/var/www/merimap/shared/` (fichier `.env` et clé `APP_KEY` compris).
**Faites une sauvegarde manuelle avant chaque mise à jour.**

---

## 11. Dépannage

| Symptôme                          | Cause probable               | Solution                                                             |
| --------------------------------- | ---------------------------- | -------------------------------------------------------------------- |
| Page blanche ou erreur 500        | Droits sur `storage/`        | `sudo chown -R www-data: /var/www/merimap/shared/storage`            |
| « No application encryption key » | `APP_KEY` vide               | Voir §5.1                                                            |
| Le CSS ne s'affiche pas           | Racine web incorrecte        | Le serveur doit pointer sur `current/public`                         |
| Connexion refusée en boucle       | Cookies sécurisés sans HTTPS | Activer HTTPS, ou `SESSION_SECURE_COOKIE=false` le temps du test     |
| Les passkeys ne fonctionnent pas  | Site en HTTP                 | Les passkeys exigent HTTPS avec un certificat reconnu par les postes |
| Modification du `.env` sans effet | Configuration en cache       | `sudo -u www-data php artisan optimize`                              |

Le journal d'erreurs se trouve dans `/var/www/merimap/shared/storage/logs/laravel.log`.

---

## Variante Windows Server

Possible si l'ancien Kerberos tournait sous Windows (XAMPP, WAMP, Laragon) :

1. Installez PHP 8.3 (version « Thread Safe » pour Apache) et activez dans `php.ini` les extensions
   `pdo_mysql`, `mbstring`, `intl`, `zip`, `curl`, `fileinfo`, `openssl`.
2. Décompressez l'archive dans `C:\merimap` (7-Zip lit les `.tar.gz`).
3. Pointez le `DocumentRoot` d'Apache sur `C:\merimap\public`, avec `AllowOverride All`
   et le module `mod_rewrite` actif.
4. Créez `C:\merimap\.env` comme au §5.1, puis dans PowerShell :
    ```powershell
    cd C:\merimap
    php artisan key:generate
    php artisan migrate --force
    php artisan db:seed --class=ReferenceSeeder --force
    php artisan optimize
    php artisan merimap:user dsi@votre-domaine.cg --role=admin
    ```
5. Pour les mises à jour, remplacez le dossier (en conservant `.env` et `storage\`), puis relancez
   `migrate --force` et `optimize`.
