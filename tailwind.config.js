/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.html.twig',
    './assets/**/*.js',
    './assets/styles/tailwind.css',
  ],

  /* Disable Preflight — Kiveto has its own CSS reset in kiveto.css */
  corePlugins: {
    preflight: false,
  },

  theme: {
    /* ── Font family ── */
    fontFamily: {
      sans: ['DM Sans', 'system-ui', '-apple-system', 'sans-serif'],
    },

    /* ── Font size (custom 6-step scale) ── */
    fontSize: {
      xs:   ['11px', { lineHeight: '1.2' }],
      sm:   ['12px', { lineHeight: '1.5' }],
      md:   ['13px', { lineHeight: '1.5' }],
      base: ['14px', { lineHeight: '1.5' }],
      lg:   ['16px', { lineHeight: '1.5' }],
      xl:   ['20px', { lineHeight: '1.2' }],
    },

    /* ── Font weight ── */
    fontWeight: {
      normal:   '400',
      medium:   '500',
      semibold: '600',
      bold:     '700',
    },

    /* ── Border radius ── */
    borderRadius: {
      sm:   '4px',
      md:   '8px',
      lg:   '12px',
      full: '9999px',
    },

    /* ── Z-index (semantic scale) ── */
    zIndex: {
      dropdown: '100',
      sticky:   '200',
      drawer:   '300',
      overlay:  '400',
      modal:    '500',
      popover:  '600',
      toast:    '9000',
      tooltip:  '9999',
    },

    extend: {
      /* ── Colors ── */
      colors: {
        brand: {
          50:  '#eef2ff',
          100: '#e0e7ff',
          200: '#c7d2fe',
          400: '#818cf8',
          500: '#6366f1',
          600: '#4338ca',
          700: '#3730a3',
          800: '#2d2a6e',
          900: '#1e1b4b',
          950: '#0d0b2e',
        },
        slate: {
          50:  '#f8fafc',
          100: '#f1f5f9',
          150: '#e8edf2',
          200: '#e2e8f0',
          300: '#cbd5e1',
          400: '#94a3b8',
          500: '#64748b',
          600: '#475569',
          700: '#334155',
          800: '#1e293b',
          900: '#0f172a',
          950: '#020617',
        },
        success: {
          bg:     '#f0fdf4',
          text:   '#15803d',
          border: '#bbf7d0',
          600:    '#16a34a',
        },
        warning: {
          bg:     '#fff7ed',
          text:   '#c2410c',
          border: '#fed7aa',
          600:    '#d97706',
        },
        danger: {
          bg:     '#fef2f2',
          text:   '#b91c1c',
          border: '#fecaca',
          600:    '#dc2626',
        },
        info: {
          bg:     '#eff6ff',
          text:   '#1d4ed8',
          border: '#bfdbfe',
        },
        purple: {
          bg:     '#f5f3ff',
          text:   '#7c3aed',
          border: '#ddd6fe',
        },
        surface: {
          page:   '#f1f5f9',
          card:   '#ffffff',
          subtle: '#f8fafc',
          hover:  '#f1f5f9',
        },
        text: {
          primary:   '#0f172a',
          secondary: '#334155',
          muted:     '#64748b',
          subtle:    '#94a3b8',
          disabled:  '#cbd5e1',
        },
        border: {
          light:  '#e8edf2',
          medium: '#e2e8f0',
          strong: '#cbd5e1',
        },
      },

      /* ── Spacing (4px base) ── */
      spacing: {
        0:  '0px',
        1:  '4px',
        2:  '8px',
        3:  '12px',
        4:  '16px',
        5:  '20px',
        6:  '24px',
        8:  '32px',
        10: '40px',
        12: '48px',
        16: '64px',
      },

      /* ── Box shadow ── */
      boxShadow: {
        xs:           '0 1px 2px rgba(0,0,0,.06)',
        sm:           '0 1px 4px rgba(0,0,0,.08)',
        md:           '0 4px 12px rgba(0,0,0,.08), 0 1px 3px rgba(0,0,0,.05)',
        lg:           '0 8px 24px rgba(0,0,0,.10), 0 2px 6px rgba(0,0,0,.06)',
        xl:           '0 20px 48px rgba(0,0,0,.14), 0 4px 12px rgba(0,0,0,.08)',
        focus:        '0 0 0 3px rgba(99,102,241,.18)',
        'focus-danger': '0 0 0 3px rgba(220,38,38,.14)',
      },

      /* ── Layout dimensions ── */
      width: {
        sidebar: '232px',
        rpanel:  '310px',
      },
      minWidth: {
        0: '0px',
      },
      maxWidth: {
        'modal':    '480px',
        'modal-sm': '360px',
        'modal-lg': '680px',
        'modal-xl': '90vw',
      },
      maxHeight: {
        'modal': '90vh',
        60: '240px',
      },
      height: {
        topbar: '52px',
        screen: '100vh',
      },

      /* ── Transition timing function ── */
      transitionTimingFunction: {
        DEFAULT: 'cubic-bezier(0.4, 0, 0.2, 1)',
        overlay: 'cubic-bezier(0.16, 1, 0.3, 1)',
      },
    },
  },

  plugins: [],
};
