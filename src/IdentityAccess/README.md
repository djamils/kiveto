# IdentityAccess Bounded Context

Ce BC gère les utilisateurs, l'inscription et les métadonnées d'identité.

## Structure
```
src/IdentityAccess/
├── Domain/
│   ├── User.php
│   ├── UserId.php
│   ├── Event/UserRegistered.php
│   └── Repository/UserRepositoryInterface.php
├── Application/
│   ├── Command/RegisterUser/RegisterUser.php
│   ├── Command/RegisterUser/RegisterUserHandler.php
│   ├── Query/GetUserDetails/GetUserDetails.php
│   ├── Query/GetUserDetails/GetUserDetailsHandler.php
│   └── Query/GetUserDetails/UserDetails.php
└── Infrastructure/
    └── Repository/InMemoryUserRepository.php
```

## Domain Events
- `UserRegistered` : type `identity-access.user.registered.v1`

## Flux d'inscription
1. Commande `RegisterUser` (dossier par use-case)
2. Handler génère `userId`, `occurredAt`
3. Création de l'agrégat `User::register(...)` (événement pur)
4. Enregistrement du domaine via `UserRepositoryInterface`
5. Publication ultérieure via enveloppe (`DomainEventMessageFactory`) après `pullDomainEvents()`

## Tests
- `tests/Unit/IdentityAccess/Domain/UserTest.php`
- `tests/Unit/IdentityAccess/Application/RegisterUserHandlerTest.php`
- `tests/Unit/IdentityAccess/Application/GetUserDetailsHandlerTest.php`

