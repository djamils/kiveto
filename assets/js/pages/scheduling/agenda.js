/**
 * Page module — Scheduling Agenda
 * Loaded by app.js dispatcher on turbo:load.
 *
 * Reads the server payload injected into <script type="application/json" id="agenda-data">
 * and renders the week/day grid. Navigation (prev/next, today, day/week toggle)
 * is driven by server-rendered <a href> links — no JS state for that.
 */

// =============== CONSTANTS ===============
const VET_COLORS = ['#4338ca', '#0891b2', '#059669', '#db2777', '#7c3aed', '#b45309', '#0369a1', '#9d174d'];
// Paired pastel backgrounds (Tailwind-50 shades) for solid, opaque RDV tiles.
// Aligned with docs/frontend-theme/vetsaas-layouts/vetos-agenda.html so the
// :hover brightness filter reads consistently across blocks.
const VET_BG_COLORS = ['#eef2ff', '#ecfeff', '#ecfdf5', '#fdf2f8', '#f5f3ff', '#fffbeb', '#f0f9ff', '#fdf2f8'];
const ME_BG_COLOR   = '#f0fdfa'; // teal-50 paired with #0d9488
// Reserved color for the current user's column — intentionally outside the
// --brand palette so it doesn't blend with buttons/sidebar (tech-spec D8).
const ME_COLOR = '#0d9488';
const FALLBACK_VET = { userId: null, label: '?', color: '#64748b', bg: '#f1f5f9', isMe: false };

const HOUR_START = 7;
const HOUR_END = 20;
const SLOT_H = 80;

// Free-slots overlay (client-side only — finds gaps in each active vet's
// schedule between 08:00 and 19:00 and paints them as clickable "available"
// blocks. The click currently shows a toast — the create-RDV workflow is
// out of scope for this iteration).
const FREE_START = 8 * 60;
const FREE_END = 19 * 60;
let freeSlotsActive = false;
let freeDuration = 20;

// Sync with ACTIVITY_TYPES in planning.js and PlanningBlockType.php when adding new types.
const PLANNING_BLOCK_TYPES = {
  consultation: { color: '#4338ca', bg: '#eef2ff', label: 'Consultation' },
  chirurgie:    { color: '#b45309', bg: '#fef3c7', label: 'Chirurgie'    },
  garde:        { color: '#7c3aed', bg: '#f5f3ff', label: 'Garde'        },
  bilan:        { color: '#059669', bg: '#ecfdf5', label: 'Bilans/Labo'  },
  formation:    { color: '#0891b2', bg: '#ecfeff', label: 'Formation'    },
  conge:        { color: '#dc2626', bg: '#fef2f2', label: 'Congé'        },
  admin:        { color: '#64748b', bg: '#f8fafc', label: 'Admin'        },
  urgence:      { color: '#ea580c', bg: '#fff7ed', label: 'Urgences'     },
};
const PLANNING_OVERLAY_LS_KEY = 'kiveto.agenda.planning-overlay';
let planningBlocksVisible = false;

const DOW_FR = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
const MONTHS_FR = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

// =============== RUNTIME STATE (populated by init) ===============
let AGENDA_DATA = null;
let appointments = [];
let vetById = {};
let activeVets = new Set();
let view = 'week';
let weekDays = [];
let clientProfileUrlTemplate = '';
let nowDate = new Date();

// =============== UTILS ===============
function parseUtcDateTime(utcString) {
  // Accepts MySQL "Y-m-d H:i:s" or ISO 8601 "Y-m-dTH:i:sZ" → JS Date (UTC).
  if (utcString.includes('T')) return new Date(utcString);
  return new Date(utcString.replace(' ', 'T') + 'Z');
}

// Mirrors src/Shared/Presentation/Twig/PhoneFormatRuntime.php (international mode).
// Kept in sync manually; if a new country is added server-side, add it here too.
const PHONE_COUNTRIES = [
  { dialCode: '+352', trunkPrefix: '',  maskIntl: '### ### ###',    keepTrunk: false },
  { dialCode: '+212', trunkPrefix: '0', maskIntl: '# ## ## ## ##',  keepTrunk: false },
  { dialCode: '+216', trunkPrefix: '',  maskIntl: '## ### ###',     keepTrunk: false },
  { dialCode: '+213', trunkPrefix: '0', maskIntl: '## ## ## ##',    keepTrunk: false },
  { dialCode: '+33',  trunkPrefix: '0', maskIntl: '# ## ## ## ##',  keepTrunk: false },
  { dialCode: '+32',  trunkPrefix: '0', maskIntl: '## ## ## ##',    keepTrunk: false },
  { dialCode: '+41',  trunkPrefix: '0', maskIntl: '# ### ## ##',    keepTrunk: false },
  { dialCode: '+34',  trunkPrefix: '',  maskIntl: '### ## ## ##',   keepTrunk: false },
  { dialCode: '+39',  trunkPrefix: '',  maskIntl: '### ### ####',   keepTrunk: true },
  { dialCode: '+49',  trunkPrefix: '0', maskIntl: '## #######',     keepTrunk: false },
  { dialCode: '+44',  trunkPrefix: '0', maskIntl: '#### ### ###',   keepTrunk: false },
  { dialCode: '+1',   trunkPrefix: '',  maskIntl: '(###) ###-####', keepTrunk: false },
];

function formatPhoneIntl(e164) {
  if (!e164 || typeof e164 !== 'string' || !e164.startsWith('+')) return e164 || '';
  const country = PHONE_COUNTRIES.find((c) => e164.startsWith(c.dialCode));
  if (!country) return e164;
  const national = e164.slice(country.dialCode.length);
  const digits = country.keepTrunk && country.trunkPrefix ? country.trunkPrefix + national : national;
  let result = '';
  let di = 0;
  for (let i = 0; i < country.maskIntl.length && di < digits.length; i++) {
    if (country.maskIntl[i] === '#') result += digits[di++];
    else result += country.maskIntl[i];
  }
  if (di < digits.length) result += digits.slice(di);
  return `${country.dialCode} ${result}`;
}

function toClinicLocalParts(utcDate, timezone) {
  const opts = {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  };
  const parts = new Intl.DateTimeFormat('en-GB', opts).formatToParts(utcDate);
  const get = (type) => parts.find((p) => p.type === type).value;
  return {
    dateStr: `${get('year')}-${get('month')}-${get('day')}`,
    timeStr: `${get('hour')}:${get('minute')}`,
  };
}

function fmtTime(hhmm) {
  const [h, m] = hhmm.split(':');
  return `${h}h${m === '00' ? '' : m}`;
}

function shortTimezoneLabel(timezone) {
  // "Europe/Paris" → "Paris", "America/New_York" → "New York", "UTC" → "UTC"
  if (!timezone) return '';
  const parts = timezone.split('/');
  return parts[parts.length - 1].replace(/_/g, ' ');
}

function currentUtcOffsetLabel(timezone) {
  try {
    const parts = new Intl.DateTimeFormat('en-GB', {
      timeZone: timezone,
      timeZoneName: 'shortOffset',
    }).formatToParts(new Date());
    const tzName = parts.find((p) => p.type === 'timeZoneName');
    if (!tzName) return '';
    // Intl returns "GMT+2" or "GMT+2:30" — pad the hour part to two digits
    // for a stable, aligned display ("GMT+02", "GMT+02:30", "GMT").
    const match = /^GMT([+-])(\d+)(?::(\d+))?$/.exec(tzName.value);
    if (!match) return tzName.value;
    const sign = match[1];
    const hh = match[2].padStart(2, '0');
    return match[3] ? `GMT${sign}${hh}:${match[3]}` : `GMT${sign}${hh}`;
  } catch (_e) {
    return '';
  }
}

// ISO-8601 week number (week starts Monday, week 1 contains the first Thursday of the year).
function isoWeekNumber(date) {
  const target = new Date(date);
  target.setHours(0, 0, 0, 0);
  // Thursday of the current week decides the ISO year.
  target.setDate(target.getDate() + 3 - ((target.getDay() + 6) % 7));
  const firstThursday = new Date(target.getFullYear(), 0, 4);
  const diffDays = (target.getTime() - firstThursday.getTime()) / 86400000;
  return 1 + Math.round((diffDays - 3 + ((firstThursday.getDay() + 6) % 7)) / 7);
}

// "Avril 2026" when the range stays in one month, "Mars – Avril 2026" when
// it straddles two months in the same year, "Décembre 2025 – Janvier 2026"
// across a year boundary.
function monthRangeLabel(start, end) {
  const sameYear = start.getFullYear() === end.getFullYear();
  const sameMonth = sameYear && start.getMonth() === end.getMonth();
  if (sameMonth) {
    return `${MONTHS_FR[start.getMonth()]} ${start.getFullYear()}`;
  }
  if (sameYear) {
    return `${MONTHS_FR[start.getMonth()]} – ${MONTHS_FR[end.getMonth()]} ${start.getFullYear()}`;
  }
  return `${MONTHS_FR[start.getMonth()]} ${start.getFullYear()} – ${MONTHS_FR[end.getMonth()]} ${end.getFullYear()}`;
}

function pad2(n) {
  return String(n).padStart(2, '0');
}

function isoDate(d) {
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function addDays(d, n) {
  const r = new Date(d);
  r.setDate(r.getDate() + n);
  return r;
}

function mondayOf(d) {
  const day = d.getDay(); // 0=Sun
  const diff = day === 0 ? -6 : 1 - day;
  const s = new Date(d);
  s.setDate(d.getDate() + diff);
  s.setHours(0, 0, 0, 0);
  return s;
}

// =============== DATA PREP ===============
function buildVetIndex() {
  vetById = {};
  activeVets = new Set();
  const vetList = [];
  (AGENDA_DATA.veterinarians || []).forEach((v, i) => {
    const isMe = v.userId === AGENDA_DATA.currentUserId;
    const titlePrefix = v.professionalTitle === 'DR' ? 'Dr. ' : (v.professionalTitle === 'PR' ? 'Pr. ' : '');
    const profileLabel = v.displayName ? `${titlePrefix}${v.displayName}` : null;
    const entry = {
      userId: v.userId,
      label: isMe ? 'Moi' : (profileLabel || `Praticien ${i + 1}`),
      color: isMe ? ME_COLOR : (v.agendaColor || VET_COLORS[i % VET_COLORS.length]),
      bg:    isMe ? ME_BG_COLOR : VET_BG_COLORS[i % VET_BG_COLORS.length],
      isMe,
    };
    vetById[v.userId] = entry;
    vetList.push(entry);
    activeVets.add(v.userId);
  });
  // Expose vet list for the appointment form Stimulus controller
  window.__agendaVets = vetList;
}

/**
 * Decorate each appointment with clinic-local date / time parts so rendering
 * uses the clinic's timezone, not the browser's.
 */
function prepareAppointments() {
  appointments = (AGENDA_DATA.appointments || []).map((a) => {
    const utc = parseUtcDateTime(a.startsAtUtc);
    const parts = toClinicLocalParts(utc, AGENDA_DATA.clinicTimezone);
    const [hh, mm] = parts.timeStr.split(':').map(Number);
    const startMin = hh * 60 + mm;
    return {
      ...a,
      _date: parts.dateStr,
      _startTime: parts.timeStr,
      _startMin: startMin,
      _endMin: startMin + a.durationMinutes,
      _endUtc: new Date(utc.getTime() + a.durationMinutes * 60 * 1000),
    };
  });
}

function buildWeekDays() {
  // selectedDate is a "Y-m-d" string in the clinic timezone. Turn it into a
  // local JS Date that represents the same calendar day for display purposes.
  const [y, m, d] = AGENDA_DATA.selectedDate.split('-').map(Number);
  const selected = new Date(y, m - 1, d);
  weekDays = [];
  if (view === 'week') {
    const start = mondayOf(selected);
    for (let i = 0; i < 7; i += 1) weekDays.push(addDays(start, i));
  } else {
    weekDays = [selected];
  }
}

// =============== RENDER ===============
function renderWeek() {
  const head = document.getElementById('agenda-head');
  const inner = document.getElementById('agenda-inner');
  if (!head || !inner) return;

  const cols = weekDays.length;
  const gridCols = `55px repeat(${cols},1fr)`;
  // Provisional head template — the trailing scrollbar-spacer width is
  // adjusted at the end of renderWeek() once the body actually overflows
  // and the real scrollbar width is known.
  head.style.gridTemplateColumns = `55px repeat(${cols},1fr) 0px`;
  inner.style.gridTemplateColumns = gridCols;

  while (head.children.length > 1) head.removeChild(head.lastChild);
  inner.innerHTML = '';

  // Timezone label in the top-left spacer cell so the user knows the grid's
  // reference timezone at a glance (e.g. "Paris · UTC+2").
  const firstCell = head.firstElementChild;
  if (firstCell) {
    firstCell.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;font-size:10px;color:#94a3b8;line-height:1.15;">
      <span style="font-weight:500;color:#64748b;">${escapeHtml(shortTimezoneLabel(AGENDA_DATA.clinicTimezone))}</span>
      <span>${escapeHtml(currentUtcOffsetLabel(AGENDA_DATA.clinicTimezone))}</span>
    </div>`;
  }

  // The "Month year · Semaine N" label in the static header is patched
  // in place by patchHeaderNav() / renderHeaderLabel(), called from
  // bootstrapFromPayload() before renderWeek(). Nothing to do here.

  // Day headers
  const todayIso = toClinicLocalParts(nowDate, AGENDA_DATA.clinicTimezone).dateStr;
  weekDays.forEach((d, di) => {
    const iso = isoDate(d);
    const isToday = iso === todayIso;
    const el = document.createElement('div');
    el.style.cssText = `padding:8px 0;text-align:center;border-left:${di === 0 ? '1px solid #dde3ea' : '1px solid #f1f5f9'};${isToday ? 'background:#f5f3ff;' : ''}`;
    const dowIndex = (d.getDay() + 6) % 7 + 1; // align with legacy label mapping
    el.innerHTML = `
      <div style="font-size:var(--text-xs);font-weight:var(--weight-medium);color:${isToday ? '#4338ca' : '#94a3b8'};text-transform:uppercase;letter-spacing:.06em;">${DOW_FR[dowIndex] || DOW_FR[0]}</div>
      <div style="font-size:18px;font-weight:${isToday ? '800' : 'var(--weight-medium)'};color:${isToday ? '#4338ca' : '#0f172a'};line-height:1.1;margin-top:1px;">${d.getDate()}</div>
      <div style="font-size:var(--text-xs);color:var(--text-subtle);margin-top:1px;">${countRdv(iso)} RDV</div>`;
    head.appendChild(el);
  });
  // Scrollbar spacer
  head.appendChild(document.createElement('div'));

  // Time column
  const timeCol = document.createElement('div');
  timeCol.className = 'time-col';
  for (let h = HOUR_START; h <= HOUR_END; h += 1) {
    const lbl = document.createElement('div');
    lbl.className = 'time-label';
    lbl.textContent = `${h}:00`;
    timeCol.appendChild(lbl);
  }
  inner.appendChild(timeCol);

  // Day columns
  weekDays.forEach((d) => {
    const iso = isoDate(d);
    const isToday = iso === todayIso;
    const col = document.createElement('div');
    col.className = 'day-col';
    col.dataset.date = iso;
    if (isToday) col.style.background = '#fdfcff';
    const totalH = HOUR_END - HOUR_START + 1;
    col.style.height = `${totalH * SLOT_H}px`;
    col.style.position = 'relative';

    renderPlanningOverlays(col, iso); // must be first — prepend order determines stacking

    // Hour slots — two clickable half-zones (on the hour / half past) with
    // hover highlight. Click shows a "not wired yet" toast — the full create
    // workflow is a separate follow-up spec.
    for (let h = HOUR_START; h <= HOUR_END; h += 1) {
      const slotWrap = document.createElement('div');
      slotWrap.style.cssText = `position:absolute;top:${(h - HOUR_START) * SLOT_H}px;left:0;right:0;height:${SLOT_H}px;border-bottom:1px solid #c9d0d8;`;

      const top = document.createElement('div');
      top.style.cssText = 'position:absolute;top:0;left:0;right:0;height:50%;cursor:pointer;transition:background .1s;';
      top.dataset.time = `${pad2(h)}:00`;
      top.onmouseenter = () => { top.style.background = '#f5f3ff'; };
      top.onmouseleave = () => { top.style.background = ''; };
      top.onclick = (e) => { e.stopPropagation(); openNewRdv(iso, top.dataset.time, null); };

      const bot = document.createElement('div');
      bot.style.cssText = 'position:absolute;bottom:0;left:0;right:0;height:50%;cursor:pointer;transition:background .1s;border-top:1px dashed #dde2e7;';
      bot.dataset.time = `${pad2(h)}:30`;
      bot.onmouseenter = () => { bot.style.background = '#f5f3ff'; };
      bot.onmouseleave = () => { bot.style.background = ''; };
      bot.onclick = (e) => { e.stopPropagation(); openNewRdv(iso, bot.dataset.time, null); };

      slotWrap.appendChild(top);
      slotWrap.appendChild(bot);
      col.appendChild(slotWrap);
    }

    // Appointments for this day (filters: active vets, non-cancelled).
    // The lane algorithm below requires chronological order — the source
    // array can become unsorted after a reschedule (in-place replacement
    // keeps the original index even when the new start is earlier/later).
    const dayAppts = appointments.filter((a) =>
      a._date === iso
      && activeVets.has(a.practitionerUserId)
      && a.status !== 'CANCELLED',
    ).sort((a, b) => a._startMin - b._startMin);

    // Assign lanes to overlapping blocks
    const lanes = [];
    dayAppts.forEach((a) => {
      let lane = 0;
      while (lanes[lane] && lanes[lane] > a._startMin) lane += 1;
      lanes[lane] = a._endMin;
      a._lane = lane;
    });
    const totalLanes = lanes.length || 1;

    dayAppts.forEach((a) => {
      const block = createRdvBlock(a);
      const laneW = 100 / totalLanes;
      const left = a._lane * laneW;
      block.style.left = `calc(${left}% + 3px)`;
      block.style.right = `calc(${100 - left - laneW}% + 3px)`;
      col.appendChild(block);
    });

    // Free slots overlay (when toggled on)
    if (freeSlotsActive) renderFreeSlots(col, iso);

    // Now line
    if (isToday) {
      const nowParts = toClinicLocalParts(nowDate, AGENDA_DATA.clinicTimezone);
      const [nh, nm] = nowParts.timeStr.split(':').map(Number);
      const nowMin = nh * 60 + nm;
      const startMin = HOUR_START * 60;
      if (nowMin >= startMin && nowMin <= HOUR_END * 60) {
        const top = ((nowMin - startMin) / 60) * SLOT_H;
        const line = document.createElement('div');
        line.className = 'now-line';
        line.style.top = `${top}px`;
        line.innerHTML = '<div class="now-dot"></div>';
        col.appendChild(line);
      }
    }

    inner.appendChild(col);
  });

  // Scroll to 8am
  const body = document.getElementById('agenda-body');
  if (body) body.scrollTop = (8 - HOUR_START) * SLOT_H;

  // Align the day-column headers with the scrolled body columns: the head is
  // outside the scroll container so its full width covers the body + scrollbar,
  // while the inner's 1fr columns are computed from (body width − scrollbar).
  // Reserve the exact OS scrollbar width in the head's trailing spacer so the
  // 7 head 1fr cells match the 7 inner 1fr cells 1:1.
  if (body) {
    const scrollbarWidth = body.offsetWidth - body.clientWidth;
    head.style.gridTemplateColumns = `55px repeat(${cols},1fr) ${scrollbarWidth}px`;
  }
}

function countRdv(iso) {
  return appointments.filter((a) =>
    a._date === iso
    && activeVets.has(a.practitionerUserId)
    && a.status !== 'CANCELLED',
  ).length;
}

function createRdvBlock(a) {
  const vet = vetById[a.practitionerUserId] || FALLBACK_VET;
  const topPx = ((a._startMin - HOUR_START * 60) / 60) * SLOT_H + 1;
  const heightPx = Math.max((a.durationMinutes / 60) * SLOT_H - 3, 14);

  const isPast = a._endUtc < nowDate;
  const isCancelled = a.status === 'CANCELLED';
  // Dead branch in this iteration (cancelled are filtered upstream) but kept
  // for the future "show cancelled" toggle spec.
  // eslint-disable-next-line no-unused-vars
  const strikethrough = isCancelled ? 'text-decoration:line-through;' : '';

  const block = document.createElement('div');
  block.className = 'rdv-block';
  block.dataset.id = a.id;

  let bg;
  let borderColor;
  let opacity = '1';
  if (isCancelled) {
    bg = '#f1f5f9';
    borderColor = '#94a3b8';
    opacity = '0.8';
  } else if (isPast) {
    bg = '#e2e8f0';
    borderColor = vet.color;
    opacity = '0.8';
  } else {
    bg = vet.bg;
    borderColor = vet.color;
  }

  block.style.cssText = `top:${topPx}px;height:${heightPx}px;background:${bg};border-left-color:${borderColor};opacity:${opacity};overflow:hidden;`;

  const nameColor = (isCancelled || isPast) ? '#334155' : '#0f172a';
  const timeColor = (isCancelled || isPast) ? '#64748b' : vet.color;
  const subColor = (isCancelled || isPast) ? '#475569' : '#64748b';
  const dot = `<span style="width:6px;height:6px;border-radius:50%;background:${vet.color};flex-shrink:0;display:inline-block;"></span>`;
  const R = 'display:flex;align-items:center;gap:3px;overflow:hidden;white-space:nowrap;height:15px;min-height:15px;max-height:15px;flex-shrink:0;';

  const lines = Math.floor((heightPx - 2) / 15);
  let html = `<div style="display:flex;flex-direction:column;overflow:hidden;width:100%;height:${heightPx - 2}px;justify-content:center;">`;
  const reasonText = escapeHtml(a.reason || 'Consultation');
  const animalEmoji = speciesEmoji(a.animalSpecies);
  const animalName = a.animalLabel ? `${animalEmoji} ${escapeHtml(a.animalLabel)}` : '';
  const ownerName = a.ownerLabel ? escapeHtml(a.ownerLabel) : '';
  const vetLabel = escapeHtml(vet.label);
  if (lines === 1) {
    html += `<div style="${R}">${dot}<span style="font-size:var(--text-xs);font-weight:var(--weight-bold);color:${timeColor};flex-shrink:0;">${fmtTime(a._startTime)} · ${a.durationMinutes}min</span>${animalName ? `<span style="font-size:11px;font-weight:var(--weight-bold);color:${nameColor};overflow:hidden;text-overflow:ellipsis;">· ${animalName}</span>` : `<span style="font-size:var(--text-xs);color:${nameColor};overflow:hidden;text-overflow:ellipsis;">· ${reasonText}</span>`}</div>`;
  } else if (lines === 2) {
    html += `<div style="${R}">${dot}<span style="font-size:var(--text-xs);font-weight:var(--weight-bold);color:${timeColor};flex-shrink:0;">${fmtTime(a._startTime)} · ${a.durationMinutes}min</span></div>`;
    html += `<div style="${R}"><span style="font-size:11.5px;font-weight:var(--weight-bold);color:${nameColor};overflow:hidden;text-overflow:ellipsis;">${animalName || reasonText}</span></div>`;
  } else if (lines === 3) {
    html += `<div style="${R}">${dot}<span style="font-size:var(--text-xs);font-weight:var(--weight-bold);color:${timeColor};flex-shrink:0;">${fmtTime(a._startTime)} · ${a.durationMinutes}min</span></div>`;
    html += `<div style="${R}"><span style="font-size:11.5px;font-weight:var(--weight-bold);color:${nameColor};overflow:hidden;text-overflow:ellipsis;">${animalName || reasonText}</span></div>`;
    html += `<div style="${R}"><span style="font-size:10.5px;color:${subColor};overflow:hidden;text-overflow:ellipsis;">${ownerName || vetLabel}</span></div>`;
  } else if (lines >= 4) {
    html += `<div style="${R}">${dot}<span style="font-size:var(--text-xs);font-weight:var(--weight-bold);color:${timeColor};flex-shrink:0;">${fmtTime(a._startTime)} · ${a.durationMinutes}min</span></div>`;
    html += `<div style="${R}"><span style="font-size:11.5px;font-weight:var(--weight-bold);color:${nameColor};overflow:hidden;text-overflow:ellipsis;">${animalName || reasonText}</span></div>`;
    if (ownerName) {
      html += `<div style="${R}"><span style="font-size:10.5px;color:${subColor};overflow:hidden;text-overflow:ellipsis;">${ownerName}</span></div>`;
    }
    html += `<div style="${R}"><span style="font-size:10.5px;color:${timeColor};font-weight:500;overflow:hidden;text-overflow:ellipsis;">${reasonText}${lines >= 5 ? ` · ${vetLabel}` : ''}</span></div>`;
  }
  html += '</div>';
  block.innerHTML = html;

  const isTerminal = ['CANCELLED', 'NO_SHOW', 'COMPLETED'].includes(a.status);
  if (!isPast && !isTerminal) {
    block.classList.add('is-draggable');
    block.addEventListener('pointerdown', (e) => startDrag(e, a, block));
  }

  block.onclick = (e) => {
    if (block.dataset.dragJustEnded === '1') {
      block.dataset.dragJustEnded = '';
      e.stopPropagation();
      e.preventDefault();
      return;
    }
    e.stopPropagation();
    showRdvPopup(a.id, e);
  };
  return block;
}

// =============== DRAG & DROP ===============
// Click-and-drag an rdv-block onto a different time (same practitioner, same
// or different day in week view). On drop: POST to reschedule, re-render via
// appointment:saved. On conflict/error: rollback visually + toast.
const DRAG_THRESHOLD_PX = 6;
const DRAG_SNAP_MIN = 15;

let _drag = null;

function startDrag(e, appointment, block) {
  // Left button only; ignore right-clicks, middle-clicks, extra buttons.
  if (e.button !== undefined && e.button !== 0) return;
  e.preventDefault();

  const blockRect = block.getBoundingClientRect();
  const parentCol = block.parentElement;

  _drag = {
    appointment,
    block,
    startX:         e.clientX,
    startY:         e.clientY,
    offsetY:        e.clientY - blockRect.top,
    origTop:        parseFloat(block.style.top) || 0,
    origCol:        parentCol,
    origDateIso:    appointment._date,
    origStartMin:   appointment._startMin,
    targetCol:      parentCol,
    targetStartMin: appointment._startMin,
    targetDateIso:  appointment._date,
    hasMoved:       false,
  };

  const onMove = (ev) => onDragMove(ev);
  const onUp   = (ev) => onDragEnd(ev);
  const onKey  = (ev) => { if (ev.key === 'Escape') cancelDrag(); };

  document.addEventListener('pointermove', onMove);
  document.addEventListener('pointerup', onUp);
  document.addEventListener('keydown', onKey);
  _drag.cleanup = () => {
    document.removeEventListener('pointermove', onMove);
    document.removeEventListener('pointerup', onUp);
    document.removeEventListener('keydown', onKey);
  };
}

function onDragMove(e) {
  if (!_drag) return;
  const dx = e.clientX - _drag.startX;
  const dy = e.clientY - _drag.startY;
  if (!_drag.hasMoved && Math.abs(dx) < DRAG_THRESHOLD_PX && Math.abs(dy) < DRAG_THRESHOLD_PX) return;

  if (!_drag.hasMoved) {
    _drag.hasMoved = true;
    _drag.block.classList.add('is-dragging');
    document.body.style.cursor = 'grabbing';
    document.body.style.userSelect = 'none';
  }

  const targetCol = findDayColUnder(e.clientX, e.clientY) || _drag.origCol;

  // Reparent into the target column when the cursor crosses a column boundary.
  if (targetCol !== _drag.targetCol) {
    targetCol.appendChild(_drag.block);
    _drag.targetCol = targetCol;
    _drag.targetDateIso = targetCol.dataset.date || _drag.origDateIso;
  }

  const colRect = targetCol.getBoundingClientRect();
  const yInCol = e.clientY - colRect.top - _drag.offsetY;
  const rawMinutes = Math.max(0, yInCol) / SLOT_H * 60;
  const snappedOffset = Math.round(rawMinutes / DRAG_SNAP_MIN) * DRAG_SNAP_MIN;
  const duration = _drag.appointment.durationMinutes;
  const minStart = HOUR_START * 60;
  const maxStart = (HOUR_END + 1) * 60 - duration;
  const newStartMin = Math.max(minStart, Math.min(minStart + snappedOffset, maxStart));

  const newTop = ((newStartMin - HOUR_START * 60) / 60) * SLOT_H + 1;
  _drag.block.style.top = `${newTop}px`;
  _drag.targetStartMin = newStartMin;
}

function findDayColUnder(x, y) {
  const hits = document.elementsFromPoint(x, y);
  for (const el of hits) {
    if (el.classList && el.classList.contains('day-col')) return el;
  }
  return null;
}

function cancelDrag() {
  if (!_drag) return;
  rollbackDragPosition();
  endDragCleanup();
}

function rollbackDragPosition() {
  if (!_drag) return;
  if (_drag.origCol && _drag.block.parentElement !== _drag.origCol) {
    _drag.origCol.appendChild(_drag.block);
  }
  _drag.block.style.top = `${_drag.origTop}px`;
}

function endDragCleanup() {
  if (!_drag) return;
  _drag.cleanup();
  _drag.block.classList.remove('is-dragging');
  document.body.style.cursor = '';
  document.body.style.userSelect = '';
  _drag = null;
}

function swallowNextClickOnce() {
  const handler = (ev) => {
    ev.stopPropagation();
    ev.preventDefault();
    document.removeEventListener('click', handler, true);
  };
  document.addEventListener('click', handler, true);
}

async function onDragEnd() {
  if (!_drag) return;
  const state = _drag;

  if (!state.hasMoved) {
    endDragCleanup();
    return; // plain click — let the onclick handler open the popup
  }

  // Prevent the synthetic click that follows pointerup from opening a
  // popup or hitting an hour-slot behind the block.
  state.block.dataset.dragJustEnded = '1';
  swallowNextClickOnce();
  window.setTimeout(() => {
    if (state.block) state.block.dataset.dragJustEnded = '';
  }, 300);

  const samePosition = state.targetStartMin === state.origStartMin
                    && state.targetDateIso === state.origDateIso;
  if (samePosition) {
    rollbackDragPosition();
    endDragCleanup();
    return;
  }

  const hh = Math.floor(state.targetStartMin / 60);
  const mm = state.targetStartMin % 60;
  const newStartsAt = `${state.targetDateIso}T${pad2(hh)}:${pad2(mm)}`;

  const csrfInput = document.querySelector('#modalAppointment input[name="_token"]');
  if (!csrfInput) {
    rollbackDragPosition();
    endDragCleanup();
    showToast("Erreur: jeton CSRF introuvable", '#dc2626');
    return;
  }

  const body = new URLSearchParams();
  body.append('_token', csrfInput.value);
  body.append('startsAtUtc', newStartsAt);
  body.append('durationMinutes', String(state.appointment.durationMinutes));
  body.append('practitionerUserId', state.appointment.practitionerUserId);

  const url = `/scheduling/appointments/${state.appointment.id}/reschedule`;

  try {
    const response = await fetch(url, {
      method: 'POST',
      body,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin',
    });
    const data = await response.json();
    if (data.success) {
      document.dispatchEvent(new CustomEvent('appointment:saved', {
        detail: { appointment: data.appointment, action: 'rescheduled' },
      }));
      endDragCleanup();
      return;
    }
    // Prefer a user-friendly message by errorCode; the raw server message
    // for conflicts currently contains the practitioner UUID.
    const friendly = {
      APPOINTMENT_CONFLICT:        'Ce créneau est déjà occupé.',
      APPOINTMENT_TERMINAL_STATUS: 'Ce rendez-vous n\'est plus modifiable.',
    }[data.errorCode];
    const msg = friendly || data.errors?.global?.[0] || 'Erreur lors du déplacement.';
    rollbackDragPosition();
    endDragCleanup();
    showToast(msg, '#dc2626');
  } catch (_err) {
    rollbackDragPosition();
    endDragCleanup();
    showToast('Erreur réseau.', '#dc2626');
  }
}

function speciesEmoji(species) {
  switch (species) {
    case 'dog': return '🐶';
    case 'cat': return '🐱';
    case 'nac': return '🐾';
    default:    return '🐾';
  }
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// =============== POPUP ===============
function showRdvPopup(id, e) {
  const a = appointments.find((x) => x.id === id);
  if (!a) return;
  const vet = vetById[a.practitionerUserId] || FALLBACK_VET;
  const endMin = a._endMin;
  const endTime = `${pad2(Math.floor(endMin / 60))}:${pad2(endMin % 60)}`;

  const animalText = a.animalLabel
    ? `${speciesEmoji(a.animalSpecies)} ${a.animalLabel}`
    : (a.reason || 'Consultation');
  document.getElementById('pp-animal').textContent = animalText;
  const ownerParts = [];
  if (a.ownerLabel) ownerParts.push(a.ownerLabel);
  if (a.ownerPhone) ownerParts.push(formatPhoneIntl(a.ownerPhone));
  document.getElementById('pp-proprio').textContent = ownerParts.join(' · ');
  const badge = document.getElementById('pp-badge');
  // Hide default PLANNED status — only show badge for non-trivial states.
  if ('PLANNED' === a.status) {
    badge.textContent = '';
    badge.style.display = 'none';
  } else {
    badge.textContent = a.status;
    badge.style.display = '';
    badge.className = 'badge';
  }
  document.getElementById('pp-time').textContent = `${a._startTime.replace(':', ' h ')} — ${endTime.replace(':', ' h ')}`;
  document.getElementById('pp-duration').textContent = `${a.durationMinutes} min`;
  document.getElementById('pp-vet').textContent = vet.label;
  document.getElementById('pp-motif').textContent = a.reason || '';
  const noteRow = document.getElementById('pp-note-row');
  if (a.notes) {
    noteRow.style.display = 'flex';
    document.getElementById('pp-note').textContent = a.notes;
  } else {
    noteRow.style.display = 'none';
  }

  // Reset cancel confirm state
  cancelCancelConfirm();

  // Past RDV: cancel is forbidden → hide the button. Modifier remains enabled
  // (editing past RDV is allowed). Profil visible only when an owner is linked.
  // Buttons use flex:1 so visible ones share the row evenly.
  const profileBtn = document.getElementById('pp-profile-btn');
  const editBtn    = document.getElementById('pp-edit-btn');
  const cancelBtn  = document.getElementById('pp-cancel-btn');
  const isPast     = a._endUtc < nowDate;
  const isTerminal = ['CANCELLED', 'NO_SHOW', 'COMPLETED'].includes(a.status);
  if (profileBtn) {
    if (a.ownerId && clientProfileUrlTemplate) {
      profileBtn.href = clientProfileUrlTemplate.replace('__ID__', a.ownerId);
      profileBtn.style.display = '';
    } else {
      profileBtn.style.display = 'none';
    }
  }
  if (editBtn) editBtn.disabled = isTerminal;
  if (cancelBtn) {
    cancelBtn.style.display = isPast ? 'none' : '';
    cancelBtn.disabled = isTerminal;
  }

  const popup = document.getElementById('rdv-popup');
  popup.dataset.rdvId = id;
  positionPopup(popup, e);
  showPopup(popup);
}

function positionPopup(popup, e) {
  const x = e.clientX;
  const y = e.clientY;
  const w = 300;
  const h = 300;
  let left = x + 12;
  let top = y - 40;
  if (left + w > window.innerWidth - 16) left = x - w - 12;
  if (top + h > window.innerHeight - 16) top = window.innerHeight - h - 16;
  popup.style.left = `${left}px`;
  popup.style.top = `${Math.max(8, top)}px`;
  popup.style.right = 'auto';
}

function showPopup(popup) {
  closeAllPopups();
  popup.style.display = 'block';
  const overlay = document.getElementById('overlay');
  if (overlay) overlay.style.display = 'block';
}

function closeAllPopups() {
  const popup = document.getElementById('rdv-popup');
  if (popup) popup.style.display = 'none';
  const overlay = document.getElementById('overlay');
  if (overlay) overlay.style.display = 'none';
}

// =============== ACTIONS ===============
function toggleVet(el, userId) {
  if (activeVets.has(userId)) activeVets.delete(userId);
  else activeVets.add(userId);
  const check = document.querySelector(`[data-vet-check="${userId}"]`);
  if (check) check.style.opacity = activeVets.has(userId) ? '1' : '0.15';
  renderWeek();
}

// =============== PLANNING OVERLAYS ===============
function togglePlanningOverlay() {
  planningBlocksVisible = !planningBlocksVisible;
  localStorage.setItem(PLANNING_OVERLAY_LS_KEY, planningBlocksVisible ? '1' : '0');
  const btn = document.getElementById('planning-overlay-btn');
  if (btn) btn.classList.toggle('is-active', planningBlocksVisible);
  renderWeek(); // renderWeek() handles both day and week views — no renderDay() exists
}

function renderPlanningOverlays(col, iso) {
  if (!planningBlocksVisible) return;

  // Parse, clamp, and filter blocks for this column
  const items = [];
  (AGENDA_DATA.planningBlocks || []).forEach((b) => {
    if (b.date !== iso || !activeVets.has(b.vet)) return;
    const [sh, sm] = b.start.split(':').map(Number);
    const [eh, em] = b.end.split(':').map(Number);
    const startMin = Math.max(sh * 60 + sm, HOUR_START * 60);
    const endMin   = Math.min(eh * 60 + em, HOUR_END   * 60);
    if (endMin <= startMin) return;
    const topPx    = ((startMin - HOUR_START * 60) / 60) * SLOT_H + 1;
    const heightPx = ((endMin - startMin)          / 60) * SLOT_H - 2;
    if (heightPx < 6) return;
    items.push({ b, startMin, endMin, topPx, heightPx, lane: 0 });
  });
  if (items.length === 0) return;

  // Sort by start time — required by the lane algorithm
  items.sort((a, b) => a.startMin - b.startMin);

  // One fixed lane per vet (sidebar order) — all blocks from the same vet
  // always occupy the same column, regardless of time overlap.
  const vetsWithBlocks = [...activeVets].filter((id) => items.some((item) => item.b.vet === id));
  const vetLane = Object.fromEntries(vetsWithBlocks.map((id, i) => [id, i]));
  const totalLanes = vetsWithBlocks.length || 1;
  const laneW = 100 / totalLanes;
  items.forEach((item) => { item.lane = vetLane[item.b.vet] ?? 0; });

  items.forEach((item) => {
    const { b, topPx, heightPx, lane } = item;
    const type    = PLANNING_BLOCK_TYPES[b.type] ?? PLANNING_BLOCK_TYPES.consultation;
    const vet     = vetById[b.vet];
    const vetName = vet ? vet.label : '';
    const label   = vetName ? `${vetName} — ${type.label}` : type.label;

    const leftPct  = lane * laneW;
    const rightPct = 100 - leftPct - laneW;

    const el = document.createElement('div');
    el.style.cssText = [
      'position:absolute',
      `top:${topPx}px`,
      `height:${heightPx}px`,
      `left:${leftPct}%`,
      `right:${rightPct}%`,
      `background:${type.bg}`,
      `border-left:3px solid ${type.color}`,
      'opacity:0.5',
      'pointer-events:none',
      'overflow:hidden',
    ].join(';');
    const nameHtml = vetName
      ? `<span style="display:block;padding:2px 5px 0;font-size:var(--text-xs);font-weight:var(--weight-medium);color:${type.color};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${vetName}</span>`
      : '';
    const typeHtml = `<span style="display:block;padding:0 5px 2px;font-size:var(--text-xs);color:${type.color};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${type.label}</span>`;
    el.innerHTML = nameHtml + typeHtml;

    col.prepend(el); // prepend = behind all later-appended children (slots, appointments)
  });
}

// =============== FREE SLOTS ===============
function toggleFreeSlots() {
  freeSlotsActive = !freeSlotsActive;
  const btn = document.getElementById('free-slots-btn');
  const picker = document.getElementById('free-dur-picker');
  if (btn) btn.classList.toggle('is-active', freeSlotsActive);
  if (picker) picker.style.display = freeSlotsActive ? 'flex' : 'none';
  renderWeek();
}

function setFreeDur(dur) {
  freeDuration = dur;
  document.querySelectorAll('[data-dur]').forEach((b) => {
    b.classList.toggle('is-active', Number(b.dataset.dur) === dur);
  });
  renderWeek();
}

function renderFreeSlots(col, dateStr) {
  // Past free slots aren't actionable — a vet can't book a patient into a
  // window that has already started. Filter by "now" in the clinic timezone:
  //   - past days: nothing at all
  //   - today: only slots whose start is strictly after current minute
  //   - future days: everything
  const nowParts = toClinicLocalParts(nowDate, AGENDA_DATA.clinicTimezone);
  const todayIso = nowParts.dateStr;
  if (dateStr < todayIso) return;
  const isToday = dateStr === todayIso;
  const [nh, nm] = nowParts.timeStr.split(':').map(Number);
  const nowMin = nh * 60 + nm;

  [...activeVets].forEach((userId) => {
    const vet = vetById[userId];
    if (!vet) return;

    const vetAppts = appointments
      .filter((a) => a._date === dateStr
        && a.practitionerUserId === userId
        && a.status !== 'CANCELLED')
      .map((a) => ({ s: a._startMin, e: a._endMin }))
      .sort((a, b) => a.s - b.s);

    const gaps = [];
    let cursor = FREE_START;
    vetAppts.forEach(({ s, e }) => {
      if (s > cursor) gaps.push({ s: cursor, e: s });
      cursor = Math.max(cursor, e);
    });
    if (cursor < FREE_END) gaps.push({ s: cursor, e: FREE_END });

    gaps.forEach((gap) => {
      if (gap.e - gap.s < freeDuration) return;
      // Slots are consecutive (step = duration) so they never overlap
      // visually. Start aligned on the nearest quarter-hour so a gap
      // from 08:07 → 09:00 gets slots at 08:15 / 08:35 / … for 20min,
      // not at 08:07 / 08:27 / …
      let t = Math.ceil(gap.s / 15) * 15;
      while (t + freeDuration <= gap.e) {
        if (!isToday || t > nowMin) {
          renderFreeSlotBlock(col, vet, dateStr, t, t + freeDuration);
        }
        t += freeDuration;
      }
    });
  });
}

function renderFreeSlotBlock(col, vet, dateStr, startMin, endMin) {
  const topPx = ((startMin - HOUR_START * 60) / 60) * SLOT_H + 1;
  const heightPx = ((endMin - startMin) / 60) * SLOT_H - 2;
  if (heightPx < 10) return;

  const vetList = [...activeVets];
  const idx = vetList.indexOf(vet.userId);
  const total = vetList.length;
  const laneW = 100 / total;
  const leftPct = idx * laneW;

  const el = document.createElement('div');
  el.className = 'free-slot';
  el.style.cssText = `top:${topPx}px;height:${heightPx}px;left:calc(${leftPct}% + 2px);width:calc(${laneW}% - 4px);background:${vet.bg};border:1.5px dashed ${vet.color};opacity:.85;align-items:flex-start;padding:2px 5px;`;

  const hh = pad2(Math.floor(startMin / 60));
  const mm = pad2(startMin % 60);
  const timeLabel = fmtTime(`${hh}:${mm}`);
  el.innerHTML = `<span style="font-size:var(--text-xs);font-weight:var(--weight-bold);color:${vet.color};">${timeLabel}</span>`;
  el.dataset.startTime = `${hh}:${mm}`;
  el.dataset.practitionerId = vet.userId;
  el.onclick = (e) => {
    e.stopPropagation();
    openNewRdv(dateStr, el.dataset.startTime, el.dataset.practitionerId);
  };

  col.appendChild(el);
}

// =============== NEW-RDV ===============
function openNewRdv(dateStr, time, practitionerUserId) {
  document.dispatchEvent(new CustomEvent('appointment:open-modal', {
    detail: {
      mode: 'create',
      prefill: { date: dateStr, time, practitionerUserId: practitionerUserId || null },
    },
  }));
}

function openNewRdvGlobal() {
  const selectedDate = AGENDA_DATA ? AGENDA_DATA.selectedDate : new Date().toISOString().slice(0, 10);
  document.dispatchEvent(new CustomEvent('appointment:open-modal', {
    detail: {
      mode: 'create',
      prefill: { date: selectedDate },
    },
  }));
}

// =============== EDIT / CANCEL ACTIONS ===============
function editAppointment() {
  const popup = document.getElementById('rdv-popup');
  if (!popup) return;
  const id = popup.dataset.rdvId;
  const a = appointments.find((x) => x.id === id);
  if (!a) return;

  closeAllPopups();
  document.dispatchEvent(new CustomEvent('appointment:open-modal', {
    detail: {
      mode: 'edit',
      appointment: a,
    },
  }));
}

function cancelAppointment() {
  const actions = document.querySelector('.pp-actions');
  const confirm = document.getElementById('pp-cancel-confirm');
  if (actions) actions.style.display = 'none';
  if (confirm) confirm.style.display = 'block';
}

function cancelCancelConfirm() {
  const actions = document.querySelector('.pp-actions');
  const confirm = document.getElementById('pp-cancel-confirm');
  if (actions) actions.style.display = 'flex';
  if (confirm) confirm.style.display = 'none';
}

function confirmCancel() {
  const popup = document.getElementById('rdv-popup');
  if (!popup) return;
  const appointmentId = popup.dataset.rdvId;
  if (!appointmentId) return;

  const btn = document.getElementById('pp-confirm-cancel-btn');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Annulation...';
  }

  // Read CSRF from the modal form
  const csrfToken = document.querySelector('#modalAppointment input[name="_token"]')?.value || '';

  fetch(`/scheduling/appointments/${appointmentId}/cancel`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ _token: csrfToken }),
    credentials: 'same-origin',
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        closeAllPopups();
        cancelCancelConfirm();
        document.dispatchEvent(new CustomEvent('appointment:saved', {
          detail: { appointment: { id: appointmentId, appointmentId }, action: 'cancelled' },
        }));
      } else {
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Confirmer';
        }
        showToast(data.errors?.global?.[0] || 'Erreur lors de l\'annulation', '#dc2626');
      }
    })
    .catch(() => {
      if (btn) {
        btn.disabled = false;
        btn.textContent = 'Confirmer';
      }
      showToast('Erreur réseau', '#dc2626');
    });
}

// =============== APPOINTMENT SAVED HANDLER ===============
function onAppointmentSaved(e) {
  const { appointment, action } = e.detail || {};
  if (!appointment) return;

  if (action === 'created') {
    // Push into the source array so prepareAppointments() picks it up
    AGENDA_DATA.appointments.push(appointment);
    prepareAppointments();
    renderWeek();

    // Flash animation on the new block
    requestAnimationFrame(() => {
      const block = document.querySelector(`.rdv-block[data-id="${appointment.id}"]`);
      if (block) {
        block.classList.add('is-new');
        block.addEventListener('animationend', () => block.classList.remove('is-new'), { once: true });
      }
    });

    showToast('Rendez-vous créé avec succès');
  } else if (action === 'rescheduled') {
    // Replace in the source array so prepareAppointments() picks it up
    const srcIdx = AGENDA_DATA.appointments.findIndex((a) => a.id === appointment.id);
    if (srcIdx !== -1) AGENDA_DATA.appointments[srcIdx] = appointment;
    else AGENDA_DATA.appointments.push(appointment);
    prepareAppointments();
    renderWeek();

    requestAnimationFrame(() => {
      const block = document.querySelector(`.rdv-block[data-id="${appointment.id}"]`);
      if (block) {
        block.classList.add('is-new');
        block.addEventListener('animationend', () => block.classList.remove('is-new'), { once: true });
      }
    });

    showToast('Rendez-vous modifié avec succès');
  } else if (action === 'cancelled') {
    const appointmentId = appointment.id || appointment.appointmentId;
    // Mark as cancelled in the source array
    const srcI = AGENDA_DATA.appointments.findIndex((a) => a.id === appointmentId);
    if (srcI !== -1) AGENDA_DATA.appointments[srcI].status = 'CANCELLED';

    const block = document.querySelector(`.rdv-block[data-id="${appointmentId}"]`);
    if (block) {
      block.classList.add('is-removing');
      block.addEventListener('animationend', () => {
        prepareAppointments();
        renderWeek();
      }, { once: true });
    } else {
      prepareAppointments();
      renderWeek();
    }

    showToast('Rendez-vous annulé');
  }
}

// =============== TOAST ===============
function showToast(msg, color = '#16a34a') {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.style.background = color;
  t.style.opacity = '1';
  t.style.transform = 'translateX(-50%) translateY(0)';
  setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translateX(-50%) translateY(8px)';
  }, 2500);
}

// =============== AGENDA SIDEBAR DRAWER (mobile) ===============
function toggleAgendaSidebar() {
  const sb = document.querySelector('.scheduling-aside');
  const ov = document.getElementById('drawer-overlay');
  if (!sb || !ov) return;
  const isOpen = sb.classList.contains('open');
  if (isOpen) {
    sb.classList.remove('open');
    ov.classList.remove('open');
  } else {
    sb.classList.add('open');
    ov.classList.add('open');
  }
}

function closeAgendaSidebar() {
  const sb = document.querySelector('.scheduling-aside');
  const ov = document.getElementById('drawer-overlay');
  if (sb) sb.classList.remove('open');
  if (ov) ov.classList.remove('open');
}

// =============== EXPOSE TO WINDOW (for onclick attributes in HTML) ===============
window.toggleVet = toggleVet;
window.toggleAgendaSidebar = toggleAgendaSidebar;
window.closeAgendaSidebar = closeAgendaSidebar;
window.closeAllPopups = closeAllPopups;
window.togglePlanningOverlay = togglePlanningOverlay;
window.toggleFreeSlots = toggleFreeSlots;
window.setFreeDur = setFreeDur;
window.openNewRdvGlobal = openNewRdvGlobal;
window.editAppointment = editAppointment;
window.cancelAppointment = cancelAppointment;
window.cancelCancelConfirm = cancelCancelConfirm;
window.confirmCancel = confirmCancel;

// =============== PAYLOAD + RENDER BOOTSTRAP ===============
// Reads #agenda-data (fresh after each frame swap), rebuilds state and
// renders the grid. Safe to call on both the initial page load and after
// every `turbo:frame-load`.
function bootstrapFromPayload() {
  const dataNode = document.getElementById('agenda-data');
  if (!dataNode) return;

  AGENDA_DATA = JSON.parse(dataNode.textContent);
  view = AGENDA_DATA.view === 'day' ? 'day' : 'week';
  clientProfileUrlTemplate = AGENDA_DATA.clientProfileUrlTemplate || '';
  nowDate = new Date();

  planningBlocksVisible = localStorage.getItem(PLANNING_OVERLAY_LS_KEY) === '1';
  const overlayBtn = document.getElementById('planning-overlay-btn');
  if (overlayBtn) overlayBtn.classList.toggle('is-active', planningBlocksVisible);

  buildVetIndex();
  prepareAppointments();
  buildWeekDays();
  patchHeaderNav();
  renderWeek();
  syncSidebarCalendar();
}

// Update the static page-header: nav link hrefs, view-toggle active state,
// and the "Month year · Semaine N" label. Done in place so the header DOM
// is never rebuilt on frame navigation — no visible snap.
function patchHeaderNav() {
  if (!AGENDA_DATA) return;

  const [y, m, d] = AGENDA_DATA.selectedDate.split('-').map(Number);
  const sel = new Date(y, m - 1, d);
  const step = view === 'day' ? 1 : 7;

  const prev = new Date(sel);
  prev.setDate(sel.getDate() - step);
  const next = new Date(sel);
  next.setDate(sel.getDate() + step);

  const setHref = (selector, url) => {
    const el = document.querySelector(selector);
    if (el) el.setAttribute('href', url);
  };
  setHref('[data-nav="prev"]', `/scheduling/agenda?date=${isoDate(prev)}&view=${view}`);
  setHref('[data-nav="next"]', `/scheduling/agenda?date=${isoDate(next)}&view=${view}`);
  setHref('[data-nav="today"]', `/scheduling/agenda?view=${view}`);
  setHref('[data-nav="week-view"]', `/scheduling/agenda?date=${AGENDA_DATA.selectedDate}&view=week`);
  setHref('[data-nav="day-view"]', `/scheduling/agenda?date=${AGENDA_DATA.selectedDate}&view=day`);

  document.querySelectorAll('[data-nav="week-view"]').forEach((el) => {
    el.classList.toggle('is-active', view === 'week');
  });
  document.querySelectorAll('[data-nav="day-view"]').forEach((el) => {
    el.classList.toggle('is-active', view === 'day');
  });

  renderHeaderLabel();
}

// Structured label: two child spans (month + week badge). On first run we
// build them; on subsequent runs we only touch a span if its text actually
// changed, and the change is wrapped in a quick fade so identical renders
// produce zero visual movement.
function renderHeaderLabel() {
  const label = document.getElementById('week-label');
  if (!label || !AGENDA_DATA) return;

  const ws = weekDays[0];
  const we = weekDays[weekDays.length - 1];
  const monthLabel = view === 'week'
    ? monthRangeLabel(ws, we)
    : `${MONTHS_FR[ws.getMonth()]} ${ws.getFullYear()}`;
  const weekText = `Semaine ${isoWeekNumber(ws)}`;

  let monthEl = label.querySelector('.week-month-label');
  let badgeEl = label.querySelector('.week-number-badge');

  if (!monthEl || !badgeEl) {
    // First render: build the structure.
    label.innerHTML = '';
    monthEl = document.createElement('span');
    monthEl.className = 'week-month-label';
    monthEl.textContent = monthLabel;
    badgeEl = document.createElement('span');
    badgeEl.className = 'week-number-badge';
    badgeEl.textContent = weekText;
    label.appendChild(monthEl);
    label.appendChild(badgeEl);
    return;
  }

  // Subsequent renders: only touch what changed, fade in place.
  if (monthEl.textContent !== monthLabel) fadeSwapText(monthEl, monthLabel);
  if (badgeEl.textContent !== weekText) fadeSwapText(badgeEl, weekText);
}

// Quick fade-out → swap text → fade-in on a single element. No-op if the
// content is already up to date (caller guards).
const LABEL_FADE_OUT_MS = 70;
const LABEL_FADE_IN_MS = 110;
function fadeSwapText(el, newText) {
  el.style.transition = `opacity ${LABEL_FADE_OUT_MS}ms ease-out`;
  el.style.opacity = '0';
  const onOut = () => {
    el.removeEventListener('transitionend', onOut);
    el.textContent = newText;
    el.style.transition = `opacity ${LABEL_FADE_IN_MS}ms ease-in`;
    el.style.opacity = '1';
    const onIn = () => {
      el.removeEventListener('transitionend', onIn);
      el.style.transition = '';
      el.style.opacity = '';
    };
    el.addEventListener('transitionend', onIn);
  };
  el.addEventListener('transitionend', onOut);
}

function syncSidebarCalendar() {
  // The sidebar calendar is OUTSIDE the turbo-frame, so its Stimulus
  // controller survives frame swaps. After a navigation, push the new
  // selected date into the calendar so the sidebar stays in sync with
  // the agenda grid.
  const root = document.getElementById('scheduling-calendar');
  if (!root || !window.Stimulus || typeof window.Stimulus.getControllerForElementAndIdentifier !== 'function') return;
  const ctrl = window.Stimulus.getControllerForElementAndIdentifier(root, 'calendar');
  if (ctrl && typeof ctrl.setSelected === 'function') {
    ctrl.setSelected(AGENDA_DATA.selectedDate);
  }
}

// =============== CALENDAR ↔ AGENDA NAVIGATION ===============
// All frame navigations — sidebar calendar click, header link click, etc. —
// go through the same path: Turbo swaps the frame contents, then we run
// bootstrapFromPayload() and manually push the new URL to history. We do
// NOT use data-turbo-action="advance" on the frame because it caused layout
// glitches in our flex shell when combined with link clicks.
let _calendarSelectHandler = null;
let _frameLoadHandler = null;
let _popstateHandler = null;
let _navClickHandler = null;
let _beforeFrameRenderHandler = null;
let _frameRenderHandler = null;
let _appointmentSavedHandler = null;
let _bootstrappedOnce = false;

// Direction of the upcoming frame swap — 'prev', 'next' or null (fade only).
// Set by onNavigationClick / onCalendarSelect before the frame reloads, then
// consumed and cleared by the turbo frame render handlers.
let _pendingNavDirection = null;

// Transition tuning (ms / px / opacity).
const NAV_OUT_MS = 80;
const NAV_IN_MS = 140;
const NAV_SHIFT_PX = 24;
// Minimum opacity during the swap — keep the grid mostly visible so the
// slide reads as "motion" rather than "content disappearing".
const NAV_MIN_OPACITY = '0.55';

function computeDirection(newIso) {
  if (!newIso || !AGENDA_DATA || !AGENDA_DATA.selectedDate) return null;
  // In week view, a navigation that stays within the same Monday-Sunday
  // window shouldn't slide — the grid content is identical, only the
  // selected-day state changes. Compare week anchors in that case.
  const compareA = view === 'week' ? weekAnchor(AGENDA_DATA.selectedDate) : AGENDA_DATA.selectedDate;
  const compareB = view === 'week' ? weekAnchor(newIso) : newIso;
  if (compareB < compareA) return 'prev';
  if (compareB > compareA) return 'next';
  return null;
}

function weekAnchor(iso) {
  const [y, m, d] = iso.split('-').map(Number);
  return isoDate(mondayOf(new Date(y, m - 1, d)));
}

function directionFromHref(href) {
  try {
    const url = new URL(href, window.location.origin);
    let date = url.searchParams.get('date');
    if (!date) {
      // "Today" link omits the date param — resolve it client-side in the
      // clinic timezone so it can be compared to selectedDate.
      date = toClinicLocalParts(new Date(), AGENDA_DATA.clinicTimezone).dateStr;
    }
    return computeDirection(date);
  } catch (_e) {
    return null;
  }
}

function onCalendarSelect(e) {
  const iso = e && e.detail && e.detail.date;
  if (!iso || typeof iso !== 'string') return;
  if (iso === AGENDA_DATA.selectedDate) return; // no-op re-click

  // In week view, staying within the same Monday-Sunday window means the
  // grid content is identical — skip the server roundtrip entirely and
  // just patch local state + URL so the Day/Week toggles and history
  // still reflect the newly picked day.
  if (view === 'week' && weekAnchor(iso) === weekAnchor(AGENDA_DATA.selectedDate)) {
    AGENDA_DATA.selectedDate = iso;
    patchHeaderNav();
    const nextUrl = `/scheduling/agenda?date=${encodeURIComponent(iso)}&view=week`;
    const current = window.location.pathname + window.location.search;
    if (current !== nextUrl) window.history.pushState({}, '', nextUrl);
    return;
  }

  const frame = document.getElementById('agenda-frame');
  if (!frame) return;
  _pendingNavDirection = computeDirection(iso);
  frame.src = `/scheduling/agenda?date=${encodeURIComponent(iso)}&view=${encodeURIComponent(view)}`;
}

function onNavigationClick(e) {
  // Capture-phase delegated click on any <a data-turbo-frame="agenda-frame">,
  // fired before Turbo intercepts the click. The header lives OUTSIDE the
  // frame so we match by attribute instead of DOM containment.
  const target = e.target;
  if (!target || typeof target.closest !== 'function') return;
  const a = target.closest('a[data-turbo-frame="agenda-frame"]');
  if (!a || !a.href) return;

  // For the Day/Week view toggles, the sidebar calendar is the source of
  // truth for the selected date. Read its current selection and rewrite the
  // href just before Turbo navigates — this prevents a race where a recent
  // calendar click hasn't finished reloading the frame yet, leaving the
  // toggle's href pointing at the previous date.
  const nav = a.getAttribute('data-nav');
  if (nav === 'day-view' || nav === 'week-view') {
    const calendarIso = getSidebarCalendarSelected();
    if (calendarIso) {
      const targetView = nav === 'day-view' ? 'day' : 'week';
      a.href = `/scheduling/agenda?date=${encodeURIComponent(calendarIso)}&view=${targetView}`;
    }
  }

  _pendingNavDirection = directionFromHref(a.href);
}

function getSidebarCalendarSelected() {
  const root = document.getElementById('scheduling-calendar');
  if (!root || !window.Stimulus || typeof window.Stimulus.getControllerForElementAndIdentifier !== 'function') return null;
  const ctrl = window.Stimulus.getControllerForElementAndIdentifier(root, 'calendar');
  if (!ctrl) return null;
  const iso = ctrl.selectedValue;
  return iso && typeof iso === 'string' ? iso : null;
}

function onBeforeFrameRender(e) {
  const frame = e.target;
  if (!frame || frame.id !== 'agenda-frame') return;

  // We animate `.agenda-wrap` (grid only), not the frame itself, so that
  // the page header — still inside the frame so its hrefs and label stay
  // server-rendered — doesn't slide along with the grid.
  const wrap = frame.querySelector('.agenda-wrap');
  if (!wrap) return;

  // No direction = same view anchor (e.g. another day within the same week
  // in week view). Skip the transition entirely so the grid stays stable.
  if (!_pendingNavDirection) return;

  // Defer Turbo's DOM swap so the "slide-out + fade-out" transition can run
  // to completion first, then resume() below hands control back to Turbo.
  e.preventDefault();

  const dir = _pendingNavDirection;
  const outX = dir === 'next' ? `-${NAV_SHIFT_PX}px` : `${NAV_SHIFT_PX}px`;

  wrap.style.willChange = 'opacity, transform';
  wrap.style.transition = `opacity ${NAV_OUT_MS}ms ease-out, transform ${NAV_OUT_MS}ms ease-out`;
  wrap.style.opacity = NAV_MIN_OPACITY;
  wrap.style.transform = `translateX(${outX})`;

  window.setTimeout(() => {
    if (e.detail && typeof e.detail.resume === 'function') e.detail.resume();
  }, NAV_OUT_MS + 10);
}

function onFrameRender(e) {
  const frame = e.target;
  if (!frame || frame.id !== 'agenda-frame') return;

  // The new DOM is already mounted — grab the fresh .agenda-wrap.
  const wrap = frame.querySelector('.agenda-wrap');
  if (!wrap) return;

  // No direction = no animation on the way out either; bail out without
  // touching styles so the mounted DOM stays put.
  if (!_pendingNavDirection) return;

  const dir = _pendingNavDirection;
  const inX = dir === 'next' ? `${NAV_SHIFT_PX}px` : `-${NAV_SHIFT_PX}px`;

  // Jump to the "entering" position without transition, then animate back
  // to the resting state after a forced reflow so the transition actually
  // kicks in on the already-mounted new DOM.
  wrap.style.transition = 'none';
  wrap.style.opacity = NAV_MIN_OPACITY;
  wrap.style.transform = `translateX(${inX})`;
  // eslint-disable-next-line no-unused-expressions
  wrap.offsetHeight;

  wrap.style.transition = `opacity ${NAV_IN_MS}ms ease-out, transform ${NAV_IN_MS}ms ease-out`;
  wrap.style.opacity = '1';
  wrap.style.transform = 'translateX(0)';

  const onEnd = () => {
    wrap.style.transition = '';
    wrap.style.transform = '';
    wrap.style.opacity = '';
    wrap.style.willChange = '';
    _pendingNavDirection = null;
    wrap.removeEventListener('transitionend', onEnd);
  };
  wrap.addEventListener('transitionend', onEnd);
}

function onFrameLoad(e) {
  const frame = e.target;
  if (!frame || frame.id !== 'agenda-frame') return;

  bootstrapFromPayload();

  // Skip history manipulation on the very first frame-load that fires when
  // the frame is initially parsed on a full page load — the URL is already
  // correct and pushing again would create a duplicate entry.
  if (!_bootstrappedOnce) {
    _bootstrappedOnce = true;
    return;
  }

  // Sync the browser URL with the new frame src so back/forward and
  // bookmarks work. Only push when the target differs from the current
  // location to avoid no-op duplicate entries.
  if (frame.src) {
    const target = new URL(frame.src, window.location.origin);
    const current = window.location.pathname + window.location.search;
    const next = target.pathname + target.search;
    if (current !== next) {
      window.history.pushState({}, '', next);
    }
  }
}

function onPopState() {
  // Browser back/forward — reload the frame from the new URL so the agenda
  // matches the history entry the user just moved to.
  const frame = document.getElementById('agenda-frame');
  if (!frame) return;
  const url = window.location.pathname + window.location.search;
  const absolute = new URL(url, window.location.origin).href;
  if (frame.src !== absolute) {
    frame.src = url;
  }
}

// =============== INIT / CLEANUP ===============
export function init() {
  bootstrapFromPayload();

  if (!_calendarSelectHandler) {
    _calendarSelectHandler = onCalendarSelect;
    document.addEventListener('calendar:select', _calendarSelectHandler);
  }
  if (!_frameLoadHandler) {
    _frameLoadHandler = onFrameLoad;
    document.addEventListener('turbo:frame-load', _frameLoadHandler);
  }
  if (!_popstateHandler) {
    _popstateHandler = onPopState;
    window.addEventListener('popstate', _popstateHandler);
  }
  if (!_navClickHandler) {
    _navClickHandler = onNavigationClick;
    // Capture phase so we set the direction BEFORE Turbo's bubble-phase
    // link handler decides to navigate the frame.
    document.addEventListener('click', _navClickHandler, true);
  }
  if (!_beforeFrameRenderHandler) {
    _beforeFrameRenderHandler = onBeforeFrameRender;
    document.addEventListener('turbo:before-frame-render', _beforeFrameRenderHandler);
  }
  if (!_frameRenderHandler) {
    _frameRenderHandler = onFrameRender;
    document.addEventListener('turbo:frame-render', _frameRenderHandler);
  }
  if (!_appointmentSavedHandler) {
    _appointmentSavedHandler = onAppointmentSaved;
    document.addEventListener('appointment:saved', _appointmentSavedHandler);
  }
}

export function cleanup() {
  delete window.togglePlanningOverlay;
  delete window.toggleVet;
  delete window.toggleAgendaSidebar;
  delete window.closeAgendaSidebar;
  delete window.closeAllPopups;
  delete window.toggleFreeSlots;
  delete window.setFreeDur;
  delete window.openNewRdvGlobal;
  delete window.editAppointment;
  delete window.cancelAppointment;
  delete window.cancelCancelConfirm;
  delete window.confirmCancel;
  delete window.__agendaVets;

  if (_calendarSelectHandler) {
    document.removeEventListener('calendar:select', _calendarSelectHandler);
    _calendarSelectHandler = null;
  }
  if (_frameLoadHandler) {
    document.removeEventListener('turbo:frame-load', _frameLoadHandler);
    _frameLoadHandler = null;
  }
  if (_popstateHandler) {
    window.removeEventListener('popstate', _popstateHandler);
    _popstateHandler = null;
  }
  if (_navClickHandler) {
    document.removeEventListener('click', _navClickHandler, true);
    _navClickHandler = null;
  }
  if (_beforeFrameRenderHandler) {
    document.removeEventListener('turbo:before-frame-render', _beforeFrameRenderHandler);
    _beforeFrameRenderHandler = null;
  }
  if (_frameRenderHandler) {
    document.removeEventListener('turbo:frame-render', _frameRenderHandler);
    _frameRenderHandler = null;
  }
  if (_appointmentSavedHandler) {
    document.removeEventListener('appointment:saved', _appointmentSavedHandler);
    _appointmentSavedHandler = null;
  }
  _bootstrappedOnce = false;
  _pendingNavDirection = null;
}
