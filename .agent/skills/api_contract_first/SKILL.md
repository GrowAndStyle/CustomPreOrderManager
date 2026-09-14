---
name: load-blueprint-api-contract-first
description: KRITISCH! Erzwingt die vollständige API-Kontraktdefinition VOR jeder Endpoint-Implementierung. Laden bei Projektstart, neuen Features mit API-Endpunkten oder Schema-Änderungen an bestehenden Schnittstellen.
---

# PLANNING BLUEPRINT: API Contract-First (Enterprise Standard 2026)

**SCOPE:** Alle REST-API-Endpunkte (Shopware Store-API, FastAPI Companion, Frontend-Konsumenten) | **PARADIGMA:** Schema-First — kein Code ohne Kontrakt

## 1. Contract-First Pflicht

- **Reihenfolge ist PFLICHT:** Schema-Definition → Review → Mock → Implementierung → Validierung gegen Schema. Implementierung ohne vorherigen Kontrakt ist **STRIKT VERBOTEN**.
- **Schema-Formate:** Shopware-Plugin: PHP-Attribute + Request/Response-DTOs. Companion: Pydantic-Modelle → OpenAPI 3.1. Frontend: Zod-Schemas.
- **Kontrakt-Artefakt:** Jeder Endpoint MUSS eine Datei `docs/api-contracts/<entity>.<method>.yaml` besitzen, bevor ein Controller erstellt wird.

## 2. Shopware Store-API Kontrakt

- **Route-Definition:** `#[Route(path: '/store-api/grow-and-style/{entity}', name: 'store-api.gas.{entity}.{action}', methods: ['GET'])]` — MUSS zwingend vor Controller-Logik definiert sein.
- **Request-DTO:** Eigene Klasse `<Entity>ListRequest extends StoreApiRequest` mit validierten Properties. Inline-Array-Zugriff auf `$request->request->get(...)` ist **untersagt**.
- **Response-Struktur:** `<Entity>ListResponse extends StoreApiResponse` mit typisiertem `EntitySearchResult`. Rohes `JsonResponse` zurückgeben ist **STRIKT VERBOTEN**.

## 3. FastAPI Companion Kontrakt

- **Pydantic-First:** Jedes Response-Modell MUSS als `BaseModel`-Subklasse existieren, bevor der Router-Endpoint geschrieben wird. `dict`-Returns sind **verboten**.
- **OpenAPI-Generierung:** Schema wird automatisch aus Pydantic generiert — manuelle OpenAPI-YAML-Pflege ist untersagt.
- **Zod-Parität:** Das Frontend-Zod-Schema MUSS strukturell identisch zum Pydantic-Modell sein. Abweichungen werden als **Kill-Kriterium** gewertet.

## 4. Error-Contract

- **Standard-Schema:** `{ "error_code": "GAS-XXXX", "message": "<user-facing>", "details": null | object }` — gilt für alle APIs einheitlich.
- **Shopware-Isolation:** Stack-Traces, interne Shopware-Fehlermeldungen oder Entity-IDs NIEMALS an das Frontend durchreichen. Externe Fehler werden auf opake `GAS-5000`-Codes gemappt.
- **HTTP-Status:** `400` Validierung, `401` Auth, `403` Berechtigung, `404` Ressource, `409` Konflikt, `500` nur für unerwartete Fehler. Wildcard-`500` ohne Mapping ist **verboten**.

## 5. Mock-Strategie

| Schicht | Strategie | Verboten |
|---|---|---|
| **Shopware Plugin** | PHPUnit-Fixtures in `tests/fixtures/`, `EntityLoader` für Testdaten | Produktiv-DB in Tests nutzen |
| **Companion** | `MOCK_MODE=True` → `mock_data.py` liefert statische Responses | Mocks in Produktiv-Config |
| **Frontend** | MSW-Handler oder statische JSON in `__mocks__/api/` | Echte API-Calls in Unit-Tests |

## 6. Endpoint-Naming

- **URL-Konvention:** Plural-Nomen, keine Verben: `/store-api/grow-and-style/products` ✓, `/store-api/grow-and-style/getProducts` ✗.
- **Query-Parameter:** `page`, `limit`, `sort`, `filter` — konsistent über alle Endpunkte. Custom-Parameter MUSS in der Kontrakt-Datei dokumentiert sein.
- **Shopware-Präfix:** Alle Custom-Routes MÜSSEN unter `/store-api/grow-and-style/` liegen. Routen direkt unter `/store-api/` zu registrieren ist **untersagt**.

## 7. Versioning

- **Shopware Plugin:** Versionierung über `composer.json` Constraints (`"shopware/core": "~6.6.0"`). Breaking Changes erfordern neuen Major-Constraint.
- **Companion:** URL-basiert (`/v1/`, `/v2/`). Alte Version MUSS mindestens 2 Sprints nach Deprecation verfügbar bleiben.
- **Breaking-Change-Regel:** Feldentfernung oder Typänderung = Breaking. MUSS zwingend als neuer API-Version-Endpoint bereitgestellt werden.

## 8. Schema-Sync Contract

- **Synchronitätspflicht:** Bei jeder Backend-Schema-Änderung MÜSSEN Frontend-Types im **selben PR** aktualisiert werden. Getrennte PRs sind **STRIKT VERBOTEN**.
- **Validierung:** CI-Pipeline MUSS `zod`-Schema gegen OpenAPI-Spec prüfen (z. B. via `openapi-zod-client`). Drift = Build-Failure.
- **Cross-Referenz:** Bei Multi-Stack-Projekten müssen Frontend-Typen und Backend-Modelle synchron gehalten werden.

## 9. Kill-Kriterien

| Kriterium | Schwere | Konsequenz |
|---|---|---|
| Endpoint ohne vorherige Kontrakt-Datei | **FATAL** | PR wird blockiert |
| Zod-Schema weicht von Pydantic/OpenAPI ab | **FATAL** | Build schlägt fehl |
| Shopware Stack-Trace im Frontend sichtbar | **FATAL** | Sofortige Korrektur, Security-Review |
| `dict`-Return in FastAPI statt Pydantic-Modell | **SCHWER** | PR-Rückweisung |
| Verben in Endpoint-URLs | **SCHWER** | Umbenennung vor Merge |
| Frontend-Typ-Update in separatem PR | **SCHWER** | PR wird blockiert bis synchronisiert |
| Mock-Daten in Produktiv-Konfiguration | **SCHWER** | Sofortige Entfernung |
