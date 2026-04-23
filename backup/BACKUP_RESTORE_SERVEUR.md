# Backup et Restauration — Serveur École

Auteur : Klaudia Juhasz
Contexte : TP Backup / Restauration — BTS SIO
Serveur : 37.64.159.66 (port 2222)
Projet sur le serveur : `/home/iris/slam/cryptovault/`

---

## Structure sur le serveur

```
/home/iris/slam/cryptovault/
├── backup/
│   ├── backup_serveur.sh              # Sauvegarde automatisée (hot backup)
│   ├── restore_serveur.sh             # Restauration en production
│   ├── restore_serveur_isoled.sh      # Restauration isolée (prod intacte)
│   └── BACKUP_RESTORE_SERVEUR.md     # Ce fichier
├── backups/
│   ├── cryptovault_serveur_backup_YYYYMMDD_HHMMSS.tar.gz
│   ├── cryptovault_serveur_backup_YYYYMMDD_HHMMSS.tar.gz.sha256
│   ├── backup_YYYYMMDD_HHMMSS.log
│   └── cron_backup.log
└── restores/
    ├── code/                          # Code restauré (restore isolé)
    └── docker-compose.restore.yml    # Généré automatiquement (restore isolé)
```

---

## Backup

### Lancer un backup manuellement

```bash
bash /home/iris/slam/cryptovault/backup/backup_serveur.sh
```

### Ce que le backup contient

```
cryptovault_serveur_backup_YYYYMMDD_HHMMSS/
├── database/
│   └── cryptovault.sql.gz             # Dump MySQL compressé
├── volumes/
│   └── cryptovault-mysql-data.tar.gz  # Volume Docker MySQL
├── config/
│   ├── docker-compose.prod.yml
│   ├── .env
│   ├── Dockerfile
│   ├── php.ini
│   ├── composer.json
│   ├── composer.lock
│   └── init.sql
├── applications/
│   ├── public.tar.gz
│   ├── src.tar.gz
│   ├── config.tar.gz
│   ├── vendor.tar.gz
│   ├── storage.tar.gz
│   └── sql.tar.gz
└── logs/
    ├── cryptovault-web.log
    └── cryptovault-db.log
```

### Caractéristiques

- **Hot backup** : les conteneurs restent UP pendant toute la sauvegarde
- **Intégrité** : fichier `.sha256` généré et vérifié automatiquement après chaque archive
- **Rétention** : archives de plus de 7 jours supprimées automatiquement

### Consulter les logs

```bash
# Dernier log de backup
ls -t /home/iris/slam/cryptovault/backups/backup_*.log | head -1 | xargs tail -50

# Log cron
tail -f /home/iris/slam/cryptovault/backups/cron_backup.log

# Lister les archives
ls -lh /home/iris/slam/cryptovault/backups/cryptovault_serveur_backup_*.tar.gz
```

---

## Restauration en production

> Arrête les services, restaure le code et la base, redémarre.
> Utiliser uniquement si on veut écraser la production.

```bash
# Mode interactif (liste les backups disponibles)
bash /home/iris/slam/cryptovault/backup/restore_serveur.sh

# Fichier backup spécifique
bash /home/iris/slam/cryptovault/backup/restore_serveur.sh \
  --backup-file /home/iris/slam/cryptovault/backups/cryptovault_serveur_backup_YYYYMMDD_HHMMSS.tar.gz
```

Le script demande une confirmation `oui/non` avant d'écraser la production.

---

## Restauration isolée (recommandée)

> La production reste UP et intacte.
> Le backup est restauré dans des conteneurs séparés pour vérification.

```bash
# Mode interactif
bash /home/iris/slam/cryptovault/backup/restore_serveur_isoled.sh

# Fichier backup spécifique
bash /home/iris/slam/cryptovault/backup/restore_serveur_isoled.sh \
  --backup-file /home/iris/slam/cryptovault/backups/cryptovault_serveur_backup_YYYYMMDD_HHMMSS.tar.gz
```

### Ressources isolées créées

| Ressource | Valeur |
|-----------|--------|
| Conteneur web | `cryptovault-restore-web` |
| Conteneur DB | `cryptovault-restore-db` |
| Volume MySQL | `cryptovault-restore-mysql-data` |
| Réseau | `cryptovault-restore-network` |
| Port local | `127.0.0.1:9094` |
| Code restauré | `/home/iris/slam/cryptovault/restores/code/` |

### Vérifier que la restauration est accessible

```bash
curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:9094
# Résultat attendu : 200 ou 302
```

### Nettoyer après vérification

```bash
docker compose -f /home/iris/slam/cryptovault/restores/docker-compose.restore.yml down -v
```

---

## Vérification de l'intégrité

Chaque archive est accompagnée d'un `.sha256`. Le script de restauration le vérifie automatiquement.

Pour vérifier manuellement :

```bash
cd /home/iris/slam/cryptovault/backups/
sha256sum -c cryptovault_serveur_backup_YYYYMMDD_HHMMSS.tar.gz.sha256
# Résultat attendu : cryptovault_serveur_backup_YYYYMMDD_HHMMSS.tar.gz: OK
```

---

## Automatisation CRON

### Planning actuel

| Expression | Script | Projet |
|------------|--------|--------|
| `30 18 * * 5` | `backup_serveur.sh` (CryptoVault) | Vendredi 18h30 |

### Consulter le crontab

```bash
crontab -l
```

### Log cron CryptoVault

```bash
tail -f /home/iris/slam/cryptovault/backups/cron_backup.log
```

---

## Prérequis

- Docker en cours d'exécution (`docker info`)
- Conteneur `cryptovault-db` existant et healthy
- `sha256sum` disponible (inclus sur Debian/Ubuntu)
- Accès SSH : `ssh -i ~/.ssh/serveurMediaSchool -p 2222 klaudia@37.64.159.66`
