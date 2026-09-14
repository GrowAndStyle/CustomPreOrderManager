# ADR-002: Atomares DBAL JSON-Counter-Inkrement

**Status:** ENTSCHIEDEN  
**Datum:** 2026-09-14  
**Autor:** Antigravity (Principal Shopware Architect)  

## Kontext
Bei hohem Bestellaufkommen (Hype-Drops, limitierte Vorbestellungs-Kontingente) kaufen mehrere Kunden zeitgleich dasselbe Vorbestellungsprodukt. Die verkaufte Menge (`custom_preorder_sold_count`) muss exakt gezählt werden, um ein Überbuchen des Kontingents zuverlässig zu verhindern.

## Entscheidung
Wir aktualisieren den Zähler `custom_preorder_sold_count` ausschließlich über ein atomares DBAL-Statement (`JSON_SET` in Verbindung mit `JSON_EXTRACT`), das direkt auf die Live-Version (`WHERE id = :id AND version_id = :versionId`) abzielt.

## Begründung
1. **Concurrency & Race-Condition-Schutz:** Ein DAL-Read-Modify-Write (`search` gefolgt von `update`) führt bei gleichzeitigen Schreibvorgängen zu Lost Updates. Das DBAL-Statement delegiert das atomare Inkrementieren an die Datenbank-Engine (Row-Locking).
2. **Versions-Sicherheit:** Die `product`-Tabelle ist versioniert. Die strikte Eingrenzung auf `Defaults::LIVE_VERSION` verhindert Korruption von Draft- oder Archiv-Versionen.

## Abgelehnte Alternativen

| Alternative | Grund der Ablehnung |
|---|---|
| DAL-Repository `update()` | Anfällig für Race-Conditions bei parallelen Checkouts; kein atomares JSON-Increment im Shopware DAL verfügbar. |
| DBAL-Update ohne `version_id` | Trifft alle Versionen (Live, Drafts, Parent/Children) und führt zu Dateninkonsistenzen. |
