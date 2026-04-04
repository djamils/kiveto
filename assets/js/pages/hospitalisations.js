/**
 * Page module — Hospitalisations
 * Loaded by app.js dispatcher on turbo:load.
 */

// ════════════════════════════════════════════════════
// DATA
// ════════════════════════════════════════════════════
const PATIENTS = [
  {
    id:1, animal:'Rex', espece:'🐶', race:'Berger Allemand', age:'7 ans', poids:'36 kg',
    proprio:'François Garnier', tel:'+33 6 77 66 55 44',
    avatar:'RX', color:'#dc2626', bg:'#fef2f2',
    motif:'Insuffisance rénale aiguë',
    severity:'critique', vet:'Dr. Rousseau',
    admission:'2026-03-21 09:00', sortie_prev:'2026-03-25',
    notes:'Patient sous perfusion continue. Surveiller diurèse toutes les 4h. Suivi BU quotidien.',
    soins:[
      {id:101, type:'Perfusion IV', produit:'NaCl 0.9%', dose:'30ml/h', freq:'Continue', done:true,  heure:'En cours', priorite:'haute'},
      {id:102, type:'Médicament IV', produit:'Furosémide', dose:'2mg/kg', freq:'2×/jour', done:true,  heure:'8h00', priorite:'haute'},
      {id:103, type:'Médicament IV', produit:'Furosémide', dose:'2mg/kg', freq:'2×/jour', done:false, heure:'20h00', priorite:'haute'},
      {id:104, type:'Prise de sang', produit:'Bilan rénal', dose:'—', freq:'1×/jour', done:true,  heure:'7h30', priorite:'normale'},
      {id:105, type:'Prise de sang', produit:'Bilan rénal', dose:'—', freq:'1×/jour', done:false, heure:'7h30 demain', priorite:'normale'},
    ],
    alimentation:{
      type:'Ration réduite (50%)', eau:'Aucun accès (perfusion)',
      croquettes_g:0, eau_ml:0, eau_perf_ml:720,
      repas:[
        {heure:'7h30', type:'Alimentation humide', quantite:'100g', administre:true},
        {heure:'18h00', type:'Alimentation humide', quantite:'100g', administre:false},
      ]
    },
    evolution:[
      {heure:'10h00', date:'22/03', etat:'surveille', note:'Légère amélioration de la diurèse. Reste abattu. Appétit nul. Muqueuses pâles.', vet:'Dr. Rousseau', temp:38.9, fc:110, fr:24},
      {heure:'7h00',  date:'22/03', etat:'surveille', note:'Nuit difficile. Vomissements × 2. Perfusion maintenue. Urémie élevée au bilan.', vet:'Dr. Martin',   temp:39.1, fc:118, fr:26},
      {heure:'18h00', date:'21/03', etat:'degrade',   note:'Admission en urgence. Déshydraté, abattu. Début protocole perfusion.', vet:'Dr. Rousseau', temp:39.4, fc:125, fr:28},
    ]
  },
  {
    id:2, animal:'Luna', espece:'🐶', race:'Golden Retriever', age:'5 ans', poids:'28.5 kg',
    proprio:'Sarah Jenkins', tel:'+33 6 33 44 55 66',
    avatar:'LU', color:'#0891b2', bg:'#ecfeff',
    motif:'Post-opératoire stérilisation',
    severity:'stable', vet:'Dr. Dupont',
    admission:'2026-03-22 08:30', sortie_prev:'2026-03-23',
    notes:'Suite opératoire simple. Surveillance cicatrice et reprise alimentation progressive.',
    soins:[
      {id:201, type:'Médicament oral', produit:'Méloxicam', dose:'1.5mg/kg', freq:'1×/jour', done:true,  heure:'9h00', priorite:'normale'},
      {id:202, type:'Médicament oral', produit:'Méloxicam', dose:'1.5mg/kg', freq:'1×/jour', done:false, heure:'9h00 demain', priorite:'normale'},
      {id:203, type:'Pansement',       produit:'Cicatrice abdominale', dose:'—', freq:'1×/jour', done:true, heure:'8h30', priorite:'normale'},
      {id:204, type:'Pansement',       produit:'Cicatrice abdominale', dose:'—', freq:'1×/jour', done:false, heure:'8h30 demain', priorite:'normale'},
    ],
    alimentation:{
      type:'Ration réduite (50%)', eau:'Libre accès',
      croquettes_g:150, eau_ml:400, eau_perf_ml:0,
      repas:[
        {heure:'8h00',  type:'Pâtée post-op', quantite:'75g', administre:true},
        {heure:'13h00', type:'Pâtée post-op', quantite:'75g', administre:true},
        {heure:'18h00', type:'Pâtée post-op', quantite:'75g', administre:false},
      ]
    },
    evolution:[
      {heure:'9h30', date:'22/03', etat:'ameliore', note:'Réveil post-op sans complication. Mange un peu. Cicatrice propre. Douleur bien contrôlée.', vet:'Dr. Dupont', temp:38.5, fc:92, fr:20},
    ]
  },
  {
    id:3, animal:'Mimi', espece:'🐱', race:'Européen', age:'2 ans', poids:'3.8 kg',
    proprio:'Sophie Dubois', tel:'+33 6 12 34 56 78',
    avatar:'MI', color:'#7c3aed', bg:'#f5f3ff',
    motif:'Ingestion corps étranger — suivi post-endoscopie',
    severity:'surveille', vet:'Dr. Dupont',
    admission:'2026-03-22 10:15', sortie_prev:'2026-03-23',
    notes:'Extraction endoscopique réussie (fil de laine). Surveillance 48h. Alimentation progressive.',
    soins:[
      {id:301, type:'Médicament oral', produit:'Cerenia 4mg', dose:'1mg/kg', freq:'1×/jour', done:true, heure:'10h30', priorite:'normale'},
      {id:302, type:'Médicament oral', produit:'Cerenia 4mg', dose:'1mg/kg', freq:'1×/jour', done:false, heure:'10h30 demain', priorite:'normale'},
      {id:303, type:'Injection SC',    produit:'Métronidazole', dose:'15mg/kg', freq:'2×/jour', done:true, heure:'11h00', priorite:'normale'},
      {id:304, type:'Injection SC',    produit:'Métronidazole', dose:'15mg/kg', freq:'2×/jour', done:false, heure:'23h00', priorite:'normale'},
    ],
    alimentation:{
      type:'Ration réduite (50%)', eau:'Libre accès',
      croquettes_g:25, eau_ml:60, eau_perf_ml:0,
      repas:[
        {heure:'12h00', type:'Alimentation humide', quantite:'30g', administre:true},
        {heure:'18h00', type:'Alimentation humide', quantite:'30g', administre:false},
      ]
    },
    evolution:[
      {heure:'11h30', date:'22/03', etat:'stable', note:'État satisfaisant. A bu un peu. Aucun vomissement depuis l\'extraction. Légèrement apathique.', vet:'Dr. Dupont', temp:38.6, fc:140, fr:22},
    ]
  },
  {
    id:4, animal:'Bijou', espece:'🐱', race:'Persan', age:'1 an', poids:'3.1 kg',
    proprio:'Claire Moreau', tel:'+33 6 66 77 88 99',
    avatar:'BJ', color:'#f59e0b', bg:'#fffbeb',
    motif:'Gastro-entérite aiguë avec déshydratation',
    severity:'surveille', vet:'Dr. Martin',
    admission:'2026-03-22 07:45', sortie_prev:'2026-03-24',
    notes:'Déshydratation modérée. Perfusion de rééquilibration. Réévaluation à 24h.',
    soins:[
      {id:401, type:'Perfusion IV', produit:'Ringer Lactate', dose:'20ml/h', freq:'Continue', done:true, heure:'En cours', priorite:'haute'},
      {id:402, type:'Injection IV', produit:'Métoclopramide', dose:'0.5mg/kg', freq:'3×/jour', done:true, heure:'8h00', priorite:'normale'},
      {id:403, type:'Injection IV', produit:'Métoclopramide', dose:'0.5mg/kg', freq:'3×/jour', done:false, heure:'16h00', priorite:'normale'},
      {id:404, type:'Injection IV', produit:'Métoclopramide', dose:'0.5mg/kg', freq:'3×/jour', done:false, heure:'24h00', priorite:'normale'},
    ],
    alimentation:{
      type:'Diète stricte', eau:'Aucun accès (perfusion)',
      croquettes_g:0, eau_ml:0, eau_perf_ml:480,
      repas:[]
    },
    evolution:[
      {heure:'9h00',  date:'22/03', etat:'stable',    note:'Perfusion en cours. Aucun vomissement depuis 4h. Toujours abattu mais réactif.', vet:'Dr. Martin', temp:38.8, fc:148, fr:24},
      {heure:'7h45',  date:'22/03', etat:'surveille', note:'Admission. Vomissements répétés (×6 en 12h). Déshydratation estimée à 8%. Début perfusion.', vet:'Dr. Martin', temp:39.2, fc:162, fr:28},
    ]
  }
];

// ════════════════════════════════════════════════════
// STATE
// ════════════════════════════════════════════════════
let selectedId = null;
let currentTab = 'soins';

// ════════════════════════════════════════════════════
// UTILS
// ════════════════════════════════════════════════════
function toast(m, c = '#16a34a') {
  const t = document.getElementById('toast');
  t.textContent = m;
  t.style.background = c;
  t.style.opacity = '1';
  t.style.transform = 'translateX(-50%) translateY(0)';
  setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translateX(-50%) translateY(8px)';
  }, 2500);
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function fmtDays(dateStr) {
  const d = Math.floor((new Date() - new Date(dateStr)) / 86400000);
  if (d === 0) return "Admis aujourd'hui";
  if (d === 1) return '1 jour';
  return `${d} jours`;
}

function severityColor(s) { return { critique: '#dc2626', surveille: '#d97706', stable: '#059669' }[s] || '#64748b'; }
function severityBg(s) { return { critique: '#fef2f2', surveille: '#fff7ed', stable: '#f0fdf4' }[s] || '#f8fafc'; }
function severityLabel(s) { return { critique: 'Critique', surveille: 'Surveillé', stable: 'Stable' }[s] || s; }
function etatColor(e) { return { ameliore: '#059669', stable: '#0891b2', surveille: '#d97706', degrade: '#dc2626' }[e] || '#64748b'; }
function etatLabel(e) { return { ameliore: 'Amélioré', stable: 'Stable', surveille: 'À surveiller', degrade: 'Dégradé' }[e] || e; }
function etatIcon(e) { return { ameliore: '✅', stable: '➡', surveille: '⚠️', degrade: '🔴' }[e] || '•'; }

// ════════════════════════════════════════════════════
// LIST
// ════════════════════════════════════════════════════
function renderList() {
  document.getElementById('list-count').textContent = `${PATIENTS.length} patients`;
  const sorted = [...PATIENTS].sort((a, b) => {
    const order = { critique: 0, surveille: 1, stable: 2 };
    return (order[a.severity] || 3) - (order[b.severity] || 3);
  });
  document.getElementById('hosp-list').innerHTML = sorted.map(p => {
    const active = p.id === selectedId;
    const sc = severityColor(p.severity);
    const sb = severityBg(p.severity);
    const done = p.soins.filter(s => s.done).length;
    const total = p.soins.length;
    const pct = Math.round(done / total * 100);
    const lastEvo = p.evolution[0];
    return `<div class="hcard${active ? ' active' : ''}" onclick="selectPatient(${p.id})" id="hc-${p.id}">
      <div class="hcard-row">
        <div class="hcard-avatar" style="background:${p.bg};color:${p.color};">${p.espece}</div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
            <p style="font-size:var(--text-md);font-weight:600;color:#0f172a;">${p.animal}</p>
            <span style="font-size:var(--text-xs);font-weight:var(--weight-medium);padding:1px 7px;border-radius:20px;background:${sb};color:${sc};">${severityLabel(p.severity)}</span>
          </div>
          <p style="font-size:var(--text-xs);color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${p.motif}</p>
          <div style="display:flex;align-items:center;gap:8px;margin-top:3px;">
            <span style="font-size:var(--text-xs);color:#94a3b8;">${fmtDays(p.admission)}</span>
            <span style="font-size:var(--text-xs);color:#94a3b8;">· ${p.vet}</span>
          </div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <p style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:${done === total ? '#059669' : '#0f172a'};">${done}/${total}</p>
          <p style="font-size:var(--text-xs);color:#94a3b8;">soins</p>
        </div>
      </div>
      <!-- Progress bar -->
      <div style="margin-top:8px;">
        <div style="height:3px;border-radius:2px;background:#e2e8f0;overflow:hidden;">
          <div style="height:100%;border-radius:2px;background:${done === total ? '#16a34a' : '#4338ca'};width:${pct}%;transition:width .4s;"></div>
        </div>
      </div>
    </div>`;
  }).join('');
}

// ════════════════════════════════════════════════════
// SELECT + DETAIL
// ════════════════════════════════════════════════════
function selectPatient(id) {
  selectedId = id;
  currentTab = 'soins';
  document.querySelectorAll('.hcard').forEach(el => el.classList.toggle('active', el.id === `hc-${id}`));
  var p = PATIENTS.find(x => x.id === id);
  if (window.innerWidth > 1024) {
    renderDetail(p);
  } else {
    renderDetail(p);
    var w = window.innerWidth;
    var html = document.getElementById('detail-panel').innerHTML;
    if (w <= 1024 && w > 640) {
      document.getElementById('slide-inner').innerHTML = html;
      document.getElementById('detail-slide').classList.add('open');
      document.getElementById('drawer-overlay').classList.add('open');
    } else if (w <= 640) {
      document.getElementById('bs-inner').innerHTML = html;
      var bs = document.getElementById('bs');
      document.getElementById('bs-overlay').style.display = 'block';
      bs.style.display = 'flex';
      requestAnimationFrame(function () { bs.style.transform = 'translateY(0)'; });
    }
  }
}

function setTab(tab) {
  currentTab = tab;
  document.querySelectorAll('.dtab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
  renderTabContent(PATIENTS.find(p => p.id === selectedId));
}

function renderDetail(p) {
  const sc = severityColor(p.severity), sb = severityBg(p.severity);
  const done = p.soins.filter(s => s.done).length, total = p.soins.length;
  const lastEvo = p.evolution[0];

  document.getElementById('detail-panel').innerHTML = `
    <!-- Header -->
    <div class="detail-header">
      <div style="display:flex;align-items:center;gap:14px;">
        <div style="width:48px;height:48px;border-radius:50%;background:${p.bg};color:${p.color};display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;flex-shrink:0;">${p.espece}</div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px;">
            <h2 style="font-size:var(--text-lg);font-weight:600;color:#0f172a;">${p.animal}</h2>
            <span style="font-size:var(--text-sm);color:#64748b;">${p.race} · ${p.age} · ${p.poids}</span>
            <span style="font-size:var(--text-xs);font-weight:var(--weight-medium);padding:2px 9px;border-radius:20px;background:${sb};color:${sc};">${severityLabel(p.severity)}</span>
            ${lastEvo ? `<span style="font-size:var(--text-xs);font-weight:var(--weight-medium);padding:2px 9px;border-radius:20px;background:${etatColor(lastEvo.etat)}22;color:${etatColor(lastEvo.etat)};">${etatIcon(lastEvo.etat)} ${etatLabel(lastEvo.etat)}</span>` : ''}
          </div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="font-size:var(--text-sm);color:#94a3b8;">${p.proprio} · ${p.tel}</span>
            <span style="font-size:var(--text-sm);color:#94a3b8;">· ${p.vet}</span>
            <span style="font-size:var(--text-sm);color:#94a3b8;">· Admis ${fmtDays(p.admission)}</span>
            <span style="font-size:var(--text-sm);color:${p.sortie_prev === new Date().toISOString().slice(0, 10) ? '#d97706' : '#94a3b8'};">· Sortie prévue ${new Date(p.sortie_prev).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })}</span>
          </div>
        </div>
        <div style="display:flex;gap:6px;flex-shrink:0;flex-wrap:wrap;justify-content:flex-end;">
          <button class="btn btn-secondary btn-xs" onclick="openModal('modal-evo')">+ Évolution</button>
          <button class="btn btn-secondary btn-xs" onclick="openModal('modal-soin')">+ Soin</button>
          <button class="btn btn-success btn-xs" onclick="openModal('modal-sortie')">
            <svg width="11" height="11" fill="none" viewBox="0 0 12 12"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Préparer la sortie
          </button>
        </div>
      </div>
      ${p.notes ? `<div style="margin-top:10px;background:#f8fafc;border-radius:8px;padding:8px 12px;font-size:var(--text-sm);color:#475569;border-left:3px solid #4338ca;">${p.notes}</div>` : ''}
    </div>

    <!-- Tabs -->
    <div class="detail-tabs">
      <div class="dtab active" data-tab="soins" onclick="setTab('soins')">
        Protocole de soins
        <span style="font-size:var(--text-xs);background:${done === total ? '#f0fdf4' : '#eef2ff'};color:${done === total ? '#16a34a' : '#4338ca'};padding:0 6px;border-radius:10px;margin-left:5px;">${done}/${total}</span>
      </div>
      <div class="dtab" data-tab="alim" onclick="setTab('alim')">Alimentation & Hydratation</div>
      <div class="dtab" data-tab="evo" onclick="setTab('evo')">
        Évolution
        <span style="font-size:var(--text-xs);background:#f8fafc;color:#64748b;padding:0 6px;border-radius:10px;margin-left:5px;border:1px solid #e2e8f0;">${p.evolution.length}</span>
      </div>
      <div class="dtab" data-tab="sortie" onclick="setTab('sortie')">Sortie & Compte-rendu</div>
    </div>

    <!-- Tab content -->
    <div class="detail-scroll" id="tab-content"></div>`;

  renderTabContent(p);
}

function renderTabContent(p) {
  const el = document.getElementById('tab-content');
  if (!el || !p) return;

  if (currentTab === 'soins') {
    const pending = p.soins.filter(s => !s.done);
    const done = p.soins.filter(s => s.done);
    el.innerHTML = `
      <div class="widget">
        <div class="wh">
          <span class="wt">À faire — ${pending.length} soins en attente</span>
          <button class="btn btn-primary btn-xs" onclick="openModal('modal-soin')">+ Ajouter un soin</button>
        </div>
        ${pending.length ? `<div>${pending.map(s => soinRow(s, p)).join('')}</div>` : '<div class="wb"><p style="font-size:var(--text-md);color:#94a3b8;text-align:center;padding:8px 0;">✅ Tous les soins du jour ont été administrés</p></div>'}
      </div>
      ${done.length ? `
      <div class="widget">
        <div class="wh"><span class="wt">Administrés — ${done.length} soins</span></div>
        <div>${done.map(s => soinRow(s, p)).join('')}</div>
      </div>` : ''}`;

  } else if (currentTab === 'alim') {
    const a = p.alimentation;
    const totalRepas = a.repas.length;
    const prisRepas = a.repas.filter(r => r.administre).length;
    el.innerHTML = `
      <div class="w2" style="margin-bottom:12px;">
        <div class="widget">
          <div class="wh"><span class="wt">Alimentation</span><button class="btn btn-secondary btn-xs" onclick="toast('Modifier…','#4338ca')">Modifier</button></div>
          <div class="wb">
            <div class="irow"><span class="il">Régime</span><span class="iv">${a.type}</span></div>
            <div class="irow"><span class="il">Repas aujourd'hui</span><span class="iv" style="color:${prisRepas === totalRepas ? '#059669' : '#d97706'}">${prisRepas}/${totalRepas} donnés</span></div>
            ${a.croquettes_g ? `<div class="irow"><span class="il">Quantité totale</span><span class="iv">${a.croquettes_g} g</span></div>` : ''}
            ${totalRepas === 0 ? `<div style="margin-top:8px;text-align:center;padding:8px;background:#f8fafc;border-radius:8px;font-size:var(--text-sm);color:#94a3b8;">Aucune alimentation prévue</div>` : ''}
          </div>
          ${a.repas.length ? `<div>${a.repas.map((r, i, arr) => `
            <div style="display:flex;align-items:center;gap:10px;padding:8px 14px;${i < arr.length - 1 ? 'border-bottom:1px solid #f1f5f9;' : ''}transition:background .12s;" onmouseenter="this.style.background='#f5f3ff'" onmouseleave="this.style.background='transparent'">
              <div style="width:20px;height:20px;border-radius:6px;background:${r.administre ? '#4338ca' : '#e2e8f0'};display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;" onclick="toast('${r.administre ? 'Repas annulé' : 'Repas marqué administré'}','#4338ca')">
                ${r.administre ? `<svg width="10" height="10" fill="none" viewBox="0 0 10 10"><path d="M2 5l2 2 4-4" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>` : ''}
              </div>
              <div style="flex:1;"><p style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:#0f172a;">${r.type} · ${r.quantite}</p></div>
              <span style="font-size:var(--text-sm);color:#94a3b8;">${r.heure}</span>
              <span class="badge ${r.administre ? 'b-ok' : 'b-grey'}" style="font-size:var(--text-xs);">${r.administre ? 'Donné' : 'En attente'}</span>
            </div>`).join('')}</div>` : ''
          }
        </div>
        <div class="widget">
          <div class="wh"><span class="wt">Hydratation</span></div>
          <div class="wb">
            <div class="irow"><span class="il">Accès eau</span><span class="iv">${a.eau}</span></div>
            ${a.eau_ml ? `<div class="irow"><span class="il">Eau bue (estimée)</span><span class="iv">${a.eau_ml} ml</span></div>` : ''}
            ${a.eau_perf_ml ? `<div class="irow"><span class="il">Apport perfusion</span><span class="iv" style="color:#2563eb;">${a.eau_perf_ml} ml/jour</span></div>` : ''}
            <div style="margin-top:10px;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                <span style="font-size:var(--text-xs);color:#64748b;font-weight:var(--weight-medium);">Hydratation estimée</span>
                <span style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:${a.eau_ml + a.eau_perf_ml > 300 ? '#059669' : '#d97706'};">${a.eau_ml + a.eau_perf_ml} ml</span>
              </div>
              <div class="hydra-bar">
                <div class="hydra-fill" style="width:${Math.min(100, Math.round((a.eau_ml + a.eau_perf_ml) / 800 * 100))}%;background:${a.eau_ml + a.eau_perf_ml > 300 ? '#3b82f6' : '#f59e0b'};"></div>
              </div>
              <p style="font-size:var(--text-xs);color:#94a3b8;margin-top:3px;">Objectif indicatif : 50ml/kg/j (${Math.round(parseFloat(p.poids) * 50)} ml)</p>
            </div>
          </div>
        </div>
      </div>`;

  } else if (currentTab === 'evo') {
    el.innerHTML = `
      <div class="widget">
        <div class="wh">
          <span class="wt">Journal d'évolution</span>
          <button class="btn btn-primary btn-xs" onclick="openModal('modal-evo')">+ Saisir une évolution</button>
        </div>
        <div class="wb">
          ${p.evolution.map((e, i, arr) => `
            <div class="evo-entry">
              <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;">
                <div class="evo-dot" style="background:${etatColor(e.etat)};"></div>
                ${i < arr.length - 1 ? `<div style="flex:1;width:1px;background:#e2e8f0;min-height:20px;margin-top:4px;"></div>` : ''}
              </div>
              <div style="flex:1;min-width:0;padding-bottom:${i < arr.length - 1 ? '10px' : '0'};">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:5px;">
                  <span style="font-size:var(--text-sm);font-weight:var(--weight-medium);padding:2px 8px;border-radius:20px;background:${etatColor(e.etat)}18;color:${etatColor(e.etat)};">${etatIcon(e.etat)} ${etatLabel(e.etat)}</span>
                  <span style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:#334155;">${e.date} à ${e.heure}</span>
                  <span style="font-size:var(--text-xs);color:#94a3b8;">${e.vet}</span>
                </div>
                <p style="font-size:var(--text-md);color:#334155;line-height:1.5;margin-bottom:6px;">${e.note}</p>
                ${e.temp || e.fc || e.fr ? `
                <div style="display:inline-flex;gap:10px;background:#f8fafc;border:1px solid #e8edf2;border-radius:8px;padding:5px 10px;">
                  ${e.temp ? `<span style="font-size:var(--text-xs);color:#64748b;">🌡 <strong>${e.temp}°C</strong></span>` : ''}
                  ${e.fc ? `<span style="font-size:var(--text-xs);color:#64748b;">❤️ <strong>${e.fc} bpm</strong></span>` : ''}
                  ${e.fr ? `<span style="font-size:var(--text-xs);color:#64748b;">🫁 <strong>${e.fr}/min</strong></span>` : ''}
                </div>` : ''}
              </div>
            </div>`).join('')}
        </div>
      </div>`;

  } else if (currentTab === 'sortie') {
    const jours = Math.floor((new Date() - new Date(p.admission)) / 86400000) + 1;
    const soinsRealis = p.soins.filter(s => s.done).length;
    el.innerHTML = `
      <div class="w2" style="margin-bottom:12px;">
        <div class="widget">
          <div class="wh"><span class="wt">Résumé du séjour</span></div>
          <div class="wb">
            <div class="irow"><span class="il">Durée d'hospitalisation</span><span class="iv">${jours} jour${jours > 1 ? 's' : ''}</span></div>
            <div class="irow"><span class="il">Admission</span><span class="iv">${new Date(p.admission).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })}</span></div>
            <div class="irow"><span class="il">Sortie prévue</span><span class="iv" style="color:#d97706;">${new Date(p.sortie_prev).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}</span></div>
            <div class="irow"><span class="il">Soins réalisés</span><span class="iv">${soinsRealis} / ${p.soins.length}</span></div>
            <div class="irow"><span class="il">Observations</span><span class="iv">${p.evolution.length}</span></div>
            <div class="irow"><span class="il">Vétérinaire référent</span><span class="iv">${p.vet}</span></div>
          </div>
        </div>
        <div class="widget">
          <div class="wh"><span class="wt">Dernière évolution</span></div>
          <div class="wb">
            ${p.evolution[0] ? `
              <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                <span style="font-size:var(--text-md);font-weight:var(--weight-medium);padding:3px 10px;border-radius:20px;background:${etatColor(p.evolution[0].etat)}18;color:${etatColor(p.evolution[0].etat)};">${etatIcon(p.evolution[0].etat)} ${etatLabel(p.evolution[0].etat)}</span>
                <span style="font-size:var(--text-sm);color:#94a3b8;">${p.evolution[0].date} ${p.evolution[0].heure}</span>
              </div>
              <p style="font-size:var(--text-md);color:#334155;line-height:1.5;">${p.evolution[0].note}</p>
            ` : '<p style="font-size:var(--text-sm);color:#94a3b8;">Aucune évolution saisie</p>'}
          </div>
        </div>
      </div>

      <div class="widget">
        <div class="wh">
          <span class="wt">Compte-rendu de sortie</span>
          <div style="display:flex;gap:6px;">
            <button class="btn btn-secondary btn-xs" onclick="window.print()">
              <svg width="11" height="11" fill="none" viewBox="0 0 12 12"><path d="M3 1h6v3H3V1zM1 4h10v5H9v2H3V9H1V4z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>Imprimer
            </button>
            <button class="btn btn-primary btn-xs" onclick="openModal('modal-sortie')">Préparer la sortie</button>
          </div>
        </div>
        <div class="wb">
          <div style="background:#f8fafc;border:1px solid #e8edf2;border-radius:9px;padding:14px;margin-bottom:10px;">
            <p style="font-size:var(--text-xs);font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px;">Motif d'hospitalisation</p>
            <p style="font-size:var(--text-md);color:#0f172a;font-weight:var(--weight-medium);">${p.motif}</p>
          </div>
          <div style="background:#f8fafc;border:1px solid #e8edf2;border-radius:9px;padding:14px;margin-bottom:10px;">
            <p style="font-size:var(--text-xs);font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px;">Soins réalisés</p>
            ${p.soins.filter(s => s.done).map(s => `<p style="font-size:var(--text-sm);color:#334155;margin-bottom:3px;">• ${s.type} — ${s.produit} ${s.dose} (${s.freq})</p>`).join('') || '<p style="font-size:var(--text-sm);color:#94a3b8;">Aucun soin validé</p>'}
          </div>
          <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:9px;padding:14px;display:flex;align-items:center;gap:10px;">
            <svg width="16" height="16" fill="none" viewBox="0 0 16 16" style="color:#d97706;flex-shrink:0;"><path d="M8 2l6 12H2L8 2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M8 7v3M8 11.5h.01" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            <p style="font-size:var(--text-sm);color:#92400e;">Le compte-rendu de sortie complet sera généré après validation. Cliquez sur <strong>Préparer la sortie</strong> pour compléter et imprimer.</p>
          </div>
        </div>
      </div>`;
  }
}

function soinRow(s, p) {
  const isHaute = s.priorite === 'haute';
  return `<div class="soin-row">
    <div class="soin-check${s.done ? ' done' : ''}" onclick="toggleSoin(${p.id},${s.id})">
      <svg width="10" height="10" fill="none" viewBox="0 0 10 10"><path d="M2 5l2.5 2.5L8 2.5" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <div style="flex:1;min-width:0;">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;flex-wrap:wrap;">
        <span style="font-size:var(--text-sm);font-weight:${s.done ? '400' : '600'};color:${s.done ? '#94a3b8' : '#0f172a'};${s.done ? 'text-decoration:line-through;' : ''}">${s.produit}</span>
        <span class="badge ${isHaute ? 'b-alert' : 'b-grey'}" style="font-size:var(--text-xs);">${s.type}</span>
        ${isHaute && !s.done ? `<span style="font-size:var(--text-xs);font-weight:var(--weight-medium);color:#dc2626;">● Priorité haute</span>` : ''}
      </div>
      <p style="font-size:var(--text-xs);color:#94a3b8;">${s.dose} · ${s.freq}</p>
    </div>
    <div style="text-align:right;flex-shrink:0;">
      <p style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:${s.done ? '#94a3b8' : '#334155'};">${s.heure}</p>
      ${s.done ? `<span class="badge b-ok" style="font-size:var(--text-xs);">Fait</span>` : ``}
    </div>
  </div>`;
}

function toggleSoin(patientId, soinId) {
  const p = PATIENTS.find(x => x.id === patientId);
  const s = p.soins.find(x => x.id === soinId);
  s.done = !s.done;
  toast(s.done ? `${s.produit} — administré ✓` : `${s.produit} — annulé`, '#4338ca');
  renderList();
  renderTabContent(p);
}

// ════════════════════════════════════════════════════
// SIDEBAR DRAWER
// ════════════════════════════════════════════════════
function toggleSidebar() {
  var sb = document.querySelector('.sidebar');
  var ov = document.getElementById('drawer-overlay');
  var isOpen = sb.classList.contains('open');
  if (isOpen) { sb.classList.remove('open'); ov.classList.remove('open'); }
  else { sb.classList.add('open'); ov.classList.add('open'); }
}

function closeSidebar() {
  document.querySelector('.sidebar').classList.remove('open');
  document.getElementById('drawer-overlay').classList.remove('open');
}

// Slide / Bottom sheet detail
function closeBs() {
  var bs = document.getElementById('bs');
  bs.style.transform = 'translateY(100%)';
  setTimeout(function () { bs.style.display = 'none'; document.getElementById('bs-overlay').style.display = 'none'; }, 300);
}

function closeSlide() {
  document.getElementById('detail-slide').classList.remove('open');
  document.getElementById('drawer-overlay').classList.remove('open');
}

// Keyboard handler reference for cleanup
function _onKeydown(e) {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
}

// ════════════════════════════════════════════════════
// EXPORTS
// ════════════════════════════════════════════════════

/** Initialise the hospitalisation page. */
export function init() {
  // Expose functions used via onclick in HTML
  window.toast = toast;
  window.openModal = openModal;
  window.closeModal = closeModal;
  window.selectPatient = selectPatient;
  window.setTab = setTab;
  window.toggleSoin = toggleSoin;
  window.toggleSidebar = toggleSidebar;
  window.closeSidebar = closeSidebar;
  window.closeBs = closeBs;
  window.closeSlide = closeSlide;

  renderList();
  selectPatient(1);

  document.addEventListener('keydown', _onKeydown);
}

/** Teardown: remove global listeners and window references. */
export function cleanup() {
  document.removeEventListener('keydown', _onKeydown);

  delete window.toast;
  delete window.openModal;
  delete window.closeModal;
  delete window.selectPatient;
  delete window.setTab;
  delete window.toggleSoin;
  delete window.toggleSidebar;
  delete window.closeSidebar;
  delete window.closeBs;
  delete window.closeSlide;
}
