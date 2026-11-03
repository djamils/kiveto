# Kiveto Portal — Contrat API v1

Oct 29, 2026 · @Djamil — révisé le même jour pour l'aligner sur le code et la maquette (tech-spec `portal-api-v1-foundation-invitation`). Révision v1.1 du 31 oct. 2026 : décisions des tranches 2 à 7 (tech-spec `portal-api-v1-slices-2-7`). Mise à jour du 2 nov. 2026 : alignement sur le fichier réellement exporté (OpenAPI 3.2.0), épinglage du contrat sans tag, limites actuelles de la génération du client Dart — sans changement du contrat.

## Vision et choix structurants

Le contrat d'API précède le code : un fichier OpenAPI versionné dans le repo backend (exporté en OpenAPI 3.2.0, voir « Format exporté et génération du client Dart ») est la source de vérité, le backend DDD l'implémente, l'app Flutter génère son client depuis lui.

- **API Platform, contrat généré puis figé.** Les ressources API Platform sont des DTO de `src/Presentation/Api/Portal/Resource`, jamais des entités Doctrine. Chaque opération a **exactement un** State Provider ou **un** State Processor, qui traduit HTTP ⇄ requêtes/commandes sans logique métier ; un processor peut enchaîner une commande puis l'émission de session (ex. vérification du code puis `IssuePortalSession`). C'est ainsi que l'API respecte la règle « une action par classe ». Les routes de fichiers restent des contrôleurs Symfony `__invoke` (fichier de document, carnet, export, `.ics` et téléchargements signés). L'OpenAPI exporté (`make openapi-export`) est commité dans `openapi/portal.v1.yaml` ; `make openapi-check` (lancé par `make ci`) échoue s'il diverge du code. Le client Dart en est généré ; le générateur n'est pas encore choisi : il le sera dans le repo mobile, après un essai sur ce fichier (voir « Format exporté et génération du client Dart »).
- **Repo Flutter séparé.** Il consomme le YAML commité, épinglé par SHA de commit : le repo backend n'a pas encore de tags de version du contrat. Un changement d'API = une PR backend qui modifie les DTO et le YAML ensemble ; le diff du YAML sert de revue du contrat.
- **Mobile d'abord.** iOS et Android en Flutter. La version web du Portal vient après, en Symfony Twig/Turbo sur les mêmes handlers (pas Flutter web) : les fiches de l'annuaire doivent être indexables, ce que Flutter web fait mal.
- **Le propriétaire est un `User` de type `PORTAL`.** Pas d'agrégat `OwnerAccount` : l'identité vit dans `System/IdentityAccess` (`User` de type `PORTAL`, identifiant de connexion = téléphone E.164, sans mot de passe). Prénom, nom et adresse vivent dans le profil propriétaire (`OwnerProfile`) du contexte `Context/ClientPortal`.
- **Le Portal ne lit les autres contextes que par leurs bus de requêtes, jamais leurs agrégats ni leurs tables.** Les lectures sont faites en direct (pas de projection `client_portal__*` des données cliniques) ; le Portal n'écrit chez les autres contextes que par leurs commandes. Seul le registre de publication des documents (`client_portal__published_documents`) appartient au Portal : ce que la clinique n'a pas publié n'existe pas pour le propriétaire.
- **Ce qui modifie le dossier tenu par la clinique est une demande.** Modification d'animal, renouvellement, questionnaire, rattachement : le Portal crée une demande, la clinique la traite dans VetApp. Écrivent directement, par commande sur le contexte concerné : la prise, le report et l'annulation de RDV (Scheduling), les consentements (Client), le profil et l'e-mail du compte (ClientPortal, IdentityAccess). **Exception explicite** : à la prise de RDV, le propriétaire peut créer **son propre nouvel animal** (`with-new-pet`) ; il est créé avec sexe et stérilisation inconnus, et archivé automatiquement si la demande de RDV est refusée ou expire avant tout acte clinique.
- **Les consentements restent dans `Client`.** Ils prolongent `Client.communicationPreferences` (mêmes `ConsentKind`) avec un historique append-only (`client__consent_history`). Notification ne lit jamais les consentements : c'est l'appelant qui les applique avant de demander un envoi.

## Conventions transverses

Toutes les routes vivent sous `/api/portal/v1` sur l'hôte du Portal, en JSON, authentifiées par jeton Bearer sauf mention « publique ». Pas de CORS (app native).

| Sujet | Règle |
| --- | --- |
| Authentification | Access token JWT 15 min (`sub` = id du `User` PORTAL, `typ` = `portal`) + refresh token opaque **rotatif** (haché en base, nouveau jeton à chaque usage, expiré après 90 j d'inactivité). Pas de mot de passe : connexion par code SMS ; un e-mail **vérifié** devient un second facteur de connexion (renvoi du code de connexion par e-mail, tranche 5 — revient sur la décision D18 de la tranche 1). |
| Rafraîchissement | **Le client doit sérialiser ses refresh** (un seul refresh en vol dans l'intercepteur Dio). Filet de sécurité : rejouer le même refresh token dans les 30 s produit **une seule** nouvelle paire de la même famille ; au-delà (> 30 s, successeur déjà utilisé, ou deuxième rejeu), toute la famille est révoquée (`401 refresh_revoked`). Un compte qui n'est plus actif révoque sa famille au refresh suivant. |
| Paires multiples | Un utilisateur déjà connecté qui accepte une invitation (par le lien ou par jeton d'onboarding) reçoit une nouvelle paire ; l'ancienne reste valide jusqu'à son expiration. **L'app garde toujours la paire la plus récente.** |
| Multi-tenant | Le compte est global. Toute donnée clinique passe par `/clinics/{clinicId}/…` ; le serveur vérifie un `ClientLink` **confirmé** entre le compte et ce tenant, sinon `404 not_found` (jamais 403, pour ne pas révéler l'existence d'une ressource). Une ressource du tenant appartenant à un autre client de la clinique répond aussi 404. Un lien révoqué n'est jamais exposé ; un lien `pending` n'ouvre aucune route `/clinics/{c}/…`. |
| Identifiants | UUID v7 en chaîne. Exceptions : `listingId` (identifiant d'annuaire, jamais l'id de la clinique) et l'id opaque d'une suggestion de rattachement. |
| Dates | ISO 8601 avec décalage (`2026-11-04T10:30:00+01:00`) ; dates seules en `YYYY-MM-DD`. Horodatages de compte et d'authentification émis en UTC (`+00:00`). **Instants cliniques** (`issuedAt`, `timeline.date`, `startAt`, `cancellableUntil`…) émis avec le décalage du fuseau de la clinique (`GET /me` → `clinics[].timezone`, IANA). **Dates seules** (`birthDate`, `dueOn`, `lastDoneOn`, `lastWeight.at`, `validUntil`) en `YYYY-MM-DD`, sans conversion de fuseau. |
| Montants | `{ "amount": 6800, "currency": "EUR" }` en unités mineures, jamais de float. |
| Poids | `weightGrams` entier. |
| Enums | Codes `snake_case` stables ; libellés traduits côté Flutter. Exception : données configurées par la clinique (motifs, praticiens, tags) renvoyées avec leur libellé. |
| Erreurs | `application/problem+json` (RFC 9457), `type` = code métier. Toute erreur de validation est une 422 avec `type` = `validation_failed` et `violations[]` (`propertyPath`, `message`, `code`) — pas `errors[]` ; le serveur convertit le format natif d'API Platform. Liste des codes ci-dessous. |
| Secrets | **Aucun secret dans un chemin d'URL.** Jetons d'invitation, d'onboarding, d'inscription et de refresh voyagent uniquement dans le corps des requêtes (API) ou dans le fragment `#t=` du lien d'invitation. Le YAML exporté ne contient aucun paramètre de chemin `{token}`. Seule exception : la signature (`_hash`, `_expiration`) des URL de téléchargement, valable 5 min. |
| Une forme par route | Chaque route a un seul schéma d'entrée, champs requis non nullables, **pas de `oneOf`**. |
| Pagination | Curseur opaque : `?cursor=&limit=` → `{ "items": [], "nextCursor": null }`. Limite par défaut 20 ; toute valeur au-delà de 50 est ramenée à 50. Curseur altéré, incomplet ou émis pour d'autres paramètres → `400 cursor_invalid`. Toute route qui accepte `cursor` répond avec cette enveloppe. |
| Idempotence | En-tête `Idempotency-Key` obligatoire sur les POST qui créent (RDV, demandes, RDV nouveau client) : même clé + même corps = même réponse rejouée pendant 24 h ; même clé + autre corps = `422 idempotency_key_reused` ; en-tête absent = `400 idempotency_key_missing`. Un POST de création dont la réponse s'est perdue avant l'écriture de la clé renvoie la ressource existante (201) : idempotence naturelle côté serveur. L'annulation de RDV est naturellement idempotente (répétée → 200, même corps) et n'utilise pas la clé. Les deux routes d'acceptation d'invitation n'en utilisent pas : elles ne sont pas idempotentes par nature mais sont rendues rejouables par la règle de rejeu (voir « Rejeu »). |
| Fichiers | `GET …/file`, `…/vaccination-booklet` et `…/data-exports/{id}/file` (authentifiées) répondent **302** vers une route de téléchargement `/files/…` signée par le serveur (`UriSigner`), valable **5 min**, publique (sans jeton Bearer). `Cache-Control: private, no-store` sur la 302 et sur le fichier. Signature invalide ou expirée, document retiré, version inconnue → `404 not_found`. Un passage ultérieur à des URL S3 pré-signées ne change pas le contrat. |
| Versions | Rupture de contrat = `/v2`. Ajout de champ = compatible ; le client Dart ignore les champs inconnus. |
| Notifications | Notification n'applique aucun consentement ; le contexte appelant vérifie le consentement avant de demander un envoi. Un client archivé ne reçoit aucun message optionnel (contrôle à l'envoi). Les SMS d'authentification (`PORTAL_OTP`), d'invitation (`PORTAL_INVITATION`), de vérification d'e-mail, d'export et de refus d'une demande de RDV nouveau client (`PORTAL_BOOKING_DECLINED`) sont envoyés par Kiveto ; les messages de RDV et de résolution de demande (`PORTAL_REQUEST_RESOLVED`) sont envoyés au nom de la clinique. |

### Limitation de débit

Les quotas sont consommés **avant** l'exécution de la commande : une requête qui échoue ensuite (ex. `phone_not_supported`) a quand même consommé son quota. Dépassement = 429 avec en-tête `Retry-After` ; le `type` dépend de la famille (`otp_rate_limited` pour les codes, `export_rate_limited` pour l'export, `rate_limited` pour les autres).

| Limiteur | Politique | Clé | Routes |
| --- | --- | --- | --- |
| par numéro | 3 / heure (fenêtre fixe) | téléphone | `/auth/otp/request`, `/auth/otp/{challengeId}/resend`, `/invitations/otp`, `/me/email-change/otp`, `/me/deletion-request/otp` |
| par numéro, journalier | 10 / 24 h (fenêtre glissante) | téléphone | les mêmes — les renvois ne sont pas gratuits |
| par IP | 30 / heure | IP cliente | les mêmes (30 et non 10 : CGNAT des opérateurs mobiles) |
| par invitation | 3 / heure | invitation | `/invitations/otp` |
| par challenge | 3 / heure | challenge | `/auth/otp/{challengeId}/resend`, consommé seulement après résolution du challenge : un challenge inconnu répond 404 après le seul quota par IP, sans consommer les quotas par numéro ni celui du challenge |
| budget numéros inconnus | trois couches, dans l'ordre : réseau 20 / heure (clé = `/24` en IPv4, `/64` en IPv6) ; indice d'appareil 5 / heure (en-tête facultatif `X-Device-Id`, simple signal, jamais seul garde-fou) ; global 500 / heure (configurable) | réseau, appareil, — | `/auth/otp/request` uniquement, pour un numéro sans compte actif **et** sans invitation en attente. Couche épuisée : même 202, challenge créé mais marqué « supprimé », aucun SMS envoyé ; épuisement global = alerte critique côté serveur. Aucun signal distinct dans l'API (pas d'oracle) : l'app affiche « Vous n'avez pas reçu de code ? Réessayez dans quelques minutes » après le délai de renvoi. Les renvois (`resend`) d'un challenge supprimé répondent le même 202 et n'envoient jamais de SMS. Les codes d'invitation ne consomment jamais ce budget. |
| inscription | 10 / heure | IP cliente | `/auth/register` |
| export | 1 / 24 h, rendu si la construction échoue | utilisateur | `/me/data-export` → `429 export_rate_limited` |
| demandes | 10 / 24 h ; en plus, 3 demandes de modification ouvertes au plus par animal et par champ | utilisateur + clinique | `…/change-requests`, `…/renewal-requests` → `429 rate_limited` |
| RDV nouveau client | 10 / 24 h | utilisateur | `POST /clinic-listings/{l}/booking/appointments` → `429 rate_limited` |
| annuaire public | 120 / minute | IP cliente | `GET /clinic-listings…` → `429 rate_limited` |

Essais : 5 par code. Le code (6 chiffres) est valable 10 min, renvoi possible après 30 s. Les codes d'action (changement d'e-mail, suppression du compte) sont liés à l'utilisateur qui les a demandés et portent un texte SMS propre à leur finalité.

### Numéros acceptés

Seuls les numéros mobiles reconnus par la règle `MobileNumberPolicy` du contexte Client (préfixes mobiles de France métropolitaine et d'outre-mer, dont `+262 69`, `+508 55`, `+681`, `+687 7–9`, `+689 87–89`) peuvent se connecter ou être invités. Tout autre numéro → `422 phone_not_supported` sur `/auth/otp/request`. Élargir la couverture (Belgique, Suisse…) = ajouter des préfixes à cette règle, à la demande d'une clinique pilote, ce qui élargit invitations et API en même temps. Un mobile étranger identifié seulement par son libellé « mobile » sur la fiche client ne peut pas encore être invité.

### Codes d'erreur

| Code | HTTP | Contexte |
| --- | --- | --- |
| `unauthorized` | 401 | Jeton absent, invalide, ou utilisateur non PORTAL / non actif |
| `token_expired` | 401 | Access token expiré → appeler `/auth/refresh` |
| `refresh_revoked` | 401 | Refresh inconnu, expiré, révoqué, réutilisé, ou compte désactivé → reconnexion par code |
| `otp_invalid` | 422 | Code faux, déjà utilisé, challenge d'une autre finalité / invitation / d'un autre utilisateur, ou challenge inconnu sur `otp/verify` |
| `otp_expired` | 422 | Code périmé (> 10 min) |
| `otp_attempts_exceeded` | 422 | 5 essais atteints → redemander un code |
| `otp_rate_limited` | 429 | Quota de codes dépassé, voir `Retry-After` |
| `otp_challenge_not_found` | 404 | Challenge inconnu sur `resend` |
| `phone_not_supported` | 422 | Numéro hors couverture |
| `invitation_invalid` | 404 | Invitation inconnue, jeton remplacé par un renvoi, invitation non liée au téléphone prouvé, ou (route par jeton d'onboarding) invitation plus en attente alors que le numéro en a d'autres |
| `invitation_expired` | 410 | Invitation de plus de 7 jours (lookup, code et acceptation par lien) |
| `invitation_already_used` | 410 | Invitation acceptée par un autre compte ou révoquée (lookup, code et acceptation par lien ; une acceptation par le même compte est un rejeu) |
| `onboarding_invalid` | 401 | « Jeton expiré ou invalide » : jeton d'onboarding ou d'inscription inconnu, expiré (> 15 min), révoqué, déjà consommé ou présenté à la mauvaise route, ou plus aucune invitation en attente pour ce numéro — y compris après une acceptation dont la réponse s'est perdue : refaire la connexion par numéro |
| `account_disabled` | 403 | Compte PORTAL désactivé |
| `account_exists` | 409 | `POST /auth/register` : un compte actif existe déjà pour ce numéro (le jeton n'est pas consommé) |
| `terms_outdated` | 422 | `termsVersion` différente de la version courante |
| `idempotency_key_missing` | 400 | En-tête absent sur une route idempotente |
| `idempotency_key_reused` | 422 | Même clé, corps différent |
| `cursor_invalid` | 400 | Curseur altéré, incomplet ou émis pour d'autres paramètres |
| `validation_failed` | 422 | Toute erreur de validation, avec `violations[]` ; c'est le seul `type` d'une 422 de validation |
| `not_found` | 404 | Ressource inconnue, hors du tenant, d'un autre client de la clinique, retirée, ou URL de téléchargement invalide ou expirée |
| `conflict` | 409 | Écriture concurrente sur la même ressource (consentements) → relire puis réessayer |
| `rate_limited` | 429 | Quota dépassé hors codes et export (demandes, RDV nouveau client, annuaire), voir `Retry-After` |
| `export_rate_limited` | 429 | Un export déjà demandé dans les dernières 24 h |
| `email_taken` | 422 | `POST /me/email-change/verify` : l'adresse est déjà vérifiée par un autre compte |
| `slot_taken` | 409 | Créneau pris entre-temps, hors grille ou non proposé → recharger les créneaux |
| `cancellation_window_closed` | 422 | Délai d'annulation ou de report en ligne dépassé |
| `appointment_not_modifiable` | 422 | RDV terminé, refusé, annulé par la clinique, non venu |
| `booking_not_open` | 422 | Réservation en ligne fermée : réglage désactivé, clinique non active, motif non ouvert en ligne, RDV non pris en ligne (report) |
| `pet_not_bookable` | 422 | Animal décédé ou disparu |
| `pet_not_eligible` | 422 | Demande de modification sur un animal décédé, disparu ou archivé |
| `questionnaire_locked` | 422 | Questionnaire figé : RDV commencé ou consultation démarrée |
| `prescription_not_renewable` | 422 | Ordonnance non renouvelable (non émise, substance contrôlée, animal non vivant, refus de moins de 7 jours) |
| `already_linked` | 422 | RDV par l'annuaire alors qu'un lien confirmé existe avec cette clinique → passer par `/clinics/{c}/…` |
| `booking_request_limit` | 422 | Plus d'une demande de RDV nouveau client en attente dans cette clinique, ou plus de trois au total |
| `service_unavailable` | 503 | Service de génération PDF indisponible (carnet de vaccination), voir `Retry-After` → réessayer plus tard |

Configuration API Platform : format `json` seul (JSON-LD/Hydra désactivés), erreurs en `problem+json`, préfixe `/api/portal/v1` sur l'hôte Portal uniquement, pagination désactivée (curseur implémenté dans les providers), documentation (`/api/portal/v1/docs`) servie en dev et absente en prod. Les routes hors API Platform (`…/documents/{id}/file`, `…/vaccination-booklet`, `/me/data-exports/{id}/file`, `…/calendar.ics` et les téléchargements signés `/files/…`) sont documentées par un décorateur d'`OpenApiFactory` et renvoient leurs erreurs par la même table de codes.

### Format exporté et génération du client Dart

État constaté le 2 nov. 2026 sur `openapi/portal.v1.yaml`. Ce sont des limites connues, pas des changements de contrat.

- **Version.** `make openapi-export` écrit `openapi: 3.2.0`, la version par défaut d'API Platform 4.4 (`ApiPlatform\OpenApi\OpenApi::VERSION`). La commande accepte `--spec-version=3.0.0` (sortie héritée en 3.0), qui n'est pas utilisé ; il n'existe pas de sortie 3.1 (`--spec-version=3.1.0` produit du 3.2.0).
- **Nullabilité.** Style JSON Schema 2020-12 : `type: [string, 'null']` pour les scalaires, `anyOf: [{$ref}, {type: 'null'}]` pour les objets. Ni `nullable`, ni `oneOf`, ni `allOf`.
- **`operationId`.** Générés par API Platform à partir du chemin (`api_clinics_clinicIdhome_get`, `api_authotprequest_post`…) : stables, mais ils donnent des noms de méthodes peu lisibles dans un client généré.
- **Tags.** En français (`Animaux`, `Rendez-vous`, `Compte`…). Les générateurs qui créent une classe par tag en tirent des noms comme `AnimauxApi` ou `RendezVousApi`.
- **Schémas.** 110 schémas, dont des noms pointés issus des formes d'entrée et de sortie (`Appointment.AppointmentView`, `MeUpdate.jsonMergePatch`) et des formes répétées : `AppointmentView` existe aussi sous `AppointmentBooking.`, `AppointmentCancellation.` et `AppointmentReschedule.`.
- **Authentification.** Schéma `apiKey` dans l'en-tête `Authorization` (valeur `Bearer <jeton>`), pas un schéma `http` / `bearer` : le client pose l'en-tête lui-même.
- **Erreurs.** Sur 191 réponses d'erreur, 58 référencent un schéma (`Error` ou `ConstraintViolation`, en `application/problem+json` et `application/json`) ; les autres n'ont qu'une description. `Error.type` est une chaîne libre : les codes métier ne sont pas énumérés dans le YAML, le client les décode d'après la table « Codes d'erreur ».
- **Générateurs.** D'après leurs sources et leurs suivis d'issues (aucun n'a encore été exécuté sur ce fichier) : `openapi-generator` 7.27.0 (Java) ne lit pas les documents 3.2 ; `swagger_parser` (Dart) n'accepte que Swagger 2.0, OpenAPI 3.0 et 3.1 ; `tonik` (Dart) lit 3.2. Le repo mobile compare ces options sur ce fichier avant de retenir un générateur.

## Onboarding : comment un propriétaire arrive

Trois chemins, tous sans dépendance au lien ni à l'installation préalable de l'app :

1. **Invitation de la clinique.** La clinique clique « Inviter au Portal » sur la fiche client (SMS ou e-mail). Le propriétaire reçoit un lien `https://<portal>/invitation#t=<token>` valable 7 jours.
   - **Chemin universel** — installer l'app → saisir son numéro → recevoir un code → l'app propose l'invitation (`invitation_pending`) → accepter les CGU. Aucune dépendance au lien : un SMS perdu, une app installée après coup, tout passe par là.
   - **Raccourci** — si l'app est déjà installée, le lien l'ouvre directement (universal link iOS / app link Android) ; l'app lit le jeton dans le fragment `#t=`, affiche l'invitation, demande un code envoyé au mobile de l'invitation, puis accepte.
2. **Inscription libre (tranche 7).** Un numéro sans compte ni invitation reçoit `registration_required` avec un `registrationToken` (15 min, usage unique, finalité `registration`) ; `POST /auth/register` crée le compte. L'app propose ensuite les suggestions de rattachement (fiches client dont le mobile correspond) et l'annuaire.
3. **RDV nouveau client par l'annuaire (tranche 7).** Un compte sans lien avec une clinique réserve depuis sa fiche d'annuaire ; la clinique confirme la demande, ce qui crée le lien.

Côté hôte Portal :

- `/.well-known/apple-app-site-association` et `/.well-known/assetlinks.json` : publics, `application/json`, sans redirection, générés depuis la configuration (identifiant d'app iOS, package Android, empreintes de certificat), chemin `/invitation*`.
- `/invitation` sans l'app : page statique en français avec les boutons App Store / Play Store et la phrase « Installez l'app puis connectez-vous avec votre numéro », en-tête `Referrer-Policy: no-referrer`. Elle ne lit jamais le fragment ; le jeton n'atteint donc jamais le serveur web (absent des logs et de tout `Referer`). Pas de deep link différé.

Une invitation acceptée crée directement un `ClientLink` **confirmé**. Si un compte PORTAL actif existe déjà pour ce numéro, la clinique lui est rattachée (pas de doublon) ; s'il est désactivé, l'acceptation est refusée (`403 account_disabled`) et l'invitation reste en attente. À la création, l'e-mail de la fiche client est recopié sur le compte s'il n'est pas déjà pris par un autre compte PORTAL (membres d'une famille partageant une adresse) ; il reste non vérifié.

**Rejeu.** Si la réponse d'une acceptation ou d'une inscription est perdue, l'app renvoie exactement la même requête. Par le lien : même challenge et même code, avant l'expiration d'origine du code. Par jeton d'onboarding ou d'inscription : même jeton, tant qu'il est encore valide (15 min). Le serveur reconnaît l'opération déjà faite par ce même compte et renvoie une paire de jetons neuve, sans rien recréer ni compter d'essai.

Accepter la **dernière** invitation en attente d'un numéro révoque son jeton d'onboarding (jamais un jeton d'inscription). Dans le cas courant (une seule invitation), le rejeu par jeton reçoit donc `401 onboarding_invalid` : l'app refait alors la connexion par numéro (`/auth/otp/request` puis `/auth/otp/verify`) ; le compte existe désormais et la réponse est `authenticated`.

**Limite.** Un utilisateur déjà connecté ne voit une nouvelle invitation qu'à sa prochaine connexion par code (`pendingInvitations[]` de `authenticated`) ; aucune route ne liste les invitations en attente d'un compte connecté. Les suggestions de rattachement sont calculées à la demande (`GET /me/link-suggestions`).

## Carte écran → endpoints

Les 18 écrans de la maquette consomment 58 routes (dont 1 post-alpha), plus 3 routes de téléchargement signées hors écran ; l'accueil est le seul écran servi par un endpoint agrégé, pour s'afficher en un appel. Chemins relatifs à `/api/portal/v1`, `{c}` = `clinicId`, `{l}` = `listingId`.

| # | Écran | Lecture | Écriture |
| --- | --- | --- | --- |
| 0 | Activation (invitation) | `POST /invitations/lookup`, `GET /legal/terms/current` | `POST /invitations/otp`, `POST /invitations/accept`, `POST /invitations/{invitationId}/accept` (après connexion par numéro) |
| 1 | Accueil | `GET /clinics/{c}/home`, `GET /me` | — |
| 2 | Fiche animal | `GET /clinics/{c}/pets`, `GET /clinics/{c}/pets/{id}`, `GET …/pets/{id}/care-items`, `GET …/pets/{id}/timeline`, `GET …/pets/{id}/vaccination-booklet`, `GET /clinics/{c}/requests` | `POST …/pets/{id}/change-requests` |
| 3 | RDV étape 3 — créneau | `GET /clinics/{c}/booking/practitioners`, `GET /clinics/{c}/booking/availability` | — |
| 4 | Mes rendez-vous | `GET /clinics/{c}/appointments?scope=upcoming\|past`, `GET /me/booking-requests` | `POST …/appointments/{id}/reschedule` |
| 5 | Documents | `GET /clinics/{c}/documents`, `GET …/documents/{id}/file` | — |
| 6 | Compte & consentements | `GET /me`, `GET /clinics/{c}/consents`, `GET /clinics/{c}/consents/history`, `GET /me/data-exports`, `GET /me/data-exports/{id}/file` | `PATCH /me`, `POST /me/email-change/otp`, `POST /me/email-change`, `POST /me/email-change/verify`, `PUT /clinics/{c}/consents/{kind}`, `POST /me/data-export`, `POST /me/deletion-request/otp`, `POST /me/deletion-request`, `POST /auth/logout` |
| 7 | Bienvenue | — | `POST /auth/otp/request` |
| 8 | Saisie du code | — | `POST /auth/otp/verify`, `POST /auth/otp/{challengeId}/resend` (`sms`, ou `email` quand le compte a un e-mail vérifié) |
| 9 | Inscription | `GET /legal/terms/current` | `POST /auth/register` |
| 10 | Trouver une clinique | `GET /clinic-listings`, `GET /me/link-suggestions` | `POST /me/link-suggestions/{id}/accept`, `POST /clinic-listings/{l}/notify-me` (post-alpha) |
| 11 | Fiche clinique | `GET /clinic-listings/{l}`, `GET /clinic-listings/{l}/booking/motifs`, `GET /clinic-listings/{l}/booking/practitioners`, `GET /clinic-listings/{l}/booking/availability` | `POST /clinic-listings/{l}/booking/appointments` |
| 12 | RDV étape 1 — animal | `GET /clinics/{c}/pets` | — |
| 13 | RDV étape 2 — motif | `GET /clinics/{c}/booking/motifs?petId=` | — |
| 14 | RDV étape 4 — récap | — | `POST /clinics/{c}/appointments` ou `POST /clinics/{c}/appointments/with-new-pet`, `GET …/appointments/{id}/calendar.ics` |
| 15 | Questionnaire | `GET /clinics/{c}/appointments/{id}/questionnaire` | `PUT …/appointments/{id}/questionnaire` |
| 16 | Annulation | `GET /clinics/{c}/appointments/{id}` | `POST …/appointments/{id}/cancel` |
| 17 | Renouvellement | `GET /clinics/{c}/prescriptions/{id}` | `POST …/prescriptions/{id}/renewal-requests` |
| — | Hors écran | `GET /files/documents/{c}/{id}/{v}`, `GET /files/booklets/{c}/{animalId}/{hash}`, `GET /files/exports/{id}` (cibles signées des 302) | `POST /auth/refresh` |

## Endpoints détaillés

Chaque route indique son entrée, sa réponse et les erreurs métier que Flutter doit gérer ; les formes complètes vont dans le YAML. Toute route `/clinics/{c}/…` peut répondre `404 not_found` (pas de lien confirmé, ou ressource d'un autre client).

### Authentification, invitation, CGU (tranches 1 et 7, publiques sauf logout)

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `POST /auth/otp/request` | `phone` (E.164) ; en-tête facultatif `X-Device-Id` | 202 `challengeId`, `expiresAt` (+10 min), `resendAvailableAt` (+30 s) | 422 `phone_not_supported`, 429 `otp_rate_limited`. Réponse identique que le numéro ait un compte ou non, et que le SMS parte ou soit supprimé par le budget numéros inconnus. |
| `POST /auth/otp/{challengeId}/resend` | `channel` — enum `[sms, email]` | 202 même corps ; nouveau code, l'ancien ne vérifie plus | 404 `otp_challenge_not_found`, 429 `otp_rate_limited` (avant `resendAvailableAt` ou quotas). `email` n'est accepté que pour un challenge de **connexion** dont le numéro a un compte actif avec e-mail **vérifié** ; sinon même 202, rien ne change et le code SMS reste valable. Les codes d'action (changement d'e-mail, suppression) ne sont jamais renvoyés par e-mail. Toute autre valeur de `channel` → 422 `violations[]`. |
| `POST /auth/otp/verify` | `challengeId`, `code` | 200, selon `status` : **`authenticated`** → `accessToken`, `accessTokenExpiresAt`, `refreshToken`, `refreshTokenExpiresAt`, `pendingInvitations[]` (`invitationId`, `clinic { name, city }`), `onboardingToken` (ou `null`), `onboardingTokenExpiresAt` (ou `null`) · **`invitation_pending`** (numéro sans compte avec invitation en attente) → `onboardingToken`, `onboardingTokenExpiresAt` (+15 min), `invitations[]` (même forme que `pendingInvitations[]`) · **`registration_required`** (numéro sans compte ni invitation) → `registrationToken`, `registrationTokenExpiresAt` (+15 min, usage unique, finalité `registration`, refusé par `/invitations/{id}/accept`) | 422 `otp_invalid` (y compris code déjà utilisé et `challengeId` inconnu), `otp_expired`, `otp_attempts_exceeded` ; 403 `account_disabled` (compte désactivé : ni jetons, ni onboarding). Les invitations expirées ou révoquées ne sont jamais listées. |
| `POST /invitations/lookup` | `token` | 200 `invitationId`, `clinic { id, name, city }`, `email` (ou `null`), `maskedPhone`, `petNames[]` (animaux actifs), `expiresAt` | 404 `invitation_invalid`, 410 `invitation_expired`, 410 `invitation_already_used` |
| `POST /invitations/otp` | `token` | 202 même corps que `/auth/otp/request` ; code envoyé au mobile de l'invitation | Mêmes erreurs que `lookup` + 429. Ne consomme jamais le budget numéros inconnus. |
| `POST /invitations/accept` | `token`, `challengeId`, `code`, `termsVersion` | 200 `accessToken`, `accessTokenExpiresAt`, `refreshToken`, `refreshTokenExpiresAt`, `account` ; `ClientLink` confirmé | 422 `otp_invalid` (challenge de connexion ou d'une autre invitation : essai compté), `otp_expired`, `otp_attempts_exceeded`, `terms_outdated` ; 404 `invitation_invalid` (jeton inconnu ou remplacé par un renvoi) ; 410 `invitation_expired` ; 410 `invitation_already_used` (acceptée par un autre compte ou révoquée) ; 403 `account_disabled`. Rendue rejouable (voir « Rejeu »). |
| `POST /invitations/{invitationId}/accept` | `onboardingToken`, `termsVersion` | 200 même corps que ci-dessus | 401 `onboarding_invalid` (jeton invalide, jeton d'inscription, ou invitation plus en attente alors qu'il n'en reste aucune pour ce numéro) ; 404 `invitation_invalid` (inconnue, liée à un autre numéro que celui du jeton, ou plus en attente alors que le numéro en a d'autres) ; 422 `terms_outdated` ; 403 `account_disabled`. Le jeton d'onboarding n'est accepté **que** par cette route ; il reste valable pour les autres invitations en attente du même numéro et est révoqué quand il n'en reste plus. Rendue rejouable tant que le jeton vit (voir « Rejeu »). |
| `POST /auth/register` | `registrationToken`, `firstName`, `lastName`, `email?`, `termsVersion` | 201 `accessToken`, `accessTokenExpiresAt`, `refreshToken`, `refreshTokenExpiresAt`, `account` | Tranche 7, publique, limitée par IP. 401 `onboarding_invalid` (jeton inconnu, expiré, déjà consommé ou jeton d'invitation) ; 409 `account_exists` (un compte actif détient déjà le numéro : rien n'est créé ni écrasé, le jeton reste utilisable) ; 422 `terms_outdated` (rien n'est créé, le jeton reste utilisable) ; 403 `account_disabled` (le numéro appartient à un compte désactivé). `email` ignoré sans erreur s'il est déjà utilisé par un autre compte ; jamais vérifié à l'inscription. Rejeu du même jeton dans les 15 min par le compte qu'il a créé → même `account`, nouvelle paire. |
| `POST /auth/refresh` | `refreshToken` | 200 nouvelle paire (rotation) | 401 `refresh_revoked`. Un access token expiré envoyé en `Authorization` est ignoré sur cette route comme sur toutes les routes publiques. |
| `POST /auth/logout` | `refreshToken` (authentifiée) | 204 ; famille du jeton révoquée si elle appartient à l'appelant, sinon rien | 401 `unauthorized`, `token_expired`. L'access token reste valable jusqu'à son expiration (15 min). |
| `GET /legal/terms/current` | — | `version`, `url` | Publique |

### Compte, rattachement, export, suppression

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /me` | — | `account { id, firstName, lastName, email (nullable), emailVerified, maskedPhone, address }`, `clinics[] { clinicId, name, phone, address { line, postalCode, city }, timezone, linkStatus, linkedAt, petNames[], tags[], unreadDocuments }` | `timezone` = fuseau IANA de la clinique (sert à afficher les instants cliniques). `phone` et `address` viennent de l'en-tête de la clinique (`line` = ses deux lignes de rue jointes par « , », `postalCode` nullable) ; `null` quand l'en-tête ne les a pas (adresse sans rue ou sans ville). `linkStatus` : `confirmed`, ou `pending` (suggestion acceptée, tranche 7) ; quand une clinique a les deux, `confirmed` l'emporte ; `linkedAt` est `null` tant que le lien est `pending`. `unreadDocuments` = documents listables non lus (tranche 2), 0 pour un lien `pending`. Alimente la pastille de l'onglet Documents. Ne liste pas les invitations en attente (voir « Onboarding »). |
| `PATCH /me` | `firstName?`, `lastName?` (1 à 60 caractères après suppression des espaces, non vides), `address?` (`streetLine1`, `postalCode?`, `city`, `countryCode`) | 200 `account` | Tranche 5. Modifie le profil Portal uniquement (pas les fiches `Client` des cliniques), sans géocodage. 422 `validation_failed`. Téléphone et e-mail : non modifiables par cette route. |
| `POST /me/email-change/otp` | — | 202 `challengeId`, `expiresAt`, `resendAvailableAt` | Tranche 5. Code SMS de finalité `email_change`, lié à l'utilisateur, texte « confirmation de changement d'e-mail ». 429 `otp_rate_limited`. |
| `POST /me/email-change` | `challengeId`, `code`, `email` | 202 `challengeId` (challenge `email_verification`, 10 min, 5 essais) | Tranche 5. Code SMS vérifié ; un code est envoyé à la nouvelle adresse. 422 `otp_invalid` (code faux, d'une autre finalité ou d'un autre utilisateur), `otp_expired`, `otp_attempts_exceeded`, `validation_failed`. Sert aussi à revérifier l'adresse actuelle non vérifiée. |
| `POST /me/email-change/verify` | `challengeId`, `code` | 200 `account` (e-mail vérifié) | Tranche 5. 422 `otp_invalid`, `otp_expired`, `otp_attempts_exceeded` ; 422 `email_taken` **seulement ici**, et seulement si un autre compte a **vérifié** cette adresse ; un autre compte qui la détenait sans l'avoir vérifiée la perd. L'ancienne adresse vérifiée reçoit un avis de changement. Une fois vérifiée, l'adresse sert de second facteur de connexion (`resend` par e-mail). |
| `GET /me/link-suggestions` | — | `[{ id, clinic { name, city }, matchedOn: phone }]` | Tranche 7. Une suggestion **par clinique** : fiches `Client` non archivées, dans des cliniques actives, dont le numéro libellé « mobile » est le téléphone vérifié du compte (les fiches d'une même famille sont regroupées). `id` opaque (aucun id de client exposé). Exclut les cliniques déjà reliées (lien de tout statut) à toutes les fiches correspondantes et les suggestions refusées par la clinique. |
| `POST /me/link-suggestions/{id}/accept` | — | 202 `linkStatus: pending` | Tranche 7. Ouvre un lien `pending` vers chaque fiche correspondante de cette clinique ; la clinique confirme ou refuse dans VetApp (« Demandes Portal » → « Rattachement »). Rien n'est lu dans `/clinics/{c}/…` avant confirmation. 404 `not_found` (suggestion inconnue ou plus valable). |
| `GET /me/booking-requests` | `cursor`, `limit` | `items[{ id, clinic { name, city }, pet { name, species }, motif, startAt, status: pending\|confirmed\|declined, declineReason?, appointmentId? }]`, `nextCursor` | Tranche 7. Demandes de RDV nouveau client du compte, toutes cliniques (portée compte, pas tenant). `declineReason` : `declined`, `expired`, `cancelled`, `account_deleted`. Une fois `confirmed`, le RDV apparaît normalement dans `/clinics/{c}/appointments` (`appointmentId`). `declineReason` et `appointmentId` valent `null` quand ils ne s'appliquent pas. |
| `POST /me/data-export` | — | 202 `{ id, status: pending }` | Tranche 5. Construit en asynchrone ; aucune exigence d'e-mail. 1 par 24 h (quota rendu si la construction échoue) → 429 `export_rate_limited`. Un e-mail vérifié reçoit seulement un avis, **sans lien**. |
| `GET /me/data-exports` | — | `[{ id, status: pending\|ready\|failed\|expired, requestedAt, readyAt?, expiresAt? }]` | Tranche 5. Exports du compte. |
| `GET /me/data-exports/{id}/file` | — | 302 URL signée 5 min (`/files/exports/{id}`) | Tranche 5. Contrôleur `__invoke`. Export de l'appelant à l'état `ready` ; conservé 48 h puis supprimé → 404 `not_found`. Contenu (`schemaVersion: 1`) : `account.json` (téléphone, e-mail, profil, CGU acceptées), `clinics/<clinicId>.json` (clinique, statut et dates du lien, consentements et leur historique, RDV `{ startAt, pet, motif, status }`, documents publiés `{ type, title, issuedAt }` sans les fichiers, demandes et questionnaires — tranche 6 —, demandes de RDV nouveau client — tranche 7), `summary.pdf`. Un compte sans lien reçoit `account.json` et `summary.pdf` seulement. |
| `POST /me/deletion-request/otp` | — | 202 `challengeId`, `expiresAt`, `resendAvailableAt` | Tranche 5. Code SMS de finalité `account_deletion`, texte « Code de confirmation de suppression de votre compte Kiveto ». 429 `otp_rate_limited`. |
| `POST /me/deletion-request` | `challengeId`, `code` | 202 | Tranche 5. 422 `otp_invalid` (code faux, de connexion ou d'un autre utilisateur), `otp_expired`, `otp_attempts_exceeded`. Dès la 202, le compte est désactivé : tout access token et tout refresh répondent 401. En asynchrone : liens, invitations en attente, jetons et challenges du numéro révoqués ; profil et compte anonymisés (« Compte supprimé », téléphone et e-mail effacés) ; exports supprimés ; demandes closes et texte libre effacé ; demandes de RDV nouveau client en attente refusées sans message. Les fiches `Client` des cliniques et leurs consentements ne sont pas modifiés (la clinique reste responsable de son dossier ; factures et ordonnances conservées par snapshot). Une nouvelle invitation vers ce numéro crée un nouveau compte. |

### Consentements (par clinique, tranche 5)

Les consentements sont ceux de `Client.communicationPreferences` : un consentement par `kind` couvre une liste de canaux. Valeurs de `kind` : `appointment_reminders`, `vaccination_reminders`, `clinical_documents`, `billing`, `marketing`. **Canaux d'un consentement : `sms`, `email` seulement** (canaux automatisés) ; `preferredChannel` garde ses quatre valeurs (`sms`, `email`, `phone`, `mail`).

Deux effets :

- `send_control` — `marketing`, `appointment_reminders`, `vaccination_reminders` (types optionnels) : sans consentement, le message n'est pas envoyé ; l'utilisateur peut tout retirer.
- `channel_preference` — `billing`, `clinical_documents` : ces messages partent de toute façon ; le consentement choisit seulement le canal, au moins un canal joignable est exigé.

Quand le compte est relié à plusieurs fiches `Client` de la clinique, la lecture est l'**union** des canaux par `kind` sur les fiches confirmées non archivées, et une écriture s'applique à chacune de ces fiches (une fiche archivée est ignorée). Un client archivé ne reçoit aucun message optionnel, mais ses consentements ne sont jamais retirés : il les retrouve intacts s'il est désarchivé.

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /clinics/{c}/consents` | — | `preferredChannel`, `consents[{ kind, channels[], grantedAt, effect: send_control\|channel_preference }]` | Les cinq `kind` sont toujours renvoyés ; `channels: []` = non consenti. 404 sans fiche liée active. |
| `PUT /clinics/{c}/consents/{kind}` | `channels[]` (`sms`, `email` ; vide = retrait) | 200 même forme que `GET` | Chaque changement ajoute des lignes d'historique (source `portal`, auteur = nom du profil). Ajouter un canal = consentement daté du jour. 422 `validation_failed` (canal hors `sms\|email`, ou liste vide sur `billing` / `clinical_documents`) ; 409 `conflict` (modification concurrente : relire puis réessayer). |
| `GET /clinics/{c}/consents/history` | `cursor`, `limit` | `items[{ kind, channel, action: granted\|withdrawn, at, source: portal\|clinic\|system, author, approximate }]`, `nextCursor` | Append-only, du plus récent au plus ancien ; un changement appliqué à deux fiches donne une seule ligne. `source: clinic` = saisie au comptoir dans VetApp, auteur affiché « La clinique » (jamais le nom d'un employé) ; `source: system` + `approximate: true` = reprise des consentements existants (date au plus tard). Lien « Historique » de l'écran 6. |

### Accueil et animaux (tranche 3)

Les animaux exposés sont les `Animal` actifs dont le **propriétaire principal actif** est l'une des fiches `Client` reliées (lien confirmé) au compte dans cette clinique, et qui ne sont ni vendus ni donnés. Un animal archivé, en copropriété secondaire, vendu ou donné n'est jamais exposé (`404 not_found` sur toutes ses routes). Les animaux décédés ou disparus restent dans la liste et la fiche (`lifeStatus`) mais sont exclus des soins, de l'accueil et de la prise de RDV.

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /clinics/{c}/home` | — | `nextAppointment?`, `pets[]` (résumé), `careItems[]` (3 max), `latestDocuments[]` (2 max) | Animaux vivants seulement. `nextAppointment` = premier RDV à venir d'un animal vivant visible ; en tranche 3 `{ id, startAt, pet { id, name }, practitioner?, motifLabel }` (libellé fixe : « Vaccination », « Contrôle », « Bilan », « Chirurgie », « Urgence » ou « Consultation », jamais le motif saisi), forme complète d'un rendez-vous à partir de la tranche 4. `careItems[]` = les 3 `dueOn` les plus proches, chacun avec `pet { id, name }`. `latestDocuments[]` = mêmes règles que la liste des documents. Sections vides = tableau vide (`nextAppointment: null`) ; l'app masque la section. |
| `GET /clinics/{c}/pets` | — | `[{ id, name, species, breed, sex, sterilization, birthDate, initial, lifeStatus }]` | `species` : `dog`, `cat`, `nac`, `other`. `sex` : `male`, `female`, `unknown`. `sterilization` : `intact`, `neutered`, `unknown`. `lifeStatus` : `alive`, `deceased`, `missing`. `breed` = race, sinon « Croisé » pour un croisé, sinon `null`. `initial` = première lettre du nom, en majuscule. Vivants d'abord, puis par nom. |
| `GET /clinics/{c}/pets/{id}` | — | même forme + `lastWeight { weightGrams, at }` (ou `null`), `chipNumberMasked` (`•••• 1234` ou `null`), `referentVet` (ou `null`) | `lastWeight` = la mesure la plus récente entre les pesées de la fiche animal et le poids des consultations **clôturées** (à date égale, la pesée l'emporte) ; `at` en date locale de la clinique. `referentVet` = vétérinaire du dernier compte rendu visible par l'appelant (nom figé au partage). |
| `GET /clinics/{c}/pets/{id}/care-items` | — | `[{ kind, label, lastDoneOn, dueOn, status }]` | Dernier acte de chaque protocole (`kind` + libellé, sans casse) ayant une prochaine échéance (`nextDueOn`) ; un protocole renouvelé sans échéance disparaît. `status` : `overdue` (`dueOn` < aujourd'hui), `due_soon` (≤ 30 jours), `up_to_date` ; à partir de la tranche 4, `booked` quand un RDV à venir de l'animal porte un motif du même `kind` dans `[aujourd'hui, max(dueOn, aujourd'hui) + 30 j]` (`booked` > `overdue` > `due_soon` > `up_to_date`). « Aujourd'hui » = date locale de la clinique. Tri par `dueOn` croissant. Vide pour un animal décédé ou disparu. |
| `GET /clinics/{c}/pets/{id}/timeline` | `cursor`, `limit` | `items[{ visitId, date, vet, title, documents[{ id, type }] }]`, `nextCursor` | Seulement les visites dont le compte rendu a été partagé avec l'appelant, triées par date de clôture décroissante. `date` = début de la consultation (instant, décalage de la clinique) ; `vet` = nom figé au partage ; `title` = motifs de la visite (« Vaccination et bilan annuel »), sinon motif de consultation, sinon « Consultation ». `documents[]` = documents de la visite visibles par l'appelant (une facture seulement pour son payeur). 400 `cursor_invalid`. |
| `GET /clinics/{c}/pets/{id}/vaccination-booklet` | — | 302 URL signée 5 min (`/files/booklets/{c}/{animalId}/{hash}`) | Contrôleur `__invoke`. PDF généré à la volée (Gotenberg) et mis en cache tant que ses données ne changent pas : vaccinations, identité, puce, en-tête de la clinique. « Aucune vaccination enregistrée » quand il n'y en a pas. Servi aussi pour un animal décédé. Service PDF indisponible → 503 `service_unavailable` (`Retry-After`). |
| `POST /clinics/{c}/pets/{id}/change-requests` | `field` (`name`, `weight`, `sterilization`, `chip_number`, `other`), `proposedValue` (≤ 120), `measuredOn?` (poids, ≤ aujourd'hui), `note?` (≤ 500) | 202 `requestId` | Tranche 6. Idempotency-Key. Règles : `name` 1 à 60 caractères ; `weight` décimal (virgule ou point) de 0,05 à 150 kg ; `sterilization` `neutered\|intact` ; `chip_number` 15 chiffres ; `other` exige `note`. 422 `validation_failed`, 422 `pet_not_eligible` (décédé, disparu, archivé), 429 `rate_limited`. La clinique applique la modification à la main ; l'animal ne change pas avant. |

### Prise de RDV et rendez-vous (tranche 4)

Le motif de réservation s'appelle `BookingMotif` côté code ; la clinique le configure dans VetApp (durée, types de plages acceptés, praticiens réservables, mode de confirmation pour un client connu `auto|manual`, ouverture aux nouveaux clients, type de soin préventif associé). Le praticien est obligatoire : l'app le fait choisir (ou propose « premier disponible » en le résolvant côté client) avant d'afficher les créneaux.

**Créneaux en ligne (règle stricte).** Un créneau n'existe que dans une plage de planning d'un type accepté par le motif, pour un praticien réservable, dans les horaires d'ouverture (créneaux adjacents fusionnés, fermetures exceptionnelles exclues), hors absences et RDV occupant déjà le praticien, dans la capacité de la plage, sur la grille et au-delà du délai minimal. Grille (défaut 15 min), délai minimal (défaut 2 h), délai pour une réservation à confirmer (défaut 24 h) et horizon (défaut 60 j) sont des réglages de la clinique. Les créneaux sont calculés en heure locale (changements d'heure gérés) et émis avec leur décalage.

**Confirmation.** Réglée par motif pour un client connu (`auto` par défaut) ; un nouveau client est **toujours** validé par la clinique. Une réservation à confirmer (`pending_clinic_confirmation`) occupe le créneau, n'envoie aucun SMS ni rappel ; elle ne propose que des créneaux au-delà du délai de confirmation. Sans réponse, elle est refusée automatiquement 48 h après la réservation ou 2 h avant son début, au premier des deux. Un passage au comptoir la confirme implicitement, sans SMS.

**Délai d'annulation.** Réglable par clinique (défaut 24 h, de 0 à 168 h), appliqué à l'annulation **et** au report : `cancellableUntil = max(startAt − délai, createdAt + 15 min)`, et seulement avant le début du RDV.

Forme d'un rendez-vous (`AppointmentView`) : `{ id, startAt, endAt, pet { id, name }, motif { id, label }, practitioner { id, displayName }, status, source: clinic|portal, cancellableUntil, questionnaireStatus: not_started|submitted, relatedCareItem? }`. `status` : `confirmed`, `pending_clinic_confirmation`, `cancelled_by_client`, `cancelled_by_clinic`, `declined` (une demande à confirmer annulée par la clinique est `declined`), `completed`, `no_show`. `motif` = `{ id: null, label: "Rendez-vous" }` pour un RDV pris par la clinique sans motif en ligne ; jamais le motif interne ni les notes. `cancellableUntil` est `null` pour un statut qui n'occupe plus le créneau. `relatedCareItem { kind, label, dueOn, status }` = le soin de l'animal du même `kind` que le motif, sinon `null`.

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /clinics/{c}/booking/motifs` | `petId?` | `[{ id, label, description, durationMin, suggested, suggestionReason? }]` | Seulement les motifs ouverts en ligne. `suggested` quand l'animal a un soin en retard **ou** à prévoir du `kind` du motif (`suggestionReason` : « Rappel de vaccin en retard », « Rappel de vaccin à prévoir »…). 422 `booking_not_open`. |
| `GET /clinics/{c}/booking/practitioners` | `motifId` | `[{ id, displayName }]` | Praticiens réservables en ligne pour ce motif. 404 `not_found` (motif inconnu, archivé ou non ouvert en ligne), 422 `booking_not_open`. |
| `GET /clinics/{c}/booking/availability` | `motifId`, `practitionerId`, `from`, `to` (dates locales `YYYY-MM-DD`, incluses, 14 jours au plus, `from` ≥ aujourd'hui local) | `days[{ date, open, slots[{ startAt }] }]` | `open` = jour d'ouverture, hors fermeture exceptionnelle, dans l'horizon ; au-delà de l'horizon `open: false`. Seulement les créneaux réservables. 422 `validation_failed` (intervalle), 404 `not_found` (motif, ou praticien non réservable pour ce motif), 422 `booking_not_open` (réservation désactivée, clinique non active). |
| `POST /clinics/{c}/appointments` | `petId`, `motifId`, `practitionerId`, `startAt` | 201 `AppointmentView` ; `status` = `confirmed` ou `pending_clinic_confirmation`, `source: portal` | Idempotency-Key. 409 `slot_taken` → recharger les créneaux (garantie réelle contre la double réservation ; aussi pour un `startAt` hors grille ou non proposé). 422 `pet_not_bookable`, 422 `booking_not_open` ; 404 `not_found` (animal d'un autre client, vendu ou donné). |
| `POST /clinics/{c}/appointments/with-new-pet` | `newPet { name, species }` (`species` : `dog`, `cat`, `nac`, `other`), `motifId`, `practitionerId`, `startAt` | 201 même réponse | Idempotency-Key. Crée l'animal du propriétaire (sexe et stérilisation inconnus) dans la même transaction que le RDV : en cas de `slot_taken`, aucun animal ne reste. Seule une nouvelle tentative de la même requête (même Idempotency-Key) réutilise l'animal qu'elle a créé ; un animal créé par la clinique n'est jamais réutilisé. Archivé automatiquement si la demande est refusée ou expire avant tout acte clinique. Mêmes erreurs que la route précédente. |
| `GET /clinics/{c}/appointments` | `scope` (`upcoming`\|`past`), `cursor`, `limit` | `items[AppointmentView]`, `nextCursor` | RDV des fiches reliées et de leurs animaux principaux. `upcoming` = RDV qui occupent un créneau et commencent maintenant ou plus tard, du plus proche au plus lointain ; `past` = tous les autres, du plus récent au plus ancien. 400 `cursor_invalid`. |
| `GET /clinics/{c}/appointments/{id}` | — | `AppointmentView` | Alimente l'avertissement de l'écran 16 (`relatedCareItem`). |
| `POST …/appointments/{id}/reschedule` | `startAt`, `practitionerId` | 200 `AppointmentView` | Seulement un RDV pris en ligne avec motif, sinon 422 `booking_not_open`. Statut conservé (une demande à confirmer reste à confirmer et repart pour un nouveau délai). Même créneau = 200 sans effet. 409 `slot_taken`, 422 `cancellation_window_closed`, 422 `appointment_not_modifiable`, 404 `not_found` (praticien non réservable pour le motif). |
| `POST …/appointments/{id}/cancel` | `reason` (`unavailable`, `no_longer_needed`, `seen_elsewhere`, `other`) | 200 `AppointmentView` (`status: cancelled_by_client`) | Naturellement idempotente : un RDV déjà annulé par le client → 200, même corps. 422 `cancellation_window_closed` ; 422 `appointment_not_modifiable` (refusé, terminé, non venu, annulé par la clinique). |
| `GET …/appointments/{id}/calendar.ics` | — | `text/calendar` | Contrôleur `__invoke`. `METHOD:PUBLISH`, sans participant, même `UID` que l'invitation envoyée par e-mail, `STATUS:TENTATIVE` tant que le RDV est à confirmer. 404 `not_found` pour un RDV annulé, refusé ou terminé. |
| `GET …/appointments/{id}/questionnaire` | — | `{ version, status, answers?, updatedAt? }` | Tranche 6. `status` : `not_started`, `submitted`. 404 pour un RDV annulé ou refusé. |
| `PUT …/appointments/{id}/questionnaire` | `version: 1`, `symptoms[]` (`none` exclusif, `cough`, `vomiting`, `diarrhea`, `appetite_loss`, `itching`, `lameness`), `ongoingTreatment { has, details? }` (`details` ≤ 500), `temperament` (`calm`\|`stressed`\|`may_bite`), `notes?` (≤ 1000) | 200 même forme que `GET` | Tranche 6. Naturellement idempotente. Modifiable tant que le RDV occupe son créneau, n'a pas commencé et qu'aucune consultation n'a démarré ; ensuite 422 `questionnaire_locked` (un report ne le rouvre pas). 404 pour un RDV annulé ou refusé. `version` ≠ 1 → 422 `validation_failed`. |

### Documents et ordonnances (tranche 2)

**Publication.** Factures et ordonnances sont publiées automatiquement à leur émission ; le compte rendu de consultation seulement quand le vétérinaire clique « Partager sur le Portal » sur une consultation clôturée (jamais à la clôture). Une facture est visible par son **payeur** uniquement (pas par le propriétaire de l'animal s'il n'est pas le payeur) ; une vente comptoir sans client n'est jamais publiée. Une ordonnance est visible par le propriétaire principal au moment de sa rédaction ; un compte rendu par le propriétaire principal de l'animal au moment du partage. Un document publié pour un client sans lien confirmé apparaît dès que le lien est confirmé. Facture annulée et ordonnance annulée sont retirées (absentes de la liste, du détail et des fichiers) ; une facture avoirée reste avec `paymentStatus: credited` (les avoirs ne sont pas publiés en v1). Un compte rendu partagé de nouveau (après un addendum) remplace le fichier et redevient non lu ; l'ancienne version reste servie jusqu'à ce que la nouvelle soit prête. `lab_result` et `certificate` sont réservés : aucun document de ces types n'est publié en v1.

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /clinics/{c}/documents` | `type?` (`report`, `prescription`, `invoice`, `lab_result`, `certificate`), `petId?`, `cursor`, `limit` | `items[{ id, type, title, petIds[], issuedAt, unread, invoice?{ total, paymentStatus }, prescription?{ id, validUntil, renewable, renewalStatus } }]`, `nextCursor` | Du plus récent au plus ancien. `petIds[]` peut être vide (facture sans animal) ; un filtre `petId` exclut ces documents. `paymentStatus` : `unpaid`, `partially_paid`, `paid`, `credited` (lu en direct). `renewable` = ordonnance émise sans substance contrôlée (périmée comprise) ; `renewalStatus` = `{ status, requestId, resultingPrescriptionId? }` de la dernière demande de renouvellement, ou `null` (tranche 6). Le détail par type évite un appel par ligne. 400 `cursor_invalid`. |
| `GET /clinics/{c}/documents/{id}/file` | — | 302 URL signée 5 min (`/files/documents/{c}/{id}/{v}`) | Contrôleur `__invoke`. Marque le document comme lu pour l'appelant seulement. 404 `not_found` (document retiré, d'un autre client ou fichier pas encore prêt). |
| `GET /clinics/{c}/prescriptions/{id}` | — | `{ id, issuedAt, vet, pet { id, name }, lines[{ label, quantity, posologySummary }] }` | Lignes affichées sur l'écran 17. 404 pour une ordonnance non publiée, retirée ou d'un autre client. |
| `POST /clinics/{c}/prescriptions/{id}/renewal-requests` | `petCondition` (`improved`\|`no_improvement`\|`adverse_effects`), `note?` (≤ 500) | 202 `{ requestId, status: submitted }` | Tranche 6. Idempotency-Key. Porte sur **toute** l'ordonnance (pas de `lineIds[]`) ; reste une demande traitée par la clinique (elle rédige au besoin une nouvelle ordonnance, liée à la demande). Une seconde demande alors qu'une est ouverte → 202 avec la même `{ requestId, status }` (pas de 409). 422 `prescription_not_renewable` (non émise, substance contrôlée, animal non vivant, refus de moins de 7 jours), 429 `rate_limited`. |
| `GET /clinics/{c}/requests` | `cursor`, `limit` | `items[{ id, kind: pet_change\|prescription_renewal, petId, field?, proposedValue?, prescriptionId?, status: submitted\|done\|rejected\|closed, submittedAt, resolvedAt?, resolutionMessage?, resultingPrescriptionId? }]`, `nextCursor` | Tranche 6. Demandes de modification et de renouvellement de l'appelant dans cette clinique, de la plus récente à la plus ancienne. **Pas d'annulation ni de modification d'une demande envoyée en v1** (le questionnaire reste modifiable jusqu'au RDV). `closed` = close sans traitement (lien révoqué, client archivé, ordonnance annulée, animal archivé ou décédé, compte supprimé). À la résolution (`done` ou `rejected`), le propriétaire reçoit un message de la clinique (« Votre demande de {type} pour {animal} a été traitée. » / « … n'a pas pu être acceptée. », suivi du message de l'équipe s'il y en a un). 400 `cursor_invalid`. |

### Téléchargements signés (publics)

Cibles des 302 ci-dessus, servies sans jeton Bearer par des contrôleurs `__invoke`, avec `Cache-Control: private, no-store`. Signature invalide ou expirée (5 min), identifiant de clinique altéré, document retiré ou version inconnue → `404 not_found`.

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /files/documents/{c}/{id}/{v}` | signature | `application/pdf` (`inline`) | Sert la version `v` signée, même si une version plus récente a été publiée entre-temps. |
| `GET /files/booklets/{c}/{animalId}/{hash}` | signature | `application/pdf` (`inline`) | Carnet de vaccination. |
| `GET /files/exports/{id}` | signature | `application/zip` (`attachment`) | Export de données, tant qu'il n'a pas expiré (48 h). |

### Annuaire des cliniques (tranche 7)

L'annuaire ne liste que les cliniques Kiveto actives qui ont choisi d'y figurer (réglage VetApp « Annuaire »). Une clinique suspendue ou fermée (ou dont le groupe l'est) disparaît sans perdre ses réglages. Les fiches non revendiquées et `notify-me` sont post-alpha.

| Route | Entrée | Réponse | Erreurs / notes |
| --- | --- | --- | --- |
| `GET /clinic-listings` | `q?` (≥ 2 caractères, sur le nom et la ville), `lat?` (−90 à 90), `lng?` (−180 à 180), `cursor`, `limit` | `items[{ id, name, address, phone, distanceM?, onKiveto, onlineBooking, tags[], nextAvailableAt }]`, `nextCursor` | Publique. Avec `lat/lng` : tri par distance, les cliniques sans coordonnées sont absentes de ce résultat seulement ; sinon tri par nom. Le curseur est lié à `lat/lng` (autres coordonnées → 400 `cursor_invalid`). `onlineBooking` calculé en direct (réservation activée, ouverte aux nouveaux clients, au moins un motif ouvert aux nouveaux clients). `nextAvailableAt` toujours `null` en v1. 422 `validation_failed`, 429 `rate_limited`. |
| `GET /clinic-listings/{l}` | — | `{ id, name, address, phone, timezone, openingHours[], acceptsNewClients, onlineBooking, tags[] }` | Publique (servira les pages web indexables). Les identifiants d'annuaire ne sont pas des identifiants de clinique. 404 `not_found` (non listée ou inactive), comme toutes les routes `/clinic-listings/{l}/…`. |
| `GET /clinic-listings/{l}/booking/motifs` | — | même forme que `/clinics/{c}/booking/motifs` | Publique. Motifs ouverts aux nouveaux clients. 422 `booking_not_open`. |
| `GET /clinic-listings/{l}/booking/practitioners` | `motifId` | même forme | Publique. |
| `GET /clinic-listings/{l}/booking/availability` | `motifId`, `practitionerId`, `from`, `to` | même forme | Publique. Un nouveau client est toujours validé par la clinique : seuls les créneaux au-delà du délai de confirmation sont proposés. |
| `POST /clinic-listings/{l}/booking/appointments` | `newPet { name, species }`, `motifId`, `practitionerId`, `startAt` | 201 `BookingRequestView { id, clinic { name, city }, pet { name, species }, motif, startAt, status: pending }` | **Authentifiée** (seule route non-GET de l'annuaire) et idempotente (Idempotency-Key). Crée une demande de RDV nouveau client, sans `ClientLink` ; le créneau est réservé en attendant la clinique. Le propriétaire la suit par `GET /me/booking-requests`. Quand la clinique confirme (fiche existante ou nouvelle, animal existant ou nouveau), le lien est confirmé et le RDV apparaît dans `/clinics/{c}/appointments` ; refus, expiration ou annulation par la clinique → `declined` et SMS « Demande de RDV Portal non confirmée » au numéro du compte. 401 `unauthorized`, 409 `slot_taken`, 422 `already_linked`, 422 `booking_request_limit`, 422 `booking_not_open`, 404 `not_found`, 429 `rate_limited`. |
| `POST /clinic-listings/{l}/notify-me` | — | 202 | Post-alpha, authentifiée |

## Découpage DDD

Le Portal ajoute deux contextes (`ClientPortal`, `ClinicListing`) et en étend neuf (`IdentityAccess`, `Client`, `Scheduling`, `Notification`, `Animal`, `Billing`, `Prescription`, `Consultation`, `Clinic`) ; aucun contexte clinique n'expose ses agrégats au Portal.

Le Portal lit les autres contextes par leurs bus de requêtes (ports et adaptateurs), jamais leurs tables ; les écritures du propriétaire deviennent des demandes traitées dans VetApp, sauf la réservation (et la création de son nouvel animal à la réservation), les consentements et le profil. Les événements d'intégration n'existent que là où un consommateur en a besoin. Les noms d'événements sont ceux du code.

| Contexte | Statut | Agrégats et éléments | Événements |
| --- | --- | --- | --- |
| `System/IdentityAccess` | étendu | `User` de type `PORTAL` (téléphone obligatoire et unique par type, e-mail facultatif vérifié par code, sans mot de passe, désactivation puis anonymisation à la suppression), `OtpChallenge` (code haché en HMAC, sujet `invitation` ou `user`, finalités `login`, `invitation`, `email_change`, `email_verification`, `account_deletion`, jeton d'onboarding de finalité `invitation` ou `registration`), familles de refresh tokens | publie `PortalUserRegistered` |
| `Context/ClientPortal` | nouveau | `PortalInvitation` (jeton haché propre, 7 jours, renvoi = même invitation, nouveau jeton), `ClientLink` (compte ↔ fiche `Client` d'une clinique, `pending`\|`confirmed`\|`revoked` interne ; `pending` par suggestion acceptée), `OwnerProfile` (nom, adresse, CGU acceptées), registre de publication `PublishedDocument` (`client_portal__published_documents`, lectures `client_portal__document_reads`), `PreVisitQuestionnaire`, `PetChangeRequest`, `PrescriptionRenewalRequest`, `BookingRequest` (`client_portal__booking_requests`), `DataExport`, `LinkSuggestion` (calculée, non stockée) | publie `PortalInvitationSent`, `PortalInvitationAccepted`, `ClientLinkConfirmed` (+ `ClientLinkConfirmedIntegrationEvent`), `ClientLinkRevoked` (événement de domaine, sans motif), `DocumentPublished`, `DocumentWithdrawn` ; consomme `ClientArchivedIntegrationEvent` (invitations révoquées, lien révoqué, demandes closes ; le désarchivage ne restaure pas le lien), les événements de facture et d'ordonnance (publication, retrait), `PatientLinkedToAnimalIntegrationEvent` et `PatientReconciledIntoIntegrationEvent` (animaux des documents), les trois `PendingAppointment*IntegrationEvent` (demandes de RDV nouveau client) |
| `Context/Client` | étendu | `communicationPreferences` porte les consentements (`ConsentKind` existants, canaux `sms`\|`email`) ; historique append-only `client__consent_history` (kind, canal, accordé/retiré, date, source `portal`\|`clinic`\|`system`, auteur, reprise approximative) ; règle d'envoi « un client archivé ne reçoit aucun message optionnel » ; requêtes `IsPortalMobileNumber` sur `MobileNumberPolicy` et `ListClientsByMobilePhone` (suggestions, toutes cliniques) | publie `ClientCommunicationPreferencesUpdated`, `ClientArchivedIntegrationEvent` |
| `Context/Scheduling` | étendu | `BookingMotif`, `OnlineBookingSettings` (activation, grille, délais, horizon, délai d'annulation, réservation nouveau client), calcul des créneaux en ligne. `Appointment` gagne les statuts `PENDING_CLINIC_CONFIRMATION` et `DECLINED`, `source: clinic\|portal`, `cancelledBy`, `cancellationReason`, le motif réservé et l'expiration de la demande ; verrou par praticien et par jour contre la double réservation | publie, pour les seuls RDV en ligne en attente (`source = portal`) : `PendingAppointmentConfirmedIntegrationEvent`, `PendingAppointmentDeclinedIntegrationEvent`, `PendingAppointmentCancelledIntegrationEvent` ; tous les autres événements restent de domaine (règle D1 assouplie pour ces trois) |
| `Context/Billing` | étendu | statut de paiement lu en direct par lot pour le Portal | publie `InvoiceIssuedForPortalIntegrationEvent`, `InvoiceCancelledIntegrationEvent` |
| `Context/Prescription` | étendu | prédicat « renouvelable par le propriétaire » (émise, sans substance contrôlée) | publie `PrescriptionIssuedIntegrationEvent`, `PrescriptionCancelledIntegrationEvent` |
| `Context/Consultation` | étendu | fichier du compte rendu à la demande, contrôle « partageable », titres de visite et poids des consultations clôturées | — |
| `Context/Animal` | étendu | animaux du Portal (actifs, propriétaire principal, ni vendus ni donnés), dernier acte de chaque protocole de soins, création de l'animal à la réservation | — |
| `Context/Clinic` | étendu | — | publie `ClinicProfileChangedIntegrationEvent` (nom, horaires, coordonnées, fuseau, statut de la clinique ou de son groupe) |
| `Context/ClinicListing` | nouveau | `ListingSettings` (`clinic_listing__listing_settings` : figurer dans l'annuaire, accepte de nouveaux clients, tags), projection `ClinicListing` (`clinic_listing__clinic_listings`, coordonnées géocodées, jamais supprimée : inactive quand la clinique ou son groupe est suspendu ou fermé) ; plus tard les fiches non revendiquées | consomme `ClinicProfileChangedIntegrationEvent` |
| `System/Notification` | étendu | finalités `PORTAL_OTP`, `PORTAL_INVITATION`, `PORTAL_EMAIL_VERIFICATION`, `PORTAL_EMAIL_CHANGED_NOTICE`, `PORTAL_DATA_EXPORT_READY`, `PORTAL_BOOKING_DECLINED` (envoyées par Kiveto), `APPOINTMENT_DECLINED`, `PORTAL_REQUEST_RESOLVED` (envoyées au nom de la clinique) ; cloches VetApp des RDV en ligne et des demandes Portal | — |
| `Presentation/Api/Portal` | nouveau | ressources DTO, un State Provider ou un State Processor par opération, qui traduit HTTP ⇄ requêtes/commandes sans logique métier (un processor peut enchaîner une commande puis l'émission de session) ; contrôleurs `__invoke` pour les fichiers, l'`.ics` et les téléchargements signés | — |

`User`, `ClientLink`, `OwnerProfile`, `BookingRequest` et `ClinicListing` sont hors tenant (tables globales) ; tout le reste est scopé par clinique. Le seul registre du Portal sur les données cliniques est `client_portal__published_documents` ; l'annuaire a sa projection `clinic_listing__clinic_listings`.

## Ordre de développement

Sept tranches verticales, chacune livrée de bout en bout (contrat, backend, Flutter) avant la suivante ; les cliniques pilotes invitent leurs clients dès la tranche 1, l'inscription libre arrive en dernier.

1. **Socle et invitation.** API Platform, JWT + refresh rotatif, problem+json, idempotence, curseur, limiteurs ; connexion par code SMS ; invitation depuis la fiche client (« Inviter au Portal »), acceptation par lien ou par connexion au numéro, `ClientLink`, `GET /me` ; page `/invitation` et fichiers `.well-known`. Écrans 0, 7 (connexion seule), 8 et un accueil vide. Livrée en trois branches : contrat, socle d'authentification, invitation.
2. **Documents publiés.** Registre de publication dans `ClientPortal`, événements d'intégration de Billing et Prescription, action « Partager sur le Portal » dans VetApp, liste, fichier signé, détail d'ordonnance, pastille non lus. Écran 5 : la valeur la plus visible pour le coût le plus bas.
3. **Animaux et historique.** Animaux, fiche, soins, historique des visites partagées, carnet PDF, accueil complet, fuseau de la clinique dans `GET /me`. Écrans 1 et 2.
4. **Prise de RDV.** En deux branches : réglages (`BookingMotif`, `OnlineBookingSettings`, statuts à confirmer et refusé, verrou, réglages et file VetApp), puis réservation (créneaux, création, liste, report, annulation, `.ics`). Écrans 3, 4, 12, 13, 14, 16.
5. **Consentements et compte.** Consentements par canal et historique dans `Client`, profil, changement d'e-mail vérifié, export, suppression du compte. Écran 6. À livrer avant d'activer le moindre rappel.
6. **Demandes.** Questionnaire, modification d'animal, renouvellement, suivi des demandes, et leur file côté VetApp (« Demandes Portal »). Écrans 2, 15, 17.
7. **Arrivée organique.** Inscription libre (`registrationToken`), suggestions de rattachement, `ClinicListing`, fiche clinique, RDV nouveau client et son suivi. Écrans 4, 7, 9, 10, 11.

## Décisions ouvertes

Un seul point reste à trancher.

- [ ] **Push.** Enregistrement des appareils (`POST /me/devices`, `DELETE /me/devices/{id}`), préférences de notification du compte (`/me/notification-preferences`) et transport push sont décidés ensemble, quand un transport existera : aucun expéditeur ne lit aujourd'hui de préférence de compte, et des jetons stockés maintenant seraient périmés à l'arrivée du push. SMS et e-mail restent gouvernés par les consentements par clinique. Ces routes ne font pas partie de la v1.

Tranchées :

- Identité du propriétaire : `User` PORTAL par téléphone.
- Source des échéances de soins : `nextDueOn` des soins préventifs.
- Canal e-mail des codes : absent en tranche 1 ; en tranche 5, un e-mail vérifié reçoit les codes de **connexion** à la demande (`resend`), jamais les codes d'action.
- Mode de confirmation des RDV : réglé par motif pour un client connu (`auto` par défaut) ; un nouveau client est toujours validé par la clinique ; une demande sans réponse est refusée automatiquement 48 h après la réservation ou 2 h avant son début.
- Délai d'annulation en ligne : réglable par clinique, 24 h par défaut, avec 15 min de grâce après la réservation ; s'applique aussi au report.
- Consentements quand un lien est révoqué ou un client archivé : aucun retrait ; un client archivé ne reçoit aucun message optionnel (contrôle à l'envoi) ; les consentements sont intacts au désarchivage.
- Questionnaire : version 1 fixe (enums ci-dessus) ; le champ `version` permettra une version configurable par clinique plus tard.
- Visibilité d'un RDV pris par un nouveau client avant confirmation : une demande de RDV suivie par `GET /me/booking-requests` (portée compte) ; le RDV n'apparaît sous `/clinics/{c}/…` qu'une fois le lien confirmé par la clinique.
- Numéros étrangers : rien pour l'instant ; Belgique et Suisse seront ajoutées à `MobileNumberPolicy` à la demande d'une clinique pilote frontalière.
