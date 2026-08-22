# Tech spec — Consultation cockpit, nouveau layout

**Source de vérité :** `docs/frontend-theme/vetsaas-layouts/new-gen/vetsaas-consultation.html`
**Portée :** présentation uniquement. Aucun nouvel endpoint, aucun changement de
domaine — le cockpit écrit déjà par les commandes existantes.

## Ce qui change

Le cockpit actuel vient de la maquette précédente (`vetos-consultation.html`).
La nouvelle passe d'un empilement de bandes collées à un **plan de cartes
posées sur le fond de page**, et déplace le chrome.

1. **Mode focus.** La page perd la sidebar de navigation et sa topbar à fil
   d'Ariane. La patient bar passe pleine largeur au-dessus de la zone de
   travail et du panneau droit, et reçoit un bouton retour vers la liste des
   consultations, l'état d'enregistrement, la durée, Historique, Imprimer et
   Dossier complet. Le header global de l'application est conservé : le mode
   focus ne doit pas enfermer l'utilisateur dans la page.

2. **Motif et constantes deviennent deux cartes** côte à côte (44 % / 56 %)
   au lieu de deux bandes empilées. Le motif libre devient un champ éditable
   sur place. Les constantes non saisies passent de la liste de texte grise à
   des **cases vides cliquables**, et le bouton d'ajout se réduit à une icône
   de la hauteur exacte d'une case.

3. **Les vigilances descendent dans la carte des constantes.** Les alertes
   médicales de l'animal (allergie, suivi chronique) quittent la patient bar
   pour se poser derrière un séparateur, au bout de la réglette des
   constantes : elles se lisent avec les chiffres qu'elles conditionnent.

4. **Panneau droit en cartes**, plus étroit (340 px), sur fond transparent. Le
   total quitte le flux pour un **pied sombre** qui porte aussi la phrase
   d'engagement de clôture — ce que la clôture va produire, dit avant de
   cliquer.

5. **Quads** : ombre légère, plus de barre de couleur en tête.

## Ce qui ne change pas

Les quads S/O/A/P, leurs modales de saisie rapide, les templates d'examen, la
recherche catalogue, l'ordonnance, la facturation dérivée, le mémo équipe, la
lecture seule d'une consultation clôturée : tout reste tel quel. Le mémo équipe
et les boutons Pause / Plus tard, absents de la maquette, sont conservés — ce
sont des fonctions, pas des éléments de style, et le pied sombre de la maquette
prévoit déjà les variantes de boutons pour les accueillir.

Le bouton « Suggérer (IA) » du quad Diagnostic reste ce qu'il est aujourd'hui :
la maquette ne change rien à son comportement.

## Découpage

1. `templates/clinic/base.html.twig` — un bloc `content_header` optionnel,
   rendu entre la topbar et la zone scrollable, pour qu'une page puisse poser
   un bandeau pleine largeur au-dessus du panneau droit.
2. `_patient_banner.html.twig` — retour, identité, actions, état
   d'enregistrement.
3. `_summary_cards.html.twig` (ex-`_sidebar_vitals.html.twig`) — les deux
   cartes.
4. `index.html.twig` — plus de topbar cockpit, pied de panneau droit.
5. `consultation.css` — cartes, pied sombre, cases vides, vigilances.
6. `consultation.js` — sélecteurs par classe plutôt que par position, rendu des
   vigilances et des cases vides, phrase de clôture.

## Critères d'acceptation

- **Étant donné** une consultation ouverte, **quand** la page est affichée,
  **alors** la sidebar a disparu, la patient bar occupe toute la largeur et le
  bouton retour ramène à la liste des consultations.
- **Étant donné** une constante jamais saisie, **quand** on clique sa case
  vide, **alors** la modale de saisie s'ouvre sur cette constante.
- **Étant donné** un animal porteur d'une alergie documentée, **quand** la page
  est affichée, **alors** la vigilance apparaît dans la carte des constantes et
  l'ordonnance garde son bandeau d'incompatibilité.
- **Étant donné** un plan qui produit des lignes facturées, **quand** une ligne
  est ajoutée ou retirée, **alors** le total du pied et la phrase de clôture
  suivent.
- **Étant donné** une consultation clôturée, **quand** la page est affichée,
  **alors** elle reste en lecture seule.
- **Étant donné** `make ci`, **alors** il est vert.
