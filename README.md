# CustomPreOrderManager (Shopware 6 Plugin)

Autarkes High-Conversion Vorbestellungs-System für Shopware 6.5.x Community Edition (aufwärtskompatibel zu 6.6 und 6.7).

## Übersicht & Kernfunktionen

1. **Produkt-Steuerung:** Pflege von Erscheinungsdatum, Hinweistext und Zulauf-Kontingent (`inbound_stock`) direkt am Produkt via CustomFields (`custom_preorder_set`).
2. **Storefront UX:** Dynamischer „Jetzt vorbestellen"-Button, badges auf Kategorieseiten und PDP, transparente Warenkorb-Hinweise.
3. **Scarcity Engine:** Dringlichkeits-Hinweise („Nur noch X Stück!"), per Plugin-Konfiguration steuerbar.
4. **Automatischer Stock-Lifecycle:** Physischer Lagerbestand (`stock > 0`) deaktiviert den Vorbestell-Modus automatisch und schaltet auf Normalverkauf um.
5. **Backend Auto-Tagging:** Automatisches Zuweisen des Shopware-Tags `Vorbestellung` an Bestellungen mit Vorbestell-Artikeln.
6. **Kontingent-Sperre:** Bei erschöpftem Kontingent (`inbound_stock - sold_count <= 0`) wird der Kauf-Button gesperrt und der Hinweistext „Aktuell nicht vorbestellbar" angezeigt. Keine externe Warteliste.
7. **Flow Builder Business Event:** Bereitstellung von `preorder.order.placed` (`PreOrderPlacedEvent`) für automatisierte Workflows und VIP-Kundenbenachrichtigungen.

---

## Technische Spezifikationen

* **Kompatibilität:** Shopware ~6.5.8.0 || ^6.6.0 || ^6.7.0
* **PHP-Version:** PHP 8.1+
* **Composer Package:** `growandstyle/custom-pre-order-manager`
* **Plugin-Klasse:** `CustomPreOrderManager\CustomPreOrderManager`
* **Tabellen- / CustomField-Prefix:** `custom_` / `custom_preorder_`

---

## Konfigurations-Optionen

Konfigurierbar unter *Einstellungen > Erweiterungen > PreOrderManager*:

| Konfigurations-Schlüssel | Typ | Standard | Beschreibung |
|---|---|:---:|---|
| `CustomPreOrderManager.config.enableListingBadge` | `bool` | `true` | Zeigt das Vorbestell-Badge auf Listing-Cards und in der Suche an. |
| `CustomPreOrderManager.config.enableScarcityCounter` | `bool` | `true` | Aktiviert den Restmengen-Zähler auf der Produktdetailseite. |
| `CustomPreOrderManager.config.lowStockThreshold` | `int` | `5` | Schwellenwert für das Dringlichkeits-Badge („Fast vergriffen"). |

---

## Architektur & Datenmodell

* **CustomFields statt eigener Tabelle:** Verhindert Schema-Locks auf der `product`-Tabelle und vererbt Einstellungen automatisch an Produktvarianten.
* **Atomare DBAL-Inkrementierung:** Parallele Checkouts aktualisieren `custom_preorder_sold_count` atomar im `WHERE version_id = Defaults::LIVE_VERSION` Block ohne Race-Conditions.
* **LineItem-Payload Enrichment:** `PreOrderCartCollector` (Priorität `4100`) heftet Vorbestell-Metadaten direkt an die Position. Standard-Mail-Templates (`order.placed`) und Belege bleiben unverändert.

---

## Testing & Qualitätssicherung

Das Plugin wird über eine automatisierte PHPUnit-Testsuite nach Shopware Enterprise Tier-1 Standard verifiziert:

* **Struktur:** Saubere Trennung in `tests/Unit/` (isolierte Service- und Logik-Tests mit Mocks) und `tests/Integration/` (Container- und Kernel-Tests mit echten Repositories).
* **Testumfang:** 34 automatisierte Tests mit 80 Assertions.
* **Code Coverage (Dockware Teststation):**
  * **Classes:** 100.00% (5/5)
  * **Methods:** 100.00% (20/20)
  * **Lines:** 100.00% (91/91)
* **Validierung:** Alle XML- und JSON-Dateien werden bei jedem Testlauf automatisch auf Schemakonformität geprüft.

---

## Build & Release

Release-Pakete werden standardmäßig via `shopware-cli` gebaut:

```bash
# Release-ZIP aus Git-Tag erstellen
shopware-cli extension zip . --output-directory dist/
```
