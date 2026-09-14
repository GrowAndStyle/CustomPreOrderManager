---
name: load-blueprint-state-architecture
description: KRITISCH! Definiert die State-Management-Strategie VOR jeder Implementierung. Wird bei Projektstart UND bei neuen Features mit State-Anforderungen geladen. Erzwingt kategorisierte State-Map, Stack-spezifische Toolwahl und Persistenz-Dokumentation.
---

# PLANNING BLUEPRINT: State Architecture (Enterprise Standard 2026)

**SCOPE:** Jede Anwendung mit UI-State | **PARADIGMA:** Kategorisieren → Zuordnen → Dokumentieren → Implementieren

## 1. State-Kategorien (PFLICHT vor Implementierung)

Jeder State MUSS exakt einer Kategorie zugeordnet werden:

| Kategorie | Beschreibung | Beispiel |
|---|---|---|
| **Server State** | Cache von API-Daten, nie manuell konstruiert | Produktliste, User-Profil |
| **Client State** | Globaler/shared UI-State über mehrere Komponenten | Theme, Sidebar-Status, aktiver Workspace |
| **Local State** | Nur innerhalb einer Komponente sichtbar | Dropdown offen/zu, Hover-Zustand |
| **URL State** | In Router-Params/Query-Params abgebildet | Filterkriterien, aktive Tab-ID, Pagination |
| **Form State** | Eingabewerte, Validierung, Dirty-Tracking | Checkout-Formular, Login-Felder |
| **Persisted State** | Überlebt Page-Reload via Storage | Auth-Token, Warenkorb-Entwurf |

## 2. Stack-spezifische Regeln

| Stack | Server State | Client State | Local State | Hinweis |
|---|---|---|---|---|
| **Svelte 5** | SvelteQuery / `$effect` fetch | Context API (`setContext`/`getContext`) | `$state()` + `$derived` | NIEMALS Stores für Local State |
| **React** | TanStack Query | Zustand | `useState` / `useReducer` | Redux ist **VERBOTEN** für Neuprojekte |
| **Vue.js 3 (Shopware Admin)** | Shopware Repository API | Shopware State Store | `ref()` / `reactive()` | Pinia nur nach Architektur-Review |
| **PHP/Twig (Storefront)** | Controller-Injection | Session-Variablen | Twig Context Variables | JS-State nur für progressive Enhancement |

## 3. Entscheidungsmatrix

- **Daten kommen vom Server?** → TanStack Query / SvelteQuery / Repository API. NIEMALS manueller `fetch` + `setState`.
- **Mehrere Komponenten lesen denselben State?** → Zustand (React) / Context API (Svelte) / State Store (Vue). Prop Drilling ist **ab 2 Ebenen VERBOTEN**.
- **Nur eine Komponente braucht den State?** → `useState` / `$state()` / `ref()`. Kein globaler Store.
- **State soll in URL reflektiert werden?** → Router-Params/Query-Params. MUSS beim Teilen des Links den gleichen View reproduzieren.
- **Formulardaten?** → Dedizierte Form-Library oder lokaler State. Globaler Store für Formulare ist **STRIKT VERBOTEN**.

## 4. Persistenz-Strategie

**PFLICHT:** Dokumentiere, welcher State einen Reload überlebt und warum. Direkter `localStorage.setItem()`-Aufruf ist **VERBOTEN** — MUSS über typsicheren Wrapper laufen.

- **sessionStorage:** Temporäre Flows (z.B. SetGenerator speichert Warenkorb-Entwurf für aktive Session)
- **localStorage:** Langlebige Präferenzen (z.B. POS speichert Auth-Token via Zustand `persist`-Middleware)
- **IndexedDB:** Offline-Daten > 5MB (z.B. Offline-Produktkatalog für Mobile POS)

## 5. State-Map (PFLICHT vor Implementierung)

Vor jeder Implementierung MUSS folgende Tabelle im Planungsdokument existieren:

| State-Name | Kategorie | Wo lebt er? | Persistiert? | Wer liest? | Wer schreibt? |
|---|---|---|---|---|---|
| `currentUser` | Server State | TanStack Query Cache | Nein | Header, Sidebar, Settings | Login-Flow |
| `cartDraft` | Persisted State | Zustand + sessionStorage | Ja (Session) | CartPreview, Checkout | ProductCard, CartActions |

## 6. Mobile State Considerations

- **Offline-Capability:** States mit `isOffline`-Flag MÜSSEN Fallback-Daten aus IndexedDB/Cache liefern. Sync-Strategie (optimistic/pessimistic) MUSS dokumentiert sein.
- **Touch-Event-State:** Swipe-Richtung, Long-Press-Timer — MUSS als Local State leben, NIEMALS global.
- **Viewport-State:** Breakpoint-abhängiger State MUSS über Media-Query-Hook/Rune gesteuert werden, NICHT über manuelle `resize`-Listener.

## 7. Anti-Patterns (STRIKT VERBOTEN)

- **Prop Drilling > 2 Ebenen** → Context/Store verwenden. Ausnahme: explizite Composition-Patterns.
- **`useEffect` als State-Sync** → Zwei States synchron halten via Effect ist untersagt. `$derived` / `useMemo` / `computed` nutzen.
- **Globaler Store für Formulardaten** → Formulare leben lokal. Ausnahme: Multi-Step-Wizards mit dokumentierter Begründung.
- **Raw `localStorage` ohne Wrapper** → Kein direkter Zugriff. Typsicherer Wrapper mit Serialisierung ist PFLICHT.
- **Redux/Vuex für alles** → Monolithischer Global-State ist untersagt. Kategorisierung aus §1 erzwingt gezielte Toolwahl.
- **State-Duplikation** → Derselbe Datenpunkt darf NIEMALS in zwei Stores gleichzeitig leben.

## 8. Kill-Kriterien

| Kriterium | Schwelle | Konsequenz |
|---|---|---|
| State-Map fehlt im Planungsdokument | 0 Toleranz | PR wird abgelehnt |
| Prop Drilling über 2+ Ebenen | > 2 Ebenen | Refactoring PFLICHT vor Merge |
| `useEffect`/`$effect` als State-Sync | Jedes Vorkommen | Sofortiger Rewrite zu derived/computed |
| Raw `localStorage`/`sessionStorage` | Jeder direkte Aufruf | Wrapper-Migration vor Merge |
| Unkategorisierter State in neuem Feature | Jedes Vorkommen | Feature-Branch blockiert |
| Server-Daten manuell in Client-Store kopiert | Jedes Vorkommen | Migration zu Query-Library PFLICHT |
