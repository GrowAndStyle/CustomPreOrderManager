---
name: load-masterplan-review
description: ZWINGEND bei Plan-Reviews, Code-Change-Reviews und Impact-Analysen. Systematische Prüfung von Masterplänen auf Vollständigkeit, Blast-Radius-Analyse bei Code-Änderungen und Edge-Case-Identifikation.
---

# Masterplan Review & Impact Analysis (Enterprise Standard 2026)

**SCOPE:** Plan-Audits, Blast-Radius-Checks, Edge-Case-Identifikation, Consistency-Prüfung
**PARADIGMA:** Unabhängiger Review — der Reviewer hat NICHT den gleichen Kontext wie der Plan-Ersteller

## 1. Grundprinzip

Der Projekt-Agent der einen Plan erstellt hat **Tunnelblick**: Er denkt in SEINEM Kontext, mit SEINEN Skills, über SEIN aktuelles Problem. Dieses Review existiert um die **blinden Flecken** systematisch aufzudecken die durch diesen Tunnelblick entstehen.

**Drei Review-Modi:**
1. **Plan-Review** — Masterplan vor der Umsetzung prüfen (§1–§3)
2. **Change-Review** — Einzelne Code-Änderung auf Blast-Radius prüfen (§4)
3. **Milestone-Review** — Nach Phase/Slice prüfen ob alles konsistent ist (§5)

---

## 2. Plan-Review: Strategische Vollständigkeit

### A. Pflicht-Checkliste (für JEDEN Masterplan)

| # | Prüfpunkt | Frage | Wenn fehlt |
|:-:|---|---|---|
| 1 | **Ziel klar definiert?** | Gibt es ein messbares Erfolgskriterium? Nicht "besser werden" sondern "Top 5 organisch in DE" | Plan hat keine Abbruchbedingung |
| 2 | **Dependency-Graph vollständig?** | Gibt es versteckte Abhängigkeiten die nicht im Graph stehen? (Server-Admin, externe APIs, manuelle Schritte) | Blocker wird zu spät entdeckt |
| 3 | **Rollback-Strategie?** | Was passiert wenn Phase X scheitert? Kann man zurück ohne Datenverlust? | Kein Weg zurück bei Fehler |
| 4 | **Daten-Migration?** | Gibt es bestehende Daten die transformiert/migriert werden müssen? | Datenverlust oder Korruption |
| 5 | **Externe Blocker identifiziert?** | Server-Admin, DNS, Drittanbieter-APIs, Lizenzen, Kundenfeedback? | Plan blockiert durch Externe |
| 6 | **Performance-Implikation?** | Neue DB-Queries pro Request? Event-Subscriber-Frequenz? Cache-Invalidierung? | Shop wird langsamer |
| 7 | **Monitoring/Logging?** | Wie wird gemessen ob der Plan funktioniert? Welche Metriken werden getrackt? | Erfolg nicht messbar |
| 8 | **Zeitschätzungen realistisch?** | Enthält die Schätzung Debugging-Puffer? Erste Implementierung eines Patterns dauert 2x so lang | Zeitplan platzt |
| 9 | **Skill-Coverage?** | Hat der Agent alle Skills die er für JEDEN Schritt braucht? | Agent improvisiert ohne Regeln |
| 10 | **Exit-Kriterien pro Phase?** | Gibt es klare Gates die bestanden werden müssen bevor die nächste Phase startet? | Phases verschwimmen |

### B. Plan-Typ-spezifische Checklisten

#### Shopware Plugin Plan

| Prüfpunkt | Frage |
|---|---|
| Plugin vs. App Entscheidung dokumentiert? | Braucht der Plan Server-Events die nur als Plugin möglich sind? |
| Lifecycle: install/update/uninstall definiert? | Wird `keepUserData()` respektiert? Was wird bei Deinstallation gelöscht? |
| Entity-Design zukunftssicher? | Können absehbare Erweiterungen ohne Breaking Migration hinzugefügt werden? |
| Admin-UI vollständig spezifiziert? | Jede Seite, jedes Feld, jeder Filter, jeder Button? |
| Caching-Strategie für Subscriber? | Wie oft feuern die Subscriber? Brauchen sie einen Cache-Layer? |
| Kompatibilitäts-Range definiert? | 6.5 + 6.6 + 6.7? Wurden 6.6/6.7-Breaking-Changes geprüft? |
| Testbarkeit? | Kann das Plugin auf dem Test-Server deployed und end-to-end getestet werden? |

#### SEO/URL-Migration Plan

| Prüfpunkt | Frage |
|---|---|
| ALLE bestehenden URLs erfasst? | Crawl-Daten? Search Console Export? Interne Verlinkungen geprüft? |
| Redirect-Ketten vermieden? | A→B und dann B→C? Wird das zu A→C aufgelöst? |
| Canonical-Tags angepasst? | Zeigen Canonicals nach der Migration auf die neuen URLs? |
| Interne Verlinkung aktualisiert? | Links in Content, Navigation, Footer, Sitemap — ALLE? |
| Sitemap neu generiert? | Alte URLs raus, neue URLs rein, an Google Search Console gemeldet? |
| Monitoring nach Migration? | 404-Rate tracken, Search Console Indexierung beobachten, Ranking-Veränderungen? |
| Staggered Rollout? | Alles auf einmal oder Kategorie für Kategorie? Was ist sicherer? |

#### Content/Kategorie-Restructuring Plan

| Prüfpunkt | Frage |
|---|---|
| Produktzuordnung vollständig? | Sind ALLE Produkte den neuen Kategorien zugeordnet? Keine Waisen? |
| Breadcrumb-Pfade korrekt? | Stimmen die Breadcrumbs nach Umhängung noch? |
| Cross-Selling/Upselling betroffen? | Referenzieren Cross-Selling-Regeln die alten Kategorien? |
| Filter/Facetten angepasst? | Stimmen die Property-Filter in den neuen Kategorien? |
| CMS-Layouts zugewiesen? | Hat jede neue/umbenannte Kategorie ein CMS-Layout? |

---

## 3. Plan-Review: Edge-Case-Jagd

**Prinzip:** Für JEDE Komponente im Plan systematisch die folgenden Szenarien durchgehen.

### A. Daten-Edge-Cases

| Szenario | Frage |
|---|---|
| **Leerer Zustand** | Was passiert wenn die Tabelle/Liste leer ist? Zeigt die UI einen sinnvollen Empty-State? |
| **Einzelner Eintrag** | Funktioniert Pagination/Sorting mit nur 1 Eintrag? |
| **Massendaten** | Was bei 10.000+ Einträgen? Wird paginiert? Timeout? |
| **Sonderzeichen in URLs** | Umlaute (ä/ö/ü), Leerzeichen (%20), Fragezeichen, Hash, Unicode? |
| **Duplikate** | Was wenn der gleiche Eintrag doppelt angelegt wird? DB-Constraint? UI-Feedback? |
| **Null/Undefined** | Optionale Felder die null sind — crashed die UI? Crashed der Service? |
| **Maximale Feldlänge** | VARCHAR(2048) — was wenn jemand eine 3000-Zeichen-URL eingibt? |
| **Encoding** | UTF-8 ohne BOM? Was wenn eine CSV mit BOM oder Latin-1 importiert wird? |

### B. Timing/Race-Condition Edge-Cases

| Szenario | Frage |
|---|---|
| **Gleichzeitige Änderung** | Zwei Admins bearbeiten den gleichen Redirect gleichzeitig — Last Write Wins? Conflict Detection? |
| **Event-Reihenfolge** | Subscriber A hängt von Subscriber B ab — ist die Reihenfolge garantiert? Priority gesetzt? |
| **Cache-Invalidierung** | Nach Redirect-Änderung: wird der HTTP-Cache sofort invalidiert oder erst beim nächsten Purge? |
| **Migration während Betrieb** | Plugin-Update mit Schema-Änderung während der Shop live ist — Downtime? |

### C. Fehler-Pfad Edge-Cases

| Szenario | Frage |
|---|---|
| **DB nicht erreichbar** | Subscriber kann Redirect nicht auflösen — 500 oder Fallback auf 404? |
| **Zirkulärer Redirect** | A→B→C→A — wird das erkannt bevor der Browser in einer Endlosschleife hängt? Max-Hops? |
| **Ungültige Ziel-URL** | Redirect zeigt auf eine URL die selbst 404 liefert — wird das erkannt? |
| **Plugin deaktiviert** | Was passiert mit bestehenden Redirects wenn das Plugin deaktiviert wird? Alle 404? |
| **CSV mit fehlerhaften Zeilen** | 50 korrekte + 3 fehlerhafte Zeilen — Import der korrekten, Fehler in Response? Oder alles-oder-nichts? |

---

## 4. Plan-Review: Performance & Security Red-Team

### A. Performance-Checkliste

| Frage | Risiko wenn nicht adressiert |
|---|---|
| Wie viele DB-Queries erzeugt ein normaler Page-Load NACH der Implementierung? | Shop wird spürbar langsamer |
| Feuert ein Subscriber auf JEDEM Request oder nur auf relevanten? | Unnötige CPU-Last |
| Gibt es einen Cache-Layer für häufig abgefragte Daten? | Redundante DB-Hits |
| Werden Indices auf den richtigen Spalten gesetzt? | Full Table Scans bei großen Tabellen |
| Batch-Operationen (Import) — wird in Chunks verarbeitet oder alles in einem Query? | Memory-Overflow bei großen Imports |
| Event-Subscriber Priorität korrekt? | Subscriber blockiert andere wichtige Events |

### B. Security Red-Team

| Angriffsszenario | Frage |
|---|---|
| **Open Redirect** | Kann ein Angreifer `target_url` auf `https://evil.com` setzen und dann die `source_url` verbreiten? |
| **Header Injection** | Kann `source_url` Newlines/CRLF enthalten die HTTP-Header injizieren? |
| **Path Traversal** | Kann `source_url` `../` enthalten um aus dem URL-Namespace auszubrechen? |
| **ReDoS** | Falls Regex-Matching geplant ist: sind die Patterns safe gegen Catastrophic Backtracking? |
| **Admin-Auth-Bypass** | Sind ALLE Admin-Routen durch den Symfony Firewall geschützt? Keine versehentlich öffentliche Route? |
| **Mass-Assignment** | Kann ein Admin über die API Felder setzen die nicht gesetzt werden dürfen (z.B. `hit_count` manipulieren)? |
| **CSV Injection** | Kann eine importierte CSV Formeln enthalten (`=CMD(...)`) die bei Export in Excel ausgeführt werden? |

---

## 5. Change-Review: Blast-Radius-Analyse (KRITISCH)

> **KERNREGEL:** Wenn eine Logik an Stelle X geändert wird, MUSS systematisch geprüft werden ob die GLEICHE Logik auch an den Stellen Y, Z, ... existiert und dort ebenfalls angepasst werden muss.

### A. Der Blast-Radius-Algorithmus

Bei JEDER Code-Änderung diese Schritte durchgehen:

```
Schritt 1: WAS wurde geändert?
  → Query, Business-Regel, UI-Pattern, Entity-Feld, API-Contract, Event?

Schritt 2: WO wird das GLEICHE Pattern noch verwendet?
  → grep/ripgrep über die GESAMTE Codebase
  → Nicht nur im gleichen Plugin — auch in anderen Plugins und im Theme

Schritt 3: Für JEDE gefundene Stelle:
  → Muss diese Stelle die gleiche Änderung bekommen?
  → Wenn NEIN: dokumentieren WARUM nicht (bewusste Abweichung)
  → Wenn JA: in den Task aufnehmen und MIT-ändern

Schritt 4: Gibt es INDIREKTE Abhängigkeiten?
  → Cached jemand das Ergebnis dieser Logik?
  → Gibt es einen Event-Subscriber der auf Änderungen dieser Entity reagiert?
  → Gibt es Mail-Templates die Felder dieser Entity verwenden?
  → Gibt es Admin-Views die diese Daten anzeigen?
```

### B. Häufige Blast-Radius-Muster

| Änderung | Wo noch prüfen (typische Stellen die vergessen werden) |
|---|---|
| **Query geändert** (z.B. Widerruf-Abfrage) | ALLE Stellen die die gleiche Entity/Tabelle abfragen: Controller, Services, Subscribers, Admin-API, Store-API, Scheduled Tasks, Mail-Templates |
| **Entity-Feld hinzugefügt/geändert** | Migration, Entity-Definition, Admin-Detail-Seite, Admin-List-Spalten, API-Response, Mail-Template-Variablen, CSV-Export |
| **Status-Enum erweitert** | Status-Badge-Mapping in Admin, Flow-Builder Event-Routing, Mail-Template-Conditions, Filter-Dropdowns, Statistik-Dashboards |
| **URL-Pfad geändert** | Interne Links, Breadcrumbs, Sitemap, robots.txt, Canonical-Tags, Social-Media-Shares, externe Verlinkungen, Google Ads |
| **CSS-Klasse geändert** | ALLE Templates die diese Klasse verwenden, JS das via querySelector darauf zugreift, E2E-Tests die darauf selektieren |
| **Event umbenannt/entfernt** | ALLE Subscriber die auf dieses Event hören, Flow-Builder Konfigurationen im Admin, Dokumentation |
| **Datenbank-Schema geändert** | Alle DAL-Queries die die Spalte verwenden, Admin-UI Felder, Import/Export Profile, Custom API-Endpoints |
| **Permission/ACL geändert** | Alle Admin-Routen die diese Permission prüfen, alle Admin-UI Elemente die per `v-if="acl.can()"` gesteuert werden |

### C. Blast-Radius-Checkliste (Vor jedem Commit)

```
□ Habe ich nach dem geänderten Pattern/Query/Klasse in der GESAMTEN Codebase gesucht?
□ Habe ich nicht nur im aktuellen Plugin gesucht, sondern auch im Theme und anderen Plugins?
□ Habe ich Admin-Templates UND Storefront-Templates geprüft?
□ Habe ich Mail-Templates geprüft die Variablen der geänderten Entity verwenden?
□ Habe ich Flow-Builder Konfigurationen geprüft?
□ Habe ich Cache-Invalidierung geprüft?
□ Wenn ich eine Stelle bewusst NICHT angepasst habe: steht im Commit-Message WARUM?
```

### D. Beispiel: Das Widerrufs-Problem

```
Änderung: SQL-Query für Widerrufe im FloatButton angepasst
         (z.B. neues WHERE-Kriterium, JOIN geändert)

Blast-Radius-Suche:
1. grep -r "Widerruf\|cancellation\|revocation" --include="*.php" --include="*.twig" --include="*.js"
2. Treffer:
   ✅ FloatButton Subscriber (GEÄNDERT)
   ❌ Bestellhistorie Controller (VERGESSEN — gleiche Query, altes WHERE)
   ❌ Admin Inquiry-List (VERGESSEN — zeigt Widerrufe mit altem Filter)
   ❌ Flow Builder Event (OK — anderer Codepfad, kein Query)
   ❌ Mail-Template (OK — nutzt Entity-Variablen, nicht die Query)

Ergebnis: 2 Stellen vergessen → Task erweitern
```

---

## 6. Milestone-Review: Konsistenz-Check nach Implementierung

### A. Nach jedem Slice/Phase

| Prüfpunkt | Aktion |
|---|---|
| **Alle DoD-Punkte erfüllt?** | Jeder Checkbox-Punkt einzeln verifizieren — nicht "sieht gut aus" |
| **Walkthrough aktuell?** | Dokumentiert das Walkthrough was tatsächlich gebaut wurde (nicht was geplant war)? |
| **Task.md synchron?** | Stimmen die Task-Status mit dem tatsächlichen Code überein? |
| **Abweichungen vom Plan?** | Wurde etwas anders implementiert als geplant? Wenn ja: ADR Nachtrag oder Plan-Update? |
| **Neue Edge-Cases entdeckt?** | Während der Implementierung tauchen IMMER neue Edge-Cases auf — sind sie dokumentiert? |
| **Technische Schulden?** | Wurde etwas "quick and dirty" gelöst mit dem Vorsatz "mache ich später richtig"? TODO dokumentiert? |

### B. Cross-Component Konsistenz

| Prüfpunkt | Was verglichen wird |
|---|---|
| **Entity vs. Admin-UI** | Hat jedes Entity-Feld ein entsprechendes Admin-UI-Element? |
| **Entity vs. Migration** | Stimmt das Schema in der Migration mit der Entity-Definition überein? |
| **Services.xml vs. PHP** | Sind alle Services/Subscriber registriert? Keine toten Registrierungen? |
| **Snippets vs. Templates** | Werden alle referenzierten Snippet-Keys auch definiert? Keine Missing Keys? |
| **Routes.xml vs. Controller** | Stimmen die registrierten Routes mit den Controller-Methoden überein? |
| **Composer.json vs. Code** | Werden alle Dependencies die `use`'d werden auch in composer.json stehen? |

---

## 7. Review-Output-Format

Jeder Review produziert einen strukturierten Report:

```markdown
# Plan-Review: [Planname]

## Gesamtbewertung: 🟢 Freigabe / 🟡 Nacharbeit / 🔴 Grundlegende Überarbeitung

## Stärken
- [Was gut ist und warum]

## Kritische Findings (🔴 müssen vor Freigabe gelöst werden)
- [Finding + Impact + Empfehlung]

## Wichtige Findings (🟡 sollten adressiert werden)
- [Finding + Impact + Empfehlung]

## Edge-Cases (identifiziert, Agent muss adressieren)
- [Edge-Case + Szenario + Empfehlung]

## Performance-Risiken
- [Risiko + Impact + Mitigation]

## Security-Risiken
- [Risiko + Angriffsvektor + Mitigation]

## Blast-Radius-Checks (bei Code-Changes)
- [Geänderte Stelle → Weitere betroffene Stellen → Status]

## Empfehlung an den User
- [Klare Handlungsempfehlung in nicht-technischer Sprache]
```

---

## 8. Kill-Kriterien

| Verstoß | Konsequenz |
|---|---|
| Plan freigegeben ohne Review-Checkliste durchlaufen | Freigabe zurückziehen |
| Code-Änderung ohne Blast-Radius-Suche committed | Rollback bis Blast-Radius geprüft |
| Edge-Case identifiziert aber nicht dokumentiert | Task erstellen bevor weitergearbeitet wird |
| Abweichung vom Plan ohne ADR-Nachtrag | Implementation pausieren bis dokumentiert |
| Review-Finding als "nicht relevant" abgetan ohne Begründung | Finding bleibt offen |
| Zeitschätzung ohne Debugging-Puffer (Faktor 1.5x) | Schätzung korrigieren |
