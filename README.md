# Orbit — API de suivi de projets

Projet réalisé dans le cadre d’un test technique pour un stage professionnel. Il utilise **Laravel 12**, **PHP 8.4** et **SQLite**. Le développement a été effectué sous Windows avec **Wamp64**. L’application renvoie du JSON et ne possède pas d’interface graphique.

## 1. Compréhension du besoin

Orbit souhaite centraliser le suivi de ses projets et des tâches confiées aux collaborateurs.
Chaque collaborateur doit pouvoir consulter ses tâches et mettre à jour leur avancement.
Le responsable doit avoir une vue sur ses projets et repérer rapidement les tâches en retard.
La priorité est de proposer un outil simple tout en empêchant les modifications sans autorisation.

### Trois questions au client

| Question | Impact sur la solution |
| --- | --- |
| Un collaborateur peut-il voir les tâches de ses collègues ? Le responsable peut-il les modifier ? | Détermine les règles d’accès. Choix retenu : chacun voit et modifie ses propres tâches ; le responsable peut consulter et changer les statuts de toutes les tâches de ses projets. |
| À quel moment une tâche est-elle considérée en retard, et dans quel fuseau horaire ? | Détermine le calcul du retard. Choix retenu : après la fin de la date d’échéance, dans le fuseau `Africa/Douala`, si le statut est différent de `DONE`. |
| Faut-il envoyer des notifications lorsque les échéances approchent ou sont dépassées ? | Nécessiterait un système de notifications et une exécution planifiée. Cette fonctionnalité est reportée pour garder une première version simple. |

### MVP : trois fonctionnalités essentielles

1. **Consulter les projets et les tâches accessibles**, pour que chacun retrouve son travail.
2. **Mettre à jour les statuts et identifier les retards**, pour suivre l’avancement.
3. **Authentifier les utilisateurs et contrôler leurs droits**, pour protéger les données.

**Fonctionnalité à reporter :** les notifications par e-mail. Le responsable peut déjà repérer les retards grâce au champ `is_overdue`.

**Critère d’acceptation :** si Alice tente de modifier une tâche attribuée à Bob sans autorisation, l’API retourne `403 Forbidden` et le statut en base reste inchangé. Ce cas est couvert par un test automatisé.

## 2. Installation avec Wamp64

Prérequis : Git, Composer 2 et PHP 8.4 avec les extensions SQLite/PDO SQLite, mbstring, OpenSSL, fileinfo, cURL et XML/DOM. Adapter le chemin de PHP si la version installée dans Wamp est différente.

Depuis le dossier du projet, ouvrir PowerShell :

```powershell
cd C:\wamp64\www\orbit
$env:PATH = 'C:\wamp64\bin\php\php8.4.15;' + $env:PATH

# Configuration PHP locale, si elle est présente sur la machine
if (Test-Path .tools/php.ini) {
    $env:PHPRC = (Resolve-Path .tools/php.ini).Path
}

# Utiliser Composer installé, ou la copie locale disponible
if (Test-Path .tools/composer.phar) {
    php .tools/composer.phar install
} else {
    composer install
}

php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan serve --no-reload
```

L’API est accessible sur **http://127.0.0.1:8000**. Garder le terminal ouvert et utiliser **Ctrl + C** pour arrêter le serveur. L’option `--no-reload` évite le problème de lancement rencontré sous Windows ; redémarrer le serveur après une modification du `.env`.

Wamp fournit ici PHP. La base utilisée est SQLite : MySQL et Apache ne sont pas nécessaires pour ce lancement avec Artisan. Vérifier que le `.env` contient `DB_CONNECTION=sqlite`.

Le dossier `.tools` contient des outils propres à la machine de développement et n’est pas versionné. Après un clonage Git, utiliser PHP et Composer installés sur la machine. Adapter également le chemin `orbit` au nom du dossier cloné. Le `.env`, la base SQLite et `vendor` sont exclus de Git.

## 3. Comptes de démonstration

Le seeder crée **4 utilisateurs, 1 projet et 3 tâches**. Les mots de passe sont hachés en base.

| Identifiant | Accès |
| --- | --- |
| `manager@orbit.test` | Toutes les tâches de son projet |
| `alice@orbit.test` | Ses deux tâches |
| `bob@orbit.test` | Sa tâche |
| `outsider@orbit.test` | Aucun projet |

**Mot de passe commun :** `Orbit-demo-2026!`

L’authentification utilise **HTTP Basic** : transmettre l’email et le mot de passe à chaque requête, par exemple avec l’onglet *Authorization → Basic Auth* de Postman. Ce choix convient à la démonstration ; une utilisation réelle demanderait HTTPS et le remplacement des comptes de test.

Le seeder peut être relancé sans créer de doublons. Les échéances sont calculées lors de la première création des tâches.

## 4. Endpoints et essais

| Méthode | URL | Description |
| --- | --- | --- |
| GET | `/api/projects` | Projets accessibles à l’utilisateur connecté |
| GET | `/api/projects/{project}/tasks` | Tâches autorisées du projet |
| PATCH | `/api/tasks/{task}/status` | Modification du statut d’une tâche autorisée |

Statuts acceptés : `TODO`, `IN_PROGRESS`, `DONE`. Le PATCH modifie uniquement `status`. Les listes sont paginées par 20 éléments. Le champ `is_overdue` indique un retard ; une tâche terminée ou dont l’échéance est aujourd’hui n’est pas en retard.

### Consulter les données

Dans un deuxième terminal PowerShell :

```powershell
curl.exe -u 'manager@orbit.test:Orbit-demo-2026!' http://127.0.0.1:8000/api/projects
curl.exe -u 'manager@orbit.test:Orbit-demo-2026!' http://127.0.0.1:8000/api/projects/1/tasks
```

### Modifier un statut

```powershell
$headers = @{
    Authorization = 'Basic ' + [Convert]::ToBase64String(
        [Text.Encoding]::UTF8.GetBytes('alice@orbit.test:Orbit-demo-2026!')
    )
}

Invoke-RestMethod -Method Patch `
    -Uri http://127.0.0.1:8000/api/tasks/1/status `
    -Headers $headers `
    -ContentType 'application/json' `
    -Body '{"status":"IN_PROGRESS"}'
```

Sur la base de démonstration initiale, la tâche `1` appartient à Alice : la requête réussit. Remplacer `1` par `2` pour viser la tâche de Bob : la réponse attendue est **403**.

Principales réponses : **200** succès, **401** identifiants absents ou invalides, **403** accès interdit, **404** ressource inexistante, **422** données invalides et **429** limite de requêtes atteinte.

## 5. Tests

Depuis le dossier du projet, dans un terminal où PHP est configuré comme ci-dessus :

```powershell
php artisan test
php vendor/bin/pint --test
```

Dernière vérification : **13 tests réussis, 59 assertions**, et contrôle de style validé. Les tests couvrent les droits d’accès, les statuts, les données invalides, les tâches inexistantes et le calcul des retards. Ils utilisent une base SQLite en mémoire et ne modifient pas les données de démonstration.

## 6. Diagnostic du code fourni

```php
public function update(Request $request, $id) {
    $task = Task::find($id);
    $task->update($request->all());
    return response()->json($task);
}
```

| Risque | Correction |
| --- | --- |
| Aucun contrôle visible ne vérifie le droit de modifier la tâche. Un utilisateur peut viser celle d’un autre. | Authentifier la requête et vérifier une Policy avec `Gate::authorize('update', $task)`. |
| `$request->all()` transmet des champs non validés, dont potentiellement l’affectation ou le projet selon les champs autorisés du modèle. | Valider le statut parmi les trois valeurs acceptées et enregistrer uniquement ce champ. |
| `Task::find($id)` peut retourner `null`, ce qui provoque une erreur lors de `update()`. | Utiliser `findOrFail()` ou le route model binding pour retourner un 404. |

**À corriger en premier : le contrôle des droits**, car il protège directement le travail des collaborateurs. Ces corrections sont appliquées dans le contrôleur et les Policies du projet.

## 7. Choix et limites

Les modèles `Project`, `Task` et `User` sont reliés avec Eloquent. Les colonnes `name`, `title`, `status` et `due_date` correspondent au nom, au titre, au statut et à l’échéance demandés.

Cette version livre les trois endpoints, l’authentification, les autorisations et le suivi des retards. Les projets, les tâches et les affectations sont préchargés par le seeder : leur création par API reste à développer. Les notifications par e-mail et l’historique des modifications ne sont pas implémentés. Aucune interface graphique n’est prévue pour ce test.

### Décision principale et suite

J’ai privilégié un parcours simple et fonctionnel, avec des droits vérifiés côté serveur.
SQLite et HTTP Basic permettent de tester rapidement la solution dans mon environnement Wamp.
Les Policies limitent les accès aux tâches attribuées et aux projets supervisés.
La prochaine amélioration métier serait de permettre au responsable de créer les projets et d’attribuer les tâches par API, avec les mêmes contrôles d’accès.

## Dépôt GitHub

[Code source — test-laravel](https://github.com/NjikepFranck/test-laravel)
