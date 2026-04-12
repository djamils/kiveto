---
title: 'International Phone Input Component'
slug: 'phone-input-component'
created: '2026-04-12'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - PHP 8.4
  - Symfony 7.4 (FormType custom, form theme, Validator)
  - Stimulus 3.2.2 (phone_input_controller.js)
  - Twig 3 + Kiveto form theme (kiveto_form_theme.html.twig)
  - Tailwind CSS v4 + custom tokens (kiveto.css)
  - flag-icons (CDN SVG sprites — CSS only, no JS dependency)
  - AssetMapper importmap
  - PHPUnit 12, Foundry 2.x
files_to_modify:
  - src/Shared/Domain/ValueObject/PhoneNumber.php (enforce E.164 strict)
  - src/Presentation/Clinic/Form/Client/ClientFormType.php (replace TelType with PhoneType)
  - src/Presentation/Clinic/Controller/Client/Profile/CreateClientController.php (remove Regex constraint handling, phone arrives as E.164)
  - templates/form/kiveto_form_theme.html.twig (add phone_widget block)
  - templates/base.html.twig (add flag-icons CDN stylesheet)
  - assets/styles/components/forms.css (add ki-phone-* styles)
  - tests/Unit/Shared/Domain/ValueObject/PhoneNumberTest.php (update for E.164 strict)
  - tests/Unit/Presentation/Clinic/Form/Client/ClientFormTypeTest.php (update phone assertions)
  - src/Presentation/Clinic/Controller/Client/Profile/UpdateClientController.php (guard PhoneNumber::fromString with try/catch — F1 fix)
  - src/Context/Client/Application/Command/ReplaceClientContactMethods/ReplaceClientContactMethodsHandler.php (verify PhoneNumber::fromString() call path — F5 check)
files_to_create:
  - src/Shared/Presentation/Form/Type/PhoneType.php (custom FormType)
  - assets/controllers/phone_input_controller.js (Stimulus controller)
  - templates/components/ui/phone.html.twig (Twig partial)
  - tests/Unit/Shared/Presentation/Form/Type/PhoneTypeTest.php (FormType unit tests)
code_patterns:
  - Single-action controllers (__invoke, no business logic)
  - Custom Symfony FormType extending AbstractType with configureOptions + buildForm
  - Symfony form theme block naming convention: {type_name}_widget (e.g. phone_widget)
  - ki-select component architecture: hidden input for real value + visible trigger + dropdown (select_controller.js)
  - Stimulus controller with static targets/values, connect()/disconnect() lifecycle
  - form-input height 36px, Tailwind @apply within @layer components
  - PhoneNumber VO is the server-side source of truth for phone format validation
test_patterns:
  - Unit: FormTypes tested via Symfony FormTestCase + ValidatorExtension (existing pattern in ClientFormTypeTest)
  - Unit: PhoneNumber VO tested via PHPUnit TestCase (15 existing cases in PhoneNumberTest)
  - Unit: Stimulus controllers manually tested (no Vitest/Playwright in project)
  - self::assertSame() everywhere, never assertEquals()
---

# Tech-Spec: International Phone Input Component

**Created:** 2026-04-12

## Overview

### Problem Statement

Le champ téléphone est un simple `TelType` sans indicatif pays ni masque de saisie. L'utilisateur peut saisir n'importe quoi (lettres, formats incohérents), ce qui provoque un 500 du VO `PhoneNumber::fromString()` quand la saisie ne matche pas la regex `/^[+]?[0-9]{6,20}$/`. Aucune normalisation au format E.164 côté front, aucun feedback visuel de l'indicatif, aucun masque d'aide à la saisie. Bloque la qualité des données téléphone dès la première utilisation.

### Solution

Créer un composant transversal composé de :
- Un **Symfony FormType custom** `PhoneType` qui rend un sélecteur d'indicatif (drapeaux SVG via flag-icons CDN) + un input masqué par pays, et expose la valeur E.164 dans un hidden field côté serveur.
- Un **Stimulus controller** `phone_input_controller.js` qui gère le comportement interactif (masque dynamique, mise à jour du hidden E.164, changement d'indicatif).
- Un **template Twig** `components/ui/phone.html.twig` intégré au form theme Kiveto.
- ~15 pays pré-configurés (FR, BE, CH, LU, MA, TN, DZ, ES, IT, DE, GB, US, CA) + "Autre" en fallback.
- France (+33) par défaut, hardcodé.
- Mise à jour du VO `PhoneNumber` pour enforcer le format E.164 strict.

### Scope

**In Scope:**

- `PhoneType` FormType custom Symfony (validation serveur incluse, réutilisable dans tout FormType futur)
- `phone_input_controller.js` Stimulus (masque dynamique par pays, indicatif, hidden E.164)
- `components/ui/phone.html.twig` Twig partial
- Intégration au form theme `kiveto_form_theme.html.twig` (block `phone_widget`)
- Flag-icons via CDN dans `templates/base.html.twig`
- Données de masquage par pays pour les ~15 pays (FR, BE, CH, LU, MA, TN, DZ, ES, IT, DE, GB, US, CA) + "Autre" avec input libre
- Remplacement du `TelType` existant dans `ClientFormType` uniquement
- Validation côté serveur compatible avec le VO `PhoneNumber`
- Mise à jour du VO `PhoneNumber` pour enforcer le format E.164 strict en entrée (commence par `+`, suivi de l'indicatif, digits uniquement). DB resetée à chaque merge en alpha — pas de rétro-compatibilité nécessaire.

**Out of Scope:**

- Liste exhaustive 240+ pays (évolution future, le composant est conçu pour l'accueillir)
- Détection automatique via `navigator.language`
- Validation libphonenumber (dépendance lourde)
- Intégration dans d'autres FormTypes que `ClientFormType` (sera fait spec par spec quand les formulaires existeront)

## Context for Development

### Codebase Patterns (verified in Step 2)

- **PhoneNumber VO** (`src/Shared/Domain/ValueObject/PhoneNumber.php`): strips whitespace, validates `/^[+]?[0-9]{6,20}$/`. Stores the cleaned string. Currently accepts both `0612345678` (local) and `+33612345678` (international). Will be tightened to E.164 strict: must start with `+`, digits only after that, 7-15 digits total per ITU-T E.164.

- **ContactMethod domain object** (`src/Context/Client/Domain/ValueObject/ContactMethod.php`): factory `ContactMethod::phone(PhoneNumber, ContactLabel, isPrimary)`. Stores `$phoneNumber->toString()` as the value. The E.164 string flows from FormType hidden field → Controller → ContactMethodDto → CreateClientHandler → PhoneNumber::fromString() → ContactMethod::phone().

- **CreateClientController** (`src/Presentation/Clinic/Controller/Client/Profile/CreateClientController.php`): reads `$data['phone']` from form array (data_class null), builds `ContactMethodDto(type: 'phone', label: 'mobile', value: $phone)`. With PhoneType, the `$data['phone']` will already be E.164 from the hidden field.

- **ClientFormType** (`src/Presentation/Clinic/Form/Client/ClientFormType.php`): currently uses `TelType` with `Regex` constraint `/^[+\s]?[\d\s]{6,20}$/`. Will be replaced by `PhoneType` which handles its own validation internally.

- **Kiveto form theme** (`templates/form/kiveto_form_theme.html.twig`): provides blocks `form_row`, `form_widget_simple`, `choice_widget_collapsed`, etc. `TelType` renders via `form_widget_simple` (plain `<input type="tel">`). No `tel_widget` or `phone_widget` block exists yet. Adding `phone_widget` will make any `PhoneType` field auto-render with the compound component.

- **ki-select component** (`assets/controllers/select_controller.js` + `templates/components/ui/select.html.twig`): reference architecture for the phone country picker. Uses hidden `<input>` for the real form value + visible trigger button + dropdown panel.

- **Forms CSS** (`assets/styles/components/forms.css`): `.form-input` is 36px height. `.ki-select-trigger` is also 36px. The phone compound input must combine a narrow country selector (~60px for flag + dial code) + the number input in a single row at 36px.

- **Flag-icons**: CSS-only SVG flag library. Usage: `<span class="fi fi-fr"></span>` renders the French flag. Loaded via a single CSS file from CDN.

- **Existing tests**: `PhoneNumberTest` (15 cases) tests format validation. `ClientFormTypeTest` (7 cases) tests form constraints.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Shared/Domain/ValueObject/PhoneNumber.php` | VO to tighten to E.164 strict |
| `tests/Unit/Shared/Domain/ValueObject/PhoneNumberTest.php` | 15 existing tests to update |
| `src/Presentation/Clinic/Form/Client/ClientFormType.php` | Replace TelType with PhoneType |
| `src/Presentation/Clinic/Controller/Client/Profile/CreateClientController.php` | Reads phone from form data |
| `src/Context/Client/Application/Command/CreateClient/CreateClientHandler.php` | Calls PhoneNumber::fromString() |
| `src/Context/Client/Domain/ValueObject/ContactMethod.php` | Factory ContactMethod::phone() |
| `templates/form/kiveto_form_theme.html.twig` | Add phone_widget block |
| `templates/base.html.twig` | Add flag-icons CDN `<link>` |
| `assets/styles/components/forms.css` | Add ki-phone-* CSS |
| `assets/controllers/select_controller.js` | Reference architecture for dropdown |
| `templates/components/ui/select.html.twig` | Reference for Twig partial structure |

### Technical Decisions

- **D1 — PhoneType is a compound field**: renders a hidden `<input name="phone" value="+33612345678" autocomplete="off">` (the real form value) + a visible country selector + a visible number input (`type="tel"`, `inputmode="tel"`, `autocomplete="tel-national"`). The Stimulus controller syncs visible → hidden on every keystroke. Server receives E.164 directly. Browser autofill targets the visible input; Stimulus propagates to hidden.

- **D2 — Country data lives in a static JS map inside the controller**: `COUNTRIES` array with `{code, dialCode, flag, mask, example, trunkPrefix}`. ~15 entries + "other". Sorted by `dialCode.length` **descending** so that country detection from E.164 matches longest dial codes first (`+1868` before `+1`). No server-side country list needed — the FormType only validates the E.164 output, not which country was selected.

- **D3 — Mask format uses placeholder digits**: e.g. FR mask `"## ## ## ## ##"` where `#` = digit. The controller replaces `#` with user input and ignores non-digits. When the user selects a new country, the **input is cleared** and the new country's placeholder example appears *(F7)*. Rationale: a FR number cannot become a US number — clearing is the only honest UX. No confirmation modal, the focus stays in the input.

- **D4 — E.164 assembly with trunk prefix stripping** *(revised after Party Mode F1)*: `hidden.value = dialCode + strippedDigits` where `strippedDigits` = raw digits with the country's `trunkPrefix` removed if present at the start. Example: country FR (`dialCode: '+33'`, `trunkPrefix: '0'`), input `06 12 34 56 78` → raw digits `0612345678` → strip trunk `0` → `612345678` → hidden value `+33612345678`. Countries without a trunk prefix (US, Canada) strip nothing. This produces real E.164 without libphonenumber — 6 lines of JS. Assembly logic:
  ```js
  let digits = rawInput.replace(/\D/g, '');
  if (country.trunkPrefix && digits.startsWith(country.trunkPrefix)) {
    digits = digits.slice(country.trunkPrefix.length);
  }
  hidden.value = country.dialCode + digits;
  ```

- **D5 — "Autre" country fallback** *(detailed after F6 review)*: when "OTHER" is selected in the dropdown, the country trigger button is **replaced by a small `<input type="text">` (max 5 chars, placeholder `+XX`)** inside the `.ki-phone-country` container. This input is NOT a form field — it has no `name` attribute, it's purely visual. The Stimulus controller reads its value as the dial code. The mask is disabled (free typing in the main input). E.164 assembly: `hidden.value = dialCodeInput.value + digits`. **Validation**: the controller enforces that the dial code starts with `+` and contains only digits after it (strip on keyup). If the user submits an invalid combo, the PhoneNumber VO Callback (D6) catches it server-side with a French error message. When switching FROM "Autre" back to a named country, the free-text input is removed and the normal trigger button is restored.

- **D6 — Server validation via PhoneType**: the `PhoneType::configureOptions()` adds a `Callback` constraint that runs `PhoneNumber::fromString($value)` in a try/catch and converts the exception to a form violation with a French message. This means validation is always consistent with the VO.

- **D7 — Block name `phone_widget`**: Symfony form theme resolves block names from the FormType class name. `PhoneType` → `phone_widget`. The form theme block renders the `phone.html.twig` component.

- **D8 — flag-icons loaded as CSS `<link>`**: added in `templates/base.html.twig` `<head>` as a CDN stylesheet, not via importmap (importmap is for JS modules). CSS-only, ~30-40KB gzipped (full 260+ flags — not tree-shakeable). Acceptable for v1 (F9 accepted).

- **D9 — Form theme block delegates to Twig include** *(revised after Party Mode F2)*: the `phone_widget` block in `kiveto_form_theme.html.twig` does `{% include 'components/ui/phone.html.twig' %}` passing the form context variables (`id`, `full_name`, `value`, `required`, `disabled`, `valid`, `attr`). The component partial contains the full compound markup. This avoids bloating the form theme (already ~180 lines) with another ~60 lines of inline markup.

- **D10 — Paste handling with auto-detection** *(Party Mode F3)*: the Stimulus controller listens on the `paste` event. On paste: strip spaces, dots, dashes, parentheses. If the cleaned value starts with `+`, iterate `COUNTRIES` sorted by `dialCode.length` descending, find the first matching dial code, set country selector + format the remaining digits into the mask. If it starts with `00`, strip `00` and treat as `+`. If it starts with `0` (or other digit), keep current country and format. This handles the 3 common paste formats: `+33 6 12 34 56 78`, `0033612345678`, `06 12 34 56 78`.

- **D11 — Hydration from hidden value on connect()** *(Party Mode F4, refined F9)*: when the Stimulus controller's `connect()` fires (initial render or 422 re-render), it reads the hidden input's value. If non-empty and starts with `+`, it runs the same country-detection algorithm as paste (longest `dialCode` match first), sets the country selector, then **re-adds the country's `trunkPrefix`** to the national number before formatting into the mask. Example: hidden `+33612345678` → match FR → national digits `612345678` → prepend trunk `0` → `0612345678` → format with mask → display `06 12 34 56 78`. Countries without trunk prefix (US) skip the prepend step. This ensures the visible input always shows the locally-familiar format.

- **D12 — Visible input attributes for mobile + autofill** *(Party Mode F5)*: the visible `<input>` carries `type="tel"`, `inputmode="tel"` (numeric keypad on mobile), and `autocomplete="tel-national"` (browser autofill targets the visible input, Stimulus propagates to hidden). The hidden `<input>` has `autocomplete="off"` to prevent double-fill.

- **D13 — Country match algorithm: longest dial code first** *(Party Mode, user precision)*: the `COUNTRIES` array is sorted by `dialCode.length` descending at definition time. Country detection (paste, hydration) iterates this sorted array and returns the first match.

- **D14 — Responsive country selector** *(Party Mode F8)*: below 640px, the country selector shows only the flag + chevron (~36px wide). The dial code text (`+33`) is hidden via `@media (max-width: 639px)` on a `.ki-phone-dial-text` class. Above 640px, flag + dial code visible (~60px). 3 lines of CSS.

- **D15 — phone.html.twig works standalone and in form theme** *(Party Mode F10)*: every variable in the partial uses `|default('')` so it renders both when included by the form theme block (variables injected by Symfony) and when included manually in a non-form context (variables passed explicitly like `select.html.twig`). Pattern: `{% set _id = id|default('phone-' ~ (name|default('phone'))) %}`.

- **D16 — Known limitation: semantic E.164 validity without libphonenumber**: the PhoneNumber VO validates syntax (`/^\+[1-9]\d{6,14}$/`) but cannot validate semantics (e.g., `+33012345678` with un-stripped trunk would pass the regex). The JS-side trunk stripping (D4) is the primary defense. Accepted residual risk, not a bug. A future libphonenumber integration would close this gap.

## Implementation Plan

### Tasks

Ordered by dependency — each phase must be complete before the next starts.

#### Phase 1 — PhoneNumber VO E.164 strict (~30min)

- [ ] **Task 1.1: Tighten PhoneNumber VO to E.164 strict**
  - File: `src/Shared/Domain/ValueObject/PhoneNumber.php`
  - Action: Replace the regex `/^[+]?[0-9]{6,20}$/` with `/^\+[1-9]\d{6,14}$/` (E.164: mandatory `+`, first digit 1-9, then 6-14 more digits, total 7-15 digits after `+`). Update error messages to reflect E.164 requirement.
  - Notes: DB is reset on every merge — no migration or backwards compat needed.

- [ ] **Task 1.2: Update PhoneNumberTest for E.164 strict**
  - File: `tests/Unit/Shared/Domain/ValueObject/PhoneNumberTest.php`
  - Action: Rewrite the 15 existing tests. Key changes:
    - `'0123456789'` (local format) must now **reject** (no `+` prefix)
    - `'+33123456789'` must still **accept**
    - `'+33 1 23 45 67 89'` must still **accept** (whitespace stripped then validated)
    - `'+0123456789'` must **reject** (first digit after `+` cannot be 0)
    - `'+33612345678'` (9 digits after `+33`, total 11 after `+`) must **accept**
    - Minimum: `'+123456'` (7 digits after `+`) must **accept**
    - Maximum: `'+123456789012345'` (15 digits after `+`) must **accept**
    - `'+1234567890123456'` (16 digits) must **reject**

- [ ] **Task 1.3: Guard all existing PhoneNumber::fromString() call sites (F1/F2 fix)**
  - Files:
    - `src/Presentation/Clinic/Controller/Client/Profile/UpdateClientController.php` — reads phone values from raw `$request` without FormType. After E.164 strict, local-format input (`0612345678`) will 500. **Fix**: wrap the `PhoneNumber::fromString()` path (inside `ReplaceClientContactMethodsHandler`) with a try/catch in the controller, same pattern as `CreateClientController`'s safety net. Add a `FormError`-equivalent flash or throw a user-friendly error. Alternatively, add `PhoneNumber::tryFromString($value): ?self` static method that returns null instead of throwing — the handler can then reject gracefully.
    - `src/Context/Client/Infrastructure/Persistence/Doctrine/Mapper/ClientMapper.php` — calls `PhoneNumber::fromString($entity->getValue())` when hydrating from DB. **Verify**: since DB is reset on every merge, no local-format data exists. Add a **defensive note in the spec Notes section** that this path will 500 if the DB is NOT reset. No code change needed if DB-reset guarantee holds.
    - `src/Context/Client/Application/Command/ReplaceClientContactMethods/ReplaceClientContactMethodsHandler.php` — calls `PhoneNumber::fromString()`. Same try/catch concern as UpdateClientController. **Fix**: the handler should throw a domain exception (not `\InvalidArgumentException`) if the phone is invalid — the controller catches domain exceptions. Verify this is already the case or add a guard.
  - Notes: This task ensures the VO change doesn't create silent regressions on paths NOT migrated to PhoneType yet. The goal is defense-in-depth, not migration of UpdateClientController to PhoneType (that's a separate spec).

#### Phase 2 — PhoneType FormType (~45min)

- [ ] **Task 2.1: Create PhoneType FormType**
  - File: `src/Shared/Presentation/Form/Type/PhoneType.php`
  - Action: Create `PhoneType extends AbstractType`. The type is NOT compound (no children) — it submits a single E.164 string value. Override `getParent()` to return `TextType::class` *(F7 fix — ensures non-compound rendering and correct block name resolution)*. `buildForm()` is empty (no sub-fields). `configureOptions()` sets:
    - `'compound' => false` — single scalar value
    - `'constraints'` → `[new Callback([self::class, 'validatePhone'])]`
    - `'empty_data' => ''`
  - Static method `validatePhone(mixed $value, ExecutionContextInterface $context)`: if value is non-empty string, try `PhoneNumber::fromString($value)`, catch `\InvalidArgumentException` → `$context->buildViolation('Le numéro de téléphone est invalide.')->addViolation()`.
  - The block name `phone_widget` is derived automatically from the class name by Symfony. `getParent() → TextType` ensures the rendering falls through to `form_widget_simple` when no `phone_widget` block is found, but since we add `phone_widget` in the form theme, it takes precedence.

- [ ] **Task 2.2: Create PhoneTypeTest**
  - File: `tests/Unit/Shared/Presentation/Form/Type/PhoneTypeTest.php`
  - Action: Extend `TypeTestCase` with `ValidatorExtension`. Cases:
    1. Submit `'+33612345678'` → valid, data = `'+33612345678'`
    2. Submit `''` with `required: false` → valid, data = `''`
    3. Submit `'not-a-phone'` → invalid, French error
    4. Submit `'0612345678'` (no `+`) → invalid (E.164 strict)
    5. Submit `'+33 6 12 34 56 78'` → valid (VO strips whitespace)

#### Phase 3 — Flag-icons CDN + CSS (~30min)

- [ ] **Task 3.1: Add flag-icons CDN stylesheet**
  - File: `templates/base.html.twig`
  - Action: In the `<head>` block, add `<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css">` before the app stylesheets. This provides `.fi` + `.fi-xx` classes for all country flags.

- [ ] **Task 3.2: Add ki-phone CSS styles**
  - File: `assets/styles/components/forms.css`
  - Action: Add at the end of the `@layer components` block, after the ki-select section:
    ```css
    /* ── PHONE INPUT (ki-phone Stimulus controller) ── */
    .ki-phone { @apply relative flex items-center; }
    .ki-phone-country {
      @apply flex items-center gap-1 shrink-0 cursor-pointer bg-surface-subtle
             border border-border-medium border-r-0 rounded-l-md px-2;
      height: 36px;
    }
    .ki-phone-country:hover { @apply bg-surface-card border-border-strong; }
    .ki-phone-flag { width: 20px; height: 15px; border-radius: 2px; }
    .ki-phone-dial-text { @apply text-md text-text-secondary; }
    .ki-phone-chevron { @apply text-text-subtle shrink-0; width: 10px; }
    .ki-phone-input {
      @apply flex-1 text-base font-sans bg-surface-subtle border border-border-medium
             rounded-r-md px-3 outline-none text-text-primary;
      height: 36px; border-left: none;
      transition: var(--t-normal);
    }
    .ki-phone-input:focus { @apply border-brand-400 bg-surface-card shadow-focus; }
    .ki-phone-input.is-error { @apply border-danger-600 bg-danger-bg; }
    .ki-phone:focus-within .ki-phone-country { @apply border-brand-400; }
    .ki-phone-dropdown {
      @apply absolute left-0 right-0 z-popover mt-1 bg-surface-card
             border border-border-light overflow-hidden;
      top: 100%; border-radius: 6px;
      box-shadow: 0 4px 6px -1px rgba(0,0,0,.05);
    }
    .ki-phone-dropdown.hidden { display: none; }
    .ki-phone-options { @apply overflow-y-auto; max-height: 240px; padding: var(--space-1) 0; }
    .ki-phone-option {
      @apply flex items-center gap-2 text-md cursor-pointer rounded-sm bg-transparent
             font-sans text-left text-text-secondary;
      padding: var(--space-2) var(--space-3); min-height: 44px;
      margin: 1px var(--space-1); width: calc(100% - var(--space-2));
    }
    .ki-phone-option:hover, .ki-phone-option.is-focused {
      @apply bg-surface-subtle text-text-primary;
    }
    .ki-phone-option-dial { @apply text-text-muted; min-width: 40px; }
    @media (max-width: 639px) { .ki-phone-dial-text { display: none; } }
    ```

#### Phase 4 — Twig partial + form theme integration (~45min)

- [ ] **Task 4.1: Create phone.html.twig partial**
  - File: `templates/components/ui/phone.html.twig`
  - Action: Create the compound markup. The partial receives variables via the form theme scope or explicit `include`. Uses `|default()` for standalone compatibility (D16). Structure:
    - `<div class="ki-phone" data-controller="phone-input" data-phone-input-default-country-value="FR">`
    - Hidden input: `<input type="hidden" name="{{ _name }}" value="{{ _value }}" id="{{ _id }}" data-phone-input-target="hidden" autocomplete="off">`
    - Country trigger: `<button type="button" class="ki-phone-country" data-phone-input-target="countryTrigger" data-action="click->phone-input#toggleDropdown">` with flag span + dial text + chevron SVG
    - Visible input: `<input type="tel" inputmode="tel" autocomplete="tel-national" class="ki-phone-input" data-phone-input-target="input" data-action="input->phone-input#onInput keydown->phone-input#onKeydown paste->phone-input#onPaste" placeholder="06 12 34 56 78">`
    - Dropdown: `<div class="ki-phone-dropdown hidden" data-phone-input-target="dropdown">` with country options list (generated by Stimulus from JS COUNTRIES data, not hardcoded in Twig)
  - Notes: The dropdown content is rendered by JS on connect() from the COUNTRIES array — the Twig partial only provides the empty container `<div>`.

- [ ] **Task 4.2: Add phone_widget block to form theme**
  - File: `templates/form/kiveto_form_theme.html.twig`
  - Action: Add before the `{# ── TEXTAREA ── #}` section:
    ```twig
    {# ── PHONE INPUT ── #}
    {% block phone_widget %}
      {% include 'components/ui/phone.html.twig' with {
        name: full_name,
        id: id,
        value: value|default(''),
        required: required|default(false),
        disabled: disabled|default(false),
        valid: valid|default(true),
        attr: attr|default({}),
      } only %}
    {% endblock %}
    ```
  - Notes: The `only` keyword is intentional — it prevents form-theme internal variables from leaking into the partial. All needed variables are passed explicitly (F15 fix). The `attr` hash is included so the partial can apply custom HTML attributes (e.g., `data-*`, `class`) passed via `->add('phone', PhoneType::class, ['attr' => [...]])`.

#### Phase 5 — Stimulus controller (~2h)

- [ ] **Task 5.1: Create phone_input_controller.js**
  - File: `assets/controllers/phone_input_controller.js`
  - Action: Create the Stimulus controller. Key elements:
    - **Static targets**: `hidden`, `input`, `countryTrigger`, `dropdown`, `flagIcon`, `dialText`
    - **Static values**: `defaultCountry: { type: String, default: 'FR' }`
    - **COUNTRIES constant**: sorted by `dialCode.length` descending. 15 entries:
      ```js
      { code: 'FR', dialCode: '+33',  trunkPrefix: '0', mask: '## ## ## ## ##',     example: '06 12 34 56 78', name: 'France' },
      { code: 'BE', dialCode: '+32',  trunkPrefix: '0', mask: '### ## ## ##',       example: '047 12 34 56',   name: 'Belgique' },
      { code: 'CH', dialCode: '+41',  trunkPrefix: '0', mask: '## ### ## ##',       example: '07 123 45 67',   name: 'Suisse' },
      { code: 'LU', dialCode: '+352', trunkPrefix: '',  mask: '### ### ###',        example: '621 123 456',    name: 'Luxembourg' },
      { code: 'MA', dialCode: '+212', trunkPrefix: '0', mask: '## ## ## ## ##',     example: '06 12 34 56 78', name: 'Maroc' },
      { code: 'TN', dialCode: '+216', trunkPrefix: '',  mask: '## ### ###',         example: '20 123 456',     name: 'Tunisie' },
      { code: 'DZ', dialCode: '+213', trunkPrefix: '0', mask: '### ## ## ##',       example: '055 12 34 56',   name: 'Algérie' },
      { code: 'ES', dialCode: '+34',  trunkPrefix: '',  mask: '### ## ## ##',       example: '612 34 56 78',   name: 'Espagne' },
      { code: 'IT', dialCode: '+39',  trunkPrefix: '',  mask: '### ### ####',       example: '312 345 6789',   name: 'Italie' },
      { code: 'DE', dialCode: '+49',  trunkPrefix: '0', mask: '### #######',        example: '015 1234567',    name: 'Allemagne' },
      { code: 'GB', dialCode: '+44',  trunkPrefix: '0', mask: '#### ### ####',      example: '07911 123 456',  name: 'Royaume-Uni' },
      { code: 'US', dialCode: '+1',   trunkPrefix: '',  mask: '(###) ###-####',     example: '(201) 555-0123', name: 'États-Unis' },
      { code: 'CA', dialCode: '+1',   trunkPrefix: '',  mask: '(###) ###-####',     example: '(514) 555-0123', name: 'Canada' },
      { code: 'OTHER', dialCode: '',  trunkPrefix: '',  mask: '',                   example: '',                name: 'Autre' },
      ```
    - **connect()**: render dropdown options from COUNTRIES, set initial country to `defaultCountryValue`, hydrate from hidden value if non-empty (D11 algorithm)
    - **onInput(event)**: extract digits only from visible input, apply mask, update hidden with E.164 (D4 trunk stripping)
    - **onPaste(event)**: intercept paste, clean value (strip dots/dashes/parens/spaces), detect country from pasted number (D10 algorithm), set country + format into mask
    - **onKeydown(event)**: when dropdown is open, ArrowUp/Down navigate options, Enter selects, Escape closes. Standard keyboard nav.
    - **toggleDropdown()**: show/hide country dropdown
    - **selectCountry(code)**: update flag, dial text, clear input, update mask + placeholder (D3), close dropdown, focus input
    - **_detectCountry(e164)**: iterate COUNTRIES sorted by dialCode length desc (D13), return first match or default
    - **_hydrateFromHidden()**: D11 — parse hidden E.164 → detect country → re-add trunkPrefix → format with mask → update visible
    - **_applyMask(digits, mask)**: replace `#` chars in mask with digits, return formatted string
    - **_assembleE164(rawInput, country)**: D4 — strip trunkPrefix, prepend dialCode
    - **"Autre" mode** (D5): when "OTHER" selected, replace country trigger with a small text input for dial code, disable masking, assemble E.164 from free-form dial code + digits

- [ ] **Task 5.2: Manual smoke test of the Stimulus controller**
  - Action: After Task 5.1, open the client creation modal in the browser and verify:
    1. Default country is FR with flag + `+33`
    2. Typing `0612345678` → displays `06 12 34 56 78`, hidden = `+33612345678`
    3. Switch to US → input cleared, placeholder shows `(201) 555-0123`
    4. Paste `+44 7911 123456` → auto-detects GB, displays `07911 123 456`
    5. Paste `0033612345678` → detects FR, displays `06 12 34 56 78`
    6. Submit empty (required: false) → no error
    7. Submit invalid → French error message

#### Phase 6 — Wire into ClientFormType (~20min)

- [ ] **Task 6.1: Replace TelType with PhoneType in ClientFormType**
  - File: `src/Presentation/Clinic/Form/Client/ClientFormType.php`
  - Action: Replace `->add('phone', TelType::class, [...Regex constraint...])` with `->add('phone', PhoneType::class, ['required' => false, 'empty_data' => ''])`. Remove the `TelType` and `Regex` imports. The PhoneType handles its own validation (D6). The explicit `'empty_data' => ''` ensures the `validateAtLeastOneContactMethod` Callback sees `''` (not `null`) for empty phone submissions — preserving the existing `'' === $phone` check (F14 fix).

- [ ] **Task 6.2: Update CreateClientController if needed**
  - File: `src/Presentation/Clinic/Controller/Client/Profile/CreateClientController.php`
  - Action: Verify that `$data['phone']` (now E.164 from PhoneType) passes through to `ContactMethodDto` without modification. The existing code `ContactMethodDto(type: 'phone', label: 'mobile', value: $phone)` should work as-is since `$phone` is already `+33612345678`. Remove the `Regex` import if present. Verify the `try/catch` safety net on `$this->commandBus->dispatch()` still works as a last-resort fallback.

- [ ] **Task 6.3: Update ClientFormTypeTest**
  - File: `tests/Unit/Presentation/Clinic/Form/Client/ClientFormTypeTest.php`
  - Action: Update phone-related test assertions. The old tests submitted raw strings like `'+33612345678'` which coincidentally is valid E.164. The key changes are:
    - `testValidSubmission`: phone value stays `'+33612345678'` — already E.164, no change needed.
    - `testPhoneAloneIsValid`: phone value stays `'+33612345678'` — already E.164, no change needed.
    - `testEmptyPhoneAndEmailTriggersCallback`: still submits `''` for both — unchanged. **Verify** (F14 fix): that PhoneType returns `''` (not `null`) for empty submission, so the Callback still sees `'' === $phone`.
    - Add new case: `testInvalidPhoneTriggersPhoneTypeValidation`: submit phone as `'0612345678'` (local format, no `+`) → form invalid. Verify the error message does NOT reference local format as a valid example (F13 fix — the old Regex message `"ex : +33 6 12 34 56 78 ou 0612345678"` is gone, replaced by PhoneType's `"Le numéro de téléphone est invalide."`).
    - Add new case: `testPhoneWithLettersTriggersValidation`: submit phone as `'abc'` → form invalid with French error.

#### Phase 7 — CI + validation (~15min)

- [ ] **Task 7.1: Run `make ci`**
  - Action: php-cs-fixer dry-run, phpcs, phpstan max, tailwind-build, phpunit. Fix any failures.

- [ ] **Task 7.2: Run `make assets`**
  - Action: Rebuild Tailwind + asset-map after CSS + JS changes.

- [ ] **Task 7.3: Manual smoke test (full flow)**
  - Steps:
    1. Open clinic.kiveto.local/clients, click "Nouveau client"
    2. Verify phone field shows FR flag + `+33` + masked input
    3. Type `0612345678` → formatted as `06 12 34 56 78`
    4. Submit with valid data → 303 redirect, client appears in list
    5. Verify in DB: `client__contact_methods.value` = `+33612345678`
    6. Change country to US → input clears, placeholder `(201) 555-0123`
    7. Paste `+44 7911 123456` → auto-detect UK
    8. Submit with invalid phone → French error in modal, modal stays open
    9. Resize to mobile (<640px) → dial code text hidden, only flag visible

### Acceptance Criteria

- **AC-Phone-FR-Default** — Given the phone input component, when it renders with no initial value, then the country selector shows the French flag (🇫🇷) with `+33`, and the input placeholder shows `06 12 34 56 78`.

- **AC-Phone-Mask-FR** — Given the country is FR, when the user types `0612345678`, then the visible input displays `06 12 34 56 78` and the hidden input contains `+33612345678` (trunk `0` stripped per D4).

- **AC-Phone-Mask-US** — Given the country is US, when the user types `2015550123`, then the visible input displays `(201) 555-0123` and the hidden input contains `+12015550123`.

- **AC-Phone-Country-Switch** — Given the user has typed a FR number, when they switch country to US, then the visible input is cleared and the placeholder updates to the US example.

- **AC-Phone-Paste-International** — Given the user pastes `+44 7911 123456`, then the controller auto-detects GB, sets the country selector to 🇬🇧 `+44`, and displays `07911 123 456` in the visible input with hidden value `+447911123456`.

- **AC-Phone-Paste-DoubleZero** — Given the user pastes `0033612345678`, then the controller detects FR (strips `00`, matches `+33`), displays `06 12 34 56 78`, hidden = `+33612345678`.

- **AC-Phone-Paste-Local** — Given the country is FR and the user pastes `06 12 34 56 78`, then the controller keeps FR, formats the number, hidden = `+33612345678`.

- **AC-Phone-Hydrate-422** — Given the hidden input has value `+33612345678` on a 422 re-render, when the Stimulus controller connects, then it detects FR, displays `06 12 34 56 78` in the visible input, and shows the FR flag.

- **AC-Phone-Server-Valid** — Given a form submission with `phone = '+33612345678'`, when PhoneType validates, then no error is raised and the value passes through to the VO.

- **AC-Phone-Server-Invalid** — Given a form submission with `phone = 'abc'`, when PhoneType validates, then a form violation `"Le numéro de téléphone est invalide."` is raised.

- **AC-Phone-Server-LocalReject** — Given a form submission with `phone = '0612345678'` (no `+` prefix), when PhoneNumber VO validates, then it rejects with an error (E.164 strict).

- **AC-Phone-Empty-Optional** — Given `required: false`, when the form submits with an empty phone field, then no validation error occurs.

- **AC-Phone-Other-Country** — Given the user selects "Autre", when they type dial code `+7` and number `9161234567`, then the hidden value is `+79161234567` and the mask is disabled (free typing).

- **AC-Phone-Responsive** — Given a viewport < 640px, when the phone component renders, then only the flag + chevron are visible in the country selector (dial code text is hidden).

- **AC-Phone-Mobile-Keyboard** — Given a mobile device, when the user taps the visible input, then the numeric telephone keypad appears (via `inputmode="tel"`).

- **AC-VO-E164-Strict** — Given the updated PhoneNumber VO, when `fromString('+33612345678')` is called, then it succeeds; when `fromString('0612345678')` is called, then it throws `InvalidArgumentException`.

- **AC-CI-Green** — `make ci` passes all stages with the new code.

## Additional Context

### Dependencies

- **flag-icons CDN** (`https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css`) — external CSS-only dependency. Pinned to v7.2.3. No JS, no build step. ~30-40KB gzipped (full set, not tree-shakeable).
- **No new PHP dependencies** — PhoneType uses only Symfony's built-in Form + Validator components.
- **PhoneNumber VO** — the spec modifies the VO regex. All consumers (`CreateClientHandler`, `ReplaceClientContactMethodsHandler`) must pass E.164 format. Since the PhoneType produces E.164 and the DB is reset on merge, no migration is needed.

### Testing Strategy

- **Unit tests — PhoneNumber VO** (`PhoneNumberTest.php`): 15 cases rewritten for E.164 strict. Tests both acceptance (valid E.164) and rejection (local format, too short, too long, letters, missing `+`).
- **Unit tests — PhoneType FormType** (`PhoneTypeTest.php`): 5 cases. Valid E.164, empty optional, invalid string, local format rejection, whitespace tolerance.
- **Unit tests — ClientFormType** (`ClientFormTypeTest.php`): update existing phone-related assertions to submit E.164 values. Add 1 new case for PhoneType validation error.
- **Manual testing — Stimulus controller**: no automated JS test framework in the project. Smoke test covers: mask formatting, country switch, paste handling, hydration, responsive, mobile keyboard. Documented in Task 5.2 and Task 7.3.
- **Total**: ~22 unit test cases (15 VO + 5 PhoneType + 2 ClientFormType updates) + 2 manual smoke sessions.

### Notes

- **JS required — no graceful degradation (F4, accepted)**: the phone component requires JavaScript. Without JS, the country selector and mask don't render, and the hidden field stays empty. This is an accepted constraint — the target audience (veterinary clinics on iPad/desktop) always has JS enabled. The current `<input type="tel">` fallback is worse (no validation, no E.164), so the tradeoff is net positive.
- **flag-icons CDN size (F9, accepted)**: the full `flag-icons.min.css` is ~30-40KB gzipped (all 260+ flags), not ~5KB. Loaded on every page via `base.html.twig`. Acceptable for v1 — optimize later by self-hosting a subset if performance metrics flag it.
- **Mask length limitations for variable-length national numbers (F10/F12/F24, accepted)**: GB, IT, DE have variable-length phone numbers (mobile vs landline vs area codes). A single mask per country cannot accommodate all formats. Excess digits beyond the mask are silently accepted (the mask formatter stops applying formatting but keeps the digits). The hidden E.164 value is always correct regardless of display formatting. Documented as known limitation — a future libphonenumber integration would enable format-aware masking.
- **Known limitation (D16)**: without libphonenumber, `+33012345678` (trunk prefix not stripped) would pass the E.164 regex. The JS trunk stripping is the primary defense. This is a residual risk accepted for the sake of zero external dependencies. The limitation is invisible to the user as long as the JS controller works correctly.
- **Future: 240+ countries**: the `COUNTRIES` array is designed to be extended. Adding a country requires one JS object with `{code, dialCode, trunkPrefix, mask, example, name}`. No server-side or CSS changes needed (flag-icons already covers all ISO 3166-1 codes).
- **Future: other FormTypes**: the PhoneType is self-contained and ready for use in `AnimalFormType` (auxiliary contact phone), RDV forms, etc. Just `->add('phone', PhoneType::class)`.
- **flag-icons CDN pinning**: the URL is pinned to `@7.2.3` to prevent breaking changes. Update the version explicitly when upgrading.
- **US/CA dial code conflict (F3 fix)**: both share `+1`. The COUNTRIES array lists US **before** CA at the same `dialCode.length` — array iteration order is stable in JS, so US is always the first `+1` match. Acceptable — the user picks the right flag manually when entering a CA number. Auto-detection from paste cannot distinguish US from CA. NANP Caribbean codes (+1868, +1876, etc.) are not in the 15-country list and will match US by default — acceptable for v1 (the number is still valid E.164).
