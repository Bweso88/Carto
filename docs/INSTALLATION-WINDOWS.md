# Installer MeriMap sur Windows (64 bits)

Pour une machine Windows 10/11 ou Windows Server, 64 bits. La machine doit rester **allumée en permanence** :
les utilisateurs se connecteront à elle depuis le réseau.

Outils retenus (gratuits) :

| Outil                         | Pourquoi                                                           |
| ----------------------------- | ------------------------------------------------------------------ |
| **WampServer 3.3+ (64 bits)** | Apache + MySQL/MariaDB + PHP 8.3, gratuit, y compris en entreprise |
| **Composer**                  | Dépendances PHP                                                    |
| **Node.js LTS**               | Compilation de l'interface                                         |

> XAMPP ne convient pas : sa dernière version s'arrête à PHP 8.2, or MeriMap exige PHP 8.3.
> Laragon est payant pour un usage professionnel.

Si l'ancien Kerberos tourne sur la même machine avec XAMPP, **arrêtez XAMPP** (ou changez ses ports) :
les deux occuperaient les ports 80 (Apache) et 3306 (MySQL).

---

## 1. WampServer

1. Installez d'abord les **Visual C++ Redistributables** (paquet « tous les VC++ » proposé sur le site
   de WampServer) : sans eux, WampServer ne démarre pas.
2. Installez **WampServer 64 bits** en tant qu'administrateur (clic droit → Exécuter en tant qu'administrateur).
3. Si PHP 8.3 n'est pas inclus, installez l'add-on « PHP 8.3 » depuis le site de WampServer.
4. Icône WampServer (barre des tâches) → **PHP → Version → 8.3.x**.
5. Icône → **PHP → Extensions PHP** : cochez `intl`, `zip`, `fileinfo`, `pdo_mysql`, `mbstring`, `openssl`, `curl`.
6. Icône → **Apache → Modules Apache** : cochez `rewrite_module`.
7. L'icône doit être **verte**. Orange = un service ne démarre pas (souvent le port 80 déjà pris, voir Dépannage).

## 2. PHP en ligne de commande, Composer, Node.js

1. Ajoutez le dossier de PHP 8.3 au **PATH** Windows (Paramètres → Système → Informations →
   Paramètres avancés → Variables d'environnement → Path → Nouveau) :
   `C:\wamp64\bin\php\php8.3.x` (remplacez x par votre version).
2. Ouvrez `C:\wamp64\bin\php\php8.3.x\php.ini` et retirez le `;` devant les mêmes extensions qu'au §1.5.
   (Ce fichier sert à la ligne de commande ; celui d'Apache est réglé par le menu WampServer.)
3. Installez **Composer** (Composer-Setup.exe, sur getcomposer.org) en choisissant ce même `php.exe`.
4. Installez **Node.js LTS** (nodejs.org).
5. Ouvrez un **nouveau** PowerShell et vérifiez :
    ```powershell
    php -v         # 8.3.x
    composer -V
    node -v        # v22 ou plus
    ```

## 3. Base de données

Ouvrez phpMyAdmin (`http://localhost/phpmyadmin`, utilisateur `root`) → onglet **SQL** :

```sql
CREATE DATABASE merimap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'merimap'@'localhost' IDENTIFIED BY 'un-mot-de-passe-long';
CREATE USER 'merimap'@'127.0.0.1' IDENTIFIED BY 'un-mot-de-passe-long';
GRANT ALL PRIVILEGES ON merimap.* TO 'merimap'@'localhost', 'merimap'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Mettez aussi un mot de passe au compte `root` de MySQL : il est vide par défaut.

## 4. Installer MeriMap

1. Décompressez MeriMap dans `C:\merimap`.
2. PowerShell (en administrateur) :
    ```powershell
    cd C:\merimap
    powershell -ExecutionPolicy Bypass -File deploy\windows\install.ps1
    ```
    Le script vérifie les outils, pose quelques questions (adresse du site, base de données,
    e-mail de l'administrateur), installe les dépendances, compile l'interface, crée les tables
    et le premier compte.

## 5. Publier le site sur le réseau

1. Ouvrez `C:\wamp64\bin\apache\apache2.4.x\conf\extra\httpd-vhosts.conf` et ajoutez à la fin le contenu
   de `C:\merimap\deploy\windows\merimap-vhost.conf` (adaptez `ServerName`).
2. Icône WampServer → **Redémarrer les services**.
3. Ouvrez le port 80 dans le pare-feu (PowerShell administrateur) :
    ```powershell
    New-NetFirewallRule -DisplayName "MeriMap HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow
    ```
4. Faites créer dans le DNS interne le nom `merimap.intranet.local` vers l'adresse IP de la machine.
   En attendant, un poste peut tester en ajoutant cette ligne dans `C:\Windows\System32\drivers\etc\hosts` :
   `192.168.x.x  merimap.intranet.local`
5. Démarrage automatique : `services.msc` → services **wampapache64** et **wampmysqld64**
   (ou **wampmariadb64**) → Type de démarrage **Automatique**.

**HTTPS** (recommandé, requis pour les passkeys) : WampServer 3.3.2+ sait créer un VirtualHost HTTPS
(menu Vos VirtualHosts). Utilisez un certificat de votre autorité interne, puis passez `APP_URL` en
`https://…` et `SESSION_SECURE_COOKIE=true` dans `C:\merimap\.env`, et lancez `php artisan optimize`.

## 6. Comptes et reprise Kerberos

```powershell
cd C:\merimap
php artisan merimap:user agent@votre-domaine.cg --role=editor    # contributeur
php artisan merimap:user lecteur@votre-domaine.cg --role=reader  # lecture seule

# Reprise Kerberos : renseigner KERBEROS_DB_* dans .env, puis
php artisan kerberos:import --dry-run
php artisan kerberos:import
```

## 7. Sauvegardes et mises à jour

Sauvegarde manuelle : `powershell -ExecutionPolicy Bypass -File deploy\windows\backup.ps1`
(fichiers dans `C:\merimap\backups`, 30 jours conservés).

Sauvegarde automatique chaque nuit à 1 h 30 (PowerShell administrateur) :

```powershell
schtasks /Create /TN "MeriMap sauvegarde" /SC DAILY /ST 01:30 /RU SYSTEM `
  /TR "powershell -NoProfile -ExecutionPolicy Bypass -File C:\merimap\deploy\windows\backup.ps1"
```

Mise à jour : remplacez les fichiers de `C:\merimap` par la nouvelle version **sans supprimer**
`.env`, `storage\` ni `backups\`, puis :

```powershell
powershell -ExecutionPolicy Bypass -File deploy\windows\update.ps1
```

Le script sauvegarde la base, met le site en maintenance, met à jour, migre, puis rouvre le site.

## Dépannage

| Symptôme                              | Solution                                                                            |
| ------------------------------------- | ----------------------------------------------------------------------------------- |
| Icône WampServer orange               | Port 80 occupé : arrêter IIS (« Service de publication World Wide Web ») ou XAMPP   |
| « VCRUNTIME140.dll introuvable »      | Installer les Visual C++ Redistributables (§1.1)                                    |
| `install.ps1` : extensions manquantes | Activer les extensions dans le `php.ini` de la ligne de commande (§2.2)             |
| `php -v` affiche une autre version    | Le PATH pointe vers un autre PHP (ex. XAMPP) : corriger le PATH, rouvrir PowerShell |
| Erreur 500                            | Lire `C:\merimap\storage\logs\laravel.log`                                          |
| « Forbidden » depuis un autre poste   | Vérifier `Require all granted` dans le VirtualHost et le pare-feu                   |
| Le CSS ne s'affiche pas               | Le `DocumentRoot` doit être `C:/merimap/public`                                     |
