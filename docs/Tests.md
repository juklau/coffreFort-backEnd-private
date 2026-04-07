# Documentation technique des tests — Coffre-Fort Numérique

## Contexte et périmètre

Les tests unitaires réalisés couvrent **une partie ciblée** du backend, et non l'intégralité de l'application. Ce choix est délibéré et méthodologique : il s'agit de valider les comportements critiques de chaque contrôleur de manière isolée, sans dépendance à une base de données réelle ni à un serveur HTTP actif.

### Ce qui est testé

Les 4 contrôleurs principaux de l'API REST :

| Contrôleur | Fichier de test | Tests |
|---|---|---|
| UserController | UserControllerTest.php | 16 |
| FileController | FileControllerTest.php | 10 |
| ShareController | ShareControllerTest.php | 13 |
| AdminController | AdminControllerTest.php | 11 |
| **Total** | | **50** |

### Ce qui n'est pas testé (et pourquoi)

| Élément | Raison de l'exclusion |
|---|---|
| Middlewares Slim | Testés au niveau intégration, pas unitaire |
| Repositories / modèles | Mockés — leurs interactions DB sont simulées |
| Routes (routing Slim) | Testées via des tests d'intégration ou manuellement |
| Upload de fichiers réels | Nécessite un système de fichiers, hors périmètre unitaire |
| Chiffrement des fichiers | Testé séparément au niveau de la classe `EncryptionService` |

---

## Méthodologie

### Approche : tests unitaires par couche contrôleur

Les tests ciblent la **couche contrôleur** car c'est elle qui :
- reçoit les requêtes HTTP
- orchestre la logique métier (validation, authentification, accès aux données)
- retourne les codes HTTP et corps de réponse JSON attendus par le frontend

Tester les contrôleurs permet de valider l'ensemble de la logique de traitement d'une requête sans avoir besoin d'une vraie base de données.

### Isolation via Mockery

La base de données (`Medoo\Medoo`) est **mockée** dans chaque test. Cela signifie qu'on remplace l'objet réel par un faux objet qui simule les réponses attendues. Avantages :

- les tests sont rapides (pas de connexion DB)
- les tests sont reproductibles (pas de données variables)
- on peut tester des cas difficiles à reproduire (utilisateur inexistant, quota dépassé, etc.)

```php
// Exemple : simuler un utilisateur trouvé en base
$this->database->shouldReceive('get')
    ->with('users', \Mockery::any(), ['email' => 'admin@example.com'])
    ->andReturn(['id' => 1, 'email' => 'admin@example.com', 'is_admin' => 1])
    ->once();
```

### Stratégie de couverture : cas nominaux + cas d'erreur

Pour chaque route testée, on couvre systématiquement :

1. **Le cas nominal** — la requête valide retourne le code et les données attendus
2. **Les cas d'erreur prévisibles** — données manquantes, format invalide, droits insuffisants, ressource inexistante

Le tableau ci-dessous détaille pour chaque test : le scénario exact simulé et le code HTTP attendu.

---

## Tests par contrôleur

### UserController — 16 tests

**POST /auth/register**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testRegisterSuccess` | Email valide, mot de passe fort — premier utilisateur créé (devient admin automatiquement) | 201 |
| `testRegisterInvalidEmail` | Email malformé (`invalid-email`) | 400 |
| `testRegisterShortPassword` | Mot de passe trop court (`short`) | 400 |
| `testRegisterEmailExists` | Email déjà présent en base — mock retourne un utilisateur existant | 409 |

**POST /auth/login**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testLoginSuccess` | Email et mot de passe corrects — mock retourne l'utilisateur avec le bon hash | 200 |
| `testLoginInvalidEmail` | Email malformé, rejeté avant toute requête DB | 400 |
| `testLoginShortPassword` |  Mot de passe trop court (`short`) | 400 |
| `testLoginUserNotFound` | Email valide mais mock retourne `null` (utilisateur inexistant) | 401 |
| `testLoginWrongPassword` | Mock retourne un hash différent du mot de passe fourni | 401 |

**GET /users**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testListUsersAsAdmin` | Token JWT admin valide — mock retourne la liste de tous les utilisateurs | 200 |
| `testListUsersAsNonAdmin` | Token JWT utilisateur standard (`is_admin = 0`) | 403 |

**GET /users/{id}**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testShowUserAsAdmin` | Token admin, mock retourne l'utilisateur demandé (id=2) | 200 |
| `testShowUserNotFound` | Token admin, mock retourne `null` pour l'id=999 | 404 |

**GET /dashboard**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testDashboardSuccess` | JWT valide passé en query param (`?jwt=...`) | 200 |
| `testDashboardNoToken` | Requête sans token ni header Authorization | 401 |
| `testDashboardInvalidToken` | Header Authorization avec un token forgé invalide | 403 |

---

### FileController — 10 tests

**GET /files**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testListFilesSuccess` | Token valide — mock retourne la liste des fichiers de l'utilisateur | 200 |
| `testListFilesUnauthorized` | Requête sans token d'authentification | 401 |

**GET /files/{id}**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testShowFileSuccess` | Token valide, mock retourne le fichier avec ses versions | 200 |

**GET /folders**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testListFoldersSuccess` | Token valide — mock retourne la liste des dossiers de l'utilisateur | 200 |

**POST /folders**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testCreateFolderSuccess` | Token valide, nom fourni — mock simule un INSERT réussi | 201 |
| `testCreateFolderNoName` | Token valide, champ `name` vide (`""`) — rejeté par validation | 400 |

**PUT /folders/{id}**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testRenameFolderSuccess` | Token valide, nouveau nom fourni — mock simule un UPDATE réussi | 200 |

**DELETE /folders/{id}**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testDeleteFolderSuccess` | Token valide — mock simule une suppression réussie | 200 |

**GET /files/{id}/versions**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testListVersionsSuccess` | Token valide — mock retourne la liste des versions du fichier | 200 |

**GET /me/quota**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testGetUserQuotaSuccess` | Token valide — mock retourne quota total et espace utilisé | 200 |

---

### ShareController — 13 tests

**POST /shares**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testCreateShareSuccess` | Token valide, `kind=file`, fichier trouvé et appartenant à l'utilisateur — mock simule INSERT + UPDATE signature | 201 |
| `testCreateShareInvalidKind` | Champ `kind` avec une valeur non acceptée (ni `file` ni `folder`) | 400 |
| `testCreateShareInvalidTargetId` | `target_id` à 0 ou négatif | 400 |

**GET /shares**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testListSharesSuccess` | Token valide — mock retourne la liste des partages de l'utilisateur | 200 |

**GET /shares/{id}**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testShowShareSuccess` | Token valide, mock retourne le partage demandé | 200 |
| `testShowShareInvalidId` | ID non numérique ou ≤ 0 | 400 |

**DELETE /shares/{id}**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testDeleteShareSuccess` | Token valide, partage appartenant à l'utilisateur — mock simule DELETE réussi | 200 |

**PATCH /shares/{id}/revoke**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testRevokeShareSuccess` | Token valide — mock simule la révocation (is_revoked = 1) | 200 |

**GET /s/{token} (route publique)**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testPublicShareInfoSuccess` | Token valide en base, partage actif non révoqué | 200 |
| `testPublicShareTokenNotFound` | Token valide syntaxiquement mais absent en base — mock retourne `null` | 404 |
| `testPublicShareEmptyToken` | Token vide dans l'URL | 400 |

**GET /s/{token}/versions (route publique)**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testPublicShareVersionsSuccess` | Token valide, `allow_fixed_versions = 1` — mock retourne les versions | 200 |
| `testPublicShareVersionsNotAllowed` | Token valide mais `allow_fixed_versions = 0` — accès refusé | 403 |

---

### AdminController — 11 tests

**GET /admin/users/quotas**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testListUsersWithQuotaAsAdmin` | Token admin valide — mock retourne la liste des utilisateurs avec leurs quotas | 200 |
| `testListUsersWithQuotaAsNonAdmin` | Token utilisateur standard (`is_admin = 0`) | 403 |
| `testListUsersWithoutAuthentication` | Requête sans token d'authentification | 401 |

**PUT /admin/users/{id}/quota**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testUpdateUserQuotaAsAdmin` | Token admin, nouveau quota valide supérieur à l'espace utilisé | 200 |
| `testUpdateUserQuotaBelowUsedSpace` | Nouveau quota inférieur à l'espace déjà occupé par l'utilisateur | 400 |
| `testUpdateUserQuotaAsNonAdmin` | Token utilisateur standard — accès refusé | 403 |
| `testUpdateUserQuotaInvalidId` | ID utilisateur ≤ 0 | 400 |

**DELETE /admin/users/{id}**

| Test | Scénario simulé | Code attendu |
|---|---|---|
| `testDeleteUserAsAdmin` | Token admin, suppression d'un autre utilisateur | 200 |
| `testDeleteOwnUserAccount` | Admin tente de supprimer son propre compte — protection métier | 400 |
| `testDeleteUserAsNonAdmin` | Token utilisateur standard — accès refusé | 403 |
| `testDeleteNonexistentUser` | ID valide mais mock retourne `null` — utilisateur introuvable | 404 |

---

## Utilitaires BaseTestCase

Tous les fichiers de test héritent de `Tests\BaseTestCase` qui fournit des méthodes utilitaires pour créer des requêtes PSR-7 sans serveur HTTP réel.

### Créer des requêtes

```php
// GET simple
$request = $this->createGetRequest('/files');

// GET avec query params
$request = $this->createGetRequest('/dashboard', ['jwt' => $token]);

// POST avec corps JSON
$request = $this->createPostRequest('/auth/login', [
    'email'    => 'test@example.com',
    'password' => 'password123'
]);

// PUT, DELETE, PATCH
$request = $this->createPutRequest('/folders/1', ['name' => 'Nouveau nom']);
$request = $this->createDeleteRequest('/files/1');
$request = $this->createPatchRequest('/shares/100/revoke', []);
```

### Ajouter un token JWT

```php
$token = $this->createAdminJwt();   // is_admin = 1
$token = $this->createUserJwt();    // is_admin = 0

$request = $request->withHeader('Authorization', 'Bearer ' . $token);
// ou
$request = $this->createRequestWithToken($request, $token);
```

### Lire la réponse

```php
$response = $this->createResponse();
$result   = $this->controller->methode($request, $response);

$this->assertEquals(200, $result->getStatusCode());
$data = $this->getResponseData($result); // tableau PHP décodé depuis le JSON
```

---

## Mocking avec Mockery

```php
protected function setUp(): void
{
    parent::setUp();
    $this->database = m::mock('Medoo\Medoo');
}

protected function tearDown(): void
{
    parent::tearDown();
    m::close(); // libère les mocks — obligatoire pour éviter les warnings
}
```

### Exemples de mocks courants

```php
// Simuler un utilisateur trouvé
$this->database->shouldReceive('get')
    ->with('users', \Mockery::any(), ['email' => 'test@example.com'])
    ->andReturn(['id' => 1, 'email' => 'test@example.com', 'is_admin' => 0])
    ->once();

// Simuler un INSERT
$this->database->shouldReceive('insert')->andReturn(null);
$this->database->shouldReceive('id')->andReturn(1);

// Simuler un SELECT multiple
$this->database->shouldReceive('select')
    ->andReturn([
        ['id' => 1, 'email' => 'admin@example.com'],
        ['id' => 2, 'email' => 'user@example.com'],
    ]);

// Simuler un UPDATE
$this->database->shouldReceive('update')->andReturn(null);
```

---

## Généraliser les tests à l'ensemble du backend

Les 50 tests actuels couvrent les contrôleurs. Pour étendre la couverture à l'ensemble du backend, voici les étapes à suivre :

### 1. Tester les Repositories

Créer `tests/unit/Repository/` avec des tests pour chaque repository (`UserRepository`, `FileRepository`, `ShareRepository`). Ces tests vérifient que les requêtes SQL construites par Medoo sont correctes.

```
tests/unit/
├── Controller/     ← déjà fait
└── Repository/     ← à créer
    ├── UserRepositoryTest.php
    ├── FileRepositoryTest.php
    └── ShareRepositoryTest.php
```

### 2. Tester les Services

Les services métier (`AuthService`, `EncryptionService`, `ShareToken`) peuvent être testés unitairement sans mock de base de données.

```
tests/unit/
└── Service/        ← à créer
    ├── AuthServiceTest.php
    └── EncryptionServiceTest.php
```

### 3. Ajouter des tests d'intégration

Les tests d'intégration testent plusieurs couches ensemble (contrôleur + service + repository) avec une vraie base de données SQLite en mémoire :

```
tests/
└── integration/    ← à créer
    └── UserFlowTest.php   # register → login → dashboard
```

### 4. Augmenter la couverture des cas existants

Chaque contrôleur a encore des cas non testés. Exemples à ajouter :

| Contrôleur | Cas manquants |
|---|---|
| FileController | Fichier non trouvé (404), accès fichier d'un autre utilisateur (403) |
| ShareController | Partage expiré, max_uses atteint |
| AdminController | Quota à 0 (illimité), mise à jour d'un admin |

### 5. Mesurer la couverture de code

```bash
./vendor/bin/phpunit --coverage-html coverage/
# Ouvrir coverage/index.html dans un navigateur
```

PHPUnit génère un rapport HTML indiquant quelles lignes du code source sont couvertes par les tests. L'objectif recommandé est **80 % minimum** sur les contrôleurs.

---

## Problèmes rencontrés et solutions

### `Tests\BaseTestCase` not found

`BaseTestCase.php` doit être dans `tests/` (pas `tests/unit/`) et `composer.json` doit contenir :

```json
"autoload-dev": {
    "psr-4": { "Tests\\": "tests/" }
}
```

Puis : `composer dump-autoload`

### Tests retournent 401 au lieu de 200

`JWT_SECRET` absent dans `phpunit.xml`. Le contrôleur ne peut pas décoder le token car son secret est vide.

```xml
<env name="JWT_SECRET" value="votre_secret_jwt"/>
```

### Tests ShareController retournent 500

`SHARE_SECRET` absent. Ajouter dans `phpunit.xml` :

```xml
<env name="SHARE_SECRET" value="votre_secret_partage"/>
```

### Faute de frappe dans un message d'erreur

`assertStringContainsString` est sensible à l'orthographe exacte. Exemple rencontré : `infèrieure` (accent grave) au lieu de `inférieure` (accent aigu) dans `AdminController` — le test échouait car la chaîne ne correspondait pas.

### Nom de dossier vide accepté

`isset($body['name'])` retourne `true` pour une chaîne vide. Corriger avec :

```php
if (!isset($body['name']) || trim($body['name']) === '') {
    return $this->json($response, ['error' => 'Nom requis'], 400);
}
```

### Warnings Mockery

`OK, but there were issues!` signifie que des mocks ont été définis mais non appelés. Ne fait pas échouer les tests. Se corrige en ajustant les contraintes : `once()` si appelé exactement une fois, `zeroOrMoreTimes()` si optionnel.

---

## CI/CD

Le workflow GitHub Actions est configuré dans `.github/workflows/tests.yml`.
Il se déclenche sur chaque push et pull request vers `main` et `develop`,
et peut aussi être lancé manuellement depuis l'interface GitHub.

```yaml
name: Tests

on: # déclenchement du workflow
    push:
        branches:
        - main
        - develop
    pull_request:
        branches:
        - main
        - develop
    workflow_dispatch:  # démarrage manuel depuis GitHub en ajoutant un bouton 

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/cache@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install --no-interaction --prefer-dist --optimize-autoloader
      - run: ./vendor/bin/phpunit --no-coverage # pour aller plus vite
    env:
      JWT_SECRET: ${{ secrets.JWT_SECRET }}
      SHARE_SECRET: ${{ secrets.SHARE_SECRET }}
      KEY_ENCRYPTION_KEY: ${{ secrets.KEY_ENCRYPTION_KEY }}
      APP_PUBLIC_BASE_URL: ${{ secrets.APP_PUBLIC_BASE_URL }}
```

Les secrets nécessaires (`JWT_SECRET`, `SHARE_SECRET`, `KEY_ENCRYPTION_KEY`, 
`APP_PUBLIC_BASE_URL`) sont à définir dans **Settings → Secrets → Actions**.

---

## Ressources

- [PHPUnit Documentation](https://docs.phpunit.de/)
- [Mockery Documentation](https://docs.mockery.io/)
- [Slim Framework](https://www.slimframework.com/)
- [PSR-7 HTTP Message](https://www.php-fig.org/psr/psr-7/)
