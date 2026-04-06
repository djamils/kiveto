/**
 * Page module — Scheduling Waiting Room
 * Loaded by app.js dispatcher on turbo:load.
 */

// ════════════════════════════════════════════════════
// CLOCK
// ════════════════════════════════════════════════════
let clockInterval = null;

function updateClock() {
  var n = new Date();
  document.getElementById('clock-h').textContent = String(n.getHours()).padStart(2, '0');
  document.getElementById('clock-m').textContent = String(n.getMinutes()).padStart(2, '0');
  document.getElementById('clock-s').textContent = String(n.getSeconds()).padStart(2, '0');
}

// ════════════════════════════════════════════════════
// UTILS
// ════════════════════════════════════════════════════
function toast(m, c) {
  c = c || '#16a34a';
  var t = document.getElementById('wr-toast');
  t.textContent = m;
  t.style.background = c;
  t.style.opacity = '1';
  t.style.transform = 'translateX(-50%) translateY(0)';
  setTimeout(function () {
    t.style.opacity = '0';
    t.style.transform = 'translateX(-50%) translateY(8px)';
  }, 2800);
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ════════════════════════════════════════════════════
// DATA
// ════════════════════════════════════════════════════
var BASE = new Date(); BASE.setHours(9, 0, 0, 0);

function minAgo(m) { var d = new Date(BASE); d.setMinutes(d.getMinutes() + m); return d; }
function waitTime(a) { var d = Math.floor((new Date() - a) / 60000); if (d < 1) return "A l'instant"; if (d < 60) return d + " min"; return Math.floor(d / 60) + "h" + String(d % 60).padStart(2, '0'); }
function waitColor(a) { var d = Math.floor((new Date() - a) / 60000); if (d < 15) return '#16a34a'; if (d < 30) return '#d97706'; return '#dc2626'; }

var PATIENTS = [
  { id: 1, animal: 'Gaia', espece: 'Canin', race: 'Labrador', age: '4 ans', proprio: 'Marc Lef\u00e8vre', tel: '+33 6 11 22 33 44', motif: 'Convulsions soudaines', priority: 'urgence', statut: 'attente', arrival: minAgo(2), vet: null, salle: null, avatar: 'GL', color: '#dc2626', bg: '#fef2f2', poids: '28 kg', rdv: false },
  { id: 2, animal: 'Milo', espece: 'F\u00e9lin', race: 'British Shorthair', age: '3 ans', proprio: 'James Park', tel: '+33 6 12 34 56 78', motif: 'Toux persistante depuis 3j', priority: 'normal', statut: 'attente', arrival: minAgo(25), vet: 'Dr. Rousseau', salle: null, avatar: 'ML', color: '#4338ca', bg: '#eef2ff', poids: '4,2 kg', rdv: true },
  { id: 3, animal: 'Rocky', espece: 'Canin', race: 'Boxer', age: '6 ans', proprio: 'Sophie Dubois', tel: '+33 6 22 33 44 55', motif: 'Allergie cutan\u00e9e', priority: 'normal', statut: 'attente', arrival: minAgo(18), vet: 'Dr. Martin', salle: null, avatar: 'RK', color: '#059669', bg: '#ecfdf5', poids: '32 kg', rdv: true },
  { id: 4, animal: 'Luna', espece: 'Canin', race: 'Golden Retriever', age: '5 ans', proprio: 'Sarah Jenkins', tel: '+33 6 33 44 55 66', motif: 'Bilan annuel + vaccination', priority: 'normal', statut: 'consultation', arrival: minAgo(45), vet: 'Dr. Rousseau', salle: 'Salle 2', avatar: 'LU', color: '#0891b2', bg: '#ecfeff', poids: '28,5 kg', rdv: true },
  { id: 5, animal: 'Max', espece: 'Canin', race: 'Berger Allemand', age: '7 ans', proprio: 'Lisa Wong', tel: '+33 6 44 55 66 77', motif: 'Boiterie patte avant droite', priority: 'prioritaire', statut: 'consultation', arrival: minAgo(62), vet: 'Dr. Dupont', salle: 'Salle 1', avatar: 'MX', color: '#7c3aed', bg: '#f5f3ff', poids: '36 kg', rdv: true },
  { id: 6, animal: 'Cleo', espece: 'F\u00e9lin', race: 'Siamois', age: '2 ans', proprio: 'Tom Anderson', tel: '+33 6 55 66 77 88', motif: 'Otite \u2014 contr\u00f4le traitement', priority: 'normal', statut: 'sortie', arrival: minAgo(90), vet: 'Dr. Lambert', salle: null, avatar: 'CL', color: '#db2777', bg: '#fdf2f8', poids: '3,8 kg', rdv: true },
  { id: 7, animal: 'Bijou', espece: 'F\u00e9lin', race: 'Persan', age: '1 an', proprio: 'Claire Moreau', tel: '+33 6 66 77 88 99', motif: 'Vomissements r\u00e9p\u00e9t\u00e9s', priority: 'prioritaire', statut: 'attente', arrival: minAgo(8), vet: 'Dr. Lambert', salle: null, avatar: 'BJ', color: '#f59e0b', bg: '#fffbeb', poids: '3,1 kg', rdv: false },
];

var ROOMS = [
  { id: 1, nom: 'Salle 1', statut: 'occupee', vet: 'Dr. Dupont', patient: 'Max', depuis: '9h00' },
  { id: 2, nom: 'Salle 2', statut: 'occupee', vet: 'Dr. Rousseau', patient: 'Luna', depuis: '9h30' },
  { id: 3, nom: 'Salle 3', statut: 'libre', vet: null, patient: null, depuis: null },
  { id: 4, nom: 'Chirurgie', statut: 'nettoyage', vet: null, patient: null, depuis: null }
];

var VETS_DATA = [
  { nom: 'Dr. Rousseau', initiales: 'DR', color: '#4338ca', bg: '#dbeafe', statut: 'consultation', patient: 'Luna', salle: 'Salle 2' },
  { nom: 'Dr. Martin', initiales: 'MA', color: '#059669', bg: '#dcfce7', statut: 'disponible', patient: null, salle: null },
  { nom: 'Dr. Dupont', initiales: 'DU', color: '#7c3aed', bg: '#f3e8ff', statut: 'consultation', patient: 'Max', salle: 'Salle 1' },
  { nom: 'Dr. Lambert', initiales: 'LA', color: '#db2777', bg: '#fce7f3', statut: 'disponible', patient: null, salle: null }
];

var TIMELINE = [
  { heure: '9h00', animal: 'Max', espece: 'Canin', motif: 'Boiterie patte avant', vet: 'Dr. Dupont', statut: 'consultation' },
  { heure: '9h30', animal: 'Luna', espece: 'Canin', motif: 'Bilan annuel + vaccination', vet: 'Dr. Rousseau', statut: 'consultation' },
  { heure: '9h45', animal: 'Milo', espece: 'F\u00e9lin', motif: 'Toux persistante', vet: 'Dr. Rousseau', statut: 'attente' },
  { heure: '9h50', animal: 'Rocky', espece: 'Canin', motif: 'Allergie cutan\u00e9e', vet: 'Dr. Martin', statut: 'attente' },
  { heure: '10h02', animal: 'Gaia', espece: 'Canin', motif: 'Convulsions \u2014 URGENCE', vet: '\u2014', statut: 'urgence' },
  { heure: '10h08', animal: 'Bijou', espece: 'F\u00e9lin', motif: 'Vomissements r\u00e9p\u00e9t\u00e9s', vet: 'Dr. Lambert', statut: 'attente' },
  { heure: '8h00', animal: 'Cleo', espece: 'F\u00e9lin', motif: 'Otite \u2014 contr\u00f4le', vet: 'Dr. Lambert', statut: 'sortie' }
];

// ════════════════════════════════════════════════════
// STATE
// ════════════════════════════════════════════════════
var selectedId = null, qFilter = 'all', tabFilter = 'all';
var PRIO = { urgence: 0, prioritaire: 1, normal: 2 }, STAT = { attente: 0, consultation: 1, sortie: 2 };

// ════════════════════════════════════════════════════
// FILTERING & SORTING
// ════════════════════════════════════════════════════
function getFiltered(f) {
  return PATIENTS.filter(function (p) { return f === 'all' || p.statut === f || (f === 'urgence' && p.priority === 'urgence'); })
    .sort(function (a, b) {
      if (a.priority === 'urgence' && b.priority !== 'urgence') return -1;
      if (b.priority === 'urgence' && a.priority !== 'urgence') return 1;
      var sd = STAT[a.statut] - STAT[b.statut]; if (sd) return sd;
      var pd = PRIO[a.priority] - PRIO[b.priority]; if (pd) return pd;
      return a.arrival - b.arrival;
    });
}

function stripeClass(p) {
  if (p.priority === 'urgence') return 'urgence';
  if (p.statut === 'consultation') return 'consult';
  if (p.statut === 'sortie') return 'sortie';
  if (p.priority === 'prioritaire') return 'prio';
  return 'attente';
}

// ════════════════════════════════════════════════════
// QUEUE CARD RENDERING
// ════════════════════════════════════════════════════
function timerHTML(p, wt, wc) {
  if (p.statut !== 'attente') return '';
  if (wt === "A l'instant") {
    return '<span style="font-size:var(--text-xs);font-weight:var(--weight-medium);color:#16a34a;background:#f0fdf4;padding:2px 8px;border-radius:20px;">Vient d\'arriver</span>';
  }
  var parts = wt.split(' ');
  var num = parts[0];
  var unit = parts[1] || '';
  return '<span class="wait-big" style="color:' + wc + ';">' + num + '</span><span class="wait-label">' + unit + ' d\'attente</span>';
}

function qcardHTML(p, fn) {
  var wt = waitTime(p.arrival), wc = waitColor(p.arrival);
  var sLabel = p.statut === 'consultation' ? 'En consultation' : p.statut === 'sortie' ? 'Sortie' : p.priority === 'urgence' ? 'URGENCE' : p.priority === 'prioritaire' ? 'Prioritaire' : 'En attente';
  var sColor = p.statut === 'consultation' ? '#2563eb' : p.statut === 'sortie' ? '#16a34a' : p.priority === 'urgence' ? '#dc2626' : p.priority === 'prioritaire' ? '#d97706' : '#64748b';
  var sBg = p.statut === 'consultation' ? '#eff6ff' : p.statut === 'sortie' ? '#f0fdf4' : p.priority === 'urgence' ? '#fef2f2' : p.priority === 'prioritaire' ? '#fffbeb' : '#f8fafc';
  var icon = p.priority === 'urgence'
    ? '<div style="width:40px;height:40px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><svg width="18" height="18" fill="none" viewBox="0 0 14 14"><path d="M7 2l5 9H2l5-9z" stroke="#dc2626" stroke-width="1.4" stroke-linejoin="round"/><path d="M7 5.5v2M7 9h.01" stroke="#dc2626" stroke-width="1.4" stroke-linecap="round"/></svg></div>'
    : '<div style="width:40px;height:40px;border-radius:50%;background:' + p.bg + ';color:' + p.color + ';display:flex;align-items:center;justify-content:center;font-size:var(--text-md);font-weight:var(--weight-medium);flex-shrink:0;">' + p.avatar + '</div>';
  var rdvBadge = !p.rdv ? '<span style="font-size:var(--text-xs);font-weight:var(--weight-medium);color:#d97706;background:#fff7ed;border:1px solid #fde68a;border-radius:20px;padding:0 4px;">Sans RDV</span>' : '';
  var vetLine = p.vet ? '<span style="font-weight:var(--weight-medium);">' + p.vet + '</span>' : '<span>Non assign\u00e9</span>';
  var salleLine = p.salle ? '<span>\u00B7</span><span style="color:#1d4ed8;">' + p.salle + '</span>' : '';
  return '<div class="qcard' + (p.priority === 'urgence' ? ' urgence' : '') + '" onclick="' + fn + '(' + p.id + ')" id="qcard-' + p.id + '">'
    + '<div class="qcard-stripe ' + stripeClass(p) + '"></div>'
    + '<div class="qcard-body">' + icon + '<div style="flex:1;min-width:0;">'
    + '<div style="display:flex;align-items:center;gap:4px;margin-bottom:2px;"><p class="qcard-name">' + p.animal + '</p>' + rdvBadge + '</div>'
    + '<p class="qcard-race">' + p.espece + ' \u00B7 ' + p.race + ' \u00B7 ' + p.age + '</p>'
    + '<p class="qcard-motif">' + p.motif + '</p>'
    + '<div class="qcard-meta" style="display:flex;align-items:center;gap:4px;">' + vetLine + salleLine + '</div>'
    + '</div><div class="qcard-right">'
    + '<span style="font-size:var(--text-xs);font-weight:var(--weight-medium);color:' + sColor + ';background:' + sBg + ';padding:2px 8px;border-radius:20px;">' + sLabel + '</span>'
    + timerHTML(p, wt, wc)
    + '</div></div></div>';
}

// ════════════════════════════════════════════════════
// FILTER TABS
// ════════════════════════════════════════════════════
function setQFilter(btn) {
  qFilter = btn.dataset.f;
  btn.closest('.queue-filter').querySelectorAll('.qf-tab').forEach(function (t) { t.classList.remove('active'); });
  btn.classList.add('active');
  renderQueue();
}

function setQFilterTab(btn) {
  tabFilter = btn.dataset.f;
  btn.closest('.queue-filter').querySelectorAll('.qf-tab').forEach(function (t) { t.classList.remove('active'); });
  btn.classList.add('active');
  renderQueueTab();
}

// ════════════════════════════════════════════════════
// RENDERING
// ════════════════════════════════════════════════════
function renderQueue() {
  var list = getFiltered(qFilter), el = document.getElementById('queue-list'); if (!el) return;
  document.getElementById('queue-count').textContent = list.length + ' patient' + (list.length > 1 ? 's' : '');
  el.innerHTML = list.map(function (p) { return qcardHTML(p, 'selectPatient'); }).join('');
  if (selectedId) { var c = document.getElementById('qcard-' + selectedId); if (c) c.classList.add('active'); }
}

function renderQueueTab() {
  var list = getFiltered(tabFilter), el = document.getElementById('queue-list-tab'); if (!el) return;
  document.getElementById('queue-count-tab').textContent = list.length + ' patient' + (list.length > 1 ? 's' : '');
  el.innerHTML = list.map(function (p) { return qcardHTML(p, 'selectPatientTab'); }).join('');
}

// ════════════════════════════════════════════════════
// PATIENT SELECTION
// ════════════════════════════════════════════════════
function selectPatient(id) {
  selectedId = id;
  document.querySelectorAll('#queue-list .qcard').forEach(function (el) { el.classList.toggle('active', el.id === 'qcard-' + id); });
  var p = PATIENTS.find(function (x) { return x.id === id; });
  var html = detailHTML(p);
  if (window.innerWidth <= 1280) {
    document.getElementById('slide-title').textContent = p.animal;
    document.getElementById('slide-content').innerHTML = html;
    document.getElementById('detail-slide').classList.add('open');
  } else {
    document.getElementById('patient-detail-wrap').innerHTML = '<div class="widget" style="margin-bottom:12px;">' + html + '</div>';
  }
}

function selectPatientTab(id) {
  selectedId = id;
  var p = PATIENTS.find(function (x) { return x.id === id; });
  document.getElementById('bs-content').innerHTML = detailHTML(p);
  document.getElementById('bso').style.display = 'block';
  var bs = document.getElementById('bs');
  bs.style.display = 'flex';
  requestAnimationFrame(function () { bs.style.transform = 'translateY(0)'; });
}

function closeBottomSheet() {
  var bs = document.getElementById('bs');
  bs.style.transform = 'translateY(100%)';
  setTimeout(function () { bs.style.display = 'none'; document.getElementById('bso').style.display = 'none'; }, 300);
}

function closeDetailSlide() {
  document.getElementById('detail-slide').classList.remove('open');
}

// ════════════════════════════════════════════════════
// DETAIL HTML
// ════════════════════════════════════════════════════
function detailHTML(p) {
  var wt = waitTime(p.arrival), wc = waitColor(p.arrival);
  var actions = p.statut === 'attente'
    ? '<button class="btn-wr btn-green btn-xs" onclick="document.getElementById(\'modal-appel-title\').textContent=\'Appeler ' + p.animal + '\';openModal(\'modal-appel\')"><svg width="10" height="10" fill="none" viewBox="0 0 12 12"><path d="M2 2l8 4-8 4V7l5-1-5-1V2z" fill="currentColor"/></svg> Appeler</button><button class="btn-wr btn-primary btn-xs" onclick="toast(\'' + p.animal + ' \u2014 prise en charge\',\'#4338ca\')"><svg width="10" height="10" fill="none" viewBox="0 0 12 12"><path d="M2 6h8M6 2l4 4-4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg> Prendre en charge</button>'
    : p.statut === 'consultation'
      ? '<button class="btn-wr btn-secondary btn-xs" onclick="toast(\'Consultation termin\u00e9e\',\'#059669\')">Terminer</button><button class="btn-wr btn-secondary btn-xs">Ordonnance</button>'
      : '<button class="btn-wr btn-primary btn-xs" onclick="toast(\'Facture g\u00e9n\u00e9r\u00e9e\',\'#059669\')">G\u00e9n\u00e9rer la facture</button>';
  return '<div style="padding:16px;border-bottom:1px solid #e8edf2;display:flex;align-items:flex-start;gap:12px;">'
    + '<div style="width:44px;height:44px;border-radius:50%;background:' + p.bg + ';color:' + p.color + ';display:flex;align-items:center;justify-content:center;font-size:var(--text-base);font-weight:var(--weight-medium);flex-shrink:0;">' + p.avatar + '</div>'
    + '<div style="flex:1;min-width:0;">'
    + '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:2px;">'
    + '<h2 style="font-size:var(--text-lg);font-weight:var(--weight-medium);color:var(--text-primary);">' + p.animal + '</h2>'
    + '<span style="font-size:var(--text-base);color:var(--text-muted);">' + p.espece + ' ' + p.race + ' \u00B7 ' + p.age + '</span>'
    + (p.priority === 'urgence' ? '<span class="badge-wr b-urgence">URGENCE</span>' : p.priority === 'prioritaire' ? '<span class="badge-wr b-wait">Prioritaire</span>' : '')
    + '</div>'
    + '<p style="font-size:var(--text-md);color:var(--text-subtle);">' + p.proprio + ' \u00B7 ' + p.tel + '</p>'
    + '</div>'
    + '<div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end;">' + actions + '</div>'
    + '</div>'
    + '<div style="padding:12px 16px;">'
    + '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;">'
    + '<div class="pd-kpi"><p class="pd-kpi-label">Attente</p><p class="pd-kpi-val" style="color:' + wc + ';">' + wt + '</p></div>'
    + '<div class="pd-kpi"><p class="pd-kpi-label">Poids</p><p class="pd-kpi-val">' + p.poids + '</p></div>'
    + '<div class="pd-kpi"><p class="pd-kpi-label">RDV</p><p class="pd-kpi-val" style="font-size:var(--text-base);color:' + (p.rdv ? '#059669' : '#d97706') + ';">' + (p.rdv ? 'Oui' : 'Non') + '</p></div>'
    + '</div>'
    + '<div class="pd-row"><span class="pd-label">Motif</span><span style="font-size:var(--text-base);font-weight:var(--weight-medium);color:var(--text-primary);text-align:right;max-width:220px;">' + p.motif + '</span></div>'
    + '<div class="pd-row"><span class="pd-label">V\u00e9t\u00e9rinaire</span><span style="font-size:var(--text-base);font-weight:var(--weight-medium);color:var(--text-primary);">' + (p.vet || '\u2014 Non assign\u00e9') + '</span></div>'
    + (p.salle ? '<div class="pd-row"><span class="pd-label">Salle</span><span style="font-size:var(--text-base);font-weight:var(--weight-medium);color:#1d4ed8;">' + p.salle + '</span></div>' : '')
    + '<div class="pd-row"><span class="pd-label">Statut</span>'
    + '<select style="font-size:var(--text-md);border:1px solid #e2e8f0;border-radius:8px;padding:3px 8px;background:#fff;font-family:inherit;outline:none;cursor:pointer;" onchange="toast(\'Statut mis \u00e0 jour\',\'#059669\')">'
    + '<option ' + (p.statut === 'attente' ? 'selected' : '') + '>En attente</option>'
    + '<option ' + (p.statut === 'consultation' ? 'selected' : '') + '>En consultation</option>'
    + '<option ' + (p.statut === 'sortie' ? 'selected' : '') + '>Sortie</option>'
    + '</select></div></div>';
}

// ════════════════════════════════════════════════════
// VETS & TIMELINE
// ════════════════════════════════════════════════════
function vetsHTML() {
  return VETS_DATA.map(function (v, i) {
    var br = i % 2 === 0 ? 'border-right:1px solid #e8edf2;' : '';
    var bb = i < 2 ? 'border-bottom:1px solid #e8edf2;' : '';
    var isBusy = v.statut === 'consultation';
    var dotStyle = isBusy ? 'background:#3b82f6;box-shadow:0 0 0 3px #bfdbfe;' : 'background:#16a34a;';
    var statusLabel = isBusy ? 'En consultation' : 'Disponible';
    var statusColor = isBusy ? '#2563eb' : '#16a34a';
    var sub = isBusy ? v.patient + ' \u00B7 ' + v.salle : 'Disponible';
    return '<div class="vet-cell" style="' + br + bb + '">'
      + '<div class="vet-avatar" style="background:' + v.bg + ';color:' + v.color + ';">' + v.initiales + '</div>'
      + '<div style="flex:1;min-width:0;"><p style="font-size:var(--text-base);font-weight:var(--weight-medium);color:var(--text-primary);">' + v.nom + '</p><p style="font-size:var(--text-md);color:var(--text-subtle);">' + sub + '</p></div>'
      + '<div style="display:flex;align-items:center;gap:4px;"><div style="width:7px;height:7px;border-radius:50%;flex-shrink:0;' + dotStyle + '"></div><span style="font-size:var(--text-xs);font-weight:var(--weight-medium);color:' + statusColor + ';">' + statusLabel + '</span></div>'
      + '</div>';
  }).join('');
}

function timelineHTML() {
  var sorted = [].concat(TIMELINE).sort(function (a, b) { return a.heure.localeCompare(b.heure); });
  var colors = { consultation: '#3b82f6', attente: '#f59e0b', sortie: '#16a34a', urgence: '#dc2626' };
  var bgs = { consultation: '#eff6ff', attente: '#fff7ed', sortie: '#f0fdf4', urgence: '#fef2f2' };
  var labels = { consultation: 'En cours', attente: 'Attente', sortie: 'Termin\u00e9', urgence: 'Urgence' };
  return sorted.map(function (t, i, a) {
    var border = i < a.length - 1 ? 'border-bottom:1px solid #f8fafc;' : '';
    return '<div style="display:flex;align-items:center;gap:12px;padding:8px 0;' + border + '">'
      + '<span style="font-size:var(--text-md);color:var(--text-subtle);min-width:44px;flex-shrink:0;">' + t.heure + '</span>'
      + '<div style="width:7px;height:7px;border-radius:50%;background:' + colors[t.statut] + ';flex-shrink:0;"></div>'
      + '<div style="flex:1;min-width:0;"><span style="font-size:var(--text-base);font-weight:var(--weight-medium);color:var(--text-primary);">' + t.animal + '</span><span style="font-size:var(--text-md);color:var(--text-muted);margin-left:8px;">' + t.motif + '</span></div>'
      + '<span style="font-size:var(--text-md);color:var(--text-subtle);flex-shrink:0;white-space:nowrap;">' + t.vet + '</span>'
      + '<span style="font-size:var(--text-xs);font-weight:var(--weight-medium);padding:2px 8px;border-radius:20px;background:' + bgs[t.statut] + ';color:' + colors[t.statut] + ';flex-shrink:0;">' + labels[t.statut] + '</span>'
      + '</div>';
  }).join('');
}

// ════════════════════════════════════════════════════
// TAB SWITCHING (tablet portrait)
// ════════════════════════════════════════════════════
function switchTab(tab) {
  ['queue', 'rooms'].forEach(function (t) {
    document.getElementById('tab-' + t).classList.toggle('active', t === tab);
    document.getElementById('btab-' + t).classList.toggle('active', t === tab);
  });
}

// ════════════════════════════════════════════════════
// LAYOUT
// ════════════════════════════════════════════════════
function applyLayout() {
  var w = window.innerWidth;
  var bd = document.getElementById('page-body-desktop');
  var bt = document.getElementById('page-body-tabs');
  if (w <= 1024) {
    bd.style.display = 'none'; bt.style.display = 'flex';
    renderQueueTab();
    var vt = document.getElementById('vets-list-tab'); if (vt) vt.innerHTML = vetsHTML();
    var tlt = document.getElementById('timeline-list-tab'); if (tlt) tlt.innerHTML = timelineHTML();
  } else {
    bd.style.display = 'flex'; bt.style.display = 'none';
    renderQueue();
    var vl = document.getElementById('vets-list'); if (vl) vl.innerHTML = vetsHTML();
    var tl = document.getElementById('timeline-list'); if (tl) tl.innerHTML = timelineHTML();
    selectPatient(PATIENTS[0].id);
  }
}

// ════════════════════════════════════════════════════
// MODAL ACTIONS
// ════════════════════════════════════════════════════
function doCheckin() { toast("Patient enregistr\u00e9", '#059669'); closeModal('modal-checkin'); }
function doUrgence() { toast('Urgence enregistr\u00e9e', '#dc2626'); closeModal('modal-urgence'); }
function doAppel() { var s = document.getElementById('appel-salle').value; toast('Patient appel\u00e9 \u2192 ' + s, '#059669'); closeModal('modal-appel'); }
function selectCheckinRdv(el) { el.style.background = '#eef2ff'; el.style.outline = '2px solid #c7d2fe'; toast('Patient s\u00e9lectionn\u00e9', '#4338ca'); }

// Keyboard handler reference for cleanup
let refreshInterval = null;

function _onKeydown(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(function (m) { m.classList.remove('open'); });
    closeDetailSlide();
    closeBottomSheet();
  }
}

// ════════════════════════════════════════════════════
// EXPORTS
// ════════════════════════════════════════════════════

/** Initialise the waiting room page. */
export function init() {
  // Initialise Lucide icons already present in the HTML

  // Start clock
  updateClock();
  clockInterval = setInterval(updateClock, 1000);

  // Expose functions used via onclick in HTML
  window.toast = toast;
  window.openModal = openModal;
  window.closeModal = closeModal;
  window.selectPatient = selectPatient;
  window.selectPatientTab = selectPatientTab;
  window.closeBottomSheet = closeBottomSheet;
  window.closeDetailSlide = closeDetailSlide;
  window.setQFilter = setQFilter;
  window.setQFilterTab = setQFilterTab;
  window.switchTab = switchTab;
  window.doCheckin = doCheckin;
  window.doUrgence = doUrgence;
  window.doAppel = doAppel;
  window.selectCheckinRdv = selectCheckinRdv;

  // Keyboard shortcuts
  document.addEventListener('keydown', _onKeydown);

  // Only render if the queue is empty (skip when restored from Turbo cache)
  var queueList = document.getElementById('queue-list');
  if (!queueList || !queueList.children.length) {
    applyLayout();
  }

  // Always re-attach resize listener and refresh interval (cleared by cleanup)
  window.addEventListener('resize', applyLayout);
  refreshInterval = setInterval(function () { renderQueue(); renderQueueTab(); }, 30000);
}

/** Teardown: remove global listeners, intervals, and window references. */
export function cleanup() {
  // Clear intervals
  if (clockInterval) { clearInterval(clockInterval); clockInterval = null; }
  if (refreshInterval) { clearInterval(refreshInterval); refreshInterval = null; }

  // Remove listeners
  document.removeEventListener('keydown', _onKeydown);
  window.removeEventListener('resize', applyLayout);

  // Clean up window globals
  delete window.toast;
  delete window.openModal;
  delete window.closeModal;
  delete window.selectPatient;
  delete window.selectPatientTab;
  delete window.closeBottomSheet;
  delete window.closeDetailSlide;
  delete window.setQFilter;
  delete window.setQFilterTab;
  delete window.switchTab;
  delete window.doCheckin;
  delete window.doUrgence;
  delete window.doAppel;
  delete window.selectCheckinRdv;
}
