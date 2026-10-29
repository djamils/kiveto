# Kiveto Portal — Contrat API v1

Oct 29, 2026 · @Djamil — révisé le même jour pour l'aligner sur le code et la maquette (tech-spec `portal-api-v1-foundation-invitation`).

## Vision et choix structurants

Le contrat d'API précède le code : un fichier OpenAPI 3.1 versionné dans le repo backend est la source de vérité, le backend DDD l'implémente, l'app Flutter génère son client depuis lui.

- **API Platform, contrat généré puis figé.** Les ressources API Platform sont des DTO de `src/Presentation/Api/Portal/Resource`, jamais des entités Doctrine. Chaque opération a **exactement un** State Provider ou **un** State Processor, qui traduit HTTP ⇄ requêtes/commandes sans logique métier ; un processor peut enchaîner une commande puis l'émission de session (ex. vérification du code puis `IssuePortalSession`). C'est ainsi que l'API respecte la règle « une action par classe ». Trois routes restent des contrôleurs Symfony `__invoke` (fichier, carnet, `.ics`). L'OpenAPI exporté (`make openapi-export`) est commité dans `openapi/portal.v1.yaml` ; `make openapi-check` (lancé par `make ci`) échoue s'il diverge du code. Le client Dart en est généré (`openapi-generator`, cible `dart-dio`).
- **Repo Flutter séparé.** Il consomme une version taguée du YAML commité. Un changement d'API = une PR backend qui modifie les DTO et le YAML ensemble ; le diff du YAML sert de revue du contrat.
- **Mobile d'abord.** iOS et Android en Flutter. La version web du Portal vient après, en Symfony Twig/Turbo sur les mêmes handlers (pas Flutter web) : les fiches de l'annuaire doivent être indexables, ce que Flutter web fait mal.
- **Le propriétaire est un `User` de type `PORTAL`.** Pas d'agrégat `OwnerAccount` : l'identité vit dans `System/IdentityAccess` (`User` de type `PORTAL`, identifiant de connexion = téléphone E.164, sans mot de passe). Prénom, nom et adresse vivent dans le profil propriétaire (`OwnerProfile`) du contexte `Context/ClientPortal`.
- **Le Portal ne touche jamais aux agrégats cliniques directement.** Il lit les autres contextes via leurs bus de requêtes (ports applicatifs) ou via des projections `client_portal__*` alimentées par événements d'intégration, et n'y écrit que par leurs commandes. Ce que la clinique n'a pas publié n'existe pas pour le propriétaire.
- **Ce qui modifie le dossier tenu par la clinique est une demande.** Modification d'animal, renouvellement, questionnaire, rattachement : le Portal crée une demande, la clinique la traite dans VetApp. Écrivent directement, par commande sur le contexte concerné : la prise, le report et l'annulation de RDV (Scheduling), les consentements (Client), le profil, les préférences et les appareils du compte (ClientPortal).
- **Les consentements restent dans `Client`.** Ils prolongent `Client.communicationPreferences` (mêmes `ConsentKind`) avec un historique append-only. Notification ne lit jamais les consentements : c'est l'appelant qui les applique avant de demander un envoi.

## Conventions transverses

Toutes les routes vivent sous `/api/portal/v1` sur l'hôte du Portal, en JSON, authentifiées par jeton Bearer sauf mention « publique ». Pas de CORS (app native).

| Sujet | Règle |
| --- | --- |
| Authentification | Access token JWT 15 min (`sub` = id du `User` PORTAL, `typ` = `portal`) + refresh token opaque **rotatif** (haché en base, nouveau jeton à chaque usage, expiré après 90 j d'inactivité). Pas de mot de passe : connexion par code SMS. |
| Rafraîchissement | **Le client doit sérialiser ses refresh** (un seul refresh en vol dans l'intercepteur Dio). Filet de sécurité : rejouer le même refresh token dans les 30 s produit **une seule** nouvelle paire de la même famille ; au-delà (> 30 s, successeur déjà utilisé, ou deuxième rejeu), toute la famille est révoquée (`401 refresh_revoked`). Un compte qui n'est plus actif révoque sa famille au refresh suivant. |
| Paires multiples | Un utilisateur déjà connecté qui accepte une invitation (par le lien ou par jeton d'onboarding) reçoit une nouvelle paire ; l'ancienne reste valide jusqu'à son expiration. **L'app garde toujours la paire la plus récente.** |
| Multi-tenant | Le compte est global. Toute donnée clinique passe par `/clinics/{clinicId}/…` ; le serveur vérifie un `ClientLink` **confirmé** entre le compte et ce tenant, sinon 404 (jamais 403, pour ne pas révéler l'existence d'une ressource). Un lien révoqué n'est jamais exposé. |
| Identifiants | UUID v7 en chaîne. |
| Dates | ISO 8601 avec décalage (`2026-11-04T10:30:00+01:00`) ; dates seules en `YYYY-MM-DD`. Tranche 1 : horodatages émis en UTC (`+00:00`). Données cliniques (tranches 2+) : fuseau de la clinique, renvoyé dans son profil. |
| Montants | `{ "amount": 6800, "currency": "EUR" }` en unités mineures, jamais de float. |
| Poids | `weightGrams` entier. |
| Enums | Codes `snake_case` stables ; libellés traduits côté Flutter. Exception : données configurées par la clinique (motifs, praticiens, tags) renvoyées avec leur libellé. |
| Erreurs | `application/problem+json` (RFC 9457), `type` = code métier. Toute erreur de validation est une 422 avec `type` = `validation_failed` et `violations[]` (`propertyPath`, `message`, `code`) — pas `errors[]` ; le serveur convertit le format natif d'API Platform. Liste des codes ci-dessous. |
| Secrets | **Aucun secret dans un chemin d'URL.** Jetons d'invitation, d'onboarding et de refresh voyagent uniquement dans le corps des requêtes (API) ou dans le fragment `#t=` du lien d'invitation. Le YAML exporté ne contient aucun paramètre de chemin `{token}`. |
| Une forme par route | Chaque route a un seul schéma d'entrée, champs requis non nullables, **pas de `oneOf`**. |
| Pagination | Curseur opaque : `?cursor=&limit=` → `{ "items": [], "nextCursor": null }`. Limite par défaut 20 ; toute valeur au-delà de 50 est ramenée à 50. Curseur altéré → `400 cursor_invalid`. Toute route qui accepte `cursor` répond avec cette enveloppe. |
| Idempotence | En-tête `Idempotency-Key` obligatoire sur les POST qui créent (RDV, demandes) : même clé + même corps = même réponse rejouée pendant 24 h ; même clé + autre corps = `422 idempotency_key_reused` ; en-tête absent = `400 idempotency_key_missing`. Les deux routes d'acceptation d'invitation n'en utilisent pas : elles ne sont pas idempotentes par nature mais sont rendues rejouables par la règle de rejeu (voir « Rejeu »). |
| Fichiers | `GET …/file` répond 302 vers une URL S3 signée valable 5 min. |
| Versions | Rupture de contrat = `/v2`. Ajout de champ = compatible ; le client Dart ignore les champs inconnus. |
| Notifications | Notification n'applique aucun consentement ; le contexte appelant vérifie le consentement avant de demander un envoi. Les SMS d'authentification (`PORTAL_OTP`) et d'invitation (`PORTAL_INVITATION`) sont des messages critiques envoyés par Kiveto. |

### Limitation de débit

Les quotas sont consommés **avant** l'exécution de la commande : une requête qui échoue ensuite (ex. `phone_not_supported`) a quand même consommé son quota. Dépassement = `429 otp_rate_limited` avec en-tête `Retry-After`.

| Limiteur | Politique | Clé | Routes |
| --- | --- | --- | --- |
| par numéro | 3 / heure (fenêtre fixe) | téléphone | `/auth/otp/request`, `/auth/otp/{challengeId}/resend`, `/invitations/otp` |
| par numéro, journalier | 10 / 24 h (fenêtre glissante) | téléphone | les trois mêmes — les renvois ne sont pas gratuits |
| par IP | 30 / heure | IP cliente | les trois mêmes (30 et non 10 : CGNAT des opérateurs mobiles) |
| par invitation | 3 / heure | invitation | `/invitations/otp` |
| par challenge | 3 / heure | challenge | `/auth/otp/{challengeId}/resend`, consommé seulement après résolution du challenge : un challenge inconnu répond 404 après le seul quota par IP, sans consommer les quotas par numéro ni celui du challenge |
| budget numéros inconnus | 200 / heure, global (configurable) | — | `/auth/otp/request` uniquement, pour un numéro sans compte actif **et** sans invitation en attente. Budget épuisé : même 202, challenge créé mais marqué « supprimé », aucun SMS envoyé, alerte côté serveur. Les renvois (`resend`) d'un challenge supprimé répondent le même 202 et n'envoient jamais de SMS. Les codes d'invitation ne consomment jamais ce budget. |

Essais : 5 par code. Le code (6 chiffres) est valable 10 min, renvoi possible après 30 s.

### Numéros acceptés

Seuls les numéros mobiles reconnus par la règle `MobileNumberPolicy` du contexte Client (préfixes mobiles de France métropolitaine et d'outre-mer, dont `+262 69`, `+508 55`, `+681`, `+687 7–9`, `+689 87–89`) peuvent se connecter ou être invités. Tout autre numéro → `422 phone_not_supported` sur `/auth/otp/request`. Élargir la couverture (Belgique, Suisse…) = ajouter des préfixes à cette règle, ce qui élargit invitations et API en même temps. Un mobile étranger identifié seulement par son libellé « mobile » sur la fiche client ne peut pas encore être invité.

### Codes d'erreur

| Code | HTTP | Contexte |
| --- | --- | --- |
| `unauthorized` | 401 | Jeton absent, invalide, ou utilisateur non PORTAL / non actif |
| `token_expired` | 401 | Access token expiré → appeler `/auth/refresh` |
| `refresh_revoked` | 401 | Refresh inconnu, expiré, révoqué, réutilisé, ou compte désactivé → reconnexion par code |
| `otp_invalid` | 422 | Code faux, déjà utilisé, challenge d'une autre finalité / invitation, ou challenge inconnu sur `otp/verify` |
| `otp_expired` | 422 | Code périmé (> 10 min) |
| `otp_attempts_exceeded` | 422 | 5 essais atteints → redemander un code |
| `otp_rate_limited` | 429 | Quota dépassé, voir `Retry-After` |
| `otp_challenge_not_found` | 404 | Challenge inconnu sur `resend` |
| `phone_not_supported` | 422 | Numéro hors couverture |
| `invitation_invalid` | 404 | Invitation inconnue, jeton remplacé par un renvoi, invitation non liée au téléphone prouvé, ou (route par jeton d'onboarding) invitation plus en attente alors que le numéro en a d'autres |
| `invitation_expired` | 410 | Invitation de plus de 7 jours (lookup, code et acceptation par lien) |
| `invitation_already_used` | 410 | Invitation acceptée par un autre compte ou révoquée (lookup, code et acceptation par lien ; une acceptation par le même compte est un rejeu) |
| `onboarding_invalid` | 401 | Jeton d'onboarding inconnu, expiré (> 15 min) ou révoqué, ou plus aucune invitation en attente pour ce numéro — y compris après une acceptation dont la réponse s'est perdue : refaire la connexion par numéro |
| `account_disabled` | 403 | Compte PORTAL désactivé |
| `terms_outdated` | 422 | `termsVersion` différente de la version courante |
| `idempotency_key_missing` | 400 | En-tête absent sur une route idempotente |
| `idempotency_key_reused` | 422 | Même clé, corps différent |
| `cursor_invalid` | 400 | Curseur altéré |
| `validation_failed` | 422 | Toute erreur de validation, avec `violations[]` ; c'est le seul `type` d'une 422 de validation |

Codes des tranches suivantes, détaillés avec leurs routes : `slot_taken`, `cancellation_window_closed`, `questionnaire_locked`, `prescription_not_renewable`.

Configuration API Platform : format `json` seul (JSON-LD/Hydra désactivés), erreurs en `problem+json`, préfixe `/api/portal/v1` sur l'hôte Portal uniquement, pagination désactivée (curseur implémenté dans les providers), documentation (`/api/portal/v1/docs`) servie en dev et absente en prod. Les trois routes hors API Platform (`…/documents/{id}/file`, `…/vaccination-booklet` en 302, `…/calendar.ics` en `text/calendar`) sont documentées par un décorateur d'`OpenApiFactory`.

## Onboarding : comment un propriétaire arrive

Le chemin universel ne dépend ni du lien ni de l'installation préalable de l'app :

1. La clinique clique « Inviter au Portal » sur la fiche client (SMS ou e-mail). Le propriétaire reçoit un lien `https://<portal>/invitation#t=<token>` valable 7 jours.
2. **Chemin universel** — installer l'app → saisir son numéro → recevoir un code → l'app propose l'invitation (`invitation_pending`) → accepter les CGU. Aucune dépendance au lien : un SMS perdu, une app installée après coup, tout passe par là.
3. **Raccourci** — si l'app est déjà installée, le lien l'ouvre directement (universal link iOS / app link Android) ; l'app lit le jeton dans le fragment `#t=`, affiche l'invitation, demande un code envoyé au mobile de l'invitation, puis accepte.

Côté hôte Portal :

- `/.well-known/apple-app-site-association` et `/.well-known/assetlinks.json` : publics, `application/json`, sans redirection, générés depuis la configuration (identifiant d'app iOS, package Android, empreintes de certificat), chemin `/invitation*`.
- `/invitation` sans l'app : page statique en français avec les boutons App Store / Play Store et la phrase « Installez l'app puis connectez-vous avec votre numéro », en-tête `Referrer-Policy: no-referrer`. Elle ne lit jamais le fragment ; le jeton n'atteint donc jamais le serveur web (absent des logs et de tout `Referer`). Pas de deep link différé.

Une invitation acceptée crée directement un `ClientLink` **confirmé**. Si un compte PORTAL actif existe déjà pour ce numéro, la clinique lui est rattachée (pas de doublon) ; s'il est désactivé, l'acceptation est refusée (`403 account_disabled`) et l'invitation reste en attente. À la création, l'e-mail de la fiche client est recopié sur le compte s'il n'est pas déjà pris par un autre compte PORTAL (membres d'une famille partageant une adresse) ; il reste non vérifié.

**Rejeu.** Si la réponse d'une acceptation est perdue, l'app renvoie exactement la même requête. Par le lien : même challenge et même code, avant l'expiration d'origine du code. Par jeton d'onboarding : même jeton, tant qu'il est encore valide. Le serveur reconnaît l'invitation déjà acceptée par ce même compte et renvoie une paire de jetons neuve, sans rien recréer ni compter d'essai.

Accepter la **dernière** invitation en attente d'un numéro révoque son jeton d'onboarding. Dans le cas courant (une seule invitation), le rejeu par jeton reçoit donc `401 onboarding_invalid` : l'app refait alors la connexion par numéro (`/auth/otp/request` puis `/auth/otp/verify`) ; le compte existe désormais et la réponse est `authenticated`.

**Limite de la tranche 1.** Un utilisateur déjà connecté ne voit une nouvelle invitation qu'à sa prochaine connexion par code (`pendingInvitations[]` de `authenticated`) ; aucune route ne liste les invitations en attente d'un compte connecté.

## Carte écran → endpoints

Les 18 écrans de la maquette consomment 54 routes (dont 1 post-alpha) ; l'accueil est le seul écran servi par un endpoint agrégé, pour s'afficher en un appel. Chemins relatifs à `/api/portal/v1`, `{c}` = `clinicId`, `{l}` = `listingId`.

| # | Écran | Lecture | Écriture |
| --- | --- | --- | --- |
| 0 | Activation (invitation) | `POST /invitations/lookup`, `GET /legal/terms/current` | `POST /invitations/otp`, `POST /invitations/accept`, `POST /invitations/{invitationId}/accept` (après connexion par numéro) |
| 1 | Accueil | `GET /clinics/{c}/home`, `GET /me` | — |
| 2 | Fiche animal | `GET /clinics/{c}/pets`, `GET /clinics/{c}/pets/{id}`, `GET …/pets/{id}/care-items`, `GET …/pets/{id}/timeline`, `GET …/pets/{id}/vaccination-booklet` | `POST …/pets/{id}/change-requests` |
| 3 | RDV étape 3 — créneau | `GET /clinics/{c}/booking/practitioners`, `GET /clinics/{c}/booking/availability` | — |
| 4 | Mes rendez-vous | `GET /clinics/{c}/appointments?scope=upcoming\|past` | `POST …/appointments/{id}/reschedule` |
| 5 | Documents | `GET /clinics/{c}/documents`, `GET …/documents/{id}/file` | — |
| 6 | Compte & consentements | `GET /me`, `GET /clinics/{c}/consents`, `GET /clinics/{c}/consents/history`, `GET /me/notification-preferences` | `PATCH /me`, `PUT /clinics/{c}/consents/{kind}`, `PUT /me/notification-preferences`, `POST /me/data-export`, `POST /me/deletion-request`, `POST /auth/logout` |
| 7 | Bienvenue | — | `POST /auth/otp/request` |
| 8 | Saisie du code | — | `POST /auth/otp/verify`, `POST /auth/otp/{challengeId}/resend` (SMS seulement ; l'option « Recevoir par e-mail » est masquée en tranche 1) |
| 9 | Inscription | `GET /legal/terms/current` | `POST /auth/register` |
| 10 | Trouver une clinique | `GET /clinic-listings`, `GET /me/link-suggestions` | `POST /me/link-suggestions/{id}/accept`, `POST /clinic-listings/{l}/notify-me` (post-alpha) |
| 11 | Fiche clinique | `GET /clinic-listings/{l}`, `GET /clinic-listings/{l}/booking/motifs`, `GET /clinic-listings/{l}/booking/practitioners`, `GET /clinic-listings/{l}/booking/availability` | `POST /clinic-listings/{l}/booking/appointments` |
| 12 | RDV étape 1 — animal | `GET /clinics/{c}/pets` | — |
| 13 | RDV étape 2 — motif | `GET /clinics/{c}/booking/motifs?petId=` | — |
| 14 | RDV étape 4 — récap | — | `POST /clinics/{c}/appointments` ou `POST /clinics/{c}/appointments/with-new-pet`, `GET …/appointments/{id}/calendar.ics` |
| 15 | Questionnaire | `GET /clinics/{c}/appointments/{id}/questionnaire` | `PUT …/appointments/{id}/questionnaire` |
| 16 | Annulation | `GET /clinics/{c}/appointments/{id}` | `POST …/appointments/{id}/cancel` |
| 17 | Renouvellement | `GET /clinics/{c}/prescriptions/{id}` | `POST …/prescriptions/{id}/renewal-requests` |
| — | Hors écran | — | `POST /auth/refresh`, `POST /me/devices`, `DELETE /me/devices/{id}` (push) |

## Endpoints détaillés

Chaque route indique son entrée, sa réponse et les erreurs métier que Flutter doit gérer ; les formes complètes vont dans le YAML.

### Authentification, invitation, CGU (tranche 1, publiques sauf logout)

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `POST /auth/otp/request` | `phone` (E.164) | 202 `challengeId`, `expiresAt` (+10 min), `resendAvailableAt` (+30 s) | 422 `phone_not_supported`, 429 `otp_rate_limited`. Réponse identique que le numéro ait un compte ou non. |
| `POST /auth/otp/{challengeId}/resend` | `channel` — enum `[sms]` en tranche 1 | 202 même corps ; nouveau code, l'ancien ne vérifie plus | 404 `otp_challenge_not_found`, 429 `otp_rate_limited` (avant `resendAvailableAt` ou quotas). Toute autre valeur de `channel` → 422 `violations[]`. `email` rejoindra l'enum avec la vérification d'e-mail (`PATCH /me`) : il n'enverra qu'à l'e-mail vérifié du compte actif de ce numéro, avec le même 202 dans tous les cas. |
| `POST /auth/otp/verify` | `challengeId`, `code` | 200, selon `status` : **`authenticated`** → `accessToken`, `accessTokenExpiresAt`, `refreshToken`, `refreshTokenExpiresAt`, `pendingInvitations[]` (`invitationId`, `clinic { name, city }`), `onboardingToken` (ou `null`), `onboardingTokenExpiresAt` (ou `null`) · **`invitation_pending`** (numéro sans compte avec invitation en attente) → `onboardingToken`, `onboardingTokenExpiresAt` (+15 min), `invitations[]` (même forme que `pendingInvitations[]`) · **`registration_required`** → `registrationToken: null` (rempli en tranche 7) | 422 `otp_invalid` (y compris code déjà utilisé et `challengeId` inconnu), `otp_expired`, `otp_attempts_exceeded` ; 403 `account_disabled` (compte désactivé : ni jetons, ni onboarding). Les invitations expirées ou révoquées ne sont jamais listées. |
| `POST /invitations/lookup` | `token` | 200 `invitationId`, `clinic { id, name, city }`, `email` (ou `null`), `maskedPhone`, `petNames[]` (animaux actifs), `expiresAt` | 404 `invitation_invalid`, 410 `invitation_expired`, 410 `invitation_already_used` |
| `POST /invitations/otp` | `token` | 202 même corps que `/auth/otp/request` ; code envoyé au mobile de l'invitation | Mêmes erreurs que `lookup` + 429. Ne consomme jamais le budget numéros inconnus. |
| `POST /invitations/accept` | `token`, `challengeId`, `code`, `termsVersion` | 200 `accessToken`, `accessTokenExpiresAt`, `refreshToken`, `refreshTokenExpiresAt`, `account` ; `ClientLink` confirmé | 422 `otp_invalid` (challenge de connexion ou d'une autre invitation : essai compté), `otp_expired`, `otp_attempts_exceeded`, `terms_outdated` ; 404 `invitation_invalid` (jeton inconnu ou remplacé par un renvoi) ; 410 `invitation_expired` ; 410 `invitation_already_used` (acceptée par un autre compte ou révoquée) ; 403 `account_disabled`. Rendue rejouable (voir « Rejeu »). |
| `POST /invitations/{invitationId}/accept` | `onboardingToken`, `termsVersion` | 200 même corps que ci-dessus | 401 `onboarding_invalid` (jeton invalide, ou invitation plus en attente alors qu'il n'en reste aucune pour ce numéro) ; 404 `invitation_invalid` (inconnue, liée à un autre numéro que celui du jeton, ou plus en attente alors que le numéro en a d'autres) ; 422 `terms_outdated` ; 403 `account_disabled`. Le jeton d'onboarding n'est accepté **que** par cette route ; il reste valable pour les autres invitations en attente du même numéro et est révoqué quand il n'en reste plus. Rendue rejouable tant que le jeton vit (voir « Rejeu »). |
| `POST /auth/refresh` | `refreshToken` | 200 nouvelle paire (rotation) | 401 `refresh_revoked`. Un access token expiré envoyé en `Authorization` est ignoré sur cette route comme sur toutes les routes publiques. |
| `POST /auth/logout` | `refreshToken` (authentifiée) | 204 ; famille du jeton révoquée si elle appartient à l'appelant, sinon rien | 401 `unauthorized`, `token_expired`. L'access token reste valable jusqu'à son expiration (15 min). |
| `GET /legal/terms/current` | — | `version`, `url` | Publique |
| `POST /auth/register` | `registrationToken`, `firstName`, `lastName`, `email?`, `termsVersion` | 201 jetons + `account` | Tranche 7, publique. 422 `terms_outdated`. |

### Compte, rattachement, appareils

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /me` | — | `account { id, firstName, lastName, email (nullable), maskedPhone, address }`, `clinics[] { clinicId, name, linkStatus, linkedAt, petNames[], tags[], unreadDocuments }` | Tranche 1 : `linkStatus` = `confirmed`, `tags: []`, `unreadDocuments: 0` (rempli en tranche 2). `pending` apparaît avec les suggestions de rattachement (tranche 7). Alimente la pastille de l'onglet Documents. Ne liste pas les invitations en attente (voir « Onboarding »). |
| `PATCH /me` | `firstName?`, `lastName?`, `address?` ; plus tard `email` avec vérification par code | 200 `account` | Tranche 5. Changement d'e-mail vérifié : hors tranche 1 ; il active le canal e-mail de `resend`. Téléphone : non modifiable. |
| `GET /me/link-suggestions` | — | `[{ id, clinic { name, city }, matchedOn: phone }]` | Tranche 7. Fiches `Client` dont le mobile correspond au téléphone vérifié. |
| `POST /me/link-suggestions/{id}/accept` | — | 202 `linkStatus: pending` | Tranche 7. La clinique confirme dans VetApp. |
| `GET /me/notification-preferences` · `PUT` | `channels[]` (`sms`, `email`, `push`) | 200 | Tranche 5. Au moins un canal. |
| `POST /me/devices` | `platform` (`ios`\|`android`), `pushToken` | 201 `deviceId` | Tranche 5. Upsert sur le jeton. |
| `DELETE /me/devices/{id}` | — | 204 | Tranche 5. Appelé au logout. |
| `POST /me/data-export` | — | 202 ; lien envoyé par e-mail | Tranche 5. 1 par 24 h |
| `POST /me/deletion-request` | — | 202 | Tranche 5. Anonymisation ; factures conservées par snapshot |

### Consentements (par clinique, tranche 5)

Les consentements sont ceux de `Client.communicationPreferences` : un consentement par `kind` couvre une liste de canaux. Valeurs de `kind` : `appointment_reminders`, `vaccination_reminders`, `clinical_documents`, `billing`, `marketing`. Canaux : `sms`, `email`, `phone`, `mail`.

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /clinics/{c}/consents` | — | `preferredChannel`, `consents[{ kind, channels[], grantedAt }]` | Un `kind` absent = non consenti |
| `PUT /clinics/{c}/consents/{kind}` | `channels[]` (vide = retrait) | 200 | Chaque changement ajoute des lignes d'historique. Ajouter un canal = nouveau consentement daté du jour. |
| `GET /clinics/{c}/consents/history` | `cursor` | `items[{ kind, channel, action: granted\|withdrawn, at, source: portal\|clinic, author }]`, `nextCursor` | Append-only ; `source: clinic` = saisie au comptoir dans VetApp. Lien « Historique » de l'écran 6. |

### Accueil et animaux (tranche 3)

Les animaux exposés sont les `Animal` dont le client est propriétaire principal ; les patients cliniques réconciliés sont rapportés à leur animal dans les projections.

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /clinics/{c}/home` | — | `nextAppointment?`, `pets[]` (résumé), `careItems[]` (3 max, les plus proches), `latestDocuments[]` (2 max) | Sections vides = tableau vide ; l'app masque la section |
| `GET /clinics/{c}/pets` | — | `[{ id, name, species, breed, sex, sterilization, birthDate, initial }]` | `sterilization` : `intact`, `neutered`, `unknown` |
| `GET /clinics/{c}/pets/{id}` | — | + `lastWeight { weightGrams, at }`, `chipNumberMasked`, `referentVet` | — |
| `GET /clinics/{c}/pets/{id}/care-items` | — | `[{ kind, label, lastDoneOn?, dueOn, status }]` | Source : date de prochaine échéance (`nextDueOn`) des soins préventifs enregistrés. `status` : `up_to_date`, `due_soon`, `overdue`, `booked`. |
| `GET /clinics/{c}/pets/{id}/timeline` | `cursor` | `items[{ visitId, date, vet, title, documents[{ id, type }] }]`, `nextCursor` | Seulement les visites publiées |
| `GET /clinics/{c}/pets/{id}/vaccination-booklet` | — | 302 vers PDF | Contrôleur `__invoke`. Généré à la volée (Gotenberg), mis en cache. |
| `POST /clinics/{c}/pets/{id}/change-requests` | `field` (`name`, `weight`, `sterilization`, `chip_number`, `other`), `proposedValue`, `note?` | 202 `requestId` | Tranche 6. Idempotency-Key. |

### Prise de RDV et rendez-vous (tranche 4)

Le motif de réservation s'appelle `BookingMotif` côté code. Le praticien est obligatoire : l'app le fait choisir (ou propose « premier disponible » en le résolvant côté client) avant d'afficher les créneaux.

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /clinics/{c}/booking/motifs` | `petId?` | `[{ id, label, description, durationMin, suggested, suggestionReason? }]` | Seulement les motifs ouverts en ligne ; `suggested` dérivé des `care-items` en retard |
| `GET /clinics/{c}/booking/practitioners` | `motifId` | `[{ id, displayName }]` | Praticiens réservables en ligne pour ce motif |
| `GET /clinics/{c}/booking/availability` | `motifId`, `practitionerId`, `from`, `to` (14 j max) | `days[{ date, open, slots[{ startAt }] }]` | Seulement les créneaux réservables |
| `POST /clinics/{c}/appointments` | `petId`, `motifId`, `practitionerId`, `startAt` | 201 rendez-vous complet (même forme que `GET …/appointments/{id}`) ; `status` = `confirmed` ou `pending_clinic_confirmation` | 409 `slot_taken` → recharger les créneaux (garantie réelle contre la double réservation). `pending_clinic_confirmation` n'envoie aucun SMS. Idempotency-Key. |
| `POST /clinics/{c}/appointments/with-new-pet` | `newPet { name, species }`, `motifId`, `practitionerId`, `startAt` | 201 même réponse | Animal pas encore connu de la clinique. Mêmes erreurs et mêmes règles que la route précédente. Idempotency-Key. |
| `GET /clinics/{c}/appointments` | `scope` (`upcoming`\|`past`), `cursor` | `items[{ id, startAt, pet, motif, practitioner, status, questionnaireStatus, cancellableUntil }]`, `nextCursor` | — |
| `GET /clinics/{c}/appointments/{id}` | — | même forme + `relatedCareItem?` | Alimente l'avertissement de l'écran 16 |
| `POST …/appointments/{id}/reschedule` | `startAt`, `practitionerId` | 200 rendez-vous | 409 `slot_taken`, 422 `cancellation_window_closed` |
| `POST …/appointments/{id}/cancel` | `reason` (`unavailable`, `no_longer_needed`, `seen_elsewhere`, `other`) | 200 `status: cancelled_by_client` | 422 `cancellation_window_closed` |
| `GET …/appointments/{id}/calendar.ics` | — | `text/calendar` | Contrôleur `__invoke` |
| `GET …/appointments/{id}/questionnaire` | — | `{ version, status, answers? }` | Tranche 6. `status` : `not_started`, `submitted` |
| `PUT …/appointments/{id}/questionnaire` | `version: 1`, `symptoms[]` (`none` exclusif, `cough`, `vomiting`, `diarrhea`, `appetite_loss`, `itching`, `lameness`), `ongoingTreatment { has, details? }`, `temperament` (`calm`\|`stressed`\|`may_bite`), `notes?` | 200 | Tranche 6. Modifiable jusqu'au début du RDV ; 422 `questionnaire_locked` après |

### Documents et ordonnances (tranche 2)

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /clinics/{c}/documents` | `type?` (`report`, `prescription`, `invoice`, `lab_result`, `certificate`), `petId?`, `cursor` | `items[{ id, type, title, petIds[], issuedAt, unread, invoice?{ total, paymentStatus }, prescription?{ id, validUntil, renewable, renewalStatus? } }]`, `nextCursor` | `petIds[]` peut être vide (facture sans animal). Le détail par type évite un appel par ligne. |
| `GET /clinics/{c}/documents/{id}/file` | — | 302 URL signée | Contrôleur `__invoke`. Marque le document comme lu. |
| `GET /clinics/{c}/prescriptions/{id}` | — | `{ id, issuedAt, vet, pet, lines[{ label, quantity, posologySummary }] }` | Lignes affichées sur l'écran 17 |
| `POST /clinics/{c}/prescriptions/{id}/renewal-requests` | `petCondition` (`improved`\|`no_improvement`\|`adverse_effects`), `note?` | 202 `{ requestId, status: submitted }` | Tranche 6. Porte sur **toute** l'ordonnance (pas de `lineIds[]`) ; reste une demande validée par la clinique. 422 `prescription_not_renewable`. Idempotency-Key. |

### Annuaire des cliniques (tranche 7)

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /clinic-listings` | `q?`, `lat?`, `lng?`, `cursor` | `items[{ id, name, address, phone, distanceM?, onKiveto, onlineBooking, tags[], nextAvailableAt? }]`, `nextCursor` | Publique. Alpha : cliniques Kiveto uniquement |
| `GET /clinic-listings/{l}` | — | `{ name, address, phone, timezone, openingHours[], acceptsNewClients, onlineBooking, tags[] }` | Publique (servira les pages web indexables) |
| `GET /clinic-listings/{l}/booking/motifs` | — | même forme que `/clinics/{c}/booking/motifs` | Publique. Motifs ouverts aux nouveaux clients |
| `GET /clinic-listings/{l}/booking/practitioners` | `motifId` | même forme | Publique |
| `GET /clinic-listings/{l}/booking/availability` | `motifId`, `practitionerId`, `from`, `to` | même forme | Publique |
| `POST /clinic-listings/{l}/booking/appointments` | `newPet { name, species }`, `motifId`, `practitionerId`, `startAt` | 201 rendez-vous `pending_clinic_confirmation` | Authentifiée. Nouveau client sans lien avec la clinique ; ce qu'il voit de ce rendez-vous avant confirmation du rattachement est une décision ouverte. 409 `slot_taken`. Idempotency-Key. |
| `POST /clinic-listings/{l}/notify-me` | — | 202 | Post-alpha, authentifiée |

## Découpage DDD

Le Portal ajoute trois contextes (`ClientPortal`, `ClinicListing`, `Document`) et en étend cinq (`IdentityAccess`, `Client`, `Scheduling`, `Notification`, `Animal`) ; aucun contexte clinique n'expose ses agrégats au Portal.

Les lectures passent par les bus de requêtes des autres contextes ou par des projections ; les écritures du propriétaire deviennent des demandes traitées dans VetApp, sauf la réservation. Les noms d'événements sont ceux du code.

| Contexte | Statut | Agrégats et éléments | Événements |
| --- | --- | --- | --- |
| `System/IdentityAccess` | étendu | `User` de type `PORTAL` (téléphone obligatoire et unique par type, e-mail facultatif, sans mot de passe), `OtpChallenge` (code haché en HMAC, porte le jeton d'onboarding), familles de refresh tokens | publie `PortalUserRegistered` |
| `Context/ClientPortal` | nouveau | `PortalInvitation` (jeton haché propre, 7 jours, renvoi = même invitation, nouveau jeton), `ClientLink` (compte ↔ fiche `Client` d'une clinique, `pending`\|`confirmed`\|`revoked` interne), `OwnerProfile` (nom, adresse, CGU acceptées), puis `LinkSuggestion`, `ChangeRequest`, `RenewalRequest`, `PreVisitQuestionnaire`, `Device` | publie `PortalInvitationSent`, `PortalInvitationAccepted`, `ClientLinkConfirmed` (+ `ClientLinkConfirmedIntegrationEvent`), `ClientLinkRevoked` ; consomme `ClientArchivedIntegrationEvent` (invitations révoquées, lien révoqué ; le désarchivage ne restaure pas le lien) |
| `Context/Client` | étendu | `communicationPreferences` porte les consentements (`ConsentKind` existants) ; historique append-only (kind, canal, accordé/retiré, date, source `portal`\|`clinic`, auteur) ; requête `IsPortalMobileNumber` sur `MobileNumberPolicy` | publie `ClientCommunicationPreferencesUpdated` |
| `Context/Scheduling` | étendu | `OnlineBookingSettings` : motifs ouverts en ligne (`BookingMotif` avec `durationMin`, `description`), praticiens réservables, mode de confirmation, délai d'annulation. `Appointment` gagne `source: portal` et `cancellationReason`. La règle D1 du README (événements de domaine seulement) évolue : le Portal a besoin d'événements d'intégration. | publie `AppointmentScheduled`, `AppointmentRescheduled`, `AppointmentCancelled` |
| `Context/Document` | nouveau | publication explicite : factures et ordonnances publiées à l'émission, compte rendu et résultats sur action « Partager » du vétérinaire | publie `DocumentPublished` |
| `Context/ClinicListing` | nouveau | `ClinicListing` : projection des cliniques Kiveto, plus tard fiches non revendiquées | consomme les événements de profil de `Clinic` (`ClinicRenamed`, `ClinicOpeningHoursUpdated`, `ClinicLetterheadUpdated`…) |
| `Context/Animal` | étendu si besoin | `ListAnimalSummariesPerClientIds` limité aux animaux actifs, pour les noms d'animaux du Portal | — |
| `System/Notification` | étendu | finalités `PORTAL_OTP` (code, 10 min) et `PORTAL_INVITATION` (lien, 7 jours), envoyées par Kiveto | — |
| `Presentation/Api/Portal` | nouveau | ressources DTO, un State Provider ou un State Processor par opération, qui traduit HTTP ⇄ requêtes/commandes sans logique métier (un processor peut enchaîner une commande puis l'émission de session) ; trois contrôleurs `__invoke` pour fichiers et `.ics` | — |

`User`, `ClientLink`, `OwnerProfile` et `ClinicListing` sont hors tenant (tables globales) ; tout le reste est scopé par clinique. Projections : `client_portal__pets`, `client_portal__timeline_entries`, `client_portal__care_items`, `client_portal__appointments`, `client_portal__documents`, `clinic_listing__clinic_listings`.

## Ordre de développement

Sept tranches verticales, chacune livrée de bout en bout (contrat, backend, Flutter) avant la suivante ; les cliniques pilotes invitent leurs clients dès la tranche 1, l'inscription libre arrive en dernier.

1. **Socle et invitation.** API Platform, JWT + refresh rotatif, problem+json, idempotence, curseur, limiteurs ; connexion par code SMS ; invitation depuis la fiche client (« Inviter au Portal »), acceptation par lien ou par connexion au numéro, `ClientLink`, `GET /me` ; page `/invitation` et fichiers `.well-known`. Écrans 0, 7 (connexion seule), 8 et un accueil vide. Livrée en trois branches : contrat, socle d'authentification, invitation.
2. **Documents publiés.** Contexte `Document`, événement `DocumentPublished`, `client_portal__documents`, liste, fichier signé, pastille non lus. Écran 5 : la valeur la plus visible pour le coût le plus bas.
3. **Animaux et historique.** `client_portal__pets`, `client_portal__timeline_entries`, `client_portal__care_items`, carnet PDF, accueil complet. Écrans 1 et 2.
4. **Prise de RDV.** `OnlineBookingSettings`, motifs, praticiens, disponibilités, création, liste, report, annulation ; événements d'intégration Scheduling. Écrans 3, 4, 12, 13, 14, 16.
5. **Consentements et notifications.** Consentements par canal et historique dans `Client`, préférences de canal, appareils push. Écran 6. À livrer avant d'activer le moindre rappel.
6. **Demandes.** Questionnaire, modification d'animal, renouvellement, et leur file côté VetApp. Écrans 2, 15, 17.
7. **Arrivée organique.** Inscription libre (`registrationToken`), suggestions de rattachement, `ClinicListing`, fiche clinique, RDV nouveau client. Écrans 7, 9, 10, 11.

## Décisions ouvertes

Six points restent à trancher ; chacun a une proposition par défaut pour ne pas bloquer le développement. Tranchés depuis la première version : identité du propriétaire (`User` PORTAL par téléphone), source des échéances de soins (`nextDueOn` des soins préventifs), canal e-mail des codes (absent en tranche 1).

- [ ] **Mode de confirmation des RDV.** Proposition : réglable par motif et par type de client dans `OnlineBookingSettings`, défaut = confirmé pour un client connu, à valider pour un nouveau client.
- [ ] **Délai d'annulation en ligne.** Proposition : réglable par clinique, défaut 24 h.
- [ ] **Consentements quand un lien est révoqué.** Proposition : retrait automatique de tous les consentements optionnels de ce tenant, trace conservée dans l'historique.
- [ ] **Questionnaire : fixe ou configurable par clinique.** Proposition : version 1 fixe (enums ci-dessus), champ `version` déjà présent pour une version configurable plus tard.
- [ ] **Visibilité d'un RDV pris par un nouveau client avant confirmation du rattachement.** À trancher dans la spec de la tranche 7 (la route `/clinics/{c}/…` exige un lien confirmé).
- [ ] **Couverture des numéros étrangers.** Proposition : ajouter Belgique et Suisse aux préfixes de `MobileNumberPolicy` quand une clinique pilote frontalière le demande.
