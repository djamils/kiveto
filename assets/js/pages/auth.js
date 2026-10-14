/**
 * Page module -- sign-in stage (connexion_3.html)
 * Loaded by the app.js dispatcher on clinic_login, clinic_lock,
 * clinic_select_clinic and clinic_no_access.
 *
 * Ports the mock-up behaviour that has a backend:
 *   - screen transitions (slide in from the right, or from the left going back)
 *   - password reveal, caps-lock hint, field validation, loading button,
 *     shake on wrong credentials
 *   - « Entrée » welcome screen with its progress bar before the clinic opens
 *   - clinic picker with 1-9 keyboard shortcuts and « Changer de clinique »
 * The login is posted as JSON to the ContextAuthenticator, which answers with
 * the next URL (200) or a 401.
 */
import { showToast } from 'kiveto/shell';

const $ = (id) => document.getElementById(id);

const OEIL = [
  '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>',
  '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10.7 5.1A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-2.2 3.2M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a9.700 9.700 0 0 0 5.400-1.600M3 3l18 18M9.900 9.900a3 3 0 0 0 4.200 4.200"/></svg>',
];
const CAPS = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m12 4-8 8h5v6h6v-6h5Z"/></svg>Verrouillage des majuscules activé';
const EMAIL = /\S+@\S+\.\S+/;

let timers = [];
let keyHandler = null;

const later = (fn, ms) => { timers.push(setTimeout(fn, ms)); };
const greeting = () => (new Date().getHours() < 18 ? 'Bonjour' : 'Bonsoir');

/* ═════════ Navigation between screens ═════════ */
function aller(id, { retour = false } = {}) {
  document.querySelectorAll('body.auth .ecran').forEach((e) => e.classList.remove('on', 'retour'));
  const e = $('e-' + id);
  e.classList.add('on');
  if (retour) e.classList.add('retour');
  const f = e.querySelector('input:not([disabled]):not([type=checkbox]):not([type=hidden])');
  if (f) later(() => f.focus(), 60);
}

/* ═════════ « Entrée » ═════════ */
function bienvenue(sous, { canChange = false } = {}) {
  $('t-ok').textContent = greeting();
  $('okSous').textContent = sous;
  $('okChg').hidden = !canChange;
  const bar = $('e-ok').querySelector('.barre i');
  bar.style.animation = 'none';
  void bar.offsetWidth;
  bar.style.animation = '';
  aller('ok');
}

/* ═════════ Password field ═════════ */
function bindPassword() {
  const b = $('oeil'), i = $('mdp'), a = $('mdpAide');
  if (!b || !i) return;

  b.innerHTML = OEIL[0];
  b.addEventListener('click', () => {
    const v = i.type === 'password';
    i.type = v ? 'text' : 'password';
    b.innerHTML = OEIL[v ? 1 : 0];
    b.setAttribute('aria-pressed', String(v));
    b.setAttribute('aria-label', v ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    i.focus();
  });

  const majAide = (e) => {
    if (e.getModifierState && e.getModifierState('CapsLock')) {
      a.className = 'aide ambre';
      a.innerHTML = CAPS;
    } else if (a.classList.contains('ambre')) {
      a.className = 'aide';
      a.textContent = '';
    }
  };
  i.addEventListener('keyup', majAide);
  i.addEventListener('keydown', majAide);

  ['mail', 'mdp'].forEach((id) => $(id)?.addEventListener('input', () => {
    $('iMail')?.classList.remove('ko');
    $('iMdp').classList.remove('ko');
    if (a.classList.contains('ko')) { a.className = 'aide'; a.textContent = ''; }
  }));
}

function refuser(field, input, txt, { shake = false } = {}) {
  const a = $('mdpAide');
  $(field).classList.add('ko');
  if (shake) {
    $(field).classList.add('secoue');
    later(() => $(field).classList.remove('secoue'), 400);
  }
  a.className = 'aide ko';
  a.textContent = txt;
  if (shake) $(input).select(); else $(input).focus();
}

function charge(b, on) {
  b.classList.toggle('charge', on);
  b.disabled = on;
}

/* ═════════ Login ═════════ */
function bindLogin() {
  const form = $('fMdp');
  if (!form) return;

  const oubli = $('lOubli');
  oubli.addEventListener('keydown', (e) => { if (e.key === 'Enter') oubli.click(); });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const m = $('mail').value.trim(), p = $('mdp').value, b = $('bMdp');

    if (!EMAIL.test(m)) { refuser('iMail', 'mail', 'Saisissez votre adresse e-mail.'); return; }
    if (!p) { refuser('iMdp', 'mdp', 'Saisissez votre mot de passe.'); return; }

    charge(b, true);
    let res;
    try {
      res = await fetch(form.action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email: m, password: p }),
      });
    } catch {
      charge(b, false);
      refuser('iMdp', 'mdp', 'Connexion impossible pour le moment. Réessayez.');
      return;
    }

    if (!res.ok) {
      charge(b, false);
      if (res.status === 401) refuser('iMdp', 'mdp', 'E-mail ou mot de passe incorrect.', { shake: true });
      else refuser('iMdp', 'mdp', 'Connexion impossible pour le moment. Réessayez.');
      return;
    }

    const data = await res.json().catch(() => ({}));
    const url = typeof data.redirect === 'string' ? data.redirect : form.action;
    charge(b, false);

    // Straight into a clinic: play the welcome screen first. The clinic picker
    // and the « no clinic » page are screens of their own.
    if (new URL(url, location.href).pathname === form.dataset.dashboard) {
      bienvenue('Ouverture de votre clinique…');
      later(() => { location.href = url; }, 1300);
    } else {
      location.href = url;
    }
  });
}

/* ═════════ Lock ═════════ */
function bindLock() {
  const form = $('fVerrou');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!$('mdp').value) { refuser('iMdp', 'mdp', 'Saisissez votre mot de passe.'); return; }
    charge($('bVerrou'), true);
    showToast('Déverrouillé');
    later(() => { location.href = form.action; }, 600);
  });
}

/* ═════════ Clinic picker ═════════ */
function bindClinics() {
  const list = $('clins');
  if (!list) return;

  const ouvrir = (btn) => {
    timers.forEach(clearTimeout);
    timers = [];
    bienvenue(btn.dataset.clinicName, { canChange: list.children.length > 1 });
    later(() => {
      $('clinic-select-input').value = btn.dataset.clinicId;
      $('clinic-select-form').requestSubmit();
    }, list.children.length > 1 ? 2200 : 1300);
  };

  list.addEventListener('click', (e) => {
    const b = e.target.closest('.clin');
    if (b) ouvrir(b);
  });

  const changer = () => {
    timers.forEach(clearTimeout);
    timers = [];
    aller('clin', { retour: true });
  };
  $('lChg').addEventListener('click', changer);
  $('lChg').addEventListener('keydown', (e) => { if (e.key === 'Enter') changer(); });

  keyHandler = (e) => {
    if (e.target instanceof Element && e.target.matches('input')) return;
    if (e.metaKey || e.ctrlKey || e.altKey) return;
    if (!$('e-clin')?.classList.contains('on') || !/^[1-9]$/.test(e.key)) return;
    const b = list.children[Number(e.key) - 1];
    if (b) { e.preventDefault(); b.click(); }
  };
  document.addEventListener('keydown', keyHandler);
}

export function init() {
  // The opening screen takes the focus, as every screen does on arrival.
  const first = document.querySelector('body.auth .ecran.on input:not([disabled]):not([type=checkbox]):not([type=hidden])');
  if (first) later(() => first.focus(), 60);

  bindPassword();
  bindLogin();
  bindLock();
  bindClinics();
}

export function cleanup() {
  timers.forEach(clearTimeout);
  timers = [];
  if (keyHandler) document.removeEventListener('keydown', keyHandler);
  keyHandler = null;
}
