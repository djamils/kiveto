/**
 * Page module -- Select Clinic
 * Loaded by app.js dispatcher on turbo:load.
 *
 * Faithful reproduction of vetos-clinic-picker.html JS.
 * Always renders fixture clinics for the demo UI. When server-side
 * clinics are available (window.CLINICS), the form POST uses real IDs.
 */

const CLINICS = [
  { id:1, name:"Clinique Montmartre",     city:"Paris 18e",    vets:12, color:"#1e1b4b", letter:"M", isDefault:true  },
  { id:2, name:"Clinique République",     city:"Paris 11e",    vets:8,  color:"#0c4a6e", letter:"R", isDefault:false },
  { id:3, name:"Clinique Nation",         city:"Paris 12e",    vets:6,  color:"#065f46", letter:"N", isDefault:false },
  { id:4, name:"Centre Hospitalier Vét.", city:"Vincennes",    vets:22, color:"#581c87", letter:"C", isDefault:false },
  { id:5, name:"Clinique Bastille",       city:"Paris 4e",     vets:4,  color:"#7c2d12", letter:"B", isDefault:false },
  { id:6, name:"Clinique Belleville",     city:"Paris 20e",    vets:5,  color:"#134e4a", letter:"V", isDefault:false },
  { id:7, name:"Clinique Saint-Cloud",    city:"Saint-Cloud",  vets:9,  color:"#1e3a5f", letter:"S", isDefault:false },
  { id:8, name:"Clinique Boulogne",       city:"Boulogne",     vets:7,  color:"#3b1f6e", letter:"B", isDefault:false },
];

const CLINIC_META = {
  1:{ address:"14 rue Lepic, Paris 18e",               hours:{1:"8h00\u201319h30",2:"8h00\u201319h30",3:"8h00\u201319h30",4:"8h00\u201319h30",5:"8h00\u201319h30",6:"9h00\u201317h00",0:null} },
  2:{ address:"28 bd Voltaire, Paris 11e",              hours:{1:"8h30\u201318h30",2:"8h30\u201318h30",3:"8h30\u201318h30",4:"8h30\u201318h30",5:"8h30\u201318h30",6:"9h00\u201313h00",0:null} },
  3:{ address:"5 av du Trône, Paris 12e",               hours:{1:"9h00\u201318h00",2:"9h00\u201318h00",3:"9h00\u201318h00",4:"9h00\u201318h00",5:"9h00\u201318h00",6:null,0:null} },
  4:{ address:"3 rue de Fontenay, Vincennes",           hours:{1:"0h00\u201324h00",2:"0h00\u201324h00",3:"0h00\u201324h00",4:"0h00\u201324h00",5:"0h00\u201324h00",6:"0h00\u201324h00",0:"0h00\u201324h00"} },
  5:{ address:"12 rue de la Bastille, Paris 4e",        hours:{1:"9h00\u201319h00",2:"9h00\u201319h00",3:"9h00\u201319h00",4:"9h00\u201319h00",5:"9h00\u201319h00",6:"10h00\u201316h00",0:null} },
  6:{ address:"42 rue de Belleville, Paris 20e",        hours:{1:"8h00\u201320h00",2:"8h00\u201320h00",3:"8h00\u201320h00",4:"8h00\u201320h00",5:"8h00\u201320h00",6:"9h00\u201315h00",0:null} },
  7:{ address:"8 bd de la République, Saint-Cloud",     hours:{1:"9h00\u201318h30",2:"9h00\u201318h30",3:"9h00\u201318h30",4:"9h00\u201318h30",5:"9h00\u201318h30",6:null,0:null} },
  8:{ address:"67 av Jean-Baptiste Clément, Boulogne",  hours:{1:"8h30\u201319h00",2:"8h30\u201319h00",3:"8h30\u201319h00",4:"8h30\u201319h00",5:"8h30\u201319h00",6:"9h00\u201313h30",0:null} },
};

let selectedId = null;
let scrollHandler = null;

function toast(msg, color='#16a34a', icon='\u2713') {
  const t = document.getElementById('toast');
  t.innerHTML = `<span aria-hidden="true">${icon}</span><span>${msg}</span>`;
  t.style.cssText = `background:${color};opacity:1;transform:translateX(-50%) translateY(0)`;
  clearTimeout(t._t);
  t._t = setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(-50%) translateY(8px)'; }, 2500);
}

function getOpenStatus(meta) {
  if (!meta || !meta.hours) return { open:false, hours:null };
  const now = new Date(), day = now.getDay();
  const h = meta.hours[day];
  if (!h) return { open:false, hours:null };
  const toMin = s => { const [hh,mm] = s.replace('h',':').split(':'); return +hh*60+(+mm||0); };
  const parts = h.split('\u2013');
  if (parts.length < 2) return { open:false, hours:h };
  const cur = now.getHours()*60+now.getMinutes();
  return { open: cur>=toMin(parts[0])&&cur<toMin(parts[1]), hours:h };
}

function renderList(list) {
  const el = document.getElementById('clinic-list');
  if (!list.length) {
    el.innerHTML = `<div class="list-empty">
      <p style="font-size:var(--text-base);font-weight:var(--weight-medium);color:var(--text-muted)">Aucune clinique trouv\u00e9e</p>
      <p style="font-size:var(--text-md);color:var(--text-subtle)">Essayez un autre terme.</p>
    </div>`;
    updateHint(); return;
  }
  el.innerHTML = list.map(c => {
    const m = CLINIC_META[c.id]||{address:'',hours:{}};
    const s = getOpenStatus(m);
    const sel = c.id === selectedId;
    return `<div class="clinic-item${sel?' is-selected':''}" id="ci-${c.id}"
      onclick="pick(${c.id})" role="option" aria-selected="${sel}" tabindex="0"
      onkeydown="if(event.key==='Enter'||event.key===' '){pick(${c.id});event.preventDefault()}">
      <div class="clinic-logo" style="background:${c.color}" aria-hidden="true">
        ${c.letter}<span class="status-dot" style="background:${s.open?'#16a34a':'#94a3b8'}"></span>
      </div>
      <div class="clinic-info">
        <p class="clinic-name">${c.name}</p>
        <div class="clinic-meta">
          <span class="meta-item">
            <svg width="9" height="9" fill="none" viewBox="0 0 12 12" aria-hidden="true"><circle cx="6" cy="3.5" r="2" stroke="currentColor" stroke-width="1.2"/><path d="M2 10c0-2 1.8-3.5 4-3.5s4 1.5 4 3.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
            ${c.vets} v\u00e9t\u00e9rinaire${c.vets>1?'s':''}
          </span>
          <div class="meta-sep" aria-hidden="true"></div>
          <span class="meta-item">
            <svg width="9" height="9" fill="none" viewBox="0 0 12 12" aria-hidden="true"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.2"/><path d="M6 4v2l1.5 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
            ${s.hours||'Ferm\u00e9 aujourd\'hui'}
          </span>
        </div>
        <div class="clinic-address">
          <svg width="9" height="9" fill="none" viewBox="0 0 12 12" aria-hidden="true"><path d="M6 1a4 4 0 014 4c0 3-4 7-4 7S2 8 2 5a4 4 0 014-4z" stroke="currentColor" stroke-width="1.2"/></svg>
          ${m.address}
        </div>
      </div>
      <div class="clinic-right">
        <span class="clinic-status" style="background:${s.open?'var(--color-success-bg)':'var(--surface-subtle)'};color:${s.open?'var(--color-success-text)':'var(--text-subtle)'}">
          ${s.open?'Ouvert':'Ferm\u00e9'}
        </span>
        <div class="clinic-check" aria-hidden="true">
          <svg width="11" height="11" fill="none" viewBox="0 0 12 12"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
      </div>
    </div>`;
  }).join('');
  updateHint();
}

function updateHint() {
  const list = document.getElementById('clinic-list');
  const hint = document.getElementById('scroll-hint');
  const zone = document.getElementById('list-zone');
  const more = list.scrollHeight > list.clientHeight + 8;
  hint.classList.toggle('hidden', !more);
  zone.classList.toggle('at-bottom', !more);
}

export function init() {
  selectedId = null;

  window.toast = toast;

  window.filterList = function(val) {
    const q = val.toLowerCase().trim();
    renderList(CLINICS.filter(c => !q||c.name.toLowerCase().includes(q)||c.city.toLowerCase().includes(q)));
  };

  window.pick = function(id) {
    selectedId = id;
    document.querySelectorAll('.clinic-item').forEach(el => {
      const mine = el.id===`ci-${id}`;
      el.classList.toggle('is-selected', mine);
      el.setAttribute('aria-selected', mine);
    });
    const c = CLINICS.find(x=>x.id===id);
    document.getElementById('sel-dot').classList.add('active');
    const lbl = document.getElementById('sel-label');
    lbl.textContent = c.name; lbl.classList.add('active');
    document.getElementById('enter-btn').disabled = false;
  };

  window.enter = function() {
    if (!selectedId) return;
    // If server clinics exist, submit the form with real clinic ID
    const serverClinics = window.CLINICS || [];
    const form = document.getElementById('clinic-select-form');
    if (form && serverClinics.length > 0) {
      // Map fixture ID to server clinic (by position)
      const serverClinic = serverClinics[selectedId - 1] || serverClinics[0];
      document.getElementById('clinic-select-input').value = serverClinic.id;
      form.submit();
    } else {
      toast('Clinique sélectionnée !', '#4338ca', '\u2713');
    }
  };

  const clinicList = document.getElementById('clinic-list');
  scrollHandler = function() {
    const atBottom = clinicList.scrollHeight - clinicList.scrollTop - clinicList.clientHeight < 20;
    document.getElementById('list-zone').classList.toggle('at-bottom', atBottom);
    document.getElementById('scroll-hint').classList.toggle('hidden', atBottom);
  };
  clinicList.addEventListener('scroll', scrollHandler);

  renderList(CLINICS);
}

export function cleanup() {
  const clinicList = document.getElementById('clinic-list');
  if (clinicList && scrollHandler) clinicList.removeEventListener('scroll', scrollHandler);
  scrollHandler = null;
  selectedId = null;
  delete window.toast;
  delete window.filterList;
  delete window.pick;
  delete window.enter;
}
