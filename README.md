# H2 Backup API Plugin

Ein WordPress-Plugin, das eine sichere REST API für Datenbank-Dumps, wp-content Verzeichnis-Indexierung und Datei-Downloads bereitstellt. Alle Endpunkte sind durch Bearer-Token-Authentifizierung geschützt.

## 📋 Beschreibung

Das H2 Backup API Plugin ermöglicht es Ihnen, WordPress-Backups über eine REST API zu erstellen und herunterzuladen. Das Plugin bietet drei Hauptfunktionen:

- **SQL Dump**: Vollständiger Datenbank-Export als komprimierte .sql.gz-Datei
- **wp-content Index**: Liste aller Dateien im wp-content-Verzeichnis mit Prüfsummen
- **wp-content File Download**: Download einzelner Dateien aus dem wp-content-Verzeichnis

## ✨ Features

- 🔒 **Sicherheit**: Alle API-Endpunkte sind durch Bearer-Token geschützt
- 📦 **Komprimierung**: SQL-Dumps werden automatisch mit gzip komprimiert
- ✅ **Prüfsummen**: SHA-256 Checksummen für alle Dateien im Index
- 🎯 **Einfache Verwaltung**: Benutzerfreundliche Einstellungsseite im WordPress-Admin
- 🚀 **REST API**: Standard WordPress REST API Implementation

## 🔧 Installation

1. Laden Sie die Datei `backup.php` in Ihr WordPress-Plugins-Verzeichnis hoch:
   ```
   /wp-content/plugins/h2-backup-api/backup.php
   ```

2. Aktivieren Sie das Plugin im WordPress-Admin unter "Plugins"

3. Navigieren Sie zu "Einstellungen" → "Backup API"

4. Konfigurieren Sie einen sicheren Bearer Token (empfohlen: mindestens 32 Zeichen)

## 📚 Verwendung

### Konfiguration

1. Gehen Sie zu **Einstellungen → Backup API** im WordPress-Admin
2. Geben Sie einen sicheren Bearer Token ein und speichern Sie die Einstellungen
3. Kopieren Sie die API-Endpunkt-URLs aus der Einstellungsseite

### API-Endpunkte

#### 1. SQL Dump

```bash
GET /wp-json/backup/v1/sql-dump
Authorization: Bearer YOUR_TOKEN_HERE
```

**Beschreibung**: Erstellt einen vollständigen Datenbank-Dump aller WordPress-Tabellen.

**Response**: Komprimierte .sql.gz-Datei mit Zeitstempel im Dateinamen (z.B. `backup-2026-02-15-143025.sql.gz`)

**Beispiel**:
```bash
curl -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     https://ihre-domain.de/wp-json/backup/v1/sql-dump \
     -o backup.sql.gz
```

#### 2. wp-content Index

```bash
GET /wp-json/backup/v1/wp-content-index
Authorization: Bearer YOUR_TOKEN_HERE
```

**Beschreibung**: Gibt eine Liste aller Dateien im wp-content-Verzeichnis zurück.

**Response**: JSON mit Dateiinformationen
```json
{
  "generated_at": "2026-02-15T14:30:25+00:00",
  "count": 1234,
  "files": [
    {
      "path": "uploads/2026/02/image.jpg",
      "checksum": "abc123...",
      "size": 123456,
      "modified": "2026-02-15T14:30:25+00:00"
    }
  ]
}
```

**Beispiel**:
```bash
curl -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     https://ihre-domain.de/wp-json/backup/v1/wp-content-index
```

#### 3. wp-content Datei-Download

```bash
GET /wp-json/backup/v1/wp-content-file?path=RELATIVER_PFAD
Authorization: Bearer YOUR_TOKEN_HERE
```

**Beschreibung**: Lädt eine einzelne Datei aus dem wp-content-Verzeichnis herunter.

**Parameter**:
- `path` (erforderlich): Relativer Pfad zur Datei innerhalb von wp-content

**Response**: Die angeforderte Datei als Download

**Beispiel**:
```bash
curl -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     "https://ihre-domain.de/wp-json/backup/v1/wp-content-file?path=uploads/2026/02/image.jpg" \
     -o image.jpg
```

## 🔐 Sicherheit

- **Bearer Token**: Verwenden Sie einen starken, zufälligen Token (mindestens 32 Zeichen)
- **HTTPS**: Verwenden Sie das Plugin nur über HTTPS-Verbindungen
- **Path Traversal Protection**: Das Plugin schützt gegen Directory-Traversal-Angriffe
- **WordPress-Berechtigungen**: Einstellungen sind nur für Benutzer mit `manage_options`-Berechtigung zugänglich

### Token-Generierung

Empfohlene Methode zur Generierung eines sicheren Tokens:

```bash
# Linux/Mac
openssl rand -base64 32

# Windows PowerShell
[Convert]::ToBase64String([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
```

## 🛠️ Technische Details

- **WordPress Version**: 5.0+
- **PHP Version**: 7.0+
- **REST API Namespace**: `backup/v1`
- **Komprimierung**: gzip Level 9
- **Prüfsummen**: SHA-256

## 📝 Changelog

### Version 1.0.0
- Initiale Version
- SQL-Dump-Funktion mit gzip-Komprimierung
- wp-content Index mit SHA-256 Checksummen
- Einzelner Datei-Download
- Bearer-Token-Authentifizierung

## 📄 Lizenz

Dieses Projekt kann frei verwendet werden. Bitte beachten Sie, dass Sie es auf eigene Verantwortung einsetzen.

## ⚠️ Hinweise

- Stellen Sie sicher, dass Ihr Server genügend Speicher für große Datenbank-Dumps hat
- Bei sehr großen wp-content-Verzeichnissen kann die Indexierung Zeit in Anspruch nehmen
- Testen Sie das Plugin zunächst in einer Staging-Umgebung
- Halten Sie Ihren Bearer Token geheim und speichern Sie ihn sicher

## 🤝 Support

Bei Fragen oder Problemen erstellen Sie bitte ein Issue in diesem Repository.
