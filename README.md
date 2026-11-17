# Orbit — suivi de projets

API réalisée pour le test technique Laravel. J’ai utilisé Laravel 12, PHP 8.4 fourni par Wamp64 et SQLite. Il n’y a pas de frontend.

## Le besoin et mes choix

Orbit veut centraliser le suivi de ses projets et des tâches attribuées aux collaborateurs.
Chacun doit pouvoir consulter ses tâches et mettre à jour leur avancement.
Le responsable doit repérer rapidement les retards sur ses projets.
La priorité est de rendre ce suivi utilisable sans permettre à quelqu’un de modifier le travail d’un autre sans autorisation.

### Questions à poser au client

- **Qui peut voir et modifier les tâches ?** Cela détermine les autorisations. J’ai retenu qu’un collaborateur accède à ses tâches et que le responsable peut consulter et changer les statuts de toutes les tâches de ses projets.
- **Quand une tâche devient-elle en retard ?** Cela détermine le calcul des échéances. Ici, elle est en retard après sa date d’échéance, dans le fuseau `Africa/Douala`, si elle n’est pas `DONE`.
- **Faut-il des notifications par e-mail ?** Cela demanderait un envoi de mails et une exécution planifiée. Je reporte cette fonctionnalité : les retards sont déjà visibles dans les réponses de l’API.

### MVP retenu

1. Consulter les projets et les tâches accessibles, pour retrouver son travail.
2. Changer le statut d’une tâche et identifier les retards, pour suivre l’avancement.
3. Authentifier les utilisateurs et vérifier leurs droits, pour protéger les données.

**Critère d’acceptation :** si Alice essaie de modifier une tâche de Bob sans autorisation, l’API répond `403` et la tâche reste inchangée en base. Un test vérifie ce cas.

## Installation et lancement

Prérequis : PHP 8.4 avec les extensions Laravel et SQLite/PDO SQLite, Composer 2 et Git. MySQL n’est pas utilisé.

Depuis PowerShell, dans le dossier du projet :

```powershell
$env:PATH = 'C:\wamp64\bin\php\php8.4.15;' + $env:PATH
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan serve --no-reload
```

Adapter le chemin de PHP à la version installée dans Wamp. Le `.env` utilise `DB_CONNECTION=sqlite`. L’API démarre sur **http://127.0.0.1:8000** ; Ctrl + C arrête le serveur.

Sur ma machine, j’utilise aussi une configuration PHP et Composer dans `.tools`. Ce dossier n’est pas versionné. S’il est présent, définir `$env:PHPRC = "$PWD\.tools\php.ini"` avant les commandes et remplacer `composer install` par `php .tools/composer.phar install`. L’option `--no-reload` évite le problème de lancement rencontré sous Windows.

## Tester l’API

Le seeder crée un projet, trois tâches et les comptes suivants. Le mot de passe commun est **`Orbit-demo-2026!`**.

| Email | Accès |
| --- | --- |
| `manager@orbit.test` | Toutes les tâches de son projet |
| `alice@orbit.test` | Ses deux tâches |
| `bob@orbit.test` | Sa tâche |
| `outsider@orbit.test` | Aucun projet |

L’authentification utilise HTTP Basic : renseigner l’email et le mot de passe dans Postman, sous **Authorization → Basic Auth**.

| Méthode | Route | Utilité |
| --- | --- | --- |
| GET | `/api/projects` | Lister les projets accessibles |
| GET | `/api/projects/{project}/tasks` | Consulter les tâches autorisées du projet |
| PATCH | `/api/tasks/{task}/status` | Modifier le statut d’une tâche autorisée |

Corps JSON du PATCH :

```json
{"status": "IN_PROGRESS"}
```

Les statuts possibles sont `TODO`, `IN_PROGRESS` et `DONE`. Seul le statut est modifiable. Les listes sont paginées par 20 ; `is_overdue` indique les tâches en retard.

Pour un premier essai dans un deuxième terminal :

```powershell
curl.exe -u 'manager@orbit.test:Orbit-demo-2026!' http://127.0.0.1:8000/api/projects
```

Sur la base initiale, Alice peut modifier `/api/tasks/1/status`, mais reçoit un `403` sur `/api/tasks/2/status`, qui appartient à Bob. Sans identifiants valides, la réponse est `401`. Un statut invalide retourne `422` et une tâche inexistante retourne `404`.

### Tests automatisés

```powershell
php artisan test
```

Dernier résultat : **13 tests réussis, 59 assertions**. Ils vérifient les accès, les changements de statut, la validation et les retards. La base de test est en mémoire : les données de démonstration ne sont pas modifiées. Le serveur n’a pas besoin d’être lancé pour ces tests.

## Diagnostic du code fourni

Le contrôleur du sujet utilise `Task::find($id)` puis `$task->update($request->all())`. J’y relève trois risques :

1. **Pas de vérification des droits sur la tâche.** Un utilisateur peut viser celle d’un autre. Il faut vérifier une Policy avant la modification. C’est le premier point à corriger, car il touche directement à la protection du travail des collaborateurs.
2. **Les données reçues ne sont pas validées.** Selon les champs autorisés du modèle, l’affectation ou le projet pourraient être modifiés. Il faut valider le statut et enregistrer uniquement ce champ.
3. **La tâche peut ne pas exister.** `find()` retourne alors `null` et `update()` provoque une erreur. `findOrFail()` ou le route model binding permettent de retourner un `404`.

Ces corrections sont appliquées dans le contrôleur et les Policies du projet.

## Limites et prochaine étape

Les trois endpoints sont fonctionnels. Les projets, les tâches et les affectations sont créés par le seeder ; leur création par API reste à développer. Les notifications par e-mail et l’historique des changements ne sont pas inclus. HTTP Basic avec des comptes connus sert à la démonstration ; avant une utilisation réelle, il faudra adapter l’authentification et utiliser HTTPS.

J’ai choisi de concentrer cette première version sur le suivi et les contrôles d’accès.
SQLite et HTTP Basic m’ont permis de garder une installation simple avec Wamp.
Les droits sont vérifiés côté serveur selon le projet et la personne affectée.
La prochaine étape serait d’ajouter la création des projets et l’attribution des tâches par le responsable, avec leurs tests d’autorisation.

[Dépôt GitHub](https://github.com/NjikepFranck/test-laravel)
