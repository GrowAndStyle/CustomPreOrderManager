---
name: load-blueprint-dsgvo-compliance
description: ZWINGEND bei Verarbeitung personenbezogener Daten! Erzwingt DSGVO-konforme Datenverarbeitung, Speicherfristen, Löschkonzepte, PII-Minimierung und Datenschutz-by-Design in allen Projekten.
---

# DSGVO / GDPR Compliance (Enterprise Standard 2026)

**SCOPE:** Personenbezogene Daten in allen Projekten — Shopware-Plugins, Backend-Services, Logging, APIs
**PARADIGMA:** Privacy-by-Design, Datenminimierung, Zweckbindung
**RECHTSGRUNDLAGE:** DSGVO (EU) 2016/679, BDSG-neu, TTDSG

---

## 1. Grundprinzipien (IMMER aktiv)

> **KERNREGEL: Speichere nur das absolute Minimum an personenbezogenen Daten, so kurz wie möglich, so sicher wie nötig.**

| Prinzip | Bedeutung | Praxisbeispiel |
|---------|-----------|---------------|
| **Datenminimierung** (Art. 5c) | Nur Daten erfassen die für den Zweck nötig sind | 404-Log: Browser-Familie statt vollständiger User-Agent |
| **Zweckbindung** (Art. 5b) | Daten nur für den erhobenen Zweck nutzen | Widerrufs-E-Mail nicht für Marketing verwenden |
| **Speicherbegrenzung** (Art. 5e) | Löschfristen definieren und automatisch durchsetzen | 404-Logs nach 30 Tagen, Inquiry-Daten nach 6 Monaten |
| **Integrität & Vertraulichkeit** (Art. 5f) | Verschlüsselung, Zugriffsschutz, Audit-Trail | ACL auf Kundendaten-Endpoints, TLS für Übertragung |

---

## 2. PII-Klassifikation

### A. Was ist PII (Personally Identifiable Information)?

| Kategorie | Beispiele | Sensitivität |
|-----------|----------|:------------:|
| **Direkt identifizierend** | Name, E-Mail, Telefon, Adresse | 🔴 HOCH |
| **Indirekt identifizierend** | IP-Adresse, User-Agent, Geräte-ID, Session-ID | 🟠 MITTEL |
| **Finanziell** | IBAN, Kreditkartennummer, Bestellwert | 🔴 HOCH |
| **Verhaltensbezogen** | Suchbegriffe im Referer, Klickpfade, Warenkorbinhalt | 🟡 NIEDRIG–MITTEL |
| **Rechtlich** | Widerrufs-Grund, Beschwerdetext | 🟠 MITTEL |

### B. PII-Entscheidungsbaum

```
Wird ein personenbezogenes Datum gespeichert?
├─ NEIN → Kein Handlungsbedarf
└─ JA → Ist es für den Zweck ZWINGEND nötig?
   ├─ NEIN → NICHT speichern (Datenminimierung)
   └─ JA → Kann es minimiert/pseudonymisiert werden?
      ├─ JA → Minimieren (z.B. User-Agent → Browser-Familie)
      └─ NEIN → Speichern MIT:
         ├─ Löschfrist definieren
         ├─ Zugriff per ACL einschränken
         ├─ Logging OHNE PII (maskiert)
         └─ Dokumentation im Plugin/Service
```

---

## 3. PII in Logs und Monitoring (KRITISCH)

> **NIEMALS personenbezogene Daten im Klartext in Logs schreiben.**

```php
// ✅ KORREKT: PII maskiert
$this->logger->info('Widerruf eingereicht', [
    'customerId' => substr($customerId, 0, 8) . '***',
    'orderNumber' => $orderNumber,  // Bestellnummer ist OK (keine PII)
]);

// ❌ VERBOTEN: PII im Klartext
$this->logger->info('Widerruf eingereicht', [
    'email' => $customer->getEmail(),      // PII!
    'name' => $customer->getFirstName(),    // PII!
    'userAgent' => $request->headers->get('User-Agent'),  // Indirekt PII!
]);
```

### Maskierungs-Regeln

| Datentyp | Maskierung | Beispiel |
|----------|-----------|---------|
| E-Mail | Lokalteil maskieren | `n***@gmail.com` |
| Name | Erster Buchstabe + `***` | `M***` |
| IP-Adresse | Letztes Oktett nullen | `192.168.2.0` |
| User-Agent | Nur Browser-Familie + Major-Version | `Chrome/125` |
| Referer | Nur Pfad, keine Query-Parameter | `/kategorie/erde/` |
| UUID/ID | Erste 8 Zeichen | `a1b2c3d4***` |

---

## 4. Speicherfristen und Löschkonzepte (PFLICHT)

### A. Standard-Fristen

| Datentyp | Frist | Begründung | Umsetzung |
|----------|-------|-----------|-----------|
| **404-Logs** (URL, User-Agent, Referer) | 30–90 Tage | Monitoring-Zweck erfüllt | ScheduledTask mit `retentionDays` Config |
| **Anfragen/Inquiries** (Name, E-Mail, Nachricht) | 6 Monate nach Abschluss | Beweispflicht bei Streitigkeiten | ScheduledTask oder Admin-Action |
| **Widerrufs-Daten** | 3 Jahre (BGB §195) | Gesetzliche Aufbewahrungspflicht | Kein automatisches Löschen |
| **Rechnungen/Bestellungen** | 10 Jahre (HGB §257) | Steuerliche Aufbewahrungspflicht | Kein automatisches Löschen |
| **Session-Daten** | 24 Stunden | Technische Notwendigkeit | TTL im Session-Store |

### B. Umsetzung in Shopware-Plugins

```php
// Jedes Plugin das zeitbezogene PII speichert MUSS:
// 1. Eine konfigurierbare Löschfrist in config.xml haben
// 2. Einen ScheduledTask für die automatische Bereinigung implementieren
// 3. In der Plugin-Doku die Speicherfrist dokumentieren

// config.xml
<input-field type="int">
    <name>retentionDays</name>
    <label>Speicherfrist (Tage)</label>
    <label lang="de-DE">Speicherfrist (Tage)</label>
    <helpText>Nach dieser Frist werden Einträge automatisch gelöscht.</helpText>
    <defaultValue>90</defaultValue>
</input-field>
```

---

## 5. Recht auf Löschung (Art. 17 DSGVO)

### A. Plugin-Uninstall

Wenn der Shopbetreiber das Plugin deinstalliert und `keepUserData === false` wählt:

```php
public function uninstall(UninstallContext $context): void
{
    if (!$context->keepUserData()) {
        // Custom Tables MÜSSEN gelöscht werden
        $this->connection->executeStatement('DROP TABLE IF EXISTS `custom_redirect_rule`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `custom_404_log`');

        // Custom Fields entfernen
        // Plugin-Config löschen

        // NIEMALS löschen:
        // - Bestellungen
        // - Rechnungen/Dokumente
        // - Kundendaten (gehören Shopware, nicht dem Plugin)
    }
}
```

### B. Kunden-Löschanfragen

Wenn ein Kunde sein Konto löscht oder Löschung beanfragt:
- Alle Plugin-eigenen Daten die dem Kunden zugeordnet sind MÜSSEN gelöscht werden
- Registriere einen Subscriber auf `CustomerDeletedEvent` falls das Plugin kundenbezogene Daten speichert
- **Ausnahme:** Daten die gesetzlichen Aufbewahrungspflichten unterliegen → Anonymisieren statt Löschen

---

## 6. Datenschutz-by-Design Checkliste

Vor jedem Feature das Daten speichert:

- [ ] Welche personenbezogenen Daten werden gespeichert?
- [ ] Ist jedes Datum für den Zweck ZWINGEND nötig? (Datenminimierung)
- [ ] Kann das Datum minimiert werden? (User-Agent → Browser-Familie)
- [ ] Ist eine Löschfrist definiert? (Config + ScheduledTask)
- [ ] Sind Logs PII-frei oder maskiert?
- [ ] Ist der Zugriff per ACL eingeschränkt?
- [ ] Was passiert bei Plugin-Deinstallation?
- [ ] Was passiert bei Kunden-Löschanfrage?
- [ ] Ist die Speicherung in der Plugin-Doku dokumentiert?

---

## 7. Shopware-spezifische DSGVO-Regeln

### A. Entity-Definitionen mit PII

```php
// Felder mit PII MÜSSEN:
// 1. KEIN ApiAware-Flag haben (nicht über Store-API abrufbar)
// 2. Im Admin nur für berechtigte Rollen sichtbar sein (ACL)
// 3. In der Entity-Doku als PII markiert sein

// ✅ KORREKT: PII-Felder ohne ApiAware
(new StringField('customer_email', 'customerEmail'))
    ->addFlags(new Required()),  // Kein ApiAware!

// ❌ VERBOTEN: PII über Store-API exponiert
(new StringField('customer_email', 'customerEmail'))
    ->addFlags(new Required(), new ApiAware()),  // PII-Leak!
```

### B. Flow Builder Events mit PII

Events die PII enthalten (E-Mail, Name, Adresse) dürfen im Flow Builder NUR für:
- E-Mail-Versand an den Kunden selbst
- Interne Admin-Benachrichtigungen

NICHT für:
- Webhook-Calls an externe Services (ohne Auftragsverarbeitung)
- Log-Entries mit PII im Klartext

---

## 8. Kill-Kriterien

| Verstoß | Konsequenz |
|---|---|
| PII im Klartext in Logs | Sofort maskieren — DSGVO Art. 5f Verstoß |
| Keine Löschfrist für zeitbezogene PII-Daten | Frist definieren + ScheduledTask implementieren |
| PII-Feld mit `ApiAware`-Flag | Flag entfernen — Daten-Leak über Store-API |
| Plugin-Uninstall ohne Daten-Cleanup bei `keepUserData === false` | Cleanup implementieren |
| User-Agent/Referer ungekürzt in DB | Minimieren: Browser-Familie, Pfad ohne Query |
| Kundenbezogene Daten ohne `CustomerDeletedEvent`-Subscriber | Subscriber registrieren |
| Fehlende Datenschutz-Dokumentation bei PII-Speicherung | Dokumentation ergänzen |
