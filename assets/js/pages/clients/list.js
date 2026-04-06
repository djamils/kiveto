/**
 * Page module — Clients & Animaux
 * Loaded by app.js dispatcher on turbo:load.
 *
 * Renders 2 data tables (Clients and Animaux) from in-memory sample data.
 * The Vue d'ensemble tab is fully static (KPIs, recent activity, alerts).
 */

const SPECIES_EMOJI = { Félin: '🐱', Canin: '🐶', Lapin: '🐰', NAC: '🦎', Oiseau: '🦜' };
const SPECIES_LABEL = { Félin: 'Chat', Canin: 'Chien', Lapin: 'Lapin', NAC: 'NAC', Oiseau: 'Oiseau' };

const CLIENTS = [
  { id:1, first:'Sophie', last:'Dubois', phone:'+33 6 12 34 56 78', email:'s.dubois@email.fr', address:'12 rue de la Paix, 75001 Paris', since:'2021-03-15', lastVisit:'2026-03-21', initials:'SD', color:'#4338ca', bg:'#eef2ff',
    animals:[
      { id:101, name:'Luna',  species:'Félin',  breed:'Persan',          dob:'2019-06-10', sex:'F', lastVisit:'2026-03-21', nextVisit:'2026-06-15', status:'ok'    },
      { id:102, name:'Mimi',  species:'Félin',  breed:'Européen',        dob:'2022-01-20', sex:'F', lastVisit:'2026-01-15', nextVisit:null,         status:'warn'  },
    ] },
  { id:2, first:'Marc',     last:'Bernard',    phone:'+33 6 98 76 54 32', email:'m.bernard@gmail.com',     address:'5 av Victor Hugo, 75016 Paris',  since:'2020-07-08', lastVisit:'2026-03-19', initials:'MB', color:'#0891b2', bg:'#ecfeff',
    animals:[
      { id:201, name:'Max',   species:'Canin',  breed:'Labrador',         dob:'2018-04-15', sex:'M', lastVisit:'2026-03-19', nextVisit:'2026-09-19', status:'ok'    },
    ] },
  { id:3, first:'Claire',   last:'Petit',      phone:'+33 6 11 22 33 44', email:'claire.petit@outlook.fr', address:'8 rue du Faubourg, 75010 Paris', since:'2022-11-02', lastVisit:'2026-03-15', initials:'CP', color:'#059669', bg:'#ecfdf5',
    animals:[
      { id:301, name:'Milo',  species:'Félin',  breed:'Européen',         dob:'2021-08-30', sex:'M', lastVisit:'2026-03-15', nextVisit:null,         status:'ok'    },
      { id:302, name:'Félix', species:'Félin',  breed:'Maine Coon',       dob:'2023-02-14', sex:'M', lastVisit:'2025-12-20', nextVisit:'2026-06-20', status:'ok'    },
    ] },
  { id:4, first:'Antoine',  last:'Dupuis',     phone:'+33 6 55 66 77 88', email:'a.dupuis@free.fr',        address:'23 bd Haussmann, 75009 Paris',   since:'2019-05-20', lastVisit:'2026-02-28', initials:'AD', color:'#7c3aed', bg:'#f5f3ff',
    animals:[
      { id:401, name:'Rex',   species:'Canin',  breed:'Berger Allemand',  dob:'2017-11-01', sex:'M', lastVisit:'2026-02-28', nextVisit:'2026-08-28', status:'warn'  },
    ] },
  { id:5, first:'Julie',    last:'Leclerc',    phone:'+33 6 44 33 22 11', email:'j.leclerc@sfr.fr',        address:'17 imp. des Lilas, 92100 Boulogne', since:'2023-02-14', lastVisit:'2026-03-20', initials:'JL', color:'#db2777', bg:'#fdf2f8',
    animals:[
      { id:501, name:'Nala',  species:'Félin',  breed:'Maine Coon',       dob:'2022-05-18', sex:'F', lastVisit:'2026-03-20', nextVisit:'2026-09-20', status:'ok'    },
    ] },
  { id:6, first:'Pierre',   last:'Moreau',     phone:'+33 6 77 88 99 00', email:'p.moreau@yahoo.fr',       address:'3 rue de Rivoli, 75001 Paris',   since:'2020-01-10', lastVisit:'2026-03-18', initials:'PM', color:'#0891b2', bg:'#ecfeff',
    animals:[
      { id:601, name:'Oscar', species:'Canin',  breed:'Golden Retriever', dob:'2019-09-12', sex:'M', lastVisit:'2026-03-18', nextVisit:'2026-09-18', status:'ok'    },
    ] },
  { id:7, first:'Isabelle', last:'Girard',     phone:'+33 6 22 11 44 55', email:'i.girard@laposte.net',    address:'45 rue République, 69001 Lyon',  since:'2021-09-03', lastVisit:'2026-03-10', initials:'IG', color:'#b45309', bg:'#fef3c7',
    animals:[
      { id:701, name:'Bella',  species:'Canin', breed:'Beagle',           dob:'2020-03-22', sex:'F', lastVisit:'2026-03-10', nextVisit:'2026-09-10', status:'ok'    },
      { id:702, name:'Caramel',species:'Lapin', breed:'Bélier nain',      dob:'2023-07-01', sex:'M', lastVisit:'2025-11-20', nextVisit:null,         status:'ok'    },
    ] },
  { id:8, first:'Thomas',   last:'Lefebvre',   phone:'+33 6 33 44 55 66', email:'t.lefebvre@orange.fr',    address:'9 cours Mirabeau, 13100 Aix',    since:'2024-01-15', lastVisit:'2026-01-20', initials:'TL', color:'#4338ca', bg:'#eef2ff',
    animals:[
      { id:801, name:'Simba', species:'Félin',  breed:'Siamois',          dob:'2023-11-05', sex:'M', lastVisit:'2026-01-20', nextVisit:'2026-07-20', status:'ok'    },
    ] },
  { id:9, first:'Nathalie', last:'Fontaine',   phone:'+33 6 55 44 33 22', email:'n.fontaine@bouygues.fr',  address:'22 r Cherche-Midi, 75006 Paris', since:'2018-06-30', lastVisit:'2026-03-22', initials:'NF', color:'#059669', bg:'#ecfdf5',
    animals:[
      { id:901, name:'Rocky', species:'Canin',  breed:'Boxer',            dob:'2016-08-14', sex:'M', lastVisit:'2026-03-22', nextVisit:null,         status:'alert' },
    ] },
  { id:10, first:'Camille', last:'Chevalier',  phone:'+33 6 66 55 44 33', email:'camille.c@gmail.com',     address:'6 rue des Martyrs, 75009 Paris', since:'2022-04-11', lastVisit:'2026-02-14', initials:'CC', color:'#7c3aed', bg:'#f5f3ff',
    animals:[
      { id:1001, name:'Lily', species:'Félin',  breed:'Ragdoll',          dob:'2021-12-01', sex:'F', lastVisit:'2026-02-14', nextVisit:'2026-08-14', status:'ok'    },
    ] },
  { id:11, first:'François', last:'Garnier',   phone:'+33 6 77 66 55 44', email:'f.garnier@wanadoo.fr',    address:'14 av de Breteuil, 75007 Paris', since:'2019-11-25', lastVisit:'2026-03-12', initials:'FG', color:'#0891b2', bg:'#ecfeff',
    animals:[
      { id:1101, name:'Bruno',species:'Canin',  breed:'Rottweiler',       dob:'2018-07-22', sex:'M', lastVisit:'2026-03-12', nextVisit:'2026-09-12', status:'warn'  },
    ] },
  { id:12, first:'Amélie',  last:'Roux',       phone:'+33 6 88 77 66 55', email:'amelie.roux@sfr.fr',      address:'33 r Saint-Denis, 75001 Paris',  since:'2023-08-07', lastVisit:'2026-03-05', initials:'AR', color:'#db2777', bg:'#fdf2f8',
    animals:[
      { id:1201, name:'Coco', species:'Félin',  breed:'British Shorthair',dob:'2022-10-15', sex:'F', lastVisit:'2026-03-05', nextVisit:'2026-09-05', status:'ok'    },
      { id:1202, name:'Kiwi', species:'Oiseau', breed:'Perroquet',        dob:'2020-03-01', sex:'M', lastVisit:'2025-06-10', nextVisit:null,         status:'ok'    },
    ] },
];

// ── State ─────────────────────────────────────────────────────────
let clientsState = { search:'', filter:'all', sort:'name', page:1, pageSize:10 };
let animalsState = { search:'', filter:'all', statusFilter:'all', sort:'name', page:1, pageSize:10 };

// ── Utilities ─────────────────────────────────────────────────────
function escapeHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg, color) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.style.background = color || '#16a34a';
  t.style.opacity = '1';
  t.style.transform = 'translateX(-50%) translateY(0)';
  setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(8px)'; }, 2500);
}

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('fr-FR', { day:'numeric', month:'short', year:'numeric' });
}

function ageYears(dob) {
  if (!dob) return '—';
  const now = new Date();
  const birth = new Date(dob);
  let y = now.getFullYear() - birth.getFullYear();
  const m = now.getMonth() - birth.getMonth();
  if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) y--;
  return y + ' an' + (y > 1 ? 's' : '');
}

function statusBadge(status) {
  if (status === 'alert')  return '<span class="badge b-alert">Alerte</span>';
  if (status === 'warn')   return '<span class="badge b-warn">Surveillance</span>';
  return '<span class="badge b-ok">En forme</span>';
}

function worstStatus(c) {
  if (c.animals.some(a => a.status === 'alert')) return 'alert';
  if (c.animals.some(a => a.status === 'warn'))  return 'warn';
  return 'ok';
}

// Flatten all animals with their owner
function getAllAnimals() {
  const list = [];
  CLIENTS.forEach(c => {
    c.animals.forEach(a => {
      list.push({ ...a, owner: c });
    });
  });
  return list;
}

// ── Clients table ─────────────────────────────────────────────────
function getFilteredClients() {
  let list = CLIENTS.slice();
  const q = clientsState.search.trim().toLowerCase();

  if (clientsState.filter === 'cat')    list = list.filter(c => c.animals.some(a => a.species === 'Félin'));
  else if (clientsState.filter === 'dog')   list = list.filter(c => c.animals.some(a => a.species === 'Canin'));
  else if (clientsState.filter === 'other') list = list.filter(c => c.animals.some(a => a.species !== 'Félin' && a.species !== 'Canin'));
  else if (clientsState.filter === 'alert') list = list.filter(c => worstStatus(c) !== 'ok');
  else if (clientsState.filter === 'recent') {
    const cut = new Date(); cut.setDate(cut.getDate() - 30);
    list = list.filter(c => new Date(c.lastVisit) >= cut);
  }

  if (q) {
    list = list.filter(c => {
      const hay = [c.first, c.last, c.email, c.phone].concat(c.animals.map(a => a.name)).join(' ').toLowerCase();
      return hay.includes(q);
    });
  }

  if (clientsState.sort === 'name')        list.sort((a, b) => a.last.localeCompare(b.last));
  else if (clientsState.sort === 'recent') list.sort((a, b) => new Date(b.lastVisit) - new Date(a.lastVisit));
  else if (clientsState.sort === 'animals') list.sort((a, b) => b.animals.length - a.animals.length);

  return list;
}

function renderClientsTable() {
  const tbody = document.getElementById('clients-tbody');
  if (!tbody) return;

  const list = getFilteredClients();
  const start = (clientsState.page - 1) * clientsState.pageSize;
  const pageItems = list.slice(start, start + clientsState.pageSize);

  if (!pageItems.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="dg-cell-muted" style="text-align:center;padding:32px 16px;">Aucun client trouvé</td></tr>';
    updateClientsPagination(list.length);
    return;
  }

  tbody.innerHTML = pageItems.map(c => `
    <tr>
      <td class="dg-th-checkbox"><input type="checkbox" class="dg-checkbox" aria-label="Sélectionner ${escapeHtml(c.first + ' ' + c.last)}"/></td>
      <td>
        <div class="dg-name-cell">
          <span class="dg-avatar" style="background:${c.bg};color:${c.color};">${escapeHtml(c.initials)}</span>
          <a href="#" class="dg-name-link" onclick="showToast('Ouvrir ${escapeHtml(c.first + ' ' + c.last)}','#4338ca');return false;">${escapeHtml(c.first + ' ' + c.last)}</a>
        </div>
      </td>
      <td class="col-email"><span class="dg-cell-muted">${escapeHtml(c.email)}</span></td>
      <td class="col-phone"><span class="dg-cell-muted">${escapeHtml(c.phone)}</span></td>
      <td class="col-animals">
        <div class="dg-animals-chips">
          ${c.animals.map(a => `<span class="dg-animal-chip" title="${escapeHtml(a.name + ' · ' + a.breed)}">${SPECIES_EMOJI[a.species] || '🐾'} ${escapeHtml(a.name)}</span>`).join('')}
        </div>
      </td>
      <td class="col-lastrdv"><span class="dg-cell-muted">${fmtDate(c.lastVisit)}</span></td>
      <td>${statusBadge(worstStatus(c))}</td>
      <td class="dg-th-actions">
        <div class="dg-row-actions">
          <button type="button" class="dg-row-action dg-row-action--star" aria-label="Favori" onclick="toggleStar(this);event.stopPropagation();">
            <i data-lucide="star"></i>
          </button>
          <button type="button" class="dg-row-action dg-row-action--menu" aria-label="Menu" onclick="event.stopPropagation();showToast('Menu actions','#4338ca');">
            <i data-lucide="more-horizontal"></i>
          </button>
        </div>
      </td>
    </tr>
  `).join('');

  updateClientsPagination(list.length);

  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  } else if (typeof createIcons === 'function') {
    createIcons();
  }
  // Trigger global Lucide refresh from app.js
  document.dispatchEvent(new CustomEvent('kiveto:icons-refresh'));
}

function updateClientsPagination(total) {
  const info = document.getElementById('clients-pagination-info');
  if (info) {
    const start = (clientsState.page - 1) * clientsState.pageSize + 1;
    const end = Math.min(clientsState.page * clientsState.pageSize, total);
    info.textContent = total === 0 ? '0 sur 0' : `${start} - ${end} sur ${total}`;
  }
  const prev = document.getElementById('clients-prev');
  const next = document.getElementById('clients-next');
  if (prev) prev.disabled = clientsState.page <= 1;
  if (next) next.disabled = clientsState.page * clientsState.pageSize >= total;

  const tabCount = document.getElementById('ca-tab-count-clients');
  if (tabCount) tabCount.textContent = CLIENTS.length;
}

// ── Animals table ─────────────────────────────────────────────────
function getFilteredAnimals() {
  let list = getAllAnimals();
  const q = animalsState.search.trim().toLowerCase();

  if (animalsState.filter !== 'all') {
    list = list.filter(a => a.species === animalsState.filter);
  }
  if (animalsState.statusFilter !== 'all') {
    list = list.filter(a => a.status === animalsState.statusFilter);
  }
  if (q) {
    list = list.filter(a => {
      const hay = [a.name, a.breed, a.species, a.owner.first, a.owner.last].join(' ').toLowerCase();
      return hay.includes(q);
    });
  }
  if (animalsState.sort === 'name')   list.sort((a, b) => a.name.localeCompare(b.name));
  else if (animalsState.sort === 'recent') list.sort((a, b) => new Date(b.lastVisit) - new Date(a.lastVisit));

  return list;
}

function renderAnimalsTable() {
  const tbody = document.getElementById('animals-tbody');
  if (!tbody) return;

  const list = getFilteredAnimals();
  const start = (animalsState.page - 1) * animalsState.pageSize;
  const pageItems = list.slice(start, start + animalsState.pageSize);

  if (!pageItems.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="dg-cell-muted" style="text-align:center;padding:32px 16px;">Aucun animal trouvé</td></tr>';
    updateAnimalsPagination(list.length);
    return;
  }

  tbody.innerHTML = pageItems.map(a => `
    <tr>
      <td class="dg-th-checkbox"><input type="checkbox" class="dg-checkbox" aria-label="Sélectionner ${escapeHtml(a.name)}"/></td>
      <td>
        <div class="dg-name-cell">
          <span class="dg-avatar" style="background:#f1f5f9;color:#475569;font-size:14px;">${SPECIES_EMOJI[a.species] || '🐾'}</span>
          <a href="#" class="dg-name-link" onclick="showToast('Ouvrir ${escapeHtml(a.name)}','#4338ca');return false;">${escapeHtml(a.name)}</a>
        </div>
      </td>
      <td><span class="dg-cell-muted">${escapeHtml(a.breed || '—')}</span></td>
      <td>
        <div class="dg-name-cell">
          <span class="dg-avatar" style="background:${a.owner.bg};color:${a.owner.color};width:22px;height:22px;font-size:10px;">${escapeHtml(a.owner.initials)}</span>
          <a href="#" class="dg-name-link" style="font-weight:normal;font-size:12px;" onclick="showToast('Ouvrir ${escapeHtml(a.owner.first + ' ' + a.owner.last)}','#4338ca');return false;">${escapeHtml(a.owner.first + ' ' + a.owner.last)}</a>
        </div>
      </td>
      <td><span class="dg-cell-muted">${ageYears(a.dob)}</span></td>
      <td><span class="dg-cell-muted">${fmtDate(a.lastVisit)}</span></td>
      <td><span class="dg-cell-muted">${fmtDate(a.nextVisit)}</span></td>
      <td>${statusBadge(a.status)}</td>
      <td class="dg-th-actions">
        <div class="dg-row-actions">
          <button type="button" class="dg-row-action dg-row-action--star" aria-label="Favori" onclick="toggleStar(this);event.stopPropagation();">
            <i data-lucide="star"></i>
          </button>
          <button type="button" class="dg-row-action dg-row-action--menu" aria-label="Menu" onclick="event.stopPropagation();showToast('Menu actions','#4338ca');">
            <i data-lucide="more-horizontal"></i>
          </button>
        </div>
      </td>
    </tr>
  `).join('');

  updateAnimalsPagination(list.length);
  document.dispatchEvent(new CustomEvent('kiveto:icons-refresh'));
}

function updateAnimalsPagination(total) {
  const info = document.getElementById('animals-pagination-info');
  if (info) {
    const start = (animalsState.page - 1) * animalsState.pageSize + 1;
    const end = Math.min(animalsState.page * animalsState.pageSize, total);
    info.textContent = total === 0 ? '0 sur 0' : `${start} - ${end} sur ${total}`;
  }
  const prev = document.getElementById('animals-prev');
  const next = document.getElementById('animals-next');
  if (prev) prev.disabled = animalsState.page <= 1;
  if (next) next.disabled = animalsState.page * animalsState.pageSize >= total;

  const tabCount = document.getElementById('ca-tab-count-animals');
  if (tabCount) tabCount.textContent = getAllAnimals().length;
}

// ── Public actions (exposed to inline handlers) ───────────────────
function onClientSearch(v)   { clientsState.search = v; clientsState.page = 1; renderClientsTable(); }
function setClientFilter(f, btn) { clientsState.filter = f; clientsState.page = 1; renderClientsTable(); }
function setClientSort(s)    { clientsState.sort = s; renderClientsTable(); }
function toggleClientCol(/*col, visible*/) { /* placeholder */ }
function goClientPage(delta) {
  const total = getFilteredClients().length;
  const maxPage = Math.max(1, Math.ceil(total / clientsState.pageSize));
  clientsState.page = Math.min(maxPage, Math.max(1, clientsState.page + delta));
  renderClientsTable();
}

function onAnimalSearch(v)   { animalsState.search = v; animalsState.page = 1; renderAnimalsTable(); }
function setAnimalFilter(f, btn) { animalsState.filter = f; animalsState.page = 1; renderAnimalsTable(); }
function setAnimalStatusFilter(f, btn) { animalsState.statusFilter = f; animalsState.page = 1; renderAnimalsTable(); }
function goAnimalPage(delta) {
  const total = getFilteredAnimals().length;
  const maxPage = Math.max(1, Math.ceil(total / animalsState.pageSize));
  animalsState.page = Math.min(maxPage, Math.max(1, animalsState.page + delta));
  renderAnimalsTable();
}

function toggleStar(btn) {
  btn.classList.toggle('is-starred');
}

// ── Init / cleanup ────────────────────────────────────────────────
let _pageSizeListenersAttached = false;

export function init() {
  // Always update tab counts (they don't depend on cached DOM content)
  const tabClients = document.getElementById('ca-tab-count-clients');
  const tabAnimals = document.getElementById('ca-tab-count-animals');
  if (tabClients) tabClients.textContent = CLIENTS.length;
  if (tabAnimals) tabAnimals.textContent = getAllAnimals().length;

  // Render tables only if empty (skip when restored from Turbo cache)
  const cTbody = document.getElementById('clients-tbody');
  if (cTbody && !cTbody.children.length) renderClientsTable();

  const aTbody = document.getElementById('animals-tbody');
  if (aTbody && !aTbody.children.length) renderAnimalsTable();

  // Wire page-size selectors
  if (!_pageSizeListenersAttached) {
    const cSize = document.getElementById('clients-page-size');
    if (cSize) {
      cSize.addEventListener('change', () => {
        clientsState.pageSize = parseInt(cSize.value, 10) || 10;
        clientsState.page = 1;
        renderClientsTable();
      });
    }
    const aSize = document.getElementById('animals-page-size');
    if (aSize) {
      aSize.addEventListener('change', () => {
        animalsState.pageSize = parseInt(aSize.value, 10) || 10;
        animalsState.page = 1;
        renderAnimalsTable();
      });
    }
    _pageSizeListenersAttached = true;
  }

  // Expose functions used by inline onclick attributes
  window.showToast          = showToast;
  window.onClientSearch     = onClientSearch;
  window.setClientFilter    = setClientFilter;
  window.setClientSort      = setClientSort;
  window.toggleClientCol    = toggleClientCol;
  window.goClientPage       = goClientPage;
  window.onAnimalSearch     = onAnimalSearch;
  window.setAnimalFilter    = setAnimalFilter;
  window.setAnimalStatusFilter = setAnimalStatusFilter;
  window.goAnimalPage       = goAnimalPage;
  window.toggleStar         = toggleStar;
}

export function cleanup() {
  // Nothing destructive — keep DOM intact for Turbo cache restoration
}
