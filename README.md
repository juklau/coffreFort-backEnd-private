# Coffre-fort Numérique - Backend API

> Projet de réalisation professionnelle - Backend REST sécurisé pour un coffre-fort numérique avec chiffrement au repos, gestion de versions et partages contrôlés.

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![Slim Framework](https://img.shields.io/badge/Slim-4.12-green)](https://www.slimframework.com/)
[![Medoo](https://img.shields.io/badge/Medoo-2.2-orange)](https://medoo.in/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

---

## Table des matières

- [Vue d'ensemble](#vue-densemble)
- [Fonctionnalités](#fonctionnalités)
- [Architecture technique](#architecture-technique)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
- [API Documentation](#api-documentation)
- [Sécurité](#sécurité)
- [Tests](#tests)
- [Déploiement](#déploiement)
- [Contribution](#contribution)
- [Problèmes connus et solutions](#problèmes-connus-et-solutions)

---

## Vue d'ensemble

Le backend du coffre-fort numérique est une API REST construite avec **Slim Framework 4.12** et **Medoo 2.2**, offrant un système de stockage sécurisé de fichiers avec :

- **Chiffrement au repos** (AES-256-GCM)
- **Authentification JWT** (Firebase PHP-JWT)
- **Organisation hiérarchique** (dossiers/fichiers avec CASCADE)
- **Versionnage** automatique des fichiers
- **Partages contrôlés** avec tokens sécurisés
- **Gestion des quotas** utilisateur
- **Journalisation** complète des accès et téléchargements
- **Rôle administrateur** (suppression utilisateurs, gestion quota)
- **Audit complet** via table `audit_logs`

### Contexte pédagogique

Ce projet prolonge le TP « Mini coffre-fort REST (MVC-API-REST) » en ajoutant :
- Authentification multi-utilisateurs avec JWT
- Chiffrement AES-256-GCM avec enveloppe de clés
- Système de partage avec tokens signés
- Versionnage et traçabilité complète
- Gestion avancée des droits (admin/user)
- Protection contre les suppressions critiques

---

## Fonctionnalités

### Gestion des utilisateurs
- Inscription avec validation email
- Authentification JWT via Firebase PHP-JWT
- Hachage sécurisé des mots de passe (password_hash)
- Gestion des quotas d'espace individuels (quota_total, quota_used)
- Rôle administrateur (is_admin)
- Suppression d'utilisateurs par admin avec CASCADE automatique

### Stockage sécurisé
- Upload de fichiers avec chiffrement AES-256-GCM
- Organisation en dossiers hiérarchiques (parent_id)
- Versionnage automatique lors du remplacement
- Interdiction de supprimer la dernière version
- Métadonnées complètes : original_name, stored_name, MIME, taille, checksum SHA-256
- Suppression CASCADE : user → folders/files → versions/logs

### Partages et diffusion
- Création de liens de partage sécurisés (token base64url)
- Support fichiers ET dossiers (kind: 'file'|'folder')
- Contrôle d'expiration (expires_at)
- Limitation du nombre d'utilisations (max_uses, remaining_uses)
- Révocation immédiate (is_revoked)
- Journalisation détaillée (IP, user-agent, succès/échec, version téléchargée)

### Administration
- Suppression d'utilisateurs avec nettoyage automatique :
  - Fichiers physiques sur le disque
  - Dossiers et sous-dossiers (CASCADE)
  - Fichiers et versions (CASCADE)
  - Partages et logs de téléchargements (CASCADE)
- Protection contre l'auto-suppression admin
- Gestion des quotas utilisateurs

### Audit et traçabilité
- Table `audit_logs` pour traçabilité complète
- Journalisation des actions utilisateurs (login, upload, delete, etc.)
- Stockage IP, user-agent, détails de l'action
- Index optimisés pour recherche rapide par user, action, date

---

## Architecture technique

### Stack technologique

```
┌─────────────────────────────────────┐
│         Clients                     │
│  (JavaFX Desktop + Web Public)      │
└──────────────┬──────────────────────┘
               │ HTTPS/REST (38 routes)
               ▼
┌─────────────────────────────────────┐
│     API Backend (Slim 4.12)         │
│  ┌─────────────────────────────┐    │
│  │  Middlewares                │    │
│  │  - CORS (origins)           │    │
│  │  - JWT Auth (Firebase)      │    │
│  │  - Error Handler (JSON)     │    │
│  │  - Rate Limiter (TODO v2)   │    │
│  └─────────────────────────────┘    │
│  ┌─────────────────────────────┐    │
│  │  Routes & Controllers       │    │
│  │  - Auth (register, login)   │    │
│  │  - Folders (CRUD)           │    │
│  │  - Files (upload, versions) │    │
│  │  - Shares (create, revoke)  │    │
│  │  - Admin (delete users)     │    │
│  │  - Public (/s/{token})      │    │
│  └─────────────────────────────┘    │
│  ┌─────────────────────────────┐    │
│  │  Repositories (Medoo 2.2)   │    │
│  │  - UserRepository           │    │
│  │  - FolderRepository         │    │
│  │  - FileRepository           │    │
│  │  - ShareRepository          │    │
│  │  - DownloadLogRepository    │    │
│  │  - AuditLogRepository       │    │
│  └─────────────────────────────┘    │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│    Medoo ORM (Micro)                │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│   MySQL Database                    │
│   Tables:                           │
│   - users (with is_admin)           │
│   - folders (hierarchical)          │
│   - files (with stored_name)        │
│   - file_versions (AES-256-GCM)     │
│   - shares (token base64url)        │
│   - downloads_log                   │
│   - audit_logs                      │
│   + Triggers & CASCADE constraints  │
└─────────────────────────────────────┘

           +

┌─────────────────────────────────────┐
│   File Storage (chiffré)            │
│   storage/files/{user_id}/...       │
└─────────────────────────────────────┘
```

### Technologies utilisées

- **PHP 8.2+** : langage backend
- **Slim Framework 4.12** : micro-framework REST (AppFactory, PSR-7, PSR-15)
- **Medoo 2.2** : micro-ORM léger pour accès MySQL
- **Firebase PHP-JWT 6.11** : gestion des tokens d'authentification
- **OpenSSL** : chiffrement AES-256-GCM
- **MySQL 8.0** : base de données relationnelle
- **Docker & Docker Compose** : containerisation

### Modèle de données (implémenté)

```sql
-- Utilisateurs (avec rôle admin)
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    pass_hash VARCHAR(255) NOT NULL,
    quota_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
    quota_used BIGINT UNSIGNED NOT NULL DEFAULT 0,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Dossiers hiérarchiques (CASCADE sur parent et user)
CREATE TABLE folders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_folders_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_folders_parent FOREIGN KEY (parent_id)
        REFERENCES folders(id) ON DELETE CASCADE
);

-- Fichiers (avec nom stockage séparé)
CREATE TABLE files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    folder_id INT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(150) NOT NULL,  -- UUID
    mime VARCHAR(255) NOT NULL,
    size BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_files_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_files_folder FOREIGN KEY (folder_id)
        REFERENCES folders(id) ON DELETE SET NULL
);

-- Versions de fichiers (chiffrement AES-256-GCM)
CREATE TABLE file_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    iv VARBINARY(12) NOT NULL,              -- Initialization Vector
    auth_tag VARBINARY(16) NOT NULL,        -- Authentication Tag
    key_envelope BLOB NOT NULL,             -- Clé chiffrée avec KEK
    size BIGINT UNSIGNED NOT NULL,
    checksum CHAR(64) NOT NULL,             -- SHA-256 du ciphertext
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_file_versions_file FOREIGN KEY (file_id)
        REFERENCES files(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_file_version (file_id, version)
);

-- Partages sécurisés (token base64url)
CREATE TABLE shares (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    kind ENUM('file', 'folder') NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    token CHAR(64) NOT NULL UNIQUE,
    label VARCHAR(255) NULL,
    expires_at DATETIME NULL,
    max_uses INT UNSIGNED NULL,
    remaining_uses INT UNSIGNED NULL,
    is_revoked TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    allow_fixed_versions TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_shares_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
);

-- Logs des téléchargements
CREATE TABLE downloads_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    share_id INT UNSIGNED NOT NULL,
    version_id INT UNSIGNED NULL,
    downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    success TINYINT(1) NOT NULL,
    message VARCHAR(255) NULL,
    CONSTRAINT fk_downloads_share FOREIGN KEY (share_id)
        REFERENCES shares(id) ON DELETE CASCADE,
    CONSTRAINT fk_downloads_version FOREIGN KEY (version_id)
        REFERENCES file_versions(id) ON DELETE SET NULL
);

-- Audit complet des actions utilisateurs
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action ENUM(
        'USER_LOGIN', 'USER_REGISTER', 'USER_LOGOUT',
        'FOLDER_CREATE', 'FOLDER_RENAME', 'FOLDER_DELETE',
        'FILE_UPLOAD', 'FILE_RENAME', 'FILE_DELETE',
        'FILE_VERSION_UPLOAD', 'FILE_VERSION_DELETE',
        'SHARE_CREATE', 'SHARE_REVOKE', 'SHARE_DELETE',
        'FILE_DOWNLOAD', 'FILE_VERSION_DOWNLOAD', 'SHARE_DOWNLOAD',
        'QUOTA_UPDATE', 'USER_DELETE',
        'OTHER'
    ) NOT NULL,
    table_name VARCHAR(50) NULL,
    record_id BIGINT UNSIGNED NULL,
    details TEXT NULL,
    ip_address VARCHAR(50) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_table_record (table_name, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Points clés CASCADE** :
- Supprimer un user → supprime automatiquement folders, files, shares
- Supprimer un folder → supprime les sous-dossiers (parent_id)
- Supprimer un file → supprime toutes ses versions
- Supprimer un share → supprime les logs de téléchargements associés

---

## Prérequis

### Environnement de développement

- **PHP** ≥ 8.2
  - Extensions requises : `pdo`, `pdo_mysql`, `json`, `mbstring`, `openssl`, `zip`
- **Composer** (gestionnaire de dépendances PHP)
- **MySQL** ≥ 8.0 ou **MariaDB** ≥ 10.5
- **Git**

### Optionnel mais recommandé

- **Docker** et **Docker Compose** (pour déploiement rapide)
- **Node.js** et **Newman** (pour tests E2E Postman)

---

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/PlumCreativ/coffreFort.git
cd coffreFort
```

### 2. Installer les dépendances

```bash
composer install
```

**Dépendances principales** (automatiquement installées) :
```json
{
  "require": {
    "slim/slim": "^4.12",
    "slim/psr7": "^1.8",
    "catfan/medoo": "^2.2",
    "firebase/php-jwt": "^6.11"
  }
}
```

> **Note** : Si problème avec Medoo, utiliser :
> ```bash
> rm -rf vendor composer.lock
> composer require slim/slim:"4.12" slim/psr7:"1.8" catfan/medoo:"2.2"
> composer update
> ```

### 3. Configurer l'environnement

```bash
cp .env.example .env
```

Éditer `.env` avec vos paramètres (voir section [Configuration](#configuration))

### 4. Créer la base de données

```bash
# MySQL
mysql -u root -p -e "CREATE DATABASE coffreFort CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Ou via Docker
docker compose up -d mysql
```

### 5. Exécuter les migrations

```bash
# Via script SQL
mysql -u votre_user -p coffreFort < sql/init.sql

# Ou via Docker
docker exec -i coffreFort-db-private mysql -uroot -proot coffreFort < sql/init.sql
```

### 6. Créer le répertoire de stockage

```bash
mkdir -p storage
chmod 700 storage
```

### 7. Lancer avec Docker Compose (recommandé)

```bash
docker compose up -d --build
```

Vérifier les logs :
```bash
docker compose logs -f web
docker logs coffreFort-web-private
```

Arrêter :
```bash
docker compose down -v
```

---

## Configuration

### Variables d'environnement (.env)

```ini
# Base de données
DB_HOST=mysql              # 'mysql' pour Docker, 'localhost' pour local
DB_PORT=3306
DB_NAME=coffreFort
DB_USER=root
DB_PASSWORD=root
DB_ROOT_PASSWORD=votre_password_root_securise

# JWT Authentication (OBLIGATOIRE)
JWT_SECRET=votre_secret_jwt_ultra_long_et_aleatoire_ici

# Clé de chiffrement AES-256-GCM (OBLIGATOIRE, ≥32 caractères)
KEY_ENCRYPTION_KEY=votre_cle_kek_de_32_caracteres_minimum_securisee

# Clé de signature HMAC pour tokens de partage (OBLIGATOIRE)
SHARE_SECRET=votre_secret_hmac_pour_tokens_partage

# Application
APP_PUBLIC_BASE_URL=http://localhost:9083
```

> **SÉCURITÉ** : Ne jamais committer le fichier `.env` ! Utilisez `.env.example` comme template.

### Configuration Docker Compose

Le fichier `docker-compose.yml` configure :
- **Service `web`** (`coffreFort-web-private`) : PHP 8.2 + Apache
- **Service `mysql`** (`coffreFort-db-private`) : MySQL 8.0
- **Service `webdb`** : WebDB — interface d'administration de la base de données
- **Volumes** : persistance BDD (`mysql-data`) + fichiers (`storage`)
- **Ports** :
  - `9083` → API backend
  - `3306` → MySQL (accessible depuis l'hôte)
  - `22071` → WebDB

---

## Utilisation

### Démarrage du serveur

```bash
docker compose up -d

# Vérifier que les conteneurs tournent
docker ps
```

Les services seront accessibles sur :
- **API** : `http://localhost:9083`
- **WebDB** : `http://localhost:22071`

### Accès à WebDB

WebDB est l'interface d'administration de la base de données, accessible à `http://localhost:22071`.

| Paramètre | Valeur |
|---|---|
| Host | `mysql` |
| Port | `3306` |
| Utilisateur | `root` |
| Mot de passe | valeur de `DB_ROOT_PASSWORD` dans `.env` |
| Base de données | valeur de `DB_NAME` dans `.env` |

### Vérification santé

```bash
curl http://localhost:9083/
```

Réponse attendue :
```json
{
  "message": "File Vault API",
  "endpoints": ["GET /admin/users/quotas", "PUT /admin/users/{id}/quota", "..."]
}
```

### Workflow complet d'utilisation sur Postman

#### 1. Créer un compte

```bash
curl -X POST http://localhost:9083/auth/register \
  -H "Content-Type: application/json" \
  -d '{ "email": "exemple@gmail.com", "password": "exemple12345" }'
```

#### 2. Se connecter (obtenir JWT)

```bash
curl -X POST http://localhost:9083/auth/login \
  -H "Content-Type: application/json" \
  -d '{ "email": "exemple@gmail.com", "password": "exemple12345" }'
```

Réponse :
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": { "id": 1, "email": "exemple@gmail.com", "is_admin": false }
}
```

#### 3. Créer un dossier

```bash
curl -X POST http://localhost:9083/folders \
  -H "Authorization: Bearer VOTRE_TOKEN_JWT" \
  -H "Content-Type: application/json" \
  -d '{ "name": "Documents", "parent_id": null }'
```

#### 4. Uploader un fichier (chiffré automatiquement)

```bash
curl -X POST http://localhost:9083/files \
  -H "Authorization: Bearer VOTRE_TOKEN_JWT" \
  -F "file=@/chemin/vers/document.pdf" \
  -F "folder_id=1"
```

#### 5. Créer un partage

```bash
curl -X POST http://localhost:9083/shares \
  -H "Authorization: Bearer VOTRE_TOKEN_JWT" \
  -H "Content-Type: application/json" \
  -d '{
    "kind": "file",
    "target_id": 1,
    "label": "Mon rapport",
    "expires_at": "2026-12-31T23:59:59Z",
    "max_uses": 5
  }'
```

#### 6. Télécharger via lien public (sans authentification)

```bash
curl -X POST http://localhost:9083/s/abc123def456.../download -O -J
```

---

## API Documentation

### Documentation OpenAPI

```
https://editor.swagger.io/?url=https://raw.githubusercontent.com/AstrowareConception/Coffre-fort-numerique/refs/heads/main/openapi.yaml
```

### Endpoints implémentés (38 routes)

| Méthode | Endpoint | Description | Auth |
|---|---|---|---|
| **Authentification** | | | |
| POST | `/auth/register` | Inscription | Non |
| POST | `/auth/login` | Connexion JWT | Non |
| **Dossiers** | | | |
| GET | `/folders` | Liste dossiers | Oui |
| GET | `/folders/{id}` | Détails dossier | Oui |
| POST | `/folders` | Créer dossier | Oui |
| PUT | `/folders/{id}` | Renommer dossier | Oui |
| DELETE | `/folders/{id}` | Supprimer dossier | Oui |
| **Fichiers** | | | |
| GET | `/files` | Liste fichiers | Oui |
| GET | `/files/{id}` | Métadonnées fichier | Oui |
| POST | `/files` | Upload fichier v1 | Oui |
| DELETE | `/files/{id}` | Supprimer fichier | Oui |
| GET | `/files/{id}/download` | Télécharger | Oui |
| **Versions** | | | |
| POST | `/files/{id}/versions` | Nouvelle version | Oui |
| GET | `/files/{id}/versions` | Liste versions | Oui |
| DELETE | `/files/{id}/versions/{vid}` | Supprimer version* | Oui |
| **Partages** | | | |
| GET | `/shares` | Mes partages | Oui |
| POST | `/shares` | Créer partage | Oui |
| POST | `/shares/{id}/revoke` | Révoquer partage | Oui |
| GET | `/s/{token}` | Info partage public | Non |
| POST | `/s/{token}/download` | Télécharger public | Non |
| **Quotas & Stats** | | | |
| GET | `/me/quota` | Mon quota | Oui |
| GET | `/me/activity` | Mon activité | Oui |
| **Admin** | | | |
| GET | `/admin/users/quotas` | Liste quotas | Admin |
| PUT | `/admin/users/{id}/quota` | Modifier quota | Admin |
| DELETE | `/admin/users/{id}` | Supprimer user | Admin |

\* Interdiction de supprimer la dernière version d'un fichier

### Codes de statut HTTP

| Code | Signification | Exemples d'utilisation |
|---|---|---|
| 200 | OK | GET réussi |
| 201 | Created | POST création réussie |
| 204 | No Content | DELETE réussi |
| 400 | Bad Request | JSON malformé |
| 401 | Unauthorized | Token JWT manquant/invalide/expiré |
| 403 | Forbidden | Non admin pour route admin |
| 404 | Not Found | Ressource introuvable |
| 409 | Conflict | Nom dossier existant, dernière version |
| 413 | Payload Too Large | Fichier > limite |
| 422 | Unprocessable Entity | Validation échouée |
| 429 | Too Many Requests | Rate limit (v2) |
| 500 | Internal Server Error | Erreur serveur |

### Format des erreurs JSON

```json
{ "error": "Message d'erreur lisible", "code": "CODE_APPLICATIF" }
```

---

## Sécurité

### Authentification JWT (Firebase PHP-JWT)

**Implémentation** : `src/Security/AuthService.php`

**Format du payload JWT** :
```json
{
  "iss": "coffre-fort-api",
  "sub": 1,
  "email": "user@example.com",
  "is_admin": false,
  "iat": 1707675000,
  "exp": 1707678600
}
```

### Chiffrement des fichiers (AES-256-GCM)

**Implémentation** : `src/Security/FileCrypto.php`

```
UPLOAD
  └─ FileCrypto::encryptForStorage()
       1. fileKey aléatoire (32 bytes) + IV (12 bytes)
       2. AES-256-GCM : plaintext → ciphertext + auth_tag
          AAD : "file:{fileId}:v{version}"
       3. Key Wrapping : fileKey + KEK → key_envelope
          AAD : "filekey:{fileId}:v{version}"
       4. Checksum : SHA-256(ciphertext)

STOCKAGE
  BDD (file_versions) : iv, auth_tag, key_envelope, checksum
  Disque (storage/)   : ciphertext

DOWNLOAD
  └─ FileCrypto::decryptFromStorage()
       1. Parse key_envelope → envIv, envTag, wrappedKey
       2. Unwrap fileKey avec KEK
       3. AES-256-GCM : ciphertext → plaintext
       4. Vérification checksum
```

**Points clés** : IV unique par chiffrement, Auth Tag 16 bytes, AAD lie le chiffrement au contexte, clé isolée par fichier.

### Tokens de partage sécurisés

**Implémentation** : `src/Security/ShareToken.php`

- Base64url (RFC 4648), URL-safe
- `random_bytes(32)` = 256 bits d'entropie
- Contrainte UNIQUE en BDD
- Décrément atomique de `remaining_uses` (évite les race conditions)

### Suppression CASCADE sécurisée

```php
// Vérifications avant suppression
if (!$currentUser['is_admin'])          → 403
if ($currentUser['id'] === $userId)     → 403 (auto-suppression)

// Suppression fichiers physiques AVANT la BDD
unlink($storagePath . '/' . $file['stored_name']);

// DELETE users CASCADE → folders → files → file_versions → shares → downloads_log
```

---

## Tests

### Périmètre

Ces tests couvrent **les 4 contrôleurs** de l'API REST via des **tests unitaires** (PHPUnit + Mockery). Ils ne nécessitent ni serveur ni base de données réels — toutes les dépendances sont simulées par des mocks.

```
tests/
├── BaseTestCase.php
└── unit/
    └── Controller/
        ├── UserControllerTest.php      # 15 tests
        ├── FileControllerTest.php      # 10 tests
        ├── ShareControllerTest.php     # 13 tests
        └── AdminControllerTest.php     # 11 tests
```

**Total : 49 tests**

### Prérequis

PHP 8.1+, Composer. Les dépendances de test s'installent avec :

```bash
composer install
```

Cela installe **PHPUnit 13** et **Mockery 1.6**.

Vérifier que `composer.json` contient :

```json
"autoload-dev": {
    "psr-4": { "Tests\\": "tests/" }
}
```

Puis relancer l'autoload :

```bash
composer dump-autoload
```

### Configuration obligatoire

Créer `phpunit.xml` à la racine du projet :

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

> **Important** : sans ce fichier, les tests retourneront des erreurs 401 ou 500 car les secrets JWT et SHARE ne seront pas définis.

### Lancer les tests

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

# Couverture de code (HTML)
./vendor/bin/phpunit --coverage-html coverage/
```

Résultat attendu :
```
OK (49 tests, XX assertions)
```

> Si des warnings Mockery apparaissent (`OK, but there were issues!`), les tests passent quand même. Voir `docs/TESTS.md` pour les corriger.

### Routes testées

| Contrôleur | Routes couvertes | Tests |
|---|---|---|
| `UserControllerTest` | POST /auth/register, POST /auth/login, GET /users, GET /users/{id}, GET /dashboard | 15 |
| `FileControllerTest` | GET /files, GET /files/{id}, GET /folders, POST /folders, PUT /folders/{id}, DELETE /folders/{id}, GET /files/{id}/versions, GET /me/quota | 10 |
| `ShareControllerTest` | POST /shares, GET /shares, GET /shares/{id}, DELETE /shares/{id}, PATCH /shares/{id}/revoke, GET /s/{token}, GET /s/{token}/versions | 13 |
| `AdminControllerTest` | GET /admin/users/quotas, PUT /admin/users/{id}/quota, DELETE /admin/users/{id} | 11 |

Chaque route est testée sur **au minimum deux scénarios** : le cas nominal (succès) et les cas d'erreur prévisibles (données invalides, droits insuffisants, ressource inexistante).

### Rechercher un test

```bash
# Par route
grep -r "GET /files" tests/unit/Controller/FileControllerTest.php

# Par mot-clé
grep -r "Invalid\|validation" tests/unit/
grep -r "jwt\|token\|auth" tests/unit/
```

### Ajouter un test

1. Ouvrir le fichier du contrôleur concerné
2. Copier un test existant de même nature
3. Renommer la méthode en `testXxx` (le préfixe `test` est obligatoire)
4. Adapter les mocks et les assertions
5. Lancer : `./vendor/bin/phpunit --filter testXxx`

### Point critique — fermeture de Mockery

Obligatoire dans chaque classe de test :

```php
protected function tearDown(): void
{
    parent::tearDown();
    \Mockery::close();
}
```

Pour la méthodologie détaillée et la roadmap d'extension des tests, voir [`docs/TESTS.md`](../docs/TESTS.md).

---

## Déploiement

### Docker Compose

```bash
# Build et démarrage
docker compose up -d --build

# Vérifier les logs
docker compose logs -f web
docker logs coffreFort-web-private

# Vérifier les conteneurs actifs
docker ps
```

### Services disponibles

| Service | URL | Description |
|---|---|---|
| API Backend | `http://localhost:9083` | API REST Slim |
| WebDB | `http://localhost:22071` | Interface d'administration BDD |
| MySQL | `localhost:3306` | Accessible depuis l'hôte |

### Arrêt et nettoyage

```bash
# Arrêter les conteneurs
docker compose down

# Arrêter ET supprimer les volumes (⚠️ perte de données)
docker compose down -v
```

---

## Contribution

### Workflow Git

```bash
git checkout -b feature/nom-fonctionnalite
git add .
git commit -m "feat: description de la fonctionnalité"
git push origin feature/nom-fonctionnalite
```

### Conventions de commit

- `feat:` nouvelle fonctionnalité
- `fix:` correction de bug
- `docs:` documentation
- `refactor:` refactoring
- `test:` ajout/modification de tests
- `chore:` maintenance

---

## Problèmes connus et solutions

### 1. Medoo non reconnu après `composer init`

```bash
rm -rf vendor composer.lock
composer require slim/slim:"4.12" slim/psr7:"1.8" catfan/medoo:"2.2"
composer update
```

### 2. Upload échoue avec 400 Bad Request (limite taille)

Créer `php.ini` :
```ini
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 256M
```

Puis rebuild :
```bash
docker compose down && docker compose up -d --build
```

### 3. `parent_id` — contrainte FOREIGN KEY

> Non implémenté (v2) — `parent_id` doit être `null` pour les dossiers racines :

```json
{ "name": "Racine", "parent_id": null }
```

### 4. Token JWT expire trop vite

Modifier dans `UserController::login()` :
```php
'exp' => time() + 3600,  // 1 heure
```

### 5. Triggers MySQL — accès root requis

```bash
docker exec -it coffreFort-db-private mysql -uroot -proot coffreFort
```

---

## Ressources

- [Slim Framework 4](https://www.slimframework.com/docs/v4/)
- [Medoo](https://medoo.in/doc)
- [Firebase PHP-JWT](https://github.com/firebase/php-jwt)
- [WebDB](https://webdb.app/)
- [PHPUnit Docs](https://docs.phpunit.de/)
- [Mockery Docs](https://docs.mockery.io/)
- [Postman](https://www.postman.com/)
- [Swagger Editor](https://editor.swagger.io/)

---

## Équipe

- **Backend Lead** : Klaudia Juhasz
- **JavaFX Developer** : Klaudia Juhasz
- **Web Developer** : Denys LYULCHAK

---

## Roadmap

### Version 1.0 (MVP) ✅
- [x] Auth JWT (register, login)
- [x] CRUD dossiers/fichiers
- [x] Chiffrement AES-256-GCM
- [x] Versionnage fichiers
- [x] Partages contrôlés (token base64url)
- [x] Quotas utilisateurs
- [x] Rôle admin (suppression users)
- [x] Journalisation téléchargements
- [x] Table audit_logs
- [x] 38 routes testées (Postman + PHPUnit)
- [x] CASCADE automatique
- [x] 49 tests unitaires (4 contrôleurs)

### Version 2.0 (En cours)
- [ ] Protection bruteforce (rate limiting)
- [ ] Déplacement dossiers/fichiers
- [ ] Triggers supplémentaires pour audit_logs
- [ ] Fonctionnalité "Mot de passe oublié"
- [ ] Renforcement politique mots de passe
- [ ] Extension tests : repositories, services, intégration

### Version 3.0 (Futur)
- [ ] Déchiffrement côté client (zero-knowledge)
- [ ] 2FA (TOTP)
- [ ] Prévisualisation fichiers
- [ ] Notifications email
- [ ] Export audit_logs (CSV/JSON)

---

## Support

- **Issues GitHub** : [https://github.com/PlumCreativ/coffreFort/issues](https://github.com/PlumCreativ/coffreFort/issues)

---

**Dernière mise à jour** : 25 mars 2026

**Version API** : 1.0.0
**Routes testées** : 38/38 ✅ | **Tests unitaires** : 49/49 ✅
