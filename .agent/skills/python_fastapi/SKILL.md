---
name: load-blueprint-python-fastapi
description: KRITISCH für Backend-Aufgaben! Lade diese Datei für Python, FastAPI, Pydantic, SQLAlchemy/SQLModel und API-Sicherheit.
---

# TECH BLUEPRINT: Python & FastAPI (Enterprise Standard 2026)

**STACK:** Python 3.12+, FastAPI, Pydantic v2, Ruff, Pytest
**PARADIGMA:** Asynchron, Service-Layer, Zero-Blocking

## 1. Code-Qualität & Typisierung (Zero-Any)
- **Strikte Typisierung:** Jede Funktion, Methode und Variable MUSS vollständig typisiert sein (inklusive Return-Types wie `-> dict[str, Any]` oder `-> None`).
- **Linter & Formatierung:** Der Code muss lückenlos durch `ruff` (oder `ruff format`) laufen. Nutze für Ausführungen stets den Runner-Contract (keine globalen `pip installs`).
- **Docstrings:** Jede öffentliche Funktion/Klasse erfordert zwingend einen PEP-257 konformen Docstring auf Deutsch (Zweck, Args, Returns, Raises).

## 2. Architektur: Das Service-Layer-Pattern
- **Router (API-Layer):** Dateien in `routers/` dürfen **ausschließlich** Request-Validierung (via Pydantic), Dependency Injection (Auth/DB-Session) und Response-Formatierung übernehmen.
- **Services (Business-Layer):** Die gesamte Geschäftslogik liegt in `services/`. Router rufen lediglich die passenden Service-Funktionen auf. 
- **Verbot:** Business-Logik, direkte SQL-Queries oder komplexe Berechnungen innerhalb von `@router.get/post(...)` Controllern sind strikt verboten.

**Beispiel:**

✅ Router ruft Service auf:
```python
@router.post('/orders')
async def create_order(data: OrderCreate, service: OrderService = Depends()):
    return await service.create(data)
```

❌ Business-Logik im Router:
```python
@router.post('/orders')
async def create_order(data: OrderCreate, db: Session = Depends()):
    order = Order(**data.dict())
    db.add(order)
    await db.commit()
    return order
```

## 3. Asynchrones Paradigma (Zero-Blocking)
- **Async First:** Nutze zwingend `async def` für I/O-gebundene Operationen (Datenbank, API-Calls, Dateisystem).
- **Blocking-Prävention:** Synchrone, CPU-lastige Berechnungen (z.B. komplexe Krypto-Hashes) dürfen den Main-Thread (Event Loop) nicht blockieren und MÜSSEN in Thread-Pools (`asyncio.to_thread`) ausgelagert werden.
- **Background Jobs:** Asynchrone Prozesse, die potenziell länger als 300ms dauern (z.B. E-Mail-Versand, PDF-Rechnungsgenerierung, langwierige API-Syncs), dürfen niemals im Request-Zyklus hängen. Diese MÜSSEN zwingend an `FastAPI BackgroundTasks` oder eine externe Worker-Queue (z.B. Celery, RQ) übergeben werden.

## 4. Error Handling & Sicherheit
- **Globale Exception Handler & Opaque Errors:** Keine rohen Try/Catch-Blöcke in Routern, die 500er Fehler an den User durchreichen. Nutze Custom Exceptions (z.B. `EntityNotFoundError`). Es gilt die **Opaque Error Policy**: Interne Stacktraces, Datenbank-Feldnamen oder fehlschlagende SQL-Queries dürfen NIEMALS an das Frontend ausgeliefert werden, da dies ein massives Security-Leak darstellt.

**Beispiel:**

✅ Custom Exception mit opakem Error-Code:
```python
raise EntityNotFoundError('Produkt nicht gefunden')
# → Frontend sieht: {'error_code': 'GAS-4004', 'message': 'Produkt nicht gefunden'}
```

❌ Stacktrace an Frontend geleakt:
```python
raise HTTPException(500, detail=str(e))
# → Frontend sieht den internen Stacktrace
```

- **Dependency Injection:** Auth, DB-Sessions und Konfigurationen MÜSSEN über `Depends()` injiziert werden. Keine globalen Zustände (Global State).

## 5. Pydantic v2 & SQLModel Standards
- **Validatoren:** Nutze strikte Validatoren (`@field_validator`, `@model_validator`).
- **Finanzen:** Für Finanzen immer `Decimal`. Für Konfigurationen `pydantic-settings` verwenden.
- **Sequential Diff Rule (CRITICAL):** Änderungen an SQLModels und ihren Pydantic-Derivaten (`*Read`, `*Create`) dürfen vom Agenten NICHT als massives Einzel-Diff geschrieben werden. Passe die Dateien zwingend **sequenziell** (nacheinander) an.
- **API-Contract Reminder:** Wird hier ein Schema geändert, MUSS das Frontend-Interface im selben Task aktualisiert werden.
> → Siehe auch: `api_contract_first` für den vollständigen Contract-First-Workflow.

## 6. Observability & Log-Sanitization (Zero-Blind-Flight)
- **Strukturiertes JSON-Logging:** Standard `print()`-Befehle sind im Backend strikt verboten. Nutze ein strukturiertes Logging-Framework (z.B. `structlog`), um Logs maschinenlesbar (JSON) zu halten.
- **GDPR/DSGVO Log-Masking:** Der Agent ist zwingend verpflichtet, Bequemlichkeits-Logs wie `logger.info("Order", data=order.dict())` zu vermeiden, wenn PII enthalten ist. PII-Daten (Namen, E-Mails) MÜSSEN vor dem Log-Output aktiv herausgefiltert oder kryptografisch maskiert werden.
- **Traceability:** Jede kritische Transaktion (z.B. Finanzbuchungen, Orders) MUSS eine eindeutige Trace-ID erhalten.

## 7. FinTech & Concurrency Rules (CRITICAL)
- **Idempotency-Pflicht für Finanzen:** Mutierende, finanzielle oder kritische Endpunkte (z.B. `/checkout`, `/storno`) MÜSSEN idempotent gestaltet sein. Das Frontend schickt einen eindeutigen Header (`X-Idempotency-Key`). Das Backend MUSS prüfen, ob die Transaktion bereits durchgeführt wurde, um Doppelbuchungen bei Netzwerk-Retries (z.B. durch React Query) abzufangen.
- **Race Conditions & DB-Locks:** Laufzeit-Konflikte (Double Spends) bei Bestandsveränderungen oder Geldbeträgen MÜSSEN verhindert werden.
> → Für DB-Lock-Patterns siehe sql_standard §4 (nur in POS).

## 8. Kill-Kriterien

| Verstoß | Konsequenz |
|---------|------------|
| Business-Logik im Router statt Service-Layer | **Refactor** — Logik in `services/` extrahieren |
| Synchrones I/O in `async def` | **Refactor** — `asyncio.to_thread()` nutzen |
| Stacktrace an Frontend geleakt | **Sofort fixen** — Opaque Error Policy durchsetzen |
| `print()` statt strukturiertes Logging | **Ersetzen** — `structlog`/JSON-Logging verwenden |
| PII in Logs ohne Maskierung | **Sofort maskieren** — DSGVO-Verstoß |
| Finanzieller Endpoint ohne Idempotency-Key | **Implementieren** — `X-Idempotency-Key` Header pflicht |