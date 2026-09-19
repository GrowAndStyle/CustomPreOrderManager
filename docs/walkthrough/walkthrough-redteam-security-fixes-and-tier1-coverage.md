# Walkthrough: Konsolidierte RedTeam-Security-Fixes & 100% Tier-1 Coverage (v1.4.2)

**Datum:** 19.09.2026  
**Betroffene Komponenten:** Checkout, Payment-Pipeline, Storefront Twig & SCSS, Administration ACL, Plugin-Lifecycle, Testsuite  
**Branch:** `fix/redteam-audit-fixes`  
**Letzter Commit:** `fc109d4` (`fix(storefront): Scarcity-Default-Bedingung vereinfacht und redundante Testsuite bereinigt`)  
**Test-Status:** 106 Tests, 751 Assertions, 100% Coverage (Classes: 9/9, Methods: 44/44, Lines: 313/313)  

---

## 1. Ziel & Ausgangslage

Nach zwei parallelen RedTeam-Audits (Flash & Claude Opus) wurden 10 konsolidierte Findings identifiziert. Das schwerwiegendste Finding war das **Quota-Spoofing** (SEC-2026-001) in Kombination mit Race Conditions beim simultanen Erst-Checkout (TOCTOU) und fehlendem CSRF-Schutz im Storefront-Listing.

Ziel dieses Releases (v1.4.2) war:
1. Vollständige Behebung aller 10 RedTeam-Findings nach Enterprise-Tier-1-Standard.
2. Einhaltung des Git-Blueprints (atomare Commits auf dediziertem Fix-Branch).
3. Erreichen von 100 % Code-Coverage auf allen Plugin-Klassen, Methoden und Zeilen zur Vorbereitung auf den finalen BlueTeam-Audit.

---

## 2. Technische Implementierung im Detail

### A. Checkout & Payment-Resilienz (Release-Blocker)

1. **[`PaymentStateSubscriber.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Core/Checkout/Subscriber/PaymentStateSubscriber.php):**
   * **Pessimistic Flagging (Set-then-Increment):** Beim Event `paid` wird das CustomField `custom_preorder_sold_count_applied` auf der Order VOR dem DBAL-Inkrement auf `true` gesetzt. Bei Timeouts oder Webhook-Retries wird die Order als bereits verarbeitet erkannt und ein Over-Selling verhindert.
   * **Revocation-Guard (Quota-Spoofing-Schutz):** Bei `cancelled` und `refunded` wird das CustomField geprüft. Nur wenn die Order zuvor nachweislich bezahlt war (`custom_preorder_sold_count_applied === true`), wird der Counter dekrementiert. Unbezahlte Abbrüche (`open -> cancelled`) können das Kontingent nicht mehr manipulieren. Doppelte Stornierungen (`refunded -> cancelled`) werden abgefangen.
   * **Webhook-Resilienz:** Sämtliche DBAL-Operationen sind in `try/catch (\Throwable)` gekapselt. Fehler werden im PSR-3-Logger protokolliert, anstatt HTTP 500 an externe Payment-Provider zurückzugeben.
   * **DBAL-Typisierung:** Explizite Angabe von `ParameterType::BINARY` und `ParameterType::INTEGER`.

2. **[`Migration1726200000AddPreOrderCustomFields.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Migration/Migration1726200000AddPreOrderCustomFields.php):**
   * Der Tag `Vorbestellung` wird bereits in der Migration idempotent angelegt.

3. **[`OrderPlacedSubscriber.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Core/Checkout/Subscriber/OrderPlacedSubscriber.php):**
   * Tag-Zuweisung und -Erstellung sind gegen TOCTOU-Race-Conditions abgesichert: Scheitert `create()` an einem Unique-Constraint zweier paralleler Checkouts, wird der Tag per `searchIds()` nachgeholt.

---

### B. Storefront-Security & XSS-Prävention

1. **[`action.html.twig`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/views/storefront/component/product/card/action.html.twig):**
   * Ergänzung von `{{ sw_csrf('frontend.checkout.line-item.add') }}` im Kaufen-Formular (verhindert HTTP 403 im `twig`-CSRF-Modus).
   * Saubere Twig-Map-Syntax `{ }` bei `replace`-Filtern für CSS-Variablen.

2. **[`buy-widget-form.html.twig`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/views/storefront/component/buy-widget/buy-widget-form.html.twig):**
   * Twig-Map-Syntax `{ }` korrigiert.

3. **[`base.html.twig`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/views/storefront/base.html.twig):**
   * `cardRadius` mit Standard-Twig `max(0, min(100, cardRadius|abs))` gehärtet (kein ungültiger Convenience-Filter).

---

### C. Core Business-Logik (Switch-Dilemma)

Das Switch-Dilemma wurde in allen relevanten Schichten konsistent behoben:
* Wenn `custom_preorder_active` explizit `false` ist, ist der Vorbestellmodus inaktiv – selbst wenn in der Datenbank noch ein historisches `release_date` hinterlegt ist.
* Einheitlich angepasst in:
  - [`PreOrderCartCollector.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Core/Checkout/Cart/PreOrderCartCollector.php)
  - [`delivery-information.html.twig`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/views/storefront/component/delivery-information.html.twig)
  - [`badges.html.twig`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/views/storefront/component/product/card/badges.html.twig)
  - [`box-standard.html.twig`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/views/storefront/component/product/card/box-standard.html.twig)
  - [`scarcity-badge.html.twig`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/views/storefront/component/preorder/scarcity-badge.html.twig)

---

### D. Admin-UI, Lifecycle & Metadaten

1. **[`index.js`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/app/administration/src/module/custom-preorder-manager/index.js):**
   * `privilege: 'order.viewer'` an Route-Meta und Navigation registriert (Berechtigungsprüfung für Admins ohne Super-Admin-Rechte).

2. **[`CustomPreOrderManager.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/CustomPreOrderManager.php):**
   * `uninstall()`-Transaktion mit `beginTransaction()`, `commit()` und `rollBack()` gekapselt.
   * `product_translation`-Bereinigung mit `JSON_CONTAINS_PATH` gefiltert, um unnötige Table-Locks zu vermeiden.

3. **Snippet-Bereinigung:**
   * 4 verwaiste JSON-Dateien direkt unter `src/Resources/snippet/` gelöscht. Nur noch die registrierten Ordner `de_DE/` und `en_GB/` verbleiben.

4. **[`composer.json`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/composer.json):**
   * `"php": "^8.1"` ergänzt.
   * Shopware-Constraint auf `"~6.5.8.0 || ^6.6.0 || ^6.7.0"` erweitert.

---

## 3. Tier-1 Test-Coverage Ausbau (100 %)

Nach dem initialen Fix-Lauf lag die Coverage bei 66,67 % Classes / 90,42 % Lines, da die neuen defensiven Catch-Blöcke und Resilienz-Pfade noch nicht isoliert getestet worden waren. Durch gezielte Unit-Tests wurde eine vollständige Abdeckung (100 %) erreicht:

* **[`PaymentStateSubscriberTest.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/tests/Unit/Subscriber/PaymentStateSubscriberTest.php):**
  * `testIsCounterAppliedReturnsTrueForNumericOne`: Typ-Normalisierung (`'1'`).
  * `testSetCounterAppliedLogsErrorOnExceptionWithoutThrowing`: Catch-Block bei Order-Flag Update.
  * `testIncrementSoldCountLogsErrorOnExceptionWithoutThrowing`: Catch-Block bei Inkrement.
  * `testDecrementSoldCountLogsErrorOnExceptionWithoutThrowing`: Catch-Block bei Dekrement.
  * `testAdjustSoldCountHandlesNullAndEmptyLineItems`: Null- & Empty-Collection Pfade.
  * `testAdjustSoldCountSkipsNonPreOrderItemsAndNullReferencedId`: Edge-Case Filterung.
  * `testOnPaymentRefundedIgnoresUnpaidOrder`: Unbezahlter Storno-Guard.
* **[`OrderPlacedSubscriberTest.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/tests/Unit/Subscriber/OrderPlacedSubscriberTest.php):**
  * `testOnOrderPlacedHandlesTagCreationCollisionGracefully`: TOCTOU Collision Retry.
  * `testOnOrderPlacedHandlesOrderUpdateExceptionGracefully`: Order-Update Catch-Block.
* **[`PluginUninstallTest.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/tests/Unit/Plugin/PluginUninstallTest.php):**
  * `testUninstallToleratesMissingProductCustomFieldsColumn`: Fallback in Shopware 6.5+.
  * `testUninstallRollsBackTransactionOnError`: Transaktions-Rollback bei DBAL-Fehler.

---

## 4. Vollständige Commit-Historie des Fix-Branches

Alle Änderungen wurden als atomare Conventional Commits auf dem Branch `fix/redteam-audit-fixes` festgehalten:

1. `30bc5b2` – `fix(checkout): Quota-Spoofing-Guard, Idempotenz-Flag und TOCTOU-Tag-Absicherung`
2. `1dd73cc` – `fix(storefront): CSRF-Schutz im Listing, Admin-ACL und Deinstallations-Transaktionen`
3. `06b2c6a` – `fix(core): Switch-Dilemma behoben, verwaiste Snippets entfernt und Composer-Constraints erweitert`
4. `f8b8903` – `test(subscriber): Unit-Tests für Idempotenz-Guard, Quota-Spoofing und Exception-Handling aktualisiert`
5. `d8832f2` – `docs(walkthrough): Phase 11 RedTeam Security Fixes v1.4.2 dokumentiert`
6. `a395941` – `fix(test): Integration-Subscriber-Event-Array, Snippet-Pfade und SCSS-Overlay-Radius korrigiert`
7. `d33f126` – `docs(walkthrough): Test-Anpassungen und Status für PHPUnit-Re-Run aktualisiert`
8. `21d7a47` – `test(coverage): Unit-Tests fuer Resilienz, DBAL-Catch-Bloecke und Lifecycle-Rollback ergaenzt`
9. `04d399d` – `fix(test): TypeError durch OrderEntity-Default-State fuer leere LineItems behoben`
10. `17cb4db` – `docs(walkthrough): Commit 04d399d fuer TypeError-Fix in Walkthrough erfasst`
11. `96cc9ec` – `docs(walkthrough): Phase 11 Walkthrough nach docs/walkthrough modularisiert und im Root-Index verlinkt`
12. `fc109d4` – `fix(storefront): Scarcity-Default-Bedingung vereinfacht und redundante Testsuite bereinigt`

---

## 5. Verifikations-Ergebnis auf Teststation

```text
OK (106 tests, 751 assertions)

Code Coverage Report:       
  2026-09-19 13:47:42       
                            
 Summary:                   
  Classes: 100.00% (9/9)    
  Methods: 100.00% (44/44)  
  Lines:   100.00% (313/313)

CustomPreOrderManager\Core\Checkout\Cart\Error\PreOrderQuantityAdjustedError
  Methods: 100.00% ( 6/ 6)   Lines: 100.00% ( 16/ 16)
CustomPreOrderManager\Core\Checkout\Cart\Error\PreOrderQuotaExhaustedError
  Methods: 100.00% ( 6/ 6)   Lines: 100.00% ( 12/ 12)
CustomPreOrderManager\Core\Checkout\Cart\PreOrderCartCollector
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% ( 39/ 39)
CustomPreOrderManager\Core\Checkout\Cart\PreOrderCartValidator
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% ( 24/ 24)
CustomPreOrderManager\Core\Checkout\Event\PreOrderPlacedEvent
  Methods: 100.00% ( 7/ 7)   Lines: 100.00% (  9/  9)
CustomPreOrderManager\Core\Checkout\Subscriber\OrderPlacedSubscriber
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 40/ 40)
CustomPreOrderManager\Core\Checkout\Subscriber\PaymentStateSubscriber
  Methods: 100.00% (11/11)   Lines: 100.00% (126/126)
CustomPreOrderManager\CustomPreOrderManager
  Methods: 100.00% ( 6/ 6)   Lines: 100.00% ( 35/ 35)
CustomPreOrderManager\DependencyInjection\CustomPreOrderManagerExtension
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 12/ 12)
```

---

## 6. BlueTeam Defensive Quality Audit (Opus) — 🟢 PASS

Am 19.09.2026 hat der BlueTeam-Agent (Claude Opus) den Branch `fix/redteam-audit-fixes` gegen **103 Kill-Criteria** und **11 ADRs** auditiert:

* **Ergebnis:** 🟢 **PASS — Release-fähig** (0 Critical, 0 High, 2 Medium, 2 Low/Info).
* **Kill-Criteria:** 95/103 bestanden, 8 nicht anwendbar (keine Custom API Routes / PII / Entities).
* **ADR-Compliance:** 11/11 vollständig konform.
* **Services & DI:** 100% Match zwischen `services.xml` und PHP-Konstruktoren.

### Nachgelagerte Optimierungen (sofort behoben)
1. **BT-001 (Medium):** Scarcity-Bedingung in [`delivery-information.html.twig`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/views/storefront/component/delivery-information.html.twig) vereinfacht auf `{% if scarcityMode == 'detail_only' or scarcityMode == 'everywhere' %}`, um ungespeicherte Default-Configs (`null`) strikt als `disabled` zu behandeln.
2. **BT-002 (Medium):** Veralteten doppelten Testordner `tests/Unit/Core/` entfernt; fehlenden Testfall `testCollectProcessesMultipleMixedLineItems` in [`PreOrderCartCollectorTest.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/tests/Unit/Cart/PreOrderCartCollectorTest.php) konsolidiert.

---

## 7. Finaler Status & Release-Freigabe

* **Sicherheit:** Alle 10 konsolidierten RedTeam-Findings (SEC-01 bis SEC-10) verifiziert und behoben.
* **Qualitäts-Audit:** BlueTeam Defensive Quality Audit mit 🟢 **PASS** bestanden.
* **Testsuite:** Bereinigt von Duplikaten, 100 % Coverage über alle 9 Klassen, 44 Methoden und 313 Zeilen.
* **Status:** **Bereit für v1.4.2 Release / Merge in `main`**.
