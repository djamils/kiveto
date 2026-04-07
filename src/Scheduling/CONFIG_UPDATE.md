# Configuration Scheduling - Mise à jour

## ✅ Fichiers de configuration mis à jour

### 1. `config/packages/doctrine.yaml`

Ajout du mapping Doctrine pour le BC Scheduling :

```yaml
Scheduling:
    type: attribute
    is_bundle: false
    dir: '%kernel.project_dir%/src/Scheduling/Infrastructure/Persistence/Doctrine/Entity'
    prefix: 'App\Scheduling\Infrastructure\Persistence\Doctrine\Entity'
    alias: Scheduling
```

### 2. `config/packages/doctrine_migrations.yaml`

Ajout du path de migrations Scheduling :

```yaml
doctrine_migrations:
    migrations_paths:
        # ... autres BCs
        'DoctrineMigrations\Scheduling': '%kernel.project_dir%/migrations/Scheduling'
```

### 3. `config/services.yaml`

Ajout des services Scheduling dans le fichier principal (comme tous les autres BCs) :

```yaml
# ============================================================================
# BOUNDED CONTEXT: SCHEDULING
# ============================================================================

App\Scheduling\Domain\Repository\AppointmentRepositoryInterface:
    class: App\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DoctrineAppointmentRepository

App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface:
    class: App\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DoctrineWaitingRoomEntryRepository

# Read repositories et adapters...
```

**Important** : Pas de fichier séparé `config/services/scheduling.yaml`, tout est dans `services.yaml` principal comme les autres BCs.

### 4. `Makefile`

Ajout de la target `scheduling-migrations` :

```makefile
# Dans .PHONY
.PHONY: ... scheduling-migrations ...

# Dans la target migrations
migrations: ... scheduling-migrations ...

# Nouvelle target
scheduling-migrations:
	@$(call step,Generating migrations for Scheduling...)
	$(Q)$(call run_live,$(SYMFONY) doctrine:migrations:diff --no-interaction --allow-empty-diff --formatted --namespace='DoctrineMigrations\Scheduling' --filter-expression='/^scheduling__/')
	@$(call ok,Scheduling migrations generated)
```

---

## 🚀 Commandes disponibles

### Générer une migration Scheduling

```bash
make scheduling-migrations
```

ou directement :

```bash
php bin/console doctrine:migrations:diff \
  --namespace='DoctrineMigrations\Scheduling' \
  --filter-expression='/^scheduling__/'
```

### Exécuter les migrations

```bash
# Toutes les migrations
make migrate-db

# ou directement
php bin/console doctrine:migrations:migrate --no-interaction
```

### Générer toutes les migrations (tous BCs)

```bash
make migrations
```

Cela exécutera :
- identity-access-migrations
- translations-migrations
- clinic-migrations
- access-control-migrations
- client-migrations
- animal-migrations
- **scheduling-migrations** ⭐ (nouveau)
- shared-migrations

---

## ✅ Vérification

### Test de la configuration Doctrine

```bash
# Vérifier les mappings
php bin/console doctrine:mapping:info

# Devrait afficher :
# ...
# [OK] App\Scheduling\Infrastructure\Persistence\Doctrine\Entity
```

### Test des migrations

```bash
# Voir le statut des migrations Scheduling
php bin/console doctrine:migrations:status --namespace='DoctrineMigrations\Scheduling'

# Lister les migrations disponibles
php bin/console doctrine:migrations:list --namespace='DoctrineMigrations\Scheduling'
```

---

## 📦 Résumé des changements

| Fichier | Changement | Ligne(s) |
|---------|-----------|---------|
| `config/packages/doctrine.yaml` | Ajout mapping Scheduling | ~60-65 |
| `config/packages/doctrine_migrations.yaml` | Ajout path migrations | ~9 |
| `config/services.yaml` | Ajout services Scheduling | ~213-240 |
| `Makefile` | Ajout target scheduling-migrations | ~101, 240, 272-276 |

---

## 🎉 Configuration complète !

Tous les fichiers de configuration sont maintenant à jour pour supporter le module Scheduling.

Les commandes Makefile sont cohérentes avec les autres BCs :

```bash
make identity-access-migrations  # IdentityAccess BC
make translations-migrations     # Translation BC
make clinic-migrations          # Clinic BC
make access-control-migrations  # AccessControl BC
make client-migrations          # Client BC
make animal-migrations          # Animal BC
make scheduling-migrations      # Scheduling BC ⭐ (nouveau)
make shared-migrations          # Shared BC
```

---

*Document généré le 1er février 2026*
