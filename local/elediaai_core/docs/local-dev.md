# Lokale Moodle-Instanz (OrbStack / Docker)

Runbook für die lokale Entwicklungsinstanz aus `infra/docker-compose.local.yml`.
Start/Upgrade laufen über `scripts/local-deploy.sh deploy`.

## Modell: Core im Image, Custom-Plugins gemountet

- **Moodle-Core** wird ins Image gebaut (Dockerfile klont den gepinnten Tag
  `MOODLE_GIT_REF`). Core wird nie gemountet.
- **Custom-Plugins** werden aus dem Workspace **bind-gemountet** (Anchor
  `x-moodle-volumes` in `infra/docker-compose.local.yml`, geteilt von `moodle` und
  `cron`). Der Host ist die Quelle der Wahrheit: eine Änderung am Plugin ist
  im Container sofort live, der Container kann nicht mehr vom Repo abdriften.

Warum das nötig war: Ohne Mounts wurden Plugins ins Image gebacken bzw. per
`docker cp` einmalig hineinkopiert. Sobald der Host-Code sich änderte, lief
der Container auf einer alten Version weiter — im schlimmsten Fall älter als
der DB-Stand, was jedes `admin/cli/upgrade.php` mit `cannotdowngrade`
blockierte und damit auch den Classmap-Rebuild (neue autoloaded Klassen waren
außerhalb von PHPUnit unsichtbar).

## Gemountete Plugins

30 Bind-Mounts: 29 aus diesem Repo (`public/<typ>/<plugin>`), einer aus dem
Nachbar-Repo lernhive. `local/elediaai_sources` zieht seine Subplugins über den
Parent-Mount mit.

**Aus eledia.ai (`public/...`):**

| Typ | Plugins |
|---|---|
| `admin/tool/` | dynamic_cohorts, taskrunner |
| `ai/provider/` | eledia |
| `blocks/` | elediaai_tutor, elediaai_chat, elediaai_path, elediaai_tactics |
| `customfield/field/` | languageselect |
| `filter/` | eledia_translate |
| `local/` | activityfilter, customerportal, elediaai_tutor_premium, h5pauthor, elediaai_core, elediaai_coursegen, elediaai_questiongen, elediaai_selfstudy, elediaai_strategy, elediaai_tactics, elediaai_teachertools, literag, elediaai_sources |
| `mod/` | aichat, aifeedback, elli |
| `qbank/` | elediaai_questiongen |
| `qtype/` | aitext |
| `webservice/` | elediamcp |

`local/elediaai_sources` bringt seine Subplugins (`subplugins/*`) selbst mit — der
Parent-Mount deckt sie ab, sie brauchen keine eigenen Zeilen.

**Aus dem lernhive-Repo (Cross-Repo):**

| Quelle | Ziel |
|---|---|
| `../Lernhive/lernhive/public/local/lernhive` (vom Repo-Root) | `local/lernhive` |

`local_lernhive` wird für AI Suite Core nicht benötigt. Die historische lokale
Mount-Pfad ist relativ zu `infra/docker-compose.local.yml` (`../../Lernhive/lernhive`);
bei abweichendem Checkout-Ort die eine Zeile im Compose-Header anpassen.

**Nicht gemountet, bewusst:**

- `filter/translations` — Alt-Filter vor der Umbenennung in
  `filter_eledia_translate`, in keinem Repo mehr gepflegt, bleibt image-baked
  bis zur Deinstallation.
- Plugins, die der Build via `prune_unmet_plugins.sh` wegen unerfüllter
  Abhängigkeiten aussortiert (aktuell keine). Solche nicht mounten — ein Mount
  würde sie installieren und das Upgrade an der fehlenden Abhängigkeit
  scheitern lassen.

## Neues Plugin aufnehmen

1. Zeile in den `x-moodle-volumes`-Anchor eintragen:
   `- ../public/<typ>/<name>:/var/www/html/public/<typ>/<name>`
2. Container neu erstellen (übernimmt geänderte Volumes, ohne Image-Rebuild):
   `docker compose -p elediaai --env-file .env.local -f infra/docker-compose.local.yml up -d`
3. Upgrade + Caches: `docker compose ... exec moodle php admin/cli/upgrade.php --non-interactive`
   und `... purge_caches.php`.

Wichtig: nur Plugins mounten, die tatsächlich installiert (oder installierbar)
sind — sonst bricht das Upgrade an unerfüllten Abhängigkeiten.

## Wechselwirkung mit `local-deploy.sh`

Das Script installiert/upgraded die Datenbank und fährt die Post-Deploy-Tasks.
Custom-Plugins kopiert es **nicht** mehr in den Container — das übernehmen die
Bind-Mounts.

Der frühere Schritt `inject_external_lernhive` (`docker cp` von
`LOCAL_LERNHIVE_PATH` nach `local/lernhive`) wurde entfernt: `docker cp`
schrieb **durch einen Bind-Mount hindurch** aufs Host-Verzeichnis und hätte bei
abweichendem `LOCAL_LERNHIVE_PATH` den gemounteten Working Tree überschrieben.
`LOCAL_LERNHIVE_PATH` ist damit obsolet; der Pfad zum lernhive-Checkout steht
nur noch im Compose-Mount (`../../Lernhive/lernhive`, relativ zur Compose-Datei).

Der `fresh`-Befehl baut das Image bewusst ohne eingebackene Custom-Plugins
(`INSTALL_CUSTOM_PLUGINS=false`) — sie kommen ohnehin aus den Mounts. Ein
komplett pluginfreies Moodle ist mit dieser Compose-Datei daher nicht mehr
möglich (dazu müssten die Mounts auskommentiert werden).

## Was der Mount nicht abnimmt

Ein `version.php`-Bump erfordert weiterhin `admin/cli/upgrade.php`. Der Mount
hält nur den Code synchron, nicht den DB-Schema-/Version-Stand.

## Behat mit Selenium

Der optionale Compose-Service `selenium` liegt im Profil `behat` und startet
bei normalen `deploy`-/`up`-Aufrufen nicht. Behat verwendet die eigene
MariaDB-Datenbank `moodle_behat`, den Präfix `bht_` und das Volume
`local_moodledata_behat`; Live- und PHPUnit-Daten bleiben unberührt.

```bash
# Einmalig bzw. nach Änderungen an Plugins/Behat-Schritten:
scripts/local-deploy.sh behat-init

# Standard-Smoke für den Kurstools-Block:
scripts/local-deploy.sh behat

# Anderen selektiven Track ausführen:
BEHAT_TAGS=@local_elediaai_tactics scripts/local-deploy.sh behat
```

Profil und Tags lassen sich über `BEHAT_PROFILE`, `BEHAT_TAGS` und optional
`BEHAT_NAME` eingrenzen. Browser und Moodle kommunizieren ausschließlich im
Compose-Netz (`http://selenium:4444/wd/hub` bzw. `http://moodle`); es werden
keine zusätzlichen Host-Ports und keine Secrets benötigt. Fehlschläge liegen
unter dem Behat-Dataroot in `faildumps`.

## Drift prüfen

Container-Versionen gegen die Workspace-Repos vergleichen, falls doch etwas
auseinanderläuft (z. B. ein nicht gemountetes, image-baked Plugin):

```bash
docker compose -p elediaai --env-file .env.local -f infra/docker-compose.local.yml \
  exec moodle sh -c 'for d in /var/www/html/public/*/*/version.php; do \
    grep -HoE "\$plugin->version[[:space:]]*=[[:space:]]*[0-9]+" "$d"; done'
```
