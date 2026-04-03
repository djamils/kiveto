/**
 * Page module — Select Clinic
 * Loaded by app.js dispatcher on turbo:load.
 */

let selectedId = null;
let scrollHandler = null;

/**
 * Show a brief toast notification at the bottom of the screen.
 */
function toast(msg, color = '#16a34a', icon = '\u2713') {
  const t = document.getElementById('toast');
  t.innerHTML = `<span aria-hidden="true">${icon}</span><span>${msg}</span>`;
  t.style.cssText = `background:${color};opacity:1;transform:translateX(-50%) translateY(0)`;
  clearTimeout(t._t);
  t._t = setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translateX(-50%) translateY(8px)';
  }, 2500);
}

/**
 * Render the clinic list into the DOM.
 */
function renderList(list) {
  const el = document.getElementById('clinic-list');
  if (!list.length) {
    el.innerHTML = `<div class="list-empty">
      <p style="font-size:var(--text-base);font-weight:var(--weight-medium);color:var(--text-muted)">Aucune clinique trouvée</p>
      <p style="font-size:var(--text-md);color:var(--text-subtle)">Essayez un autre terme.</p>
    </div>`;
    updateHint();
    return;
  }
  el.innerHTML = list.map(c => {
    const sel = c.id === selectedId;
    const letter = c.name.charAt(0).toUpperCase();
    return `<div class="clinic-item${sel ? ' is-selected' : ''}" id="ci-${c.id}"
      onclick="pick('${c.id}')" role="option" aria-selected="${sel}" tabindex="0"
      onkeydown="if(event.key==='Enter'||event.key===' '){pick('${c.id}');event.preventDefault()}">
      <div class="clinic-logo" style="background:var(--brand-900)" aria-hidden="true">
        ${letter}
      </div>
      <div class="clinic-info">
        <p class="clinic-name">${c.name}</p>
        <div class="clinic-meta">
          <span class="meta-item">${c.role}</span>
          <div class="meta-sep" aria-hidden="true"></div>
          <span class="meta-item">${c.engagement}</span>
        </div>
        <div class="clinic-address">
          <svg width="9" height="9" fill="none" viewBox="0 0 12 12" aria-hidden="true"><path d="M6 1a4 4 0 014 4c0 3-4 7-4 7S2 8 2 5a4 4 0 014-4z" stroke="currentColor" stroke-width="1.2"/></svg>
          ${c.slug}
        </div>
      </div>
      <div class="clinic-right">
        <div class="clinic-check" aria-hidden="true">
          <svg width="11" height="11" fill="none" viewBox="0 0 12 12"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
      </div>
    </div>`;
  }).join('');
  updateHint();
}

/**
 * Update the scroll hint and bottom-fade visibility.
 */
function updateHint() {
  const list = document.getElementById('clinic-list');
  const hint = document.getElementById('scroll-hint');
  const zone = document.getElementById('list-zone');
  const more = list.scrollHeight > list.clientHeight + 8;
  hint.classList.toggle('hidden', !more);
  zone.classList.toggle('at-bottom', !more);
}

export function init() {
  // Reset state for Turbo navigation
  selectedId = null;

  // Read the CLINICS data injected by the template
  const clinics = window.CLINICS || [];

  // Expose functions globally for inline onclick handlers
  window.toast = toast;

  window.filterList = function (val) {
    const q = val.toLowerCase().trim();
    renderList(clinics.filter(c => !q || c.name.toLowerCase().includes(q) || c.slug.toLowerCase().includes(q)));
  };

  window.pick = function (id) {
    selectedId = id;
    document.querySelectorAll('.clinic-item').forEach(el => {
      const mine = el.id === `ci-${id}`;
      el.classList.toggle('is-selected', mine);
      el.setAttribute('aria-selected', mine);
    });
    const c = clinics.find(x => x.id === id);
    document.getElementById('sel-dot').classList.add('active');
    const lbl = document.getElementById('sel-label');
    lbl.textContent = c.name;
    lbl.classList.add('active');
    document.getElementById('enter-btn').disabled = false;
  };

  window.enter = function () {
    if (!selectedId) return;
    // Submit the hidden form to POST the clinic selection
    document.getElementById('clinic-select-input').value = selectedId;
    document.getElementById('clinic-select-form').submit();
  };

  // Attach scroll listener to the clinic list
  const clinicList = document.getElementById('clinic-list');
  scrollHandler = function () {
    const atBottom = clinicList.scrollHeight - clinicList.scrollTop - clinicList.clientHeight < 20;
    document.getElementById('list-zone').classList.toggle('at-bottom', atBottom);
    document.getElementById('scroll-hint').classList.toggle('hidden', atBottom);
  };
  clinicList.addEventListener('scroll', scrollHandler);

  // Initial render
  renderList(clinics);
}

export function cleanup() {
  // Remove scroll listener
  const clinicList = document.getElementById('clinic-list');
  if (clinicList && scrollHandler) {
    clinicList.removeEventListener('scroll', scrollHandler);
  }
  scrollHandler = null;
  selectedId = null;

  // Remove global references
  delete window.toast;
  delete window.filterList;
  delete window.pick;
  delete window.enter;
}
