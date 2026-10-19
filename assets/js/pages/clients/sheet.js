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
import { mount as monterFisc, toPayload as fiscPayload, vatKey, PRO } from 'kiveto/client-tax-profile';

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

  /*
   * The tax profile (client-v-topbar-busy_6). The card renders every state
   * of the mock-up from what the server sends (ClientSheetBuilder::
   * taxProfileView): what it does not know yet — establishments, e-invoicing
   * directory, public bodies, foreign clients, who changed it — comes empty
   * and those parts show as coming soon. In edit mode the company is picked
   * in the registry search ({available, reason, items}); « Saisir à la main »
   * (always offered, opened by itself when the registry is down) falls back
   * to the shared block (kiveto/client-tax-profile). The verification (badges,
   * last check, « Revérifier ») is advisory: it never blocks a save.
   */
  const F = S.fisc;
  const FZT = {
    part: ['Particulier', 'Une personne, sans activité professionnelle'],
    ent: ['Entreprise ou association', 'Élevage, centre équestre, refuge, toiletteur…'],
    pub: ['Organisme public', 'Mairie, fourrière, gendarmerie, État', 'Les organismes publics (Chorus Pro) ne sont pas encore pris en charge'],
    etr: ['Client établi à l\'étranger', 'Professionnel hors de France', 'Les clients établis à l\'étranger ne sont pas encore pris en charge'],
  };
  const FZI = {
    ok: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
    i: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    f: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>',
  };
  const CHEV = '<svg class="chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
  /* Country, label, VAT prefix (none outside the EU). */
  const PAYS = [['BE', 'Belgique', 'BE'], ['CH', 'Suisse', ''], ['DE', 'Allemagne', 'DE'], ['ES', 'Espagne', 'ES'], ['IT', 'Italie', 'IT'], ['LU', 'Luxembourg', 'LU'], ['MC', 'Monaco', 'FR'], ['GB', 'Royaume-Uni', '']];
  const norm = t => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const tvaFR = s => 'FR' + vatKey(s) + s;
  const fmtS = d => String(d).replace(/(\d{3})(?=\d)/g, '$1 ');
  const fmtSiret = d => fmtS(d.slice(0, 9)) + ' ' + d.slice(9);
  const fmtTva = t => t.replace(/^FR(\d\d)/, 'FR $1 ');
  const aeDefaut = (e, siret) => (!e.ann ? '' : (e.ann.adrs.find(a => a.id.endsWith('_' + siret)) || e.ann.adrs[0]).id);
  const aeDe = (e, id) => e.ann && e.ann.adrs.find(a => a.id === id);
  const fmtAe = id => id.replace(/^(\d{3})(\d{3})(\d{3})/, '$1 $2 $3').replace('_', ' · ');
  const vies = v => (v === true ? '<span class="ok">✓ VIES</span>' : v === false ? '<span class="ko">✗ VIES</span>' : '');
  /* Words of the last-check line, after « Vérifié le 12/11 à 08:12 ». */
  const FZCO = { active: 'actif', ceased: 'radiée', confidential: 'non diffusible', not_found: 'SIREN introuvable', unavailable: 'registre indisponible', unchecked: 'registre non consulté' };
  const FZVO = { valid: 'TVA valide', invalid: 'TVA non reconnue', not_applicable: 'sans n° de TVA', unavailable: 'VIES indisponible', unchecked: 'TVA non vérifiée' };
  /* Badges, official name, last check and « Revérifier » of a professional. */
  function verifFisc() {
    const v = F.verification;
    if (!v) return '';
    const ton = c => (c === 'company_confidential' ? 'neutre' : c === 'unverified' ? 'attente' : 'alerte');
    const badges = v.warnings.filter(w => !(v.pending && w.code === 'unverified'))
      .map(w => `<span class="fz-badge ${ton(w.code)}">${esc(w.label)}</span>`).join('');
    const nom = v.registryName && v.warnings.some(w => w.code === 'name_mismatch')
      ? `<div class="fz-st warn">${FZI.i}<div>Nom officiel : <b>${esc(v.registryName)}</b></div>${ARCH ? '' : '<button type="button" class="act" data-fznom>Utiliser le nom officiel</button>'}</div>` : '';
    const ligne = v.pending ? 'Vérification en cours' : F.verif ? `Vérifié le ${esc(F.verif)} · ${FZCO[v.company] ?? ''} · ${FZVO[v.vat] ?? ''}` : 'Pas encore vérifié';
    return `${badges ? `<div class="fz-badges">${badges}</div>` : ''}${nom}<div class="fz-verif"><span>${ligne}</span>${ARCH || v.pending ? '' : '<button type="button" class="lien" data-fzrev>Revérifier</button>'}</div>`;
  }
  /* A dropdown that lists what the directory returns, greyed out until it is wired. */
  const ddBientot = (label, txt, tip, aide = '') => `<div><label class="flbl">${label}</label><div class="dd off" data-tip="${esc(tip)}"><button type="button" class="dd-btn" aria-disabled="true" tabindex="-1"><span>${txt}</span>${CHEV}</button></div>${aide ? `<small class="fz-aide">${aide}</small>` : ''}</div>`;
  /* What the status means for the invoice: the sentence read first. */
  function circuit(f) {
    const live = F.efacture;
    if (f.t === 'part') return live ? 'Factures classiques (PDF) · ventes déclarées en e-reporting, automatiquement' : 'Factures classiques (PDF) · e-reporting des ventes bientôt disponible';
    if (f.t === 'etr') return live ? 'Factures classiques (PDF) · opérations déclarées en e-reporting international' : 'Factures classiques (PDF) · e-reporting international bientôt disponible';
    if (f.t === 'pub') return 'Factures électroniques vers Chorus Pro, avec code service et n° d\'engagement';
    if (!f.ass) return live ? 'Non assujettie à la TVA : factures classiques · e-reporting' : 'Non assujettie à la TVA : factures classiques (PDF)';
    return live ? 'Factures électroniques via plateforme agréée' : 'Factures classiques (PDF) pour l\'instant · la facture électronique via plateforme agréée arrive bientôt';
  }
  const radiee = (e, suite) => `<div class="fz-st ko">${FZI.i}<div><b>${e.cesse === true ? 'Entreprise radiée selon le registre' : `Entreprise radiée le ${e.cesse.split('-').reverse().join('/')}`}</b>${suite}</div></div>`;
  let FS = null, FUI = null, fzSeq = 0, fzTimer = 0, fzAbort = null;

  function lireFisc() {
    const f = F, phrase = circuit(f);
    if (f.t === 'part') return `<div class="fz-h"><b>${esc(nomC())}</b><span class="fz-tag">Particulier</span></div><div class="fz-st">${FZI.f}<div>${phrase}.</div></div>`;
    if (f.t === 'etr') {
      const p = PAYS.find(x => x[0] === f.pays);
      return `<div class="fz-h"><b>${esc(f.rs)}</b><span class="fz-tag etr">${esc(p ? p[1] : f.pays)}</span></div><dl class="fz-kv"><dt>TVA</dt><dd>${esc(f.tva || '—')}${f.tva ? vies(f.vies) : ''}</dd></dl><div class="fz-st">${FZI.f}<div>${phrase}.</div></div>`;
    }
    const e = f.ent;
    const tag = f.t === 'pub' ? '<span class="fz-tag pub">Organisme public</span>'
      : !f.ass ? `<span class="fz-tag">${e ? esc(e.forme) + ' · non assujettie' : 'Non assujettie'}</span>` : '<span class="fz-tag pro">Assujettie à la TVA</span>';
    if (!e) {
      // No directory record yet: what is stored.
      if (!f.ass) return `<div class="fz-h"><b>${esc(f.rs)}</b>${tag}</div><div class="fz-st">${FZI.f}<div>${phrase}.</div></div>`;
      return `<div class="fz-h"><b>${esc(f.rs)}</b>${tag}</div>
        <dl class="fz-kv">${f.siret ? `<dt>SIRET</dt><dd>${fmtSiret(f.siret)}</dd>` : `<dt>SIREN</dt><dd>${esc(f.sirenAff)}</dd>`}<dt>TVA</dt><dd>${f.tvaAff ? `${esc(f.tvaAff)}${vies(f.vies)}` : '<span class="nv seul">Pas de n° de TVA</span>'}</dd></dl>
        ${verifFisc()}
        <div class="fz-st">${FZI.i}<div><b>Annuaire de la facturation électronique</b> · pas encore consulté : ses factures restent classiques (PDF) en attendant.</div></div>`;
    }
    const et = e.ets.find(x => x.siret === f.siret) || e.ets[0];
    let st;
    if (e.cesse) st = radiee(e, ' · vérifiez avec le client avant toute facture.');
    else if (f.t === 'pub') st = `<div class="fz-st info">${FZI.ok}<div><b>Chorus Pro</b> · service ${esc(f.svc)}. Le n° d'engagement est demandé à chaque facture.</div></div>`;
    else if (!f.ass) st = `<div class="fz-st">${FZI.f}<div>${phrase}.</div></div>`;
    else if (e.ann) { const ae = aeDe(e, f.ae) || e.ann.adrs[0]; st = `<div class="fz-st ok">${FZI.ok}<div><b>Adresse électronique ${esc(fmtAe(ae.id))}</b> · ${esc(ae.l.toLowerCase())}, reçue via ${esc(ae.pa)}. Vérifié dans l'annuaire ${esc(f.verif || 'aujourd\'hui')}.</div></div>`; }
    else st = `<div class="fz-st warn">${FZI.i}<div><b>Pas encore inscrite à l'annuaire</b> · ses factures restent en attente de dépôt jusqu'à son inscription. Kiveto revérifie chaque nuit.</div></div>`;
    const tva = f.tva || tvaFR(e.siren);
    return `<div class="fz-h"><b>${esc(e.n)}</b>${tag}</div>
      <dl class="fz-kv"><dt>SIRET</dt><dd>${fmtSiret(et.siret)} · ${esc(et.l)}</dd>${f.t === 'ent' && f.ass ? `<dt>TVA</dt><dd>${fmtTva(tva)}${vies(f.vies)}</dd>` : ''}${f.t === 'pub' ? `<dt>Service</dt><dd>${esc(f.svc)}</dd>` : ''}</dl>
      <div style="margin-top:9px">${st}</div>
      ${f.t === 'pub' ? '<div class="fz-lines"><div class="fz-line"><span>Référence</span><b>N° d\'engagement exigé sur chaque facture</b></div></div>' : ''}`;
  }

  function editFisc() {
    const f = FS;
    let h = `<div class="fz-opts" role="radiogroup" aria-label="Statut fiscal">${Object.entries(FZT).map(([k, [l, sub, tip]]) => {
      const off = !F.statuts.includes(k);
      return `<button type="button" class="fz-opt${f.t === k ? ' on' : ''}${off ? ' off' : ''}" data-ft="${k}" role="radio" aria-checked="${f.t === k}"${off ? ` aria-disabled="true" data-tip="${esc(tip ?? 'Pas encore pris en charge')}"` : ''}><span class="rd"></span><span><b>${l}</b><small>${sub}</small></span></button>`;
    }).join('')}</div>`;
    if (f.t === 'ent' || f.t === 'pub') {
      const e = f.e;
      if (!e) {
        const quoi = f.t === 'pub' ? 'Organisme' : 'Entreprise ou association';
        const manuel = !F.annuaireUrl || f.manual;
        if (!manuel) {
          h += `<div><label class="flbl" for="fzQ">${quoi} <span class="req">*</span></label><div class="fz-res"><input class="finput" id="fzQ" role="combobox" aria-expanded="false" aria-controls="fzS" aria-autocomplete="list" placeholder="Raison sociale, SIREN ou SIRET du siège…" autocomplete="off" value="${esc(f.q || '')}"><div class="fz-sug" id="fzS" role="listbox" aria-label="Entreprises de l'annuaire"></div></div><small class="fz-aide">Recherche dans l'annuaire officiel des entreprises : nom, adresse et n° de TVA sont repris tels quels.</small><button type="button" class="lien fz-man" data-fzman>Saisir à la main</button></div>`;
        } else if (F.annuaireUrl) {
          h += `<div class="fz-st${f.down ? ' warn' : ''}">${FZI.i}<div>${f.down ? '<b>Recherche indisponible, saisie manuelle.</b> Les informations seront vérifiées dès que l\'annuaire répondra.' : 'Saisie manuelle : les informations seront vérifiées dans l\'annuaire.'}</div><button type="button" class="act" data-fzrech>Rechercher dans l'annuaire</button></div>`;
        }
        if (f.t === 'ent' && manuel) {
          h += `<div id="fiscBloc"></div>
            ${ddBientot('Établissement facturé', 'Choisi dans l\'annuaire', 'Le choix de l\'établissement (SIRET) arrive avec l\'annuaire officiel')}
            <div class="fz-sw"><span class="sw${f.ass ? ' on' : ''}" data-fass role="switch" aria-checked="${f.ass}" tabindex="0"></span><div><b>Assujettie à la TVA</b><small>Une association sans activité lucrative ne l'est généralement pas.</small></div></div>`;
          if (f.ass) h += ddBientot('Adresse de facturation électronique', 'Lue dans l\'annuaire', 'L\'adresse de facturation électronique arrive avec la facture électronique', 'Adresses déclarées par le client dans l\'annuaire : c\'est par elle que sa plateforme reçoit la facture.');
        }
      } else {
        const dbl = F.clientsSiret[f.siret];
        if (f.dbl) h += `<div class="fz-st warn">${FZI.i}<div>Un client porte déjà ce SIREN : <b>${esc(f.dbl.rs || f.dbl.n)}</b></div><a class="act" href="${esc(f.dbl.url)}">Ouvrir sa fiche</a></div>`;
        h += `<div class="fz-ent"><div><b>${esc(e.n)}</b><small>${esc(e.forme)} · SIREN ${fmtS(e.siren)} · ${esc(e.naf || '')}</small></div><button type="button" class="x" data-fzx>Changer</button></div>`;
        if (e.cesse) h += radiee(e, ' selon l\'annuaire officiel. Vérifiez auprès du client.');
        if (e.ets.length > 1) h += '<div><label class="flbl">Établissement facturé <span class="req">*</span></label><div class="dd" id="ddEt"></div></div>';
        if (dbl) h += `<div class="fz-st warn">${FZI.i}<div><b>Cet établissement est déjà client :</b> ${esc(dbl.n)}.</div><a class="act" href="${esc(dbl.url)}">Ouvrir sa fiche</a></div>`;
        else {
          const autre = e.ets.find(x => F.clientsSiret[x.siret]);
          if (autre) h += `<div class="fz-st">${FZI.i}<div>Même entreprise que <b>${esc(F.clientsSiret[autre.siret].n)}</b> : les deux fiches seront regroupées pour le suivi des impayés.</div></div>`;
        }
        if (f.t === 'ent') h += `<div class="fz-sw"><span class="sw${f.ass ? ' on' : ''}" data-fass role="switch" aria-checked="${f.ass}" tabindex="0"></span><div><b>Assujettie à la TVA</b><small>${e.ass ? 'Proposé d\'après sa forme juridique.' : 'Une association sans activité lucrative ne l\'est généralement pas.'}${f.ass ? (f.tva ? ` TVA : ${fmtTva(f.tva)} — à confirmer, certaines entreprises n'ont pas de n° de TVA.` : ' Sans n° de TVA (franchise en base).') : ''}</small></div></div>`;
        if (f.t === 'pub') h += '<div><label class="flbl">Service destinataire <span class="req">*</span></label><div class="dd" id="ddSvc"></div><small class="fz-aide">Liste publiée par l\'organisme dans Chorus Pro.</small></div>';
        if (f.t === 'ent' && f.ass) {
          // `ann` absent: the e-invoicing directory was not consulted; null: consulted, not registered.
          if (e.ann === undefined) h += `<div class="fz-st">${FZI.i}<div><b>Annuaire de la facturation électronique</b> · pas encore consulté : ses factures restent classiques (PDF) en attendant.</div></div>`;
          else if (!e.ann) h += `<div class="fz-st warn">${FZI.i}<div><b>Pas encore inscrite à l'annuaire</b> · aucune adresse électronique déclarée : les factures attendront son inscription, Kiveto revérifie chaque nuit.</div></div>`;
          else if (e.ann.adrs.length > 1) h += '<div><label class="flbl">Adresse de facturation électronique <span class="req">*</span></label><div class="dd" id="ddAe"></div><small class="fz-aide">Adresses déclarées par le client dans l\'annuaire : c\'est par elle que sa plateforme reçoit la facture.</small></div>';
          else h += `<div class="fz-st ok">${FZI.ok}<div><b>Adresse électronique ${esc(fmtAe(e.ann.adrs[0].id))}</b> · seule adresse déclarée dans l'annuaire, reçue via ${esc(e.ann.adrs[0].pa)}.</div></div>`;
        }
      }
    }
    if (f.t === 'etr') {
      const p = PAYS.find(x => x[0] === f.pays);
      h += `<div class="f2"><div><label class="flbl">Pays <span class="req">*</span></label><div class="dd" id="ddPays"></div></div><div><label class="flbl">Raison sociale <span class="req">*</span></label><input class="finput" id="fzRs" maxlength="80" value="${esc(f.rs || '')}"></div></div>
        ${p && p[2] ? `<div><label class="flbl">N° de TVA intracommunautaire <span class="req">*</span></label><input class="finput" id="fzTva" style="font-family:var(--font-data)" placeholder="${p[2]} …" value="${esc(f.tva || '')}"><small class="ferr" id="fzTvaE"></small></div>` : p ? '<p class="fz-aide">Hors Union européenne : pas de n° de TVA intracommunautaire.</p>' : ''}`;
    }
    const phrase = circuit(f.t === 'ent' && !f.e && !f.rs && F.annuaireUrl ? { t: 'ent', ass: true } : f);
    h += `<div class="fz-st info">${FZI.f}<div><b>Pour la facture :</b> ${phrase.charAt(0).toLowerCase() + phrase.slice(1)}.</div></div><p class="fz-aide">Les factures déjà émises ne changent pas${F.trace ? ' ; le changement est noté dans l\'historique' : ''}.</p>`;
    return h;
  }

  function monteFisc() {
    const f = FS, e = f.e, m = () => marquer(CARTES.cFisc);
    f.fisc = (f.t === 'ent' && f.ass) ? PRO : 'ind';
    FUI = $('fiscBloc') ? monterFisc($('fiscBloc'), f, m, { sirenUrl: F.dblUrl, self: C.id, onCheck: () => {}, structureOnly: true, hideStatus: true }) : null;
    if (e && e.ets.length > 1 && $('ddEt')) creerDropdown($('ddEt'), e.ets.map(x => ({ v: x.siret, l: `${x.l} · ${x.a}` })), f.siret, v => { f.siret = v; f.ae = aeDefaut(e, v); m(); CARTES.cFisc.rendre(); });
    if ($('ddSvc')) creerDropdown($('ddSvc'), (e.services || []).map(([v, l]) => ({ v, l: `${l} · ${v}` })), f.svc || '', v => { f.svc = v; m(); }, 'Choisissez le service');
    if ($('ddAe')) creerDropdown($('ddAe'), e.ann.adrs.map(a => ({ v: a.id, l: `${fmtAe(a.id)} · ${a.l} · via ${a.pa}` })), f.ae, v => { f.ae = v; m(); });
    if ($('ddPays')) creerDropdown($('ddPays'), PAYS.map(([v, l]) => ({ v, l })), f.pays || '', v => { f.pays = v; f.tva = ''; m(); CARTES.cFisc.rendre(); }, 'Choisissez le pays');
  }

  function validerFisc() {
    const f = FS;
    const err = (id, msg) => { $(id)?.classList.add('ko'); showToast(msg); return null; };
    if (FUI && !FUI.showErrors()) return null;
    if ((f.t === 'ent' || f.t === 'pub') && F.annuaireUrl && !f.manual) {
      if (!f.e) return err('fzQ', 'Choisissez l\'entreprise dans l\'annuaire');
      if (F.clientsSiret[f.siret]) return err('ddEt', 'Cet établissement a déjà une fiche client');
      if (f.t === 'pub' && !f.svc) return err('ddSvc', 'Choisissez le service destinataire');
    }
    if (f.t === 'etr') {
      if (!f.pays) return err('ddPays', 'Choisissez le pays');
      if (!(f.rs || '').trim()) return err('fzRs', 'Raison sociale requise');
      const pre = PAYS.find(x => x[0] === f.pays)[2];
      if (pre && !new RegExp('^' + pre + '[0-9A-Z]{8,12}$').test((f.tva || '').replace(/\s/g, ''))) {
        const e2 = $('fzTvaE'); if (e2) e2.textContent = `Format attendu : ${pre} suivi de 8 à 12 caractères`;
        return err('fzTva', 'N° de TVA invalide');
      }
    }
    // The fields the server reads today, then those it will read.
    const base = f.t === 'part' ? { fisc: 'ind', rs: '', siren: '', tva: '' }
      : f.e ? { fisc: f.ass ? PRO : 'ind', rs: f.rs || f.e.n, siren: f.ass ? f.e.siren : '', tva: f.ass ? (f.tva || '') : '' }
      : fiscPayload(f);
    const p = { ...base, statut: f.t, siret: f.siret || '', ae: f.ae || '', svc: f.svc || '', pays: f.pays || '' };
    const avant = { ...fiscPayload(F), statut: F.t, siret: F.siret || '', ae: F.ae || '', svc: F.svc || '', pays: F.pays || '' };
    if (Object.keys(p).every(k => p[k] === avant[k])) return { change: false };
    return { change: true, appliquer: () => {
      const form = $('fiscForm');
      Object.entries(p).forEach(([k, v]) => { const i = form.querySelector(`[name="${k}"]`); if (i) i.value = v; });
      form.requestSubmit();
    } };
  }

  carte($('cFisc'), {
    titre: 'Profil fiscal', meta: F.meta, lecture: ARCH,
    avant() {
      // An existing professional opens on what is stored, then on its registry record if the SIREN is found.
      const pro = F.t === 'ent' && !!F.siren;
      FS = { t: F.t, ass: F.ass, fisc: F.fisc, rs: F.rs, siren: F.siren, tva: F.tva, siret: F.siret, ae: F.ae, svc: F.svc, pays: F.pays, e: F.ent, q: '', manual: pro, down: false, dbl: null, res: [], hl: -1 };
      if (pro && F.annuaireUrl) parSiren(F.siren);
    },
    lire: lireFisc, editer: editFisc, monte: monteFisc, valider: validerFisc,
  }, CARTES, signal);

  /* Asks the registry; `sur(items)` gets its hits, `ralenti()` a spent budget; an outage or a network failure opens manual entry. */
  function annuaire(q, sur, ralenti = () => {}) {
    window.clearTimeout(fzTimer);
    fzAbort?.abort();
    const ctl = fzAbort = new AbortController();
    const panne = () => { if (!FS || ctl.signal.aborted) return; Object.assign(FS, { manual: true, down: true }); CARTES.cFisc.rendre(); };
    return fetch(`${F.annuaireUrl}?${new URLSearchParams({ q })}`, { headers: { Accept: 'application/json' }, signal: ctl.signal })
      .then(r => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then(R => {
        if (!FS || ctl.signal.aborted) return;
        // Throttled: the next keystroke asks again.
        if (!R.available) { if (R.reason === 'throttled') ralenti(); else panne(); return; }
        sur(R.items);
      })
      .catch(err => { if (err.name !== 'AbortError') panne(); });
  }
  /* Editing a stored professional: its own registry record, if the SIREN is found. */
  function parSiren(siren) {
    annuaire(siren, items => {
      const x = items.find(i => i.siren === siren);
      if (!x || !FS.manual || CARTES.cFisc.dirty) return;
      choisir(x, true);
    });
  }
  /* A registry hit picked (`garder`: the stored profile's own record, its name and VAT number kept). */
  function choisir(x, garder = false) {
    const st = x.ets[0]?.siret || null;
    Object.assign(FS, {
      e: x, siret: st, ae: aeDefaut(x, st), svc: null, q: '', manual: false, down: false, dbl: null, res: [], hl: -1,
      ass: garder ? FS.ass : !!x.ass, rs: garder ? F.rs : x.n, tva: garder ? F.tva : (x.ass ? (x.tva || '') : ''),
    });
    if (!garder) marquer(CARTES.cFisc);
    CARTES.cFisc.rendre();
    doublon(x.siren);
  }
  /* « Un client porte déjà ce SIREN » for the company picked. */
  function doublon(siren) {
    if (!F.dblUrl) return;
    fetch(`${F.dblUrl}?${new URLSearchParams({ siren })}`, { headers: { Accept: 'application/json' }, signal })
      .then(r => (r.ok ? r.json() : null))
      .then(r => {
        const autre = (r?.items || []).find(c => c.id !== C.id);
        if (!autre || !FS?.e || FS.e.siren !== siren) return;
        FS.dbl = autre;
        CARTES.cFisc.rendre();
      })
      .catch(() => {});
  }
  /* The suggestion list, a listbox driven from the search field. */
  function suggestions() {
    const s = $('fzS'), q = $('fzQ');
    if (!s || !q) return;
    const L = FS.res, lent = FS.lent ? '<div class="vide">Recherche ralentie, continuez à taper.</div>' : '';
    s.innerHTML = lent + (L.length ? L.map((x, i) => `<button type="button" role="option" id="fzo${i}" data-sir="${esc(x.siren)}" aria-selected="${i === FS.hl}"${i === FS.hl ? ' class="hl"' : ''}><span>${esc(x.n)}<small>${esc([x.forme, x.ets[0]?.a.split(', ').pop()].filter(Boolean).join(' · '))}${x.cesse ? ' · radiée' : ''}</small></span><code>${fmtS(x.siren)}</code></button>`).join('')
      : lent ? '' : '<div class="vide">Aucun résultat dans l\'annuaire. Vérifiez l\'orthographe, tapez le SIREN ou saisissez à la main.</div>');
    s.classList.add('open');
    q.setAttribute('aria-expanded', 'true');
    if (FS.hl >= 0) q.setAttribute('aria-activedescendant', `fzo${FS.hl}`); else q.removeAttribute('aria-activedescendant');
  }
  function fermerSuggestions() {
    $('fzS')?.classList.remove('open');
    $('fzQ')?.setAttribute('aria-expanded', 'false');
  }
  function chercher(v) {
    window.clearTimeout(fzTimer);
    const lettres = norm(v).replace(/\d/g, '').trim(), d = v.replace(/\D/g, '');
    if (lettres.length < 3 && d.length !== 9 && d.length !== 14) { fzAbort?.abort(); fermerSuggestions(); return; }
    const id = ++fzSeq;
    fzTimer = window.setTimeout(() => annuaire(v, items => {
      if (id !== fzSeq) return;
      Object.assign(FS, { res: items, resQ: v, hl: -1, lent: false });
      suggestions();
    }, () => {
      if (id !== fzSeq) return;
      // Hits of another search would mislead: only those of the text typed stay.
      if (FS.resQ !== v) Object.assign(FS, { res: [], hl: -1 });
      FS.lent = true;
      suggestions();
    }), 300);
  }

  const elF = $('cFisc');
  elF.addEventListener('click', e => {
    if (e.target.closest('[data-fzrev]')) { $('fiscVerifyForm').requestSubmit(); return; }
    if (e.target.closest('[data-fznom]')) {
      const form = $('fiscForm'), set = (n, v) => { form.querySelector(`[name="${n}"]`).value = v; };
      set('fisc', PRO); set('rs', F.verification.registryName); set('siren', F.siren); set('tva', F.tva);
      form.requestSubmit();
      return;
    }
    if (!CARTES.cFisc.edit) return;
    if (e.target.closest('[data-fzman]')) {
      // What was searched is likely the legal name, or the SIREN.
      const q = (FS.q || '').trim();
      if (q && !FS.rs && !FS.siren) { if (/^[\d\s.]+$/.test(q)) FS.siren = q.replace(/\D/g, ''); else FS.rs = q; }
      Object.assign(FS, { manual: true, down: false, e: null, siret: null, ae: null });
      marquer(CARTES.cFisc);
      CARTES.cFisc.rendre();
      return;
    }
    if (e.target.closest('[data-fzrech]')) { Object.assign(FS, { manual: false, down: false }); CARTES.cFisc.rendre(); setTimeout(() => $('fzQ')?.focus(), 20); return; }
    const o = e.target.closest('[data-ft]');
    if (o) {
      if (o.getAttribute('aria-disabled') === 'true' || o.dataset.ft === FS.t) return;
      Object.assign(FS, { t: o.dataset.ft, e: null, siret: null, ae: null, svc: null, pays: null, q: '' });
      if (FS.t !== 'ent') FS.ass = false;
      marquer(CARTES.cFisc);
      CARTES.cFisc.rendre();
      setTimeout(() => $('fzQ')?.focus(), 20);
      return;
    }
    if (e.target.closest('[data-fzx]')) { Object.assign(FS, { e: null, siret: null, ae: null, q: '', manual: false, dbl: null, rs: '', siren: '', tva: '' }); marquer(CARTES.cFisc); CARTES.cFisc.rendre(); setTimeout(() => $('fzQ')?.focus(), 20); return; }
    if (e.target.closest('[data-fass]')) {
      FS.ass = !FS.ass;
      if (FS.ass && FS.e && !FS.tva) FS.tva = FS.e.tva || tvaFR(FS.e.siren);
      marquer(CARTES.cFisc);
      CARTES.cFisc.rendre();
    }
  }, { signal });
  elF.addEventListener('keydown', e => {
    if ((e.key === ' ' || e.key === 'Enter') && e.target.matches('[data-fass]')) { e.preventDefault(); e.target.click(); }
  }, { signal });
  /* A registry hit picked with the mouse. */
  elF.addEventListener('mousedown', e => {
    const b = e.target.closest('[data-sir]');
    if (!b || !FS) return;
    e.preventDefault();
    const x = FS.res.find(r => r.siren === b.dataset.sir);
    if (x) choisir(x);
  }, { signal });
  /* ↑ ↓ Entrée Échap in the search field; captured before the card's own Échap (cancel). */
  elF.addEventListener('keydown', e => {
    if (e.target.id !== 'fzQ' || !FS) return;
    const open = $('fzS')?.classList.contains('open'), n = FS.res.length;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      if (!n) return;
      e.preventDefault();
      FS.hl = e.key === 'ArrowDown' ? (FS.hl + 1) % n : (FS.hl - 1 + n) % n;
      suggestions();
    } else if (e.key === 'Enter' && !e.metaKey && !e.ctrlKey && open && FS.hl >= 0) {
      e.preventDefault();
      choisir(FS.res[FS.hl]);
    } else if (e.key === 'Escape' && open) {
      e.preventDefault();
      e.stopPropagation();
      fermerSuggestions();
    }
  }, { signal, capture: true });
  elF.addEventListener('input', e => {
    const t = e.target, f = FS;
    if (!f) return;
    if (t.id === 'fzQ') {
      f.q = t.value;
      chercher(t.value);
      return;
    }
    if (t.id === 'fzRs') { f.rs = t.value; marquer(CARTES.cFisc); }
    if (t.id === 'fzTva') { t.value = t.value.toUpperCase().replace(/[^A-Z0-9 ]/g, ''); f.tva = t.value; marquer(CARTES.cFisc); }
  }, { signal });
  elF.addEventListener('focusout', e => { if (e.target.id === 'fzQ') setTimeout(fermerSuggestions, 150); }, { signal });

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

  // Opened to fix something (a refused checkout): that card opens, then the URL forgets it.
  const card = root.dataset.card;
  if (card && !ARCH) {
    if (card === 'cCoord') ouvrirCoord();
    else if ($(card)) {
      $(card).scrollIntoView({ block: 'center', behavior: 'smooth' });
      $(card).querySelector('.bmod')?.click();
    }
    const url = new URL(window.location.href);
    url.searchParams.delete('card');
    window.history.replaceState(window.history.state, '', url);
  }
}

export function cleanup() {
  ac?.abort();
  ac = null;
  document.querySelectorAll('.dossier .mveil.open').forEach(m => m.classList.remove('open'));
}
