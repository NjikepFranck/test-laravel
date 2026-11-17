# Orbit — suivi de projets

API Laravel 12, SQLite, sans frontend ni Docker. Les dépendances exactes sont verrouillées dans `composer.lock`.

## 1. Compréhension et arbitrages

### Question 1 — Reformulation du besoin métier

Orbit souhaite centraliser la gestion et le suivi de ses projets dans une application interne.
Les responsables doivent pouvoir créer des projets, attribuer des tâches aux collaborateurs et suivre leur avancement.
Les collaborateurs doivent consulter leurs tâches et actualiser leur statut, tandis que les responsables identifient rapidement les retards.
La priorité est de rendre ce suivi utilisable rapidement tout en garantissant que chaque lecture et modification respecte les droits de la personne authentifiée.

### Question 2 — Trois questions à poser au client

1. **Quels sont les rôles et les autorisations des utilisateurs ?** Un responsable peut-il modifier les tâches de ses collaborateurs ? Un collaborateur peut-il consulter les tâches des autres ou uniquement les siennes ?

   **Impact sur la solution :** la réponse détermine les Policies Laravel et le filtrage des listes côté serveur. Hypothèse retenue : un responsable consulte toutes les tâches de ses propres projets et peut changer leurs statuts ; un collaborateur consulte et modifie uniquement les tâches qui lui sont attribuées. Les droits de responsable sont propres à chaque projet, sans rôle global donnant accès à tous les projets.

2. **Comment souhaitez-vous identifier les tâches en retard ?** Une tâche est-elle en retard lorsque son échéance est dépassée et que son statut est différent de `DONE` ? La date reste-t-elle valable jusqu’à la fin de la journée, et dans quel fuseau horaire ?

   **Impact sur la solution :** la réponse détermine le type de champ (date ou date et heure) et le calcul du retard. Hypothèse retenue : échéance inclusive, retard à partir du lendemain à 00 h dans le fuseau `Africa/Douala`, sauf si la tâche est `DONE`. Cette règle est exposée dans le champ JSON `is_overdue`.

3. **Souhaitez-vous recevoir des notifications automatiques ?** Les collaborateurs doivent-ils être avertis lorsqu’une échéance approche ou lorsqu’une tâche est en retard ?

   **Impact sur la solution :** une réponse positive nécessiterait de préciser les destinataires, les délais et le canal, puis de prévoir Laravel Notifications et une exécution planifiée avec le Scheduler. Pour cette première version, les notifications sont reportées ; les retards restent consultables dans les réponses de l’API.

### Question 3 — Définition du MVP

Le MVP est la première version fonctionnelle contenant les fonctionnalités indispensables pour valider le suivi des tâches et les droits d’accès.

1. **Consultation des projets et des tâches autorisés :** permettre au responsable de consulter ses projets et leurs tâches, et à chaque collaborateur de retrouver les tâches qui lui sont attribuées. Cela fournit un point d’entrée simple et protège les données des autres utilisateurs.
2. **Suivi de l’avancement et des retards :** permettre la mise à jour du statut (`TODO`, `IN_PROGRESS`, `DONE`) d’une tâche autorisée et exposer son échéance ainsi que `is_overdue`. Le responsable peut ainsi repérer les tâches à traiter en priorité.
3. **Authentification et gestion des autorisations :** identifier chaque utilisateur et vérifier ses droits côté serveur à chaque accès. Cette protection est indispensable pour empêcher la modification du travail d’autrui sans autorisation.

**Limite explicite de la démonstration :** la création des projets, des tâches et des affectations fait partie du besoin métier complet, mais elle est réalisée ici par un seeder. Aucun endpoint de création n’est livré : le périmètre technique demandé comporte trois endpoints de consultation et de mise à jour du statut. La création et l’affectation par le responsable constituent la prochaine extension métier à implémenter et à sécuriser.

**Fonctionnalité à reporter :** les notifications automatiques par e-mail. Elles améliorent le suivi, mais ne sont pas indispensables pour valider le parcours initial : le responsable peut déjà identifier les retards dans les données retournées par l’API.

### Question 4 — Critère d’acceptation testable (4 points)

Étant donné Alice et Bob, collaborateurs du même projet, lorsqu’Alice authentifiée tente de passer à `DONE` une tâche attribuée à Bob sans autorisation, l’API retourne `403 Forbidden` et le statut de la tâche reste `IN_PROGRESS` en base.

Ce scénario est automatisé dans `tests/Feature/OrbitApiTest.php`, dans le test `test_collaborator_cannot_modify_another_collaborators_task`.

## 2. Installation et lancement

Prérequis : PHP 8.4 ou supérieur compatible avec le lock, Composer 2, extensions PDO SQLite, sqlite3, mbstring, openssl, fileinfo, cURL et XML/DOM. ZIP facilite l'installation. Aucun Node, build d'assets ou serveur MySQL requis.

Depuis le dossier de ce README :

```sh
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

SQLite est configuré par défaut. `APP_DEBUG=false` évite d'exposer les détails internes. Le fuseau métier est `Africa/Douala` dans `config/app.php`. Le fichier `.env`, les données SQLite et `vendor` sont exclus de Git. Ne pas exécuter le seeder de démonstration sur une base de production.

### Environnement Windows/Wamp de cette livraison

PHP et Composer n'étant pas disponibles dans le PATH initial, des outils locaux ont été préparés dans `C:\wamp64\www\orbit\.tools` (non versionné). Pour lancer immédiatement depuis ce dossier :

```powershell
$env:PHPRC = 'C:\wamp64\www\orbit\.tools\php.ini'
$env:PATH = 'C:\wamp64\bin\php\php8.4.15;' + $env:PATH
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Sous Windows/Wamp, `--no-reload` conserve les variables du processus PHP enfant et corrige `Failed to listen ... (reason: ?)`. Redemarrer manuellement le serveur apres modification de `.env`.

Pour une autre machine, suivre l'installation standard ci-dessus ; `.tools` n'est pas nécessaire.

### Données de démonstration et authentification

HTTP Basic Laravel via `Auth::onceBasic()`, sans session : envoyer l'email comme identifiant et le mot de passe à chaque requête. Aucun simple identifiant utilisateur fourni par le client n'est considéré comme une preuve d'identité. Les mots de passe sont hachés par le modèle User.

| Email | Accès |
| --- | --- |
| manager@orbit.test | Responsable du projet Lancement Orbit, voit ses 3 tâches et peut changer leurs statuts |
| alice@orbit.test | Voit le projet et ses 2 tâches, modifie ses propres statuts |
| bob@orbit.test | Voit le projet et sa tâche, modifie son propre statut |
| outsider@orbit.test | Aucun projet accessible |

Mot de passe commun de démonstration : `Orbit-demo-2026!`. Un projet et trois tâches sont créés, dont une TODO en retard, une IN_PROGRESS à venir et une DONE avec une ancienne échéance. Les dates sont relatives à la première exécution du seeder. Le relancer ne crée pas de doublons et ne réinitialise pas les données existantes.

Basic est un compromis de démonstration explicitement autorisé par l'énoncé. En dehors de localhost, HTTPS est indispensable. Remplacer les comptes connus et prévoir Sanctum avec jetons révocables avant une utilisation réelle. Sur Apache/FastCGI, vérifier la transmission de l'en-tête Authorization si les identifiants corrects produisent un 401.

## 3. API

Les noms de colonnes sont anglais : `name` = nom, `title` = titre, `status` = statut, `due_date` = échéance ; `responsable_id` suit l'énoncé. Les relations sont Project.responsable, Project.tasks, Task.project, Task.assignedUser, User.projects et User.assignedTasks.

| Méthode et chemin | Comportement |
| --- | --- |
| GET /api/projects | Projets dont l'utilisateur est responsable ou possède au moins une tâche |
| GET /api/projects/{project}/tasks | Toutes les tâches pour le responsable ; uniquement les tâches affectées à l'utilisateur pour un collaborateur |
| PATCH /api/tasks/{task}/status | Corps JSON : `{"status":"IN_PROGRESS"}` ; autorisé à l'affecté ou au responsable du projet |

Les listes retournent `data`, `links` et les informations de pagination à la racine (`current_page`, `last_page`, `total`), avec 20 éléments par page (`?page=2`). Les tâches sont triées par échéance puis identifiant. La réponse PATCH est `{"data":{...}}`. Chaque tâche inclut `is_overdue`, vrai si `due_date` est avant aujourd'hui et `status` différent de DONE. Une échéance du jour n'est pas encore en retard.

Statuts autorisés : `TODO`, `IN_PROGRESS`, `DONE`. Tout passage entre ces statuts est autorisé. Un PATCH ne modifie jamais titre, échéance, projet ou affectation ; leurs valeurs non vides sont rejetées en validation, les autres champs inconnus sont ignorés et ne sont jamais persistés.

Réponses : 200 succès, 401 absent/mauvais identifiants, 403 ressource existante mais accès interdit, 404 ressource inexistante, 422 validation invalide, 429 limite de 60 requêtes par minute et par IP. Les erreurs `/api/*` sont JSON même sans en-tête Accept. Les policies contrôlent l'accès serveur et le contrôleur limite les champs écrits. Un rôle n'est pas global : être responsable d'un projet ne donne aucun droit sur les autres.

### Exemples exécutables

Utiliser `curl.exe` sous Windows PowerShell, ou `curl` sous Linux/macOS. Les identifiants numériques ci-dessous correspondent à une base initiale vierge.

```sh
curl.exe -u 'manager@orbit.test:Orbit-demo-2026!' -H 'Accept: application/json' http://127.0.0.1:8000/api/projects
curl.exe -u 'manager@orbit.test:Orbit-demo-2026!' -H 'Accept: application/json' http://127.0.0.1:8000/api/projects/1/tasks
curl.exe -u 'alice@orbit.test:Orbit-demo-2026!' -H 'Accept: application/json' -H 'Content-Type: application/json' -X PATCH -d '{"status":"IN_PROGRESS"}' http://127.0.0.1:8000/api/tasks/1/status
curl.exe -u 'alice@orbit.test:Orbit-demo-2026!' -H 'Accept: application/json' -H 'Content-Type: application/json' -X PATCH -d '{"status":"DONE"}' http://127.0.0.1:8000/api/tasks/2/status
```

La dernière requête doit renvoyer 403. Dans Windows PowerShell 5, si l'échappement JSON de curl pose problème, utiliser :

```powershell
$headers = @{ Authorization = 'Basic ' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes('alice@orbit.test:Orbit-demo-2026!')) }
Invoke-RestMethod -Method Patch -Uri http://127.0.0.1:8000/api/tasks/1/status -Headers $headers -ContentType 'application/json' -Body '{"status":"IN_PROGRESS"}'
```

## 4. Vérification

```sh
php artisan test
php vendor/bin/pint --test
php artisan route:list --path=api
```

### Tests automatisés sous Windows/Wamp

```powershell
$env:PHPRC = "$PWD\.tools\php.ini"
$env:PATH = 'C:\wamp64\bin\php\php8.4.15;' + $env:PATH
php artisan test
```

Les tests utilisent une base SQLite **en mémoire**, séparée de la démonstration. Ils couvrent les identifiants invalides, les trois routes sans authentification, la visibilité des projets, le filtrage des tâches, les modifications interdites sans effet en base, l'autorisation du responsable, les trois statuts, les entrées invalides, la réaffectation interdite, les 404 JSON, la limite de date du retard et l'idempotence du seeder.

Vérification effectuée sur cette livraison : **13 tests réussis, 59 assertions**, contrôle Pint réussi, migrations/seed exécutés. Les appels HTTP réels ont confirmé 401 sans identifiants, 200 pour la lecture du responsable et le PATCH de l'affecté, et 403 pour la modification d'une tâche d'autrui.

## 5. Diagnostic rapide : trois risques et corrections

Code fourni dans le sujet :

```php
public function update(Request $request, $id) {
    $task = Task::find($id);
    $task->update($request->all());
    return response()->json($task);
}
```

| Risque concret | Correction |
| --- | --- |
| **Absence de contrôle des droits sur la tâche :** aucun contrôle visible ne vérifie que la personne peut modifier la tâche récupérée par son identifiant. Un collaborateur peut viser la tâche d’un autre (IDOR), même si une authentification existe au niveau des routes. | Authentifier la requête puis appeler `Gate::authorize('update', $task)` avec une policy autorisant uniquement la personne affectée ou le responsable du projet. **Priorité 1** : cette faille permet de modifier le travail d’autrui, en contradiction directe avec la demande du client. Tester le 403 et vérifier que la base reste inchangée. |
| **Écriture de données non validées avec `$request->all()` :** aucun statut autorisé n’est vérifié et, selon la configuration `$fillable`/`$guarded`, des champs sensibles comme `assigned_user_id` ou `project_id` peuvent être modifiés. | Valider `status` avec `required`, `string` et `Rule::in(['TODO', 'IN_PROGRESS', 'DONE'])`, rejeter les changements de champs métier hors périmètre et écrire explicitement seulement le statut validé. La protection contre l’affectation de masse ne remplace ni la validation ni la policy. |
| **Tâche inexistante non gérée :** `Task::find($id)` retourne `null` pour un identifiant absent ; appeler `update()` provoque alors une erreur serveur au lieu d’une réponse métier correcte. | Utiliser `findOrFail($id)` ou le route model binding `Task $task` pour obtenir un 404 JSON. Tester un identifiant inexistant. |

Le flux corrigé est implémenté dans `TaskController::update` pour `PATCH /api/tasks/{task}/status` : résolution de la tâche → policy → validation → écriture ciblée → réponse JSON. Les tests vérifient les droits, les données invalides, les tentatives de réaffectation et les identifiants inexistants.

## 6. Périmètre livré, limites et décision principale

Fonctionne : installation Laravel, migrations, seed répétable, authentification, trois endpoints, relations, pagination, indicateur de retard, contrôles d'accès et tests automatisés.

Manque volontairement : création/édition de projets et affectation par API, gestion des comptes, notifications, historique des changements et interface. La création et l'affectation sont démontrées par le seeder : le périmètre technique demandé impose trois endpoints, et la priorité a été donnée à leur sécurité de bout en bout. Le besoin métier complet de création par le responsable reste donc à implémenter.

SQLite convient à cette démonstration, mais une charge importante demanderait une base serveur et une étude des accès concurrents. Les changements de statut suivent le dernier enregistrement reçu ; aucun verrouillage optimiste ni audit. Le 403 distingue une ressource existante d'un ID inexistant : une politique 404 uniforme pourrait réduire cette information. L'authentification et les requêtes partagent une limite par IP, adaptée à la démonstration et à revoir derrière un proxy.

### Restitution : décision et prochaine amélioration

J'ai privilégié la protection du travail de chaque collaborateur et un parcours API utilisable immédiatement.
Les droits dépendent du projet et de l'affectation, plutôt que d'un rôle global trop permissif.
HTTP Basic et SQLite limitent le temps de mise en place pour concentrer les vérifications sur les accès.
L'amélioration métier prioritaire est la création des projets et l'affectation des tâches par le responsable, protégées par des policies et des tests ; l'authentification de production devra aussi être préparée avant déploiement.

## 7. Livraison GitHub

https://github.com/NjikepFranck/test-laravel.git


Références officielles consultées : [installation Laravel 12](https://laravel.com/docs/12.x/installation), [authentification HTTP Basic](https://laravel.com/docs/12.x/authentication#http-basic-authentication), [autorisation](https://laravel.com/docs/12.x/authorization).
