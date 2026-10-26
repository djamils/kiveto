/**
 * Page module — Équipe (equipe-v-topbar_5 mock-up). Loaded by the app.js
 * dispatcher on clinic_settings_team.
 *
 * The mock-up renders from its demo data; this module renders the same way
 * from the `data-payload` of TeamPageBuilder — every membership of the clinic
 * with its profile, the clinics of the group each member also works in, and
 * the role map — and searches and filters it here.
 *
 * The member sheet saves as it goes, like the mock-up (« Enregistré
 * automatiquement », with « Annuler » on the last change): each field is
 * posted in the background to its single-action controller with the page's
 * token, then clinic_settings_team_data is asked again so the statuses the
 * server reads (window, last sign-in) stay true.
 *
 * « Inviter un membre » sends an e-mail with a 72-hour link to choose a
 * password (« Invité », « Invitation expirée » with « Renvoyer »).
 *
 * What Kiveto does not do yet is drawn but disabled, never faked: two-factor
 * authentication and session tracking, online booking, handwritten
 * signatures, editing the role map, and giving access to another clinic from
 * here.
 *
 * Listeners are bound in init() with one AbortSignal and released in cleanup().
 */
import { showToast, toastUndo, creerDropdown } from 'kiveto/shell';

const NIL = '00000000-0000-7000-8000-000000000000';
const PALETTE = ['#4f86c6', '#6dbe78', '#c67db5', '#e0a43a', '#e07a5f', '#4fb3bf', '#8d7bd6', '#d65d7a', '#7a9e3b', '#c4895b', '#5b7bd6', '#64748b'];
const QUOT = [100, 90, 80, 60, 50];

let ac = null;

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const norm = t => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const fmtD = s => s ? s.split('-').reverse().join('/') : '—';
const I = p => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
const ICO = {
  x: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  shield: I('<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>'),
  warn: I('<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>'),
  lock: I('<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>'),
  key: I('<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>'),
  ext: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10"/></svg>',
  info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
  crit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>',
};

export function init() {
  const root = document.querySelector('.eqp[data-payload]');
  if (!root) return;

  ac = new AbortController();
  const { signal } = ac;
  const on = (el, type, fn, opts = {}) => el && el.addEventListener(type, fn, { signal, ...opts });
  const $ = id => document.getElementById(id);

  const P = JSON.parse(root.dataset.payload);
  let MEM = P.members;
  let CLINIC = P.clinic;
  let TODAY = P.today;
  let CLINICS = P.clinics;

  const ROLES = Object.fromEntries(P.roles.map(r => [r.v, r]));
  const ENG = Object.fromEntries(P.engagements.map(e => [e.v, e]));
  const PERM = P.permissions;

  /* ═════════ Serveur ═════════ */

  async function envoyer(href, body) {
    body.append('_token', P.token);
    const r = await fetch(href, { method: 'POST', body, headers: { Accept: 'application/json' }, signal });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error || 'La modification n\'a pas pu être enregistrée');
    return d;
  }
  function poster(href, data = {}) {
    const body = new FormData();
    Object.entries(data).forEach(([k, v]) => { if (v !== undefined) body.append(k, v === null ? '' : String(v)); });
    return envoyer(href, body);
  }
  async function recharger() {
    const r = await fetch(P.urls.data, { headers: { Accept: 'application/json' }, signal });
    if (!r.ok) throw new Error('L\'équipe n\'a pas pu être rechargée');
    const d = await r.json();
    MEM = d.members; CLINIC = d.clinic; TODAY = d.today; CLINICS = d.clinics;
  }
  const url = (k, id) => P.urls[k].replace(NIL, id);
  const echec = e => { if (e.name !== 'AbortError') showToast(e.message, 'danger'); };

  /* ═════════ Équipe ═════════ */

  const mem = id => MEM.find(m => m.id === id);
  const nom = m => [m.titre, m.p, m.n].filter(Boolean).join(' ');
  const ini = m => ((m.p || '')[0] ? (m.p[0] + (m.n || ' ')[0]) : (m.n || '?').slice(0, 2)).toUpperCase();
  const contratL = m => `${ENG[m.contrat] ? ENG[m.contrat].l : m.contrat}${m.tps < 100 ? ` · ${m.tps} %` : ''}`;
  const rpuce = k => ROLES[k] ? `<span class="puce r-${ROLES[k].cl}">${esc(ROLES[k].l)}</span>` : '';
  const hat = '<span class="puce r-admin" data-tip="Gère la clinique : équipe, facturation et paramètres">Gestion</span>';
  const statutM = m => [{ act: 's-act', tmp: 's-tmp', inv: 's-inv', off: 's-off' }[m.statut] || 's-act', m.sl];
  const clinic = id => CLINICS.find(c => c.id === id);
  const toutes = m => CLINICS.length > 1 && CLINICS.every(c => m.clin.includes(c.id));
  const pris = m => Object.fromEntries(MEM.filter(x => x.id !== m.id && x.statut !== 'off' && x.profile).map(x => [x.c, nom(x)]));
  const droits = m => new Set([...(PERM.roles[m.role] || []), ...(m.admin ? PERM.admin : [])]);
  const enLigne = m => m.last === 'en ligne';

  /* ═════════ État ═════════ */

  const ST = { tab: 'membres', q: '', f: { role: '', statut: 'actifs' }, fiche: null, hl: null, flash: false, saved: null };
  let HIST = [];

  function rendreFiltres() {
    const box = $('fdd'); box.innerHTML = '';
    [
      { k: 'role', l: 'Rôle', o: [...P.roles.map(r => ({ v: r.v, l: r.l })), { v: 'admin', l: 'Gère la clinique' }] },
      { k: 'statut', l: 'Statut', o: [{ v: 'actifs', l: 'Membres actuels' }, { v: 'inv', l: 'En attente' }, { v: 'off', l: 'Accès désactivés' }, { v: 'tous', l: 'Tous' }], sans: true },
    ].forEach(fd => {
      const el = document.createElement('div');
      el.className = 'dd fpill' + (ST.f[fd.k] && !(fd.k === 'statut' && ST.f[fd.k] === 'actifs') ? ' actif' : '');
      box.appendChild(el);
      creerDropdown(el, fd.sans ? fd.o : [{ v: '', l: 'Tous' }, ...fd.o], ST.f[fd.k], v => { ST.f[fd.k] = v; rendre(); });
      if (!ST.f[fd.k]) el.querySelector('.dd-btn > span').textContent = fd.l;
    });
  }
  function visibles() {
    const q = norm(ST.q);
    return MEM.filter(m => (!ST.f.role || (ST.f.role === 'admin' ? m.admin : m.role === ST.f.role))
      && (ST.f.statut === 'tous' || (ST.f.statut === 'actifs' ? m.statut !== 'off' : m.statut === ST.f.statut))
      && (!q || norm([nom(m), m.mail, ROLES[m.role] ? ROLES[m.role].l : '', contratL(m), m.admin ? 'gestion gere la clinique' : ''].join(' ')).includes(q)));
  }
  const COLS = [
    { l: 'Membre', html: m => `<div class="mem"><span class="avt${m.statut === 'off' ? ' off' : ''}" style="background:${esc(m.c)}">${esc(ini(m))}${enLigne(m) ? '<i class="dot"></i>' : ''}</span><div style="min-width:0"><b>${esc(nom(m))}${m.moi ? ' <span class="puce" style="display:inline-flex;margin-left:4px">Vous</span>' : ''}</b><span>${esc(contratL(m))}</span></div></div>` },
    { l: 'Rôle', html: m => `<span style="display:inline-flex;gap:4px">${rpuce(m.role)}${m.admin ? hat : ''}</span>` },
    { l: 'Agenda', xo: 1, html: m => m.profile ? `<span class="agc"><span class="blk" style="background:${esc(m.c)}38;border-left-color:${esc(m.c)};color:#1e293b">${esc(ini(m))}</span>${m.visible ? 'dans l\'agenda' : 'masqué'}</span>` : '<span class="agc">—</span>' },
    { l: 'Cliniques', xo: 1, html: m => toutes(m) ? '<span class="clins"><span class="tout">Tout le groupe</span></span>' : `<span class="clins">${m.clin.map(clinic).filter(Boolean).map(c => `<span style="background:${esc(c.color)}" data-tip="${esc(c.name)}">${esc(c.code)}</span>`).join('')}</span>` },
    { l: 'Sécurité', xo: 1, html: m => m.statut === 'inv' ? '<span class="sec2" style="color:var(--encre-35)">—</span>' : `<span class="sec2" style="color:var(--encre-60)" data-tip="La double authentification n'est pas encore proposée">${ICO.key}Mot de passe</span>` },
    { l: 'Statut', html: m => { const [c, l] = statutM(m); return `<span class="puce ${c}">${esc(l)}</span>`; } },
    { l: 'Activité', html: m => `<span class="last">${enLigne(m) ? '<b>en ligne</b>' : esc(m.last)}</span>` },
  ];
  function rendre() {
    $('nMem').textContent = MEM.filter(m => m.statut !== 'off').length;
    $('nRol').textContent = P.roles.length;
    const r = ST.tab === 'roles';
    $('czone').style.display = r ? 'none' : '';
    $('cfil').style.display = r ? 'none' : '';
    $('rzone').classList.toggle('on', r);
    if (r) { rendreRoles(); return; }
    const L = visibles();
    $('thead').innerHTML = `<tr>${COLS.map(c => `<th class="${c.xo ? 'xo' : ''}">${c.l}</th>`).join('')}</tr>`;
    $('tbody').innerHTML = L.map(m => `<tr data-id="${esc(m.id)}" class="${ST.fiche === m.id ? 'cur' : ''}${m.statut === 'off' ? ' off' : ''}">${COLS.map(c => `<td class="${c.xo ? 'xo' : ''}">${c.html(m)}</td>`).join('')}</tr>`).join('');
    $('cempty').innerHTML = L.length ? '' : (MEM.length
      ? '<div class="cempty"><b>Personne</b>Modifiez la recherche ou les filtres.</div>'
      : '<div class="cempty"><b>Personne pour l\'instant</b>Invitez le premier membre de l\'équipe.</div>');
    const vets = MEM.filter(m => m.role === 'VETERINARY' && (m.statut === 'act' || m.statut === 'tmp')).length;
    const att = MEM.filter(m => m.etat === 'jamais').length;
    $('phSub').textContent = `${CLINIC} · ${vets} vétérinaire${vets > 1 ? 's' : ''}${att ? ` · ${att} en attente de connexion` : ''}`;
    rendreFiltres();
  }
  on($('tbody'), 'click', e => { const tr = e.target.closest('tr'); if (tr) ouvrirFiche(tr.dataset.id); });
  on($('q'), 'input', e => { ST.q = e.target.value; rendre(); });
  on($('tabs'), 'click', e => {
    const b = e.target.closest('button'); if (!b) return;
    ST.tab = b.dataset.t; ST.hl = null;
    document.querySelectorAll('#tabs button').forEach(x => x.classList.toggle('on', x === b));
    fermerFiche(); rendre();
  });

  /* ═════════ Fiche ═════════ */

  const czone = $('czone');
  const fiBody = $('fiBody');

  function majSaved(pulse) {
    const f = root.querySelector('.fsaved'); if (!f) return;
    f.querySelector('span').innerHTML = ST.saved ? `Enregistré · ${esc(ST.saved)} <button type="button" class="lien" id="fsUndo" style="font-size:.74rem">Annuler</button>` : 'Enregistré automatiquement';
    if (pulse) { f.classList.remove('pulse'); void f.offsetWidth; f.classList.add('pulse'); }
    const u = $('fsUndo'); if (u) u.onclick = () => { const h = HIST.pop(); if (h) h(); };
  }
  function ouvrirFiche(id) { ST.fiche = id; ST.saved = null; HIST = []; czone.classList.add('ouvert'); rendreFiche(); rendre(); fiBody.scrollTop = 0; }
  function fermerFiche() { if (!ST.fiche) return; ST.fiche = null; fermerCouleur(); czone.classList.remove('ouvert'); rendre(); }

  const lab = (l, s, forId) => `<label class="flbl"${forId ? ` for="${forId}"` : ''}>${l}${s ? ` <span class="opt">· ${s}</span>` : ''}</label>`;
  const seg = (f, o, v, dis) => `<div class="seg" data-seg="${f}">${o.map(([k, l]) => `<button type="button" data-v="${esc(k)}" class="${k === v ? 'on' : ''}"${dis ? ` aria-disabled="true" data-tip="${esc(dis)}"` : ''}>${l}</button>`).join('')}</div>`;

  function bandeaux(m) {
    if (m.etat === 'invite') return `<div class="bandeau info">${ICO.info}<div><b>Invitation envoyée</b>Un e-mail à ${esc(m.mail)} l'invite à choisir son mot de passe ; le lien est valable 72 heures.<div class="act"><button type="button" data-act="renvoyer">Renvoyer l'invitation</button></div></div></div>`;
    if (m.etat === 'expire') return `<div class="bandeau warn">${ICO.warn}<div><b>Invitation expirée</b>Le lien envoyé à ${esc(m.mail)} n'est plus valable : envoyez-en un nouveau.<div class="act"><button type="button" data-act="renvoyer">Renvoyer</button></div></div></div>`;
    if (m.etat === 'jamais') return `<div class="bandeau info">${ICO.info}<div><b>Pas encore connecté</b>Son compte ${esc(m.mail)} est actif. Il passe « Actif » à sa première connexion.</div></div>`;
    if (m.etat === 'avenir') return `<div class="bandeau info">${ICO.info}<div><b>Arrive le ${fmtD(m.debut)}</b>Son accès s'ouvre de lui-même ce jour-là.<div class="act"><button type="button" data-act="ouvrir">Ouvrir l'accès dès aujourd'hui</button></div></div></div>`;
    if (m.etat === 'desactive') return `<div class="bandeau crit">${ICO.crit}<div><b>Accès désactivé</b>Ses actes restent signés de son nom.<div class="act"><button type="button" data-act="reactiver">Réactiver l'accès</button></div></div></div>`;
    if (m.etat === 'termine') return `<div class="bandeau crit">${ICO.crit}<div><b>Accès terminé le ${fmtD(m.fin)}</b>Ses actes restent signés de son nom.<div class="act"><button type="button" data-act="rouvrir">Rouvrir l'accès</button></div></div></div>`;
    return '';
  }

  function rendreFiche() {
    const m = mem(ST.fiche); if (!m) { fermerFiche(); return; }
    const [sc, sl] = statutM(m), off = m.statut === 'off', sansProfil = !m.profile;
    const dis = sansProfil ? ' disabled data-tip="Membre ajouté sans profil : non modifiable ici"' : '';
    // Keep the field being typed in, and the scroll, across a redraw.
    const act = document.activeElement && fiBody.contains(document.activeElement) ? document.activeElement.dataset.f : null;
    const top = fiBody.scrollTop;

    $('fiHead').innerHTML = `<div class="eh"><span class="avt big${off ? ' off' : ''}" style="background:${esc(m.c)}">${esc(ini(m))}</span><div class="t"><b>${esc(nom(m))}</b><span>${rpuce(m.role)}${m.admin ? hat : ''}<span class="puce ${sc}">${esc(sl)}</span></span></div></div><button type="button" class="fi-x" id="fiX" data-tip="Fermer · Échap" aria-label="Fermer">${ICO.x}</button>`;

    const D = droits(m);
    const resume = PERM.groups.map(g => {
      const ok = g.p.filter(p => D.has(p.k)).map(p => p.c);
      return `<div><span>${esc(g.l)}</span><p class="${ok.length ? '' : 'no'}">${ok.length ? esc(ok.join(', ').replace(/^./, c => c.toUpperCase())) : 'Aucun accès'}</p></div>`;
    }).join('');
    const vet = m.role === 'VETERINARY' && !sansProfil;
    const tmpSans = ENG[m.contrat] && ENG[m.contrat].tmp && !m.fin && !off;
    const titres = [['DR', 'Dr'], ...(m.title === 'PR' ? [['PR', 'Pr']] : []), ['NONE', 'Aucun']];

    fiBody.innerHTML = `
      ${bandeaux(m)}
      <section class="fs"><h3>Profil</h3>
        <div class="frow2"><div>${lab('Prénom')}<input class="finput" data-f="p" value="${esc(m.p)}"${dis}></div><div>${lab('Nom')}<input class="finput" data-f="n" value="${esc(m.n)}"${dis}></div></div>
        <div class="frow2"><div>${lab('E-mail de connexion')}<div class="ro" data-tip="L'adresse de connexion ne se modifie pas depuis l'équipe">${esc(m.mail)}${ICO.lock}</div></div><div>${lab('Téléphone')}<input class="finput" data-f="tel" value="${esc(m.tel)}" placeholder="—" inputmode="tel"${dis}></div></div>
        <div class="frow">${lab('Couleur', 'dans l\'agenda, le planning et les listes')}<div class="colrow"><span class="swatch" ${sansProfil ? 'data-tip="Membre ajouté sans profil : non modifiable ici"' : 'data-color="m" role="button" tabindex="0"'} style="background:${esc(m.c)};width:40px;height:40px;border-radius:10px" aria-label="Choisir la couleur"></span>
          <div class="colprev"><small>Aperçu</small><div class="ag" style="background:${esc(m.c)}38;border-left-color:${esc(m.c)}"><b>10:30 · Minou</b>Contrôle IRC · ${esc(ini(m))}</div><div class="pl"><span style="width:26px;font-weight:700;color:${esc(m.c)}">${esc(ini(m))}</span><i style="background:${esc(m.c)}2b"></i><i style="background:${esc(m.c)}2b;flex:.6"></i></div></div></div></div>
        ${sansProfil ? '<p class="hint">Ajouté sans profil (depuis l\'administration Kiveto) : son nom, son téléphone et sa couleur ne se modifient pas ici.</p>' : `<div class="swrow"><span class="sw${m.visible ? ' on' : ''}" data-swf="visible" role="switch" aria-checked="${m.visible}" tabindex="0"></span><div><b>Visible dans l'agenda</b><small>Une colonne à son nom dans l'agenda et le planning.</small></div></div>`}
      </section>
      ${vet ? `<section class="fs"><h3>Exercice vétérinaire</h3>
        <div class="frow2"><div>${lab('N° d\'inscription à l\'Ordre')}<input class="finput" data-f="ordre" value="${esc(m.ordre)}" maxlength="32" placeholder="Non inscrit"></div><div>${lab('Titre')}${seg('title', titres, m.veto ? m.title : '', m.veto ? '' : 'Renseignez d\'abord son n° d\'Ordre')}</div></div>
        <p class="hint" style="margin:-4px 0 6px">${m.veto ? 'Inscrit à l\'Ordre : il peut prescrire, son numéro figure sur chaque ordonnance.' : 'Sans n° d\'Ordre, il ne peut pas signer d\'ordonnance.'}</p>
        <div class="swrow lock"><span class="sw" role="switch" aria-checked="false" aria-disabled="true" tabindex="-1" data-tip="La réservation en ligne n'est pas encore proposée"></span><div><b>Réservable en ligne</b><small>Ses plages « réservables » du planning apparaîtront sur la page de réservation (bientôt).</small></div></div>
        <div class="frow" style="margin-top:8px">${lab('Signature', 'apposée sur les ordonnances et comptes rendus')}<div class="sig" style="font-family:var(--font-body);font-size:.8rem;color:var(--encre-35)">${m.sig ? 'Signature enregistrée' : 'Aucune signature enregistrée'}</div><button type="button" class="lien" style="margin-top:6px;opacity:.5;cursor:not-allowed" aria-disabled="true" data-tip="Signature au doigt ou au stylet : bientôt disponible">Refaire la signature</button></div>
      </section>` : ''}
      <section class="fs"><h3>Rôle<span class="tag"><button type="button" class="lien" data-act="voirrole">Voir ses permissions</button></span></h3>
        <div class="frow"><div class="chips" data-role>${P.roles.map(r => { const no = m.veto && r.v !== 'VETERINARY'; return `<button type="button" class="chip${m.role === r.v ? ' on' : ''}" data-v="${r.v}"${no ? ' aria-disabled="true" data-tip="Inscrit à l\'Ordre : effacez d\'abord son n° d\'inscription"' : ''}>${esc(r.l)}</button>`; }).join('')}</div>
        <p class="hint">${esc(ROLES[m.role] ? ROLES[m.role].d : '')}</p>
        <div class="swrow${m.moi ? ' lock' : ''}" style="margin-top:8px"><span class="sw${m.admin ? ' on' : ''}" data-act="gestion" role="switch" aria-checked="${m.admin}" tabindex="0"${m.moi ? ' aria-disabled="true"' : ''}></span><div><b>Gère la clinique</b><small>Équipe, facturation et paramètres, en plus de son rôle.${m.moi ? ' Vous ne pouvez pas retirer votre propre gestion.' : ''}</small></div></div>
        <div class="rsum">${resume}</div></div>
      </section>
      <section class="fs"><h3>Cliniques</h3>
        ${CLINICS.length > 1 ? `<div class="swrow lock" style="border:none;padding-top:0"><span class="sw${toutes(m) ? ' on' : ''}" role="switch" aria-checked="${toutes(m)}" aria-disabled="true" tabindex="-1" data-tip="L'accès à une autre clinique se donne depuis l'équipe de cette clinique"></span><div><b>Toutes les cliniques du groupe</b><small>Celles où vous travaillez aussi.</small></div></div>` : ''}
        <div class="clinck ro-list">${CLINICS.map(c => { const a = m.clin.includes(c.id); return `<label class="${a ? 'on' : ''}"><span class="sq" style="background:${esc(c.color)}">${esc(c.code)}</span>${esc(c.name)}<small>${a ? 'accès' : '—'}</small></label>`; }).join('')}</div>
        ${CLINICS.length > 1 ? '<p class="hint">L\'accès à une autre clinique se donne depuis l\'équipe de cette clinique.</p>' : ''}
      </section>
      <section class="fs"><h3>Contrat</h3>
        <div class="frow">${lab('Type de contrat')}<div class="chips" data-ct>${P.engagements.map(c => `<button type="button" class="chip${m.contrat === c.v ? ' on' : ''}" data-v="${c.v}">${esc(c.l)}</button>`).join('')}</div></div>
        ${m.contrat === 'INTERN' || m.contrat === 'REPLACEMENT' ? '' : `<div class="frow">${lab('Temps de travail')}${seg('tps', QUOT.map(q => [String(q), q === 100 ? 'Temps plein' : q + ' %']), String(m.tps))}</div>`}
        <div class="frow2"><div>${lab('Arrivée')}<input class="finput" type="date" data-f="debut" value="${esc(m.debut)}"></div><div>${lab('Fin de l\'accès', ENG[m.contrat] && ENG[m.contrat].tmp ? 'fin du contrat' : 'facultatif')}<input class="finput" type="date" data-f="fin" value="${esc(m.fin || '')}"></div></div>
        ${tmpSans ? '<p class="hint" style="color:#b45309">Contrat à durée limitée sans date de fin : l\'accès restera ouvert après son départ.</p>' : ''}
        <p class="hint">À la date de fin, l'accès se coupe automatiquement à minuit. Ses horaires se règlent dans le <a class="lien" href="${esc(P.urls.planning)}">Planning ${ICO.ext}</a></p>
      </section>
      ${m.statut !== 'inv' ? `<section class="fs"><h3>Sécurité</h3>
        <div class="swrow" style="border:none;padding-top:0"><span style="color:var(--encre-35);width:32px;display:grid;place-items:center">${ICO.key.replace('<svg', '<svg width="18" height="18"')}</span><div><b>Connexion par mot de passe</b><small>La double authentification n'est pas encore proposée par Kiveto.</small></div></div>
        ${!off ? `<div style="margin-top:10px">${lab('Sessions ouvertes')}<p class="hint" style="margin-top:0">Le suivi des sessions n'est pas encore disponible. Dernière connexion : ${esc(m.last)}.</p></div>
        <div style="display:flex;gap:8px;margin-top:10px"><button type="button" class="btn" disabled data-tip="Bientôt disponible" style="padding:6px 10px;font-size:.78rem">Déconnecter partout</button><button type="button" class="btn" disabled data-tip="Bientôt disponible" style="padding:6px 10px;font-size:.78rem">Réinitialiser le mot de passe</button></div>` : ''}
      </section>` : ''}`;

    $('fiFoot').innerHTML = `${m.moi || off ? '' : '<button type="button" class="btn ghost-danger" data-act="off">Désactiver l\'accès</button>'}<span class="sp"></span><span class="fsaved"><i></i><span>Enregistré automatiquement</span></span>`;
    majSaved(false);
    $('fiX').onclick = fermerFiche;
    fiBody.scrollTop = top;
    if (act) { const el = fiBody.querySelector(`[data-f="${act}"]`); if (el) el.focus(); }
  }

  /* Each field of the sheet, the route it is saved through and what it sends. */
  function champ(m, f) {
    switch (f) {
      case 'p': case 'n': return ['name', { firstName: m.p, lastName: m.n }];
      case 'tel': return ['phone', { phone: m.tel }];
      case 'c': case 'visible': return ['agenda', { color: m.c, visible: m.visible ? 1 : 0 }];
      case 'ordre': case 'title': return ['credentials', { registrationNumber: m.ordre, title: m.title }];
      case 'role': return ['role', { role: m.role }];
      case 'admin': return [m.admin ? 'grant' : 'revoke', {}];
      case 'contrat': return ['engagement', { engagement: m.contrat }];
      case 'tps': return ['workTime', { workTime: m.tps }];
      case 'debut': case 'fin': return ['validity', { from: m.debut, until: m.fin || '' }];
      default: return null;
    }
  }

  /**
   * Saves one field: drawn at once, posted, then the team asked again. A
   * refusal puts the field back and says why. The undo of a change is the
   * same write with the previous value.
   */
  async function ecrire(id, f, v, desc, undoable = true) {
    const m = mem(id); if (!m) return;
    const av = m[f]; if (av === v) return;
    m[f] = v;
    if (f === 'ordre') m.veto = !!v;
    rendreFiche(); rendre();
    const [k, data] = champ(m, f);
    try {
      await poster(url(k, id), data);
      if (undoable) { HIST.push(() => ecrire(id, f, av, desc, false)); ST.saved = desc; } else ST.saved = HIST.length ? 'modification annulée' : null;
      await recharger();
      rendre(); if (ST.fiche) rendreFiche();
      majSaved(true);
    } catch (e) {
      const x = mem(id); if (x) { x[f] = av; if (f === 'ordre') x.veto = !!av; }
      rendre(); if (ST.fiche) rendreFiche();
      echec(e);
    }
  }

  on(fiBody, 'change', e => {
    const f = e.target.dataset.f; if (!f || !ST.fiche) return;
    let v = e.target.value.trim();
    if ((f === 'p' || f === 'n') && !v) { showToast('Le prénom et le nom sont nécessaires', 'danger'); rendreFiche(); return; }
    if (f === 'debut' && !v) { showToast('La date d\'arrivée est nécessaire', 'danger'); rendreFiche(); return; }
    if (f === 'fin') v = v || null;
    ecrire(ST.fiche, f, v, { p: 'Prénom', n: 'Nom', tel: 'Téléphone', ordre: 'N° d\'Ordre', debut: 'Arrivée', fin: 'Fin de l\'accès' }[f]);
  });
  on(fiBody, 'click', e => {
    const m = mem(ST.fiche); if (!m) return;
    const t = e.target;
    const col = t.closest('[data-color]');
    if (col) {
      ouvrirCouleur(col, m.c, c => ecrireCouleur(m.id, c), { titre: 'Couleur de ' + (m.p || m.n), pris: pris(m), note: 'Utilisée dans l\'agenda, le planning et les listes.' });
      return;
    }
    const r = t.closest('[data-role] .chip');
    if (r) {
      if (r.getAttribute('aria-disabled')) { showToast('Inscrit à l\'Ordre : effacez d\'abord son n° d\'inscription'); return; }
      if (r.dataset.v === m.role) return;
      ecrire(m.id, 'role', r.dataset.v, `Rôle ${ROLES[r.dataset.v].l}`);
      return;
    }
    const ct = t.closest('[data-ct] .chip'); if (ct) { ecrire(m.id, 'contrat', ct.dataset.v, 'Contrat'); return; }
    const sg = t.closest('[data-seg] button');
    if (sg) {
      if (sg.getAttribute('aria-disabled')) { showToast('Renseignez d\'abord son n° d\'Ordre'); return; }
      const f = sg.closest('[data-seg]').dataset.seg;
      if (f === 'tps') ecrire(m.id, 'tps', +sg.dataset.v, 'Temps de travail'); else ecrire(m.id, f, sg.dataset.v, 'Titre');
      return;
    }
    const sw = t.closest('[data-swf]'); if (sw) { ecrire(m.id, 'visible', !m.visible, 'Visible dans l\'agenda'); return; }
    const ac2 = t.closest('[data-act]'); if (ac2) action(m, ac2.dataset.act);
  });
  on($('fiFoot'), 'click', e => { const a = e.target.closest('[data-act]'); if (a) action(mem(ST.fiche), a.dataset.act); });

  /* Colour: drawn at once on every pick, saved once the hand stops. */
  let colTimer = null, colAvant = null;
  function ecrireCouleur(id, c) {
    const m = mem(id); if (!m) return;
    if (colAvant === null) colAvant = m.c;
    m.c = c; rendreFiche(); rendre();
    clearTimeout(colTimer);
    colTimer = setTimeout(() => {
      const av = colAvant; colAvant = null;
      const x = mem(id); if (!x) return;
      x.c = av;
      ecrire(id, 'c', c, 'Couleur');
    }, 450);
  }

  async function bascule(m, k, data, msg, undo) {
    try {
      await poster(url(k, m.id), data);
      await recharger(); rendre(); if (ST.fiche) rendreFiche();
      if (undo) toastUndo(msg, undo); else showToast(msg);
    } catch (e) { echec(e); }
  }
  function action(m, k) {
    if (!m) return;
    if (k === 'voirrole') allerRole(m.role);
    if (k === 'gestion') {
      if (m.moi) { showToast('Vous ne pouvez pas retirer votre propre gestion de la clinique'); return; }
      ecrire(m.id, 'admin', !m.admin, m.admin ? 'Gestion retirée' : 'Gère la clinique');
    }
    if (k === 'off') { $('offS').textContent = nom(m); ouvrirModale('mOff'); }
    if (k === 'reactiver') bascule(m, 'enable', {}, `Accès de ${m.p || nom(m)} réactivé`, () => bascule(m, 'disable', {}, `Accès de ${m.p || nom(m)} désactivé`));
    if (k === 'rouvrir') ecrire(m.id, 'fin', null, 'Accès rouvert');
    if (k === 'ouvrir') ecrire(m.id, 'debut', TODAY, 'Arrivée');
    if (k === 'renvoyer') bascule(m, 'resend', {}, `Nouvelle invitation envoyée à ${m.mail}`);
  }
  on($('offOk'), 'click', () => {
    const m = mem(ST.fiche); if (!m) return;
    fermerModales();
    bascule(m, 'disable', {}, `Accès de ${nom(m)} désactivé`, () => bascule(m, 'enable', {}, `Accès de ${m.p || nom(m)} réactivé`));
  });

  /* ═════════ Sélecteur de couleur partagé ═════════ */

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
        <div class="cnote ${prisPar || proche ? 'warn' : ''}">${prisPar ? `Déjà utilisée par ${esc(prisPar[1])} : les deux se confondront à l'agenda.` : proche ? `Très proche de celle de ${esc(proche[1])}.` : esc(o.note || '')}</div>`;
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
  function fermerCouleur() { cpop.classList.remove('open'); }
  on(document, 'mousedown', e => { if (cpop.classList.contains('open') && !e.target.closest('.cpop') && !e.target.closest('[data-color]')) fermerCouleur(); });
  on(window, 'keydown', e => { if (e.key === 'Escape' && cpop.classList.contains('open')) { fermerCouleur(); e.stopImmediatePropagation(); e.stopPropagation(); } }, { capture: true });

  /* ═════════ Rôles ═════════ */

  const nb = k => MEM.filter(m => (k === 'admin' ? m.admin : m.role === k) && m.statut !== 'off').length;
  const nbL = k => nb(k) ? `${nb(k)} membre${nb(k) > 1 ? 's' : ''}` : 'aucun membre';
  function rendreRoles() {
    const hl = k => ST.hl === k ? 'hl' + (ST.flash ? ' flash' : '') : '';
    const cols = [{ v: 'admin', l: 'Gestion' }, ...P.roles];
    $('rzone').innerHTML = `<div class="rcard"><div class="rbar"><p>Chaque membre a un rôle ; la gestion de la clinique s'y ajoute. Les permissions de chaque rôle sont fixées par Kiveto.</p></div><div class="rscroll"><table class="rtab2"><thead><tr><th>Permission</th>${cols.map(r => `<th class="${hl(r.v)}" data-col="${r.v}"><span class="rh"><b>${esc(r.l)}</b></span><small>${nbL(r.v)}</small></th>`).join('')}</tr></thead><tbody>
      ${PERM.groups.map(g => `<tr class="grp"><td colspan="${cols.length + 1}">${esc(g.l)}</td></tr>${g.p.map(p => `<tr><td>${esc(p.l)}${p.d ? `<small>${esc(p.d)}</small>` : ''}</td>${cols.map(r => r.v === 'admin'
        ? (PERM.admin.includes(p.k)
          ? `<td class="${hl(r.v)}"><span class="lockc" data-tip="La gestion donne ce droit, quel que soit le rôle"><span class="sw on" style="opacity:.45;cursor:default"></span></span></td>`
          : `<td class="${hl(r.v)}"><span class="nil" data-tip="Selon le rôle du membre">—</span></td>`)
        : p.k === 'create_prescription' && !(PERM.roles[r.v] || []).includes(p.k)
          ? `<td class="${hl(r.v)}"><span class="lockc" data-tip="Réservé aux rôles de vétérinaire">${ICO.lock}</span></td>`
          : `<td class="${hl(r.v)}"><span class="sw fixe${(PERM.roles[r.v] || []).includes(p.k) ? ' on' : ''}" data-rp="${r.v}" role="switch" aria-checked="${(PERM.roles[r.v] || []).includes(p.k)}" aria-disabled="true" tabindex="0" aria-label="${esc(p.l)} · ${esc(r.l)}"></span></td>`).join('')}</tr>`).join('')}`).join('')}
    </tbody></table></div></div><p class="rnote">Prescrire demande aussi d'être inscrit à l'Ordre, et seul le rôle de vétérinaire peut l'être.</p>`;
    ST.flash = false;
  }
  function montrerCol(k) { const th = root.querySelector(`.rtab2 th[data-col="${k}"]`); if (th) th.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' }); }
  function allerRole(k) {
    fermerFiche(); ST.tab = 'roles'; ST.hl = k; ST.flash = true;
    document.querySelectorAll('#tabs button').forEach(x => x.classList.toggle('on', x.dataset.t === 'roles'));
    rendre(); montrerCol(k);
  }
  on($('rzone'), 'click', e => {
    if (e.target.closest('[data-rp]')) showToast('Les permissions des rôles sont fixées par Kiveto pour l\'instant');
  });

  /* ═════════ Invitation ═════════ */

  let IV = { role: 'VETERINARY', tmp: false, adm: false };
  const plus30 = () => { const d = new Date(TODAY + 'T12:00:00'); d.setDate(d.getDate() + 30); return d.toISOString().slice(0, 10); };
  function ouvrirInv() {
    IV = { role: 'VETERINARY', tmp: false, adm: false };
    ['ivP', 'ivN', 'ivE', 'ivO'].forEach(id => { $(id).value = ''; });
    $('ivFin').value = plus30(); $('ivFin').min = TODAY;
    $('ivTmp').classList.remove('on'); $('ivAdm').classList.remove('on');
    rendreInv(); ouvrirModale('mInv');
    setTimeout(() => $('ivP').focus(), 50);
  }
  function rendreInv() {
    $('ivR').innerHTML = P.roles.map(r => `<button type="button" class="chip${IV.role === r.v ? ' on' : ''}" data-v="${r.v}">${esc(r.l)}</button>`).join('');
    $('ivRh').textContent = ROLES[IV.role].d;
    $('ivOrdreRow').style.display = IV.role === 'VETERINARY' ? '' : 'none';
    $('ivFinRow').style.display = IV.tmp ? '' : 'none';
    $('ivTmp').setAttribute('aria-checked', IV.tmp); $('ivAdm').setAttribute('aria-checked', IV.adm);
    majInv();
  }
  function majInv() {
    const ok = $('ivP').value.trim() && $('ivN').value.trim() && /\S+@\S+\.\S+/.test($('ivE').value)
      && (IV.role !== 'VETERINARY' || $('ivO').value.trim())
      && (!IV.tmp || $('ivFin').value);
    $('ivOk').disabled = !ok;
  }
  on($('btnInv'), 'click', ouvrirInv);
  on($('ivR'), 'click', e => { const b = e.target.closest('[data-v]'); if (!b) return; IV.role = b.dataset.v; rendreInv(); });
  on($('ivTmp'), 'click', e => { IV.tmp = !IV.tmp; e.currentTarget.classList.toggle('on', IV.tmp); rendreInv(); });
  on($('ivAdm'), 'click', e => { IV.adm = !IV.adm; e.currentTarget.classList.toggle('on', IV.adm); rendreInv(); });
  ['ivP', 'ivN', 'ivE', 'ivO', 'ivFin'].forEach(id => on($(id), 'input', majInv));
  on($('ivOk'), 'click', async () => {
    const b = $('ivOk'); b.disabled = true;
    const p = $('ivP').value.trim(), n = $('ivN').value.trim(), mail = $('ivE').value.trim();
    const libre = PALETTE.find(c => !MEM.some(m => m.c === c && m.statut !== 'off')) || '#64748b';
    try {
      const r = await poster(P.urls.invite, {
        firstName: p, lastName: n, email: mail, role: IV.role,
        engagement: IV.tmp ? 'FIXED_TERM' : 'EMPLOYEE',
        registrationNumber: IV.role === 'VETERINARY' ? $('ivO').value.trim() : '',
        isClinicAdministrator: IV.adm ? 1 : 0,
        validUntil: IV.tmp ? $('ivFin').value : '', color: libre,
      });
      fermerModales();
      await recharger();
      ST.f.statut = 'actifs';
      ouvrirFiche(r.id);
      toastUndo(r.invited ? `Invitation envoyée à ${mail} · couleur attribuée automatiquement` : `${p} ${n} a rejoint l'équipe · couleur attribuée automatiquement`, () => {
        const m = mem(r.id); if (m) bascule(m, 'disable', {}, `Accès de ${p} ${n} désactivé`);
      });
    } catch (e) { echec(e); majInv(); }
  });

  /* ═════════ Modales et clavier ═════════ */

  function ouvrirModale(id) { $(id).classList.add('open'); }
  function fermerModales() { root.querySelectorAll('.mveil.open').forEach(m => m.classList.remove('open')); }
  root.querySelectorAll('.mveil').forEach(m => {
    on(m, 'mousedown', e => { if (e.target === m) fermerModales(); });
    m.querySelectorAll('[data-close]').forEach(b => on(b, 'click', fermerModales));
  });
  on(document, 'keydown', e => {
    if (e.key !== 'Escape') return;
    if (root.querySelector('.mveil.open')) { fermerModales(); e.stopPropagation(); return; }
    if (document.querySelector('.dd.open') || document.querySelector('.veil.open') || cpop.classList.contains('open')) return;
    if (ST.fiche) { fermerFiche(); e.stopPropagation(); }
  }, { capture: true });
  on(document, 'keydown', e => {
    if ((e.key === ' ' || e.key === 'Enter') && e.target.matches && e.target.matches('.eqp [role="switch"], .eqp [data-color]')) { e.preventDefault(); e.target.click(); }
  });

  /* ═════════ Démarrage ═════════ */

  try {
    const p = new URLSearchParams(location.search);
    if (p.get('onglet') === 'roles') { ST.tab = 'roles'; document.querySelectorAll('#tabs button').forEach(x => x.classList.toggle('on', x.dataset.t === 'roles')); }
    const id = p.get('membre');
    if (id && mem(id)) { ST.fiche = id; czone.classList.add('ouvert'); rendreFiche(); }
  } catch (_) { /* malformed address: open on the list */ }
  rendre();
}

export function cleanup() {
  if (ac) ac.abort();
  ac = null;
}
