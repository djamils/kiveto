# Translation Bounded Context

## Responsabilité
- Source de vérité des traductions en base (pas de YAML).
- Servir les traductions par `(scope, locale, domain)` avec fallback `shared`.
- Permettre au support/backoffice de modifier sans déploiement, avec invalidation de cache ciblée.
- Intégration transparente avec le translator Symfony (ICU conservé).

## Structure du BC
```
src/Translation/
├── Domain/
│   ├── TranslationCatalog.php          # Aggregate root (scope+locale+domain)
│   ├── TranslationEntry.php            # Entrée (key/value + metadata)
│   ├── ValueObject/
│   │   ├── AppScope.php
│   │   ├── Locale.php
│   │   ├── TranslationDomain.php
│   │   ├── TranslationKey.php
│   │   ├── TranslationText.php
│   │   ├── ActorId.php
│   │   └── TranslationCatalogId.php
│   ├── Event/
│   │   ├── TranslationUpserted.php
│   │   └── TranslationDeleted.php
│   └── Repository/
│       ├── TranslationCatalogRepository.php
│       └── TranslationSearchRepository.php
├── Application/
│   ├── Command/UpsertTranslation|DeleteTranslation|BulkUpsertTranslations
│   ├── Query/GetCatalog|GetTranslation|SearchTranslations|ListDomains|ListLocales
│   └── Port/
│       ├── CatalogCacheInterface.php
│       ├── AppScopeResolverInterface.php
│       └── LocaleResolverInterface.php
└── Infrastructure/
    ├── Persistence/Doctrine/
    │   ├── Entity/TranslationEntryEntity.php
    │   ├── Mapper/TranslationEntryMapper.php
    │   └── Repository/DoctrineTranslationCatalogRepository.php (write)
    │                               DoctrineTranslationSearchRepository.php (read)
    ├── Cache/SymfonyCatalogCache.php
    ├── Resolver/HostnameAppScopeResolver.php
    ├── Resolver/DefaultLocaleResolver.php
    └── Symfony/Translator/CatalogTranslator.php
```

## Flux principaux
- **Lecture (translator Symfony/Twig)**  
  1) `CatalogTranslator::trans` résout `scope` (hostname) et `locale` (resolver, backoffice forcé `fr_FR`).  
  2) QueryBus → `GetTranslation` → cache catalog (`translation:catalog:v1:{scope}:{locale}:{domain}`), sinon DB via `TranslationSearchRepository`.  
  3) Si key absente, fallback sur `shared`. Retour ICU intact.

- **Lecture catalogue brut (ex: export/backoffice)**  
  `GetCatalog` → cache-first sur le catalogue du scope, puis fallback `shared` pour les clés manquantes (pas de merge en cache).

- **Écriture (support/backoffice)**  
  - `UpsertTranslation` ou `BulkUpsertTranslations`: charge ou crée le `TranslationCatalog`, upsert des lignes, persiste, invalide le cache du catalogue concerné, publie `TranslationUpserted`.  
  - `DeleteTranslation`: remove idempotent, invalide le cache, publie `TranslationDeleted`.

## Modèle domaine
- Aggregate root : `TranslationCatalog` identifié par `TranslationCatalogId` (scope+locale+domain).
- Entité : `TranslationEntry` (key/value, updatedAt, updatedBy).
- Invariants : `(scope, locale, domain, key)` unique ; key/domain normalisés ; remove idempotent.
- Domain events : `TranslationUpserted`, `TranslationDeleted` (pour éventuelles projections/notifications).

## Ports & Adapters
- Cache : `CatalogCacheInterface` → `SymfonyCatalogCache` (Filesystem/APCu par défaut, prêt pour Redis).
- Résolution scope/locale : `HostnameAppScopeResolver` (hostname → scope), `DefaultLocaleResolver` (backoffice forcé fr_FR, sinon attr/query/Accept-Language).
- Translator : décorateur `CatalogTranslator` branché sur `translator` Symfony (fallback natif).

## Persistance & Migration
- Table `translation_entry` (InnoDB, utf8mb4) avec unique `(app_scope, locale, domain, translation_key)` et indexes de recherche.  
- Migration : `migrations/Translation/Version20260102120000.php`.  
- Mapping Doctrine activé dans `config/packages/doctrine.yaml`.

## Cache
- Clé : `translation:catalog:v1:{scope}:{locale}:{domain}`.  
- TTL 1h (configurable). Invalidation ciblée sur chaque write. Pas de cache du merge fallback.

## Configuration Symfony
- Cache pool : `config/packages/cache.yaml` (`cache.translation_catalog`).  
- Services/ports/adapters/décorateur translator : `config/services.yaml`.

## Tests à prévoir
- Unitaires : VOs (validation/normalisation), `TranslationCatalog` (upsert/remove/invariants), résolveurs.  
- Intégration : cache invalidation sur upsert/delete, fallback `shared` sur GetTranslation/GetCatalog, requêtes search/pagination.

