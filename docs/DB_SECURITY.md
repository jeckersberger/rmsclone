# Datenbank-Sicherheit: Minimale Benutzerrechte

## Empfohlenes Setup fuer Produktivbetrieb

### 1. Anwendungs-Benutzer (eingeschraenkt)

Erstelle einen separaten DB-Benutzer fuer die Anwendung mit minimalen Rechten:

```sql
-- Anwendungs-Benutzer erstellen
CREATE USER 'adamrms_app'@'localhost' IDENTIFIED BY '<SICHERES_PASSWORT>';

-- Nur DML-Rechte (kein DROP, ALTER, CREATE in Produktion)
GRANT SELECT, INSERT, UPDATE, DELETE ON adamrms.* TO 'adamrms_app'@'localhost';

-- FLUSH Rechte: Fuer Lock-Operationen
GRANT LOCK TABLES ON adamrms.* TO 'adamrms_app'@'localhost';

FLUSH PRIVILEGES;
```

### 2. Migrations-Benutzer (nur fuer Deployments)

```sql
-- Migrations-Benutzer fuer Schema-Aenderungen
CREATE USER 'adamrms_migrate'@'localhost' IDENTIFIED BY '<ANDERES_PASSWORT>';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
    ON adamrms.* TO 'adamrms_migrate'@'localhost';

FLUSH PRIVILEGES;
```

### 3. Backup-Benutzer (nur Lesen)

```sql
-- Backup-Benutzer fuer mysqldump
CREATE USER 'adamrms_backup'@'localhost' IDENTIFIED BY '<BACKUP_PASSWORT>';

GRANT SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES ON adamrms.* TO 'adamrms_backup'@'localhost';

FLUSH PRIVILEGES;
```

### 4. Umgebungsvariablen

```env
# Anwendung (Produktion)
DB_USER=adamrms_app
DB_PASS=<SICHERES_PASSWORT>

# Migrationen (nur beim Deployment)
DB_MIGRATE_USER=adamrms_migrate
DB_MIGRATE_PASS=<ANDERES_PASSWORT>

# Backups
DB_BACKUP_USER=adamrms_backup
DB_BACKUP_PASS=<BACKUP_PASSWORT>
```

### 5. Checkliste

- [ ] Separaten App-Benutzer ohne DDL-Rechte erstellen
- [ ] Separaten Backup-Benutzer mit nur SELECT erstellen
- [ ] Separaten Migrations-Benutzer fuer Deployments erstellen
- [ ] Root-Zugang nur ueber lokale Konsole
- [ ] Keine Remote-Verbindung fuer DB-Benutzer (nur 'localhost')
- [ ] Passwoerter in Umgebungsvariablen, nicht in Code
