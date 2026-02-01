# 🐾 Kiveto - Plateforme Vétérinaire Multi-Tenant

Kiveto est une application SaaS de gestion de cliniques vétérinaires construite avec une architecture **Domain-Driven Design (DDD)** et **CQRS**.

---

## 🏗️ Architecture

### Bounded Contexts (BCs)

Le projet est organisé en **Bounded Contexts** autonomes :

| BC | Description | Status |
|----|-------------|--------|
| **AccessControl** | Gestion des rôles et permissions (RBAC) | ✅ Production |
| **IdentityAccess** | Authentification et utilisateurs | ✅ Production |
| **Clinic** | Gestion des cliniques (multi-tenant) | ✅ Production |
| **Client** | Gestion des propriétaires d'animaux | ✅ Production |
| **Animal** | Gestion des animaux patients | ✅ Production |
| **Scheduling** | **Agenda & File d'attente** | ✅ **v1.0.0 (Nouveau !)** |
| **Translation** | Internationalisation (i18n) | ✅ Production |
| **Shared** | Abstractions communes (Bus, Clock, etc.) | ✅ Production |

### 🎉 Nouveau : Module Scheduling v1.0.0

Le module **Scheduling** (Agenda & File d'attente) est maintenant disponible ! 

**Fonctionnalités :**
- 📅 Gestion complète de l'agenda (RDV planifiés)
- 🏥 File d'attente en temps réel (Waiting Room)
- 🚨 Urgences walk-in avec priorités
- 📊 Système de triage clinique
- 🎨 Interface UI moderne et responsive

👉 **[Documentation complète](./src/Scheduling/INDEX.md)**

---

## 🚀 Stack Technique

- **Backend** : PHP 8.3+, Symfony 7.x
- **Frontend** : Twig, Stimulus, Turbo
- **Database** : MySQL 8.0+ (multi-database, 1 par BC)
- **Architecture** : DDD, CQRS, Hexagonal, Event-Driven
- **Tests** : PHPUnit, Foundry (fixtures)
- **CI/CD** : GitHub Actions
- **Docker** : php-fpm + nginx + mysql

---

## 📦 Installation

### Prérequis

- PHP 8.3+
- Composer 2.x
- MySQL 8.0+
- Node 18+ (pour assets)

### Setup

```bash
# Clone repository
git clone git@github.com:kiveto/kiveto.git
cd kiveto

# Install dependencies
composer install

# Setup environment
cp .env .env.local
# Edit .env.local with your database credentials

# Run migrations (all BCs)
php bin/console doctrine:migrations:migrate --all-or-nothing

# Load fixtures (dev only)
php bin/console doctrine:fixtures:load

# Start server
symfony server:start
```

### Hosts Configuration

Ajouter à `/etc/hosts` :

```
127.0.0.1 clinic.kiveto.local
127.0.0.1 portal.kiveto.local
127.0.0.1 backoffice.kiveto.local
```

---

## 🎯 URLs d'Accès

- **Clinic** (interface clinique) : http://clinic.kiveto.local
- **Portal** (interface clients) : http://portal.kiveto.local
- **Backoffice** (admin) : http://backoffice.kiveto.local

### Scheduling (nouveau)

- **Dashboard Agenda** : http://clinic.kiveto.local/scheduling/dashboard

---

## 🧪 Tests

```bash
# All tests
php bin/phpunit

# Specific BC
php bin/phpunit tests/Unit/Scheduling/

# With coverage
XDEBUG_MODE=coverage php bin/phpunit --coverage-html var/coverage
```

---

## 📚 Documentation

### Architecture Générale

- [Architecture DDD/CQRS](./docs/README_architecture_vet_saas.md)
- [Guide nouveau BC](./docs/GUIDE_NOUVEAU_BC.md)
- [Coding Rules](./docs/CODING_RULES.md)

### Bounded Contexts

- **AccessControl** : `src/AccessControl/README.md`
- **Clinic** : `src/Clinic/README.md`
- **Client** : `src/Client/README.md`
- **Animal** : `src/Animal/README.md`
- **Scheduling** : `src/Scheduling/INDEX.md` ⭐ **Nouveau !**
- **Translation** : `src/Translation/README.md`

### Frontend

- [Design System](./docs/FRONTEND_DESIGN_SYSTEM.md)
- [Layout 15 Implementation](./docs/LAYOUT15_IMPLEMENTATION.md)
- [Layout 16 Migration](./docs/LAYOUT16_MIGRATION_COMPLETE.md)

---

## 🛠️ Commandes Utiles

```bash
# Migrations
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:migrate

# Fixtures
php bin/console doctrine:fixtures:load --group=scheduling

# Code Quality
vendor/bin/phpcs src/
vendor/bin/phpstan analyse src/

# Cache
php bin/console cache:clear
php bin/console cache:warmup
```

---

## 📊 Statistiques Projet

```
┌─────────────────────────────────────────────┐
│  📦 Bounded Contexts      8                 │
│  💻 Lignes de Code        ~50,000           │
│  🧪 Tests Unitaires       300+              │
│  📖 Couverture            ~85%              │
│  📚 Pages Documentation   ~100              │
└─────────────────────────────────────────────┘
```

---

## 🏆 Nouveautés v1.0.0

### ✨ Module Scheduling (Février 2026)

Le module Scheduling établit un **nouveau standard de qualité** pour le projet :

- ✅ **Architecture DDD/CQRS exemplaire** (88% test coverage)
- ✅ **14 Commands + 6 Queries** (CQRS complet)
- ✅ **Interface UI moderne** (Dashboard + Waiting Room)
- ✅ **Documentation exhaustive** (13 docs, 50 pages)
- ✅ **Production ready** (0 bugs critiques)

👉 **[Release Notes Scheduling](./src/Scheduling/RELEASE_NOTES.md)**

---

## 🤝 Contribution

### Workflow

1. Créer une branche feature : `git checkout -b feature/my-feature`
2. Committer avec messages conventionnels : `feat(scheduling): add week view`
3. Push et créer PR
4. Review + CI/CD passe → Merge

### Standards

- **PSR-12** pour le code PHP
- **PHPStan Level 8** (strict types)
- **Tests unitaires** obligatoires pour Domain + Application
- **Documentation** Markdown pour chaque BC

---

## 📞 Support

- **Issues** : GitHub Issues
- **Documentation** : `/docs/` et `/src/{BC}/`
- **Email** : tech@kiveto.com

---

## 📄 Licence

Propriétaire - © 2026 Kiveto

---

## 🎉 Crédits

Développé avec ❤️ par l'équipe Kiveto.

**Excellence in Veterinary Software** 🐾

---

*README mis à jour le 1er février 2026*
