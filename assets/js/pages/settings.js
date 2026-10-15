/**
 * Page module — Paramètres (parametres-v-topbar_5 mock-up). Loaded by the
 * app.js dispatcher on clinic_settings, clinic_settings_hours,
 * clinic_settings_billing and clinic_settings_preferences.
 *
 * The mock-up renders its sections from a schema and a table of values; this
 * module does the same with the values of the `data-payload` of
 * SettingsPageBuilder. Moving from one section to another happens in place
 * (sliding indicator, entrance animation) and the address follows, so a
 * reload reopens the same section.
 *
 * Every change saves as it goes, like the mock-up (« Enregistré · … » with
 * « Annuler »): the whole section it belongs to is posted in the background
 * with the page's token, one save after the other. A save the server refuses
 * is rolled back on the page and its reason shown next to the field.
 *
 * The sections and settings Kiveto does not handle yet are drawn disabled,
 * never with made-up values: Agenda, Planning, Alertes de soins,
 * Ordonnances, Stocks, Laboratoires, Reprise des données and Sécurité in the
 * navigation; the approved e-invoicing platform, instalments and the cash
 * desk settings in Facturation; the home page and notifications in Mes
 * préférences.
 *
 * Listeners are bound in init() with one AbortSignal and released in cleanup().
 */
import { showToast, toastUndo, creerDropdown } from 'kiveto/shell';

const PALETTE = ['#4f86c6', '#6dbe78', '#c67db5', '#e0a43a', '#e07a5f', '#4fb3bf', '#8d7bd6', '#d65d7a', '#7a9e3b', '#c4895b', '#5b7bd6', '#64748b'];
const DEFAULT_COLOR = '#4338ca';

let ac = null;

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const norm = t => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const toMin = s => { const [a, b] = String(s).split(':').map(Number); return a * 60 + (b || 0); };
const hm = m => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
const I = p => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
const IC = {
  id: I('<path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6M9 10h.01M15 10h.01"/>'),
  clock: I('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'),
  cal: I('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'),
  plan: I('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 14h3M8 18h6"/>'),
  steth: I('<path d="M11 2v2M5 2v2M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1"/><path d="M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/>'),
  rx: I('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15h6"/>'),
  euro: I('<path d="M4 10h12M4 14h9M19 6a7.7 7.7 0 0 0-5.2-2A7.9 7.9 0 0 0 6 12c0 4.4 3.5 8 7.8 8 2 0 3.8-.8 5.2-2"/>'),
  box: I('<path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="M3 8l9 5 9-5M12 13v8"/>'),
  flask: I('<path d="M9 2v6l-5 9a3 3 0 0 0 2.6 4.5h10.8A3 3 0 0 0 20 17l-5-9V2M8 2h8M7 15h10"/>'),
  db: I('<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>'),
  lock: I('<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>'),
  user: I('<circle cx="12" cy="8" r="4"/><path d="M4 21c.9-3.6 3.8-6 8-6s7.1 2.4 8 6"/>'),
  team: I('<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.4 3.4-5.5 6.5-5.5s5.7 2.1 6.5 5.5"/><circle cx="17.5" cy="9" r="2.6"/><path d="M15.7 14.7c2.7.3 4.9 2.1 5.6 5.1"/>'),
  ext: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10"/></svg>',
  rm: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
};

/* Masks and validation: the input is not trusted (mock-up « Masques et validation »). */
const chiffres = v => String(v ?? '').replace(/\D/g, '');
const luhn = d => { let t = 0; [...d].reverse().forEach((c, i) => { let n = +c; if (i % 2) { n *= 2; if (n > 9) n -= 9; } t += n; }); return t % 10 === 0; };
const cleTva = siren => String((12 + 3 * (Number(siren) % 97)) % 97).padStart(2, '0');

export function init() {
  const root = document.querySelector('.prm[data-payload]');
  if (!root) return;

  ac = new AbortController();
  const { signal } = ac;
  const on = (el, type, fn, opts = {}) => el && el.addEventListener(type, fn, { signal, ...opts });
  const $ = id => document.getElementById(id);

  const P = JSON.parse(root.dataset.payload);
  const CAN = P.can;

  /* ── Values (state of the settings) ── */
  const V = { ...(P.identite || {}), ...(P.facturation || {}), 'moi.clin': P.moi['moi.clin'] };
  let HORAIRES = P.horaires ? P.horaires.week : [];
  let FERMETURES = P.horaires ? P.horaires.closures : [];
  const NEXT = P.facturation ? { fa: P.facturation.nextInvoice, av: P.facturation.nextCredit } : {};
  let LOGO = P.urls.logo;

  const sirenDe = () => chiffres(V['id.siret'] || '').slice(0, 9);
  const MASK = {
    tel: { ph: '04 91 00 00 00', im: 'tel', max: 14, f: v => chiffres(v).slice(0, 10).replace(/(\d{2})(?=\d)/g, '$1 '), ok: v => /^0[1-9]\d{8}$/.test(chiffres(v)), err: '10 chiffres, commençant par 0.' },
    cp: { ph: '13002', im: 'numeric', max: 5, f: v => chiffres(v).slice(0, 5), ok: v => /^(0[1-9]|[1-8]\d|9[0-8])\d{3}$/.test(v), err: '5 chiffres.' },
    siret: { ph: '123 456 789 00012', im: 'numeric', max: 17, f: v => { const d = chiffres(v).slice(0, 14); return [d.slice(0, 3), d.slice(3, 6), d.slice(6, 9), d.slice(9)].filter(Boolean).join(' '); }, ok: v => { const d = chiffres(v); return d.length === 14 && luhn(d.slice(0, 9)) && luhn(d); }, err: 'SIRET invalide : vérifiez les 14 chiffres.' },
    tva: { ph: 'FR 00 123456789', max: 15, f: v => { const x = v.toUpperCase().replace(/[^A-Z0-9]/g, ''); const p = x.slice(0, 2).replace(/[^A-Z]/g, ''), r = x.slice(p.length).replace(/\D/g, '').slice(0, 11); return [p, r.slice(0, 2), r.slice(2)].filter(Boolean).join(' '); }, ok: v => { const x = v.replace(/\s/g, ''); const m = x.match(/^FR(\d{2})(\d{9})$/); return !!m && m[1] === cleTva(m[2]) && (!sirenDe() || m[2] === sirenDe()); }, err: () => sirenDe().length === 9 ? `Pour ce SIRET, le numéro est FR ${cleTva(sirenDe())} ${sirenDe()}.` : 'Format attendu : FR, 2 chiffres de clé, puis le SIREN.' },
    ordre: { ph: '2207', im: 'numeric', max: 6, f: v => chiffres(v).slice(0, 6), ok: v => /^\d{3,6}$/.test(v), err: 'Numéro de 3 à 6 chiffres.' },
    contrat: { max: 20, f: v => v.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 20), ok: v => v.length >= 4, err: '4 caractères minimum, lettres et chiffres.' },
    prefixe: { max: 6, f: v => v.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 6), ok: v => /^[A-Z]{2,6}$/.test(v), err: '2 à 6 lettres.' },
    mail: { ph: 'contact@clinique.fr', im: 'email', max: 80, f: v => v.trim().toLowerCase().replace(/\s/g, ''), ok: v => /^[^@\s]+@[^@\s]+\.[a-z]{2,}$/.test(v), err: 'Adresse e-mail invalide.' },
    web: { ph: 'clinique.fr', im: 'url', max: 80, f: v => v.trim().toLowerCase().replace(/^https?:\/\//, '').replace(/\s/g, '').replace(/\/$/, ''), ok: v => !v || /^[a-z0-9-]+(\.[a-z0-9-]+)+(\/\S*)?$/.test(v), err: 'Adresse de site invalide.' },
    // Not in the mock-up: the fields the clinic already had (APE, IBAN, BIC).
    ape: { ph: '7500Z', max: 5, f: v => v.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 5), ok: v => /^\d{4}[A-Z]$/.test(v), err: '4 chiffres et une lettre.' },
    iban: { ph: 'FR76 3000 6000 0112 3456 7890 189', max: 42, f: v => v.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 34).replace(/(.{4})(?=.)/g, '$1 '), ok: v => /^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/.test(v.replace(/\s/g, '')), err: 'IBAN invalide.' },
    bic: { ph: 'AGRIFRPP', max: 11, f: v => v.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 11), ok: v => /^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/.test(v), err: '8 ou 11 caractères.' },
  };

  /* Pages of the app a setting shows up on */
  const AFF = {
    agenda: ['Agenda', P.urls.aff.agenda], planning: ['Planning', P.urls.aff.planning], consult: ['Consultation', P.urls.aff.consult], flux: ['Salle d\'attente', P.urls.aff.flux],
    ordo: ['Ordonnances', P.urls.aff.ordo], fact: ['Facturation', P.urls.aff.fact], stock: ['Stocks', P.urls.aff.stock], cat: ['Catalogue', P.urls.aff.cat],
    docs: ['Documents imprimés', null], sel: ['Sélecteur de clinique', null], tout: ['Toute l\'application', null], equipe: ['Équipe', P.urls.aff.equipe],
  };

  /* ── Schema of the sections ── */
  const R = (k, l, d, type, o = {}) => ({ k, l, d, type, ...o });
  const MOYENS = [['CARD', 'Carte bancaire'], ['CASH', 'Espèces'], ['CHECK', 'Chèque'], ['TRANSFER', 'Virement']];
  const SECTIONS = [
    { id: 'identite', g: 'Clinique', l: 'Identité', ico: IC.id, d: 'Coordonnées, mentions légales et apparence : reprises sur les documents et dans le sélecteur de clinique.', blocks: [
      { t: 'Coordonnées', rows: [R('id.nom', 'Nom affiché', 'Sur les documents et dans le sélecteur de clinique.', 'text', { req: true, max: 60, aff: ['docs', 'sel'] }), R('id.rs', 'Raison sociale', 'Telle qu\'inscrite au registre du commerce.', 'text', { req: true, max: 80, aff: ['fact'] }), R('id.adr', 'Adresse', 'Reprise sur les factures et les ordonnances.', 'adresse', { col: true, pre: 'id', aff: ['fact', 'docs'] }), R('id.tel', 'Téléphone', '', 'text', { m: 'tel', w: 170 }), R('id.mail', 'E-mail', '', 'text', { m: 'mail' }), R('id.web', 'Site web', 'Facultatif.', 'text', { m: 'web' })] },
      { t: 'Mentions légales', d: 'Obligatoires sur les factures : elles sont vérifiées avant d\'être enregistrées.', rows: [R('id.siret', 'SIRET', '14 chiffres, vérifiés.', 'text', { m: 'siret', w: 200, aff: ['fact'] }), R('id.tva', 'N° de TVA intracommunautaire', 'Doit correspondre au SIRET.', 'text', { m: 'tva', w: 200, aff: ['fact'] }), R('id.ordreN', 'N° d\'inscription à l\'Ordre', 'Celui de la société d\'exercice.', 'text', { m: 'ordre', w: 120, aff: ['docs'] }), R('id.cro', 'Conseil régional de l\'Ordre', '', 'dd', { o: P.options.councils, aff: ['docs'] }), R('id.rcpA', 'Assureur responsabilité civile professionnelle', '', 'text', { req: true, max: 40, aff: ['fact'] }), R('id.rcpN', 'N° de contrat', '', 'text', { m: 'contrat', w: 200, aff: ['fact'] }), R('id.forme', 'Forme juridique', 'SELARL, SAS, SCP…', 'text', { max: 40, w: 200, aff: ['fact'] }), R('id.capital', 'Capital social', '', 'text', { max: 40, w: 200, aff: ['fact'] }), R('id.rcsVille', 'Greffe du RCS', 'La ville où la société est immatriculée.', 'text', { max: 60, w: 200, aff: ['fact'] }), R('id.rcsN', 'N° RCS', '', 'text', { max: 40, w: 200, aff: ['fact'] }), R('id.ape', 'Code APE', '', 'text', { m: 'ape', w: 120, aff: ['fact'] })] },
      { t: 'Apparence', rows: [R('id.logo', 'Logo', 'PNG ou SVG, fond transparent de préférence.', 'logo', { aff: ['docs'] }), R('id.couleur', 'Couleur de la clinique', 'Distingue la clinique dans le sélecteur quand vous en gérez plusieurs.', 'color', { aff: ['sel'] })] },
      { t: 'Langue et fuseau horaire', d: 'La langue des documents et l\'heure sur laquelle comptent l\'agenda et la caisse.', rows: [R('id.locale', 'Langue', '', 'dd', { o: P.options.locales, aff: ['docs'] }), R('id.tz', 'Fuseau horaire', '', 'dd', { o: P.options.zones, aff: ['tout'] })] },
    ] },
    { id: 'horaires', g: 'Clinique', l: 'Horaires et fermetures', ico: IC.clock, d: 'Les heures d\'ouverture délimitent l\'agenda ; les plages de travail de chacun se règlent dans le Planning.', blocks: [
      { t: 'Heures d\'ouverture', custom: 'horaires', aff: ['agenda'] },
      { t: 'Fermetures exceptionnelles', d: 'L\'agenda les grise.', custom: 'fermetures', aff: ['agenda'] },
    ] },
    { id: 'agenda', g: 'Activité', l: 'Agenda', ico: IC.cal, off: true },
    { id: 'planning', g: 'Activité', l: 'Planning', ico: IC.plan, off: true },
    { id: 'alertes', g: 'Activité', l: 'Alertes de soins', ico: IC.steth, off: true },
    { id: 'ordonnances', g: 'Soins', l: 'Ordonnances', ico: IC.rx, off: true },
    { id: 'facturation', g: 'Gestion', l: 'Facturation et caisse', ico: IC.euro, d: 'Numérotation, moyens de paiement et caisse. Les prix se règlent dans le Catalogue.', blocks: [
      { t: 'Facturation électronique', d: 'Réception obligatoire depuis le 1er septembre 2026, émission obligatoire pour votre clinique (PME) à partir du 1er septembre 2027. Kiveto dépose vos factures aux professionnels sur la plateforme agréée et déclare vos ventes aux particuliers (e-reporting).', rows: [R('fe.pa', 'Plateforme agréée', 'Celle qui transmet vos factures et vos déclarations à l\'administration.', 'dd', { o: [['kiveto', 'Partenaire de Kiveto (incluse)'], ['autre', 'Une autre plateforme agréée']], off: true, aff: ['fact'] }), R('fe.adr', 'Adresse de réception', 'Vos fournisseurs vous envoient leurs factures à ce SIREN, inscrit à l\'annuaire national.', 'copy', { val: () => V.siren ? 'SIREN ' + V.siren : null }), R('fe.regime', 'Régime de TVA', 'Fixe le rythme de l\'e-reporting : tous les 10 jours en réel normal mensuel, chaque mois sinon.', 'seg', { o: [['MONTHLY', 'Réel normal mensuel'], ['QUARTERLY', 'Réel normal trimestriel'], ['SIMPLIFIED', 'Simplifié']], aff: ['fact'] }), R('fe.debits', 'Option pour la TVA sur les débits', 'Sans cette option (cas général des soins), vos encaissements sont aussi déclarés. Si vous l\'avez prise, la mention figure sur chaque facture.', 'sw', { aff: ['fact'] })] },
      { t: 'Numérotation', d: 'Continue et sans trou, conformément aux règles de facturation.', rows: [R('fa.prefixe', 'Préfixe des factures', () => `Prochain numéro : ${NEXT.fa}.`, 'text', { m: 'prefixe', w: 120, aff: ['fact'] }), R('fa.av', 'Préfixe des avoirs', () => `Prochain numéro : ${NEXT.av}.`, 'text', { m: 'prefixe', w: 120, aff: ['fact'] })] },
      { t: 'Paiement', rows: [R('fa.moyens', 'Moyens acceptés', '', 'chips', { o: MOYENS, soon: ['Lien de paiement'], aff: ['fact'] }), R('fa.cond', 'Particuliers', '', 'seg', { o: [['0', 'À réception'], ['15', '15 jours'], ['30', '30 jours']], aff: ['fact'] }), R('fa.pro', 'Professionnels (élevages, refuges)', '', 'num', { u: 'jours', max: 365, aff: ['fact'] }), R('fa.multi', 'Paiement en plusieurs fois', '', 'sw', { off: true, aff: ['fact'] }), R('fa.max', 'Nombre d\'échéances au maximum', '', 'num', { dep: 'fa.multi', u: 'fois', off: true }), R('fa.min', 'À partir de', '', 'num', { dep: 'fa.multi', u: '€', off: true })] },
      { t: 'Virement', d: 'Imprimés au pied des factures pour les règlements par virement.', rows: [R('fa.iban', 'IBAN', '', 'text', { m: 'iban', w: 300, aff: ['docs'] }), R('fa.bic', 'BIC', '', 'text', { m: 'bic', w: 140, aff: ['docs'] }), R('fa.adr', 'Adresse d\'émission des factures', 'Seulement si elle diffère de l\'adresse de la clinique.', 'adresse', { col: true, pre: 'fa', opt: true, aff: ['docs'] })] },
      { t: 'Caisse', rows: [R('fa.fond', 'Fond de caisse', '', 'num', { u: '€', off: true, aff: ['fact'] }), R('fa.rappel', 'Rappel de clôture de caisse', 'Une notification à l\'accueil si la journée n\'est pas clôturée.', 'sw', { off: true }), R('fa.rheure', 'Heure du rappel', '', 'time', { dep: 'fa.rappel', off: true })] },
      { t: 'Mentions', rows: [R('fa.mentions', 'Pied de facture', '', 'textarea', { col: true, aff: ['docs', 'fact'] })] },
    ] },
    { id: 'stocks', g: 'Gestion', l: 'Stocks', ico: IC.box, off: true },
    { id: 'labos', g: 'Soins', l: 'Laboratoires et analyseurs', ico: IC.flask, off: true },
    { id: 'reprise', g: 'Système', l: 'Reprise des données', ico: IC.db, off: true },
    { id: 'securite', g: 'Système', l: 'Sécurité', ico: IC.lock, off: true },
    { id: 'moi', g: 'Personnel', l: 'Mes préférences', ico: IC.user, d: 'Ces réglages ne concernent que vous.', blocks: [
      { rows: [R('moi.accueil', 'Page d\'accueil', '', 'dd', { o: [['dashboard', 'Tableau de bord'], ['agenda', 'Agenda'], ['flux', 'Salle d\'attente']], off: true }), R('moi.clin', 'Clinique par défaut', 'Celle qui s\'ouvre à la connexion.', 'dd', { o: P.moi.clinics.map(c => [c[0], c[1]]) }), R('moi.notifs', 'Me notifier pour', '', 'chips', { o: [['lab', 'Résultats de laboratoire'], ['stock', 'Stock critique'], ['taches', 'Tâches qui me sont assignées'], ['paie', 'Paiements reçus']], off: true })] },
    ] },
  ];
  // A section is open when Kiveto handles it and the member may set it.
  const ouverte = s => !s.off && !!CAN[s.id];
  const ROWS = SECTIONS.filter(ouverte).flatMap(s => s.blocks.flatMap(b => (b.rows || []).map(r => ({ ...r, s }))));
  const secDe = k => ROWS.find(r => r.k === k || (r.type === 'adresse' && k.startsWith(r.pre + '.')))?.s.id
    ?? (k.startsWith('id.') ? 'identite' : k.startsWith('moi.') ? 'moi' : 'facturation');
  const txt = d => typeof d === 'function' ? d() : (d || '');

  /* ═════════ State ═════════ */
  const ST = { sec: SECTIONS.some(s => s.id === P.section && ouverte(s)) ? P.section : (SECTIONS.find(ouverte)?.id || 'moi'), q: '' };
  let HIST = [];

  /* ═════════ Server: one save after the other, the whole section each time ═════════ */
  let file = Promise.resolve();
  let enCours = 0;
  function valeurs(sec) {
    if (sec === 'horaires') return { week: HORAIRES.map(d => d[1]), closures: FERMETURES };
    if (sec === 'moi') return { 'moi.clin': V['moi.clin'] };
    const pre = sec === 'identite' ? ['id.'] : ['fa.', 'fe.'];
    return Object.fromEntries(Object.entries(V).filter(([k]) => pre.some(p => k.startsWith(p))));
  }
  function poster(sec) {
    const body = new FormData();
    body.append('_token', P.token);
    body.append('values', JSON.stringify(valeurs(sec)));
    return fetch(P.urls.save[sec], { method: 'POST', body, headers: { Accept: 'application/json' }, signal })
      .then(r => r.json().catch(() => ({})).then(d => { if (!r.ok) { const e = new Error(d.error || 'La modification n\'a pas pu être enregistrée.'); e.field = d.field; throw e; } return d; }));
  }
  /* Saves a section; `revert` puts the page back as it was if the server refuses. */
  function enregistrer(sec, desc, revert, undo) {
    enCours++;
    const sv = $('saved');
    sv.classList.add('busy'); sv.classList.remove('ko');
    $('savedT').textContent = 'Enregistrement…';
    file = file.then(() => poster(sec)).then(() => {
      if (undo) sauver(desc, () => { undo(); rendreContenu(false); enregistrer(sec, null, null, null); });
      else $('savedT').textContent = HIST.length ? 'Modification annulée' : 'Tout est enregistré';
    }).catch(e => {
      if (e.name === 'AbortError') return;
      if (revert) { revert(); rendreContenu(false); }
      sv.classList.add('ko');
      $('savedT').textContent = 'Non enregistré';
      showToast(e.message);
      if (e.field) { const t = $('pin').querySelector(`[data-txt="${e.field}"]`); if (t) erreur(t, e.message); }
    }).finally(() => { enCours--; if (!enCours) sv.classList.remove('busy'); });
    return file;
  }

  function sauver(desc, undo) {
    if (undo) HIST.push({ desc, undo });
    const sv = $('saved'); sv.classList.remove('pulse', 'ko'); void sv.offsetWidth; sv.classList.add('pulse');
    $('savedT').innerHTML = `Enregistré · ${esc(desc)}${undo ? ' <button type="button" id="svUndo">Annuler</button>' : ''}`;
    if (undo) $('svUndo').onclick = () => { const h = HIST.pop(); if (h) h.undo(); };
  }
  function setV(k, v, desc) {
    const av = V[k];
    if (JSON.stringify(av) === JSON.stringify(v)) return;
    V[k] = v;
    const back = () => { V[k] = av; };
    enregistrer(secDe(k), desc || ROWS.find(r => r.k === k)?.l || 'Réglage', back, back);
  }
  /* Hours and closures change as a block: the whole week is kept to put it back. */
  function setH(desc, change) {
    const av = structuredClone(HORAIRES), af = structuredClone(FERMETURES);
    change();
    const back = () => { HORAIRES = av; FERMETURES = af; };
    rendreContenu(false);
    enregistrer('horaires', desc, back, back);
  }

  /* ═════════ Navigation ═════════ */
  function rendreNav() {
    const G = [...new Set(SECTIONS.map(s => s.g))];
    const it = s => ouverte(s)
      ? `<button type="button" class="pn-it${s.id === ST.sec && !ST.q ? ' on' : ''}" data-s="${s.id}">${s.ico}${s.l}</button>`
      : `<button type="button" class="pn-it off" aria-disabled="true" data-tip="${s.off ? 'Bientôt dans Kiveto' : 'Réservé à la gestion de la clinique'}">${s.ico}${s.l}</button>`;
    $('pnav').innerHTML = '<div class="pn-ind" id="pnInd"></div>' + G.map(g => `<div class="pg-t">${g}</div>${SECTIONS.filter(s => s.g === g).map(it).join('')}`).join('')
      + `<div class="pg-t">Voir aussi</div><a class="pn-it" href="${esc(P.urls.team)}">${IC.team}Équipe et accès<span class="n">↗</span></a><a class="pn-it" href="${esc(P.urls.catalogue)}">${IC.euro}Catalogue et tarifs<span class="n">↗</span></a>`;
    placerInd();
  }
  function placerInd() {
    const onIt = document.querySelector('#pnav .pn-it.on'), ind = $('pnInd');
    if (!ind) return;
    if (!onIt) { ind.style.height = '0'; return; }
    ind.style.top = onIt.offsetTop + 'px'; ind.style.height = onIt.offsetHeight + 'px';
  }
  on($('pnav'), 'click', e => { const b = e.target.closest('[data-s]'); if (!b) return; allerSection(b.dataset.s); });
  function allerSection(id, k) {
    ST.sec = id; ST.q = ''; $('pq').value = '';
    try { history.replaceState(history.state, '', P.urls.sections[id]); } catch (_) { /* address unchanged */ }
    document.querySelectorAll('#pnav .pn-it').forEach(x => x.classList.toggle('on', x.dataset.s === id)); placerInd();
    rendreContenu(true);
    $('pcont').scrollTop = 0;
    if (k) setTimeout(() => { const r = document.querySelector(`[data-row="${k}"]`); if (r) { r.scrollIntoView({ block: 'center', behavior: 'smooth' }); r.classList.add('hl'); } }, 60);
  }

  /* ═════════ Rendering ═════════ */
  const affHtml = a => a && a.length ? `<span class="aff">${a.map(x => AFF[x][1] ? `<a href="${esc(AFF[x][1])}">${AFF[x][0]}${IC.ext}</a>` : `<span>${AFF[x][0]}</span>`).join('')}</span>` : '';
  const hl = (t, q) => { if (!q) return esc(t); const n = norm(t), i = n.indexOf(q); return i < 0 ? esc(t) : esc(t.slice(0, i)) + '<mark>' + esc(t.slice(i, i + q.length)) + '</mark>' + esc(t.slice(i + q.length)); };
  const initiales = () => {
    const mots = String(V['id.nom'] || P.clinicName).split(/[\s'’-]+/).filter(m => m && !/^(clinique|cabinet|centre|v[ée]t[ée]rinaires?|de|du|des|la|le|les|d|l|et)$/i.test(m));
    if (!mots.length) return 'K';
    return (mots.length === 1 ? mots[0].slice(0, 2) : mots[0][0] + mots[1][0]).toUpperCase();
  };
  function ctrl(r) {
    const v = r.off ? null : V[r.k];
    switch (r.type) {
      case 'sw': return `<span class="sw${v ? ' on' : ''}" data-sw="${r.k}" role="switch" aria-checked="${!!v}" tabindex="${r.off ? -1 : 0}" aria-label="${esc(r.l)}"></span>`;
      case 'seg': return `<div class="seg" data-seg="${r.k}">${r.o.map(([k, l]) => `<button type="button" data-v="${esc(k)}" class="${String(k) === String(v) ? 'on' : ''}">${l}</button>`).join('')}</div>`;
      case 'dd': return `<div class="dd" data-dd="${r.k}"></div>`;
      case 'num': return `<label class="numu"><input data-num="${r.k}" value="${esc(v ?? '')}" inputmode="numeric" aria-label="${esc(r.l)}"${r.off ? ' disabled' : ''}><span>${r.u || ''}</span></label>`;
      case 'time': return `<input class="finput" type="time" data-txt="${r.k}" value="${esc(v ?? '')}" style="width:120px">`;
      case 'text': return inp(r.k, v, r);
      case 'adresse': { const p = r.pre; return `<div class="adr">${inp(p + '.adr1', V[p + '.adr1'], { ph: 'N° et voie', max: 80, req: !r.opt })}${inp(p + '.adr2', V[p + '.adr2'], { ph: 'Complément (bâtiment, étage…) · facultatif', max: 80 })}<div class="adr2">${inp(p + '.cp', V[p + '.cp'], { m: 'cp', ph: 'Code postal' })}${inp(p + '.ville', V[p + '.ville'], { ph: 'Ville', max: 60, req: !r.opt })}</div></div>`; }
      case 'textarea': return `<textarea class="finput" data-txt="${r.k}" rows="3">${esc(v)}</textarea>`;
      case 'chips': return `<div class="chips" data-chips="${r.k}">${r.o.map(([c, l]) => `<button type="button" class="chip${v && v.includes(c) ? ' on' : ''}" data-v="${esc(c)}">${l}</button>`).join('')}${(r.soon || []).map(l => `<button type="button" class="chip" disabled data-tip="Bientôt dans Kiveto" style="opacity:.45;cursor:not-allowed">${l}</button>`).join('')}</div>`;
      case 'color': { const c = v || DEFAULT_COLOR; return `<span class="swatch" data-color="${r.k}" style="background:${esc(c)}" role="button" tabindex="0" aria-label="Choisir la couleur"></span><span class="code" style="font-size:.76rem;color:var(--encre-60)">${v ? esc(v) : 'Par défaut'}</span>`; }
      case 'copy': { const val = r.val(); return val ? `<div class="rocopy" style="width:320px"><code>${esc(val)}</code><button type="button" class="btn" data-copy="${esc(val)}" style="padding:7px 10px;font-size:.76rem">Copier</button></div>` : '<span class="code" style="font-size:.76rem;color:var(--encre-60)">Renseignez le SIRET dans Identité</span>'; }
      case 'logo': return `<div class="logo-up" id="logoUp"><span class="lg${LOGO ? ' img' : ''}" style="background:${esc(V['id.couleur'] || DEFAULT_COLOR)}">${LOGO ? `<img src="${esc(LOGO)}" alt="Logo de la clinique">` : esc(initiales())}</span><button type="button" class="btn" data-logo style="padding:7px 11px;font-size:.78rem">${LOGO ? 'Remplacer' : 'Ajouter'}</button>${LOGO ? '<button type="button" class="btn" data-logo-rm style="padding:7px 11px;font-size:.78rem">Retirer</button>' : ''}</div>`;
    }
    return '';
  }
  function inp(k, v, o = {}) {
    const M = MASK[o.m] || {};
    return `<span class="fld"><input class="finput" data-txt="${k}"${o.m ? ` data-m="${o.m}"` : ''}${o.req ? ' data-req="1"' : ''} value="${esc(v ?? '')}" placeholder="${esc(o.ph || M.ph || '')}"${o.max || M.max ? ` maxlength="${o.max || M.max}"` : ''}${M.im ? ` inputmode="${M.im}"` : ''} spellcheck="false" autocomplete="off"${o.w ? ` style="width:${o.w}px"` : ''}${o.off ? ' disabled' : ''}><small class="ferr" data-err="${k}"></small></span>`;
  }
  function rowHtml(r, q) {
    if (r.dep && (r.off || !V[r.dep]) && !q) return '';
    const d = txt(r.d);
    return `<div class="srow${r.col ? ' col' : ''}${r.off ? ' off' : ''}" data-row="${r.k}"><div class="sl"><b>${hl(r.l, q)}${r.off ? '<span class="soon" data-tip="Ce réglage n\'est pas encore pris en charge par Kiveto">Bientôt</span>' : ''}</b>${d ? `<small>${hl(d, q)}</small>` : ''}${affHtml(r.aff)}</div><div class="sc"${r.off ? ' aria-disabled="true"' : ''}>${ctrl(r)}</div></div>`;
  }
  function blockHtml(b, q) {
    return `<section class="pcard">${b.t ? `<header><h3>${b.t}</h3>${b.d ? `<p>${b.d}</p>` : ''}${b.aff ? `<div class="aff" style="margin-top:7px">${affHtml(b.aff).replace(/^<span class="aff">|<\/span>$/g, '')}</div>` : ''}</header>` : ''}<div class="pb">${b.custom ? CUSTOM[b.custom]() : b.rows.map(r => rowHtml(r, q)).join('')}</div></section>`;
  }
  function rendreContenu(anim) {
    const pin = $('pin'), q = norm(ST.q.trim());
    let h = '';
    if (q) {
      const M = ROWS.filter(r => norm(r.l + ' ' + txt(r.d) + ' ' + r.s.l).includes(q));
      const B = SECTIONS.filter(ouverte).flatMap(s => s.blocks.filter(b => b.custom && norm((b.t || '') + ' ' + (b.d || '')).includes(q)).map(b => ({ s, b })));
      if (!M.length && !B.length) h = `<div class="pvide"><b>Aucun réglage pour « ${esc(ST.q)} »</b>Essayez « TVA », « couleur », « SIRET », « fermeture »…</div>`;
      else {
        const parSec = {}; M.forEach(r => (parSec[r.s.id] = parSec[r.s.id] || []).push(r));
        B.forEach(({ s }) => { parSec[s.id] = parSec[s.id] || []; });
        h = Object.entries(parSec).map(([sid, rs]) => {
          const s = SECTIONS.find(x => x.id === sid);
          return `<div class="res-h">${s.l}<button type="button" data-go="${sid}">Ouvrir la section →</button></div>${rs.length ? `<section class="pcard"><div class="pb">${rs.map(r => rowHtml(r, q)).join('')}</div></section>` : ''}${B.filter(x => x.s.id === sid).map(x => blockHtml(x.b, q)).join('')}`;
        }).join('');
      }
    } else {
      const s = SECTIONS.find(x => x.id === ST.sec);
      h = `<div class="psh"><span class="ic">${s.ico}</span><div><h2>${s.l}</h2><p>${s.d}</p></div></div>` + s.blocks.map(b => blockHtml(b)).join('');
    }
    pin.innerHTML = h;
    if (anim) { pin.classList.remove('anim'); void pin.offsetWidth; pin.classList.add('anim'); }
    monter();
  }
  function monter() {
    document.querySelectorAll('#pin [data-dd]').forEach(el => {
      const r = ROWS.find(x => x.k === el.dataset.dd);
      creerDropdown(el, r.o.map(([v, l]) => ({ v, l })), r.off ? null : V[r.k], v => setV(r.k, v), r.off ? 'Bientôt' : 'Sélectionner…');
    });
  }

  /* ═════════ Custom blocks ═════════ */
  const dureePlages = PL => PL.reduce((s, [a, b]) => s + (toMin(b) - toMin(a)), 0);
  const CUSTOM = {
    horaires: () => `<div class="hgrid">${HORAIRES.map(([j, PL], i) => `<div class="hday" data-j="${i}"><b>${j}</b><span class="sw${PL.length ? ' on' : ''}" data-hsw="${i}" role="switch" aria-checked="${!!PL.length}" tabindex="0" aria-label="${j} ouvert"></span>
    <div class="plages">${PL.length ? PL.map(([a, b], k) => `<span class="pl"><input type="time" data-h="${i}.${k}.0" value="${a}" aria-label="${j}, ouverture"><span>→</span><input type="time" data-h="${i}.${k}.1" value="${b}" aria-label="${j}, fermeture">${PL.length > 1 ? `<button type="button" class="rm" data-hrm="${i}.${k}" aria-label="Retirer">${IC.rm}</button>` : ''}</span>`).join('') + (PL.length < 3 ? `<button type="button" class="addp" data-hadd="${i}">+ plage</button>` : '') + `<span class="tot">${(dureePlages(PL) / 60).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} h</span>` : '<span class="ferme">Fermé</span>'}</div></div>`).join('')}</div>`,
    fermetures: () => `<div class="elist">${FERMETURES.length ? '<div class="eli fer-g hd"><span>Du</span><span>Au</span><span>Motif</span><span></span></div>' : ''}${FERMETURES.map((f, i) => `<div class="eli fer-g"><input class="finput" type="date" data-fe="${i}.du" value="${esc(f.du)}" aria-label="Du"><input class="finput" type="date" data-fe="${i}.au" value="${esc(f.au)}" aria-label="Au"><input class="finput" data-fe="${i}.motif" value="${esc(f.motif)}" maxlength="80" aria-label="Motif"><button type="button" class="rm" data-ferm="${i}" aria-label="Retirer">${IC.rm}</button></div>`).join('')}</div><button type="button" class="addl" data-add="fermeture">+ Ajouter une fermeture</button>`,
  };
  /* A day's slots are in order and do not overlap: what the agenda can draw. */
  const plagesOk = PL => PL.every(([a, b], k) => a && b && toMin(a) < toMin(b) && (k === 0 || toMin(PL[k - 1][1]) <= toMin(a)));

  /* ═════════ Colour picker (shared with Équipe) ═════════ */
  const cpop = $('cpop');
  const hexRgb = h => { const x = h.replace('#', ''); return [0, 2, 4].map(i => parseInt(x.substr(i, 2), 16)); };
  const dist = (a, b) => { const [r1, g1, b1] = hexRgb(a), [r2, g2, b2] = hexRgb(b); return Math.sqrt((r1 - r2) ** 2 + (g1 - g2) ** 2 + (b1 - b2) ** 2); };
  let cpCb = null, cpPris = {};
  function ouvrirCouleur(anchor, val, cb, o = {}) {
    cpCb = cb; cpPris = o.pris || {};
    const rendreCp = v => {
      const proche = Object.entries(cpPris).find(([h]) => h.toLowerCase() !== v.toLowerCase() && dist(h, v) < 60);
      const prisPar = Object.entries(cpPris).find(([h]) => h.toLowerCase() === v.toLowerCase());
      cpop.innerHTML = `<h4>${esc(o.titre || 'Couleur')}</h4><div class="cgrid">${PALETTE.map(c => { const p = Object.entries(cpPris).find(([h]) => h.toLowerCase() === c); return `<button type="button" data-c="${c}" style="background:${c}" class="${c.toLowerCase() === v.toLowerCase() ? 'on' : ''}${p ? ' pris' : ''}" ${p ? `data-tip="Déjà utilisée : ${esc(p[1])}"` : ''} aria-label="${c}"></button>`; }).join('')}</div>
        <div class="chex"><span class="swatch" style="background:${esc(v)}"></span><input id="cpHex" value="${esc(v)}" maxlength="7" spellcheck="false" aria-label="Code couleur"></div>
        <div class="cnote ${prisPar || proche ? 'warn' : ''}">${prisPar ? `Déjà utilisée par ${esc(prisPar[1])} : les deux se confondront dans le sélecteur.` : proche ? `Très proche de celle de ${esc(proche[1])}.` : esc(o.note || '')}</div>`;
      cpop.querySelectorAll('.cgrid button').forEach(b => b.addEventListener('click', () => { pick(b.dataset.c); }));
      const hx = cpop.querySelector('#cpHex');
      hx.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(hx.value)) { if (cpCb) cpCb(hx.value.toLowerCase()); const p = hx.selectionStart; rendreCp(hx.value); const n = cpop.querySelector('#cpHex'); n.focus(); n.setSelectionRange(p, p); } });
    };
    const pick = c => { if (cpCb) cpCb(c); rendreCp(c); };
    rendreCp(val);
    cpop.classList.add('open');
    const r = anchor.getBoundingClientRect(), W = 268, H = cpop.offsetHeight;
    cpop.style.left = Math.min(Math.max(8, r.left), innerWidth - W - 8) + 'px';
    cpop.style.top = (r.bottom + 6 + H > innerHeight - 8 ? Math.max(8, r.top - H - 6) : r.bottom + 6) + 'px';
  }
  /* Picking colours in a row is one change: it is saved once the picker settles. */
  let couleurAv, couleurTmr = null;
  function fermerCouleur() { cpop.classList.remove('open'); if (couleurTmr) { clearTimeout(couleurTmr); couleurTmr = null; enregistrerCouleur(); } }
  function enregistrerCouleur() {
    const av = couleurAv; couleurAv = undefined;
    if (av === undefined || av === V['id.couleur']) return;
    const back = () => { V['id.couleur'] = av; };
    enregistrer('identite', 'Couleur', back, back);
  }
  function majCouleur(c) {
    if (couleurAv === undefined) couleurAv = V['id.couleur'];
    V['id.couleur'] = c;
    const sw = document.querySelector('[data-color="id.couleur"]');
    if (sw) { sw.style.background = c; const nx = sw.nextElementSibling; if (nx && nx.classList.contains('code')) nx.textContent = c; }
    const lg = document.querySelector('.logo-up .lg:not(.img)'); if (lg) lg.style.background = c;
    clearTimeout(couleurTmr); couleurTmr = setTimeout(() => { couleurTmr = null; enregistrerCouleur(); }, 700);
  }
  on(document, 'mousedown', e => { if (cpop.classList.contains('open') && !e.target.closest('.cpop') && !e.target.closest('[data-color]')) fermerCouleur(); });
  on(window, 'keydown', e => { if (e.key === 'Escape' && cpop.classList.contains('open')) { fermerCouleur(); e.stopImmediatePropagation(); e.stopPropagation(); } }, { capture: true });

  /* ═════════ Logo ═════════ */
  function envoyerLogo(f) {
    if (!f) return;
    if (!/^image\/(png|svg\+xml|jpeg|webp)$/.test(f.type)) { showToast('Choisissez une image PNG, SVG, JPEG ou WebP'); return; }
    if (f.size > 1048576) { showToast('Le logo ne doit pas dépasser 1 Mo'); return; }
    const body = new FormData();
    body.append('_token', P.token);
    body.append('logo', f);
    const sv = $('saved');
    enCours++; sv.classList.add('busy'); sv.classList.remove('ko');
    $('savedT').textContent = 'Enregistrement…';
    fetch(P.urls.logoUpload, { method: 'POST', body, headers: { Accept: 'application/json' }, signal })
      .then(r => r.json().catch(() => ({})).then(d => { if (!r.ok) throw new Error(d.error || 'Le logo n\'a pas pu être enregistré.'); return d; }))
      .then(d => { LOGO = d.url; rendreContenu(false); sauver('Logo', null); })
      .catch(e => { if (e.name !== 'AbortError') { sv.classList.add('ko'); $('savedT').textContent = 'Non enregistré'; showToast(e.message); } })
      .finally(() => { enCours--; if (!enCours) sv.classList.remove('busy'); });
  }
  function retirerLogo() {
    const body = new FormData();
    body.append('_token', P.token);
    fetch(P.urls.logoRemove, { method: 'POST', body, headers: { Accept: 'application/json' }, signal })
      .then(r => { if (!r.ok) throw new Error('Le logo n\'a pas pu être retiré.'); LOGO = null; rendreContenu(false); toastUndo('Logo retiré · les documents reprennent l\'initiale de la clinique'); })
      .catch(e => { if (e.name !== 'AbortError') showToast(e.message); });
  }
  on($('logoFile'), 'change', e => { envoyerLogo(e.target.files[0]); e.target.value = ''; });
  on($('pin'), 'dragover', e => { if (e.target.closest('#logoUp')) e.preventDefault(); });
  on($('pin'), 'drop', e => { if (!e.target.closest('#logoUp')) return; e.preventDefault(); envoyerLogo(e.dataTransfer.files[0]); });

  /* ═════════ Interactions ═════════ */
  const pin = $('pin');
  on(pin, 'click', e => {
    const t = e.target;
    if (t.closest('.srow.off')) return;
    const go = t.closest('[data-go]'); if (go) { allerSection(go.dataset.go); return; }
    const s = t.closest('[data-sw]'); if (s) { const k = s.dataset.sw; setV(k, !V[k]); s.classList.toggle('on', !!V[k]); s.setAttribute('aria-checked', String(!!V[k])); if (ROWS.some(r => r.dep === k)) rendreContenu(false); return; }
    const sb = t.closest('[data-seg] button'); if (sb) { const k = sb.closest('[data-seg]').dataset.seg, r = ROWS.find(x => x.k === k), raw = r.o.find(o => String(o[0]) === sb.dataset.v)[0]; setV(k, raw); sb.parentElement.querySelectorAll('button').forEach(b => b.classList.toggle('on', b === sb)); return; }
    const ch = t.closest('[data-chips] .chip:not([disabled])'); if (ch) {
      const k = ch.closest('[data-chips]').dataset.chips, v = ch.dataset.v, cur = V[k] || [];
      const arr = cur.includes(v) ? cur.filter(x => x !== v) : [...cur, v];
      if (!arr.length) { showToast('Gardez au moins un moyen de paiement'); return; }
      const r = ROWS.find(x => x.k === k);
      setV(k, r.o.map(o => o[0]).filter(x => arr.includes(x)), `${r.o.find(o => o[0] === v)[1]} ${arr.includes(v) ? 'accepté' : 'retiré'}`); ch.classList.toggle('on'); return;
    }
    const cp = t.closest('[data-copy]'); if (cp) { try { navigator.clipboard.writeText(cp.dataset.copy); } catch (_) { /* clipboard refused */ } showToast('Copié dans le presse-papiers'); return; }
    if (t.closest('[data-logo]')) { $('logoFile').click(); return; }
    if (t.closest('[data-logo-rm]')) { retirerLogo(); return; }
    const col = t.closest('[data-color]'); if (col) {
      const pris = {}; P.moi.clinics.forEach(([id, n, c]) => { if (c && n !== P.clinicName) pris[c] = n; });
      ouvrirCouleur(col, V['id.couleur'] || DEFAULT_COLOR, majCouleur, { titre: 'Couleur de la clinique', pris, note: 'Utilisée dans le sélecteur de clinique.' });
      return;
    }
    // hours
    const hs = t.closest('[data-hsw]'); if (hs) { const i = +hs.dataset.hsw; setH(`${HORAIRES[i][0]} ${HORAIRES[i][1].length ? 'fermé' : 'ouvert'}`, () => { HORAIRES[i][1] = HORAIRES[i][1].length ? [] : [['09:00', '12:30'], ['14:00', '19:00']]; }); return; }
    const ha = t.closest('[data-hadd]'); if (ha) {
      const i = +ha.dataset.hadd, PL = HORAIRES[i][1], fin = toMin(PL.at(-1)[1]);
      // The new slot starts after the last one, as the mock-up's 18:00 or 19:00 do.
      const deb = Math.max(fin + 30, fin < toMin('18:00') ? toMin('18:00') : toMin('19:00'));
      if (deb + 30 > 24 * 60 - 1) { showToast('Plus de place après la dernière plage'); return; }
      setH('Plage ajoutée', () => { PL.push([hm(deb), hm(Math.min(deb + 90, 24 * 60 - 1))]); }); return;
    }
    const hr = t.closest('[data-hrm]'); if (hr) { const [i, k] = hr.dataset.hrm.split('.').map(Number); setH('Plage retirée', () => { HORAIRES[i][1].splice(k, 1); }); return; }
    // closures
    if (t.closest('[data-add="fermeture"]')) { ajouterFermeture(); return; }
    const fr = t.closest('[data-ferm]'); if (fr) { const i = +fr.dataset.ferm; setH('Fermeture retirée', () => { FERMETURES.splice(i, 1); }); return; }
  });
  on(pin, 'keydown', e => { if ((e.key === ' ' || e.key === 'Enter') && e.target.matches('[role="switch"],[data-color]')) { e.preventDefault(); e.target.click(); } });

  function ajouterFermeture() {
    // The next May Day, the mock-up's « Jour férié »
    const now = new Date(), y = now.getMonth() > 4 || (now.getMonth() === 4 && now.getDate() > 1) ? now.getFullYear() + 1 : now.getFullYear();
    setH('Fermeture ajoutée', () => { FERMETURES.push({ du: `${y}-05-01`, au: `${y}-05-01`, motif: 'Jour férié' }); });
    setTimeout(() => { const ins = document.querySelectorAll('#pin .eli:last-child input:not([type=date])'); if (ins[0]) { ins[0].focus(); ins[0].select(); } }, 30);
  }

  function erreur(t, msg) { const e = t.parentElement.querySelector('.ferr'); t.classList.toggle('ko', !!msg); if (e) e.textContent = msg || ''; }
  on(pin, 'input', e => {
    const t = e.target;
    if (t.dataset.m && MASK[t.dataset.m]) { const v = MASK[t.dataset.m].f(t.value); if (v !== t.value && t.dataset.m !== 'mail' && t.dataset.m !== 'web') t.value = v; erreur(t, ''); }
    if (t.dataset.num != null) { const v = t.value.replace(/\D/g, '').slice(0, 5); if (v !== t.value) t.value = v; }
  });
  on(pin, 'change', e => {
    const t = e.target;
    if (t.dataset.txt) {
      const M = MASK[t.dataset.m], k = t.dataset.txt;
      const v = M ? M.f(t.value) : t.value.replace(/[ \t]+/g, ' ').trim();
      if (t.type !== 'time' && t.tagName !== 'TEXTAREA') t.value = v;
      if (t.dataset.req && !v) { erreur(t, 'Obligatoire.'); return; }
      if (M && v && !M.ok(v)) { erreur(t, typeof M.err === 'function' ? M.err() : M.err); return; }
      erreur(t, '');
      if ((v || null) === (V[k] || null)) return;
      setV(k, v || null, ROWS.find(r => r.k === k)?.l || (k.endsWith('cp') || k.endsWith('ville') || k.includes('adr') ? 'Adresse' : 'Réglage'));
      if (k === 'id.siret') { const tv = pin.querySelector('[data-txt="id.tva"]'); if (tv && V['id.tva'] && !MASK.tva.ok(V['id.tva'])) erreur(tv, MASK.tva.err()); }
      if (k === 'fa.prefixe' || k === 'fa.av') { const seq = (NEXT[k === 'fa.prefixe' ? 'fa' : 'av'] || '').split('-').slice(-2).join('-'); NEXT[k === 'fa.prefixe' ? 'fa' : 'av'] = `${v || (k === 'fa.prefixe' ? 'FAC' : 'AV')}-${seq}`; rendreContenu(false); }
      if (k === 'id.nom') { $('phSub').textContent = v; }
      if (k === 'id.siret') { const d = chiffres(v); V.siren = d.length === 14 ? `${d.slice(0, 3)} ${d.slice(3, 6)} ${d.slice(6, 9)}` : null; }
      return;
    }
    if (t.dataset.num) {
      const r = ROWS.find(x => x.k === t.dataset.num), raw = t.value.trim(), v = parseInt(raw, 10);
      if (raw === '') { setV(t.dataset.num, null); return; }
      if (isNaN(v) || (r && r.max && v > r.max)) { t.value = V[t.dataset.num] ?? ''; showToast(isNaN(v) ? 'Valeur numérique attendue' : `${r.max} au maximum`); return; }
      setV(t.dataset.num, v); return;
    }
    if (t.dataset.h) {
      const [i, k, j] = t.dataset.h.split('.').map(Number), PL = structuredClone(HORAIRES[i][1]);
      PL[k][j] = t.value;
      if (!plagesOk(PL)) { showToast('Les plages d\'un jour se suivent sans se chevaucher'); rendreContenu(false); return; }
      setH(`Horaires du ${HORAIRES[i][0].toLowerCase()}`, () => { HORAIRES[i][1] = PL; }); return;
    }
    if (t.dataset.fe != null) {
      const [i, f] = t.dataset.fe.split('.'), x = { ...FERMETURES[+i], [f]: t.value };
      if (f === 'du' && (!x.au || x.au < x.du)) x.au = x.du;
      if (!x.du || !x.au) { showToast('Indiquez les dates de la fermeture'); rendreContenu(false); return; }
      if (x.au < x.du) { showToast('La fermeture se termine avant de commencer'); rendreContenu(false); return; }
      setH('Fermeture', () => { FERMETURES[+i] = x; });
    }
  });

  /* ═════════ Search ═════════ */
  const pq = $('pq');
  on(pq, 'input', () => { ST.q = pq.value; document.querySelectorAll('#pnav .pn-it').forEach(x => x.classList.toggle('on', !ST.q && x.dataset.s === ST.sec)); placerInd(); rendreContenu(false); });
  on(document, 'keydown', e => {
    if (e.key === '/' && !e.target.matches('input,textarea,select,[contenteditable]') && !document.querySelector('.veil.open,.mveil.open,.racc-veil.open')) { e.preventDefault(); pq.focus(); }
    if (e.key === 'Escape' && document.activeElement === pq && pq.value) { pq.value = ''; pq.dispatchEvent(new Event('input')); }
  });

  /* A save still on its way when leaving would be lost. */
  on(window, 'beforeunload', e => { if (enCours || couleurTmr) { e.preventDefault(); e.returnValue = ''; } });

  /* ═════════ Start ═════════ */
  rendreNav(); rendreContenu(true);
  on(window, 'resize', placerInd);
}

export function cleanup() {
  if (ac) ac.abort();
  ac = null;
}
