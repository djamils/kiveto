/**
 * Page module — animal sheet (animal-v-topbar_5 mock-up and its -busy /
 * -vierge variants). Loaded by the app.js dispatcher on turbo:load.
 *
 * The mock-up renders the sheet from its demo data; this module renders it
 * the same way from the `data-sheet` payload of AnimalSheetBuilder: identity
 * header, medical synthesis band (read-then-edit card), the four tabs —
 * history with filters and expandable rows, prevention, curves, documents.
 * Every change goes through a real form posted to its single-action
 * controller; the controller comes back with ?saved=<card> and the card
 * flashes green, as after a save in the mock-up.
 *
 * Listeners are bound in init() with one AbortSignal and released in cleanup().
 */
import { showToast, creerDropdown } from 'kiveto/shell';
import {
  esc, dates, MOIS, fmtN, fmtE, fmtQ, IC, KPLUR, icT, champ, liste, lireChamps, masquesLive,
  carte, marquer, flashSaved, menu, ouvrirModale, fermerModales, bindModales, onglets, lignes,
} from 'pages/dossier/kit';

/* Closed lists: no free typing where a list will do (as in the mock-up). */
const RACES = {
  Chat: ['Européen', 'Maine coon', 'Persan', 'Sacré de Birmanie', 'Bengal', 'British shorthair', 'Chartreux', 'Ragdoll', 'Siamois', 'Sphynx', 'Norvégien', 'Croisé'],
  Chien: ['Berger australien', 'Beagle', 'Bouledogue français', 'Border collie', 'Cavalier King Charles', 'Golden retriever', 'Jack Russell', 'Labrador retriever', 'Malinois', 'Shih tzu', 'Teckel', 'Yorkshire', 'Croisé'],
  NAC: ['Lapin', 'Lapin bélier', 'Cochon d\'Inde', 'Furet', 'Rat', 'Hamster', 'Perroquet', 'Tortue'],
};
const ROBES = {
  Chat: ['Tigré brun', 'Tigré gris', 'Roux', 'Noir', 'Blanc', 'Noir et blanc', 'Écaille de tortue', 'Bleu', 'Crème', 'Colourpoint'],
  Chien: ['Fauve', 'Noir', 'Blanc', 'Tricolore', 'Bringé', 'Merle', 'Noir et feu', 'Marron', 'Sable', 'Crème'],
  NAC: ['Unie', 'Bicolore', 'Tricolore', 'Autre'],
};
const ASSUREURS = ['Santévet', 'Bulle Bleue', 'Assur O\'Poil', 'Agria', 'SPB Animaux', 'Groupama', 'Autre assureur'];
const ESPECES = { Chat: 'cat', Chien: 'dog', NAC: 'nac' };

const SYN = [['pb', 'Problèmes actifs', '#dc2626', 'Ex. Diabète sucré'], ['trt', 'Traitements au long cours', '#16a34a', 'Ex. Prednisolone 5 mg'], ['all', 'Allergies', '#b91c1c', 'Ex. Pénicillines'], ['comp', 'Comportement', '#f59e0b', 'Ex. Muselière obligatoire']];
const VIDE = { pb: 'Aucun', trt: 'Aucun', all: 'Aucune connue', comp: 'Rien à signaler' };
const ORDRE_SYN = ['Diagnostic', 'Conclusion', 'Actes', 'Examen', 'Imagerie', 'Motif'];

let ac = null;

export function init() {
  const root = document.querySelector('.dossier[data-sheet]');
  if (!root || !document.getElementById('cSyn')) return;

  ac?.abort();
  ac = new AbortController();
  const { signal } = ac;

  const S = JSON.parse(root.dataset.sheet);
  const A = S.animal;
  const { TODAY_ISO, dCourt, dAn, dLong, fmtD, jours, age } = dates(S.today);
  const $ = id => document.getElementById(id);
  const VIS = S.visits;
  const ST = { f: '' };
  const CARTES = {};
  const L = lignes();
  const LECTURE = !A.editable;
  // The closed lists know cats, dogs and NACs; any other species reads as a NAC.
  const listeEsp = esp => (esp === 'Chat' || esp === 'Chien' ? esp : 'NAC');

  const enCours = () => (A.dcd ? null : A.enCours);
  const prochain = () => (A.dcd ? null : VIS.filter(x => x.st === 'avenir').sort((a, b) => (a.d + a.h).localeCompare(b.d + b.h))[0]);
  const sterL = () => (A.ster ? (A.sexe === 'F' ? 'stérilisée' : 'castré') : (A.sexe === 'F' ? 'entière' : 'entier'));
  const totalV = v => v.total || 0;
  const statutV = v => {
    if (v.st === 'encours') return '<span class="encours">en cours</span>';
    if (v.st === 'absent') return '<span class="etat ko"><i></i>Non venu</span>';
    if (v.st !== 'avenir') return '';
    const j = jours(v.d);
    return `<span class="etat pin"><i></i>${j <= 0 ? 'Aujourd\'hui' : j === 1 ? 'Demain' : `Dans ${j} j`}</span>`;
  };

  bindModales(root, signal);

  /* ── Identity header ── */
  function rendreTete() {
    const dc = A.dcd, enc = enCours();
    $('phAv').textContent = A.e;
    const sx = A.sexe === 'U'
      ? '<span class="sx" style="background:var(--brume);color:var(--encre-60)">Sexe non renseigné</span>'
      : `<span class="sx" style="background:${A.sexe === 'F' ? '#fdf2f8;color:#be185d' : '#eff6ff;color:#1d4ed8'}"><b>${A.sexe === 'F' ? '♀' : '♂'}</b>${A.sexe === 'F' ? 'Femelle' : 'Mâle'} ${sterL()}</span>`;
    const vigs = [...A.all.map(x => ['r', 'Allergie : ' + x.l]), ...A.pb.map(x => ['r', x.l]), ...A.comp.map(x => ['a', x.l])];
    const vu = vigs.slice(0, 5);
    $('phNom').innerHTML = `${esc(A.nom)}${LECTURE ? '' : `<button type="button" class="ph-edit" id="btnId" title="Modifier l'identité" aria-label="Modifier l'identité">${IC.crayon}</button>`}${sx}${vu.map(([c, l]) => `<span class="vig ${c}">${esc(l)}</span>`).join('')}${vigs.length > vu.length ? `<span class="vig g" title="${esc(vigs.slice(5).map(v => v[1]).join(' · '))}">+${vigs.length - vu.length}</span>` : ''}${A.isNew ? '<span class="vig b">Nouveau patient</span>' : ''}${A.archived ? '<span class="vig g">Fiche archivée</span>' : ''}`;
    const p = S.series.poids || [], poids = p.length ? p.at(-1)[1] : null, tr = p.length > 1 ? p.at(-1)[1] - p.at(-2)[1] : 0;
    const espece = `<span><b>${esc(A.esp)}</b>${A.race ? ` · ${esc(A.race)}` : ''}${A.robe ? ` · ${esc(A.robe.toLowerCase())}` : ''}</span>`;
    const naiss = A.naiss
      ? `<span>${A.sexe === 'F' ? 'Née' : 'Né'} le ${fmtD(A.naiss)} · <b>${dc ? age(A.naiss, dc.d) + ' au décès' : age(A.naiss)}</b></span>`
      : '<span style="color:var(--encre-35)">Date de naissance inconnue</span>';
    $('phFacts').innerHTML = `${espece}${naiss}<span>${poids ? `<b>${fmtN(poids)} kg</b>${Math.abs(tr) > 1e-9 ? ` <span style="color:${tr < 0 ? '#b45309' : '#15803d'}" title="Depuis la mesure précédente">${tr < 0 ? '▼' : '▲'}</span>` : ''}` : '<span style="color:var(--encre-35)">Poids non relevé</span>'}</span><span title="N° de puce">${IC.puce}<span class="mono">${esc(A.puce || '—')}</span></span><span title="Assurance santé">${IC.bouclier}${A.assur ? esc(A.assur) : '<span style="color:var(--encre-35)">Sans assurance</span>'}</span>${A.proprio ? `<span title="Propriétaire">${IC.user}<a href="${esc(A.proprio[1])}">${esc(A.proprio[0])}</a></span>` : ''}`;
    const c = $('btnCons'), r = $('btnRdv');
    if (dc || A.archived) {
      c.style.display = 'none';
      r.style.display = 'none';
      $('dcdBand').innerHTML = dc
        ? `<div class="dcd-band"><span>🕊</span><span><b>Décédé${A.sexe === 'F' ? 'e' : ''} le ${dAn(dc.d)}</b>${[dc.c, dc.corps && dc.corps.toLowerCase()].filter(Boolean).map(x => ' · ' + esc(x)).join('')}. Dossier en lecture seule.</span></div>`
        : '<div class="dcd-band"><span>🗄</span><span><b>Fiche archivée</b> · masquée des recherches, rien n\'est supprimé. Dossier en lecture seule.</span></div>';
      return;
    }
    $('dcdBand').innerHTML = '';
    r.innerHTML = `${IC.cal.replace('<svg', '<svg width="15" height="15"')}Prendre RDV`;
    c.innerHTML = enc ? `Reprendre la consultation <span class="encours" style="background:rgba(255,255,255,.22)">${esc(enc.h)}</span>` : `${IC.plus.replace('<svg', '<svg width="15" height="15"')}Nouvelle consultation`;
    if (!enc) c.dataset.tip = 'Enregistre l\'arrivée de l\'animal, puis la consultation démarre depuis le flux';
  }
  $('phNom').addEventListener('click', e => { if (e.target.closest('#btnId')) ouvrirIdentite(); }, { signal });

  /* ── Medical synthesis band (4 columns + next appointment) ── */
  let BR = null;
  const cSyn = $('cSyn');
  carte(cSyn, {
    titre: 'Synthèse médicale', lecture: LECTURE, metaHaut: true,
    avant() { BR = Object.fromEntries(SYN.map(([k]) => [k, A[k].map(x => ({ ...x }))])); },
    lire() {
      const vis = prochain();
      return SYN.map(([k, t, c]) => `<div class="sy scr"><h3><i style="background:${c}"></i>${t}${A[k].length ? `<span class="n">${A[k].length}</span>` : ''}</h3>${A[k].length ? A[k].map(o => `<div class="sy-it"><div><b>${esc(o.l)}</b>${o.d ? `<small>${esc(o.d)}</small>` : ''}</div></div>`).join('') : `<p class="vide">${VIDE[k]}</p>`}</div>`).join('')
        + `<div class="sy"><h3><i style="background:var(--pin)"></i>Prochain rendez-vous</h3>${vis ? `<div class="sy-it"><div><b>${esc(vis.t)}</b><small>${dLong(vis.d)} à ${vis.h}<br>${esc(vis.v)}${vis.note ? '<br>' + esc(vis.note) : ''}</small><div class="sy-act"><a class="lien" href="${esc(vis.appt.move)}">Déplacer</a>${vis.appt.cancel ? `<button type="button" class="lien" data-annul="${vis.id}" style="color:#b91c1c">Annuler</button>` : ''}</div></div></div>` : `<p class="vide">${A.dcd ? '—' : 'Aucun'}</p>`}</div>`;
    },
    editer() {
      return SYN.map(([k, t, c, ph]) => `<div class="sy scr"><h3><i style="background:${c}"></i>${t}</h3>${BR[k].map((o, i) => `<div class="sy-it${o.rm ? ' retire' : ''}${o.nv ? ' new' : ''}"><div><b>${esc(o.l)}</b>${o.d ? `<small>${esc(o.d)}</small>` : ''}</div><button type="button" class="x" data-rm="${k}.${i}" title="${o.rm ? 'Rétablir' : 'Retirer'}" aria-label="${o.rm ? 'Rétablir' : 'Retirer'}">${o.rm ? '↺' : IC.x.replace('<svg', '<svg width="12" height="12"')}</button></div>`).join('')}<div class="addf"><input class="finput" placeholder="${esc(ph)}" maxlength="80" data-addin="${k}"><button type="button" class="btn" data-addok="${k}">Ajouter</button></div></div>`).join('') + '<div class="sy"></div>';
    },
    valider() {
      const ajout = [], retrait = [];
      SYN.forEach(([k]) => BR[k].forEach(o => { if (o.nv && !o.rm) ajout.push([k, o]); if (o.rm && !o.nv) retrait.push(o.id); }));
      if (!ajout.length && !retrait.length) return { change: false };
      return { change: true, appliquer: () => envoyerSyn(ajout, retrait) };
    },
  }, CARTES, signal);

  function envoyerSyn(ajout, retrait) {
    const f = $('synPost'), add = (n, v) => { const i = document.createElement('input'); Object.assign(i, { type: 'hidden', name: n, value: v }); f.appendChild(i); };
    retrait.forEach(id => add('remove[]', id));
    ajout.forEach(([k, o], i) => { add(`add[${k}][${i}][l]`, o.l); add(`add[${k}][${i}][d]`, o.d || ''); });
    f.requestSubmit();
  }

  function fonduSyn() {
    cSyn.querySelectorAll('.sy').forEach(c => {
      const d = c.scrollHeight > c.clientHeight + 2;
      c.classList.toggle('defile-h', c.scrollTop > 2);
      c.classList.toggle('deb', d);
      c.classList.toggle('fin', d && c.scrollTop + c.clientHeight >= c.scrollHeight - 2);
    });
  }
  cSyn.addEventListener('click', e => {
    const rm = e.target.closest('[data-rm]');
    if (rm) {
      const [k, i] = rm.dataset.rm.split('.'), o = BR[k][+i];
      if (o.nv) BR[k].splice(+i, 1); else o.rm = !o.rm;
      marquer(CARTES.cSyn);
      CARTES.cSyn.rendre();
      return;
    }
    const ok = e.target.closest('[data-addok]');
    if (ok) ajouterSyn(ok.dataset.addok);
  }, { signal });
  const moSyn = new MutationObserver(() => requestAnimationFrame(fonduSyn));
  moSyn.observe(cSyn, { childList: true, subtree: true });
  signal.addEventListener('abort', () => moSyn.disconnect());
  cSyn.addEventListener('scroll', fonduSyn, { capture: true, signal });
  cSyn.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.metaKey && !e.ctrlKey && e.target.dataset?.addin) { e.preventDefault(); ajouterSyn(e.target.dataset.addin); } }, { signal });
  function ajouterSyn(k) {
    const i = cSyn.querySelector(`[data-addin="${k}"]`), v = i.value.replace(/\s+/g, ' ').trim();
    if (v.length < 2) { i.focus(); return; }
    BR[k].push({ l: v.replace(/^./, c => c.toUpperCase()), d: `Ajouté le ${fmtD(TODAY_ISO)}`, nv: true });
    marquer(CARTES.cSyn);
    CARTES.cSyn.rendre();
    cSyn.querySelector(`[data-addin="${k}"]`).focus();
  }

  /* ── Identity: in the header, edited in its own window ── */
  let IDF = null;
  const idForm = $('idForm');
  function ouvrirIdentite() {
    IDF = { esp: A.esp, race: A.race, robe: A.robe, sexe: A.sexe, ster: A.ster, assur: A.assur };
    $('idSub').textContent = `${A.nom} · ${A.esp.toLowerCase()}${A.naiss ? ` de ${age(A.naiss)}` : ''}`;
    $('idMeta').textContent = A.meta.id ? `Modifié le ${A.meta.id}` : '';
    idForm.innerHTML = '';
    rendreIdForm();
    ouvrirModale('mId');
    setTimeout(() => $('f_nom').focus(), 60);
  }
  // A breed or a coat typed elsewhere stays selectable next to the closed list.
  const options = (list, cur) => [...(cur && !list.includes(cur) ? [cur] : []), ...list].map(x => ({ v: x, l: x }));
  function rendreIdForm() {
    const nom = idForm.querySelector('#f_nom')?.value ?? A.nom, naiss = idForm.querySelector('#f_naiss')?.value ?? (A.naiss || ''), puce = idForm.querySelector('#f_puce')?.value ?? A.puce, assN = idForm.querySelector('#f_assurN')?.value ?? A.assurN;
    const le = listeEsp(IDF.esp);
    idForm.innerHTML = `<div class="f2">${champ('nom', nom, { l: 'Nom', req: true, m: 'nom', max: 30 })}<div><label class="flbl">Espèce</label><div class="seg" id="sgEsp">${['Chat', 'Chien', 'NAC'].map(x => `<button type="button" data-v="${x}" class="${IDF.esp === x ? 'on' : ''}">${x}</button>`).join('')}</div></div></div>
      <div class="f2">${liste('race', 'Race', true)}${liste('robe', 'Robe', true)}</div>
      <div class="f2"><div><label class="flbl">Sexe</label><div style="display:flex;gap:12px;align-items:center"><div class="seg" id="sgSexe"><button type="button" data-v="F" class="${IDF.sexe === 'F' ? 'on' : ''}">Femelle</button><button type="button" data-v="M" class="${IDF.sexe === 'M' ? 'on' : ''}">Mâle</button></div>
        <label style="display:flex;align-items:center;gap:8px;font-size:.8rem;font-weight:600;color:var(--encre-60);cursor:pointer;white-space:nowrap"><span class="sw${IDF.ster ? ' on' : ''}" id="swSter" role="switch" aria-checked="${IDF.ster}" tabindex="0"></span>${IDF.sexe === 'F' ? 'Stérilisée' : 'Castré'}</label></div></div>
        ${champ('naiss', naiss, { l: 'Date de naissance', type: 'date', req: true, maxd: TODAY_ISO })}</div>
      ${champ('puce', puce, { l: 'N° de puce', m: 'puce', ph: '15 chiffres' })}
      <div class="f2">${liste('assur', 'Assurance santé')}${IDF.assur ? champ('assurN', assN, { l: 'N° de contrat', m: 'contrat', req: true }) : '<div></div>'}</div>`;
    if (IDF.race && !RACES[le].includes(IDF.race) && IDF.race !== A.race) IDF.race = '';
    if (IDF.robe && !ROBES[le].includes(IDF.robe) && IDF.robe !== A.robe) IDF.robe = '';
    const garde = IDF.esp === A.esp;
    creerDropdown($('dd_race'), options(RACES[le], garde ? A.race : ''), IDF.race, v => { IDF.race = v; okListe('race'); sale(); }, 'Choisir la race');
    creerDropdown($('dd_robe'), options(ROBES[le], garde ? A.robe : ''), IDF.robe, v => { IDF.robe = v; okListe('robe'); sale(); }, 'Choisir la robe');
    creerDropdown($('dd_assur'), [{ v: '', l: 'Aucune' }, ...options(ASSUREURS, A.assur)], IDF.assur, v => { IDF.assur = v; sale(); rendreIdForm(); }, 'Aucune');
  }
  const okListe = k => { $('dd_' + k).classList.remove('ko'); $('err_' + k).textContent = ''; };
  const sale = () => $('mId').setAttribute('data-dirty', '1');
  masquesLive(idForm, sale, signal);
  idForm.addEventListener('click', e => {
    const es = e.target.closest('#sgEsp button');
    if (es && es.dataset.v !== IDF.esp) { IDF.esp = es.dataset.v; sale(); rendreIdForm(); return; }
    const sg = e.target.closest('#sgSexe button');
    if (sg && sg.dataset.v !== IDF.sexe) { IDF.sexe = sg.dataset.v; sale(); rendreIdForm(); return; }
    const sw = e.target.closest('#swSter');
    if (sw) { IDF.ster = !IDF.ster; sw.classList.toggle('on', IDF.ster); sw.setAttribute('aria-checked', IDF.ster); sale(); }
  }, { signal });
  $('idOk').addEventListener('click', () => {
    const V = lireChamps(idForm);
    let ok = !!V;
    [['race', IDF.race], ['robe', IDF.robe]].forEach(([k, v]) => { if (!v) { $('dd_' + k).classList.add('ko'); $('err_' + k).textContent = 'Choisissez dans la liste.'; ok = false; } });
    if (!ok) return;
    if (V.naiss > TODAY_ISO) { const t = $('f_naiss'); t.classList.add('ko'); t.parentElement.querySelector('.ferr').textContent = 'Date dans le futur.'; return; }
    const N = { nom: V.nom, naiss: V.naiss, puce: V.puce, esp: IDF.esp, race: IDF.race, robe: IDF.robe, sexe: IDF.sexe, ster: IDF.ster, assur: IDF.assur, assurN: IDF.assur ? V.assurN : '' };
    const ch = Object.keys(N).filter(k => String(N[k] ?? '') !== String(A[k] ?? ''));
    if (!ch.length) { fermerModales(); return; }
    const f = $('idPost'), set = (n, v) => { f.querySelector(`[name="${n}"]`).value = v ?? ''; };
    set('name', N.nom);
    set('species', ESPECES[N.esp] || A.species);
    set('breed', N.race);
    set('color', N.robe);
    set('sex', N.sexe === 'F' ? 'female' : N.sexe === 'M' ? 'male' : 'unknown');
    set('neutered', N.ster ? '1' : '0');
    set('birth_date', N.naiss);
    set('microchip', N.puce);
    set('insurer', N.assur);
    set('policy_number', N.assurN);
    $('idOk').disabled = true;
    f.requestSubmit();
  }, { signal });

  /* ── History (consultations, missed appointments, ordonnances) ── */
  function entrees() {
    const v = VIS.filter(x => x.st !== 'avenir').map(x => ({ id: x.id, d: x.d, h: x.h, k: x.k, t: x.t, s: x.syn ? (x.syn.Diagnostic || x.syn.Conclusion || x.syn.Motif) : x.note || (x.lignes ? x.lignes.map(l => l[0]).join(', ') : ''), v: x.v, vis: x }));
    return [...v, ...S.extra].sort((a, b) => (b.d + (b.h || '')).localeCompare(a.d + (a.h || '')));
  }
  function rendreChrono() {
    const E = entrees(), Lv = E.filter(x => !ST.f || (ST.f === 'absent' ? x.vis && x.vis.st === 'absent' : x.k === ST.f));
    $('nChrono').textContent = E.length || '';
    const types = Object.keys(KPLUR).filter(k => E.some(x => x.k === k)), abs = E.filter(x => x.vis && x.vis.st === 'absent').length;
    const fil = $('fil');
    fil.style.display = types.length > 1 || abs ? '' : 'none';
    const prem = E.at(-1);
    $('chronoInfo').textContent = prem ? `Suivi${A.sexe === 'F' ? 'e' : ''} depuis ${MOIS[+prem.d.slice(5, 7) - 1]} ${prem.d.slice(0, 4)}` : '';
    fil.innerHTML = `<button type="button" class="chip${!ST.f ? ' on' : ''}" data-f="">Tout</button>${types.map(k => `<button type="button" class="chip${ST.f === k ? ' on' : ''}" data-f="${k}">${KPLUR[k]}</button>`).join('')}${abs ? `<button type="button" class="chip${ST.f === 'absent' ? ' on' : ''}" data-f="absent">Non venu${abs > 1 ? 's' : ''} · ${abs}</button>` : ''}`;
    const rows = $('rows');
    const premier = !E.length ? `<div class="vide-g" style="min-height:0;padding:22px 20px">${icT('cons')}${LECTURE ? `<b>Aucune visite au dossier</b><span>Aucun passage de ${esc(A.nom)} n'a été enregistré à la clinique.</span>` : `<b>Aucune visite pour l'instant</b><span>Le premier passage de ${esc(A.nom)} apparaîtra ici avec son compte rendu, ses mesures et sa facture.</span>`}</div>` : '';
    rows.innerHTML = Lv.length
      ? Lv.map(x => {
        const vi = x.vis, nd = vi ? (vi.docs || []).length + (vi.ordos || []).length : 0;
        let mo;
        if (vi && vi.fac) mo = `<b>${fmtE(totalV(vi))}</b><small>${vi.fac.st === 'du' ? '<span style="color:#b91c1c;font-weight:600">impayée</span>' : vi.fac.st === 'cours' ? '<span style="color:#b45309">à encaisser</span>' : nd ? `${nd} document${nd > 1 ? 's' : ''}` : ''}</small>`;
        else mo = `<small>${nd ? `${nd} document${nd > 1 ? 's' : ''}` : ''}</small>`;
        return `<div class="row" data-id="${x.id}" tabindex="0" role="button" aria-expanded="false"><time><b>${dCourt(x.d)}</b>${x.d.slice(0, 4)}</time>${icT(x.k)}<div class="rc"><b>${esc(x.t)}${vi ? statutV(vi) : ''}</b><span>${esc(x.s || '—')} · ${esc(x.v)}</span></div><div class="mo">${mo}</div><span class="chv">${IC.chev}</span></div>`;
      }).join('')
      : (premier || '<p class="vide" style="padding:12px 0">Rien de ce type au dossier.</p>');
    setTimeout(() => L.rouvrir(rows, depAnimal));
  }

  function htmlDeplie(v) {
    if (v.st === 'absent') return `<p>${esc(v.note || 'Non venu.')}</p><div class="dep-act"><span class="sp"></span>${LECTURE ? '' : `<a class="btn primaire" href="${esc(v.appt.book)}">Reprogrammer</a>`}</div>`;
    const syn = v.syn ? ORDRE_SYN.filter(k => v.syn[k]).slice(0, 3) : [];
    const colSyn = `<div><h4>Synthèse</h4>${syn.length ? `<dl>${syn.map(k => `<dt>${k}</dt><dd>${esc(v.syn[k])}</dd>`).join('')}</dl>` : `<p>${esc(v.note || '—')}</p>`}${v.mes && v.mes.length ? `<div class="mesc">${v.mes.map(([l, x]) => `<span>${esc(l)} <b>${esc(x)}</b></span>`).join('')}</div>` : ''}</div>`;
    const docs = [
      ...(v.docs || []).map(([t, k, href]) => `<a class="dchip" href="${esc(href)}" target="_blank" rel="noopener">${IC[k] || IC.doc}<span>${esc(t)}</span></a>`),
      ...(v.ordos || []).map(id => `<button type="button" class="dchip" data-ap-ordo="${id}">${IC.ordo}<span>Ordonnance ${esc(S.ordos[id].num)}</span></button>`),
    ];
    const colDoc = docs.length ? `<div><h4>Documents</h4><div class="dchips">${docs.join('')}</div></div>` : '';
    const f = v.fac, tot = totalV(v), lg = v.lignes || [];
    const colFac = f ? `<div><h4>Facture${f.st !== 'cours' ? ` · ${esc(f.num)}` : ''}</h4><ul class="dlig">${lg.slice(0, 4).map(([l, q, g]) => `<li><span>${q > 1 ? fmtQ(q) + ' × ' : ''}${esc(l)}</span><b>${fmtE(g)}</b></li>`).join('')}${lg.length > 4 ? `<li><span>+ ${lg.length - 4} autre${lg.length > 5 ? 's' : ''}</span></li>` : ''}</ul><div class="dtot"><span>Total TTC</span><span>${fmtE(tot)}</span></div>
      <div class="dfac">${f.st === 'ok' ? `<span class="etat ok"><i></i>Réglée · ${esc(f.p)}</span>` : f.st === 'du' ? `<span class="etat ko"><i></i>Impayée · ${esc(f.p)}</span><div class="bts"><button type="button" class="btn mini" disabled data-tip="Relances de paiement : bientôt disponibles">Relancer</button><button type="button" class="btn mini primaire" data-enc="${v.id}">Encaisser</button></div>` : '<span class="etat bt"><i></i>À encaisser en fin de consultation</span>'}</div></div>` : '';
    const cols = [colSyn, colDoc, colFac].filter(Boolean);
    let act;
    if (v.st === 'encours') act = `<span class="sp"></span><a class="btn primaire" href="${esc(v.href)}">Reprendre la consultation</a>`;
    else act = `${f && f.st !== 'cours' ? `<a class="lien" href="${esc(f.href)}" style="font-size:.76rem">Corriger la facture dans Facturation ${IC.ext}</a>` : ''}<span class="sp"></span>${v.report ? `<a class="btn" href="${esc(v.report)}" target="_blank" rel="noopener">Imprimer le compte rendu</a>` : ''}<a class="btn primaire" href="${esc(v.href)}">Ouvrir la consultation</a>`;
    return `<div class="dep-g c${cols.length}">${cols.join('')}</div><div class="dep-act">${act}</div>`;
  }
  const tabOrdo = o => `<div class="dlist">${o.lignes.map(([m, p, q]) => `<div class="dl-it" style="cursor:default"><span><b>${esc(m)}</b><small>${esc(p)}</small></span><small style="color:var(--encre-60)">${esc(q)}</small></div>`).join('') || '<p class="vide">Aucune ligne.</p>'}</div>`;
  const footOrdo = o => `<a class="btn" href="${esc(o.href)}">Ouvrir dans Ordonnances</a><a class="btn" href="${esc(o.pdf)}" target="_blank" rel="noopener">Imprimer</a>`;
  const renouv = (o, id) => (o.renew ? `<button type="button" class="btn primaire" data-renew="${id}">Renouveler</button>` : '<button type="button" class="btn primaire" disabled data-tip="Aucun renouvellement autorisé sur cette ordonnance">Renouveler</button>');
  function depAnimal(id) {
    const v = VIS.find(x => x.id === id);
    if (v) return htmlDeplie(v);
    const x = S.extra.find(y => y.id === id);
    if (!x) return '';
    const o = S.ordos[x.ref];
    return `<div class="dep-g c1"><div><h4>Prescription</h4>${tabOrdo(o)}</div></div><div class="dep-act"><span class="sp"></span>${footOrdo(o)}${renouv(o, x.ref)}</div>`;
  }
  $('fil').addEventListener('click', e => { const b = e.target.closest('[data-f]'); if (b) { ST.f = b.dataset.f; rendreChrono(); } }, { signal });
  $('rows').addEventListener('click', e => {
    if (e.target.closest('.dep')) return;
    const r = e.target.closest('.row');
    if (r) L.basculer(r, depAnimal(r.dataset.id));
  }, { signal });
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape' || root.querySelector('.mveil.open') || Object.values(CARTES).some(c => c.edit)) return;
    const r = root.querySelector('.row.deplie');
    if (r) { L.fermer(); L.replier(r.parentElement); r.focus(); }
  }, { signal });

  /* ── Prevention ── */
  const AJOUTES = new Set(S.plan ? S.prev.map((p, i) => (S.plan.done.includes(p.l.toLowerCase()) ? i : -1)).filter(i => i >= 0) : []);
  function etatPrev(p) {
    if (p.a) return ['bt', 'À programmer', p.a];
    if (!p.proch) return ['na', 'Sans rappel', 'Aucune échéance enregistrée'];
    const j = jours(p.proch);
    if (j < 0) return ['ko', `En retard de ${-j} j`, `Prévu le ${fmtD(p.proch)}`];
    if (j <= 30) return ['bt', `Dans ${j} j`, `Le ${fmtD(p.proch)}`];
    return ['ok', 'À jour', `Prochain le ${fmtD(p.proch)}`];
  }
  function rendrePrev() {
    // A closed file has nothing left to schedule: the count no longer raises an alert.
    const dus = LECTURE ? 0 : S.prev.filter(p => ['ko', 'bt'].includes(etatPrev(p)[0])).length;
    const n = $('nPrev');
    n.textContent = dus || S.prev.length || '';
    n.classList.toggle('al', !!dus);
    const cible = enCours() ? { st: 'encours' } : prochain();
    const bouton = (c, i) => {
      if (!['ko', 'bt'].includes(c) || !cible || LECTURE) return '';
      if (cible.st === 'encours') return AJOUTES.has(i) ? '<span class="btn mini done">✓ Ajouté à la consultation</span>' : `<button type="button" class="btn mini" data-aj="${i}">Ajouter à la consultation</button>`;
      return `<button type="button" class="btn mini" disabled data-tip="Ajout au rendez-vous : bientôt disponible — notez-le dans le motif depuis l'agenda">Ajouter au RDV du ${dCourt(cible.d)}</button>`;
    };
    $('prev').innerHTML = S.prev.length
      ? `<table class="pv"><thead><tr><th>Protection</th><th>Dernière fois</th><th>Statut</th><th></th></tr></thead><tbody>${S.prev.map((p, i) => {
        const [c, l, s] = etatPrev(p);
        return `<tr><td><b>${esc(p.l)}</b>${p.p ? `<small>${esc(p.p)}</small>` : ''}</td><td>${p.der ? fmtD(p.der) : '—'}</td><td><span class="etat ${c}"><i></i>${l}</span><small>${esc(s)}</small></td><td class="act">${bouton(c, i)}</td></tr>`;
      }).join('')}</tbody></table>`
      : '<div class="vide-g"><b>Aucune protection enregistrée</b></div>';
  }
  $('prev').addEventListener('click', async e => {
    const b = e.target.closest('[data-aj]');
    if (!b || !S.plan) return;
    const i = +b.dataset.aj, p = S.prev[i];
    b.disabled = true;
    const body = new FormData();
    Object.entries({ _token: S.plan.token, kind: 'PERFORMED_ACT', description: p.l, quantity: '1' }).forEach(([k, v]) => body.append(k, v));
    try {
      const res = await fetch(S.plan.url, { method: 'POST', body, headers: { Accept: 'application/json' } });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.errors?.global?.[0] || 'Ajout refusé.');
      AJOUTES.add(i);
      rendrePrev();
      showToast(`${p.l} ajouté au plan de la consultation en cours`);
    } catch (err) {
      b.disabled = false;
      showToast(err.message || 'Ajout refusé.', 'danger');
    }
  }, { signal });

  /* ── Curves ── */
  function courbe(Sr, o) {
    if (!Sr || !Sr.length) return '';
    const W = 320, H = 130, P = { l: 30, r: 10, t: 10, b: 22 };
    const D = s => new Date(s + 'T12:00:00');
    const xs = Sr.map(p => D(p[0]).getTime()), vs = Sr.map(p => p[1]);
    const lo = Math.min(...vs, o.ref ? o.ref[0] : Infinity), hi = Math.max(...vs, o.ref ? o.ref[1] : -Infinity), pad = (hi - lo) * 0.15 || 1;
    const y0 = lo - pad, y1 = hi + pad, x0 = Math.min(...xs), x1 = Math.max(...xs);
    const X = t => P.l + (x1 === x0 ? (W - P.l - P.r) / 2 : (t - x0) / (x1 - x0) * (W - P.l - P.r)), Y = v => P.t + (1 - (v - y0) / (y1 - y0)) * (H - P.t - P.b);
    const pts = Sr.map(p => [X(D(p[0]).getTime()), Y(p[1])]);
    const line = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
    const ticks = [y0 + pad, (y0 + y1) / 2, y1 - pad];
    const der = Sr.at(-1), prec = Sr.at(-2), diff = prec ? der[1] - prec[1] : 0, dd = o.d ?? 1;
    const hors = o.ref && (der[1] > o.ref[1] || der[1] < o.ref[0]);
    return `<section class="card chart"><div class="cb"><h4>${o.t} <b style="color:${hors ? '#b91c1c' : 'var(--encre)'}">${fmtN(der[1], dd)}</b><span style="font-size:.74rem;color:var(--encre-60)">${o.u}</span>${prec ? `<span class="tr" style="color:${Math.abs(diff) < 1e-9 ? 'var(--encre-60)' : (diff > 0) === !!o.hautMauvais ? '#b91c1c' : '#15803d'}">${diff > 0 ? '▲' : diff < 0 ? '▼' : '='} ${fmtN(Math.abs(diff), dd)}</span>` : ''}<small>${dAn(der[0])}</small></h4>
      <svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" role="img" aria-label="${o.t}">
        ${o.ref ? `<rect x="${P.l}" y="${Y(o.ref[1])}" width="${W - P.l - P.r}" height="${Math.max(0, Y(o.ref[0]) - Y(o.ref[1]))}" fill="#16a34a" opacity=".08"/>` : ''}
        ${ticks.map(t => `<line x1="${P.l}" x2="${W - P.r}" y1="${Y(t)}" y2="${Y(t)}" stroke="#eef2f6"/><text x="${P.l - 6}" y="${Y(t) + 3}" text-anchor="end" font-size="9" fill="#94a3b8">${fmtN(t, dd)}</text>`).join('')}
        <path d="${line}" fill="none" stroke="${o.c}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
        ${pts.map((p, i) => `<circle cx="${p[0]}" cy="${p[1]}" r="3.5" fill="#fff" stroke="${o.c}" stroke-width="2"><title>${fmtD(Sr[i][0])} · ${fmtN(Sr[i][1], dd)} ${o.u}</title></circle>`).join('')}
        <text x="${P.l}" y="${H - 6}" font-size="9" fill="#94a3b8">${Sr[0][0].slice(0, 4)}</text><text x="${W - P.r}" y="${H - 6}" font-size="9" fill="#94a3b8" text-anchor="end">${der[0].slice(0, 4)}</text>
      </svg><p class="cap">${o.ref ? `Bande verte : valeurs de référence (${fmtN(o.ref[0], dd)} – ${fmtN(o.ref[1], dd)} ${o.u})` : `${Sr.length} mesure${Sr.length > 1 ? 's' : ''}`}</p></div></section>`;
  }
  function rendreCourbes() {
    const s = S.series;
    const h = courbe(s.poids, { t: 'Poids', u: 'kg', c: '#4338ca' })
      + (s.temp.length > 1 ? courbe(s.temp, { t: 'Température', u: '°C', c: '#c2410c', ref: [38, 39.2], hautMauvais: true }) : '')
      + courbe(s.gly, { t: 'Glycémie', u: 'mmol/L', c: '#7c3aed', hautMauvais: true });
    $('charts').innerHTML = h || `<div class="vide-g" style="grid-column:1/-1">${icT('labo')}<b>Pas encore de mesure</b><span>Le poids et les mesures relevés en consultation dessineront les courbes.</span></div>`;
  }

  /* ── Documents ── */
  function rendreDocs() {
    $('nDocs').textContent = S.docs.length || '';
    $('docs').innerHTML = S.docs.map(d => {
      const inner = `<span class="th" style="background:${d.c};color:${d.fg}">${IC[d.k] || IC.doc}</span><b>${esc(d.t)}</b><small>${fmtD(d.d)}</small>`;
      return d.href ? `<a class="doc" href="${esc(d.href)}" target="_blank" rel="noopener">${inner}</a>` : `<button type="button" class="doc" data-ap-ordo="${d.ordo}">${inner}</button>`;
    }).join('')
      + (LECTURE ? '' : `<div class="drop" id="drop">Glissez des fichiers ici (PDF, images, résultats, carnet de l'éleveur…) ou <button type="button" class="lien" disabled data-tip="Ajout de documents au dossier : bientôt disponible" style="opacity:.6;cursor:not-allowed">parcourez</button></div>`);
    const dz = $('drop');
    if (!dz) return;
    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('over'); }, { signal }));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('over'); }, { signal }));
    dz.addEventListener('drop', () => showToast('Ajout de documents au dossier : bientôt disponible'), { signal });
  }

  /* ── Shared windows: prescription preview, cashing, appointment cancel ── */
  function apercuOrdo(id) {
    const o = S.ordos[id];
    $('apT').textContent = `Ordonnance ${o.num}`;
    $('apS').textContent = `${dLong(o.d)} · ${o.v}`;
    $('apB').innerHTML = tabOrdo(o);
    $('apF').innerHTML = `${footOrdo(o)}<span class="sp"></span>${renouv(o, id)}`;
    ouvrirModale('mApercu');
  }
  function encaisser(v) {
    $('encInv').value = v.fac.invoiceId;
    $('encAmt').value = v.fac.balanceInput;
    $('encS').textContent = `${v.fac.num} · ${A.nom} · ${v.t}`;
    $('encM').textContent = fmtE(v.fac.balance);
    $('encMeth').value = 'CARD';
    root.querySelectorAll('#encMode button').forEach((b, i) => b.classList.toggle('on', !i));
    ouvrirModale('mEnc');
  }
  $('encMode').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b) return;
    root.querySelectorAll('#encMode button').forEach(x => x.classList.toggle('on', x === b));
    $('encMeth').value = b.dataset.v;
  }, { signal });
  $('encOk').addEventListener('click', () => { $('encOk').disabled = true; $('encForm').requestSubmit(); }, { signal });

  root.addEventListener('click', e => {
    const t = e.target;
    const o = t.closest('[data-ap-ordo]'); if (o) { apercuOrdo(o.dataset.apOrdo); return; }
    const rn = t.closest('[data-renew]');
    if (rn) { const r = S.ordos[rn.dataset.renew].renew; $('renewForm').action = r.url; $('renewTok').value = r.token; $('renewForm').requestSubmit(); return; }
    const en = t.closest('[data-enc]'); if (en) { encaisser(VIS.find(x => x.id === en.dataset.enc)); return; }
    const an = t.closest('[data-annul]'); if (an) annulerRdv(an.dataset.annul, an);
  }, { signal });

  async function annulerRdv(id, btn) {
    const v = VIS.find(x => x.id === id);
    btn.disabled = true;
    const body = new FormData();
    body.append('_token', root.dataset.apptToken);
    try {
      const res = await fetch(v.appt.cancel, { method: 'POST', body, headers: { Accept: 'application/json' } });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.errors?.global?.[0] || 'Annulation refusée.');
      showToast(`RDV du ${dCourt(v.d)} annulé · ${A.nom}`);
      VIS.splice(VIS.indexOf(v), 1);
      CARTES.cSyn.rendre();
      rendrePrev();
    } catch (err) {
      btn.disabled = false;
      showToast(err.message || 'Annulation refusée.', 'danger');
    }
  }

  /* ── Weighing, preventive act, owner change and death windows ── */
  function ouvrirForm(id, form, focus) {
    form.reset();
    // reset() restores the hidden inputs: the controls follow them.
    form.querySelectorAll('.seg[data-seg]').forEach(seg => {
      const v = form.querySelector(`input[name="${seg.dataset.seg}"]`)?.value ?? '';
      seg.querySelectorAll('button').forEach(b => b.classList.toggle('on', b.dataset.v === v));
    });
    form.querySelectorAll('.ko').forEach(n => n.classList.remove('ko'));
    form.querySelectorAll('.ferr').forEach(n => { n.textContent = ''; });
    ouvrirModale(id);
    if (focus) setTimeout(() => $(focus).focus(), 60);
  }
  const envoyer = (btn, form, check) => $(btn).addEventListener('click', () => {
    if (check && !check()) return;
    $(btn).disabled = true;
    form.requestSubmit();
  }, { signal });

  const wForm = $('wForm'), pvForm = $('pvForm'), prForm = $('prForm'), dcdForm = $('dcdForm');
  masquesLive(wForm, null, signal);
  masquesLive(pvForm, null, signal);
  envoyer('wOk', wForm, () => {
    if (!lireChamps(wForm)) { wForm.querySelector('.ko')?.focus(); return false; }
    return true;
  });
  envoyer('pvOk', pvForm, () => {
    if (!lireChamps(pvForm)) { pvForm.querySelector('.ko')?.focus(); return false; }
    const n = $('f_pvn'), d = $('f_pvd');
    if (n.value && n.value <= d.value) { n.classList.add('ko'); n.parentElement.querySelector('.ferr').textContent = 'Après la date d\'administration.'; return false; }
    return true;
  });
  envoyer('prOk', prForm, () => {
    if (!$('prOwner').value) { $('prSearch').classList.add('ko'); $('prErr').textContent = 'Choisissez le nouveau propriétaire dans la liste.'; $('prSearch').focus(); return false; }
    return true;
  });
  $('prSearch').addEventListener('input', () => { $('prOwner').value = ''; $('prErr').textContent = ''; $('prSearch').classList.remove('ko'); }, { signal });
  envoyer('dcdOk', dcdForm, () => !!$('dcdDate').value && $('dcdDate').value <= TODAY_ISO);

  /* ── « ⋯ » menu ── */
  const ferme = A.dcd ? 'Dossier clos : l\'animal est décédé' : 'Dossier clos : la fiche est archivée';
  menu($('btnMenu'), [
    { l: 'Imprimer le carnet de santé', s: 'Vaccins, traitements et identification', disabled: true, tip: 'Carnet de santé imprimable : bientôt disponible', f: () => {} },
    { l: 'Certificat de bonne santé', s: 'Pré-rempli, à signer', disabled: true, tip: 'Certificats : bientôt disponibles', f: () => {} },
    { l: 'Changer de propriétaire', s: 'Cession ou don, avec mise à jour de l\'identification', disabled: LECTURE, tip: ferme, f: () => ouvrirForm('mProprio', prForm, 'prSearch') },
    '-',
    { l: 'Nouvelle pesée', s: 'Poids et note d\'état corporel, hors consultation', disabled: LECTURE, tip: ferme, f: () => ouvrirForm('mPoids', wForm, 'f_wkg') },
    { l: 'Vaccin ou antiparasitaire', s: 'Reporté d\'un carnet ou fait hors consultation', disabled: LECTURE, tip: ferme, f: () => ouvrirForm('mPrev', pvForm, 'f_pvl') },
    '-',
    {
      l: 'Déclarer le décès', danger: true,
      f: () => {
        if (A.dcd) { showToast('Le décès est déjà enregistré'); return; }
        if (A.archived) { showToast(ferme); return; }
        $('dcdSub').textContent = `${A.nom} · ${A.esp.toLowerCase()}${A.naiss ? ` de ${age(A.naiss)}` : ''}`;
        ouvrirForm('mDcd', dcdForm);
      },
    },
    ...(A.archived ? [] : [{ l: 'Archiver la fiche', s: 'Masquée des recherches, rien n\'est supprimé', danger: true, f: () => $('archForm').requestSubmit() }]),
  ], root, signal);

  /* ── First render ── */
  const place = onglets($('tabs'), t => {
    root.querySelectorAll('.tabp').forEach(p => p.classList.toggle('on', p.id === 'p-' + t));
    rendreChrono();
    const url = new URL(window.location.href);
    if (t === 'chrono') url.searchParams.delete('tab'); else url.searchParams.set('tab', t);
    window.history.replaceState(window.history.state, '', url);
  }, signal);
  rendreTete();
  rendreChrono();
  rendrePrev();
  rendreCourbes();
  rendreDocs();
  requestAnimationFrame(fonduSyn);

  const tab = root.dataset.tab;
  if (tab && tab !== 'chrono') {
    root.querySelectorAll('#tabs button[data-t]').forEach(b => b.classList.toggle('on', b.dataset.t === tab));
    root.querySelectorAll('.tabp').forEach(p => p.classList.toggle('on', p.id === 'p-' + tab));
    requestAnimationFrame(place);
  }

  // Back from a form: the card it came from flashes green, then the URL forgets it.
  const saved = root.dataset.saved;
  if (saved) {
    flashSaved(saved === 'head' ? root.querySelector('.pagehead') : $(saved)?.querySelector(':scope > .card') || $(saved));
    const url = new URL(window.location.href);
    url.searchParams.delete('saved');
    window.history.replaceState(window.history.state, '', url);
  }
}

export function cleanup() {
  ac?.abort();
  ac = null;
  document.querySelectorAll('.dossier .mveil.open').forEach(m => m.classList.remove('open'));
}
