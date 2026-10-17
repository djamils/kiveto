/**
 * Page module — client sheet (client-v-topbar_5 mock-up and its -busy /
 * -vierge variants). Loaded by the app.js dispatcher on turbo:load.
 *
 * The mock-up renders the sheet from its demo data; this module renders it
 * the same way from the `data-sheet` payload of ClientSheetBuilder: identity
 * header, animal cards (scrolling past four), the history with its filters
 * and expandable rows, appointments to come, account, and the three
 * read-then-edit cards of the rail. Every change goes through a real form
 * posted to its single-action controller; the controller comes back with
 * ?saved=<card> and the card flashes green, as after a save in the mock-up.
 *
 * Listeners are bound in init() with one AbortSignal and released in cleanup().
 */
import { showToast, creerDropdown } from 'kiveto/shell';
import {
  esc, dates, MOIS, JC, fmtE, fmtQ, IC, icT, MASK, chiffres, champ, lireChamps, masquesLive,
  carte, marquer, flashSaved, menu, ouvrirModale, fermerModales, bindModales, lignes, fonduRail,
} from 'pages/dossier/kit';
import { mount as monterFisc, toPayload as fiscPayload, PRO } from 'kiveto/client-tax-profile';

let ac = null;

export function init() {
  const root = document.querySelector('.dossier[data-sheet]');
  if (!root) return;

  ac?.abort();
  ac = new AbortController();
  const { signal } = ac;

  const S = JSON.parse(root.dataset.sheet);
  const C = S.client;
  const ARCH = C.archived;
  const { TODAY_ISO, D, dCourt, dLong, jours } = dates(S.today);
  const $ = id => document.getElementById(id);
  const VIS = S.visits;
  const PETS = Object.fromEntries(S.animals.map(a => [a.id, a]));
  const ST = { f: '' };
  const CARTES = {};
  const L = lignes();

  const nomC = () => `${C.civ ? C.civ + ' ' : ''}${C.prenom} ${C.nom}`;
  const totalV = v => v.total || 0;
  const pet = v => PETS[v.a];
  const petTxt = v => (pet(v) ? `${pet(v).e} ${esc(pet(v).nom)} · ` : '');
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
    $('phAv').textContent = (C.prenom[0] + C.nom[0]).toUpperCase();
    $('phNom').innerHTML = `${C.rs ? `<span class="ph-rs">${esc(C.rs)}</span>` : ''}${esc(nomC())}${ARCH ? '' : `<button type="button" class="ph-edit" id="btnCo" title="Modifier les coordonnées" aria-label="Modifier les coordonnées">${IC.crayon}</button>`}${C.isNew ? ' <span class="vig b">Nouveau client</span>' : ''}`;
    const adr = C.adr1 ? `${esc(C.adr1)}${C.adr2 ? ', ' + esc(C.adr2) : ''}, ${esc(C.cp)} ${esc(C.ville)}` : '<span style="color:var(--encre-35)">Pas d\'adresse</span>';
    const tels = [C.mob, C.fixe].filter(Boolean).map(t => `<a href="tel:${chiffres(t)}">${esc(t)}</a>`).join(' · ') || '<span style="color:var(--encre-35)">Pas de téléphone</span>';
    $('phFacts').innerHTML = `<span>${IC.pin}${adr}</span><span>${IC.tel}${tels}</span><span>${IC.mail}${C.mail ? `<a href="mailto:${esc(C.mail)}">${esc(C.mail)}</a>` : '<span style="color:var(--encre-35)">Pas d\'e-mail</span>'}</span><span>${C.createdToday ? 'Créé aujourd\'hui' : `Client depuis ${MOIS[+C.depuis.slice(5, 7) - 1]} ${C.depuis.slice(0, 4)}`}</span>`;
  }
  $('phNom').addEventListener('click', e => { if (e.target.closest('#btnCo')) ouvrirCoord(); }, { signal });

  /* ── Animals ── */
  const anisEl = $('anis');
  function navAnis() {
    const g = root.querySelector('.anis-nav.g'), d = root.querySelector('.anis-nav.d'), max = anisEl.scrollWidth - anisEl.clientWidth;
    g.hidden = anisEl.scrollLeft < 4;
    d.hidden = max < 4 || anisEl.scrollLeft > max - 4;
  }
  anisEl.addEventListener('scroll', navAnis, { signal });
  window.addEventListener('resize', navAnis, { signal });
  root.querySelectorAll('.anis-nav').forEach(b => b.addEventListener('click', () => anisEl.scrollBy({ left: (b.classList.contains('d') ? 1 : -1) * (anisEl.querySelector('.ani').offsetWidth + 12), behavior: 'smooth' }), { signal }));
  $('aniAddL')?.addEventListener('click', ouvrirAnimal, { signal });
  anisEl.addEventListener('click', e => { if (e.target.closest('.ani.add')) { e.preventDefault(); ouvrirAnimal(); } }, { signal });

  function rendreAnis() {
    const plein = S.animals.length >= 4, defile = S.animals.length > 4;
    anisEl.classList.toggle('defile', defile);
    $('anisH').hidden = !plein;
    $('anisN').textContent = S.animals.length;
    const add = ARCH ? '' : `<button type="button" class="ani add">${IC.plus.replace('<svg', '<svg width="18" height="18"')}Ajouter un animal</button>`;
    anisEl.innerHTML = S.animals.map(a => {
      const du = VIS.filter(v => v.a === a.id && v.fac && v.fac.st === 'du').reduce((t, v) => t + v.fac.balance, 0);
      const tg = [...a.tags, ...(du ? [['vig r', `Impayé ${fmtE(du)}`]] : [])];
      return `<a class="ani${a.dcd ? ' dcd' : ''}" href="${esc(a.href)}"><div class="hd"><span class="e">${a.e}</span><div style="min-width:0;flex:1"><b>${esc(a.nom)}</b><small>${esc(a.d)}</small></div></div>${tg.length ? `<div class="tags">${tg.map(([c, l]) => `<span class="${c}">${esc(l)}</span>`).join('')}</div>` : ''}${a.kv.length ? `<div class="kv">${a.kv.map(([k, v, r]) => `<div><span>${k}</span><b${r ? ' style="color:#b91c1c"' : ''}>${esc(v)}</b></div>`).join('')}</div>` : ''}</a>`;
    }).join('') + (plein ? '' : add);
  }

  /* ── History rows ── */
  const ORDRE_SYN = ['Diagnostic', 'Conclusion', 'Actes', 'Examen', 'Imagerie', 'Motif'];
  const sousTitre = v => {
    if (v.k === 'comm') return `${esc(v.sens)} · ${esc(v.canal)} · ${esc(v.v)}`;
    if (v.k === 'vente') return esc((v.lignes || []).map(l => l[0]).slice(0, 2).join(', '));
    return `${esc(v.v || '—')}${v.syn && v.syn.Diagnostic ? ' · ' + esc(v.syn.Diagnostic) : v.note ? ' · ' + esc(v.note) : ''}`;
  };
  const ligne = v => {
    const nd = (v.docs || []).length + (v.ordos || []).length, tot = totalV(v);
    let mo;
    if (v.st === 'avenir') mo = statutV(v);
    else if (v.st === 'absent') mo = '<span class="etat ko"><i></i>Non venu</span>';
    else if (v.k === 'comm') mo = `<span class="etat na"><i></i>${esc(v.canal)}</span>`;
    else if (!v.fac) mo = nd ? `<small>${nd} doc${nd > 1 ? 's' : ''}</small>` : '';
    else mo = `<b>${fmtE(tot)}</b><small>${v.fac.st === 'cours' ? '<span style="color:#b45309">à encaisser</span>' : v.fac.st === 'du' ? '<span style="color:#b91c1c;font-weight:600">impayée</span>' : `réglée${nd ? ` · ${nd} doc${nd > 1 ? 's' : ''}` : ''}`}</small>`;
    return `<div class="row${v.st === 'avenir' ? ' next' : ''}" data-id="${v.id}" tabindex="0" role="button" aria-expanded="false"><time><b>${dCourt(v.d)}${v.d.slice(0, 4) !== TODAY_ISO.slice(0, 4) ? ' ' + v.d.slice(0, 4) : ''}</b>${v.h}</time>${icT(v.k)}<div class="rc"><b>${petTxt(v)}${esc(v.t)}${v.st === 'encours' ? '<span class="encours">en cours</span>' : ''}</b><span>${sousTitre(v)}</span></div>
      <div class="mo">${mo}</div><span class="chv">${IC.chev}</span></div>`;
  };

  function htmlDeplie(v) {
    if (v.st === 'absent') return `<p>${esc(v.note || 'Non venu.')}</p><div class="dep-act"><span class="sp"></span>${ARCH ? '' : `<a class="btn primaire" href="${esc(v.appt.book)}">Reprogrammer</a>`}</div>`;
    if (v.k === 'comm') return `<p style="white-space:pre-wrap">${esc(v.note || 'Pas de détail consigné.')}</p><div class="dep-act"><small>Consigné par ${esc(v.v)} · ${esc(v.sens.toLowerCase())} · ${esc(v.canal)}</small></div>`;
    const syn = v.syn ? ORDRE_SYN.filter(k => v.syn[k]).slice(0, 3) : [];
    const colSyn = `<div><h4>${v.k === 'vente' ? 'Vente' : 'Synthèse'}</h4>${syn.length ? `<dl>${syn.map(k => `<dt>${k}</dt><dd>${esc(v.syn[k])}</dd>`).join('')}</dl>` : `<p>${v.k === 'vente' ? 'Vente au comptoir' : esc(v.note || '—')}</p>`}${v.mes && v.mes.length ? `<div class="mesc">${v.mes.map(([l, x]) => `<span>${esc(l)} <b>${esc(x)}</b></span>`).join('')}</div>` : ''}</div>`;
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
    else if (v.k === 'vente') act = `<span class="sp"></span><a class="btn" href="${esc(f.href)}">Corriger dans Facturation ${IC.ext}</a>`;
    else act = `${f && f.st !== 'cours' ? `<a class="lien" href="${esc(f.href)}" style="font-size:.76rem">Corriger la facture dans Facturation ${IC.ext}</a>` : ''}<span class="sp"></span>${v.report ? `<a class="btn" href="${esc(v.report)}" target="_blank" rel="noopener">Imprimer le compte rendu</a>` : ''}<a class="btn primaire" href="${esc(v.href)}">Ouvrir la consultation</a>`;
    return `<div class="dep-g c${cols.length}">${cols.join('')}</div><div class="dep-act">${act}</div>`;
  }

  function htmlRdv(v) {
    return `<div class="dep-rdv">${esc(v.note || 'Pas de consigne particulière.')}<div class="bts">${v.appt.cancel ? `<button type="button" class="btn mini" data-annul="${v.id}" style="color:#b91c1c">Annuler</button>` : ''}<a class="btn mini primaire" href="${esc(v.appt.move)}">Déplacer</a></div></div>`;
  }

  function rendreVisites() {
    const av = VIS.filter(v => v.st === 'avenir').sort((a, b) => (a.d + a.h).localeCompare(b.d + b.h));
    const avV = ST.tousRdv || av.length <= 3 ? av : av.slice(0, 2);
    $('rdvs').innerHTML = av.length
      ? avV.map(v => { const j = jours(v.d); return `<button type="button" class="prdv" data-id="${v.id}" aria-expanded="false"><span class="dt"><b>${+v.d.slice(8)}</b><small>${MOIS[+v.d.slice(5, 7) - 1].replace('.', '')}</small></span><div><b>${petTxt(v)}${esc(v.t)}</b><small>${j <= 0 ? 'Aujourd\'hui' : j === 1 ? 'Demain' : JC[D(v.d).getDay()]} à ${v.h} · ${esc(v.v)}</small></div></button>`; }).join('')
        + (av.length > 3 ? `<button type="button" class="lien" id="rdvPlus" style="margin-top:6px;font-size:.76rem">${ST.tousRdv ? 'Afficher moins' : `+ ${av.length - 2} autres rendez-vous`}</button>` : '')
      : '<p class="vide">Aucun rendez-vous prévu.</p>';
    const H = VIS.filter(v => v.st !== 'avenir'), abs = H.filter(v => v.st === 'absent').length;
    const animaux = [...new Set(H.map(v => v.a).filter(a => a && PETS[a]))];
    const fil = $('fil');
    fil.style.display = animaux.length > 1 || abs ? '' : 'none';
    fil.innerHTML = `<button type="button" class="chip${!ST.f ? ' on' : ''}" data-f="">Tous</button>${animaux.map(a => `<button type="button" class="chip${ST.f === a ? ' on' : ''}" data-f="${a}">${PETS[a].e} ${esc(PETS[a].nom)}</button>`).join('')}${abs ? `<button type="button" class="chip${ST.f === 'absent' ? ' on' : ''}" data-f="absent">Non venus · ${abs}</button>` : ''}`;
    const Lv = H.filter(v => !ST.f || (ST.f === 'absent' ? v.st === 'absent' : v.a === ST.f)).sort((a, b) => (b.d + b.h).localeCompare(a.d + a.h));
    setTimeout(() => {
      L.rouvrir($('hist'), id => { const v = VIS.find(x => x.id === id); return v && v.st !== 'avenir' ? htmlDeplie(v) : ''; });
      L.rouvrir($('rdvs'), id => { const v = VIS.find(x => x.id === id); return v && v.st === 'avenir' ? htmlRdv(v) : ''; });
    });
    $('hist').innerHTML = Lv.length ? Lv.map(ligne).join('') : `<div class="vide-g">${icT('cons')}<b>Pas encore d'historique</b><span>Chaque passage s'ajoutera ici : compte rendu, documents et facture, consultables d'un clic.</span></div>`;
  }

  $('rdvs').addEventListener('click', e => {
    if (e.target.closest('#rdvPlus')) { ST.tousRdv = !ST.tousRdv; rendreVisites(); return; }
    if (e.target.closest('.dep')) return;
    const r = e.target.closest('.prdv');
    if (r) L.basculer(r, htmlRdv(VIS.find(v => v.id === r.dataset.id)));
  }, { signal });
  $('fil').addEventListener('click', e => { const b = e.target.closest('[data-f]'); if (b) { ST.f = b.dataset.f; rendreVisites(); } }, { signal });
  $('hist').addEventListener('click', e => {
    if (e.target.closest('.dep')) return;
    const r = e.target.closest('.row');
    if (r) L.basculer(r, htmlDeplie(VIS.find(v => v.id === r.dataset.id)));
  }, { signal });
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape' || root.querySelector('.mveil.open') || Object.values(CARTES).some(c => c.edit)) return;
    const r = root.querySelector('.row.deplie,.prdv.deplie');
    if (r) { L.fermer(); L.replier(r.parentElement); r.focus(); }
  }, { signal });

  /* ── Account ── */
  function rendreCompte() {
    const A = S.account;
    $('imps').innerHTML = A.unpaid.map(u => `<div class="imp"><b>${esc(u.label)}</b><b class="n">${fmtE(u.amount)}</b><small>${dCourt(u.d)} · ${esc(u.detail)}</small><div class="bts"><button type="button" class="btn mini" disabled data-tip="Relances de paiement : bientôt disponibles">Relancer</button><button type="button" class="btn mini primaire" data-enc-inv="${u.invoiceId}">Encaisser</button></div></div>`).join('');
    $('kpis').innerHTML = `<span${A.due ? ' class="ko"' : ''}>Reste dû${A.dueCount ? ` · ${A.dueCount} fact.` : ''}<b>${fmtE(A.due)}</b></span><span>En cours<b class="${A.current ? 'am' : ''}">${fmtE(A.current)}</b></span><span>Sur 12 mois<b>${fmtE(A.year)}</b></span>`;
  }

  /* ── Shared windows: prescription preview, cashing ── */
  function apercu(t, sub, body, foot) { $('apT').textContent = t; $('apS').textContent = sub; $('apB').innerHTML = body; $('apF').innerHTML = foot; ouvrirModale('mApercu'); }
  function apercuOrdo(id) {
    const o = S.ordos[id];
    apercu(`Ordonnance ${o.num}`, `${dLong(o.d)} · ${o.v}`,
      `<div class="dlist">${o.lignes.map(([m, p, q]) => `<div class="dl-it" style="cursor:default"><span><b>${esc(m)}</b><small>${esc(p)}</small></span><small style="color:var(--encre-60)">${esc(q)}</small></div>`).join('') || '<p class="vide">Aucune ligne.</p>'}</div>`,
      `<a class="btn" href="${esc(o.href)}">Ouvrir dans Ordonnances</a><a class="btn" href="${esc(o.pdf)}" target="_blank" rel="noopener">Imprimer</a><span class="sp"></span>${o.renew ? `<button type="button" class="btn primaire" data-renew="${id}">Renouveler</button>` : '<button type="button" class="btn primaire" disabled data-tip="Aucun renouvellement autorisé sur cette ordonnance">Renouveler</button>'}`);
  }

  function encaisser(invoiceId, sub, amount, balanceInput) {
    $('encInv').value = invoiceId;
    $('encAmt').value = balanceInput;
    $('encS').textContent = sub;
    $('encM').textContent = fmtE(amount);
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
    const en = t.closest('[data-enc]');
    if (en) { const v = VIS.find(x => x.id === en.dataset.enc); encaisser(v.fac.invoiceId, `${v.fac.num}${pet(v) ? ' · ' + pet(v).nom : ''} · ${v.t}`, v.fac.balance, v.fac.balanceInput); return; }
    const ei = t.closest('[data-enc-inv]');
    if (ei) { const u = S.account.unpaid.find(x => x.invoiceId === ei.dataset.encInv); encaisser(u.invoiceId, u.label, u.amount, u.balanceInput); return; }
    const an = t.closest('[data-annul]'); if (an) { annulerRdv(an.dataset.annul, an); return; }
    if (t.closest('[data-unarchive]')) $('archForm').requestSubmit();
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
      showToast(`RDV du ${dCourt(v.d)} annulé${pet(v) ? ' · ' + pet(v).nom : ''}`);
      const i = VIS.indexOf(v);
      VIS.splice(i, 1);
      L.fermer();
      rafraichir();
    } catch (err) {
      btn.disabled = false;
      showToast(err.message || 'Annulation refusée.', 'danger');
    }
  }

  /* ── Contact details window ── */
  let CO = null;
  const coForm = $('coForm');
  function ouvrirCoord() {
    CO = { civ: C.civ };
    $('coSub').textContent = nomC();
    $('coMeta').textContent = C.metaCo ? `Modifié le ${C.metaCo}` : '';
    coForm.querySelectorAll(':scope > :not(input[type="hidden"])').forEach(n => n.remove());
    coForm.insertAdjacentHTML('beforeend', `<div><label class="flbl">Civilité</label><div class="seg" id="sgCiv" style="width:160px"><button type="button" data-v="M." class="${CO.civ === 'M.' ? 'on' : ''}">M.</button><button type="button" data-v="Mme" class="${CO.civ === 'Mme' ? 'on' : ''}">Mme</button></div></div>
      <div class="f2">${champ('prenom', C.prenom, { l: 'Prénom', req: true, m: 'nom', name: 'first_name' })}${champ('nom', C.nom, { l: 'Nom', req: true, m: 'nom', name: 'last_name' })}</div>
      ${champ('adr1', C.adr1, { l: 'Adresse', req: true, max: 80, ph: 'N° et voie', name: 'street' })}${champ('adr2', C.adr2, { max: 80, ph: 'Complément · facultatif', name: 'street2' })}
      <div class="f2 cp">${champ('cp', C.cp, { l: 'Code postal', m: 'cp', req: true, name: 'postal_code' })}${champ('ville', C.ville, { l: 'Ville', req: true, max: 80, name: 'city' })}</div>
      <div class="f2">${champ('mob', C.mob, { l: 'Mobile', m: 'tel', req: true, name: 'mobile' })}${champ('fixe', C.fixe, { l: 'Fixe', m: 'tel', opt: true, name: 'landline' })}</div>
      ${champ('mail', C.mail, { l: 'E-mail', m: 'mail', opt: true, name: 'email' })}`);
    ouvrirModale('mCoord');
    setTimeout(() => $('f_prenom').focus(), 60);
  }
  masquesLive(coForm, () => $('mCoord').setAttribute('data-dirty', '1'), signal);
  coForm.addEventListener('click', e => {
    const b = e.target.closest('#sgCiv button');
    if (!b || b.dataset.v === CO.civ) return;
    CO.civ = b.dataset.v;
    coForm.querySelectorAll('#sgCiv button').forEach(x => x.classList.toggle('on', x === b));
    $('mCoord').setAttribute('data-dirty', '1');
  }, { signal });
  $('coOk').addEventListener('click', () => {
    const V = lireChamps(coForm);
    if (!V) { coForm.querySelector('.ko')?.focus(); return; }
    const N = { ...V, civ: CO.civ };
    const avant = { prenom: C.prenom, nom: C.nom, adr1: C.adr1, adr2: C.adr2, cp: C.cp, ville: C.ville, mob: C.mob, fixe: C.fixe, mail: C.mail, civ: C.civ };
    if (!Object.keys(avant).some(k => (N[k] ?? '') !== (avant[k] ?? ''))) { fermerModales(); return; }
    $('coCiv').value = CO.civ === 'M.' ? 'mister' : CO.civ === 'Mme' ? 'madam' : '';
    $('coOk').disabled = true;
    coForm.requestSubmit();
  }, { signal });

  /* ── New animal and logged exchange windows ── */
  const aniForm = $('aniForm');
  function ouvrirAnimal() {
    if (ARCH) return;
    aniForm.reset();
    // reset() restores the hidden inputs: the controls follow them.
    aniForm.querySelectorAll('.seg[data-seg]').forEach(seg => {
      const v = aniForm.querySelector(`input[name="${seg.dataset.seg}"]`).value;
      seg.querySelectorAll('button').forEach(b => b.classList.toggle('on', b.dataset.v === v));
    });
    aniForm.querySelectorAll('[data-sw]').forEach(s => { s.classList.remove('on'); s.setAttribute('aria-checked', 'false'); });
    aniForm.querySelectorAll('.ko').forEach(n => n.classList.remove('ko'));
    aniForm.querySelectorAll('.ferr').forEach(n => { n.textContent = ''; });
    ouvrirModale('mAni');
    setTimeout(() => $('f_aname').focus(), 60);
  }
  masquesLive(aniForm, null, signal);
  aniForm.addEventListener('click', e => {
    const s = e.target.closest('[data-sw]');
    if (!s) return;
    const on = !s.classList.contains('on');
    s.classList.toggle('on', on);
    s.setAttribute('aria-checked', String(on));
    aniForm.querySelector(`input[name="${s.dataset.sw}"]`).value = on ? '1' : '0';
  }, { signal });
  $('aniOk').addEventListener('click', () => {
    if (!lireChamps(aniForm)) { aniForm.querySelector('.ko')?.focus(); return; }
    $('aniOk').disabled = true;
    aniForm.requestSubmit();
  }, { signal });

  const msgForm = $('msgForm');
  masquesLive(msgForm, null, signal);
  $('msgOk').addEventListener('click', () => {
    if (!lireChamps(msgForm)) { msgForm.querySelector('.ko')?.focus(); return; }
    $('msgOk').disabled = true;
    msgForm.requestSubmit();
  }, { signal });

  /* ── Rail cards ── */
  const PREFS = [['sms', 'Rappels de rendez-vous par SMS', 'La veille à 18:00.'], ['mailFac', 'Factures et ordonnances par e-mail', 'Envoyées à la clôture de chaque consultation.'], ['relance', 'Relances de paiement', 'Par SMS et e-mail, avec lien de paiement.']];
  let PF = null;
  carte($('cPref'), {
    titre: 'Préférences', lecture: ARCH,
    avant() { PF = Object.fromEntries(PREFS.map(([k]) => [k, S.prefs[k]])); },
    lire() { return PREFS.map(([k, l]) => `<div class="syn-it"><i style="background:${S.prefs[k] ? '#16a34a' : '#cbd5e1'}"></i><div><b style="font-weight:${S.prefs[k] ? 600 : 500};color:${S.prefs[k] ? 'var(--encre)' : 'var(--encre-35)'}">${l}</b></div><small style="color:${S.prefs[k] ? '#15803d' : 'var(--encre-35)'};font-weight:600;font-size:.74rem">${S.prefs[k] ? 'Oui' : 'Non'}</small></div>`).join(''); },
    editer() { return PREFS.map(([k, l, s]) => `<div class="swrow"><span class="sw${PF[k] ? ' on' : ''}" data-pk="${k}" role="switch" aria-checked="${PF[k]}" tabindex="0"></span><div><b>${l}</b><small>${s}</small></div></div>`).join(''); },
    valider() {
      if (!PREFS.some(([k]) => PF[k] !== S.prefs[k])) return { change: false };
      return { change: true, appliquer: () => envoyerPrefs(PF) };
    },
  }, CARTES, signal);
  $('cPref').addEventListener('click', e => {
    const s = e.target.closest('[data-pk]');
    if (!s) return;
    const k = s.dataset.pk;
    PF[k] = !PF[k];
    s.classList.toggle('on', PF[k]);
    s.setAttribute('aria-checked', PF[k]);
    marquer(CARTES.cPref);
  }, { signal });

  /** The three switches rewrite their channel of the consents on file; the others are posted unchanged. */
  function envoyerPrefs(pf) {
    const cons = Object.fromEntries(Object.entries(S.prefs.consents).map(([k, v]) => [k, [...v]]));
    const bascule = (kind, channel, on) => {
      const l = (cons[kind] || []).filter(c => c !== channel);
      cons[kind] = on ? [...l, channel] : l;
    };
    bascule('appointment_reminders', 'sms', pf.sms);
    bascule('clinical_documents', 'email', pf.mailFac);
    cons.billing = pf.relance ? (cons.billing && cons.billing.length ? cons.billing : ['sms', 'email']) : [];
    const form = $('prefForm');
    form.querySelectorAll('input[data-consent]').forEach(n => n.remove());
    Object.entries(cons).forEach(([kind, channels]) => channels.forEach(ch => {
      const i = document.createElement('input');
      Object.assign(i, { type: 'hidden', name: `consent[${kind}][]`, value: ch });
      i.dataset.consent = '1';
      form.appendChild(i);
    }));
    form.requestSubmit();
  }

  let PS = null, NL = '', PCIV = 'Mme';
  const iniP = n => n.replace(/^(M\.|Mme) /, '').split(' ').map(x => x[0]).join('').slice(0, 2);
  const LIENS = S.choices.relationships;
  carte($('cPers'), {
    titre: 'Personnes autorisées', meta: S.pers.meta, lecture: ARCH,
    avant() { PS = S.pers.list.map(p => ({ ...p })); NL = ''; PCIV = 'Mme'; },
    lire() { return S.pers.list.length ? S.pers.list.map(p => `<div class="pers"><span class="av">${esc(p.ini || iniP(p.n))}</span><div><b>${esc(p.n)}</b><small>${esc(p.l)}${p.tel ? ` · <a href="tel:${chiffres(p.tel)}" style="color:var(--pin);font-weight:600;text-decoration:none">${esc(p.tel)}</a>` : ''}</small></div></div>`).join('') : '<p class="vide">Personne d\'autre que le titulaire ne peut déposer ou récupérer un animal.</p>'; },
    editer() {
      const actifs = PS.filter(p => !p.rm).length;
      return PS.map((p, i) => `<div class="pers${p.rm ? ' retire' : ''}"><span class="av">${esc(p.ini || iniP(p.n))}</span><div><b>${esc(p.n)}</b><small>${esc(p.l)}${p.tel ? ' · ' + esc(p.tel) : ''}</small></div><button type="button" class="x" data-prm="${i}" aria-label="${p.rm ? 'Rétablir' : 'Retirer'}">${p.rm ? '↺' : IC.x.replace('<svg', '<svg width="12" height="12"')}</button></div>`).join('')
        + (actifs ? '<p class="vide" style="padding-top:8px">Une seule personne autorisée par dossier pour l\'instant : retirez-la pour en désigner une autre.</p>' : `<div class="padd"><label class="flbl">Ajouter une personne</label>
          <div class="f3"><div class="seg" id="sgPCiv"><button type="button" data-v="M." class="${PCIV === 'M.' ? 'on' : ''}">M.</button><button type="button" data-v="Mme" class="${PCIV === 'Mme' ? 'on' : ''}">Mme</button></div>${champ('pp', '', { m: 'nom', ph: 'Prénom', libre: true })}${champ('pn', '', { m: 'nom', ph: 'Nom', libre: true })}</div>
          <div class="f2"><div><div class="dd" id="dd_lien"></div><small class="ferr" id="err_lien"></small></div>${champ('pt', '', { m: 'tel', ph: 'Téléphone', libre: true })}</div>
          <button type="button" class="btn mini" id="persAdd" style="align-self:flex-end">Ajouter à la liste</button></div>`);
    },
    monte() {
      const dd = $('dd_lien');
      if (dd) creerDropdown(dd, LIENS.map(l => ({ v: l.v, l: l.l })), NL, v => { NL = v; dd.classList.remove('ko'); $('err_lien').textContent = ''; }, 'Lien');
    },
    valider() {
      const a = PS.filter(p => p.nv && !p.rm), r = PS.filter(p => p.rm && !p.nv);
      if (!a.length && !r.length) return { change: false };
      const garde = PS.find(p => !p.rm) || null;
      return { change: true, appliquer: () => envoyerPers(garde) };
    },
  }, CARTES, signal);
  $('cPers').addEventListener('click', e => {
    const b = e.target.closest('[data-prm]');
    if (b) { const i = +b.dataset.prm, p = PS[i]; if (p.nv) PS.splice(i, 1); else p.rm = !p.rm; marquer(CARTES.cPers); CARTES.cPers.rendre(); return; }
    const cv = e.target.closest('#sgPCiv button');
    if (cv) { PCIV = cv.dataset.v; root.querySelectorAll('#sgPCiv button').forEach(x => x.classList.toggle('on', x === cv)); return; }
    if (e.target.closest('#persAdd')) {
      const pr = MASK.nom.f($('f_pp').value), no = MASK.nom.f($('f_pn').value), t = $('f_pt').value;
      let ok = true;
      [[$('f_pp'), pr.length >= 2, 'Prénom requis.'], [$('f_pn'), no.length >= 2, 'Nom requis.'], [$('f_pt'), /^0[1-9]\d{8}$/.test(chiffres(t)), '10 chiffres, commençant par 0.']].forEach(([el, v, m]) => { el.classList.toggle('ko', !v); el.parentElement.querySelector('.ferr').textContent = v ? '' : m; if (!v) ok = false; });
      if (!NL) { $('err_lien').textContent = 'Choisissez le lien.'; $('dd_lien').classList.add('ko'); ok = false; }
      if (!ok) return;
      PS.push({ n: `${PCIV} ${pr} ${no}`, ini: (pr[0] + no[0]).toUpperCase(), l: LIENS.find(x => x.v === NL).l, tel: MASK.tel.f(t), civility: PCIV === 'M.' ? 'mister' : 'madam', prenom: pr, nomP: no, lien: NL, nv: true });
      NL = '';
      marquer(CARTES.cPers);
      CARTES.cPers.rendre();
    }
  }, { signal });

  function envoyerPers(p) {
    const f = $('persForm'), set = (n, v) => { f.querySelector(`[name="${n}"]`).value = v ?? ''; };
    set('ap_civility', p?.civility);
    set('ap_first_name', p?.prenom);
    set('ap_last_name', p?.nomP);
    set('ap_relationship', p?.lien);
    set('ap_phone', p?.tel);
    f.requestSubmit();
  }

  /* The tax profile: the shared block (kiveto/client-tax-profile) in edit mode. */
  const F = S.fisc;
  let FS = null, FUI = null;
  carte($('cFisc'), {
    titre: 'Profil fiscal', lecture: ARCH,
    avant() { FS = { fisc: F.fisc, rs: F.rs, siren: F.siren, tva: F.tva }; },
    lire() {
      const ligne = t => `<small style="display:block;color:var(--encre-60);font-size:.74rem;margin-top:2px">${t}</small>`;
      const ids = F.fisc === PRO ? ligne(`SIREN ${esc(F.sirenAff)} · ${F.tvaAff ? 'TVA ' + esc(F.tvaAff) : 'Pas de n° de TVA'}`) : '';
      return `<div class="syn-it"><i style="background:${F.fisc === PRO ? 'var(--pin)' : '#cbd5e1'}"></i><div><b>${esc(F.rs || F.label)}</b>${F.rs ? ligne(esc(F.label)) : ''}${ids}</div></div>`;
    },
    editer() { return '<div id="fiscBloc"></div>'; },
    monte() { FUI = monterFisc($('fiscBloc'), FS, () => marquer(CARTES.cFisc), { sirenUrl: F.dblUrl, self: C.id, onCheck: () => {} }); },
    valider() {
      if (!FUI.showErrors()) return null;
      const p = fiscPayload(FS), avant = fiscPayload(F);
      if (Object.keys(p).every(k => p[k] === avant[k])) return { change: false };
      return { change: true, appliquer: () => {
        const f = $('fiscForm');
        Object.entries(p).forEach(([k, v]) => { f.querySelector(`[name="${k}"]`).value = v; });
        f.requestSubmit();
      } };
    },
  }, CARTES, signal);

  carte($('cNote'), {
    titre: 'Note interne', meta: S.note.meta, lecture: ARCH,
    lire() { const n = S.note.text; return n ? `<p class="note${ST.noteOuverte ? '' : ' clamp'}">${esc(n)}</p>${n.split('\n').length > 3 || n.length > 180 ? `<button type="button" class="lien" data-notetog style="font-size:.76rem;margin-top:4px">${ST.noteOuverte ? 'Réduire' : 'Lire la suite'}</button>` : ''}` : '<p class="vide">Aucune note.</p>'; },
    editer() { return `<textarea class="finput" id="noteIn" rows="4" maxlength="2000" style="width:100%;resize:vertical;font-size:.84rem;line-height:1.5" placeholder="Visible par l'équipe uniquement">${esc(S.note.text)}</textarea><small style="display:block;text-align:right;font-size:.7rem;color:var(--encre-35);margin-top:-6px" id="noteN">${S.note.text.length} / 2000</small>`; },
    valider() {
      const el = $('noteIn'), v = el.value.replace(/[ \t]+/g, ' ').replace(/\n{3,}/g, '\n\n').trim();
      if (v === S.note.text) return { change: false };
      if (!v) { el.classList.add('ko'); $('noteN').textContent = 'La note ne peut pas être vide.'; return null; }
      return { change: true, appliquer: () => { const f = $('noteForm'); f.querySelector('[name="content"]').value = v; f.requestSubmit(); } };
    },
  }, CARTES, signal);
  $('cNote').addEventListener('click', e => { if (e.target.closest('[data-notetog]')) { ST.noteOuverte = !ST.noteOuverte; CARTES.cNote.rendre(); } }, { signal });
  $('cNote').addEventListener('input', e => { if (e.target.id === 'noteIn') $('noteN').textContent = `${e.target.value.length} / 2000`; }, { signal });

  /* ── « ⋯ » menu ── */
  menu($('btnMenu'), [
    { l: 'Consigner un échange', s: 'Appel, SMS, e-mail ou courrier · ajouté à l\'historique', f: () => { ouvrirModale('mMsg'); setTimeout(() => $('f_msub').focus(), 60); } },
    '-',
    { l: 'Fusionner avec un doublon', s: 'Regroupe animaux, factures et historique', disabled: true, tip: 'Fusion des fiches : bientôt disponible', f: () => {} },
    { l: 'Exporter ses données', s: 'Droit d\'accès (RGPD) · archive PDF et CSV', disabled: true, tip: 'Export des données : bientôt disponible', f: () => {} },
    '-',
    ARCH
      ? { l: 'Désarchiver le client', s: 'La fiche redevient visible et modifiable', f: () => $('archForm').requestSubmit() }
      : { l: 'Archiver le client', s: 'Masqué du répertoire, rien n\'est supprimé', danger: true, f: () => $('archForm').requestSubmit() },
  ], root, signal);

  /* ── First render ── */
  function rafraichir() { rendreAnis(); rendreVisites(); rendreCompte(); requestAnimationFrame(navAnis); }
  const fondu = fonduRail(root.querySelector('.col.rail'), signal);
  rendreTete();
  rafraichir();
  requestAnimationFrame(() => { navAnis(); fondu(); });

  // Back from a form: the card it came from flashes green, then the URL forgets it.
  const saved = root.dataset.saved;
  if (saved) {
    flashSaved(saved === 'head' ? root.querySelector('.pagehead') : $(saved));
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
