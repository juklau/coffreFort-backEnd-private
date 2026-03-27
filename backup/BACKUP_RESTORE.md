# Backup & Restauration — CryptoVault

## Structure

```
coffreFort-back-private/
├── backup/
│   ├── backup.sh                   # Script de sauvegarde
│   ├── restore.sh                  # Script de restauration
│   └── BACKUP_RESTORE.md           # Ce fichier
├── backups/
│   ├── cryptovault_backup_20260325_120000.tar.gz
│   ├── cryptovault_backup_20260325_120000.tar.gz.sha256
│   ├── backup_20260325_120000.log
│   └── restore_20260325_120000.log
└── restores/
```

> Le dossier `backups/` est dans `.gitignore` — les archives ne sont pas committées.

---

## Lancer un backup manuellement

Depuis la racine du projet ou depuis n'importe où — le script résout son chemin automatiquement :

```bash
bash backup/backup.sh
```

---

## Restaurer un backup

```bash
# Mode interactif (liste les backups disponibles et demande le répertoire cible)
bash backup/restore.sh

# Restaurer un fichier spécifique
bash backup/restore.sh --backup-file backups/cryptovault_backup_20260325_120000.tar.gz

# Spécifier le répertoire des backups et la cible
bash backup/restore.sh --backup-dir /chemin/vers/backups --target-dir /chemin/vers/cible

# Mode développement (conserve docker-compose.yml et .env actuels)
bash backup/restore.sh --dev

# Afficher l'aide
bash backup/restore.sh --help
```

### Options disponibles

| Option | Description |
|---|---|
| `--backup-dir PATH` | Répertoire contenant les backups (défaut : `./backups`) |
| `--target-dir PATH` | Répertoire cible pour la restauration (défaut : `./restores`) |
| `--backup-file PATH` | Fichier backup spécifique à restaurer |
| `--dev` | Mode développement : conserve `docker-compose.yml` et `.env` actuels |
| `-h, --help` | Afficher l'aide |

---

## Automatiser le backup avec CRON

### Prérequis — WSL (Windows)

CRON ne fonctionne pas nativement dans Git Bash. Il faut utiliser **WSL (Ubuntu)**.

Installer et démarrer CRON dans WSL :

```bash
sudo apt update && sudo apt install cron -y
sudo service cron start
```

Vérifier que CRON tourne :

```bash
sudo service cron status
# Résultat attendu : * cron is running
```

> **Important :** sous WSL, le service CRON s'arrête quand toutes les fenêtres WSL sont fermées.
> Il faut le redémarrer à chaque session avec `sudo service cron start` avant l'heure programmée.

### Ajouter la tâche CRON

Ouvrir le crontab :

```bash
crontab -e
```

Ajouter la ligne suivante pour lancer le backup **tous les vendredis à 12h00** :

```
0 12 * * 5 /bin/bash "/mnt/c/Users/jukla/Desktop/BTS_SIO/Atelier_de_professionnalisation/GitHub/coffre-fort-numérique/coffreFort-back-private/backup/backup.sh" >> "/mnt/c/Users/jukla/Desktop/BTS_SIO/Atelier_de_professionnalisation/GitHub/coffre-fort-numérique/coffreFort-back-private/backups/cron.log" 2>&1
```

> Pour trouver le chemin absolu de ton projet depuis WSL :
> ```bash
> cd /mnt/c/Users/jukla/Desktop/.../coffreFort-back-private && pwd
> ```

Vérifier que la tâche est bien enregistrée :

```bash
crontab -l
```

### Syntaxe CRON

```
┌───── minute    (0-59)
│ ┌─── heure     (0-23)
│ │ ┌─ jour/mois (1-31)
│ │ │ ┌ mois     (1-12)
│ │ │ │ ┌ jour/semaine (0=dim, 1=lun ... 5=ven, 6=sam)
│ │ │ │ │
0 12 * * 5   →  tous les vendredis à 12h00
```

Exemples de fréquences utiles :

| Fréquence | Expression CRON |
|---|---|
| Tous les jours à 2h | `0 2 * * *` |
| Tous les vendredis à 12h | `0 12 * * 5` |
| Tous les lundis à 3h | `0 3 * * 1` |
| Toutes les 6 heures | `0 */6 * * *` |

### Procédure chaque vendredi

| Étape | Action |
|---|---|
| 1 | Allumer le PC avant 12h00 |
| 2 | Ouvrir WSL et lancer `sudo service cron start` |
| 3 | Fermer WSL si besoin — CRON continue en arrière-plan |
| 4 | À 12h00 → le backup se lance automatiquement ✅ |

> **Limite connue :** le backup ne se lance pas si l'ordinateur est éteint ou en veille à l'heure programmée.
> En production réelle sur un serveur Linux, CRON démarre automatiquement au boot sans intervention manuelle.

---

## Vérifier que le backup fonctionne

**Tester le script manuellement :**

```bash
bash backup/backup.sh
```

**Tester que CRON fonctionne (test rapide à 1 minute) :**

```bash
crontab -e
# Ajouter temporairement :
* * * * * echo "CRON OK - $(date)" >> /tmp/test-cron.log 2>&1
```

Puis après 1 minute :

```bash
cat /tmp/test-cron.log
# Résultat attendu : CRON OK - Fri Mar 25 12:00:01 UTC 2026
```

Supprimer ensuite cette ligne de test avec `crontab -e`.

**Consulter les logs du backup :**

```bash
# Logs en temps réel
tail -f backups/cron.log

# Lister les archives créées
ls -lh backups/cryptovault_backup_*.tar.gz
```

---

## Ce que contient une archive

```
cryptovault_backup_20260325_120000/
├── database/
│   └── coffreFort.sql.gz               # Dump SQL compressé (mysqldump)
├── volumes/
│   └── cryptovault_mysql-data.tar.gz   # Volume Docker MySQL
├── config/
│   ├── docker-compose.yml
│   ├── .env
│   ├── Dockerfile
│   ├── composer.json
│   ├── composer.lock
│   ├── init.sql
│   └── init_triggers.sql
├── applications/
│   ├── public.tar.gz
│   ├── src.tar.gz
│   ├── config.tar.gz
│   ├── vendor.tar.gz
│   ├── storage.tar.gz
│   └── api.tar.gz
└── logs/
    ├── coffreFort-web-private.log
    └── coffreFort-db-private.log
```

---

## Rétention

Les archives de plus de **7 jours** sont supprimées automatiquement à la fin de chaque backup (archives `.tar.gz`, fichiers `.sha256` et logs `.log`).

Pour modifier la durée, changer la variable `RETENTION_DAYS` dans `backup.sh` :

```bash
RETENTION_DAYS=7   # Modifier cette valeur
```

---

## Vérification de l'intégrité

Chaque archive est accompagnée d'un fichier `.sha256` contenant son empreinte SHA256.
Le script de restauration vérifie automatiquement cette empreinte avant de restaurer.

Pour vérifier manuellement :

```bash
cd backups/
sha256sum -c cryptovault_backup_20260325_120000.tar.gz.sha256
# Résultat attendu : cryptovault_backup_20260325_120000.tar.gz: OK
```

---

## Prérequis

- Docker Desktop démarré et conteneur `coffreFort-db-private` en cours d'exécution
- `sha256sum` disponible (inclus dans WSL/Linux)
- WSL avec CRON installé pour l'automatisation
