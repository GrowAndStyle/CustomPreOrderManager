# ADR-004: Autarkes Scarcity-System ohne Warteliste

**Status:** ENTSCHIEDEN  
**Datum:** 2026-09-14  
**Autor:** Antigravity (Principal Shopware Architect)  

## Kontext
Wenn das Zulaufkontingent eines Vorbestellartikels erschöpft ist (`sold_count >= inbound_stock`), muss die Storefront transparent reagieren. Gleichzeitig soll das Plugin autark bleiben und keine komplexen Wartelisten- oder Benachrichtigungsstrukturen verwalten.

## Entscheidung
Die Restmengenberechnung (`inbound_stock - sold_count`) erfolgt live im Template. Ist das Kontingent erschöpft, wird der Kaufen-Button deaktiviert und der Hinweis „Aktuell nicht vorbestellbar" eingeblendet. Es wird keine Warteliste implementiert.

## Begründung
1. **Autarkie-Garantie:** Das Plugin bleibt 100 % unabhängig von Fremdsystemen, zusätzlichen Tabellen oder Background-Workern.
2. **Datensparsamkeit (DSGVO):** Keine Speicherung unbestellter Kundendaten, keine Double-Opt-In-Prozesse, kein Löschkonzept für E-Mail-Wartelisten nötig.
3. **Kaufpsychologie & Klarheit:** Eindeutige Signalisierung der Nicht-Verfügbarkeit schützt vor Kundenfrust und Überbuchung.

## Abgelehnte Alternativen

| Alternative | Grund der Ablehnung |
|---|---|
| Eigene Wartelisten-Entity (`custom_preorder_waitlist`) | Erfordert DSGVO-Einwilligung, Verifikationstoken, periodische Notifier-Cronjobs und erhöht den Wartungsaufwand unverhältnismäßig. |
| Negativer Überverkauf erlauben | Führt zu ungedeckten Lieferzusagen und Kundenreklamationen bei begrenzten Zuläufen. |
