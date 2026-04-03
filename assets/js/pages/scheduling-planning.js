/**
 * Page module — Scheduling Planning
 * Loaded by app.js dispatcher on turbo:load.
 */

// =============== DATA ===============
const VETS = {
  rousseau: { name:'Dr. Rousseau', color:'#4338ca', bg:'#eef2ff' },
  martin:   { name:'Dr. Martin',   color:'#0891b2', bg:'#ecfeff' },
  dupont:   { name:'Dr. Dupont',   color:'#059669', bg:'#ecfdf5' },
  lambert:  { name:'Dr. Lambert',  color:'#db2777', bg:'#fdf2f8' },
};

const ACTIVITY_TYPES = [
  { id:'consultation', label:'Consultation',  color:'#4338ca', bg:'#eef2ff',  icon:'🩺', cap:3 },
  { id:'chirurgie',    label:'Chirurgie',      color:'#b45309', bg:'#fef3c7',  icon:'🔪', cap:1 },
  { id:'garde',        label:'Garde',          color:'#7c3aed', bg:'#f5f3ff',  icon:'🌙', cap:2 },
  { id:'bilan',        label:'Bilans / Labo',  color:'#059669', bg:'#ecfdf5',  icon:'🔬', cap:4 },
  { id:'formation',    label:'Formation',      color:'#0891b2', bg:'#ecfeff',  icon:'📚', cap:0 },
  { id:'conge',        label:'Congé / Absent', color:'#dc2626', bg:'#fef2f2',  icon:'🏖️', cap:0 },
  { id:'admin',        label:'Administratif',  color:'#64748b', bg:'#f8fafc',  icon:'📋', cap:0 },
  { id:'urgence',      label:'Urgences',       color:'#ea580c', bg:'#fff7ed',  icon:'🚨', cap:5 },
];

// Planning blocks — { id, vet, date, start, end, type, capacity, note, recurrence }
let blocks = [
  // Week 23-27 March — realistic example
  // Rousseau
  { id:1,  vet:'rousseau', date:'2026-03-23', start:'08:00', end:'12:00', type:'chirurgie',    capacity:1, note:'Bloc opératoire salle 1', recurrence:'none' },
  { id:2,  vet:'rousseau', date:'2026-03-23', start:'13:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:3,  vet:'rousseau', date:'2026-03-24', start:'08:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:4,  vet:'rousseau', date:'2026-03-25', start:'08:00', end:'12:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:5,  vet:'rousseau', date:'2026-03-25', start:'13:00', end:'17:00', type:'chirurgie',    capacity:1, note:'', recurrence:'none' },
  { id:6,  vet:'rousseau', date:'2026-03-26', start:'08:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:7,  vet:'rousseau', date:'2026-03-27', start:'08:00', end:'14:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:8,  vet:'rousseau', date:'2026-03-28', start:'08:00', end:'12:00', type:'consultation', capacity:2, note:'Permanence samedi', recurrence:'none' },
  // Martin
  { id:9,  vet:'martin',   date:'2026-03-23', start:'08:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:10, vet:'martin',   date:'2026-03-24', start:'08:00', end:'12:00', type:'bilan',        capacity:4, note:'Résultats labo', recurrence:'none' },
  { id:11, vet:'martin',   date:'2026-03-24', start:'13:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:12, vet:'martin',   date:'2026-03-25', start:'08:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:13, vet:'martin',   date:'2026-03-26', start:'08:00', end:'12:00', type:'chirurgie',    capacity:1, note:'', recurrence:'none' },
  { id:14, vet:'martin',   date:'2026-03-26', start:'13:00', end:'19:00', type:'urgence',      capacity:5, note:'Astreinte urgences', recurrence:'none' },
  { id:15, vet:'martin',   date:'2026-03-27', start:'08:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:16, vet:'martin',   date:'2026-03-28', start:'08:00', end:'12:00', type:'consultation', capacity:2, note:'', recurrence:'none' },
  // Dupont
  { id:17, vet:'dupont',   date:'2026-03-23', start:'08:00', end:'12:00', type:'chirurgie',    capacity:1, note:'', recurrence:'none' },
  { id:18, vet:'dupont',   date:'2026-03-23', start:'14:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:19, vet:'dupont',   date:'2026-03-24', start:'09:00', end:'17:00', type:'formation',    capacity:0, note:'Formation échographie', recurrence:'none' },
  { id:20, vet:'dupont',   date:'2026-03-25', start:'08:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:21, vet:'dupont',   date:'2026-03-26', start:'08:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:22, vet:'dupont',   date:'2026-03-27', start:'08:00', end:'12:00', type:'bilan',        capacity:4, note:'', recurrence:'none' },
  { id:23, vet:'dupont',   date:'2026-03-27', start:'13:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  // Lambert
  { id:24, vet:'lambert',  date:'2026-03-23', start:'08:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:25, vet:'lambert',  date:'2026-03-24', start:'08:00', end:'19:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:26, vet:'lambert',  date:'2026-03-25', start:'00:00', end:'23:59', type:'conge',        capacity:0, note:'Congé annuel', recurrence:'none' },
  { id:27, vet:'lambert',  date:'2026-03-26', start:'00:00', end:'23:59', type:'conge',        capacity:0, note:'Congé annuel', recurrence:'none' },
  { id:28, vet:'lambert',  date:'2026-03-27', start:'08:00', end:'14:00', type:'consultation', capacity:3, note:'', recurrence:'none' },
  { id:29, vet:'lambert',  date:'2026-03-27', start:'14:00', end:'18:00', type:'admin',        capacity:0, note:'Réunion équipe', recurrence:'none' },
  { id:30, vet:'lambert',  date:'2026-03-28', start:'08:00', end:'12:00', type:'garde',        capacity:2, note:'Garde weekend', recurrence:'none' },
];

let nextId = 31;

// =============== STATE ===============
const MONTHS_FR = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const DOW_FR = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

let today = new Date(2026, 2, 23);
let currentDate = new Date(2026, 2, 23);
let currentView = 'day';
let miniCalDate = new Date(2026, 2, 1);
let activeVets = new Set(['rousseau','martin','dupont','lambert']);
let editingBlockId = null;
let selectedActivityType = 'consultation';
let dragState = null; // { vetKey, dateStr, startMin }

// =============== UTILS ===============
function fmtDate(d){ return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; }
function addDays(d, n){ const r=new Date(d); r.setDate(r.getDate()+n); return r; }
function getWeekStart(d){ const r=new Date(d); const day=(r.getDay()+6)%7; r.setDate(r.getDate()-day); return r; }
function timeToMin(t){ const [h,m]=t.split(':').map(Number); return h*60+m; }
function minToTime(m){ return `${String(Math.floor(m/60)).padStart(2,'0')}:${String(m%60).padStart(2,'0')}`; }
function getActivityType(id){ return ACTIVITY_TYPES.find(a=>a.id===id) || ACTIVITY_TYPES[0]; }

function showToast(msg, color='#16a34a'){
  const t = document.getElementById('toast');
  t.textContent = msg; t.style.background = color;
  t.style.opacity='1'; t.style.transform='translateX(-50%) translateY(0)';
  setTimeout(()=>{ t.style.opacity='0'; t.style.transform='translateX(-50%) translateY(8px)'; }, 2500);
}

// =============== MINI CAL ===============
function renderMiniCal(){
  const y = miniCalDate.getFullYear(), m = miniCalDate.getMonth();
  document.getElementById('mini-month').textContent = `${MONTHS_FR[m]} ${y}`;
  const grid = document.getElementById('mini-cal-grid');
  grid.innerHTML = '';
  const first = new Date(y,m,1);
  const startDow = (first.getDay()+6)%7;
  const days = new Date(y,m+1,0).getDate();
  const blockDates = new Set(blocks.map(b=>b.date));

  for(let i=0;i<startDow;i++){
    const prev = new Date(y,m,1-startDow+i);
    const el = document.createElement('div');
    el.className='mini-cal-day other-month';
    el.textContent = prev.getDate();
    el.onclick=()=>{ currentDate=prev; currentView='day'; setView('day'); renderMiniCal(); };
    grid.appendChild(el);
  }
  for(let d=1;d<=days;d++){
    const el = document.createElement('div');
    el.className='mini-cal-day';
    el.textContent=d;
    const thisDate=new Date(y,m,d);
    const ds=fmtDate(thisDate);
    if(ds===fmtDate(today)) el.classList.add('today');
    if(ds===fmtDate(currentDate) && currentView!=='month') el.classList.add('selected');
    if(blockDates.has(ds)) el.classList.add('has-blocks');
    el.onclick=()=>{ currentDate=thisDate; if(currentView==='month') setView('day'); else renderPlanning(); renderMiniCal(); };
    grid.appendChild(el);
  }
}
function miniCalPrev(){ miniCalDate.setMonth(miniCalDate.getMonth()-1); renderMiniCal(); }
function miniCalNext(){ miniCalDate.setMonth(miniCalDate.getMonth()+1); renderMiniCal(); }

// =============== LEGEND ===============
function renderLegend(){
  const el = document.getElementById('legend-items');
  el.innerHTML = ACTIVITY_TYPES.map(at=>`
    <div style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;background:${at.bg};border:1px solid ${at.color}30;">
      <span style="font-size:10px;">${at.icon}</span>
      <span style="font-size:10.5px;font-weight:500;color:${at.color};white-space:nowrap;">${at.label}</span>
    </div>`).join('');
}

// =============== VET FILTER ===============
function toggleVet(el, vet){
  if(activeVets.has(vet)) activeVets.delete(vet);
  else activeVets.add(vet);
  const check = document.getElementById('check-'+vet);
  if(check) check.style.opacity = activeVets.has(vet) ? '1' : '0.15';
  renderPlanning();
}

// =============== VIEW ===============
function setView(v){
  currentView = v;
  ['day','week','month'].forEach(x=>{
    document.getElementById('view-'+x+'-btn').classList.toggle('active', x===v);
  });
  renderPlanning();
  renderMiniCal();
}
function prevPeriod(){ currentDate = addDays(currentDate, currentView==='week'?-7:currentView==='month'?-30:-1); renderPlanning(); renderMiniCal(); }
function nextPeriod(){ currentDate = addDays(currentDate, currentView==='week'?7:currentView==='month'?30:1); renderPlanning(); renderMiniCal(); }
function goToday(){ currentDate=new Date(today); renderPlanning(); renderMiniCal(); }

// =============== RENDER ===============
function renderPlanning(){
  if(currentView==='day')   renderDayView();
  else if(currentView==='week') renderWeekView();
  else renderMonthView();
}

const DAY_START = 0;
const DAY_END   = 24;
const HOUR_W    = 80; // px per hour in day/week view
const ROW_H     = 64; // px per vet row

// Default opening hours (configurable)
const CLINIC_OPEN  = 8;
const CLINIC_CLOSE = 20;

// ---- DAY VIEW ----
function renderDayView(){
  const wrap = document.getElementById('planning-wrap');
  const d = currentDate;
  const DOW = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
  document.getElementById('period-label').textContent = `${DOW[d.getDay()]} ${d.getDate()} ${MONTHS_FR[d.getMonth()]} ${d.getFullYear()}`;

  const totalW = (DAY_END - DAY_START) * HOUR_W;
  const vets = Object.entries(VETS).filter(([k])=>activeVets.has(k));
  const dateStr = fmtDate(d);

  // Everything in a single horizontally scrollable container
  let html = `<div style="position:absolute;inset:0;overflow:auto;display:flex;flex-direction:column;" id="day-scroll">
    <!-- Hour header — sticky top, scrolls with content -->
    <div style="display:flex;flex-shrink:0;background:#fff;border-bottom:1px solid #e8edf2;position:sticky;top:0;z-index:30;min-width:${totalW + 120}px;">
      <div style="width:120px;flex-shrink:0;background:#fff;border-right:1px solid #e8edf2;"></div>
      <div style="display:flex;width:${totalW}px;flex-shrink:0;">`;

  for(let h=DAY_START;h<DAY_END;h++){
    const isOpen = h>=CLINIC_OPEN && h<CLINIC_CLOSE;
    html+=`<div style="width:${HOUR_W}px;flex-shrink:0;padding:7px 6px 5px;border-left:1px solid ${h===DAY_START?'transparent':'#e8edf2'};${isOpen?'':'background:#fafafa;'}">
      <span style="font-size:11.5px;font-weight:${isOpen?'600':'400'};color:${isOpen?'#334155':'#cbd5e1'};">${String(h).padStart(2,'0')}h</span>
    </div>`;
  }
  html+=`</div></div>
    <!-- Rows -->
    <div style="display:flex;flex-direction:column;min-width:${totalW + 120}px;">`;

  vets.forEach(([vetKey, vet])=>{
    const dayBlocks = blocks.filter(b=>b.vet===vetKey && b.date===dateStr).sort((a,b)=>timeToMin(a.start)-timeToMin(b.start));
    html+=renderVetRow(vetKey, vet, dateStr, dayBlocks, totalW);
  });

  html+=`</div></div>`;
  wrap.innerHTML=html;

  // Auto-scroll to clinic opening hour
  const scroller = document.getElementById('day-scroll');
  if(scroller){
    scroller.scrollLeft = CLINIC_OPEN * HOUR_W - 10;
    // Horizontal scroll with mouse wheel
    scroller.addEventListener('wheel', (e) => {
      if(e.deltaY !== 0){
        e.preventDefault();
        scroller.scrollLeft += e.deltaY;
      }
    }, { passive: false });
  }
}

// ---- WEEK VIEW ----
function renderWeekView(){
  const wrap = document.getElementById('planning-wrap');
  const ws = getWeekStart(currentDate);
  const we = addDays(ws,6);
  document.getElementById('period-label').textContent = `${ws.getDate()} – ${we.getDate()} ${MONTHS_FR[we.getMonth()]} ${we.getFullYear()}`;

  const vets = Object.entries(VETS).filter(([k])=>activeVets.has(k));
  const days = Array.from({length:7},(_,i)=>addDays(ws,i));

  let html=`<div class="planning-body"><div style="display:flex;flex-direction:column;">`;

  // Header
  html+=`<div style="display:flex;background:#fff;border-bottom:1px solid #e8edf2;position:sticky;top:0;z-index:30;flex-shrink:0;">
    <div style="width:120px;flex-shrink:0;border-right:1px solid #e8edf2;"></div>`;
  days.forEach(d=>{
    const isToday=fmtDate(d)===fmtDate(today);
    html+=`<div style="flex:1;padding:8px 0;text-align:center;border-left:1px solid #dde3ea;${isToday?'background:#f5f3ff;':''}">
      <div style="font-size:10.5px;font-weight:600;color:${isToday?'#4338ca':'#94a3b8'};text-transform:uppercase;letter-spacing:.05em;border-left:1px solid #dde3ea;">${DOW_FR[(d.getDay()+6)%7]}</div>
      <div style="font-size:17px;font-weight:${isToday?'800':'600'};color:${isToday?'#4338ca':'#0f172a'};margin-top:1px;">${d.getDate()}</div>
    </div>`;
  });
  html+=`<div style="width:6px;"></div></div>`;

  // Vet rows x 7 days
  vets.forEach(([vetKey, vet])=>{
    html+=`<div style="display:flex;border-bottom:1px solid #e8edf2;">
      <div style="width:120px;flex-shrink:0;background:#fff;border-right:1px solid #e8edf2;padding:10px 12px;display:flex;flex-direction:column;justify-content:center;gap:2px;min-height:60px;">
        <div style="display:flex;align-items:center;gap:6px;">
          <div style="width:8px;height:8px;border-radius:50%;background:${vet.color};flex-shrink:0;"></div>
          <span style="font-size:12px;font-weight:500;color:#0f172a;">${vet.name.replace('Dr. ','')}</span>
        </div>
      </div>`;
    days.forEach(d=>{
      const dateStr=fmtDate(d);
      const dayBlocks=blocks.filter(b=>b.vet===vetKey&&b.date===dateStr).sort((a,b)=>timeToMin(a.start)-timeToMin(b.start));
      const isToday=fmtDate(d)===fmtDate(today);
      html+=`<div style="flex:1;border-left:1px solid #dde3ea;padding:6px;min-height:60px;${isToday?'background:#fdfcff;':''}cursor:pointer;transition:background .1s;" onclick="openBlockPopup('${vetKey}','${dateStr}',null)" onmouseenter="if(!event.target.closest('.act-block-pill'))this.style.background='#f8fafc'" onmouseleave="if(!event.target.closest('.act-block-pill'))this.style.background='${isToday?'#fdfcff':''}'">
        <div style="display:flex;flex-direction:column;gap:3px;">`;
      dayBlocks.forEach(b=>{
        const at=getActivityType(b.type);
        html+=`<div class="act-block-pill" onclick="event.stopPropagation();openBlockPopup('${b.vet}','${b.date}',${b.id})" style="padding:3px 8px;border-radius:5px;background:${at.bg};border-left:2px solid ${at.color};cursor:pointer;transition:filter .1s;" onmouseenter="this.style.filter='brightness(.95)'" onmouseleave="this.style.filter=''">
          <div style="font-size:10.5px;font-weight:600;color:${at.color};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${at.icon} ${b.start.replace(':','h')}–${b.end.replace(':','h')}</div>
          <div style="font-size:10px;color:${at.color};opacity:.8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${at.label}</div>
        </div>`;
      });
      if(dayBlocks.length===0){
        html+=`<div style="font-size:10.5px;color:#cbd5e1;text-align:center;padding:6px 0;">+ Ajouter</div>`;
      }
      html+=`</div></div>`;
    });
    html+=`<div style="width:6px;background:#fff;"></div></div>`;
  });

  html+=`</div></div>`;
  wrap.innerHTML=html;
}

// ---- MONTH VIEW ----
function renderMonthView(){
  const wrap = document.getElementById('planning-wrap');
  const y=currentDate.getFullYear(), m=currentDate.getMonth();
  document.getElementById('period-label').textContent = `${MONTHS_FR[m]} ${y}`;

  const vets = Object.entries(VETS).filter(([k])=>activeVets.has(k));
  const days=new Date(y,m+1,0).getDate();
  const firstDow=(new Date(y,m,1).getDay()+6)%7;

  let html=`<div class="planning-body"><div style="display:flex;flex-direction:column;">`;

  // Weekday header
  html+=`<div style="display:flex;background:#fff;border-bottom:1px solid #e8edf2;position:sticky;top:0;z-index:20;flex-shrink:0;">
    <div style="width:120px;flex-shrink:0;border-right:1px solid #e8edf2;"></div>`;
  DOW_FR.forEach(dw=>html+=`<div style="flex:1;padding:8px 0;text-align:center;border-left:1px solid #f1f5f9;font-size:10.5px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;border-left:1px solid #dde3ea;">${dw}</div>`);
  html+=`<div style="width:6px;"></div></div>`;

  // Rows per vet
  vets.forEach(([vetKey,vet])=>{
    html+=`<div style="display:flex;border-bottom:1px solid #e8edf2;">
      <div style="width:120px;flex-shrink:0;background:#fff;border-right:1px solid #e8edf2;padding:10px 12px;display:flex;flex-direction:column;justify-content:center;gap:2px;">
        <div style="display:flex;align-items:center;gap:6px;">
          <div style="width:8px;height:8px;border-radius:50%;background:${vet.color};flex-shrink:0;"></div>
          <span style="font-size:12px;font-weight:500;color:#0f172a;">${vet.name.replace('Dr. ','')}</span>
        </div>
      </div>
      <div style="flex:1;display:grid;grid-template-columns:repeat(7,1fr);">`;

    // Empty cells before month start
    for(let i=0;i<firstDow;i++) html+=`<div style="border-left:1px solid #dde3ea;background:#fafafa;min-height:52px;"></div>`;

    for(let d=1;d<=days;d++){
      const dateStr=`${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const dayBlocks=blocks.filter(b=>b.vet===vetKey&&b.date===dateStr);
      const isToday=dateStr===fmtDate(today);

      html+=`<div style="border-left:1px solid #dde3ea;padding:4px 5px;min-height:52px;cursor:pointer;transition:background .1s;${isToday?'background:#fdfcff;':''}" onclick="openBlockPopup('${vetKey}','${dateStr}',null)" onmouseenter="this.style.background='#f8fafc'" onmouseleave="this.style.background='${isToday?'#fdfcff':''}'">
        <div style="font-size:10px;font-weight:${isToday?'700':'400'};color:${isToday?'#4338ca':'#94a3b8'};margin-bottom:2px;">${d}</div>`;

      dayBlocks.forEach(b=>{
        const at=getActivityType(b.type);
        html+=`<div onclick="event.stopPropagation();openBlockPopup('${b.vet}','${b.date}',${b.id})" style="padding:1px 4px;border-radius:3px;background:${at.bg};border-left:2px solid ${at.color};margin-bottom:1px;cursor:pointer;transition:filter .1s;" onmouseenter="this.style.filter='brightness(.93)'" onmouseleave="this.style.filter=''">
          <span style="font-size:9.5px;font-weight:600;color:${at.color};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;">${at.icon} ${at.label}</span>
        </div>`;
      });

      if(dayBlocks.length===0) html+=`<div style="font-size:9.5px;color:#e2e8f0;text-align:center;margin-top:4px;">+</div>`;
      html+=`</div>`;
    }

    // Fill remaining cells
    const total = firstDow + days;
    const rem = total%7===0 ? 0 : 7-(total%7);
    for(let i=0;i<rem;i++) html+=`<div style="border-left:1px solid #dde3ea;background:#fafafa;min-height:52px;"></div>`;

    html+=`</div><div style="width:6px;background:#fff;"></div></div>`;
  });

  html+=`</div></div>`;
  wrap.innerHTML=html;
}

// ---- VET ROW (day view) ----
function renderVetRow(vetKey, vet, dateStr, dayBlocks, totalW){
  const totalHours = DAY_END - DAY_START;

  let row = `<div class="vet-row">
    <div class="vet-label">
      <div style="display:flex;align-items:center;gap:6px;">
        <div style="width:8px;height:8px;border-radius:50%;background:${vet.color};flex-shrink:0;"></div>
        <span style="font-size:12px;font-weight:500;color:#0f172a;">${vet.name.replace('Dr. ','')}</span>
      </div>
      <span style="font-size:10.5px;color:#94a3b8;">${vet.name.split(' ')[0]} ${vet.name.split(' ')[1]}</span>
    </div>
    <div class="vet-row-grid" id="grid-${vetKey}-${dateStr}" style="position:relative;height:${ROW_H}px;width:${totalW}px;cursor:pointer;user-select:none;"
         onmousedown="dragStart(event,'${vetKey}','${dateStr}')"
         onmousemove="dragMove(event,'${vetKey}','${dateStr}')"
         onmouseup="dragEnd(event,'${vetKey}','${dateStr}')"
         onmouseleave="dragCancel('${vetKey}','${dateStr}')">

      <!-- Grid background: hour + half-hour separators -->`;

  for(let h=DAY_START;h<DAY_END;h++){
    const left=(h-DAY_START)*HOUR_W;
    const isOpen = h>=CLINIC_OPEN && h<CLINIC_CLOSE;
    row+=`<div style="position:absolute;top:0;left:${left}px;width:${HOUR_W}px;height:100%;border-left:1px solid #c9d0d8;pointer-events:none;${isOpen?'':'background:rgba(0,0,0,.025);'}">
      <div style="position:absolute;top:0;left:50%;width:0;height:100%;border-left:1px dashed #dde2e7;"></div>
    </div>`;
  }

  // Preview block (hidden by default)
  row+=`<div id="drag-preview-${vetKey}-${dateStr}" style="display:none;position:absolute;top:6px;bottom:6px;border-radius:5px;background:rgba(99,102,241,.08);border:1.5px dashed #818cf8;pointer-events:none;z-index:5;">
    <div id="drag-label-${vetKey}-${dateStr}" style="padding:2px 6px;font-size:10px;font-weight:600;color:#6366f1;white-space:nowrap;"></div>
  </div>`;

  // Activity blocks
  dayBlocks.forEach(b=>{
    const at = getActivityType(b.type);
    const startMin = timeToMin(b.start);
    const endMin   = timeToMin(b.end);
    const leftPx   = (startMin - DAY_START*60) / 60 * HOUR_W;
    const widthPx  = (endMin - startMin) / 60 * HOUR_W;
    if(leftPx<0||widthPx<=0) return;

    const capLabel = at.cap>0 ? `${b.capacity} RDV/h` : '';
    const noteLabel = b.note ? ` · ${b.note}` : '';

    row+=`<div class="act-block" style="left:${leftPx+2}px;width:${widthPx-4}px;background:${at.bg};border-left-color:${at.color};" onclick="openBlockPopup('${b.vet}','${b.date}',${b.id})">
      <div class="act-block-inner">
        <div class="act-block-type" style="color:${at.color};">${at.icon} ${at.label}</div>
        <div class="act-block-time" style="color:${at.color};">${b.start.replace(':','h')} – ${b.end.replace(':','h')}${noteLabel}</div>
        ${capLabel ? `<div class="act-block-cap" style="color:${at.color};">${capLabel}</div>` : ''}
      </div>
    </div>`;
  });

  row+=`</div></div>`;
  return row;
}

// =============== DRAG TO SELECT ===============
function snapTo30(min){ return Math.floor(min / 30) * 30; }

function xToMin(e, gridEl){
  const rect = gridEl.getBoundingClientRect();
  const relX = Math.max(0, e.clientX - rect.left);
  const rawMin = DAY_START * 60 + relX / (HOUR_W / 60);
  return Math.floor(rawMin / 30) * 30;
}

function xToMinEnd(e, gridEl){
  const rect = gridEl.getBoundingClientRect();
  const relX = Math.max(0, e.clientX - rect.left);
  const rawMin = DAY_START * 60 + relX / (HOUR_W / 60);
  return Math.min(Math.ceil(rawMin / 30) * 30, DAY_END * 60);
}

function getGrid(vetKey, dateStr){
  return document.getElementById(`grid-${vetKey}-${dateStr}`);
}

function updatePreview(vetKey, dateStr, startMin, endMin){
  const preview = document.getElementById(`drag-preview-${vetKey}-${dateStr}`);
  const label   = document.getElementById(`drag-label-${vetKey}-${dateStr}`);
  if(!preview) return;
  const pxPerMin = HOUR_W / 60;
  const leftPx  = (startMin - DAY_START * 60) * pxPerMin;
  const widthPx = (endMin - startMin) * pxPerMin;
  preview.style.display = 'block';
  preview.style.left  = `${leftPx}px`;
  preview.style.width = `${widthPx}px`;
  label.textContent   = `${minToTime(startMin)} – ${minToTime(endMin)}`;
}

function hidePreview(vetKey, dateStr){
  const preview = document.getElementById(`drag-preview-${vetKey}-${dateStr}`);
  if(preview) preview.style.display = 'none';
}

function dragStart(e, vetKey, dateStr){
  if(e.button !== 0) return;
  e.preventDefault();
  const grid = getGrid(vetKey, dateStr);
  const startMin = xToMin(e, grid);
  dragState = { vetKey, dateStr, startMin, endMin: startMin + 30 };
  updatePreview(vetKey, dateStr, dragState.startMin, dragState.endMin);
}

function dragMove(e, vetKey, dateStr){
  const grid = getGrid(vetKey, dateStr);
  if(!dragState || dragState.vetKey !== vetKey || dragState.dateStr !== dateStr) {
    if(!dragState){
      const hoverMin = xToMin(e, grid);
      const hoverEnd = Math.min(hoverMin + 30, DAY_END * 60);
      updatePreview(vetKey, dateStr, hoverMin, hoverEnd);
    }
    return;
  }
  e.preventDefault();
  const curMin = xToMinEnd(e, grid);
  dragState.endMin = Math.max(curMin, dragState.startMin + 30);
  updatePreview(vetKey, dateStr, dragState.startMin, dragState.endMin);
}

function dragEnd(e, vetKey, dateStr){
  if(!dragState) {
    // Simple click — open popup at clicked time
    const grid = getGrid(vetKey, dateStr);
    const clickMin = xToMin(e, grid);
    const snapMin = Math.round(clickMin / 30) * 30;
    updatePreview(vetKey, dateStr, snapMin, snapMin + 60);
    openBlockPopup(vetKey, dateStr, null, snapMin, snapMin + 60);
    return;
  }
  const { startMin, endMin } = dragState;
  dragState = null;
  // Keep preview visible while popup is open
  openBlockPopup(vetKey, dateStr, null, startMin, endMin);
}

function dragCancel(vetKey, dateStr){
  if(document.getElementById('block-overlay').classList.contains('open')) return;
  if(dragState && dragState.vetKey === vetKey && dragState.dateStr === dateStr){
    dragState = null;
  }
  hidePreview(vetKey, dateStr);
}

// =============== POPUP ===============
function renderActivityTypeGrid(selected){
  const grid = document.getElementById('activity-type-grid');
  grid.innerHTML = ACTIVITY_TYPES.map(at=>`
    <button onclick="selectActivityType('${at.id}')" id="at-${at.id}" style="padding:8px 6px;border-radius:8px;border:1.5px solid ${at.id===selected?at.color:'#e2e8f0'};background:${at.id===selected?at.bg:'#f8fafc'};cursor:pointer;font-family:inherit;transition:all .1s;text-align:center;">
      <div style="font-size:16px;margin-bottom:2px;">${at.icon}</div>
      <div style="font-size:10.5px;font-weight:600;color:${at.id===selected?at.color:'#64748b'};">${at.label}</div>
    </button>`).join('');
}

function selectActivityType(id){
  selectedActivityType = id;
  renderActivityTypeGrid(id);
  // Show/hide capacity row
  const capRow = document.getElementById('popup-capacity-row');
  const at = getActivityType(id);
  capRow.style.display = at.cap > 0 ? 'block' : 'none';
}

function openBlockPopup(vetKey, dateStr, blockIdOrHour, dragStartMin, dragEndMin){
  editingBlockId = null;
  selectedActivityType = 'consultation';

  const overlay = document.getElementById('block-overlay');
  const title   = document.getElementById('popup-title');
  const delBtn  = document.getElementById('popup-delete-btn');

  // Pre-fill
  if(vetKey) document.getElementById('popup-vet').value = vetKey;
  if(dateStr && dateStr.includes('-')) document.getElementById('popup-date').value = dateStr;

  if(typeof blockIdOrHour === 'number'){
    // Edit existing
    const b = blocks.find(x=>x.id===blockIdOrHour);
    if(!b) return;
    editingBlockId = b.id;
    selectedActivityType = b.type;
    title.textContent = 'Modifier le bloc';
    delBtn.style.display = '';
    document.getElementById('popup-vet').value     = b.vet;
    document.getElementById('popup-date').value    = b.date;
    document.getElementById('popup-start').value   = b.start;
    document.getElementById('popup-end').value     = b.end;
    document.getElementById('popup-capacity').value= b.capacity;
    document.getElementById('popup-capacity-val').textContent = b.capacity;
    document.getElementById('popup-recurrence').value = b.recurrence;
    document.getElementById('popup-note').value    = b.note;
  } else {
    // New
    title.textContent = 'Nouveau bloc';
    delBtn.style.display = 'none';
    const startVal = dragStartMin != null ? minToTime(dragStartMin) : (typeof blockIdOrHour==='string' ? blockIdOrHour : '08:00');
    const endVal   = dragEndMin   != null ? minToTime(dragEndMin)   : minToTime(Math.min(timeToMin(startVal) + 240, 24 * 60));
    document.getElementById('popup-start').value = startVal;
    document.getElementById('popup-end').value   = endVal;
    document.getElementById('popup-capacity').value = 3;
    document.getElementById('popup-capacity-val').textContent = 3;
    document.getElementById('popup-recurrence').value = 'none';
    document.getElementById('popup-note').value = '';
  }

  renderActivityTypeGrid(selectedActivityType);
  selectActivityType(selectedActivityType);
  updateCapacityDisplay(document.getElementById('popup-capacity'));

  overlay.classList.add('open');
}

function closePopup(){
  document.getElementById('block-overlay').classList.remove('open');
  // Preview stays visible — disappears when clicking elsewhere
}

function updateCapacityDisplay(el){
  const v = +el.value;
  document.getElementById('popup-capacity-val').textContent = v;
  const pct = (v-1)/7*100;
  el.style.background = `linear-gradient(to right,#4338ca 0%,#4338ca ${pct}%,#e2e8f0 ${pct}%,#e2e8f0 100%)`;
}

function saveBlock(){
  const vet   = document.getElementById('popup-vet').value;
  const date  = document.getElementById('popup-date').value;
  const start = document.getElementById('popup-start').value;
  const end   = document.getElementById('popup-end').value;
  const cap   = +document.getElementById('popup-capacity').value;
  const rec   = document.getElementById('popup-recurrence').value;
  const note  = document.getElementById('popup-note').value.trim();

  if(!date||!start||!end||timeToMin(start)>=timeToMin(end)){
    showToast('Vérifiez les horaires', '#dc2626'); return;
  }

  if(editingBlockId){
    const b = blocks.find(x=>x.id===editingBlockId);
    if(b){ Object.assign(b, {vet,date,start,end,type:selectedActivityType,capacity:cap,recurrence:rec,note}); }
    showToast('Bloc modifié', '#4338ca');
  } else {
    // Handle recurrence
    const datesToAdd = [date];
    if(rec==='weekdays'){
      // Add for all weekdays of the month
      const d0=new Date(date);
      for(let i=1;i<28;i++){
        const nd=addDays(d0,i);
        const dow=nd.getDay();
        if(dow>=1&&dow<=5) datesToAdd.push(fmtDate(nd));
      }
    } else if(rec==='weekly'){
      const d0=new Date(date);
      for(let i=1;i<8;i++) datesToAdd.push(fmtDate(addDays(d0,i*7)));
    } else if(rec==='daily'){
      const d0=new Date(date);
      for(let i=1;i<7;i++) datesToAdd.push(fmtDate(addDays(d0,i)));
    }
    datesToAdd.forEach(dt=>{
      blocks.push({id:nextId++,vet,date:dt,start,end,type:selectedActivityType,capacity:cap,recurrence:rec,note});
    });
    showToast(datesToAdd.length>1?`${datesToAdd.length} blocs créés`:'Bloc créé', '#059669');
  }

  closePopup();
  document.querySelectorAll('[id^="drag-preview-"]').forEach(function(el){ el.style.display='none'; });
  renderPlanning();
  renderMiniCal();
}

function deleteBlock(){
  if(!editingBlockId) return;
  blocks = blocks.filter(b=>b.id!==editingBlockId);
  closePopup();
  renderPlanning();
  renderMiniCal();
  showToast('Bloc supprimé', '#64748b');
}

// ---- Sidebar drawer ----
function toggleSidebar(){
  var sb = document.querySelector('.sidebar');
  var ov = document.getElementById('drawer-overlay');
  var isOpen = sb.classList.contains('open');
  if(isOpen){ sb.classList.remove('open'); ov.classList.remove('open'); }
  else { sb.classList.add('open'); ov.classList.add('open'); }
}
function closeSidebar(){
  document.querySelector('.sidebar').classList.remove('open');
  document.getElementById('drawer-overlay').classList.remove('open');
}

// =============== EVENT LISTENERS (stored for cleanup) ===============
let _mousedownHandler = null;

// =============== EXPOSE TO WINDOW (for onclick attributes in HTML) ===============
window.closeSidebar = closeSidebar;
window.openBlockPopup = openBlockPopup;
window.miniCalPrev = miniCalPrev;
window.miniCalNext = miniCalNext;
window.toggleVet = toggleVet;
window.toggleSidebar = toggleSidebar;
window.prevPeriod = prevPeriod;
window.goToday = goToday;
window.nextPeriod = nextPeriod;
window.setView = setView;
window.closePopup = closePopup;
window.deleteBlock = deleteBlock;
window.saveBlock = saveBlock;
window.selectActivityType = selectActivityType;
window.updateCapacityDisplay = updateCapacityDisplay;
window.dragStart = dragStart;
window.dragMove = dragMove;
window.dragEnd = dragEnd;
window.dragCancel = dragCancel;

// =============== INIT / CLEANUP ===============
export function init() {
  // Default to week view on tablet, day on mobile
  if(window.innerWidth <= 640){ currentView='day'; }
  else if(window.innerWidth <= 1024){ currentView='week'; }
  renderLegend();
  renderMiniCal();
  renderPlanning();

  // Click outside preview — clear all previews
  _mousedownHandler = function(e){
    if(!document.getElementById('block-overlay').classList.contains('open')){
      var onPreview = e.target.closest('[id^="drag-preview-"]');
      if(!onPreview){
        document.querySelectorAll('[id^="drag-preview-"]').forEach(function(el){ el.style.display='none'; });
      }
    }
  };
  document.addEventListener('mousedown', _mousedownHandler);

  // Sync view buttons
  ['day','week','month'].forEach(function(x){
    var btn=document.getElementById('view-'+x+'-btn');
    if(btn) btn.classList.toggle('active', x===currentView);
  });
}

export function cleanup() {
  if(_mousedownHandler) {
    document.removeEventListener('mousedown', _mousedownHandler);
    _mousedownHandler = null;
  }

  // Clean up window references
  delete window.closeSidebar;
  delete window.openBlockPopup;
  delete window.miniCalPrev;
  delete window.miniCalNext;
  delete window.toggleVet;
  delete window.toggleSidebar;
  delete window.prevPeriod;
  delete window.goToday;
  delete window.nextPeriod;
  delete window.setView;
  delete window.closePopup;
  delete window.deleteBlock;
  delete window.saveBlock;
  delete window.selectActivityType;
  delete window.updateCapacityDisplay;
  delete window.dragStart;
  delete window.dragMove;
  delete window.dragEnd;
  delete window.dragCancel;
}
