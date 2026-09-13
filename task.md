# Task Board — CustomPreOrderManager

- [x] Phase 0: Scaffolding, Manifest & Skill-Suite (Tier-1)
- [x] Phase 1: DAL Migrationen (CustomField-Set am Produkt & Wartelisten-Entity custom_preorder_waitlist)
  - [x] Task 1.1: Migration Migration1726200000AddPreOrderCustomFields.php (Idempotente Anlage custom_preorder_set am Produkt)
  - [x] Task 1.2: Migration Migration1726200100CreatePreOrderWaitlist.php (Tabelle custom_preorder_waitlist mit Composite FK)
  - [x] Task 1.3: DAL-Klassen (PreOrderWaitlistDefinition, PreOrderWaitlistEntity, PreOrderWaitlistCollection)
  - [x] Task 1.4: Service-Registrierung in services.xml
  - [x] Task 1.5: Unit-Tests in tests/Unit/Core/Content/PreOrderWaitlist/
- [ ] Phase 2: Cart-Collector Pipeline & LineItem Payload Enrichment
- [ ] Phase 3: Order-Placed Subscriber & Auto-Tagging Vorbestellung
- [ ] Phase 4: Storefront Twig & SCSS (PDP Button, Listing Badges, Mobile-First)
- [ ] Phase 5: Scarcity-Engine & Stock-Lifecycle Transition
- [ ] Phase 6: Wartelisten-Formular, DOI-Controller & CIS-Mail-Templates
- [ ] Phase 7: Admin-Modul (Wartelisten-Listing & 1-Klick-Notify)
- [ ] Phase 8: End-to-End Verifikation & Release-Build mit shopware-cli
