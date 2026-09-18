# ADR-010: Scarcity-Badge Integration in Delivery-Card & Listing (UWG-konform)

**Status:** ENTSCHIEDEN
**Datum:** 2026-09-18
**Autor:** Agent (Review durch Nico Schultz)

## Kontext

Das Scarcity-Badge ("Nur noch X Stück verfügbar!") wird aktuell als separater, schwarz hinterlegter Block unterhalb des Kaufen-Buttons auf der PDP gerendert. Das ist optisch inkonsistent mit der darüber platzierten Delivery-Card, die ein durchdachtes, config-gesteuertes Card-Design mit SVG-Icons, Pill-Badge und CSS Custom Properties nutzt.

Zusätzlich fehlt eine Scarcity-Anzeige auf den Kategorieseiten (Listing). Die Sichtbarkeit soll per Config granular steuerbar sein (PDP, Listing, beides, oder deaktiviert).

## Rechtliche Analyse: UWG-Konformität

### Relevante Rechtsquellen

| Rechtsquelle | Relevanz |
|---|---|
| **UWG §5 Abs. 1** (Irreführungsverbot) | Restmengen-Angaben müssen dem tatsächlichen Bestand entsprechen. Falsche Angaben = unzulässige Irreführung. |
| **UWG §5 Abs. 1 Satz 2 Nr. 1** | Verfügbarkeit ist ein "wesentliches Merkmal" — falsche Angaben veranlassen Verbraucher zu Entscheidungen, die sie sonst nicht getroffen hätten. |
| **UWG §5a** (Irreführung durch Unterlassen) | Wenn Knappheit beworben wird, dürfen wesentliche Informationen nicht vorenthalten werden. |
| **Omnibus-Richtlinie EU 2019/2161** (seit 28.05.2022 in DE) | Stärkung des Verbraucherschutzes gegen manipulative Online-Praktiken. |
| **OLG Rostock** (Rechtsprechung) | Restmengen-Anzeigen müssen den tatsächlichen Warenbestand in **Echtzeit** widerspiegeln. |
| **DSA Art. 25** (seit Feb. 2024) | Verbot manipulativer Schnittstellen bei Online-Plattformen. Gilt primär für Marktplätze, setzt aber den Standard. |
| **Digital Fairness Act (DFA)** (EU, geplant ~2029) | Wird Dark Patterns auch für kleinere Online-Shops explizit verbieten. Unser Design ist bereits zukunftssicher. |

### Compliance-Prüfung unserer Implementierung

| Anforderung | Status | Begründung |
|---|---|---|
| **Echtzeit-Daten** | ✅ Konform | `sold_count` wird per DBAL-Statement bei jedem Zahlungseingang/Storno atomar aktualisiert (ADR-009). Kein Cache-Delay. |
| **Keine künstliche Verknappung** | ✅ Konform | `inbound_stock` wird manuell vom Shopbetreiber gepflegt = echte Liefermengen. `sold_count` basiert auf tatsächlichen Zahlungen. `remaining = inbound_stock - sold_count` ist mathematisch korrekt. |
| **Kein Dark Pattern** | ✅ Konform | Kein Countdown, kein Timer, kein Blinken, kein "Beeile dich!", kein Fake-Reset. Rein sachliche Information. |
| **Kein aggressives Urgency-Design** | ✅ Konform | Kein pulsierendes Element für Scarcity, keine Warn-Farben (Rot), keine Animation. Dezente Integration in bestehende Card. |
| **Abschaltbar** | ✅ Konform | Config `scarcityDisplayMode` deaktiviert global. Kein Zwang zur Anzeige. |
| **Sachliches Wording** | ✅ Konform | "Nur noch X Stück verfügbar" — beschreibt Tatsache, keine Panikmache. |
| **WCAG-Konformität** | ✅ Konform | `prefers-reduced-motion` wird respektiert, kein Blinken, kein autoscrolling. |
| **Nachweisbarkeit** | ✅ Konform | Werte basieren auf DB-Einträgen mit Audit-Trail (Order-Events). Bei Abmahnung nachweisbar. |

### Risikobewertung

| Risiko | Einstufung | Maßnahme |
|---|---|---|
| Falsche Restmenge bei Race Condition | **Gering** | DBAL GREATEST-Guard + atomare Updates. Maximal ±1 im Millisekunden-Fenster. |
| Shopbetreiber pflegt `inbound_stock` falsch | **Mittel** | Liegt in Verantwortung des Shopbetreibers, nicht des Plugins. Dokumentation weist darauf hin. |
| Abmahnung wegen "Urgency"-Design | **Sehr gering** | Kein Timer, kein Countdown, keine Animation. Sachliche Restmengen-Info ist rechtlich zulässig (OLG Rostock). |

> **Haftungshinweis:** Diese Analyse basiert auf öffentlich zugänglicher Rechtsprechung und Gesetzestexten. Sie stellt keine Rechtsberatung dar. Für rechtsverbindliche Aussagen empfiehlt sich ein auf Wettbewerbsrecht spezialisierter Anwalt.

## Entscheidung

**Wir integrieren das Scarcity-Badge als optionale Zeile innerhalb der bestehenden Delivery-Card** und zeigen es optional auf Kategorieseiten als dezenten Text-Badge an. Die Sichtbarkeit wird per Select-Config granular steuerbar.

### Config: Granulare Steuerung

Der bestehende Boolean `enableScarcityCounter` wird durch ein **Select-Feld** `scarcityDisplayMode` ersetzt:

| Wert | Label (de-DE) | Label (en-GB) | Verhalten |
|---|---|---|---|
| `everywhere` | Auf Kategorie- & Detailseite anzeigen | Show on category & detail page | PDP + Listing |
| `listing_only` | Nur auf Kategorieseite anzeigen | Show on category page only | Nur Listing |
| `detail_only` | Nur auf Detailseite anzeigen | Show on detail page only | Nur PDP |
| `disabled` | Ausblenden | Disabled | Nirgends |

**Default:** `detail_only` (konservativ — Shopbetreiber aktiviert Listing bewusst).

### PDP: Integration in Delivery-Card

Die Scarcity-Zeile wird als letzte Zeile innerhalb `preorder-card-body` eingefügt. Design:
- SVG Alert-Triangle-Icon (⚠), `width="14"`, gleiche Stroke-Eigenschaften wie `preorder-notice-icon`
- Text: `custom-preorder.badge.urgentFewLeft` Snippet
- CSS-Klasse `preorder-scarcity-line`, nutzt `--custom-preorder-card-accent` als Farbe
- Nur sichtbar wenn `scarcityDisplayMode` ∈ {`everywhere`, `detail_only`} UND `remaining > 0 AND remaining <= lowStockThreshold`

### Listing: Dezenter Text-Badge

Auf der Listing-Card (Kategorieseite) wird unterhalb des bestehenden "Vorbestellbar"-Badges ein kurzer Hinweis ergänzt:
- CSS-Klasse `preorder-scarcity-listing`
- Kompakter Text, kleinere Schrift
- Nur sichtbar wenn `scarcityDisplayMode` ∈ {`everywhere`, `listing_only`}

### Shared Partial

Die `scarcity-badge.html.twig` wird zum kontextabhängigen Partial mit `variant`-Parameter:
- `variant: 'card'` → Card-integrierter Stil mit SVG-Icon
- `variant: 'listing'` → Kompakter Badge ohne Icon
- Buy-Widget-Templates brauchen kein Badge mehr (wandert in Delivery-Card)

### Wording

Aus UX-Sicht empfehle ich "Nur noch X Stück verfügbar" — das ist:
- **Sachlich:** Beschreibt eine Tatsache, keine Emotion
- **UWG-konform:** Keine Panikmache, kein "Letzte Chance!", kein "Beeile dich!"
- **Klar:** Der Kunde versteht sofort, was gemeint ist
- **Kein Dark Pattern:** Kein Timer, kein Countdown, kein "X Leute schauen sich das gerade an"

## Begründung

1. **Optische Konsistenz:** Badge innerhalb der Card nutzt dieselben CSS Custom Properties und passt sich automatisch an Theme-Änderungen an.
2. **Granulare Steuerung:** Shopbetreiber entscheidet selbst, wo Scarcity angezeigt wird.
3. **UWG-Konformität:** Sachliche Darstellung, Echtzeit-Daten, keine Dark Patterns. Nachweisbar bei Abmahnung.
4. **Zukunftssicher:** Design bereits kompatibel mit geplantem Digital Fairness Act (EU, ~2029).

## Abgelehnte Alternativen

| Alternative | Grund der Ablehnung |
|---|---|
| Separater Scarcity-Block (Status quo) | Optisch inkonsistent, wird nicht als Teil der Delivery-Info wahrgenommen |
| Countdown-Timer ("Nur noch X Minuten!") | Dark Pattern, UWG §5-Verstoß, Abmahnrisiko |
| Blinkender/pulsierender Scarcity-Badge | Aggressives Urgency-Design = Dark Pattern, nicht WCAG-konform |
| Einfacher Boolean (an/aus) statt Select | Zu wenig Granularität — Shopbetreiber braucht Kontrolle über Sichtbarkeit pro Seitentyp |
| Emotionaler Wortlaut ("Letzte Chance!", "Schnell zugreifen!") | UWG §5-Risiko — erzeugt künstlichen Kaufdruck |
| Separate Config-Keys für PDP und Listing | Over-Engineering — ein Select-Feld mit 4 Optionen ist einfacher und klarer |
