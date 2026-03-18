# CI/CD — Automatisation des tests

## Pourquoi cette mise en place ?

Sans automatisation, rien n'empêche d'intégrer du code cassé sur la branche principale.
Un bug introduit par une modification peut passer inaperçu pendant des jours, et n'être
découvert qu'en production.

Le workflow `tests.yml` résout ce problème : **les 49 tests unitaires se lancent
automatiquement à chaque push**, sans intervention manuelle. Si un test échoue,
la fusion est bloquée immédiatement.

---

## Ce que ça apporte concrètement

- **Filet de sécurité** — toute régression est détectée dans la minute qui suit le push,
  pas en production
- **Branche `main` toujours stable** — seul du code ayant passé tous les tests peut y
  être intégré
- **Reproductibilité** — les tests tournent sur une machine Ubuntu propre, indépendamment
  de l'environnement local du développeur
- **Bonne pratique professionnelle** — la CI/CD est standard dans tous les projets en
  entreprise

---

## Déclencheurs

Le workflow se lance automatiquement dans trois situations :

| Déclencheur | Quand |
|---|---|
| `push` | À chaque push sur `main` ou `develop` |
| `pull_request` | À l'ouverture d'une pull request vers `main` ou `develop` |
| `workflow_dispatch` | Manuellement depuis l'onglet **Actions** de GitHub |

---

## Ce que fait le workflow étape par étape

```
1. GitHub crée une machine Ubuntu propre
2. Récupère le code du dépôt
3. Installe PHP 8.4
4. Met en cache les dépendances Composer (pour accélérer les prochains runs)
5. Installe PHPUnit 13 et Mockery via Composer
6. Injecte les secrets (JWT_SECRET, SHARE_SECRET...) depuis GitHub Secrets
7. Lance les 49 tests unitaires avec PHPUnit
8. Retourne le résultat : ✅ vert (tous passent) ou ❌ rouge (au moins un échoue)
```

---

## Variables d'environnement

Les tests nécessitent des secrets (clés JWT, clé de partage...). Ces valeurs ne sont
**jamais stockées dans le code** — elles sont configurées dans
**Settings → Secrets and variables → Actions** du dépôt GitHub et injectées
au moment de l'exécution.

| Secret GitHub | Correspond à |
|---|---|
| `JWT_SECRET` | Clé de signature des tokens JWT |
| `SHARE_SECRET` | Clé HMAC pour la signature des tokens de partage |
| `KEY_ENCRYPTION_KEY` | Clé de chiffrement des fichiers |
| `APP_PUBLIC_BASE_URL` | URL publique de l'application |

> En local, ces valeurs sont dans `phpunit.xml` qui est dans `.gitignore` —
> il ne doit jamais être commité sur GitHub.

---

## Bloquer la fusion si les tests échouent

Pour que les tests soient **obligatoires** avant toute fusion sur `main` :

1. Aller dans **Settings → Branches → Add branch ruleset**
2. Cibler la branche `main`
3. Activer **"Require status checks to pass before merging"**
4. Sélectionner le job : `Tests unitaires (PHP 8.4)`
5. Activer **"Require a pull request before merging"** pour forcer le passage par une PR

Résultat : le bouton **Merge** est grisé tant que les tests n'ont pas passé.

---

## CI vs CD

Ce workflow met en place la **CI** (Continuous Integration) uniquement :

| Sigle | Nom | Ce que ça fait | Mis en place |
|---|---|---|---|
| CI | Continuous Integration | Tester automatiquement à chaque push | ✅ Oui |
| CD | Continuous Delivery/Deployment | Déployer automatiquement si les tests passent | ❌ Pas encore |

Le CD pourra être ajouté ultérieurement quand un serveur de production sera configuré —
l'architecture du workflow est déjà prête pour l'accueillir.

---

## Fichiers concernés

```
.github/
├── workflows/
│   └── tests.yml       ← le workflow CI/CD
└── README.md           ← ce fichier

phpunit.xml             ← ignoré par Git (.gitignore), contient les secrets locaux
phpunit.xml.dist        ← modèle vide commité, à copier en phpunit.xml en local
```
