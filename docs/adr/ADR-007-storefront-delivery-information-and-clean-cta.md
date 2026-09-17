# ADR-007: Storefront Lieferinformation — Semantische Trennung von Liefertermin und Hinweistext sowie Entfall des Button-Icons

**Status:** ENTSCHIEDEN  
**Datum:** 2026-09-17  
**Autor:** Senior Fullstack Shopware 6 Architekt  

## Kontext
Das Storefront-Erlebnis des Plugins `CustomPreOrderManager` umfasst auf der Produktdetailseite (PDP) und den Listing-Cards zwei zentrale visuelle Elemente:
1. **Den Vorbestell-Hinweis (`delivery-information`):** Informiert den Kunden über den avisierten Verfügbarkeitszeitpunkt und besondere Bedingungen der Vorbestellung.
2. **Den Call-to-Action-Button (`.btn-preorder`):** Ermöglicht die Vorbestellung anstelle des regulären Kaufs.

Bei der Überprüfung der bestehenden Implementierung wurden folgende architektonische und gestalterische Mängel identifiziert:
1. **Suboptimales Datenmodell durch exklusives Überschreiben:**
   * In `delivery-information.html.twig` überschrieb das Freitextfeld `custom_preorder_release_text` das strukturierte Datum `custom_preorder_release_date` vollständig.
   * Wenn ein Händler einen erläuternden Hinweis (z. B. *„Herstellerhinweis: Limitierte Erstauflage"* oder *„Lieferung erfolgt gestaffelt"*) eintrug, verschwand das exakte Erscheinungsdatum (`30.09.2026`) aus der Kundenansicht.
   * Dies widerspricht den Grundsätzen des deutschen und europäischen Fernabsatzrechts (Transparenzgebot bezüglich des konkreten Liefertermins bzw. -zeitraums vor Kaufabschluss) sowie der Datenintegrität.
2. **Klobiges, fremdkörperartiges Styling:**
   * Die bisherige Klasse `.preorder-delivery-box` nutzte einen starren 4px-Balken (`border-left: 4px solid #1a1a2e`) und einen einfachen orangen Punkt, was in modernen, luftigen Shopware 6 Themes wie ein Fremdkörper wirkt.
3. **Visuelle Unruhe durch SVG-Button-Icon:**
   * Das im Vorbestell-Button eingebettete SVG-Kalender-Icon führt zu optischer Unruhe und weicht vom standardmäßigen Theme-Button-Design ab, das in den meisten Shops rein typografisch gehalten ist.

## Entscheidung

### 1. Semantische 2-Ebenen-Hierarchie für die Lieferinformation
In `src/Resources/views/storefront/component/delivery-information.html.twig` wird die Anzeige in eine strukturierte Zwei-Ebenen-Architektur überführt:
* **Ebene 1 (Primärer Status — Rechtssicherer Termin):**  
  Liegt ein `custom_preorder_release_date` vor, wird dieses grundsätzlich als Hauptstatus dargestellt. Um dem Händler vor dem Hintergrund des deutschen und europäischen Kaufrechts kein unverschuldetes, automatisches Leistungsverzugsrisiko (Fixgeschäft gemäß § 286 Abs. 2 Nr. 1 BGB bei herstellerseitigen Release-Verschiebungen) aufzuerlegen, wird das Standard-Snippet rechtssicher und marktkonform formuliert:
  - **de-DE:** *„Voraussichtlich lieferbar ab %date%“*
  - **en-GB:** *„Expected to be available from %date%“*
* **Ebene 2 (Sekundärer Zusatzhinweis — Kontext):**  
  Ist zusätzlich ein `custom_preorder_release_text` hinterlegt, wird dieser nicht als Ersatz, sondern als dezent gestaltete Subline / Erläuterung direkt unter dem Datum platziert.
* **Fallback-Kaskade:**
  * Ist *nur* der Freitext gepflegt (z. B. ungenaue Herstellerangabe wie *„Herbst 2026"*), wird dieser als Hauptstatus angezeigt.
  * Ist *weder* Datum noch Text gepflegt, greift das Fallback-Snippet `custom-preorder.badge.preOrder` (*„Vorbestellung"*).

```twig
<div class="product-delivery-information preorder-delivery-information">
    <div class="preorder-delivery-headline">
        <span class="delivery-status-indicator is-preorder"></span>
        <span class="preorder-delivery-status">
            {% if releaseDate %}
                {{ "custom-preorder.badge.availableFrom"|trans({'%date%': releaseDate|date('d.m.Y')}) }}
            {% elseif releaseText %}
                {{ releaseText }}
            {% else %}
                {{ "custom-preorder.badge.preOrder"|trans }}
            {% endif %}
        </span>
    </div>

    {% if releaseDate and releaseText %}
        <div class="preorder-delivery-notice">
            {{ releaseText }}
        </div>
    {% endif %}
</div>
```

### 2. Enterprise UI-Styling nach Design-System-Standards
In `src/Resources/app/storefront/src/scss/base.scss` wird die Box modernisiert:
* **Container:** Subtiles Panel mit weichem Hintergrund (`#fbfbfc`), feinem 1px Border (`#e5e9ee`) und 6px Abrundung.
* **Status-Indikator:** Eleganter Amber-Punkt (`#e67e22`) mit dezentem Glow-Ring (`box-shadow: 0 0 0 3px rgba(230, 126, 34, 0.18)`).
* **Typografie-Flucht:** Der zusätzliche Hinweistext erhält ein `padding-left: 18px`, sodass er optisch exakt mit dem Text der Hauptzeile fluchtet und nicht unter den Status-Punkt geschoben wird.

### 3. Entfall des SVG-Icons im Vorbestell-Button
* Das Kalender-SVG-Icon wird aus allen Button-Templates entfernt:
  * `src/Resources/views/storefront/page/product-detail/buy-widget-form.html.twig`
  * `src/Resources/views/storefront/component/buy-widget/buy-widget-form.html.twig`
  * `src/Resources/views/storefront/component/product/card/action.html.twig`
* Der Button agiert als reiner, fokussierter Text-Button (*„Jetzt vorbestellen"*).
* In `src/Resources/config/config.xml` wird der Default-Wert für `showButtonIcon` auf `false` gesetzt.

## Begründung
* **Rechtssicherheit & Preistransparenz:** Der Endkunde sieht immer das konkrete Erscheinungsdatum, selbst wenn der Händler zusätzliche Erläuterungen im Freitextfeld hinterlegt.
* **Maximale Theme-Harmonie:** Die Entfernung des Icons und das minimalistische Panel fügen sich nahtlos in professionelle Shopware 6 Themes ein.
* **Fehlerresistenz & Barrierefreiheit:** Semantisches HTML mit klaren Klassen sorgt für Lesbarkeit durch Screenreader und einfache CSS-Überschreibbarkeit für Child-Themes.

## Abgelehnte Alternativen

| Alternative | Grund der Ablehnung |
|---|---|
| **Beibehaltung des Entweder-Oder (Freitext überschreibt Datum):** | Führt zum Verlust des konkreten Liefertermins im Storefront bei gepflegtem Hinweistext; rechtlich bedenklich. |
| **Ersetzen durch String-Parsing (`{date}` im Textfeld):** | Fehleranfällig bei Händlereingaben (Syntaxfehler, vergessene Klammern); erfordert zusätzliche Regex-Verarbeitung im Template. |
| **Beibehaltung des Button-Icons als Pflichtelement:** | Erzeugt visuelle Unruhe und kollidiert mit minimalistischen Brand-Designs. |
