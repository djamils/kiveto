# 🐾 Kiveto - Plateforme Vétérinaire Multi-Tenant

Kiveto est une application SaaS de gestion de cliniques vétérinaires construite avec une architecture **Domain-Driven Design (DDD)** et **CQRS**.

Les règles de contribution (git, `make ci`, architecture, BMAD) sont dans [CONTRIBUTING.md](./CONTRIBUTING.md) ; les directives des agents IA dans [CLAUDE.md](./CLAUDE.md).

---

## 🏗️ Architecture

Monolithe DDD organisé en **Bounded Contexts**, chacun découpé en `Domain/`, `Application/` et `Infrastructure/`. La présentation est à part, dans `src/Presentation/` (`Clinic`, `Portal`, `Backoffice`, `Api`). Chaque contexte décrit son langage, ses invariants et ses cas d'usage dans son `README.md`.

| Groupe | Bounded Contexts |
|----|----|
| `src/Context/` (métier) | [Admission](./src/Context/Admission/README.md), [Animal](./src/Context/Animal/README.md), [Billing](./src/Context/Billing/README.md), [Catalog](./src/Context/Catalog/README.md), [Client](./src/Context/Client/README.md), [ClientPortal](./src/Context/ClientPortal/README.md), [Clinic](./src/Context/Clinic/README.md), [ClinicListing](./src/Context/ClinicListing/README.md), [Consultation](./src/Context/Consultation/README.md), [Inventory](./src/Context/Inventory/README.md), [Patient](./src/Context/Patient/README.md), [Prescription](./src/Context/Prescription/README.md), [Procurement](./src/Context/Procurement/README.md), [Regulatory](./src/Context/Regulatory/README.md), [Scheduling](./src/Context/Scheduling/README.md) |
| `src/System/` (transverses) | [AccessControl](./src/System/AccessControl/README.md), [ElectronicInvoicing](./src/System/ElectronicInvoicing/README.md), [IdentityAccess](./src/System/IdentityAccess/README.md), [Notification](./src/System/Notification/README.md), [PharmaceuticalRegistry](./src/System/PharmaceuticalRegistry/README.md), [Taxation](./src/System/Taxation/README.md), [Translation](./src/System/Translation/README.md) |
| `src/Shared/` | [Noyau partagé](./src/Shared/README.md) (bus, horloge, identifiants, `Money`, recherche…) |

---

## 🚀 Stack Technique

- **Backend** : PHP ≥ 8.5, Symfony 7.4, Doctrine ORM 3, API Platform 4 (API du Portal, JWT)
- **Frontend** : Twig, Stimulus, Turbo, Tailwind CSS v4 (AssetMapper, sans Node)
- **Database** : MySQL 8.4, une seule base ; tables préfixées par contexte (`clinic__…`) et migrations par contexte
- **Architecture** : DDD, CQRS, Hexagonal, Event-Driven
- **Tests** : PHPUnit 12, Foundry, DAMA DoctrineTestBundle
- **Qualité** : PHP-CS-Fixer, PHPCS (PSR-12), PHPStan niveau `max`, réunis dans la gate locale `make ci`
- **CI/CD** : pas encore de CI distante ; `make ci` se lance en local (voir [CONTRIBUTING.md §5](./CONTRIBUTING.md#5-local-dev--ci))
- **Docker** : php-fpm, nginx, MySQL, Gotenberg (PDF), Mailpit (e-mails de dev), phpMyAdmin, et les workers Messenger (profil `workers`)

---

## 📦 Installation

### Prérequis

- Docker avec Docker Compose v2
- GNU Make, bash et curl

PHP, Composer et MySQL tournent dans les conteneurs.

### Setup

```bash
git clone https://github.com/djamils/kiveto.git
cd kiveto
make install
```

`make install` construit les images, démarre les conteneurs, installe les dépendances Composer, génère les clés JWT du Portal, crée et migre les bases de dev et de test, charge les fixtures et compile Tailwind. `make help` liste les autres cibles.

### Hosts Configuration

Ajouter à `/etc/hosts` :

```
127.0.0.1 clinic.kiveto.local
127.0.0.1 portal.kiveto.local
127.0.0.1 backoffice.kiveto.local
```

---

## 🎯 URLs d'Accès

- **Clinic** (interface clinique) : http://clinic.kiveto.local:81
- **Portal** (interface clients et API `/api/portal/v1`) : http://portal.kiveto.local:81
- **Backoffice** (admin) : http://backoffice.kiveto.local:81
- **Mailpit** (e-mails envoyés en dev) : http://localhost:8025
- **phpMyAdmin** : http://localhost:8081

---

## 🧪 Tests

```bash
make test               # toutes les suites
make test-unit          # tests/Unit
make test-integration   # tests/Integration
make test-coverage      # rapport HTML de couverture dans coverage/
```

L'objectif est de 100 % de couverture de lignes par contexte (hors Presentation). C'est une règle vérifiée par l'auteur et la revue avec `make test-coverage` : `make ci` ne mesure pas la couverture (voir [CONTRIBUTING.md](./CONTRIBUTING.md#test-coverage-policy)).

---

## 📚 Documentation

- [CONTRIBUTING.md](./CONTRIBUTING.md) : git flow, `make ci`, contrôles automatisés et règles restant manuelles, architecture, BMAD, Definition of Done
- [CLAUDE.md](./CLAUDE.md) : directives non négociables pour les agents IA
- [Contexte projet pour les agents](./_bmad-output/project-context.md) : règles de code PHP, Symfony, DDD et tests
- [Contrat de l'API Portal](./openapi/portal-api-v1.md) et son export OpenAPI [`openapi/portal.v1.yaml`](./openapi/portal.v1.yaml)
- [Feuille de route de l'UI Kit](./_bmad-output/ui-kit-roadmap.md)
- Les `README.md` de chaque contexte (tableau ci-dessus)

Le dossier `docs/` (notes historiques, maquettes) est ignoré par git et n'existe que sur les postes qui l'ont.

---

## 🛠️ Commandes Utiles

```bash
make start / make stop            # démarrer / arrêter la stack
make ci                           # gate qualité complète (obligatoire avant chaque commit)
make phpstan                      # analyse statique
make phpcs                        # PSR-12 (seules les erreurs font échouer)
make php-cs-fixer && make phpcbf  # corriger le style
make reset-db                     # recréer la base de dev et charger les fixtures
make load-fixtures                # recharger les fixtures
make assets                       # recompiler Tailwind (AssetMapper sert assets/ à jour)
make worker-start / worker-logs   # workers Messenger et Scheduler
make openapi-export               # régénérer openapi/portal.v1.yaml
make openapi-check                # vérifier que le contrat correspond au code
```

---

## 🤝 Contribution

### Workflow

1. Créer une branche depuis `master` : `git checkout -b feature/my-feature master` (types `feature/`, `fix/`, `hotfix/`, `chore/`)
2. Committer avec messages conventionnels : `feat(scheduling): add week view`
3. Lancer `make ci` en local : il doit être vert
4. Push et créer la PR vers `master` → review → merge

Le détail est dans [CONTRIBUTING.md](./CONTRIBUTING.md).

### Standards

- **PSR-12** pour le code PHP (PHP-CS-Fixer + PHPCS)
- **PHPStan niveau `max`**
- **Couverture de 100 %** par contexte hors Presentation (règle, pas encore un seuil automatisé)
- **Documentation** Markdown pour chaque BC

---

## 📞 Support

- **Issues** : GitHub Issues
- **Documentation** : `CONTRIBUTING.md` et `src/{Context,System}/<BC>/README.md`
- **Email** : tech@kiveto.com

---

## 📄 Licence

Propriétaire - © 2026 Kiveto

---

## 🎉 Crédits

Développé avec ❤️ par l'équipe Kiveto.

**Excellence in Veterinary Software** 🐾

---

*README mis à jour le 2 novembre 2026*
