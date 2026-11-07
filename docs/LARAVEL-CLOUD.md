# Déployer MeriMap sur Laravel Cloud

Laravel Cloud héberge l'application sans serveur à administrer : il récupère le code sur GitHub,
le compile, migre la base et met le site en ligne avec une adresse `https://….laravel.cloud`.
Idéal pour un **pilote** avant l'installation sur le serveur interne.

Coût : offre Starter à 5 $/mois avec 5 $ de crédits d'usage inclus, premier mois offert
(tarifs au moment de la rédaction : vérifiez sur cloud.laravel.com/pricing). Fixez une limite de dépenses
dans les réglages de l'organisation.

> **Données sensibles.** Une cartographie contient les noms, adresses IP, systèmes en fin de vie et
> dépendances critiques du SI : c'est une carte idéale pour un attaquant. Pour le pilote, utilisez les
> **données de démonstration**, et n'importez les vraies données qu'après validation de la DSI
> (comptes nominatifs, double authentification activée).

---

## 1. Prérequis

- Le code de MeriMap dans un dépôt **GitHub privé** (ou GitLab / Bitbucket).
- Un compte sur [cloud.laravel.com](https://cloud.laravel.com) (offre Starter).

## 2. Créer l'application

1. Tableau de bord Laravel Cloud → **+ New application**.
2. Connectez GitHub et choisissez le dépôt MeriMap, branche `main`.
3. Région : la région européenne la plus proche de vos utilisateurs.

## 3. Ajouter la base de données

Sur le schéma de l'environnement : **Add database** → **Laravel MySQL** (ou **Serverless Postgres** :
MeriMap est testé sur les deux), dans la **même région** que l'application.
Laravel Cloud injecte automatiquement `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`…

## 4. Réglages de l'environnement

**General → Node version** : 22 (ou plus récent).

**Deployments → Build commands** :

```sh
composer install --no-dev --optimize-autoloader
npm ci --audit false
npm run build
php artisan optimize
```

**Deployments → Deploy commands** :

```sh
php artisan migrate --force
php artisan db:seed --class=ReferenceSeeder --force
```

**Environment variables** (à ajouter ; celles de la base sont déjà injectées) :

```ini
APP_NAME=MeriMap
APP_LOCALE=fr
APP_FALLBACK_LOCALE=en
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
MERIMAP_REGISTRATION=false
MAIL_MAILER=log
```

Vérifiez que `APP_KEY` est bien présent dans la liste. S'il manque, générez-en un sur votre poste
avec `php artisan key:generate --show` et collez la valeur.
**Ne changez plus cette clé ensuite** : cela déconnecterait tout le monde.

## 5. Déployer

Cliquez sur **Deploy**. Les déploiements suivants partent automatiquement à chaque `git push`
sur la branche `main`.

## 6. Créer les comptes

Depuis l'environnement, ouvrez l'exécution de commandes (onglet **Commands**) et lancez :

```sh
php artisan merimap:user dsi@votre-domaine.cg --name="Direction SI" --role=admin --password="un-mot-de-passe-solide"
```

Rôles disponibles : `admin`, `editor` (contributeur), `reader` (lecture seule).
Pensez à activer la double authentification depuis **Paramètres → Sécurité** une fois connecté.

## 7. (Pilote) Charger les données de démonstration

```sh
php artisan db:seed --class=DemoSeeder --force
```

Ajoute un jeu réaliste (hyperviseurs, VMs, ERP, passerelle de paiement, Active Directory, flux…)
pour présenter la topologie et l'analyse d'impact. À ne pas lancer sur une base contenant de vraies données.

## 8. Et les données Kerberos ?

Laravel Cloud est sur Internet : il ne peut pas joindre la base `carto` de votre réseau interne,
et il ne faut pas l'exposer. Deux options :

- **Recommandé** : garder Laravel Cloud pour le pilote (données de démonstration), et faire la reprise
  Kerberos lors de l'installation interne (`docs/INSTALLATION.md` ou `docs/INSTALLATION-WINDOWS.md`).
- Si la DSI valide l'hébergement des vraies données : lancer l'import sur un poste interne vers une base
  MySQL locale, exporter cette base, puis l'importer dans la base Laravel Cloud.

## Dépannage

| Symptôme                                              | Solution                                                             |
| ----------------------------------------------------- | -------------------------------------------------------------------- |
| Le build échoue sur `npm run build`                   | Vérifier la version de Node (22+) dans General                       |
| Erreur 500 juste après le premier déploiement         | Base non attachée ou `APP_KEY` absent ; redéployer après correction  |
| Les variables modifiées n'ont pas d'effet             | Redéployer : les variables ne s'appliquent qu'au déploiement suivant |
| Impossible de se connecter, pas de lien d'inscription | Normal : créer les comptes avec `merimap:user` (§6)                  |
