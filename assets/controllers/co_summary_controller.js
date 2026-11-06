import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — B3–B6 « Récapitulatif », as the wizard is filled in:
 * the clinic's name and tenant, the company, the people to invite and the
 * billable veterinarians, the features, the monthly price. What the
 * current step does not show keeps the value the server printed.
 */
export default class extends Controller {
  static targets = ['name', 'slug', 'company', 'invitations', 'features', 'vets', 'monthly'];
  static values = { price: Number, vets: Number, plan: String };

  connect() {
    this.refresh();
  }

  refresh() {
    const form = this.element.querySelector('form');
    if (!form) {
      return;
    }

    const name = form.elements.namedItem('name');
    if (name instanceof HTMLInputElement) {
      this.nameTarget.textContent = name.value.trim() || '—';
    }
    const slug = form.elements.namedItem('slug');
    if (slug instanceof HTMLInputElement) {
      this.slugTarget.textContent = slug.value.trim() || '—';
    }
    const company = form.elements.namedItem('company_name');
    if (company instanceof HTMLInputElement) {
      const legal = company.value.trim() || '—';
      this.companyTarget.textContent = legal.length > 24 ? `${legal.slice(0, 22)}…` : legal;
      this.companyTarget.title = legal;
    }

    let vets = this.vetsValue;
    const rows = [...form.querySelectorAll('[name$="[email]"]')].map((email) => email.closest('tr'));
    if (rows.length > 0) {
      const people = rows.filter((row) => [...row.querySelectorAll('input')].some((input) => input.value.trim() !== ''));
      vets = people.filter((row) => row.querySelector('[name$="[role]"]')?.value === 'VETERINARY').length;
      this.invitationsTarget.textContent = people.length > 0 ? `${people.length} personne${people.length > 1 ? 's' : ''}` : '—';
    }

    const features = form.querySelectorAll('[name="features[]"]');
    if (features.length > 0) {
      const on = [...features].filter((box) => box.checked).length;
      this.featuresTarget.textContent = on > 0 ? String(on) : '—';
    }

    const price = this.euros(this.priceValue);
    this.vetsTarget.textContent = vets > 0 ? `${vets} × ${price} HT` : '—';

    const plan = form.querySelector('[name="plan"]:checked')?.value ?? this.planValue;
    if (vets === 0) {
      this.monthlyTarget.textContent = '—';
    } else if (plan === 'pilot' && form.querySelector('[name="plan"]')) {
      this.monthlyTarget.textContent = '0 € (pilote)';
    } else {
      this.monthlyTarget.textContent = `${this.euros(vets * this.priceValue)} HT`;
    }
  }

  // « 396 € », « 99,50 € », as ConsoleFormat::euros prints them.
  euros(cents) {
    const units = Math.trunc(cents / 100).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const rest = cents % 100;
    return `${units}${rest === 0 ? '' : `,${String(rest).padStart(2, '0')}`} €`;
  }
}
