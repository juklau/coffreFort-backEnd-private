# Tests Unitaires — Coffre-Fort Numérique

## Périmètre

Ces tests couvrent **une partie ciblée** du backend : les 4 contrôleurs de l'API REST. Ils ne couvrent pas l'intégralité de l'application — les middlewares, repositories et services font l'objet d'une extension future (voir `docs/TESTS.md`).

```
tests/
├── BaseTestCase.php                    # Classe de base commune à tous les tests
└── unit/
    └── Controller/
        ├── UserControllerTest.php      # 16 tests — authentification, utilisateurs
        ├── FileControllerTest.php      # 10 tests — fichiers, dossiers, quota
        ├── ShareControllerTest.php     # 13 tests — partages publics/privés
        └── AdminControllerTest.php     # 11 tests — administration (quotas, suppression)
```

**Total : 50 tests**

## Pourquoi ces tests ?

Les contrôleurs sont la couche qui reçoit les requêtes HTTP, applique les règles métier (authentification, validation, droits d'accès) et retourne les réponses JSON. Les tester permet de valider le comportement de l'API sans serveur ni base de données réels.

Chaque route est testée sur **au minimum deux scénarios** : le cas nominal (succès) et les cas d'erreur prévisibles (données invalides, droits insuffisants, ressource inexistante).

Pour le détail complet de chaque test et scénario simulé, voir  [`docs/TESTS.md`](../docs/TESTS.md).

## Prérequis et installation

- PHP 8.1+, Composer

```bash
composer install
```

Installe **PHPUnit 13** (framework de test) et **Mockery 1.6** (simulation des objets).

## Configuration obligatoire

Créer `phpunit.xml` à la racine du projet avant tout lancement :

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit Tests">
            <directory>tests/unit</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="JWT_SECRET" value="votre_secret_jwt"/>
        <env name="SHARE_SECRET" value="votre_secret_partage"/>
        <env name="KEY_ENCRYPTION_KEY" value="0123456789abcdef0123456789abcdef"/>
        <env name="APP_PUBLIC_BASE_URL" value="http://localhost:9083"/>
    </php>
</phpunit>
```

> ⚠️ Sans ce fichier, les tests retourneront des erreurs 401 ou 500 car les secrets JWT et SHARE ne seront pas définis.

## Lancer les tests

```bash
# Tous les tests
./vendor/bin/phpunit

# Un contrôleur spécifique
./vendor/bin/phpunit tests/unit/Controller/UserControllerTest.php
./vendor/bin/phpunit tests/unit/Controller/FileControllerTest.php
./vendor/bin/phpunit tests/unit/Controller/ShareControllerTest.php
./vendor/bin/phpunit tests/unit/Controller/AdminControllerTest.php

# Un test par nom
./vendor/bin/phpunit --filter testLoginSuccess

# Rapport visuel détaillé (script inclus)
php test-report-detailed.php

# Couverture de code (HTML)
./vendor/bin/phpunit --coverage-html coverage/
```

## Résultats attendus

```
OK (50 tests, XX assertions)
```

Si des warnings Mockery apparaissent (`OK, but there were issues!`), les tests passent quand même — voir `docs/TESTS.md` pour les corriger.

## Routes testées

| Contrôleur | Routes couvertes | Tests |
|---|---|---|
| UserController | POST /auth/register, POST /auth/login, GET /users, GET /users/{id}, GET /dashboard | 16 |
| FileController | GET /files, GET /files/{id}, GET /folders, POST /folders, PUT /folders/{id}, DELETE /folders/{id}, GET /files/{id}/versions, GET /me/quota | 10 |
| ShareController | POST /shares, GET /shares, GET /shares/{id}, DELETE /shares/{id}, PATCH /shares/{id}/revoke, GET /s/{token}, GET /s/{token}/versions | 13 |
| AdminController | GET /admin/users/quotas, PUT /admin/users/{id}/quota, DELETE /admin/users/{id} | 11 |

## Trouver un test spécifique

Par fonctionnalité :
- Authentification et utilisateurs → `UserControllerTest.php`
- Fichiers, dossiers, quota → `FileControllerTest.php`
- Partages → `ShareControllerTest.php`
- Administration → `AdminControllerTest.php`

Par mot-clé dans le terminal :

```bash
grep -r "GET /files" tests/unit/Controller/FileControllerTest.php
grep -r "Invalid\|validation" tests/unit/
grep -r "jwt\|token\|auth" tests/unit/
```

## Ajouter un nouveau test

1. Ouvrir le fichier de test du contrôleur concerné
2. Copier un test existant de même nature (GET, POST, etc.)
3. Renommer la méthode en `testXxx` (le préfixe `test` est obligatoire)
4. Adapter les mocks et les assertions
5. Exécuter : `./vendor/bin/phpunit --filter testXxx`

Pour aller plus loin (tester les repositories, services, ou faire des tests d'intégration), consulter `docs/TESTS.md`.

## Points critiques

**`autoload-dev` dans `composer.json`** — sans ça, `Tests\BaseTestCase` est introuvable :

```json
"autoload-dev": {
    "psr-4": { "Tests\\": "tests/" }
}
```

Puis : `composer dump-autoload`

**Fermeture de Mockery dans `tearDown()`** — obligatoire dans chaque classe de test :

```php
protected function tearDown(): void
{
    parent::tearDown();
    m::close();
}
```

## Documentation complète

Pour la méthodologie détaillée, les exemples de mocks, les problèmes rencontrés et la roadmap d'extension des tests : voir [`docs/TESTS.md`](../docs/TESTS.md).

## Ressources

- [PHPUnit Docs](https://docs.phpunit.de/)
- [Mockery Docs](https://docs.mockery.io/)
- [Slim Framework](https://www.slimframework.com/)
- [PSR-7 HTTP Message](https://www.php-fig.org/psr/psr-7/)
