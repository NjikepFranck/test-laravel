# Orbit — suivi de projets

API Laravel 12, SQLite, sans frontend ni Docker. Les dépendances exactes sont verrouillées dans `composer.lock`.

## 1. Compréhension du besoin

Orbit veut centraliser les projets et les tâches afin que chacun sache ce qu'il doit réaliser.
Les collaborateurs doivent pouvoir consulter leurs tâches et actualiser leur avancement.
Le responsable doit identifier rapidement les retards et superviser les tâches de ses projets.
La priorité est un parcours fonctionnel où chaque lecture et modification respecte les droits de l'utilisateur authentifié.

### Trois questions au client

| Question | Conséquence sur la solution |
| --- | --- |
| Le responsable peut-il modifier les tâches de ses collaborateurs et ceux-ci peuvent-ils voir les tâches de leurs collègues ? | Détermine les policies et le filtrage des listes. Hypothèse retenue : le responsable peut tout consulter et changer les statuts dans ses propres projets ; un collaborateur accède uniquement à ses tâches. |
| Une tâche est-elle en retard dès le début de sa date d'échéance ou après la fin de cette journée, et dans quel fuseau ? | Détermine date ou datetime et règle de retard. Hypothèse : échéance inclusive, retard le lendemain à 00 h dans Africa/Douala, sauf statut DONE. |
| Qui crée les comptes, les projets et les affectations, et faut-il un annuaire d'entreprise ? | Détermine l'authentification et le périmètre de création. Hypothèse : comptes et affectations préchargés pour cette démonstration ; création métier à ajouter ensuite avec autorisation du responsable. |

### MVP et arbitrages

1. Consulter les projets accessibles : point d'entrée minimal, filtré côté serveur.
2. Consulter les tâches autorisées d'un projet, avec échéance et indicateur `is_overdue` : rend les retards visibles au responsable.
3. Modifier uniquement le statut d'une tâche autorisée : permet le suivi réel sans ouvrir la modification des affectations.

À reporter : les notifications automatiques de retard. L'indicateur JSON suffit pour valider la valeur métier avant d'ajouter une planification et un canal de notification.

**Critère d'acceptation testable :** Alice, authentifiée et affectée au même projet que Bob, tente de passer la tâche de Bob à DONE. L'API répond 403 et le statut en base reste IN_PROGRESS. Ce scénario est automatisé dans `tests/Feature/OrbitApiTest.php`.

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
| PATCH /api/tasks/{task} | Corps JSON : `{"status":"IN_PROGRESS"}` ; autorisé à l'affecté ou au responsable du projet |

Les listes retournent `data`, `links` et les informations de pagination à la racine (`current_page`, `last_page`, `total`), avec 20 éléments par page (`?page=2`). Les tâches sont triées par échéance puis identifiant. La réponse PATCH est `{"data":{...}}`. Chaque tâche inclut `is_overdue`, vrai si `due_date` est avant aujourd'hui et `status` différent de DONE. Une échéance du jour n'est pas encore en retard.

Statuts autorisés : `TODO`, `IN_PROGRESS`, `DONE`. Tout passage entre ces statuts est autorisé. Un PATCH ne modifie jamais titre, échéance, projet ou affectation ; leurs valeurs non vides sont rejetées en validation, les autres champs inconnus sont ignorés et ne sont jamais persistés.

Réponses : 200 succès, 401 absent/mauvais identifiants, 403 ressource existante mais accès interdit, 404 ressource inexistante, 422 validation invalide, 429 limite de 60 requêtes par minute et par IP. Les erreurs `/api/*` sont JSON même sans en-tête Accept. Les policies contrôlent l'accès serveur et le contrôleur limite les champs écrits. Un rôle n'est pas global : être responsable d'un projet ne donne aucun droit sur les autres.

### Exemples exécutables

Utiliser `curl.exe` sous Windows PowerShell, ou `curl` sous Linux/macOS. Les identifiants numériques ci-dessous correspondent à une base initiale vierge.

```sh
curl -u 'manager@orbit.test:Orbit-demo-2026!' -H 'Accept: application/json' http://127.0.0.1:8000/api/projects
curl -u 'manager@orbit.test:Orbit-demo-2026!' -H 'Accept: application/json' http://127.0.0.1:8000/api/projects/1/tasks
curl -u 'alice@orbit.test:Orbit-demo-2026!' -H 'Accept: application/json' -H 'Content-Type: application/json' -X PATCH -d '{"status":"IN_PROGRESS"}' http://127.0.0.1:8000/api/tasks/1
curl -u 'alice@orbit.test:Orbit-demo-2026!' -H 'Accept: application/json' -H 'Content-Type: application/json' -X PATCH -d '{"status":"DONE"}' http://127.0.0.1:8000/api/tasks/2
```

La dernière requête doit renvoyer 403. Dans Windows PowerShell 5, si l'échappement JSON de curl pose problème, utiliser :

```powershell
$headers = @{ Authorization = 'Basic ' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes('alice@orbit.test:Orbit-demo-2026!')) }
Invoke-RestMethod -Method Patch -Uri http://127.0.0.1:8000/api/tasks/1 -Headers $headers -ContentType 'application/json' -Body '{"status":"IN_PROGRESS"}'
```

## 4. Vérification

```sh
php artisan test
php vendor/bin/pint --test
php artisan route:list --path=api
```

Les tests utilisent une base SQLite **en mémoire**, séparée de la démonstration. Ils couvrent les identifiants invalides, les trois routes sans authentification, la visibilité des projets, le filtrage des tâches, les modifications interdites sans effet en base, l'autorisation du responsable, les trois statuts, les entrées invalides, la réaffectation interdite, les 404 JSON, la limite de date du retard et l'idempotence du seeder.

Vérification effectuée sur cette livraison : **13 tests réussis, 59 assertions**, contrôle Pint réussi, migrations/seed exécutés. Les appels HTTP réels ont confirmé 401 sans identifiants, 200 pour la lecture du responsable et le PATCH de l'affecté, et 403 pour la modification d'une tâche d'autrui.

## 5. Diagnostic rapide : trois risques et corrections

**Limite du document reçu :** l'extrait de code de la section diagnostic est tronqué/illisible dans la transcription (`public function update(Request ...)`, puis fragments de `$task`). Il est impossible d'attribuer avec certitude des défauts à cet extrait. L'analyse suivante est donc une hypothèse explicite sur un contrôleur qui récupère une tâche et applique directement les données de la requête ; elle ne prétend pas reproduire le code original.

| Risque concret à vérifier | Correction |
| --- | --- |
| Accès direct par identifiant sans autorisation : Alice envoie l'ID d'une tâche de Bob et modifie son travail (IDOR). | Authentifier puis exécuter `Gate::authorize('update', $task)` avec une policy affecté/responsable. Tester un 403 et l'absence de modification en base. **Priorité 1**, car cela viole directement la confidentialité et l'intégrité attendues par le client. |
| Affectation de tout le corps, par exemple `$task->update($request->all())` : modification de `assigned_user_id` ou `project_id`. | Utiliser une liste de validation, rejeter les champs métier hors périmètre et écrire explicitement seulement `status`. `$fillable` ne remplace pas le contrôle d'autorisation. |
| Statut libre ou tâche absente non gérée : données incohérentes ou erreur 500 lors de l'accès à un résultat null. | Valider `required`, `string`, `Rule::in(...)`, utiliser le route model binding pour un 404 JSON, retourner 422 pour une valeur invalide et tester ces cas. |

Le flux corrigé est implémenté dans `TaskController::update` : résolution de la tâche → policy → validation → écriture ciblée → réponse JSON.

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

Le dépôt Git local contient le code et ce README. La publication distante nécessite un compte GitHub connecté et l'autorisation de créer un dépôt dans ce compte. Aucun lien GitHub n'est présenté comme livré tant que le push n'est pas confirmé.

Après création d'un dépôt GitHub vide, depuis ce dossier :

```sh
git remote add origin https://github.com/VOTRE-COMPTE/orbit-project-tracker.git
git push -u origin main
```

Références officielles consultées : [installation Laravel 12](https://laravel.com/docs/12.x/installation), [authentification HTTP Basic](https://laravel.com/docs/12.x/authentication#http-basic-authentication), [autorisation](https://laravel.com/docs/12.x/authorization).
