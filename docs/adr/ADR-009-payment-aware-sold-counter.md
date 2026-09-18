# ADR-009: Payment-Aware Sold Counter

- **Status:** Accepted
- **Datum:** 2026-09-18
- **Beteiligte:** @nicoschultz, Agent

## Kontext

Der `OrderPlacedSubscriber` erhöht den `custom_preorder_sold_count` bei jeder Bestellung mit Vorbestellpositionen **sofort bei Bestelleingang** (`CheckoutOrderPlacedEvent`), unabhängig vom Zahlungsstatus. Das führt zu folgenden Problemen:

1. **Falsche Kontingent-Berechnung:** Unbezahlte Bestellungen (Vorkasse, Rechnung, abgebrochene Payments) blockieren dauerhaft Vorbestellkontingent.
2. **Kein Rollback:** Stornierte oder rückerstattete Bestellungen geben das Kontingent nicht frei — der `sold_count` bleibt permanent erhöht.
3. **Falsches Scarcity-Signal:** Das Dringlichkeits-Badge auf der PDP zeigt Restmengen an, die durch unbezahlte Bestellungen künstlich reduziert sind.

### Konkretes Beispiel (Produktivfall)
Bluelab EC-Pen: Zulauf 10, `sold_count` = 7 (alle unbezahlt/Vorkasse) → Restmenge 3 → Scarcity-Badge zeigt „Fast vergriffen: Nur noch 3 Stück!" obwohl kein einziges Exemplar verbindlich reserviert ist.

## Entscheidung

**Counter-Inkrement wird an den Shopware State Machine Event `state_enter.order_transaction.state.paid` gebunden.** Decrement bei `state_enter.order_transaction.state.cancelled` und `state_enter.order_transaction.state.refunded`.

### Architektur

```
CheckoutOrderPlacedEvent (Bestelleingang)
├── OrderPlacedSubscriber (unverändert)
│   ├── Order-Tag "Vorbestellung" → bleibt
│   ├── PreOrderPlacedEvent dispatch → bleibt
│   └── DBAL Counter Inkrement → ENTFERNT
│
StateMachineTransitionEvent (Zahlungsstatus)
├── PaymentStateSubscriber (NEU)
│   ├── paid → DBAL Inkrement (+qty)
│   ├── cancelled → DBAL Dekrement (-qty, GREATEST 0)
│   └── refunded → DBAL Dekrement (-qty, GREATEST 0)
```

### Begründung

1. **Tagging und Event bleiben bei Bestelleingang:** Der Tag `Vorbestellung` ist ein Klassifikations-Merkmal („enthält Vorbestellpositionen"), kein Bestätigungs-Merkmal. Er hilft dem Händler beim Filtern im Admin — unabhängig ob bezahlt oder nicht.
2. **Counter ist eine Ressourcen-Buchung:** Kontingent wird erst verbindlich reserviert wenn die Zahlung eingegangen ist. Das entspricht dem Geschäftsprozess: Vorbestellt = bezahlt.
3. **Nur `paid`, nicht `paid_partially`:** Bei Vorkasse ist die Bestellung erst bei vollständiger Zahlung verbindlich.
4. **Automatischer Rollback:** Stornierung/Rückerstattung gibt das Kontingent sofort frei. `GREATEST(..., 0)` Guard verhindert negative Werte bei manuellen Korrekturen durch den Händler.

## Konsequenzen

### Positiv
- Kontingent spiegelt nur verbindlich bezahlte Vorbestellungen wider
- Scarcity-Badge zeigt korrekte Restmengen
- Stornierungen geben Kontingent automatisch frei
- Kein manueller Eingriff durch den Händler nötig

### Negativ
- Bei Sofort-Zahlungen (PayPal, Kreditkarte) minimal verzögertes Inkrement (Event-Chain: OrderPlaced → PaymentPaid), in der Praxis < 1 Sekunde
- Race Condition bei gleichzeitiger Stornierung und Neubestellung theoretisch möglich, durch atomares DBAL (ADR-002) aber abgesichert

### Abgrenzung
- `PreOrderPlacedEvent` wird NICHT auf Zahlungseingang verschoben. Flow Builder Aktionen (z.B. Info-Mail an Kunden) feuern weiterhin bei Bestelleingang.
- Kein eigenes Event für Zahlungseingang einer Vorbestellung (könnte in ROADMAP als Future Feature).
