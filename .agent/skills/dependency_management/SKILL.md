---
name: load-blueprint-dependency-management
description: KRITISCH! Regelt die Evaluierung, Freigabe, Versionierung und laufende Pflege aller Dependencies. Deckt Approved-Lists, Lizenzprüfung, Lock-Files, Auditing, CVE-Response und Sunset-Erkennung ab.
---

# BLUEPRINT: Dependency-Management (Enterprise Standard 2026)

**SCOPE:** Alle Projekte (Frontend, Backend, Shopware) | **PARADIGMA:** Zero-Surprise-Dependencies, Reproducible Builds

## 1. Approved-Lists (Auto-Freigabe, kein Review nötig)

| Stack | Freigegebene Pakete |
|---|---|
| **Frontend (Svelte/React)** | `svelte`, `tailwindcss`, `zod`, `lucide-*`, `clsx`, `date-fns`, `@tanstack/query` |
| **Backend (FastAPI)** | `fastapi`, `pydantic`, `sqlmodel`, `uvicorn`, `redis`, `tenacity`, `structlog`, `ruff`, `pytest` |
| **Shopware (Composer)** | Nur Shopware-Core + offizielle Symfony-Bundles. Third-Party → **PFLICHT-Review ohne Ausnahme**. |

**Jedes Paket außerhalb dieser Listen** MUSS den Evaluierungs-Checklist (§2) durchlaufen.

## 2. Evaluierungs-Checklist (Neue Dependencies)

Jede Dependency MUSS **alle** Kriterien erfüllen, bevor sie vorgeschlagen wird:

- **Aktualität:** Letztes Release < 12 Monate. Älter → Sunset-Verdacht (§8).
- **Community:** ≥ 200 GitHub-Stars. Darunter → explizite Begründung PFLICHT.
- **TypeScript-Support:** Eigene Typen oder `@types/*` verfügbar. Untypisierte Pakete sind **STRIKT VERBOTEN** im Frontend.
- **Lizenz:** MUSS auf Whitelist stehen (§3). Keine Ausnahmen ohne schriftliche Freigabe.
- **Bundle-Impact:** Frontend → `bundlephobia.com`-Check dokumentieren. Backend → C-Extensions nur mit technischer Begründung. Shopware → Admin-Bundles die Webpack brechen sind **untersagt**.

✅ `lucide-svelte` — MIT, 12k Stars, eigene Typen, 8kB gzip
❌ `fancy-icons-v0.2` — 45 Stars, keine Typen, letztes Release vor 18 Monaten

## 3. Lizenz-Whitelist

| Status | Lizenzen |
|---|---|
| ✅ **Freigegeben** | MIT, Apache-2.0, BSD-2-Clause, BSD-3-Clause, ISC |
| ⚠️ **Review erforderlich** | MPL-2.0 — Copyleft-Anteil prüfen, Legal-Team konsultieren |
| ❌ **VERBOTEN** | GPL, LGPL, AGPL in proprietären Projekten. Installation ist **STRIKT VERBOTEN**. |

## 4. Shopware-Kompatibilität

- **Composer-Pakete MÜSSEN** gegen den Shopware-Kernel getestet werden (`composer why` + `composer depends`).
- Pakete die Symfony-Versionen pinnen, die mit Shopware kollidieren, sind **VERBOTEN** — `composer update --dry-run` MUSS konfliktfrei durchlaufen.
- Vor Installation: `bin/console plugin:refresh` und `bin/build-administration.sh` MÜSSEN fehlerfrei bleiben.

✅ `symfony/mailer: ^6.4` — kompatibel mit Shopware 6.5+
❌ `symfony/http-kernel: ^7.0` — pinnt Major-Version, kollidiert mit Shopware 6.5.x

## 5. Stabilitäts-Garantie

- **Keine Pre-Releases:** Die Nutzung von Alpha-, Beta-, RC- oder Nightly-Builds ist strikt untersagt.
- **Proven Versions:** Nutze ausschließlich Versionen, die als "Stable" markiert sind. In kritischer Infrastruktur (DB, OS) sind LTS-Versionen bevorzugt.
- **N-1 Regel:** Bei extrem neuen Major-Releases im Zweifel die letzte stabile Version des vorherigen Major-Cycles wählen (`N-1`), sofern diese noch Sicherheitsupdates erhält.
- **Version Pinning:** Abhängigkeiten im Manifest auf **Minor-Version-Ebene** fixieren.

✅ `"svelte": "~5.1.0"` — pinnt auf Minor, Patches kommen automatisch
❌ `"svelte": "^5.0.0"` — erlaubt Minor-Sprünge, potentiell Breaking Changes
❌ `"svelte": "*"` — erlaubt alles, Build ist nicht reproduzierbar

## 6. Lock-File-Pflicht

- **Reproduzierbare Builds:** Jedes Projekt MUSS Lock-Files verwenden (`package-lock.json`, `poetry.lock`, `composer.lock`).
- Lock-Files werden **immer** committed — `.gitignore`-Einträge für Lock-Files sind **VERBOTEN**.
- CI/CD nutzt `npm ci` (nicht `npm install`) bzw. `pip install --no-deps -r requirements.txt`.

✅ `npm ci` — installiert exakt die Versionen aus dem Lock-File
❌ `npm install` — kann Lock-File überschreiben und andere Versionen ziehen

## 7. Dokumentationspflicht

Jede neue Dependency MUSS in `docs/dependencies.md` eingetragen werden:

```markdown
| Paket | Version | Begründung | Lizenz | Geprüfte Alternative |
|-------|---------|------------|--------|----------------------|
| zod   | ^3.23   | Schema-Validierung Frontend + Backend | MIT | yup (größeres Bundle) |
```

**Fehlende Dokumentation** → Dependency gilt als nicht freigegeben und MUSS entfernt werden.

## 8. Monitoring & Sunset-Erkennung

### Automatisierte Audits
- Nutze `npm audit`, `pip-audit` oder `Snyk` um Abhängigkeiten permanent auf bekannte CVEs zu prüfen.
- Audits laufen in der CI-Pipeline — ein `high`-Severity-Fund blockiert den Merge.

### Sunset-Kriterien
Eine Dependency gilt als **Sunset-Kandidat** wenn:
- Letztes Release > 12 Monate **ODER**
- < 50 GitHub-Stars **ODER**
- Kein TypeScript-Support bei Frontend-Paketen

→ **PFLICHT:** Aktiv nach Alternativen suchen und Migration planen.

### Abandoned-Package-Schwelle
Bibliotheken ohne Updates seit > 12 Monaten müssen evaluiert werden. > 24 Monate → automatische Entfernung per PR.

## 9. CVE-Response-Prozess

| Severity | Reaktionszeit | Aktion |
|----------|:---:|--------|
| 🔴 **Critical** (CVSS ≥ 9.0) | < 24h | Sofortiges Patch/Update, Hotfix-Branch, kein regulärer PR-Prozess |
| 🟠 **High** (CVSS 7.0-8.9) | < 72h | Priority-PR, Sprint-Unterbrechung erlaubt |
| 🟡 **Medium** (CVSS 4.0-6.9) | Nächster Sprint | Regulärer PR mit Test-Coverage |
| 🟢 **Low** (CVSS < 4.0) | Backlog | Bei nächstem Update mitziehen |

✅ `npm audit fix` nach jedem `npm ci` in der Pipeline
❌ `npm audit` Warnungen ignorieren weil "es funktioniert ja"

## 10. Kill-Kriterien

| Verstoß | Konsequenz |
|---------|------------|
| **GPL/AGPL in proprietärem Projekt** | Sofort entfernen, kein Merge |
| **Pre-Release / Nightly installiert** | Downgrade auf Stable, PR blockiert |
| **Kein Release seit > 24 Monaten** | Dependency ersetzen, PR erstellen |
| **Bundle-Size Frontend > 50 kB gzip** | Begründung + Architektur-Review PFLICHT |
| **Shopware Symfony-Konflikt** | Installation VERBOTEN bis Upstream-Fix |
| **Fehlender Eintrag in dependencies.md** | Merge blockiert bis Dokumentation vollständig |
| **< 50 Stars + untypisiert** | Eigenimplementierung bevorzugen |
| **Lock-File nicht committed** | PR blockiert — Build nicht reproduzierbar |
| **Critical CVE ignoriert (> 24h)** | Eskalation an Projektleitung |
