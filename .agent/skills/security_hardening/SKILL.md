---
name: load-blueprint-security-hardening
description: KRITISCH für alle Security-Fragen, Verschlüsselung, Firewalls, Passwort-Hashing und Härtung von Systemen.
---

# TECH BLUEPRINT: Security & Hardening (Enterprise Standard 2026)

**PARADIGMA:** Zero-Trust, Defense-in-Depth, Least Privilege, Encryption-Everything

## 1. Zero-Trust & Netzwerk

- **Zero-Exposure Policy:** Kein interner Service (Datenbank, Redis, API-Internals) darf direkt über das öffentliche Internet erreichbar sein. Zugriff erfolgt ausschließlich über einen gesicherten Reverse-Proxy (z.B. Nginx, Traefik) mit TLS 1.3+.
- **ZTNA (Zero Trust Network Access):** Jede Anfrage wird individuell authentifiziert und autorisiert — unabhängig davon, ob sie aus dem internen Netzwerk stammt. Implizites Vertrauen auf Netzwerk-Ebene existiert nicht.
- **Firewalling:** Nutze strikte IP-Whitelisting-Regeln und schließe alle Ports, die nicht explizit für den Betrieb benötigt werden.
- **MFA/2FA:** Jeder administrative Zugriff (SSH, Admin-Panel, Datenbank-GUI) MUSS zwingend durch Multi-Faktor-Authentisierung geschützt sein.

## 2. Authentifizierung & Token

- **Passwort-Hashing:** Passwörter werden niemals im Klartext gespeichert. Verwende ausschließlich **Argon2id** (bevorzugt) oder BCrypt mit hohem Cost-Faktor.
- **Token Storage (JWT):** Die Speicherung von JWT-Access-Tokens im `localStorage` oder `sessionStorage` ist **STRIKT VERBOTEN** (XSS-Risiko). Tokens MÜSSEN als **HttpOnly, Secure, SameSite=Strict** Cookie vom Backend gesetzt werden.
- **Session-Management:** Access-Token-Lebensdauer maximal 15 Minuten. Refresh-Tokens mit Rotation und serverseitiger Invalidierung bei Logout.
- **Verschlüsselung:** Alle Daten müssen bei der Übertragung (TLS 1.3) und im Ruhezustand (AES-256 oder LUKS) verschlüsselt sein.

## 3. CORS (Cross-Origin Resource Sharing)

- **Origin-Whitelist:** CORS-Konfigurationen MÜSSEN eine explizite Liste erlaubter Origins verwenden.
- **Wildcard-Verbot:** `Access-Control-Allow-Origin: *` ist in Produktionsumgebungen **STRIKT VERBOTEN**.

**Beispiel:**

✅ Explizite Origin-Whitelist:
```
Access-Control-Allow-Origin: https://growandstyle.de
```

❌ Wildcard in Produktion:
```
Access-Control-Allow-Origin: *
```

## 4. Content Security Policy (CSP)

- **CSP-Header PFLICHT:** Jede produktive Web-Anwendung MUSS einen restriktiven CSP-Header setzen.
- **Nonce-basierte Scripts:** Inline-Scripts sind nur mit dynamischen Nonces erlaubt (`'nonce-{random}'`). Kein `'unsafe-eval'`.

**Beispiel (Shopware-Storefront):**

✅ Restriktiver CSP-Header:
```
Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{random}'; style-src 'self' 'unsafe-inline'
```

## 5. Input-Validierung

- **Schema-Validierung PFLICHT:** Vertraue niemals Client-Daten. Jeder Input MUSS gegen ein striktes Schema validiert werden (Pydantic im Backend, Zod im Frontend).
- **SQL-Injection-Prävention:** Ausschließlich parametrisierte Queries oder ORM-Methoden verwenden. String-Concatenation in SQL-Queries ist **STRIKT VERBOTEN**.

**Beispiel:**

✅ Pydantic Validator:
```python
@field_validator('email')
def validate_email(cls, v: str) -> str:
    if '@' not in v or len(v) > 254:
        raise ValueError('Ungültige E-Mail-Adresse')
    return v.lower().strip()
```

❌ Ungefilterte Eingabe:
```python
data = request.body  # direkt ohne Validierung weiterverarbeitet
```

## 6. Rate-Limiting

- **API-Endpoints:** Alle öffentlichen Endpoints MÜSSEN durch Rate-Limiting geschützt sein (z.B. via Reverse-Proxy oder Middleware).
- **Login/Auth:** Authentifizierungs-Endpoints erfordern **aggressives** Rate-Limiting (max. 5 Versuche pro Minute pro IP) und progressive Verzögerung (Backoff) gegen Brute-Force-Angriffe.
- **Monitoring:** Rate-Limit-Überschreitungen MÜSSEN geloggt und bei wiederholtem Auftreten alarmiert werden.

## 7. Secrets-Management

- **Keine Hardcoded Secrets:** API-Keys, Passwörter und Zertifikate dürfen NIEMALS im Quellcode stehen. Nutze Umgebungsvariablen oder einen Secret-Manager (z.B. Vault, AWS Secrets Manager).
- **`.env.example`:** Jedes Repository MUSS eine `.env.example` mit allen benötigten Variablen (ohne Werte) enthalten.
- **Secret Masking:** Secrets dürfen NIEMALS in Logs, Fehlermeldungen oder API-Responses auftauchen.

**Beispiel:**

✅ Secret aus Umgebungsvariable:
```python
hmac_secret = os.environ['HMAC_SECRET']
```

❌ Hardcoded Secret:
```python
HMAC_SECRET = 'abc123'  # NIEMALS!
```

## 8. Kill-Kriterien

| Verstoß | Konsequenz |
|---------|------------|
| Credentials/Secrets im Quellcode | **Sofort entfernen** + Secret rotieren |
| `eval()` / `innerHTML` mit User-Input | **Sofort entfernen** — Remote Code Execution Risiko |
| `Access-Control-Allow-Origin: *` in Produktion | **Sofort fixen** — Origin-Whitelist implementieren |
| Fehlende Rate-Limits auf Auth-Endpoints | **Implementieren** — Brute-Force-Schutz pflicht |
| SQL-Query mit String-Concatenation | **Sofort fixen** — Parametrisierte Query verwenden |