/**
 * Consultation cockpit — interactions faithful to the vetos-consultation.html layout.
 * No invention: everything comes from the mockup's wiring and functions.
 */

import { toast as kivetoToast } from 'kiveto/toast';

// ══════════════════════════════════════════════
//  MOCK DATA (identical to mockup)
// ══════════════════════════════════════════════
const MEDICATIONS = [
  { code: 'MED-001', name: 'Méloxicam 1,5 mg/mL',      cls: 'AINS',              form: 'sol. buvable',  dose: 0.1,  doseUnit: 'mg/kg', route: 'per os', freq: '1×/j',    days: 7,  price: 14.80 },
  { code: 'MED-002', name: 'Carprofène 50 mg',           cls: 'AINS',              form: 'comprimés',     dose: 2,    doseUnit: 'mg/kg', route: 'per os', freq: '1×/j',    days: 7,  price: 18.50 },
  { code: 'MED-003', name: 'Cartrophen Vet 100 mg/mL',   cls: 'Chondroprotecteur', form: 'injectable',    dose: 3,    doseUnit: 'mg/kg', route: 'SC',     freq: '1×/sem',  days: 28, price: 38.50 },
  { code: 'MED-004', name: 'Amoxicilline 500 mg',        cls: 'Antibiotique',      form: 'comprimés',     dose: 20,   doseUnit: 'mg/kg', route: 'per os', freq: '2×/j',    days: 7,  price: 22.00, contra: ['Pénicilline'] },
  { code: 'MED-005', name: 'Marbofloxacine 50 mg',       cls: 'Antibiotique',      form: 'comprimés',     dose: 2,    doseUnit: 'mg/kg', route: 'per os', freq: '1×/j',    days: 7,  price: 28.50 },
  { code: 'MED-006', name: 'Doxycycline 100 mg',         cls: 'Antibiotique',      form: 'comprimés',     dose: 5,    doseUnit: 'mg/kg', route: 'per os', freq: '1×/j',    days: 14, price: 16.00 },
  { code: 'MED-007', name: 'Tramadol 50 mg',             cls: 'Antalgique',        form: 'comprimés',     dose: 4,    doseUnit: 'mg/kg', route: 'per os', freq: '3×/j',    days: 5,  price: 12.00 },
  { code: 'MED-008', name: 'Gabapentine 100 mg',         cls: 'Antalgique',        form: 'gélules',       dose: 10,   doseUnit: 'mg/kg', route: 'per os', freq: '2×/j',    days: 14, price: 19.50 },
  { code: 'MED-009', name: 'Furosémide 40 mg',           cls: 'Diurétique',        form: 'comprimés',     dose: 2,    doseUnit: 'mg/kg', route: 'per os', freq: '2×/j',    days: 30, price: 9.80  },
  { code: 'MED-010', name: 'Apoquel 16 mg',              cls: 'Antiprurigineux',   form: 'comprimés',     dose: 0.5,  doseUnit: 'mg/kg', route: 'per os', freq: '2×/j',    days: 14, price: 56.00 },
  { code: 'MED-011', name: 'Frontline Combo Spot-On',    cls: 'Antiparasitaire',   form: 'pipettes',      dose: 1,    doseUnit: 'pipette',route: 'cutané', freq: '1×/mois', days: 30, price: 11.50 },
  { code: 'MED-012', name: 'Smecta poudre orale',        cls: 'Gastro-entérologie',form: 'sachets',       dose: 1,    doseUnit: 'sachet', route: 'per os', freq: '3×/j',   days: 3,  price: 4.50  },
];

const DIAGNOSTICS = [
  { code: 'M.LOC.21',  name: 'Arthrose grasset, stade précoce',     system: 'Locomoteur' },
  { code: 'M.LOC.18',  name: 'Rupture partielle ligament croisé',   system: 'Locomoteur' },
  { code: 'M.LOC.25',  name: 'Dysplasie de la hanche',              system: 'Locomoteur' },
  { code: 'M.LOC.30',  name: 'Tendinite postérieure',               system: 'Locomoteur' },
  { code: 'M.LOC.05',  name: 'Contracture musculaire',              system: 'Locomoteur' },
  { code: 'M.CARDIO.01',name:'Insuffisance cardiaque congestive',   system: 'Cardiovasculaire' },
  { code: 'M.CARDIO.05',name:'Souffle cardiaque grade 2',           system: 'Cardiovasculaire' },
  { code: 'M.DERMA.10',name: 'Pyodermite superficielle',            system: 'Cutané' },
  { code: 'M.DERMA.15',name: 'Allergie atopique',                   system: 'Cutané' },
  { code: 'M.DERMA.20',name: 'Dermatite par allergie aux puces',    system: 'Cutané' },
  { code: 'M.DIG.05',  name: 'Gastro-entérite aiguë',               system: 'Digestif' },
  { code: 'M.DIG.10',  name: 'Pancréatite',                         system: 'Digestif' },
  { code: 'M.URO.03',  name: 'Cystite bactérienne',                 system: 'Urogénital' },
  { code: 'M.NEU.01',  name: 'Hernie discale',                      system: 'Neurologique' },
  { code: 'M.PREV.01', name: 'Vaccination de routine',              system: 'Préventif' },
];

const VITAL_TYPES = [
  { id: 'temperature', label: 'Température',        unit: '°C',   range: '38,0–39,2', def: '38,5' },
  { id: 'fc',          label: 'Fréq. cardiaque',    unit: 'bpm',  range: '70–120',    def: '90' },
  { id: 'fr',          label: 'Fréq. respiratoire', unit: 'rpm',  range: '15–30',     def: '20' },
  { id: 'sc',          label: 'Score corporel',     unit: '/9',   range: 'Idéal 4–5', def: '5' },
  { id: 'douleur',     label: 'Score douleur',      unit: '/4',   range: '0 = aucune',def: '0' },
  { id: 'trc',         label: 'TRC',                unit: 's',    range: '< 2 s',     def: '<2' },
  { id: 'muqueuses',   label: 'Muqueuses',          unit: '',     range: 'Roses',     def: 'Roses' },
  { id: 'tension',     label: 'Tension artérielle', unit: 'mmHg', range: '120/80',    def: '125/80' },
  { id: 'glycemie',    label: 'Glycémie',           unit: 'mmol/L',range: '4–7',     def: '5,2' },
];

const PRESET_MOTIFS = [
  { category: 'Médecine préventive', items: ['Vaccination', 'Vermifugation', 'Bilan annuel', 'Identification (puce)', 'Stérilisation'] },
  { category: 'Pathologie',          items: ['Boiterie', 'Vomissements', 'Diarrhée', 'Toux', 'Prurit', 'Otite'] },
  { category: 'Soins',               items: ['Toilettage', 'Détartrage', 'Pansement', 'Retrait de fil'] },
  { category: 'Suivi',               items: ['Suivi post-op', 'Recontrôle', 'Suivi chronique'] },
  { category: 'Urgence',             items: ['Urgence vitale', 'Traumatisme', 'Intoxication'] },
];

const PLAN_TEMPLATES = [
  { id: 'tpl-1', name: 'Vaccination annuelle (chien)', description: 'CHPLR + examen + rappel',
    items: [{ text: 'Vaccination CHPLR sous-cutanée', meta: 'acte' }, { text: 'Examen clinique général', meta: 'acte' }, { text: 'Rappel vaccin dans 12 mois', meta: 'RDV' }, { text: 'Vermifugation orale', meta: 'acte' }] },
  { id: 'tpl-2', name: 'Suivi arthrose', description: 'AINS + chondroprotecteur + recontrôle',
    items: [{ text: 'AINS 7 j + repos forcé 10 j', meta: '→ ordonnance' }, { text: 'Chondroprotecteur cure 4 semaines', meta: '→ ordonnance' }, { text: 'Recontrôle dans 14 jours', meta: 'RDV' }, { text: 'Conseil nutritionnel : aliment joints care', meta: 'conseil' }] },
  { id: 'tpl-3', name: 'Post-stérilisation', description: 'Antibio + antalgique + retrait points',
    items: [{ text: 'Antibiotique 7 j', meta: '→ ordonnance' }, { text: 'Antalgique 5 j', meta: '→ ordonnance' }, { text: 'Collerette 10 j', meta: 'matériel' }, { text: 'Retrait des points à J+10', meta: 'RDV' }] },
  { id: 'tpl-4', name: 'Bilan pré-anesthésique', description: 'Bilan sanguin + ECG + à jeun',
    items: [{ text: 'Bilan sanguin pré-op (NFS, biochimie)', meta: 'acte' }, { text: 'ECG de repos', meta: 'acte' }, { text: 'Mise à jeun 12h avant intervention', meta: 'consigne' }] },
];

const PLAN_TYPES = [
  { id: 'acte',    icon: '🩺', label: 'Acte médical',            meta: 'acte' },
  { id: 'examen',  icon: '🔬', label: 'Examen complémentaire',   meta: 'examen' },
  { id: 'medic',   icon: '💊', label: 'Médicament (ordonnance)', meta: '→ ordonnance', special: 'medication' },
  { id: 'conseil', icon: '💬', label: 'Conseil propriétaire',    meta: 'conseil' },
  { id: 'rdv',     icon: '📅', label: 'Recontrôle / RDV',        meta: 'RDV' },
  { id: 'consigne',icon: '📋', label: 'Consigne',                meta: 'consigne' },
];

const CONFIDENCE = {
  certain:  { key: 'certain',  label: 'certain',    description: 'Établi par examen ou test' },
  probable: { key: 'probable', label: 'probable',   description: 'Hypothèse principale retenue' },
  possible: { key: 'possible', label: 'possible',   description: 'À confirmer par examens' },
  ecarter:  { key: 'ecarter',  label: 'à écarter',  description: 'Différentiel à exclure' },
};
const CONFIDENCE_ORDER = ['certain', 'probable', 'possible', 'ecarter'];

const PAST_CONSULTATIONS = [
  { date: '24 janv. 2026', motif: 'Bilan annuel + vaccin',     summary: 'Vaccination CHPLR. Poids stable 28,8 kg. RAS clinique.' },
  { date: '12 sept. 2025', motif: 'Boiterie postérieure',      summary: 'Première suspicion arthrose. AINS 5 j. À surveiller.' },
  { date: '03 mars 2025',  motif: 'Vermifugation',             summary: 'Drontal Plus. Pas de symptôme rapporté.' },
  { date: '10 août 2024',  motif: 'Plaie patte avant',         summary: 'Plaie superficielle. Désinfection + antibio 5 j. Collerette.' },
  { date: '20 mai 2024',   motif: 'Stérilisation',             summary: 'Ovariohystérectomie. Suites simples. Retrait points J+10.' },
];

const state = {
  patient: {
    name: 'Luna', species: 'Félin', breed: 'Persan',
    age: 4, sex: 'F', weight: 3.8, lastWeight: 4.0,
    allergies: ['Pénicilline'],
  },
};

// ══════════════════════════════════════════════
//  TOAST (wrapper Kiveto)
// ══════════════════════════════════════════════
const Toast = {
  success: m => kivetoToast.success(m),
  info:    m => kivetoToast.info(m),
  warn:    m => kivetoToast.warning(m),
  error:   m => kivetoToast.danger(m),
};

// ══════════════════════════════════════════════
//  MODAL (identical to mockup — uses Kiveto CSS classes)
// ══════════════════════════════════════════════
const Modal = (() => {
  let mount = null;
  let escHandler = null;

  function open({ title, body, footer, width = '' }) {
    close();
    mount = document.createElement('div');
    mount.className = 'modal-overlay';
    let wClass = '';
    if (width === 'xwide')       wClass = 'modal-xwide';
    else if (width === 'wide')   wClass = 'modal-wide';
    else if (width === 'narrow') wClass = 'modal-narrow';
    mount.innerHTML = `
      <div class="modal ${wClass}">
        <div class="modal-head">
          <span class="modal-title">${title}</span>
          <button class="btn btn-ghost btn-icon btn-sm" data-close title="Fermer">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
          </button>
        </div>
        <div class="modal-body">${body}</div>
        ${footer ? `<div class="modal-foot">${footer}</div>` : ''}
      </div>
    `;
    document.body.appendChild(mount);
    mount.addEventListener('click', e => {
      if (e.target === mount || e.target.closest('[data-close]')) close();
    });
    escHandler = e => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', escHandler);
    setTimeout(() => {
      const f = mount.querySelector('input,textarea');
      if (f) f.focus();
    }, 60);
    return mount;
  }

  function close() {
    if (mount) { mount.remove(); mount = null; }
    if (escHandler) { document.removeEventListener('keydown', escHandler); escHandler = null; }
  }

  function getEl(sel) { return mount ? mount.querySelector(sel) : null; }

  return { open, close, getEl, get el() { return mount; } };
})();

// ══════════════════════════════════════════════
//  DROPDOWN (identical to mockup)
// ══════════════════════════════════════════════
const Dropdown = (() => {
  let menu = null;
  let docHandler = null;

  function open(anchor, items, opts = {}) {
    close();
    menu = document.createElement('div');
    menu.className = 'dropdown-menu';
    menu.innerHTML = items.map(it => {
      if (it.divider) return '<div class="dropdown-divider"></div>';
      const cls = 'dropdown-item' + (it.danger ? ' is-danger' : '');
      return `<div class="${cls}" data-action="${it.action}">
        ${it.icon ? `<svg class="icon-14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">${it.icon}</svg>` : ''}
        <span>${it.label}</span>
      </div>`;
    }).join('');
    document.body.appendChild(menu);
    const r = anchor.getBoundingClientRect();
    const align = opts.align || 'right';
    menu.style.top = (r.bottom + 4) + 'px';
    if (align === 'right') {
      menu.style.right = (window.innerWidth - r.right) + 'px';
    } else {
      menu.style.left = r.left + 'px';
    }
    menu.addEventListener('click', e => {
      const it = e.target.closest('.dropdown-item');
      if (!it) return;
      const action = it.dataset.action;
      close();
      if (opts.onSelect) opts.onSelect(action);
    });
    docHandler = e => {
      if (menu && !menu.contains(e.target) && e.target !== anchor && !anchor.contains(e.target)) close();
    };
    setTimeout(() => document.addEventListener('click', docHandler), 0);
  }

  function close() {
    if (menu) { menu.remove(); menu = null; }
    if (docHandler) { document.removeEventListener('click', docHandler); docHandler = null; }
  }

  return { open, close };
})();

// ══════════════════════════════════════════════
//  HELPERS (identical to mockup)
// ══════════════════════════════════════════════
function fmtPrice(n) { return n.toFixed(2).replace('.', ',') + ' €'; }

function recomputeBillTotal() {
  const rows = [...document.querySelectorAll('.bill-row .bill-price')];
  const total = rows.reduce((s, el) => s + parseFloat(el.textContent.replace(',', '.').replace('€', '').trim()), 0);
  const ht = total / 1.2;
  const tva = total - ht;
  const totalEl = document.querySelector('.bill-total-amount');
  const subEl   = document.querySelector('.bill-total div div:nth-child(2)');
  if (totalEl) totalEl.textContent = fmtPrice(total);
  if (subEl)   subEl.textContent = `HT ${fmtPrice(ht)} · TVA ${fmtPrice(tva)}`;
}

function highlightNew(el) {
  el.classList.add('is-just-added');
  setTimeout(() => el.classList.remove('is-just-added'), 1500);
}

// ══════════════════════════════════════════════
//  ACTIONS — domain operations (identical to mockup)
// ══════════════════════════════════════════════
function addMedication(med, posology) {
  const list = document.querySelector('.rp-block .rx-list');
  const item = document.createElement('div');
  item.className = 'rx-item has-row-x';
  item.dataset.code  = med.code;
  item.dataset.price = med.price;
  item.innerHTML = `
    <div class="rx-item-row">
      <span class="rx-name">${med.name}</span>
      <span class="rx-price">${fmtPrice(med.price)}</span>
      <button class="row-x" title="Retirer ce médicament" aria-label="Retirer ce médicament">
        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
      </button>
    </div>
    <div class="rx-poso">${posology.dose} ${med.doseUnit} · ${posology.freq} · ${posology.days} ${posology.days > 1 ? 'jours' : 'jour'} · ${posology.route}</div>
  `;
  list.appendChild(item);
  wireRxItem(item, med);

  const titleEl = document.querySelector('.rp-block .rp-block-title');
  const newCount = list.children.length;
  if (titleEl) titleEl.textContent = `Ordonnance · ${newCount} médicament${newCount > 1 ? 's' : ''}`;

  const billList = document.querySelector('.bill-list');
  const billRow = document.createElement('div');
  billRow.className = 'bill-row';
  billRow.dataset.code = med.code;
  billRow.innerHTML = `
    <div class="bill-name">
      ${med.name.split(' ').slice(0, 2).join(' ')}
      <span class="bill-qty">MED · ${posology.days} ${posology.days > 1 ? 'jours' : 'jour'}</span>
    </div>
    <div class="bill-price">${fmtPrice(med.price)}</div>
  `;
  billList.appendChild(billRow);
  recomputeBillTotal();
  highlightNew(item);
  highlightNew(billRow);
  Toast.success(`${med.name} ajouté à l'ordonnance et à la facture`);
}

function addDiagnostic(dx, confidenceKey = 'probable') {
  const conf = CONFIDENCE[confidenceKey];
  if (!conf) return;
  const list = document.querySelector('.quad[data-letter="A"] .dx-list');
  const item = document.createElement('div');
  item.className = 'dx-item';
  item.dataset.code       = dx.code;
  item.dataset.confidence = conf.key;
  item.innerHTML = `
    <span class="dx-code">${dx.code}</span>
    <span class="dx-label">${dx.name}</span>
    <span class="dx-confidence" data-confidence="${conf.key}" title="Cliquer pour modifier la certitude">${conf.label}</span>
    <button class="dx-remove" title="Retirer" aria-label="Retirer ce diagnostic">
      <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
    </button>
  `;
  list.appendChild(item);
  wireDxRemove(item);
  wireDxConfidence(item);
  highlightNew(item);
  Toast.success(`Diagnostic ${conf.label} : ${dx.name}`);
}

function addPlanItem(text, meta) {
  const list = document.querySelector('.quad[data-letter="P"] .plan-list');
  const item = document.createElement('div');
  item.className = 'plan-item has-row-x';
  item.innerHTML = `
    <span class="plan-checkbox"></span>
    <span class="plan-text">${text}</span>
    <span class="plan-meta">${meta}</span>
    <button class="row-x" title="Retirer du plan" aria-label="Retirer du plan">
      <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
    </button>
  `;
  list.appendChild(item);
  wirePlanItem(item);
  highlightNew(item);
}

function addVitalPill(vt, value) {
  const strip = document.querySelector('.vitals-strip');
  const pill = document.createElement('div');
  pill.className = 'vital-pill';
  pill.dataset.vital = vt.id;
  const valueDisplay = vt.unit
    ? `${value} <span class="unit">${vt.unit}</span>`
    : `<span style="font-size:var(--text-sm)">${value}</span>`;
  pill.innerHTML = `
    <div class="vital-pill-label">${vt.label}</div>
    <div class="vital-pill-value">${valueDisplay}</div>
    <button class="row-x" title="Retirer cette constante" aria-label="Retirer ${vt.label}">
      <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
    </button>
  `;
  const addChip = strip.querySelector('.chip-add');
  strip.insertBefore(pill, addChip);
  wireVitalPill(pill);
  highlightNew(pill);
  Toast.success(`${vt.label} ajoutée : ${value} ${vt.unit}`);
}

// ══════════════════════════════════════════════
//  MODAL TEMPLATES (identical to mockup)
// ══════════════════════════════════════════════

function openAddMotifModal() {
  const html = PRESET_MOTIFS.map(cat => `
    <div class="m-list-section">${cat.category}</div>
    ${cat.items.map(item => `<div class="m-item" data-motif="${item}"><div class="m-item-content"><span class="m-item-name">${item}</span></div></div>`).join('')}
  `).join('');
  Modal.open({
    title: 'Ajouter un motif de consultation',
    body: `
      <div class="modal-search">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="M11 11l3 3"/></svg>
        <input type="text" placeholder="Rechercher un motif…" id="motif-search">
      </div>
      <div class="m-list" id="motif-list">${html}</div>
    `,
  });
  Modal.getEl('#motif-search').addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    Modal.getEl('#motif-list').querySelectorAll('.m-item').forEach(it => {
      it.style.display = it.dataset.motif.toLowerCase().includes(q) ? '' : 'none';
    });
  });
  Modal.getEl('#motif-list').addEventListener('click', e => {
    const it = e.target.closest('.m-item');
    if (!it) return;
    const motif = it.dataset.motif;
    const existing = [...document.querySelectorAll('.strip:first-child .chip:not(.chip-add)')]
      .find(c => c.textContent.trim() === motif);
    if (existing) {
      existing.classList.add('is-selected');
      Toast.info(`"${motif}" déjà présent`);
    } else {
      const chip = document.createElement('button');
      chip.className = 'chip is-selected';
      chip.type = 'button';
      chip.textContent = motif;
      wireMotifChip(chip);
      const addChip = document.querySelector('.strip-content .chip-add');
      addChip.parentNode.insertBefore(chip, addChip);
      Toast.success(`Motif ajouté : ${motif}`);
    }
    Modal.close();
  });
}

function openEditWeightModal() {
  Modal.open({
    title: 'Modifier le poids',
    body: `
      <div class="form-row">
        <span class="form-row-label">Poids actuel</span>
        <input type="text" class="inline-num-input" id="weight-input" value="${state.patient.weight}">
      </div>
      <div class="form-row"><span class="form-row-label">Unité</span><span class="form-hint">kg</span></div>
      <div class="form-row"><span class="form-row-label">Dernière pesée</span><span class="form-hint">${state.patient.lastWeight} kg · 24 janv. 2026</span></div>
      <div style="padding:var(--space-3);background:var(--brand-50);border-radius:var(--radius-md);font-size:var(--text-sm);color:var(--brand-700);margin-top:var(--space-3)">
        ℹ Le nouveau poids sert au recalcul automatique des dosages dans l'ordonnance.
      </div>
    `,
    footer: `<button class="btn btn-ghost" data-close>Annuler</button><button class="btn btn-primary" id="weight-save">Enregistrer</button>`,
    width: 'narrow',
  });
  Modal.getEl('#weight-save').addEventListener('click', () => {
    const v = parseFloat(Modal.getEl('#weight-input').value.replace(',', '.'));
    if (isNaN(v) || v <= 0) { Toast.error('Poids invalide'); return; }
    const oldW = state.patient.weight;
    state.patient.weight = v;
    const delta = v - state.patient.lastWeight;
    const pill = document.querySelector('.vital-pill');
    if (pill) {
      const pillValue = pill.querySelector('.vital-pill-value');
      const pillDelta = pill.querySelector('.vital-pill-delta');
      if (pillValue) pillValue.innerHTML = `${String(v).replace('.', ',')} <span class="unit">kg</span>`;
      if (pillDelta) {
        pillDelta.textContent = (delta >= 0 ? '+' : '') + delta.toFixed(1).replace('.', ',') + ' kg';
        pillDelta.classList.toggle('is-down', delta < 0);
        pillDelta.classList.toggle('is-up', delta > 0);
      }
    }
    Modal.close();
    Toast.success(`Poids enregistré : ${String(v).replace('.', ',')} kg (${delta >= 0 ? '+' : ''}${delta.toFixed(1).replace('.', ',')})`);
  });
}

function openAddConstanteModal() {
  const existing = [...document.querySelectorAll('.vital-pill[data-vital]')].map(p => p.dataset.vital);
  const available = VITAL_TYPES.filter(v => !existing.includes(v.id));
  if (available.length === 0) {
    Modal.open({
      title: 'Ajouter une constante',
      body: '<div class="m-empty">Toutes les constantes disponibles ont été ajoutées.</div>',
      footer: '<button class="btn btn-secondary" data-close>OK</button>',
    });
    return;
  }
  const html = available.map(v => `
    <div class="m-item" data-vital="${v.id}">
      <div class="m-item-content">
        <span class="m-item-name">${v.label}</span>
        <span class="m-item-meta">Plage normale : ${v.range}${v.unit ? ' ' + v.unit : ''}</span>
      </div>
      <span class="m-item-tag">${v.unit || '—'}</span>
    </div>
  `).join('');
  Modal.open({ title: 'Ajouter une constante', body: `<div class="m-list">${html}</div>` });
  Modal.getEl('.m-list').addEventListener('click', e => {
    const it = e.target.closest('.m-item');
    if (!it) return;
    const vt = VITAL_TYPES.find(v => v.id === it.dataset.vital);
    Modal.close();
    openVitalValueModal(vt);
  });
}

function openVitalValueModal(vt) {
  Modal.open({
    title: vt.label,
    body: `
      <div class="form-row"><span class="form-row-label">${vt.label}</span><input type="text" class="inline-num-input" id="vital-input" value="${vt.def}"></div>
      <div class="form-row"><span class="form-row-label">Unité</span><span class="form-hint">${vt.unit || '—'}</span></div>
      <div class="form-row"><span class="form-row-label">Normale</span><span class="form-hint">${vt.range}</span></div>
    `,
    footer: `<button class="btn btn-ghost" data-close>Annuler</button><button class="btn btn-primary" id="vital-save">Ajouter</button>`,
    width: 'narrow',
  });
  Modal.getEl('#vital-save').addEventListener('click', () => {
    const value = Modal.getEl('#vital-input').value.trim();
    if (!value) { Toast.error('Valeur requise'); return; }
    Modal.close();
    addVitalPill(vt, value);
  });
}

function openAddDiagnosticModal() {
  const grouped = DIAGNOSTICS.reduce((acc, d) => {
    (acc[d.system] = acc[d.system] || []).push(d);
    return acc;
  }, {});
  const pillsHtml = CONFIDENCE_ORDER.map(key =>
    `<button class="dx-quick-pill" data-conf="${key}" title="${CONFIDENCE[key].description}">${CONFIDENCE[key].label}</button>`
  ).join('');
  const html = Object.entries(grouped).map(([sys, items]) => `
    <div class="m-list-section">${sys}</div>
    ${items.map(d => `
      <div class="m-item" data-code="${d.code}">
        <span class="m-item-tag">${d.code}</span>
        <div class="m-item-content"><span class="m-item-name">${d.name}</span></div>
        <div class="dx-quick-pills">${pillsHtml}</div>
      </div>
    `).join('')}
  `).join('');
  Modal.open({
    title: 'Ajouter un diagnostic',
    body: `
      <div class="modal-search">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="M11 11l3 3"/></svg>
        <input type="text" placeholder="Rechercher par code ou nom…" id="dx-search">
      </div>
      <div style="font-size:var(--text-xs);color:var(--text-subtle);margin-bottom:var(--space-2);padding:0 var(--space-2)">
        Cliquez la ligne pour ajouter en <strong style="color:var(--brand-700)">probable</strong>, ou choisissez directement le niveau de certitude.
      </div>
      <div class="m-list" id="dx-list">${html}</div>
    `,
    width: 'xwide',
  });
  Modal.getEl('#dx-search').addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    Modal.getEl('#dx-list').querySelectorAll('.m-item').forEach(it => {
      const code = it.dataset.code.toLowerCase();
      const name = it.querySelector('.m-item-name').textContent.toLowerCase();
      it.style.display = (code.includes(q) || name.includes(q)) ? '' : 'none';
    });
  });
  Modal.getEl('#dx-list').addEventListener('click', e => {
    const pill = e.target.closest('.dx-quick-pill');
    const it   = e.target.closest('.m-item');
    if (!it) return;
    const dx = DIAGNOSTICS.find(d => d.code === it.dataset.code);
    if (document.querySelector(`.dx-item[data-code="${dx.code}"]`)) {
      Toast.warn('Ce diagnostic est déjà dans la liste'); return;
    }
    const confidenceKey = pill ? pill.dataset.conf : 'probable';
    Modal.close();
    addDiagnostic(dx, confidenceKey);
  });
}

function openSuggestDiagnosticModal() {
  Modal.open({
    title: '✨ Suggestions diagnostiques (IA)',
    body: `<div style="padding:var(--space-4);text-align:center;color:var(--text-muted);font-size:var(--text-md)"><span class="live-pulse" style="display:inline-block;background:var(--brand-500);margin-right:var(--space-2)"></span>Analyse en cours…</div>`,
    width: 'narrow',
  });
  setTimeout(() => {
    const suggestions = [
      { dx: DIAGNOSTICS[2], score: 78, reason: 'Race prédisposée + boiterie chronique' },
      { dx: DIAGNOSTICS[3], score: 64, reason: 'Boiterie après effort, amyotrophie' },
      { dx: DIAGNOSTICS[1], score: 52, reason: 'Tiroir antérieur à confirmer' },
    ];
    const html = suggestions.map(s => `
      <div class="ai-suggest-item">
        <div style="flex:1;display:flex;flex-direction:column;gap:2px;min-width:0">
          <div style="display:flex;align-items:center;gap:var(--space-2)">
            <span class="dx-code">${s.dx.code}</span>
            <span style="font-weight:var(--weight-medium);font-size:var(--text-md)">${s.dx.name}</span>
          </div>
          <span style="font-size:var(--text-sm);color:var(--text-muted)">${s.reason}</span>
        </div>
        <div class="meter"><div class="meter-fill" style="width:${s.score}%"></div></div>
        <span style="font-family:'DM Mono',monospace;font-size:var(--text-sm);color:var(--brand-700);min-width:32px;text-align:right">${s.score}%</span>
        <button class="btn btn-primary btn-xs" data-add="${s.dx.code}">+</button>
      </div>
    `).join('');
    const bodyEl = Modal.getEl('.modal-body');
    if (!bodyEl) return;
    bodyEl.innerHTML = `
      <div style="font-size:var(--text-sm);color:var(--text-muted);margin-bottom:var(--space-3)">Basé sur l'examen clinique, l'âge, la race et l'historique du patient.</div>
      ${html}
      <div style="font-size:var(--text-xs);color:var(--text-subtle);margin-top:var(--space-3);padding-top:var(--space-3);border-top:1px solid var(--border-light)">ⓘ Ces suggestions ne remplacent pas le jugement clinique du praticien.</div>
    `;
    Modal.el.querySelectorAll('[data-add]').forEach(btn => {
      btn.addEventListener('click', () => {
        const dx = DIAGNOSTICS.find(d => d.code === btn.dataset.add);
        if (document.querySelector(`.dx-item[data-code="${dx.code}"]`)) {
          Toast.warn('Diagnostic déjà ajouté'); return;
        }
        Modal.close();
        addDiagnostic(dx, 'probable');
      });
    });
  }, 800);
}

function openAddPlanActionModal() {
  const html = PLAN_TYPES.map(t => `
    <button class="type-card" data-type="${t.id}" type="button">
      <span class="type-card-icon">${t.icon}</span>
      <span class="type-card-text">
        <span class="type-card-label">${t.label}</span>
        <span class="type-card-meta">${t.meta}</span>
      </span>
    </button>
  `).join('');
  Modal.open({ title: 'Ajouter au plan', body: `<div class="type-grid">${html}</div>` });
  Modal.getEl('.type-grid').addEventListener('click', e => {
    const card = e.target.closest('.type-card');
    if (!card) return;
    const t = PLAN_TYPES.find(x => x.id === card.dataset.type);
    Modal.close();
    if (t.special === 'medication') openAddMedicationModal();
    else openPlanActionTextModal(t);
  });
}

function openPlanActionTextModal(planType) {
  Modal.open({
    title: planType.label,
    body: `
      <div class="form-row"><span class="form-row-label">Type</span><span style="display:flex;align-items:center;gap:var(--space-2)"><span style="font-size:18px">${planType.icon}</span><span>${planType.label}</span></span></div>
      <div class="form-row"><span class="form-row-label">Description</span><input type="text" class="inline-num-input" id="plan-text" placeholder="Décrire l'action…"></div>
    `,
    footer: `<button class="btn btn-ghost" data-close>Annuler</button><button class="btn btn-primary" id="plan-save">Ajouter</button>`,
    width: 'narrow',
  });
  Modal.getEl('#plan-save').addEventListener('click', () => {
    const text = Modal.getEl('#plan-text').value.trim();
    if (!text) { Toast.error('Description requise'); return; }
    Modal.close();
    addPlanItem(text, planType.meta);
    Toast.success('Action ajoutée au plan');
  });
}

function openPlanTemplatesModal() {
  const html = PLAN_TEMPLATES.map(t => `
    <div class="m-item" data-tpl="${t.id}">
      <div class="m-item-content">
        <span class="m-item-name">${t.name}</span>
        <span class="m-item-meta">${t.description} · ${t.items.length} actions</span>
      </div>
    </div>
  `).join('');
  Modal.open({
    title: 'Templates de plan',
    body: `<div style="font-size:var(--text-sm);color:var(--text-muted);margin-bottom:var(--space-3)">Sélectionnez un template — toutes ses actions seront ajoutées au plan en cours.</div><div class="m-list">${html}</div>`,
  });
  Modal.getEl('.m-list').addEventListener('click', e => {
    const it = e.target.closest('.m-item');
    if (!it) return;
    const tpl = PLAN_TEMPLATES.find(x => x.id === it.dataset.tpl);
    Modal.close();
    tpl.items.forEach(i => addPlanItem(i.text, i.meta));
    Toast.success(`Template "${tpl.name}" appliqué (${tpl.items.length} actions)`);
  });
}

function openAddMedicationModal() {
  const grouped = MEDICATIONS.reduce((acc, m) => {
    (acc[m.cls] = acc[m.cls] || []).push(m);
    return acc;
  }, {});
  const html = Object.entries(grouped).map(([cls, items]) => `
    <div class="m-list-section">${cls}</div>
    ${items.map(m => {
      const blocked = m.contra && m.contra.some(c => state.patient.allergies.includes(c));
      return `
        <div class="m-item ${blocked ? 'is-disabled' : ''}" data-code="${m.code}" ${blocked ? 'data-blocked="1"' : ''}>
          <div class="m-item-content">
            <span class="m-item-name">${m.name}</span>
            <span class="m-item-meta">${m.form} · ${m.route} · ${m.dose} ${m.doseUnit}</span>
          </div>
          ${blocked ? `<span class="m-item-warn">filtré : ${m.contra.join(', ')}</span>` : ''}
          <span class="m-item-price">${fmtPrice(m.price)}</span>
        </div>
      `;
    }).join('')}
  `).join('');
  Modal.open({
    title: 'Ajouter un médicament',
    body: `
      <div class="modal-search">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="M11 11l3 3"/></svg>
        <input type="text" placeholder="Rechercher dans le catalogue…" id="med-search">
      </div>
      <div style="padding:var(--space-2) var(--space-3);background:var(--color-warning-bg);border:1px solid var(--color-warning-border);border-radius:var(--radius-md);font-size:var(--text-sm);color:var(--color-warning-text);margin-bottom:var(--space-3);display:flex;align-items:center;gap:var(--space-2)">
        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5L14.5 13h-13z"/><path d="M8 6v3M8 11h.01" stroke-width="2"/></svg>
        Allergie pénicilline — molécules incompatibles filtrées du catalogue
      </div>
      <div class="m-list" id="med-list">${html}</div>
    `,
    width: 'wide',
  });
  Modal.getEl('#med-search').addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    Modal.getEl('#med-list').querySelectorAll('.m-item').forEach(it => {
      const name = it.querySelector('.m-item-name').textContent.toLowerCase();
      it.style.display = name.includes(q) ? '' : 'none';
    });
  });
  Modal.getEl('#med-list').addEventListener('click', e => {
    const it = e.target.closest('.m-item');
    if (!it) return;
    if (it.dataset.blocked) { Toast.warn('Médicament incompatible avec les allergies du patient'); return; }
    const med = MEDICATIONS.find(m => m.code === it.dataset.code);
    if (document.querySelector(`.rx-item[data-code="${med.code}"]`)) {
      Toast.warn('Ce médicament est déjà dans l\'ordonnance'); return;
    }
    Modal.close();
    openMedicationPosologyModal(med);
  });
}

function openMedicationPosologyModal(med) {
  const totalDose = (state.patient.weight * med.dose).toFixed(2).replace('.', ',');
  Modal.open({
    title: 'Posologie',
    body: `
      <div style="padding:var(--space-3);background:var(--brand-50);border:1px solid var(--brand-100);border-radius:var(--radius-md);margin-bottom:var(--space-3)">
        <div style="font-weight:var(--weight-medium);font-size:var(--text-md)">${med.name}</div>
        <div style="font-size:var(--text-sm);color:var(--text-muted);margin-top:2px">${med.cls} · ${med.form}</div>
      </div>
      <div class="form-row">
        <span class="form-row-label">Dose</span>
        <div style="display:flex;gap:var(--space-2);align-items:center">
          <input type="text" class="inline-num-input" id="poso-dose" value="${med.dose}" style="width:80px">
          <span class="form-hint">${med.doseUnit}</span>
          <span class="form-hint" style="margin-left:var(--space-2)">→ ${totalDose} mg / prise pour ${state.patient.weight} kg</span>
        </div>
      </div>
      <div class="form-row"><span class="form-row-label">Voie</span><input type="text" class="inline-num-input" id="poso-route" value="${med.route}"></div>
      <div class="form-row"><span class="form-row-label">Fréquence</span><input type="text" class="inline-num-input" id="poso-freq" value="${med.freq}"></div>
      <div class="form-row">
        <span class="form-row-label">Durée</span>
        <div style="display:flex;gap:var(--space-2);align-items:center">
          <input type="text" class="inline-num-input" id="poso-days" value="${med.days}" style="width:80px">
          <span class="form-hint">jours</span>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:var(--space-3);border-top:1px solid var(--border-light)">
        <span style="font-size:var(--text-sm);color:var(--text-muted)">Coût catalogue</span>
        <span style="font-size:var(--text-lg);font-weight:var(--weight-medium)">${fmtPrice(med.price)}</span>
      </div>
    `,
    footer: `<button class="btn btn-ghost" data-close>Annuler</button><button class="btn btn-primary" id="poso-save">Ajouter à l'ordonnance</button>`,
  });
  Modal.getEl('#poso-save').addEventListener('click', () => {
    const posology = {
      dose:  Modal.getEl('#poso-dose').value,
      route: Modal.getEl('#poso-route').value,
      freq:  Modal.getEl('#poso-freq').value,
      days:  parseInt(Modal.getEl('#poso-days').value) || med.days,
    };
    Modal.close();
    addMedication(med, posology);
  });
}

function openPrintModal() {
  Modal.open({
    title: 'Imprimer / Télécharger',
    body: `
      <div style="font-size:var(--text-sm);color:var(--text-muted);margin-bottom:var(--space-3)">Sélectionnez les documents à imprimer ou télécharger.</div>
      <div class="print-grid">
        <button class="print-card" data-doc="cr" type="button"><span class="print-card-title">📋 Compte-rendu de consultation</span><span class="print-card-meta">Synthèse SOAP complète · 1 page</span></button>
        <button class="print-card" data-doc="ordo" type="button"><span class="print-card-title">💊 Ordonnance</span><span class="print-card-meta">2 médicaments · format légal FR</span></button>
        <button class="print-card" data-doc="facture" type="button"><span class="print-card-title">🧾 Facture</span><span class="print-card-meta">Total TTC · NF525</span></button>
        <button class="print-card" data-doc="all" type="button"><span class="print-card-title">📦 Tout</span><span class="print-card-meta">CR + ordonnance + facture</span></button>
      </div>
    `,
  });
  Modal.getEl('.print-grid').addEventListener('click', e => {
    const card = e.target.closest('.print-card');
    if (!card) return;
    const labels = { cr: 'Compte-rendu', ordo: 'Ordonnance', facture: 'Facture', all: '3 documents' };
    Modal.close();
    Toast.info(`${labels[card.dataset.doc]} envoyé à l'impression`);
  });
}

function openHistoryModal() {
  const html = PAST_CONSULTATIONS.map(c => `
    <div class="tl-item">
      <span class="tl-date">${c.date}</span>
      <div class="tl-content">
        <span class="tl-motif">${c.motif}</span>
        <span class="tl-summary">${c.summary}</span>
      </div>
    </div>
  `).join('');
  Modal.open({ title: `Historique — ${state.patient.name}`, body: `<div class="timeline">${html}</div>`, width: 'wide' });
  Modal.getEl('.timeline').addEventListener('click', e => {
    const item = e.target.closest('.tl-item');
    if (!item) return;
    Toast.info('Ouverture du dossier historique (mock)');
  });
}

function openPatientFileModal() {
  Modal.open({
    title: 'Dossier patient',
    body: `
      <div style="display:flex;align-items:center;gap:var(--space-4);padding-bottom:var(--space-4);border-bottom:1px solid var(--border-light);margin-bottom:var(--space-4)">
        <div class="pb-photo" style="width:72px;height:72px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5C8.5 5 5.5 7.5 5 11c-.3 1.5-.5 4 1 6h12c1.5-2 1.3-4.5 1-6C18.5 7.5 15.5 5 12 5z"/><path d="M8 5 6 2M16 5l2-2"/><path d="M9.5 14v.5M14.5 14v.5M10.5 17h3"/></svg></div>
        <div>
          <div style="font-size:var(--text-xl);font-weight:var(--weight-medium)">${state.patient.name}</div>
          <div style="color:var(--text-muted);margin-top:2px">${state.patient.breed} · ${state.patient.age} ans · ${state.patient.weight} kg</div>
        </div>
      </div>
      <div class="m-list-section" style="padding-left:0">Identité</div>
      <div class="form-row"><span class="form-row-label">Nom</span><span>${state.patient.name}</span></div>
      <div class="form-row"><span class="form-row-label">Espèce</span><span>${state.patient.species}</span></div>
      <div class="form-row"><span class="form-row-label">Race</span><span>${state.patient.breed}</span></div>
      <div class="form-row"><span class="form-row-label">Âge</span><span>${state.patient.age} ans</span></div>
      <div class="form-row"><span class="form-row-label">Sexe</span><span>Femelle stérilisée</span></div>
      <div class="form-row"><span class="form-row-label">Propriétaire</span><span style="color:var(--brand-600);cursor:pointer">Mme Dubois →</span></div>
      <div class="m-list-section" style="padding-left:0;margin-top:var(--space-3)">Médical</div>
      <div class="form-row"><span class="form-row-label">Allergies</span><span style="color:var(--color-danger-text)">Pénicilline</span></div>
      <div class="form-row"><span class="form-row-label">Vaccinations</span><span>FeLV à faire</span></div>
      <div class="m-list-section" style="padding-left:0;margin-top:var(--space-3)">Activité</div>
      <div class="form-row"><span class="form-row-label">Consultations</span><span>${PAST_CONSULTATIONS.length} antérieures</span></div>
      <div class="form-row"><span class="form-row-label">Dernière visite</span><span>12 jan. 2026</span></div>
    `,
    footer: `<button class="btn btn-secondary" data-close>Fermer</button><button class="btn btn-primary">Modifier le dossier</button>`,
    width: 'wide',
  });
}

function openOwnerModal() {
  Modal.open({
    title: 'Propriétaire',
    body: `
      <div style="display:flex;align-items:center;gap:var(--space-3);padding-bottom:var(--space-3);border-bottom:1px solid var(--border-light);margin-bottom:var(--space-3)">
        <span class="avatar" style="width:36px;height:36px;font-size:var(--text-base);display:inline-flex;align-items:center;justify-content:center;background:var(--brand-100);color:var(--brand-700);border-radius:var(--radius-full);">MD</span>
        <div>
          <div style="font-size:var(--text-lg);font-weight:var(--weight-medium)">Mme Dubois</div>
          <div style="color:var(--text-muted);font-size:var(--text-sm)">Propriétaire depuis 2022 · 1 animal</div>
        </div>
      </div>
      <div class="form-row"><span class="form-row-label">Téléphone</span><span style="color:var(--brand-600);cursor:pointer">+33 6 12 34 56 78 →</span></div>
      <div class="form-row"><span class="form-row-label">Email</span><span style="color:var(--brand-600);cursor:pointer">mme.dubois@example.fr →</span></div>
      <div class="form-row"><span class="form-row-label">Solde</span><span style="color:var(--color-success-600)">+0,00 €</span></div>
      <div class="form-row"><span class="form-row-label">Préférences</span><span>SMS rappel J-1 · Email facture</span></div>
    `,
    footer: `<button class="btn btn-secondary" data-close>Fermer</button><button class="btn btn-primary">Voir fiche complète</button>`,
  });
}

function openAllergieInfoModal() {
  Modal.open({
    title: 'Détails de l\'allergie',
    body: `
      <div style="padding:var(--space-3);background:var(--color-danger-bg);border:1px solid var(--color-danger-border);border-radius:var(--radius-md);margin-bottom:var(--space-3)">
        <div style="display:flex;align-items:center;gap:var(--space-2);color:var(--color-danger-text);font-weight:var(--weight-medium);margin-bottom:var(--space-1)">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5L14.5 13h-13z"/><path d="M8 6v3M8 11h.01" stroke-width="2"/></svg>
          Allergie pénicilline
        </div>
        <div style="font-size:var(--text-sm);color:var(--color-danger-text)">Toutes les pénicillines (amoxicilline, ampicilline, etc.) sont à proscrire.</div>
      </div>
      <div style="font-size:var(--text-sm);color:var(--text-muted);line-height:var(--lh-loose)">Cette allergie est <strong style="font-weight:var(--weight-medium);color:var(--text-primary)">automatiquement appliquée</strong> au catalogue de médicaments — les molécules incompatibles apparaissent grisées.</div>
    `,
    footer: `<button class="btn btn-secondary" data-close>Fermer</button><button class="btn btn-ghost">Modifier</button>`,
    width: 'narrow',
  });
}

function openCloseConsultationModal() {
  const motifs   = document.querySelectorAll('.strip:first-child .chip:not(.chip-add).is-selected').length;
  const dxCount  = document.querySelectorAll('.dx-item').length;
  const planCount= document.querySelectorAll('.plan-item').length;
  const planDone = document.querySelectorAll('.plan-item.is-done').length;
  const rxCount  = document.querySelectorAll('.rx-item').length;
  const total    = document.querySelector('.bill-total-amount')?.textContent || '—';

  function check(label, ok, extra) {
    return `<div class="check-item ${ok ? '' : 'is-fail'}">
      <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">${ok ? '<path d="M3.5 8l3 3 6-6"/>' : '<circle cx="8" cy="8" r="6"/><path d="M8 5v3M8 11h.01"/>'}</svg>
      <span>${label}</span>
      ${extra ? `<span class="check-item-extra">${extra}</span>` : ''}
    </div>`;
  }

  Modal.open({
    title: 'Clôture de la consultation',
    body: `
      <div class="checklist">
        ${check('Motif renseigné', motifs >= 1, motifs + ' chip(s)')}
        ${check('Au moins une constante', true, '4 mesures')}
        ${check('Examen objectif renseigné', true, '8 systèmes')}
        ${check('Au moins un diagnostic', dxCount >= 1, dxCount + ' dx')}
        ${check('Plan défini', planCount >= 1, planDone + '/' + planCount + ' réalisés')}
        ${check('Facturation établie', rxCount >= 0, total)}
      </div>
      <div class="m-list-section" style="padding-left:0">Finalisation</div>
      <label style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);cursor:pointer"><input type="checkbox" checked> <span>Envoyer la facture par email à Mme Dubois</span></label>
      <label style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);cursor:pointer"><input type="checkbox" checked> <span>Envoyer l'ordonnance par email</span></label>
      <label style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);cursor:pointer"><input type="checkbox"> <span>Programmer le RDV de recontrôle (J+7)</span></label>
      <label style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);cursor:pointer"><input type="checkbox" checked> <span>Programmer rappel SMS J+6</span></label>
    `,
    footer: `<button class="btn btn-ghost" data-close>Annuler</button><button class="btn btn-primary" id="close-confirm">Clôturer et envoyer</button>`,
  });
  Modal.getEl('#close-confirm').addEventListener('click', () => {
    Modal.close();
    openConsultationClosedModal();
  });
}

function openConsultationClosedModal() {
  Modal.open({
    title: '',
    body: `
      <div style="text-align:center;padding:var(--space-6) var(--space-4)">
        <div style="width:64px;height:64px;border-radius:var(--radius-full);background:var(--color-success-bg);display:inline-flex;align-items:center;justify-content:center;margin-bottom:var(--space-3)">
          <svg width="32" height="32" viewBox="0 0 16 16" fill="none" stroke="var(--color-success-600)" stroke-width="2"><path d="M3 8l3 3 7-7"/></svg>
        </div>
        <div style="font-size:var(--text-lg);font-weight:var(--weight-medium);margin-bottom:var(--space-2)">Consultation clôturée</div>
        <div style="font-size:var(--text-md);color:var(--text-muted);line-height:var(--lh-loose)">La facture et l'ordonnance ont été envoyées à Mme Dubois.</div>
      </div>
    `,
    footer: `<button class="btn btn-ghost" data-close>Rester ici</button><button class="btn btn-primary" id="next-patient">Patient suivant →</button>`,
    width: 'narrow',
  });
  Modal.getEl('#next-patient').addEventListener('click', () => {
    Modal.close();
    Toast.success('Navigation vers le prochain patient en attente');
  });
}

function openConfirmModal({ title, message, confirmLabel = 'Confirmer', danger = false, onConfirm }) {
  Modal.open({
    title,
    body: `<div style="font-size:var(--text-md);color:var(--text-secondary);line-height:var(--lh-loose);padding:var(--space-2) 0">${message}</div>`,
    footer: `<button class="btn btn-ghost" data-close>Annuler</button><button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" id="confirm-yes">${confirmLabel}</button>`,
    width: 'narrow',
  });
  Modal.getEl('#confirm-yes').addEventListener('click', () => {
    Modal.close();
    if (onConfirm) onConfirm();
  });
}

// ══════════════════════════════════════════════
//  WIRING — identical to mockup
// ══════════════════════════════════════════════
function wireMotifChip(chip) {
  chip.addEventListener('click', () => chip.classList.toggle('is-selected'));
}

function wireExamCell(cell) {
  cell.addEventListener('click', () => {
    const status = cell.querySelector('.exam-status');
    if (cell.classList.contains('is-normal')) {
      cell.classList.remove('is-normal');
      cell.classList.add('is-abnormal');
      status.textContent = 'À noter';
    } else if (cell.classList.contains('is-abnormal')) {
      cell.classList.remove('is-abnormal');
      cell.classList.add('is-untested');
      status.textContent = '— non testé';
    } else {
      cell.classList.remove('is-untested');
      cell.classList.add('is-normal');
      status.textContent = 'RAS';
    }
  });
}

function wirePlanItem(item) {
  if (!item.classList.contains('has-row-x')) item.classList.add('has-row-x');
  item.addEventListener('click', e => {
    if (e.target.closest('.row-x') || e.target.closest('.plan-meta')) return;
    item.classList.toggle('is-done');
    const cb = item.querySelector('.plan-checkbox');
    if (item.classList.contains('is-done') && !cb.querySelector('svg')) {
      cb.innerHTML = '<svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="white" stroke-width="2"><path d="M3.5 8l3 3 6-6"/></svg>';
    } else if (!item.classList.contains('is-done')) {
      cb.innerHTML = '';
    }
  });
  const xBtn = item.querySelector('.row-x');
  if (xBtn) {
    xBtn.addEventListener('click', e => {
      e.stopPropagation();
      const text = item.querySelector('.plan-text').textContent;
      item.remove();
      Toast.info(`Action retirée : ${text}`);
    });
  }
}

function wireVitalPill(pill) {
  pill.addEventListener('click', e => {
    if (e.target.closest('.row-x')) return;
    if (!pill.dataset.vital || pill.dataset.vital === 'weight') {
      openEditWeightModal();
    } else {
      const vt = VITAL_TYPES.find(v => v.id === pill.dataset.vital);
      if (vt) openVitalValueModal(vt);
    }
  });
  const xBtn = pill.querySelector('.row-x');
  if (xBtn) {
    xBtn.addEventListener('click', e => {
      e.stopPropagation();
      const label = pill.querySelector('.vital-pill-label').textContent;
      pill.remove();
      Toast.info(`${label} retirée`);
    });
  }
}

function wireDxRemove(item) {
  const btn = item.querySelector('.dx-remove');
  if (!btn) return;
  btn.addEventListener('click', e => {
    e.stopPropagation();
    const name = item.querySelector('.dx-label').textContent;
    item.remove();
    Toast.info(`Diagnostic retiré : ${name}`);
  });
}

function wireDxConfidence(item) {
  const badge = item.querySelector('.dx-confidence');
  if (!badge) return;
  badge.addEventListener('click', e => {
    e.stopPropagation();
    const dxName = item.querySelector('.dx-label').textContent;
    const items = CONFIDENCE_ORDER.map(key => {
      const c = CONFIDENCE[key];
      const isCurrent = item.dataset.confidence === key;
      return {
        action: key,
        label: `<span class="dx-confidence" data-confidence="${key}" style="cursor:default;pointer-events:none">${c.label}</span> <span style="color:var(--text-subtle);font-size:var(--text-xs)">${c.description}</span>${isCurrent ? ' ✓' : ''}`,
      };
    });
    Dropdown.open(badge, items, {
      onSelect: key => {
        const conf = CONFIDENCE[key];
        item.dataset.confidence = key;
        badge.dataset.confidence = key;
        badge.textContent = conf.label;
        badge.title = 'Cliquer pour modifier la certitude';
        Toast.info(`${dxName} → ${conf.label}`);
      },
    });
  });
}

function wireRxItem(item, med) {
  item.addEventListener('click', e => {
    if (e.target.closest('.row-x')) return;
    const code = item.dataset.code;
    const m = med || MEDICATIONS.find(x => x.code === code);
    if (m) openMedicationPosologyModal(m);
  });
  const xBtn = item.querySelector('.row-x');
  if (xBtn) {
    xBtn.addEventListener('click', e => {
      e.stopPropagation();
      const code = item.dataset.code;
      const name = item.querySelector('.rx-name').textContent;
      item.remove();
      const billRow = document.querySelector(`.bill-row[data-code="${code}"]`);
      if (billRow) billRow.remove();
      const list    = document.querySelector('.rp-block .rx-list');
      const titleEl = document.querySelector('.rp-block .rp-block-title');
      const newCount = list.children.length;
      if (titleEl) titleEl.textContent = `Ordonnance · ${newCount} médicament${newCount > 1 ? 's' : ''}`;
      recomputeBillTotal();
      Toast.info(`${name} retiré de l'ordonnance`);
    });
  }
}

function wireStripTextEdit(span) {
  span.addEventListener('click', () => {
    const current = span.textContent;
    const input = document.createElement('textarea');
    input.value = current;
    input.className = 'quad-textarea';
    input.style.minHeight = '40px';
    input.style.fontSize = 'var(--text-md)';
    input.style.border = '1px solid var(--brand-400)';
    input.style.background = 'var(--surface-card)';
    input.style.padding = 'var(--space-2)';
    span.replaceWith(input);
    input.focus();
    input.select();
    const finish = (cancelled = false) => {
      const span2 = document.createElement('span');
      span2.className = 'strip-text';
      span2.style.marginLeft = 'var(--space-2)';
      span2.textContent = cancelled ? current : input.value;
      input.replaceWith(span2);
      wireStripTextEdit(span2);
      if (!cancelled && input.value !== current) Toast.success('Motif mis à jour');
    };
    input.addEventListener('blur', () => finish(false));
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); finish(false); }
      if (e.key === 'Escape') { e.preventDefault(); finish(true); }
    });
  });
}

// ══════════════════════════════════════════════
//  LIFECYCLE
// ══════════════════════════════════════════════
let _keydownHandler = null;

export function init() {
  // ─ existing motif chips ─
  document.querySelectorAll('.strip:first-child .chip:not(.chip-add)').forEach(wireMotifChip);

  // ─ exam cells ─
  document.querySelectorAll('.exam-cell').forEach(wireExamCell);

  // ─ existing plan items ─
  document.querySelectorAll('.plan-item').forEach(wirePlanItem);

  // ─ existing vital pills ─
  document.querySelectorAll('.vital-pill').forEach(wireVitalPill);

  // ─ existing dx items ─
  document.querySelectorAll('.dx-item').forEach(item => {
    if (!item.dataset.code) {
      const codeEl = item.querySelector('.dx-code');
      if (codeEl) item.dataset.code = codeEl.textContent.trim();
    }
    wireDxRemove(item);
    wireDxConfidence(item);
  });

  // ─ existing rx items ─
  document.querySelectorAll('.rp-block .rx-item').forEach(item => {
    if (!item.dataset.code) {
      const name = item.querySelector('.rx-name')?.textContent;
      const med = MEDICATIONS.find(m => name && name.includes(m.name.split(' ')[0]));
      if (med) item.dataset.code = med.code;
    }
    wireRxItem(item, null);
  });

  // ─ strip text inline edit ─
  document.querySelectorAll('.strip:first-child .strip-text').forEach(wireStripTextEdit);

  // ─ topbar crumb link ─
  document.querySelector('.consult-crumb a')?.addEventListener('click', e => {
    e.preventDefault();
    openConfirmModal({
      title: 'Quitter la consultation',
      message: 'La consultation est en brouillon. Vous pouvez la reprendre depuis l\'agenda.',
      confirmLabel: 'Quitter',
      onConfirm: () => Toast.info('Retour à la liste des consultations'),
    });
  });

  // ─ topbar buttons ─
  const histBtn = [...document.querySelectorAll('.consult-topbar .btn')].find(b => b.textContent.includes('Historique'));
  if (histBtn) histBtn.addEventListener('click', openHistoryModal);
  const printBtn = [...document.querySelectorAll('.consult-topbar .btn')].find(b => b.textContent.includes('Imprimer'));
  if (printBtn) printBtn.addEventListener('click', openPrintModal);

  // ─ patient bar ─
  document.querySelector('.pb-photo')?.addEventListener('click', () => Toast.info('Téléversement de photo (à implémenter)'));
  document.querySelector('.pb-owner')?.addEventListener('click', openOwnerModal);
  document.querySelectorAll('.pb-warn').forEach(warn => {
    warn.style.cursor = 'pointer';
    warn.addEventListener('click', () => {
      if (warn.textContent.includes('pénicilline') || warn.textContent.includes('Allergie')) openAllergieInfoModal();
      else Toast.info('Détails de la condition');
    });
  });
  const dossierBtn = [...document.querySelectorAll('.pb-actions .btn')].find(b => b.textContent.includes('Dossier'));
  if (dossierBtn) dossierBtn.addEventListener('click', openPatientFileModal);
  const moreBtn = document.querySelectorAll('.pb-actions .btn-icon')[0];
  if (moreBtn) {
    moreBtn.addEventListener('click', e => {
      Dropdown.open(e.currentTarget, [
        { action: 'sms',      label: 'Envoyer un SMS au propriétaire' },
        { action: 'email',    label: 'Envoyer un email' },
        { action: 'tag',      label: 'Ajouter un tag' },
        { divider: true },
        { action: 'archive',  label: 'Archiver le patient' },
        { action: 'transfer', label: 'Transférer vers une autre clinique', danger: true },
      ], { onSelect: a => Toast.info(`Action : ${a}`) });
    });
  }

  // ─ save-state click ─
  document.querySelector('.consult-save-state')?.addEventListener('click', () => {
    Toast.info('Brouillon synchronisé · auto-sauvegarde active');
  });

  // ─ motif strip ─
  document.querySelector('.strip:first-child .chip-add')?.addEventListener('click', openAddMotifModal);

  // ─ vitals strip ─
  document.querySelector('.strip:last-child .chip-add')?.addEventListener('click', openAddConstanteModal);

  // ─ SOAP S — dictate ─
  document.querySelector('.quad[data-letter="S"] .quad-action')?.addEventListener('click', () => {
    Toast.warn('Dictée vocale activée (mock) — parlez maintenant');
    setTimeout(() => Toast.success('Dictée transcrite'), 2000);
  });

  // ─ SOAP O — all clear ─
  const oQuad = document.querySelector('.quad[data-letter="O"]');
  oQuad?.querySelector('.quad-action')?.addEventListener('click', () => {
    oQuad.querySelectorAll('.exam-cell').forEach(c => {
      c.classList.remove('is-abnormal', 'is-untested');
      c.classList.add('is-normal');
      c.querySelector('.exam-status').textContent = 'RAS';
    });
    Toast.success('Tous les systèmes marqués RAS');
  });

  // ─ SOAP A ─
  document.querySelector('.quad[data-letter="A"] .quad-action')?.addEventListener('click', openSuggestDiagnosticModal);
  document.querySelector('.quad[data-letter="A"] .add-row')?.addEventListener('click', openAddDiagnosticModal);

  // ─ SOAP P ─
  document.querySelector('.quad[data-letter="P"] .quad-action')?.addEventListener('click', openPlanTemplatesModal);
  document.querySelector('.quad[data-letter="P"] .add-row')?.addEventListener('click', openAddPlanActionModal);

  // ─ rpanel — print prescription ─
  const rxPrintBtn = document.querySelector('.rp-block .btn-icon');
  if (rxPrintBtn) rxPrintBtn.addEventListener('click', openPrintModal);

  // ─ rpanel — add medication ─
  const addMedBtn = document.querySelector('.rp-block .add-row');
  if (addMedBtn) addMedBtn.addEventListener('click', openAddMedicationModal);

  // ─ rpanel — bill draft badge ─
  const draftBadge = document.querySelector('.rp-block:nth-of-type(2) .badge');
  if (draftBadge) {
    draftBadge.style.cursor = 'pointer';
    draftBadge.addEventListener('click', e => {
      Dropdown.open(e.currentTarget, [
        { action: 'draft',   label: '✏ Brouillon' },
        { action: 'pending', label: '⏳ À facturer' },
        { action: 'paid',    label: '✓ Payée' },
      ], { onSelect: a => Toast.info(`Statut : ${a}`) });
    });
  }

  // ─ rp footer ─
  const pauseBtn = [...document.querySelectorAll('.rp-foot .btn')].find(b => b.textContent.trim() === 'Pause');
  if (pauseBtn) pauseBtn.addEventListener('click', () => {
    openConfirmModal({
      title: 'Mettre la consultation en pause',
      message: 'La consultation reste accessible depuis l\'agenda. Vous pouvez la reprendre à tout moment.',
      confirmLabel: 'Mettre en pause',
      onConfirm: () => Toast.success('Consultation mise en pause'),
    });
  });
  const laterBtn = [...document.querySelectorAll('.rp-foot .btn')].find(b => b.textContent.trim() === 'Plus tard');
  if (laterBtn) laterBtn.addEventListener('click', () => {
    openConfirmModal({
      title: 'Reporter la consultation',
      message: 'Le brouillon sera conservé. Vous pourrez la reprendre depuis l\'agenda quand vous le souhaitez.',
      confirmLabel: 'Reporter',
      onConfirm: () => Toast.success('Consultation reportée — brouillon sauvegardé'),
    });
  });
  const closeBtn = [...document.querySelectorAll('.rp-foot .btn-primary')].find(b => b.textContent.includes('Clôturer'));
  if (closeBtn) closeBtn.addEventListener('click', openCloseConsultationModal);

  // ─ keyboard shortcut ─
  _keydownHandler = e => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault();
      openCloseConsultationModal();
    }
  };
  document.addEventListener('keydown', _keydownHandler);

  // expose for debug
  window.VetOS = { Modal, Toast, Dropdown, state, MEDICATIONS, DIAGNOSTICS };

  // ─ expose vanilla functions referenced via inline onclick attributes ─
  window.openDrawer  = () => {
    document.getElementById('drawer-overlay')?.classList.add('is-open');
    document.getElementById('drawer')?.classList.add('is-open');
  };
  window.closeDrawer = () => {
    document.getElementById('drawer-overlay')?.classList.remove('is-open');
    document.getElementById('drawer')?.classList.remove('is-open');
  };
  window.closeConsult = () => openCloseConsultationModal();

  // welcome toast
  setTimeout(() => Toast.info('Tous les éléments sont interactifs · ⌘↵ pour clôturer'), 600);
}

export function cleanup() {
  Modal.close();
  Dropdown.close();
  if (_keydownHandler) {
    document.removeEventListener('keydown', _keydownHandler);
    _keydownHandler = null;
  }
  delete window.openDrawer;
  delete window.closeDrawer;
  delete window.closeConsult;
}
