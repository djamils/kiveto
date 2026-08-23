# Tech spec — Paramètres de la clinique & équipe interne

**Besoin :** une clinique doit pouvoir se régler elle-même — qui y travaille, à
quelles heures elle ouvre, ce qu'elle imprime sur ses factures — sans passer par
le backoffice de l'éditeur.

Deux liens morts attendent déjà dans le sélecteur de clinique : « Paramètres de
la clinique » et « Membres & rôles ». Cette spec les remplit.

## Ce qui existe déjà

Le contexte Clinic modélise beaucoup :

- **La clinique** : nom, slug, statut, fuseau, langue, pays, juridiction, devise
  et un en-tête légal complet (raison sociale, forme, capital, SIRET, TVA, RCS,
  APE, numéro d'ordre, adresse postale, téléphone, courriel, site).
- **L'équipe** : appartenances (quatre rôles, engagement, fenêtre de validité,
  activation) et profils (nom affiché, téléphone, couleur d'agenda, ordre,
  visibilité, titre professionnel et numéro d'ordre pour les vétérinaires),
  avec l'onboarding complet et la carte des permissions par rôle.

Rien de tout cela n'est atteignable depuis l'application clinique : les seules
pages qui existent sont dans le backoffice éditeur.

## Ce qui manque au domaine

1. **Les horaires d'ouverture.** Le planning connaît les créneaux d'un
   praticien, pas les heures de la clinique. On ajoute des horaires
   hebdomadaires — pour chaque jour, une liste de plages — et des fermetures
   exceptionnelles datées.
2. **L'adresse de facturation.** L'en-tête n'en porte qu'une. Une clinique dont
   le siège diffère du cabinet doit pouvoir facturer depuis le siège.
3. **Les réglages de facturation.** La numérotation existe (une séquence par
   clinique et par année) mais rien ne la pilote : ni préfixe, ni conditions de
   règlement, ni coordonnées bancaires, ni mention de pied de facture.

## Découpage

### Domaine (contexte Clinic)

- `OpeningHours` — un jour de semaine porte zéro à plusieurs plages ; une plage
  est deux heures locales ordonnées. Les fermetures exceptionnelles sont des
  dates, avec un motif facultatif.
- `BillingSettings` — adresse de facturation facultative (à défaut, celle de
  l'en-tête), préfixe de numérotation, délai de règlement en jours, IBAN, BIC,
  mention de pied de facture.
- Commandes : `UpdateClinicOpeningHours`, `UpdateClinicBillingSettings`,
  `UpdateClinicIdentity` (nom + en-tête), plus les commandes existantes pour la
  langue, le fuseau et l'équipe.
- Lecture : `GetClinicSettings` (tout ce que les pages affichent, en une fois)
  et `ListClinicStaff` (l'équipe avec profils et appartenances).

### Présentation (application clinique)

`/settings` avec quatre onglets et une page d'équipe :

| Route | Contenu |
|---|---|
| `/settings` | Identité : nom, en-tête légal, adresse, contacts |
| `/settings/hours` | Horaires hebdomadaires et fermetures |
| `/settings/billing` | Adresse de facturation, numérotation, règlement, banque |
| `/settings/preferences` | Langue, fuseau, devise |
| `/settings/team` | L'équipe : liste, ajout, rôle, activation, profil |

Un contrôleur par action, comme partout ailleurs.

### Garde-fous

Régler une clinique n'est pas donné à tout le monde : les pages exigent la
permission `manage_staff` pour l'équipe et `manage_billing` pour la facturation.
Les autres onglets suivent `manage_staff` — c'est le rôle qui administre.

## Critères d'acceptation

- **Étant donné** un gérant, **quand** il ouvre `/settings`, **alors** il voit
  l'identité de sa clinique et peut la corriger ; la facture émise ensuite porte
  les nouvelles mentions.
- **Étant donné** des horaires saisis, **quand** la page est rechargée, **alors**
  ils sont là, et une plage dont la fin précède le début est refusée.
- **Étant donné** un préfixe de numérotation, **quand** une facture est émise,
  **alors** son numéro le porte.
- **Étant donné** l'équipe, **quand** un membre est ajouté, **alors** il apparaît
  dans la liste, dans l'agenda si on l'y a rendu visible, et peut être désactivé
  sans perdre son historique.
- **Étant donné** un réceptionniste, **quand** il demande `/settings/team`,
  **alors** l'accès est refusé.
- **Étant donné** `make ci`, **alors** il est vert.
