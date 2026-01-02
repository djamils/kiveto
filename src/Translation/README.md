## Translation Bounded Context

Responsabilité : fournir des catalogues de traduction persistés en base, cache local par `(scope, locale, domain)`, fallback `shared`, exposition via CQRS et décorateur du translator Symfony compatible ICU.

### Modèle domaine
- Aggregate root `TranslationCatalog` (id = `TranslationCatalogId` : `AppScope` + `Locale` + `TranslationDomain`)
- Entité interne `TranslationEntry` (key/value + metadata)
- VOs : `AppScope`, `Locale`, `TranslationDomain`, `TranslationKey`, `TranslationText`, `ActorId`, `TranslationCatalogId`
- Événements : `TranslationUpserted`, `TranslationDeleted`

### Application (CQRS)
- Commands : UpsertTranslation, DeleteTranslation, BulkUpsertTranslations
- Queries : GetCatalog (cache + fallback shared), GetTranslation (fallback shared), SearchTranslations, ListDomains, ListLocales
- Ports : `CatalogCacheInterface`, `AppScopeResolverInterface`, `LocaleResolverInterface`

### Infrastructure
- Persistance : Doctrine `TranslationEntryEntity`, repos `DoctrineTranslationCatalogRepository` (upsert/delete) et `DoctrineTranslationSearchRepository` (read optimisée)
- Cache : `SymfonyCatalogCache` (pool `cache.translation_catalog`, clé `translation:catalog:v1:{scope}:{locale}:{domain}`)
- Résolveurs : `HostnameAppScopeResolver`, `DefaultLocaleResolver` (backoffice forcé `fr_FR`)
- Décorateur translator : `CatalogTranslator` (fallback vers translator natif, ICU conservé)

### Migration & config
- Migration : `migrations/Translation/Version20260102120000.php` (table `translation_entry` + indexes/unique)
- Doctrine mapping : ajouté dans `config/packages/doctrine.yaml`
- Cache pool : `config/packages/cache.yaml`
- Services : `config/services.yaml` (ports/impls + décorateur translator)

### Notes d’usage
- Cache invalidé sur chaque write (catalog scope ciblé). TTL par défaut 1h.
- Fallback : on charge le scope, puis le catalogue `shared` pour les clés manquantes (pas de merge en cache).
- Préparer tests unitaires pour les VOs/aggregate et tests d’intégration sur cache + queries.

