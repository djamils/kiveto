/**
 * Page module — Client View (Fiche Client)
 * Loaded by app.js dispatcher on turbo:load.
 */

// -- Drawer (left col on tablet/mobile) --
function toggleSidebar(){
  var overlay = document.getElementById('drawer-overlay');
  var sidebar = document.querySelector('.sidebar');
  var isOpen = sidebar && sidebar.classList.contains('open');
  closeAllDrawers();
  if(!isOpen && sidebar){ sidebar.classList.add('open'); overlay.classList.add('open'); }
}
function toggleOwnerDrawer(){
  var overlay = document.getElementById('drawer-overlay');
  var col = document.querySelector('.col-left');
  var isOpen = col.classList.contains('open');
  closeAllDrawers();
  if(!isOpen){ col.classList.add('open'); overlay.classList.add('open'); }
}
function closeAllDrawers(){
  document.getElementById('drawer-overlay').classList.remove('open');
  var sidebar = document.querySelector('.sidebar');
  if(sidebar) sidebar.classList.remove('open');
  document.querySelector('.col-left').classList.remove('open');
}

// ==============================
// DATA
// ==============================
const ANIMALS = [
  {
    id:0, name:'Luna', emoji:'🐱', species:'Félin', breed:'Persan',
    photo:null, dob:'2019-06-10', weight:'4.2', sex:'F', sterilized:true,
    color:'Blanc', chip:'250268500012345', tattoo:null, passport:'FR-2019-123456',
    loof:'LOOF-2019-789', sire:null, crossBreed:null,
    status:'alive', tabColor:'#4338ca',
    auxContact:{first:'Jean', last:'Dubois', phone:'+33 6 99 88 77 66', rel:'Conjoint'},
    weights:[{d:'Oct',v:4.0},{d:'Nov',v:4.1},{d:'Déc',v:4.1},{d:'Jan',v:4.2},{d:'Fév',v:4.1},{d:'Mar',v:4.2}],
    vitals:{
      temp:[{d:'Oct',v:38.5},{d:'Nov',v:38.6},{d:'Déc',v:38.4},{d:'Jan',v:38.7},{d:'Fév',v:38.5},{d:'Mar',v:38.6}],
      fc:  [{d:'Oct',v:140}, {d:'Nov',v:145},{d:'Déc',v:138},{d:'Jan',v:142},{d:'Fév',v:144},{d:'Mar',v:140}],
      fr:  [{d:'Oct',v:22},  {d:'Nov',v:24}, {d:'Déc',v:20}, {d:'Jan',v:22}, {d:'Fév',v:23}, {d:'Mar',v:21}],
    },
    allergies:['Pénicilline'],
    antecedents:['Stérilisation (2020)','Détartrage (2023)'],
    vaccins:[
      {name:'Typhus-Coryza', date:'2025-12-10', next:'2026-12-10', status:'ok'},
      {name:'Leucose FeLV',  date:'2025-12-10', next:'2026-12-10', status:'ok'},
      {name:'Rage',          date:'2024-11-05', next:'2025-11-05', status:'warn'},
      {name:'Chlamydiose',   date:'2025-06-15', next:'2026-06-15', status:'ok'},
    ],
    traitements:[{nom:'Cystaid Plus',dose:'1 gélule/j',debut:'2026-01-10',fin:'2026-04-10',vet:'Dr. Rousseau'}],
    medias:[
      {nom:'Ordonnance Cystaid',     date:'2026-01-10', type:'Ordonnance', isImage:false},
      {nom:'Résultats analyse sang', date:'2025-12-10', type:'Analyse',    isImage:false},
      {nom:'Certificat vaccination', date:'2025-12-10', type:'Certificat', isImage:false},
      {nom:'Photo profil',           date:'2026-03-21', type:'Photo',      isImage:true,  thumb:'🐱'},
      {nom:'Post-vaccination',       date:'2025-12-10', type:'Photo',      isImage:true,  thumb:'😸'},
      {nom:'Empreinte patte',        date:'2025-09-03', type:'Photo',      isImage:true,  thumb:'🐾'},
      {nom:'Radio thorax',           date:'2025-05-18', type:'Image médicale', isImage:true, thumb:'🩻'},
      {nom:'Echo abdominale',        date:'2025-01-10', type:'Image médicale', isImage:true, thumb:'🔬'},
    ],
    facturation:[
      {libelle:'Consultation + pesée',  date:'2026-03-21', montant:65,  statut:'ok',      ref:'FAC-2026-042', vet:'Dr. Rousseau', actes:['Examen clinique','Pesée']},
      {libelle:'Vaccin typhus-coryza',  date:'2025-12-10', montant:38,  statut:'ok',      ref:'FAC-2025-198', vet:'Dr. Martin',   actes:['Vaccination']},
      {libelle:'Suivi urinaire',        date:'2025-09-03', montant:45,  statut:'pending', ref:'FAC-2025-134', vet:'Dr. Rousseau', actes:['Examen clinique','Analyse urine']},
      {libelle:'Examen ORL',            date:'2025-05-18', montant:50,  statut:'unpaid',  ref:'FAC-2025-071', vet:'Dr. Dupont',   actes:['Examen ORL']},
    ],
    factTotal:198,
    consultations:[
      {date:'2026-03-21',motif:'Consultation',vet:'Dr. Rousseau',note:'Bilan annuel — état général excellent. Poids stable. Renouvellement traitement urinaire.',actes:['Examen clinique','Pesée']},
      {date:'2025-12-10',motif:'Vaccin',vet:'Dr. Martin',note:'Rappel typhus-coryza et leucose. Aucune réaction adverse.',actes:['Vaccination typhus-coryza','Vaccination FeLV']},
      {date:'2025-09-03',motif:'Suivi',vet:'Dr. Rousseau',note:'Contrôle poids et urinaire. Légère amélioration.',actes:['Examen clinique']},
      {date:'2025-05-18',motif:'Consultation',vet:'Dr. Dupont',note:'Épistaxis légère — origine traumatique probable. Surveillance.',actes:['Examen ORL']},
    ]
  },
  {
    id:1, name:'Mimi', emoji:'🐱', species:'Félin', breed:'Européen',
    photo:null, dob:'2022-01-20', weight:'3.8', sex:'F', sterilized:true,
    color:'Tigré', chip:'250268500012346', tattoo:'GF-7X9', passport:null,
    loof:null, sire:null, crossBreed:null,
    status:'alive', tabColor:'#f59e0b',
    auxContact:null,
    weights:[{d:'Oct',v:3.5},{d:'Nov',v:3.6},{d:'Déc',v:3.7},{d:'Jan',v:3.9},{d:'Fév',v:3.7},{d:'Mar',v:3.8}],
    vitals:{
      temp:[{d:'Oct',v:38.3},{d:'Nov',v:38.5},{d:'Déc',v:38.4},{d:'Jan',v:38.8},{d:'Fév',v:38.6},{d:'Mar',v:38.5}],
      fc:  [{d:'Oct',v:138},{d:'Nov',v:142},{d:'Déc',v:136},{d:'Jan',v:150},{d:'Fév',v:141},{d:'Mar',v:139}],
      fr:  [{d:'Oct',v:20}, {d:'Nov',v:22}, {d:'Déc',v:21}, {d:'Jan',v:28}, {d:'Fév',v:22}, {d:'Mar',v:20}],
    },
    allergies:[],
    antecedents:['Stérilisation (2022)','Ingestion corps étranger (jan. 2026) — résolu'],
    vaccins:[
      {name:'Typhus-Coryza',date:'2025-08-20',next:'2026-08-20',status:'ok'},
      {name:'Leucose FeLV', date:'2025-08-20',next:'2026-08-20',status:'ok'},
      {name:'Rage',         date:'2023-08-20',next:'2024-08-20',status:'alert'},
    ],
    traitements:[],
    medias:[
      {nom:'CR Urgence corps étranger', date:'2026-01-15', type:'Compte-rendu',    isImage:false},
      {nom:'Certificat vaccination',    date:'2025-08-20', type:'Certificat',       isImage:false},
      {nom:'Radio abdominale urgence',  date:'2026-01-15', type:'Image médicale',   isImage:true, thumb:'🩻'},
      {nom:'Photo profil',              date:'2025-08-20', type:'Photo',            isImage:true, thumb:'🐱'},
      {nom:'Empreinte',                 date:'2025-08-20', type:'Photo',            isImage:true, thumb:'🐾'},
    ],
    facturation:[
      {libelle:'Urgence — endoscopie', date:'2026-01-15', montant:145, statut:'ok',      ref:'FAC-2026-008', vet:'Dr. Dupont',   actes:['Radiographie','Endoscopie','Injection']},
      {libelle:'Primo-vaccination',    date:'2025-08-20', montant:42,  statut:'ok',      ref:'FAC-2025-112', vet:'Dr. Rousseau', actes:['Vaccination typhus-coryza','Vaccination FeLV']},
    ],
    factTotal:187,
    consultations:[
      {date:'2026-01-15',motif:'Urgence',vet:'Dr. Dupont',note:'Ingestion corps étranger (fil de laine). Extraction endoscopique réussie. Surveillance 48h.',actes:['Radiographie','Endoscopie','Injection antispasmodique']},
      {date:'2025-08-20',motif:'Vaccin',vet:'Dr. Rousseau',note:'Primo-vaccination complète. Aucune réaction adverse.',actes:['Vaccination typhus-coryza','Vaccination FeLV']},
    ]
  }
];

// ==============================
// STATE
// ==============================
let currentAnimal = 0;
let currentVital = 'weight';

// ==============================
// UTILITIES
// ==============================
function toast(m,c='#16a34a'){const t=document.getElementById('toast');t.textContent=m;t.style.background=c;t.style.opacity='1';t.style.transform='translateX(-50%) translateY(0)';setTimeout(()=>{t.style.opacity='0';t.style.transform='translateX(-50%) translateY(8px)';},2500);}
function fmtDate(d){if(!d)return'—';return new Date(d).toLocaleDateString('fr-FR',{day:'numeric',month:'short',year:'numeric'});}
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
function updateStatusNote(){}

// ==============================
// CHART RENDERERS (type-aware, interactive)
// ==============================

const NORMAL_RANGES = {
  temp: {min:38.0, max:39.0},
  fc:   {min:120,  max:200},
  fr:   {min:15,   max:30},
};

// Global tooltip element — created once
function getTooltip(){
  let t = document.getElementById('chart-tooltip');
  if(!t){
    t = document.createElement('div');
    t.id = 'chart-tooltip';
    t.style.cssText = 'position:fixed;pointer-events:none;z-index:900;background:#1e293b;color:#fff;font-size:var(--text-xs);font-family:DM Sans,sans-serif;font-weight:var(--weight-medium);padding:5px 9px;border-radius:7px;white-space:nowrap;opacity:0;transition:opacity .12s;box-shadow:0 4px 12px rgba(0,0,0,.2);';
    document.body.appendChild(t);
  }
  return t;
}
function showTooltipAt(e, html){
  const t = getTooltip();
  t.innerHTML = html;
  t.style.opacity = '1';
  positionTooltip(e, t);
}
function positionTooltip(e, t){
  const margin = 10;
  let x = e.clientX + margin;
  let y = e.clientY - 32;
  const tw = t.offsetWidth || 80;
  if(x + tw > window.innerWidth - 8) x = e.clientX - tw - margin;
  if(y < 8) y = e.clientY + margin;
  t.style.left = x + 'px';
  t.style.top  = y + 'px';
}
function hideTooltip(){ getTooltip().style.opacity='0';
  // Hide all crosshairs
  document.querySelectorAll('[id$="-cross"]').forEach(l=>l.setAttribute('opacity','0'));
}

function renderVitalChart(data, type, color, fillColor, unit){
  const W=100, H=44, padX=8, padY=6, labelH=12;
  const vals = data.map(d=>d.v);
  const dataMin = Math.min(...vals);
  const dataMax = Math.max(...vals);
  const id = 'vc-'+Math.random().toString(36).slice(2,7);

  if(type==='weight'){
    const range = dataMax - dataMin || 1;
    const slotW = (W - padX*2) / vals.length;
    const barW = slotW - 3;
    const bars = vals.map((v,i)=>{
      const x = padX + i*slotW + 1.5;
      const pct = (v-dataMin)/range;
      const barH = Math.max(4, pct*(H-padY*2)+6);
      const y = H - padY - barH;
      const isLast = i===vals.length-1;
      const cx = x + barW/2;
      return `
        <rect x="${x}" y="${y}" width="${barW}" height="${barH}" rx="2"
          fill="${isLast?color:fillColor}" stroke="${color}" stroke-width="0.5" opacity="${isLast?1:0.65}"/>
        <text x="${cx}" y="${H+labelH-1}" text-anchor="middle" font-size="5.5" fill="#94a3b8" font-family="DM Sans,sans-serif">${data[i].d}</text>
        <rect class="chart-hit" x="${x-1}" y="${padY}" width="${barW+2}" height="${H-padY}" rx="2" fill="transparent"
          data-label="${data[i].d}" data-val="${v}" data-unit="${unit}" data-color="${isLast?color:'#4338ca'}"
          onmouseenter="handleChartHover(event,this)" onmouseleave="hideTooltip()"
          style="cursor:crosshair;"/>`;
    }).join('');
    return `<svg id="${id}" viewBox="0 0 ${W} ${H+labelH}" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;display:block;overflow:visible;">${bars}</svg>`;
  } else {
    const nr = NORMAL_RANGES[type];
    const allVals = nr ? [...vals, nr.min, nr.max] : vals;
    const vMin = Math.min(...allVals)*0.993;
    const vMax = Math.max(...allVals)*1.007;
    const range = vMax - vMin || 1;
    const toY = v => H - padY - ((v-vMin)/range)*(H-padY*2);
    const toX = i => padX + (i/(vals.length-1))*(W-padX*2);

    let normalZone = '';
    if(nr){
      const ny1=toY(nr.max), ny2=toY(nr.min);
      normalZone = `<rect x="${padX}" y="${ny1}" width="${W-padX*2}" height="${ny2-ny1}" fill="${fillColor}" opacity="0.45" rx="1"/>
        <line x1="${padX}" y1="${ny1}" x2="${W-padX}" y2="${ny1}" stroke="${color}" stroke-width="0.35" stroke-dasharray="2,2" opacity="0.4"/>
        <line x1="${padX}" y1="${ny2}" x2="${W-padX}" y2="${ny2}" stroke="${color}" stroke-width="0.35" stroke-dasharray="2,2" opacity="0.4"/>`;
    }

    const pts = vals.map((v,i)=>`${toX(i)},${toY(v)}`);
    const pathD = `M ${pts.join(' L ')}`;

    const dots = vals.map((v,i)=>{
      const out = nr && (v < nr.min || v > nr.max);
      const isLast = i===vals.length-1;
      const r = isLast ? 1.8 : out ? 2 : 1.2;
      const fill = out ? '#dc2626' : color;
      const op = isLast||out ? 1 : 0.65;
      return `<circle cx="${toX(i)}" cy="${toY(v)}" r="${r}" fill="${fill}" opacity="${op}"/>`;
    }).join('');

    // Invisible wide hit zones per point
    const hitZones = vals.map((v,i)=>{
      const out = nr && (v<nr.min||v>nr.max);
      const hintColor = out?'#dc2626':color;
      const slotW = (W-padX*2)/(vals.length-1||1);
      const hx = i===0 ? padX : toX(i)-slotW/2;
      const hw = i===0||i===vals.length-1 ? slotW/2 : slotW;
      return `<rect class="chart-hit" x="${hx}" y="${padY}" width="${hw}" height="${H-padY}"
        fill="transparent" data-label="${data[i].d}" data-val="${v}" data-unit="${unit}" data-color="${hintColor}"
        onmouseenter="handleChartHover(event,this,${toX(i)},${toY(v)})" onmouseleave="hideTooltip()"
        style="cursor:crosshair;"/>`;
    }).join('');

    const labels = [0, vals.length-1].map(i=>
      `<text x="${toX(i)}" y="${H+labelH-1}" text-anchor="${i===0?'start':'end'}" font-size="5.5" fill="#94a3b8" font-family="DM Sans,sans-serif">${data[i].d}</text>`
    ).join('');

    return `<svg id="${id}" viewBox="0 0 ${W} ${H+labelH}" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;display:block;overflow:visible;">
      ${normalZone}
      <path d="${pathD}" fill="none" stroke="${color}" stroke-width="0.9" stroke-linecap="round" stroke-linejoin="round"/>
      <line id="${id}-cross" x1="0" y1="${padY}" x2="0" y2="${H}" stroke="${color}" stroke-width="0.5" stroke-dasharray="2,2" opacity="0" pointer-events="none"/>
      ${dots}
      ${labels}
      ${hitZones}
    </svg>`;
  }
}

function handleChartHover(e, el, crossX, crossY){
  const label = el.dataset.label;
  const val   = el.dataset.val;
  const unit  = el.dataset.unit;
  const color = el.dataset.color;
  const svg = el.closest('svg');
  const cross = svg ? svg.querySelector('[id$="-cross"]') : null;
  if(cross && crossX!==undefined){
    cross.setAttribute('x1', crossX);
    cross.setAttribute('x2', crossX);
    cross.setAttribute('opacity', '0.5');
  }
  showTooltipAt(e, `<span style="color:${color};margin-right:4px;">●</span>${label} · <strong>${val}</strong> ${unit}`);
}

// Legacy sparkline
function sparkline(data, color='#4338ca', fillColor='#eef2ff'){
  return renderVitalChart(data, 'weight', color, fillColor, 'kg');
}

// ==============================
// STATUS CONFIG
// ==============================
const STATUS_CFG = {
  alive:    {label:'Vivant',   cls:'status-alive',    badge:'b-ok',       icon:'🐾'},
  deceased: {label:'Décédé',   cls:'status-deceased', badge:'b-deceased', icon:'🕊️'},
  missing:  {label:'Disparu',  cls:'status-missing',  badge:'b-missing',  icon:'🔍'},
  given:    {label:'Cédé',     cls:'status-given',    badge:'b-given',    icon:'🏠'},
};

// ==============================
// BUILD TABS + OWNER LIST
// ==============================
function buildTabs(){
  const el = document.getElementById('animal-tabs');
  el.innerHTML = ANIMALS.map((a,i)=>{
    const sc = STATUS_CFG[a.status];
    const vaccAlert = a.vaccins.some(v=>v.status==='alert');
    const vaccWarn  = a.vaccins.some(v=>v.status==='warn');
    const statusBadge = a.status!=='alive'
      ? `<span class="tab-badge" style="background:${a.status==='deceased'?'#1e293b':'#fef3c7'};color:${a.status==='deceased'?'#e2e8f0':'#92400e'};">${sc.icon}</span>`
      : vaccAlert ? `<span class="tab-badge" style="background:#fef2f2;color:#dc2626;">⚠</span>`
      : vaccWarn  ? `<span class="tab-badge" style="background:#fff7ed;color:#d97706;">!</span>` : '';
    return `<div class="animal-tab${i===0?' active':''}" id="tab-${i}" onclick="switchTab(${i})">
      <span class="tab-emoji">${a.emoji}</span>
      <div style="display:flex;flex-direction:column;min-width:0;">
        <span class="tab-name">${a.name}</span>
        <span class="tab-breed">${a.breed}</span>
      </div>
      ${statusBadge}
    </div>`;
  }).join('') + `<button class="btn btn-secondary btn-xs" style="margin-left:4px;align-self:center;flex-shrink:0;" onclick="toast('Ajouter un animal…','#4338ca')">
    <svg width="10" height="10" fill="none" viewBox="0 0 12 12"><path d="M6 2v8M2 6h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
  </button>`;
}

function buildAuxContact(a){
  const el = document.getElementById('aux-contact-left');
  if(!el) return;
  if(a.auxContact){
    el.innerHTML = `<div class="phone-chip">
      <svg width="13" height="13" fill="none" viewBox="0 0 16 16" style="color:#4338ca;flex-shrink:0;"><circle cx="8" cy="5" r="2.8" stroke="currentColor" stroke-width="1.3"/><path d="M2.5 14c0-3 2.5-5 5.5-5s5.5 2 5.5 5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      <div style="flex:1;min-width:0;">
        <p style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${a.auxContact.first} ${a.auxContact.last}</p>
        <p style="font-size:var(--text-xs);color:var(--text-subtle);">${a.auxContact.phone} · ${a.auxContact.rel}</p>
      </div>
      <button class="btn btn-ghost btn-icon btn-xs" onclick="toast('Appel…','#059669')" style="flex-shrink:0;">
        <svg width="12" height="12" fill="none" viewBox="0 0 16 16"><path d="M3 2h3l1.5 4-2 1.2a10 10 0 004.3 4.3L11 9.5l4 1.5v3a1 1 0 01-1 1C6.2 15 1 9.8 1 3a1 1 0 011-1z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>
      </button>
    </div>
    <p style="font-size:var(--text-xs);color:var(--text-subtle);margin-top:6px;">Contact pour : ${a.name}</p>`;
  } else {
    el.innerHTML = `<p style="font-size:var(--text-sm);color:var(--text-subtle);margin-bottom:8px;">Aucun contact pour <strong>${a.name}</strong>.</p>
      <button class="btn btn-secondary btn-xs" onclick="openModal('modal-auxcontact')">
        <svg width="10" height="10" fill="none" viewBox="0 0 12 12"><path d="M6 2v8M2 6h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>Ajouter
      </button>`;
  }
}

// ==============================
// SWITCH TAB
// ==============================
function switchTab(idx){
  currentAnimal = idx;
  currentVital = 'weight';
  document.querySelectorAll('.animal-tab').forEach((t,i)=>t.classList.toggle('active',i===idx));
  buildAuxContact(ANIMALS[idx]);
  renderAnimal(ANIMALS[idx]);
  document.getElementById('animal-content').scrollTop=0;
}

// ==============================
// RENDER ANIMAL
// ==============================
function renderMediaList(medias, filterType){
  const filtered = filterType && filterType!=='Tous' ? medias.filter(m=>m.type===filterType) : medias;
  if(!filtered.length) return `<p style="font-size:var(--text-sm);color:var(--text-subtle);padding:12px 14px;">Aucun élément</p>`;
  const TYPE_CFG = {
    'Ordonnance':     {badge:'b-blue',   icon:'📋'},
    'Analyse':        {badge:'b-purple', icon:'🧪'},
    'Certificat':     {badge:'b-ok',     icon:'✅'},
    'Compte-rendu':   {badge:'b-grey',   icon:'📄'},
    'Photo':          {badge:'b-cat',    icon:'📷'},
    'Image médicale': {badge:'b-warn',   icon:'🩻'},
  };
  return filtered.map((m,i,arr)=>{
    const cfg = TYPE_CFG[m.type]||{badge:'b-grey',icon:'📄'};
    const isLast = i===arr.length-1;
    if(m.isImage){
      return `<div style="display:flex;align-items:center;gap:10px;padding:7px 14px;${isLast?'':'border-bottom:1px solid #f1f5f9;'}transition:background .12s;cursor:pointer;"
        onmouseenter="this.style.background='#f5f3ff'" onmouseleave="this.style.background='transparent'">
        <div style="width:36px;height:36px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;border:1px solid #e8edf2;">${m.thumb||cfg.icon}</div>
        <div style="flex:1;min-width:0;"><p style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${m.nom}</p><p style="font-size:var(--text-xs);color:var(--text-subtle);">${fmtDate(m.date)}</p></div>
        <span class="badge ${cfg.badge}" style="font-size:var(--text-xs);">${m.type}</span>
        <button class="btn btn-ghost btn-icon btn-xs" onclick="event.stopPropagation();toast('Ouverture image…','#4338ca')" title="Voir">
          <svg width="11" height="11" fill="none" viewBox="0 0 12 12"><circle cx="5" cy="5" r="3.5" stroke="currentColor" stroke-width="1.3"/><path d="M7.5 7.5l2.5 2.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
        </button>
      </div>`;
    } else {
      return `<div style="display:flex;align-items:center;gap:10px;padding:7px 14px;${isLast?'':'border-bottom:1px solid #f1f5f9;'}transition:background .12s;cursor:pointer;"
        onmouseenter="this.style.background='#f5f3ff'" onmouseleave="this.style.background='transparent'">
        <div style="width:36px;height:36px;border-radius:8px;background:#f8fafc;display:flex;align-items:center;justify-content:center;font-size:var(--text-lg);flex-shrink:0;border:1px solid #e8edf2;">${cfg.icon}</div>
        <div style="flex:1;min-width:0;"><p style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${m.nom}</p><p style="font-size:var(--text-xs);color:var(--text-subtle);">${fmtDate(m.date)}</p></div>
        <span class="badge ${cfg.badge}" style="font-size:var(--text-xs);">${m.type}</span>
        <button class="btn btn-ghost btn-icon btn-xs" onclick="event.stopPropagation();toast('Téléchargement…','#4338ca')" title="Télécharger">
          <svg width="11" height="11" fill="none" viewBox="0 0 12 12"><path d="M6 2v7M3 7l3 3 3-3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><path d="M1 10h10" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
        </button>
      </div>`;
    }
  }).join('');
}

function setMediaFilter(btn, animalId){
  const container = btn.closest('[id^="media-filters-"]');
  container.querySelectorAll('.media-filter').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  const filterType = btn.dataset.mfilter;
  const a = ANIMALS.find(a=>String(a.id)===String(animalId));
  if(!a) return;
  document.getElementById('media-list-'+animalId).innerHTML = renderMediaList(a.medias, filterType);
}

function renderAnimal(a){
  const sc = STATUS_CFG[a.status];

  // Status banner
  const banner = a.status!=='alive'
    ? `<div class="status-strip ${sc.cls}" style="margin-bottom:12px;">
        <span style="font-size:var(--text-lg);">${sc.icon}</span>
        <span>Animal ${sc.label.toLowerCase()}</span>
        <button class="btn btn-xs" style="margin-left:auto;background:rgba(255,255,255,.2);color:inherit;border:1px solid rgba(255,255,255,.3);" onclick="openModal('modal-status')">Modifier</button>
       </div>` : '';

  // Media filter buttons
  const mediaTypes = ['Tous',...new Set(a.medias.map(m=>m.type))];
  const mediaFilterBtns = mediaTypes.map((t,i)=>
    `<button class="media-filter${i===0?' active':''}" data-mfilter="${t}" onclick="setMediaFilter(this,'${a.id}')">${t} <span style="opacity:.6;">${t==='Tous'?a.medias.length:a.medias.filter(m=>m.type===t).length}</span></button>`
  ).join('');

  document.getElementById('animal-content').innerHTML = `
    ${banner}

    <div class="widget-row-1" style="display:grid;grid-template-columns:4fr 6fr;gap:12px;margin-bottom:12px;align-items:stretch;">

      <!-- Animal identity — 40% -->
      <div class="widget">
        <div class="wh" style="height:48px;">
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:9px;background:#f5f3ff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;cursor:pointer;border:1.5px dashed #c7d2fe;" onclick="toast('Changer la photo…','#4338ca')">${a.emoji}</div>
            <div>
              <div style="display:flex;align-items:center;gap:6px;">
                <h2 style="font-size:var(--text-base);font-weight:var(--weight-bold);color:var(--text-primary);">${a.name}</h2>
                <span class="badge b-cat" style="font-size:var(--text-xs);">${a.species==='Félin'?'Chat':'Chien'}</span>
                <span class="badge ${STATUS_CFG[a.status].badge}" style="font-size:var(--text-xs);">${STATUS_CFG[a.status].label}</span>
              </div>
              <p style="font-size:var(--text-xs);color:var(--text-subtle);">${a.breed} · ${a.sex==='F'?'♀':'♂'}${a.sterilized?' · Stérilisée':''}</p>
            </div>
          </div>
          <div style="display:flex;gap:4px;">
            <button class="btn btn-secondary btn-xs" onclick="openModal('modal-status')">Statut</button>
            <button class="btn btn-secondary btn-xs" onclick="toast('Édition…','#4338ca')"><svg width="10" height="10" fill="none" viewBox="0 0 12 12"><path d="M8 2l2 2-6 6H2V8l6-6z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg></button>
          </div>
        </div>
        <div class="wb" style="padding:8px 14px;">
          <div style="display:flex;flex-direction:column;gap:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f8fafc;">
              <span style="font-size:var(--text-xs);color:var(--text-subtle);flex-shrink:0;min-width:90px;">Naissance</span>
              <span style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);text-align:right;">${fmtDate(a.dob)}</span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f8fafc;">
              <span style="font-size:var(--text-xs);color:var(--text-subtle);flex-shrink:0;min-width:90px;">Poids</span>
              <span style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${a.weight} kg</span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f8fafc;">
              <span style="font-size:var(--text-xs);color:var(--text-subtle);flex-shrink:0;min-width:90px;">Sexe</span>
              <span style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${a.sex==='F'?'Femelle':'Mâle'}${a.sterilized?' · Stérilisée':''}</span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f8fafc;">
              <span style="font-size:var(--text-xs);color:var(--text-subtle);flex-shrink:0;min-width:90px;">Robe</span>
              <span style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${a.color}</span>
            </div>
            ${a.chip?`<div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f8fafc;"><span style="font-size:var(--text-xs);color:var(--text-subtle);flex-shrink:0;min-width:90px;">Transpondeur</span><span style="font-size:var(--text-xs);font-weight:var(--weight-medium);color:var(--text-primary);font-family:monospace;">${a.chip}</span></div>`:''}
            ${a.tattoo?`<div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f8fafc;"><span style="font-size:var(--text-xs);color:var(--text-subtle);flex-shrink:0;min-width:90px;">Tatouage</span><span style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);font-family:monospace;">${a.tattoo}</span></div>`:''}
            ${a.passport?`<div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f8fafc;"><span style="font-size:var(--text-xs);color:var(--text-subtle);flex-shrink:0;min-width:90px;">Passeport</span><span style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${a.passport}</span></div>`:''}
            ${a.loof?`<div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f8fafc;"><span style="font-size:var(--text-xs);color:var(--text-subtle);flex-shrink:0;min-width:90px;">LOOF / LOF</span><span style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${a.loof}</span></div>`:''}
            ${a.sire?`<div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f8fafc;"><span style="font-size:var(--text-xs);color:var(--text-subtle);flex-shrink:0;min-width:90px;">SIRE</span><span style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${a.sire}</span></div>`:''}
            ${a.crossBreed?`<div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;"><span style="font-size:var(--text-xs);color:var(--text-subtle);flex-shrink:0;min-width:90px;">Croisement</span><span style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${a.crossBreed}</span></div>`:''}
          </div>
        </div>
      </div>

      <!-- Vital signs — 60%: 4 mini charts side by side -->
      <div class="widget" style="display:flex;flex-direction:column;">
        <div class="wh" style="height:48px;">
          <span class="wt">Suivi de santé</span>
          <span style="font-size:var(--text-xs);color:var(--text-subtle);">6 dernières mesures</span>
        </div>
        <div class="vital-grid" style="flex:1;display:grid;">
          ${[
            {key:'weight', label:'Poids',  unit:'kg',   color:'#4338ca', fill:'#eef2ff', type:'weight', data:a.weights.map(w=>({d:w.d,v:parseFloat(w.v)}))},
            {key:'temp',   label:'Temp.',  unit:'°C',   color:'#dc2626', fill:'#fef2f2', type:'temp',   data:a.vitals.temp},
            {key:'fc',     label:'FC',     unit:'bpm',  color:'#0891b2', fill:'#ecfeff', type:'fc',     data:a.vitals.fc},
            {key:'fr',     label:'FR',     unit:'/min', color:'#059669', fill:'#ecfdf5', type:'fr',     data:a.vitals.fr},
          ].map((v,i)=>{
            const vals=v.data.map(d=>d.v);
            const last=vals[vals.length-1];
            const prev=vals[vals.length-2];
            const trend=last>prev?'↑':last<prev?'↓':'→';
            const trendColor=last>prev?'#dc2626':last<prev?'#059669':'#94a3b8';
            return `<div style="border-left:${i>0?'1px solid #f1f5f9':'none'};padding:10px 12px;display:flex;flex-direction:column;gap:4px;">
              <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:var(--text-xs);font-weight:var(--weight-semibold);color:var(--text-subtle);text-transform:uppercase;letter-spacing:.06em;">${v.label}</span>
                <span style="font-size:var(--text-sm);font-weight:var(--weight-bold);color:${trendColor};">${trend}</span>
              </div>
              <div style="display:flex;align-items:baseline;gap:3px;">
                <span style="font-size:20px;font-weight:var(--weight-semibold);color:${v.color};line-height:1;letter-spacing:-.5px;">${last}</span>
                <span style="font-size:var(--text-xs);color:var(--text-subtle);font-weight:var(--weight-normal);">${v.unit}</span>
              </div>
              <div style="flex:1;min-height:48px;">${renderVitalChart(v.data, v.type, v.color, v.fill, v.unit)}</div>
            </div>`;
          }).join('')}
        </div>
      </div>

    </div>

    <!-- Row 1: vaccinations, treatments, consultations -->
    <div class="widget-row-2" style="display:grid;grid-template-columns:1fr 1fr 2fr;gap:12px;margin-bottom:12px;">

      <div class="widget">
        <div class="wh"><span class="wt">Vaccinations</span><button class="btn btn-secondary btn-xs" onclick="toast('Vaccinations…','#0891b2')">Voir tout</button></div>
        <div style="max-height:${Math.min(a.vaccins.length,4)*44}px;overflow-y:auto;overflow-x:hidden;padding:0 12px;">
          ${a.vaccins.slice(0,4).map(v=>{
            const dot=v.status==='alert'?'#dc2626':v.status==='warn'?'#f59e0b':'#16a34a';
            const badge=v.status==='alert'?'<span class="badge b-alert" style="font-size:var(--text-xs);">Expiré</span>':v.status==='warn'?'<span class="badge b-warn" style="font-size:var(--text-xs);">À renouveler</span>':'<span class="badge b-ok" style="font-size:var(--text-xs);">À jour</span>';
            return `<div class="trow" style="transition:background .12s;padding:6px 0;margin:0 -12px;padding-left:12px;padding-right:12px;" onmouseenter="this.style.background='#f5f3ff'" onmouseleave="this.style.background='transparent'"><div style="width:7px;height:7px;border-radius:50%;background:${dot};flex-shrink:0;"></div><div style="flex:1;min-width:0;"><p style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${v.name}</p><p style="font-size:var(--text-xs);color:var(--text-subtle);">Fait ${fmtDate(v.date)} · Prochain ${fmtDate(v.next)}</p></div>${badge}</div>`;
          }).join('')}
        </div>
      </div>

      <div class="widget">
        <div class="wh"><span class="wt">Traitements</span><button class="btn btn-secondary btn-xs" onclick="toast('Traitements…','#7c3aed')">Voir tout</button></div>
        <div style="padding:0 12px;">
          ${a.traitements.length ? a.traitements.slice(0,4).map(t=>`<div class="trow" style="transition:background .12s;margin:0 -12px;padding:6px 12px;" onmouseenter="this.style.background='#f5f3ff'" onmouseleave="this.style.background='transparent'">
            <div style="width:7px;height:7px;border-radius:50%;background:#4338ca;flex-shrink:0;"></div>
            <div style="flex:1;min-width:0;"><p style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${t.nom} <span class="badge b-blue" style="font-size:var(--text-xs);">${t.dose}</span></p><p style="font-size:var(--text-xs);color:var(--text-subtle);">${fmtDate(t.debut)} → ${fmtDate(t.fin)} · ${t.vet}</p></div>
            <button class="btn btn-xs" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;flex-shrink:0;" onclick="toast('Arrêt traitement…','#dc2626')">Arrêter</button>
          </div>`).join('') : `<p style="font-size:var(--text-sm);color:var(--text-subtle);padding:4px 0;">Aucun traitement en cours</p>`}
        </div>
      </div>

      <!-- Consultation history — 2 columns wide -->
      <div class="widget">
        <div class="wh">
          <span class="wt">Historique des consultations (${a.consultations.length})</span>
          <button class="btn btn-secondary btn-xs" onclick="toast('Voir tout…','#4338ca')">Voir tout</button>
        </div>
        <div id="consult-scroll-${a.id}" style="overflow-y:auto;overflow-x:hidden;">
          ${a.consultations.map((c,i,arr)=>`
            <div style="display:flex;gap:10px;padding:10px 14px;${i===arr.length-1?'':'border-bottom:1px solid #f1f5f9;'}transition:background .12s;align-items:center;cursor:default;"
              onmouseenter="this.style.background='#f5f3ff'" onmouseleave="this.style.background='transparent'">
              <div style="width:10px;height:10px;border-radius:50%;background:${i===0?'#4338ca':'#e2e8f0'};flex-shrink:0;"></div>
              <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:2px;">
                  <span style="font-size:var(--text-md);font-weight:var(--weight-semibold);color:var(--text-primary);">${c.motif}</span>
                  <span class="badge b-grey" style="font-size:var(--text-xs);">${c.vet}</span>
                  <span style="font-size:var(--text-xs);color:var(--text-subtle);margin-left:auto;">${fmtDate(c.date)}</span>
                </div>
                <p style="font-size:var(--text-sm);color:var(--text-muted);line-height:1.4;margin-bottom:4px;">${c.note}</p>
                <div style="display:flex;gap:3px;flex-wrap:wrap;">${c.actes.map(ac=>`<span class="badge b-grey" style="font-size:var(--text-xs);">${ac}</span>`).join('')}</div>
              </div>
              <button class="btn btn-secondary" style="font-size:var(--text-xs);padding:3px 9px;flex-shrink:0;margin-left:8px;" onclick="toast('Ouverture consultation…','#4338ca')">Ouvrir</button>
            </div>`).join('')}
        </div>
      </div>

    </div>

    <!-- Row 2: Media & Documents — Billing -->
    <div class="widget-row-3" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;align-items:stretch;">

      <!-- Media & Documents merged -->
      <div class="widget" style="display:flex;flex-direction:column;overflow:hidden;">
        <div class="wh">
          <div style="display:flex;align-items:center;gap:7px;">
            <span class="wt">Médias & Documents</span>
            <span style="font-size:var(--text-xs);font-weight:var(--weight-semibold);padding:1px 7px;border-radius:20px;background:#eef2ff;color:#4338ca;">${a.medias.length}</span>
          </div>
          <button class="btn btn-secondary btn-xs" onclick="toast('Voir tout…','#4338ca')">Voir tout</button>
        </div>
        <!-- Type filters -->
        <div style="display:flex;gap:4px;padding:8px 14px 6px;flex-wrap:wrap;border-bottom:1px solid #f1f5f9;" id="media-filters-${a.id}">
          ${mediaFilterBtns}
        </div>
        <!-- Item list -->
        <div style="flex:1;overflow-y:auto;max-height:156px;" id="media-list-${a.id}">
          ${renderMediaList(a.medias, null)}
        </div>
      </div>

      <!-- Billing -->
      <div class="widget" style="overflow:hidden;display:flex;flex-direction:column;">
        <div class="wh" style="flex-shrink:0;">
          <span class="wt">Facturation — ${a.name}</span>
          <button class="btn btn-secondary btn-xs" onclick="toast('Historique complet…','#4338ca')">Voir tout</button>
        </div>
        <div id="fact-scroll-${a.id}" style="overflow-y:auto;overflow-x:hidden;">
          ${a.facturation.map((f,fi,arr)=>{
            const isOk=f.statut==='ok', isPending=f.statut==='pending';
            const isLast=fi===arr.length-1;
            const badgeCls=isOk?'b-ok':isPending?'b-warn':'b-alert';
            const badgeLabel=isOk?'Payé':isPending?'En attente':'Impayé';
            const action=isOk
              ? `<button class="btn btn-ghost btn-xs" style="flex-shrink:0;" onclick="toast('Voir la facture…','#4338ca')" title="Voir la facture"><svg width="11" height="11" fill="none" viewBox="0 0 12 12"><path d="M4 2H3a1 1 0 00-1 1v7a1 1 0 001 1h6a1 1 0 001-1V7" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><path d="M7 1h4v4M11 1L6 6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg></button>`
              : isPending
              ? `<button class="btn btn-ghost btn-xs" style="flex-shrink:0;color:#d97706;" onclick="toast('Envoi relance…','#f59e0b')" title="Envoyer une relance"><svg width="11" height="11" fill="none" viewBox="0 0 12 12"><path d="M2 2l8 4-8 4V7.5l6-1.5-6-1.5V2z" fill="currentColor" opacity=".8"/></svg></button>`
              : `<button class="btn btn-ghost btn-xs" style="flex-shrink:0;color:#dc2626;" onclick="toast('Marquer payé…','#059669')" title="Marquer comme payé"><svg width="11" height="11" fill="none" viewBox="0 0 12 12"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>`;
            return `<div style="display:flex;align-items:flex-start;gap:10px;padding:9px 14px;${isLast?'':'border-bottom:1px solid #f1f5f9;'}transition:background .12s;cursor:default;"
              onmouseenter="this.style.background='#f5f3ff'" onmouseleave="this.style.background='transparent'">
              <!-- Date column -->
              <div style="flex-shrink:0;width:44px;text-align:center;padding-top:1px;">
                <p style="font-size:var(--text-md);font-weight:var(--weight-bold);color:var(--text-primary);line-height:1;">${new Date(f.date).getDate()}</p>
                <p style="font-size:var(--text-xs);color:var(--text-subtle);text-transform:uppercase;">${new Date(f.date).toLocaleDateString('fr-FR',{month:'short'})}</p>
                <p style="font-size:9px;color:#cbd5e1;">${new Date(f.date).getFullYear()}</p>
              </div>
              <!-- Separator -->
              <div style="width:1px;background:#f1f5f9;align-self:stretch;flex-shrink:0;"></div>
              <!-- Content -->
              <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;flex-wrap:wrap;">
                  <p style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:var(--text-primary);">${f.libelle}</p>
                  <span class="badge ${badgeCls}" style="font-size:var(--text-xs);">${badgeLabel}</span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                  <span style="font-size:var(--text-xs);color:var(--text-muted);">${f.vet}</span>
                  <span style="color:#e2e8f0;font-size:var(--text-xs);">·</span>
                  <span style="font-size:var(--text-xs);color:var(--text-subtle);font-family:monospace;">${f.ref}</span>
                </div>
                <div style="display:flex;gap:3px;margin-top:4px;flex-wrap:wrap;">
                  ${f.actes.map(ac=>`<span style="font-size:var(--text-xs);padding:1px 6px;border-radius:10px;background:#f1f5f9;color:var(--text-muted);">${ac}</span>`).join('')}
                </div>
              </div>
              <!-- Amount + action -->
              <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;">
                <p style="font-size:var(--text-md);font-weight:var(--weight-medium);color:var(--text-secondary);">${f.montant} €</p>
                ${action}
              </div>
            </div>`;
          }).join('')}
        </div>
      </div>

    </div>

  `;
  // Cap billing section to 3 visible rows
  requestAnimationFrame(()=>{
    const factEl = document.getElementById('fact-scroll-'+a.id);
    if(factEl){
      const rows = factEl.children;
      if(rows.length > 3){
        let h=0; for(let i=0;i<3;i++) h+=rows[i].offsetHeight;
        factEl.style.maxHeight = h+'px';
      }
    }
    const consultEl = document.getElementById('consult-scroll-'+a.id);
    if(consultEl){
      const rows = consultEl.children;
      if(rows.length > 2){
        let h=0; for(let i=0;i<2;i++) h+=rows[i].offsetHeight;
        consultEl.style.maxHeight = h+'px';
      }
    }
  });
}

// ==============================
// VITAL CHART SWITCH
// ==============================
function setVital(v){
  currentVital = v;
  const a = ANIMALS[currentAnimal];
  document.querySelectorAll('.vt-tab').forEach(t=>{
    t.classList.toggle('active', t.dataset.vital===v);
  });
  const vitalData = v==='weight' ? a.weights.map(w=>({d:w.d,v:parseFloat(w.v)})) : a.vitals[v];
  const colors = {weight:'#4338ca',temp:'#dc2626',fc:'#0891b2',fr:'#059669'};
  const fills  = {weight:'#eef2ff',temp:'#fef2f2',fc:'#ecfeff',fr:'#ecfdf5'};
  document.getElementById('vital-chart-wrap').innerHTML = sparkline(vitalData, colors[v], fills[v]);
}

// Event listeners stored for cleanup
let _mousemoveHandler = null;

export function init() {
  // Expose functions used by onclick attributes in HTML
  window.toggleSidebar = toggleSidebar;
  window.toggleOwnerDrawer = toggleOwnerDrawer;
  window.closeAllDrawers = closeAllDrawers;
  window.toast = toast;
  window.openModal = openModal;
  window.closeModal = closeModal;
  window.updateStatusNote = updateStatusNote;
  window.switchTab = switchTab;
  window.setMediaFilter = setMediaFilter;
  window.setVital = setVital;
  window.handleChartHover = handleChartHover;
  window.hideTooltip = hideTooltip;

  // Track mousemove for tooltip positioning
  _mousemoveHandler = e=>{
    const t = document.getElementById('chart-tooltip');
    if(t && t.style.opacity==='1') positionTooltip(e, t);
  };
  document.addEventListener('mousemove', _mousemoveHandler);

  // Initialize
  buildTabs();
  buildAuxContact(ANIMALS[0]);
  renderAnimal(ANIMALS[0]);
}

export function cleanup() {
  if (_mousemoveHandler) {
    document.removeEventListener('mousemove', _mousemoveHandler);
    _mousemoveHandler = null;
  }
  // Remove tooltip element if present
  const tooltip = document.getElementById('chart-tooltip');
  if (tooltip) tooltip.remove();
}
