/**
 * Page module — Scheduling Agenda
 * Loaded by app.js dispatcher on turbo:load.
 */

// =============== DATA ===============
const VETS = {
  rousseau: { name:'Dr. Rousseau', color:'#4338ca', bg:'#eef2ff', light:'#f5f3ff' },
  martin:   { name:'Dr. Martin',   color:'#0891b2', bg:'#ecfeff', light:'#f0fdfe' },
  dupont:   { name:'Dr. Dupont',   color:'#059669', bg:'#ecfdf5', light:'#f0fdf4' },
  lambert:  { name:'Dr. Lambert',  color:'#db2777', bg:'#fdf2f8', light:'#fce7f3' },
};

const ANIMALS = [
  { id:1,  name:'Luna',    species:'Félin', breed:'Persan',           owner:'Mme Dubois',   phone:'+33 6 12 34 56 78', emoji:'🐱' },
  { id:2,  name:'Max',     species:'Canin', breed:'Labrador',         owner:'M. Bernard',   phone:'+33 6 98 76 54 32', emoji:'🐶' },
  { id:3,  name:'Milo',    species:'Félin', breed:'Européen',         owner:'Mme Petit',    phone:'+33 6 11 22 33 44', emoji:'🐱' },
  { id:4,  name:'Rex',     species:'Canin', breed:'Berger Allemand',  owner:'M. Dupuis',    phone:'+33 6 55 66 77 88', emoji:'🐶' },
  { id:5,  name:'Nala',    species:'Félin', breed:'Maine Coon',       owner:'Mme Leclerc',  phone:'+33 6 44 33 22 11', emoji:'🐱' },
  { id:6,  name:'Oscar',   species:'Canin', breed:'Golden Retriever', owner:'M. Moreau',    phone:'+33 6 77 88 99 00', emoji:'🐶' },
  { id:7,  name:'Bella',   species:'Canin', breed:'Beagle',           owner:'Mme Girard',   phone:'+33 6 22 11 44 55', emoji:'🐶' },
  { id:8,  name:'Simba',   species:'Félin', breed:'Siamois',          owner:'M. Lefebvre',  phone:'+33 6 33 44 55 66', emoji:'🐱' },
  { id:9,  name:'Rocky',   species:'Canin', breed:'Boxer',            owner:'M. Fontaine',  phone:'+33 6 55 44 33 22', emoji:'🐶' },
  { id:10, name:'Lily',    species:'Félin', breed:'Ragdoll',          owner:'Mme Chevalier',phone:'+33 6 66 55 44 33', emoji:'🐱' },
  { id:11, name:'Bruno',   species:'Canin', breed:'Rottweiler',       owner:'M. Garnier',   phone:'+33 6 77 66 55 44', emoji:'🐶' },
  { id:12, name:'Coco',    species:'Félin', breed:'British Shorthair',owner:'Mme Roux',     phone:'+33 6 88 77 66 55', emoji:'🐱' },
];

const MOTIF_COLORS = {
  'Consultation': '#4338ca',
  'Vaccin':       '#0891b2',
  'Suivi':        '#7c3aed',
  'Urgence':      '#dc2626',
  'Chirurgie':    '#b45309',
  'Bilan':        '#059669',
  'RDV en ligne': '#16a34a',
};

// Appointments data
let appointments = [
  // Monday 16 — past
  { id:1,  animalId:1, date:'2026-03-16', start:'08:30', durationMin:30,  vet:'rousseau', motif:'Vaccination FeLV', note:'Rappel annuel', source:'cabinet' },
  { id:2,  animalId:2, date:'2026-03-16', start:'09:15', durationMin:20,  vet:'rousseau', motif:'Suivi', note:'Contrôle post-op', source:'cabinet' },
  { id:3,  animalId:4, date:'2026-03-16', start:'10:30', durationMin:60,  vet:'rousseau', motif:'Chirurgie', note:'Stérilisation', source:'cabinet' },
  { id:4,  animalId:3, date:'2026-03-16', start:'08:45', durationMin:15,  vet:'martin',   motif:'Consultation', note:'', source:'cabinet' },
  { id:5,  animalId:5, date:'2026-03-16', start:'10:00', durationMin:30,  vet:'martin',   motif:'Bilan', note:'Bilan annuel', source:'cabinet' },
  { id:6,  animalId:6, date:'2026-03-16', start:'14:00', durationMin:20,  vet:'martin',   motif:'Consultation', note:'', source:'online' },
  // Tuesday 17 — past
  { id:7,  animalId:2, date:'2026-03-17', start:'09:00', durationMin:15,  vet:'rousseau', motif:'Vaccin', note:'', source:'cabinet' },
  { id:8,  animalId:5, date:'2026-03-17', start:'09:30', durationMin:60,  vet:'rousseau', motif:'Chirurgie', note:'Ablation tumeur', source:'cabinet' },
  { id:9,  animalId:1, date:'2026-03-17', start:'11:00', durationMin:30,  vet:'rousseau', motif:'Suivi', note:'Post op J+2', source:'cabinet' },
  { id:10, animalId:4, date:'2026-03-17', start:'09:00', durationMin:30,  vet:'martin',   motif:'Consultation', note:'', source:'online' },
  { id:11, animalId:6, date:'2026-03-17', start:'14:30', durationMin:20,  vet:'martin',   motif:'Vaccination FeLV', note:'', source:'cabinet' },
  // Wednesday 18 — past
  { id:12, animalId:3, date:'2026-03-18', start:'09:00', durationMin:20,  vet:'rousseau', motif:'Urgence', note:'Boiterie soudaine', source:'cabinet' },
  { id:13, animalId:1, date:'2026-03-18', start:'10:00', durationMin:60,  vet:'rousseau', motif:'Chirurgie', note:'Stérilisation', source:'cabinet' },
  { id:14, animalId:2, date:'2026-03-18', start:'09:00', durationMin:30,  vet:'martin',   motif:'Bilan', note:'Bilan sanguin', source:'cabinet' },
  { id:15, animalId:4, date:'2026-03-18', start:'10:30', durationMin:15,  vet:'martin',   motif:'Vaccin', note:'Rage rappel', source:'cabinet' },
  { id:16, animalId:5, date:'2026-03-18', start:'14:00', durationMin:30,  vet:'martin',   motif:'Consultation', note:'', source:'online' },
  { id:17, animalId:3, date:'2026-03-18', start:'11:00', durationMin:30,  vet:'rousseau', motif:'Consultation', note:'', source:'cabinet', status:'cancelled' },
  // Thursday 19 — past
  { id:18, animalId:6, date:'2026-03-19', start:'09:00', durationMin:20,  vet:'rousseau', motif:'Suivi', note:'', source:'cabinet' },
  { id:19, animalId:2, date:'2026-03-19', start:'09:30', durationMin:60,  vet:'rousseau', motif:'Chirurgie', note:'Hernie discale', source:'cabinet' },
  { id:20, animalId:5, date:'2026-03-19', start:'11:00', durationMin:15,  vet:'rousseau', motif:'Vaccin', note:'', source:'cabinet' },
  { id:21, animalId:1, date:'2026-03-19', start:'09:00', durationMin:30,  vet:'martin',   motif:'Consultation', note:'', source:'online' },
  { id:22, animalId:3, date:'2026-03-19', start:'14:00', durationMin:30,  vet:'martin',   motif:'Bilan', note:'Résultats labo', source:'cabinet' },
  // Friday 20 — past
  { id:23, animalId:4, date:'2026-03-20', start:'09:00', durationMin:30,  vet:'rousseau', motif:'Suivi', note:'Post vaccin', source:'cabinet' },
  { id:24, animalId:6, date:'2026-03-20', start:'10:00', durationMin:20,  vet:'rousseau', motif:'Consultation', note:'', source:'cabinet' },
  { id:25, animalId:1, date:'2026-03-20', start:'10:30', durationMin:60,  vet:'martin',   motif:'Chirurgie', note:'Castration', source:'cabinet' },
  { id:26, animalId:3, date:'2026-03-20', start:'14:15', durationMin:15,  vet:'martin',   motif:'Vaccin', note:'', source:'online' },
  // Saturday 21 (today) — mix past + upcoming
  { id:27, animalId:2, date:'2026-03-21', start:'08:30', durationMin:20,  vet:'rousseau', motif:'Suivi', note:'Post op J+7', source:'cabinet' },
  { id:28, animalId:5, date:'2026-03-21', start:'09:00', durationMin:60,  vet:'rousseau', motif:'Chirurgie', note:'Laparotomie', source:'cabinet' },
  { id:29, animalId:6, date:'2026-03-21', start:'10:15', durationMin:15,  vet:'rousseau', motif:'Vaccin', note:'Rappel Rage', source:'cabinet' },
  { id:30, animalId:1, date:'2026-03-21', start:'11:00', durationMin:30,  vet:'rousseau', motif:'Consultation', note:'', source:'online' },
  { id:31, animalId:4, date:'2026-03-21', start:'09:00', durationMin:30,  vet:'martin',   motif:'Bilan', note:'', source:'cabinet' },
  { id:32, animalId:3, date:'2026-03-21', start:'14:00', durationMin:20,  vet:'martin',   motif:'Suivi', note:'', source:'cabinet' },
  { id:33, animalId:5, date:'2026-03-21', start:'14:30', durationMin:60,  vet:'martin',   motif:'Chirurgie', note:'Stérilisation', source:'cabinet' },
  // Sunday 22 — upcoming
  { id:34, animalId:1, date:'2026-03-22', start:'10:00', durationMin:30,  vet:'rousseau', motif:'Consultation', note:'Permanence', source:'cabinet' },
  { id:35, animalId:6, date:'2026-03-22', start:'11:00', durationMin:20,  vet:'rousseau', motif:'Urgence', note:'Douleur abdominale', source:'online' },
  { id:36, animalId:2, date:'2026-03-22', start:'10:00', durationMin:60,  vet:'martin',   motif:'Chirurgie', note:'Urgence', source:'cabinet' },

  // =============== Week of March 23-29, 2026 — 4 vets ===============

  // Monday 23
  { id:100, animalId:1,  date:'2026-03-23', start:'08:30', durationMin:30,  vet:'rousseau', motif:'Consultation', note:'Bilan annuel', source:'cabinet' },
  { id:101, animalId:7,  date:'2026-03-23', start:'09:15', durationMin:20,  vet:'rousseau', motif:'Vaccin', note:'Rage rappel', source:'cabinet' },
  { id:102, animalId:3,  date:'2026-03-23', start:'10:00', durationMin:60,  vet:'rousseau', motif:'Chirurgie', note:'Castration', source:'cabinet' },
  { id:103, animalId:9,  date:'2026-03-23', start:'11:30', durationMin:30,  vet:'rousseau', motif:'Suivi', note:'Post-op J+7', source:'cabinet' },
  { id:104, animalId:11, date:'2026-03-23', start:'14:00', durationMin:20,  vet:'rousseau', motif:'Consultation', note:'', source:'online' },
  { id:105, animalId:5,  date:'2026-03-23', start:'14:30', durationMin:60,  vet:'rousseau', motif:'Chirurgie', note:'Stérilisation', source:'cabinet' },
  { id:106, animalId:2,  date:'2026-03-23', start:'08:30', durationMin:30,  vet:'martin',   motif:'Bilan', note:'Bilan sanguin', source:'cabinet' },
  { id:107, animalId:8,  date:'2026-03-23', start:'09:15', durationMin:15,  vet:'martin',   motif:'Vaccin', note:'', source:'cabinet' },
  { id:108, animalId:10, date:'2026-03-23', start:'09:45', durationMin:30,  vet:'martin',   motif:'Consultation', note:'Dermatologie', source:'online' },
  { id:109, animalId:4,  date:'2026-03-23', start:'11:00', durationMin:60,  vet:'martin',   motif:'Chirurgie', note:'Hernie ombilicale', source:'cabinet' },
  { id:110, animalId:6,  date:'2026-03-23', start:'14:15', durationMin:20,  vet:'martin',   motif:'Suivi', note:'Contrôle poids', source:'cabinet' },
  { id:111, animalId:12, date:'2026-03-23', start:'08:45', durationMin:20,  vet:'dupont',   motif:'Vaccin', note:'Primo-vaccination', source:'cabinet' },
  { id:112, animalId:1,  date:'2026-03-23', start:'09:30', durationMin:30,  vet:'dupont',   motif:'Consultation', note:'Boiterie', source:'cabinet' },
  { id:113, animalId:9,  date:'2026-03-23', start:'10:30', durationMin:20,  vet:'dupont',   motif:'Suivi', note:'', source:'online' },
  { id:114, animalId:7,  date:'2026-03-23', start:'14:00', durationMin:60,  vet:'dupont',   motif:'Chirurgie', note:'Tumeur cutanée', source:'cabinet' },
  { id:115, animalId:3,  date:'2026-03-23', start:'08:30', durationMin:15,  vet:'lambert',  motif:'Vaccin', note:'FeLV', source:'cabinet' },
  { id:116, animalId:11, date:'2026-03-23', start:'09:00', durationMin:30,  vet:'lambert',  motif:'Consultation', note:'Vomissements', source:'cabinet' },
  { id:117, animalId:5,  date:'2026-03-23', start:'10:00', durationMin:20,  vet:'lambert',  motif:'Bilan', note:'Résultats labo', source:'cabinet' },
  { id:118, animalId:2,  date:'2026-03-23', start:'11:00', durationMin:60,  vet:'lambert',  motif:'Chirurgie', note:'Ablation kyste', source:'cabinet' },
  { id:119, animalId:8,  date:'2026-03-23', start:'14:30', durationMin:30,  vet:'lambert',  motif:'Suivi', note:'Post-op J+3', source:'online' },

  // Tuesday 24
  { id:120, animalId:6,  date:'2026-03-24', start:'08:30', durationMin:20,  vet:'rousseau', motif:'Vaccin', note:'Rappel annuel', source:'cabinet' },
  { id:121, animalId:10, date:'2026-03-24', start:'09:00', durationMin:60,  vet:'rousseau', motif:'Chirurgie', note:'Laparotomie', source:'cabinet' },
  { id:122, animalId:12, date:'2026-03-24', start:'10:30', durationMin:30,  vet:'rousseau', motif:'Consultation', note:'Abattement', source:'online' },
  { id:123, animalId:4,  date:'2026-03-24', start:'11:15', durationMin:15,  vet:'rousseau', motif:'Suivi', note:'', source:'cabinet' },
  { id:124, animalId:7,  date:'2026-03-24', start:'14:00', durationMin:30,  vet:'rousseau', motif:'Bilan', note:'Urée créatinine', source:'cabinet' },
  { id:125, animalId:1,  date:'2026-03-24', start:'15:00', durationMin:20,  vet:'rousseau', motif:'Vaccin', note:'', source:'cabinet' },
  { id:126, animalId:3,  date:'2026-03-24', start:'08:45', durationMin:30,  vet:'martin',   motif:'Consultation', note:'Toux chronique', source:'cabinet' },
  { id:127, animalId:11, date:'2026-03-24', start:'09:30', durationMin:20,  vet:'martin',   motif:'Suivi', note:'Post-op J+10', source:'cabinet' },
  { id:128, animalId:5,  date:'2026-03-24', start:'10:00', durationMin:60,  vet:'martin',   motif:'Chirurgie', note:'Ovariectomie', source:'cabinet' },
  { id:129, animalId:9,  date:'2026-03-24', start:'14:00', durationMin:20,  vet:'martin',   motif:'Urgence', note:'Corps étranger', source:'online' },
  { id:130, animalId:2,  date:'2026-03-24', start:'14:30', durationMin:30,  vet:'martin',   motif:'Bilan', note:'Avant chirurgie', source:'cabinet' },
  { id:131, animalId:8,  date:'2026-03-24', start:'08:30', durationMin:30,  vet:'dupont',   motif:'Consultation', note:'Prurit intense', source:'cabinet' },
  { id:132, animalId:6,  date:'2026-03-24', start:'09:15', durationMin:20,  vet:'dupont',   motif:'Vaccin', note:'', source:'online' },
  { id:133, animalId:12, date:'2026-03-24', start:'10:30', durationMin:60,  vet:'dupont',   motif:'Chirurgie', note:'Fracture radius', source:'cabinet' },
  { id:134, animalId:4,  date:'2026-03-24', start:'14:00', durationMin:30,  vet:'dupont',   motif:'Suivi', note:'Résultats radio', source:'cabinet' },
  { id:135, animalId:10, date:'2026-03-24', start:'08:30', durationMin:20,  vet:'lambert',  motif:'Vaccin', note:'Typhus-Coryza', source:'cabinet' },
  { id:136, animalId:7,  date:'2026-03-24', start:'09:00', durationMin:60,  vet:'lambert',  motif:'Chirurgie', note:'Stérilisation', source:'cabinet' },
  { id:137, animalId:1,  date:'2026-03-24', start:'10:30', durationMin:30,  vet:'lambert',  motif:'Consultation', note:'Perte appétit', source:'online' },
  { id:138, animalId:3,  date:'2026-03-24', start:'14:15', durationMin:20,  vet:'lambert',  motif:'Suivi', note:'', source:'cabinet' },

  // Wednesday 25
  { id:139, animalId:5,  date:'2026-03-25', start:'08:30', durationMin:30,  vet:'rousseau', motif:'Bilan', note:'Bilan préopératoire', source:'cabinet' },
  { id:140, animalId:9,  date:'2026-03-25', start:'09:15', durationMin:60,  vet:'rousseau', motif:'Chirurgie', note:'Résection intestinale', source:'cabinet' },
  { id:141, animalId:11, date:'2026-03-25', start:'11:00', durationMin:20,  vet:'rousseau', motif:'Vaccin', note:'Rappel rage', source:'cabinet' },
  { id:142, animalId:6,  date:'2026-03-25', start:'14:00', durationMin:30,  vet:'rousseau', motif:'Consultation', note:'Boiterie antérieure', source:'online' },
  { id:143, animalId:2,  date:'2026-03-25', start:'15:00', durationMin:15,  vet:'rousseau', motif:'Suivi', note:'', source:'cabinet' },
  { id:144, animalId:12, date:'2026-03-25', start:'08:30', durationMin:20,  vet:'martin',   motif:'Vaccin', note:'', source:'cabinet' },
  { id:145, animalId:8,  date:'2026-03-25', start:'09:00', durationMin:30,  vet:'martin',   motif:'Consultation', note:'Diarrhée chronique', source:'cabinet' },
  { id:146, animalId:10, date:'2026-03-25', start:'10:00', durationMin:60,  vet:'martin',   motif:'Chirurgie', note:'Castration', source:'cabinet' },
  { id:147, animalId:4,  date:'2026-03-25', start:'14:00', durationMin:30,  vet:'martin',   motif:'Bilan', note:'Contrôle annuel', source:'online' },
  { id:148, animalId:1,  date:'2026-03-25', start:'14:45', durationMin:20,  vet:'martin',   motif:'Suivi', note:'Post-vaccin', source:'cabinet' },
  { id:149, animalId:7,  date:'2026-03-25', start:'08:45', durationMin:15,  vet:'dupont',   motif:'Vaccin', note:'FeLV rappel', source:'cabinet' },
  { id:150, animalId:3,  date:'2026-03-25', start:'09:15', durationMin:60,  vet:'dupont',   motif:'Chirurgie', note:'Pyomètre', source:'cabinet' },
  { id:151, animalId:11, date:'2026-03-25', start:'11:00', durationMin:30,  vet:'dupont',   motif:'Consultation', note:'Masse abdominale', source:'online' },
  { id:152, animalId:9,  date:'2026-03-25', start:'14:30', durationMin:20,  vet:'dupont',   motif:'Suivi', note:'', source:'cabinet' },
  { id:153, animalId:2,  date:'2026-03-25', start:'08:30', durationMin:30,  vet:'lambert',  motif:'Consultation', note:'Otite bilatérale', source:'cabinet' },
  { id:154, animalId:6,  date:'2026-03-25', start:'09:15', durationMin:20,  vet:'lambert',  motif:'Vaccin', note:'', source:'online' },
  { id:155, animalId:12, date:'2026-03-25', start:'10:00', durationMin:60,  vet:'lambert',  motif:'Chirurgie', note:'Torsion gastrique', source:'cabinet' },
  { id:156, animalId:5,  date:'2026-03-25', start:'14:00', durationMin:30,  vet:'lambert',  motif:'Bilan', note:'Résultats écho', source:'cabinet' },

  // Thursday 26
  { id:157, animalId:8,  date:'2026-03-26', start:'08:30', durationMin:20,  vet:'rousseau', motif:'Vaccin', note:'Primo J2', source:'cabinet' },
  { id:158, animalId:4,  date:'2026-03-26', start:'09:00', durationMin:60,  vet:'rousseau', motif:'Chirurgie', note:'Stérilisation', source:'cabinet' },
  { id:159, animalId:10, date:'2026-03-26', start:'10:30', durationMin:30,  vet:'rousseau', motif:'Consultation', note:'Épilepsie suivi', source:'online' },
  { id:160, animalId:7,  date:'2026-03-26', start:'14:00', durationMin:20,  vet:'rousseau', motif:'Suivi', note:'J+14', source:'cabinet' },
  { id:161, animalId:3,  date:'2026-03-26', start:'15:00', durationMin:30,  vet:'rousseau', motif:'Bilan', note:'Thyroïde', source:'cabinet' },
  { id:162, animalId:1,  date:'2026-03-26', start:'08:30', durationMin:30,  vet:'martin',   motif:'Consultation', note:'Polydipsie', source:'cabinet' },
  { id:163, animalId:9,  date:'2026-03-26', start:'09:15', durationMin:15,  vet:'martin',   motif:'Vaccin', note:'', source:'online' },
  { id:164, animalId:5,  date:'2026-03-26', start:'09:45', durationMin:60,  vet:'martin',   motif:'Chirurgie', note:'Ablation lipome', source:'cabinet' },
  { id:165, animalId:11, date:'2026-03-26', start:'14:00', durationMin:30,  vet:'martin',   motif:'Consultation', note:'Conjonctivite', source:'cabinet' },
  { id:166, animalId:12, date:'2026-03-26', start:'15:00', durationMin:20,  vet:'martin',   motif:'Urgence', note:'Dyspnée soudaine', source:'online' },
  { id:167, animalId:2,  date:'2026-03-26', start:'08:45', durationMin:30,  vet:'dupont',   motif:'Bilan', note:'NFS + biochimie', source:'cabinet' },
  { id:168, animalId:6,  date:'2026-03-26', start:'09:30', durationMin:20,  vet:'dupont',   motif:'Vaccin', note:'', source:'cabinet' },
  { id:169, animalId:8,  date:'2026-03-26', start:'10:00', durationMin:60,  vet:'dupont',   motif:'Chirurgie', note:'Énucléation', source:'cabinet' },
  { id:170, animalId:4,  date:'2026-03-26', start:'14:00', durationMin:30,  vet:'dupont',   motif:'Suivi', note:'Post-op J+3', source:'online' },
  { id:171, animalId:10, date:'2026-03-26', start:'08:30', durationMin:20,  vet:'lambert',  motif:'Vaccin', note:'', source:'cabinet' },
  { id:172, animalId:7,  date:'2026-03-26', start:'09:00', durationMin:30,  vet:'lambert',  motif:'Consultation', note:'Masse mammaire', source:'cabinet' },
  { id:173, animalId:3,  date:'2026-03-26', start:'10:00', durationMin:60,  vet:'lambert',  motif:'Chirurgie', note:'Mastectomie', source:'cabinet' },
  { id:174, animalId:1,  date:'2026-03-26', start:'14:00', durationMin:20,  vet:'lambert',  motif:'Suivi', note:'Résultats anapath', source:'online' },
  { id:175, animalId:9,  date:'2026-03-26', start:'14:30', durationMin:30,  vet:'lambert',  motif:'Bilan', note:'Contrôle rénal', source:'cabinet' },

  // Friday 27
  { id:176, animalId:12, date:'2026-03-27', start:'08:30', durationMin:30,  vet:'rousseau', motif:'Consultation', note:'Toux kennel', source:'cabinet' },
  { id:177, animalId:2,  date:'2026-03-27', start:'09:15', durationMin:20,  vet:'rousseau', motif:'Vaccin', note:'', source:'online' },
  { id:178, animalId:6,  date:'2026-03-27', start:'09:45', durationMin:60,  vet:'rousseau', motif:'Chirurgie', note:'TPLO genou', source:'cabinet' },
  { id:179, animalId:8,  date:'2026-03-27', start:'11:30', durationMin:15,  vet:'rousseau', motif:'Suivi', note:'', source:'cabinet' },
  { id:180, animalId:5,  date:'2026-03-27', start:'14:00', durationMin:30,  vet:'rousseau', motif:'Bilan', note:'Fin traitement', source:'cabinet' },
  { id:181, animalId:11, date:'2026-03-27', start:'08:30', durationMin:20,  vet:'martin',   motif:'Vaccin', note:'Rappel FeLV', source:'cabinet' },
  { id:182, animalId:4,  date:'2026-03-27', start:'09:00', durationMin:30,  vet:'martin',   motif:'Consultation', note:'Claudication', source:'online' },
  { id:183, animalId:10, date:'2026-03-27', start:'10:00', durationMin:60,  vet:'martin',   motif:'Chirurgie', note:'Résection tumorale', source:'cabinet' },
  { id:184, animalId:1,  date:'2026-03-27', start:'14:00', durationMin:30,  vet:'martin',   motif:'Suivi', note:'J+21', source:'cabinet' },
  { id:185, animalId:3,  date:'2026-03-27', start:'08:30', durationMin:30,  vet:'dupont',   motif:'Bilan', note:'Pré-anesthésie', source:'cabinet' },
  { id:186, animalId:7,  date:'2026-03-27', start:'09:15', durationMin:15,  vet:'dupont',   motif:'Vaccin', note:'', source:'cabinet' },
  { id:187, animalId:9,  date:'2026-03-27', start:'09:45', durationMin:60,  vet:'dupont',   motif:'Chirurgie', note:'Césarienne urgence', source:'cabinet' },
  { id:188, animalId:12, date:'2026-03-27', start:'11:30', durationMin:20,  vet:'dupont',   motif:'Suivi', note:'', source:'online' },
  { id:189, animalId:6,  date:'2026-03-27', start:'14:30', durationMin:30,  vet:'dupont',   motif:'Consultation', note:'Dermatite atopique', source:'cabinet' },
  { id:190, animalId:2,  date:'2026-03-27', start:'08:30', durationMin:20,  vet:'lambert',  motif:'Vaccin', note:'', source:'online' },
  { id:191, animalId:8,  date:'2026-03-27', start:'09:00', durationMin:60,  vet:'lambert',  motif:'Chirurgie', note:'Prostatectomie', source:'cabinet' },
  { id:192, animalId:5,  date:'2026-03-27', start:'10:30', durationMin:30,  vet:'lambert',  motif:'Consultation', note:'Dystocie', source:'cabinet' },
  { id:193, animalId:11, date:'2026-03-27', start:'14:00', durationMin:20,  vet:'lambert',  motif:'Suivi', note:'Post-op J+7', source:'cabinet' },
  { id:194, animalId:4,  date:'2026-03-27', start:'14:30', durationMin:30,  vet:'lambert',  motif:'Bilan', note:'Contrôle mensuel', source:'online' },

  // Saturday 28
  { id:195, animalId:1,  date:'2026-03-28', start:'08:30', durationMin:20,  vet:'rousseau', motif:'Vaccin', note:'', source:'cabinet' },
  { id:196, animalId:7,  date:'2026-03-28', start:'09:00', durationMin:30,  vet:'rousseau', motif:'Consultation', note:'Perte poids', source:'cabinet' },
  { id:197, animalId:3,  date:'2026-03-28', start:'10:00', durationMin:60,  vet:'rousseau', motif:'Chirurgie', note:'Cystotomie', source:'cabinet' },
  { id:198, animalId:9,  date:'2026-03-28', start:'14:00', durationMin:30,  vet:'rousseau', motif:'Suivi', note:'Post-op J+5', source:'online' },
  { id:199, animalId:12, date:'2026-03-28', start:'08:30', durationMin:30,  vet:'martin',   motif:'Consultation', note:'Anorexie', source:'cabinet' },
  { id:200, animalId:2,  date:'2026-03-28', start:'09:15', durationMin:15,  vet:'martin',   motif:'Vaccin', note:'', source:'cabinet' },
  { id:201, animalId:10, date:'2026-03-28', start:'10:00', durationMin:60,  vet:'martin',   motif:'Chirurgie', note:'Entropion', source:'cabinet' },
  { id:202, animalId:4,  date:'2026-03-28', start:'09:00', durationMin:20,  vet:'dupont',   motif:'Urgence', note:'Convulsions', source:'cabinet' },
  { id:203, animalId:6,  date:'2026-03-28', start:'09:30', durationMin:30,  vet:'dupont',   motif:'Consultation', note:'Masse lymphatique', source:'online' },
  { id:204, animalId:8,  date:'2026-03-28', start:'11:00', durationMin:20,  vet:'dupont',   motif:'Suivi', note:'', source:'cabinet' },
  { id:205, animalId:5,  date:'2026-03-28', start:'08:45', durationMin:30,  vet:'lambert',  motif:'Bilan', note:'Contrôle thyroïde', source:'cabinet' },
  { id:206, animalId:11, date:'2026-03-28', start:'09:30', durationMin:20,  vet:'lambert',  motif:'Vaccin', note:'', source:'online' },
  { id:207, animalId:1,  date:'2026-03-28', start:'10:30', durationMin:30,  vet:'lambert',  motif:'Consultation', note:'Murmure cardiaque', source:'cabinet' },
];

// =============== STATE ===============
let today = new Date(2026, 2, 21, 10, 0, 0); // March 21, 2026 at 10:00
let currentWeekStart = new Date(2026, 2, 23); // Week of March 23
let miniCalDate = new Date(2026, 2, 1);
let selectedDay = fmtDate(new Date(2026, 2, 23)); // Default: Monday 23
let activeVets = new Set(['rousseau','martin','dupont','lambert']);
let currentView = 'week';
let selectedDuration = 20;
let selectedMotif = '';
let selectedAnimal = null;
let newRdvSlotDate = null;
let newRdvSlotHour = null;
let nextId = appointments.length + 1;
let activeMotifs = new Set(Object.keys(MOTIF_COLORS));
let freeSlotsActive = false;
let freeDuration = 20;

const FREE_START = 8 * 60;  // 8h00
const FREE_END   = 19 * 60; // 19h00

// =============== UTILS ===============
function getWeekStart(d){
  const day = d.getDay(); // 0=Sun
  const diff = (day === 0) ? -6 : 1 - day;
  const s = new Date(d);
  s.setDate(d.getDate() + diff);
  s.setHours(0,0,0,0);
  return s;
}
function addDays(d, n){ const r = new Date(d); r.setDate(r.getDate()+n); return r; }
function fmtDate(d){ return d.toISOString().split('T')[0]; }
function fmtTime(hhmm){ const [h,m]=hhmm.split(':'); return `${h}h${m==='00'?'':m}`; }
function timeToMin(hhmm){ const [h,m]=hhmm.split(':'); return +h*60+ +m; }
function minToTime(m){ return `${String(Math.floor(m/60)).padStart(2,'0')}:${String(m%60).padStart(2,'0')}`; }
const DOW_FR = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
const MONTHS_FR = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

// =============== MINI CALENDAR ===============
function renderMiniCal(){
  const y = miniCalDate.getFullYear(), m = miniCalDate.getMonth();
  document.getElementById('mini-month').textContent = `${MONTHS_FR[m]} ${y}`;
  const grid = document.getElementById('mini-cal-grid');
  grid.innerHTML = '';
  // Day-of-week headers
  ['L','M','M','J','V','S','D'].forEach(d => {
    const el = document.createElement('div');
    el.className = 'mini-cal-dow';
    el.textContent = d;
    grid.appendChild(el);
  });
  const first = new Date(y, m, 1);
  const startDow = (first.getDay()+6)%7; // Mon=0
  const days = new Date(y, m+1, 0).getDate();
  for(let i=0;i<startDow;i++){
    const el = document.createElement('div');
    grid.appendChild(el);
  }
  const rdvDates = new Set(appointments.map(a => a.date));
  for(let d=1;d<=days;d++){
    const el = document.createElement('div');
    el.className = 'mini-cal-day';
    el.textContent = d;
    const thisDate = new Date(y,m,d);
    const dateStr = fmtDate(thisDate);
    if(fmtDate(thisDate)===fmtDate(today)) el.classList.add('today');
    const ws = getWeekStart(thisDate);
    if(fmtDate(ws)===fmtDate(currentWeekStart)) el.classList.add('selected');
    if(rdvDates.has(dateStr)) el.classList.add('has-rdv');
    el.onclick = () => {
      currentWeekStart = getWeekStart(thisDate);
      selectedDay = dateStr;
      renderWeek();
      renderMiniCal();
      renderDayPlanning();
    };
    grid.appendChild(el);
  }
}
function miniCalPrev(){ miniCalDate.setMonth(miniCalDate.getMonth()-1); renderMiniCal(); }
function miniCalNext(){ miniCalDate.setMonth(miniCalDate.getMonth()+1); renderMiniCal(); }

// =============== WEEK RENDER ===============
const HOUR_START = 7;
const HOUR_END = 20;
const SLOT_H = 80; // px per hour — matches time-label height

function renderWeek(){
  const head = document.getElementById('agenda-head');
  const inner = document.getElementById('agenda-inner');
  const cols = currentView === 'week' ? 7 : 1;
  const gridCols = `55px repeat(${cols},1fr)`;
  const headCols = `55px repeat(${cols},1fr) 6px`;

  head.style.gridTemplateColumns = headCols;
  inner.style.gridTemplateColumns = gridCols;

  // Clear existing day headers (keep first cell)
  while(head.children.length > 1) head.removeChild(head.lastChild);
  inner.innerHTML = '';

  const days = [];
  for(let i=0;i<cols;i++) days.push(addDays(currentWeekStart, i));

  // Week label
  const ws = days[0], we = days[days.length-1];
  document.getElementById('week-label').textContent =
    currentView === 'week'
      ? `${ws.getDate()} – ${we.getDate()} ${MONTHS_FR[we.getMonth()]} ${we.getFullYear()}`
      : `${DOW_FR[(days[0].getDay()+6)%7+1] || DOW_FR[days[0].getDay()]} ${days[0].getDate()} ${MONTHS_FR[days[0].getMonth()]}`;

  // Day headers
  days.forEach((d, di) => {
    const isToday = fmtDate(d) === fmtDate(today);
    const el = document.createElement('div');
    const isSelected = fmtDate(d) === selectedDay;
    el.style.cssText = `padding:8px 0;text-align:center;border-left:${di===0?'1px solid #dde3ea':'1px solid #f1f5f9'};cursor:pointer;transition:background .1s;${isToday?'background:#f5f3ff;':isSelected?'background:#f8f7ff;':''}`;
    el.innerHTML = `
      <div style="font-size:var(--text-xs);font-weight:600;color:${isToday?'#4338ca':isSelected?'#6366f1':'#94a3b8'};text-transform:uppercase;letter-spacing:.06em;">${DOW_FR[(d.getDay()+6)%7+1]||DOW_FR[0]}</div>
      <div style="font-size:18px;font-weight:${isToday||isSelected?'800':'600'};color:${isToday?'#4338ca':isSelected?'#6366f1':'#0f172a'};line-height:1.1;margin-top:1px;">${d.getDate()}</div>
      <div style="font-size:var(--text-xs);color:#94a3b8;margin-top:1px;">${countRdv(fmtDate(d))} RDV</div>`;
    el.onclick = () => { selectedDay = fmtDate(d); renderWeek(); renderDayPlanning(); };
    head.appendChild(el);
  });

  // Scrollbar spacer
  const spacer = document.createElement('div');
  head.appendChild(spacer);

  // Time column
  const timeCol = document.createElement('div');
  timeCol.className = 'time-col';
  for(let h=HOUR_START;h<=HOUR_END;h++){
    const lbl = document.createElement('div');
    lbl.className = 'time-label';
    lbl.textContent = String(h);
    timeCol.appendChild(lbl);
  }
  inner.appendChild(timeCol);

  // Day columns
  days.forEach((d,di) => {
    const isToday = fmtDate(d) === fmtDate(today);
    const col = document.createElement('div');
    col.className = 'day-col';
    if(isToday) col.style.background = '#fdfcff';

    const totalH = (HOUR_END - HOUR_START + 1);
    col.style.height = `${totalH * SLOT_H}px`;
    col.style.position = 'relative';

    // Hour slots — 2 clickable half-zones per hour
    for(let h=HOUR_START;h<=HOUR_END;h++){
      const slotWrap = document.createElement('div');
      slotWrap.style.cssText = `position:absolute;top:${(h-HOUR_START)*SLOT_H}px;left:0;right:0;height:${SLOT_H}px;border-bottom:1px solid #c9d0d8;`;

      // Top half (on the hour)
      const top = document.createElement('div');
      top.style.cssText = `position:absolute;top:0;left:0;right:0;height:50%;cursor:pointer;transition:background .1s;`;
      top.dataset.time = `${String(h).padStart(2,'0')}:00`;
      top.onmouseenter = () => { top.style.background='#f5f3ff'; };
      top.onmouseleave = () => { top.style.background=''; };
      top.onclick = (e) => { e.stopPropagation(); openNewRdv(fmtDate(d), top.dataset.time); };

      // Bottom half (half past)
      const bot = document.createElement('div');
      bot.style.cssText = `position:absolute;bottom:0;left:0;right:0;height:50%;cursor:pointer;transition:background .1s;border-top:1px dashed #dde2e7;`;
      bot.dataset.time = `${String(h).padStart(2,'0')}:30`;
      bot.onmouseenter = () => { bot.style.background='#f5f3ff'; };
      bot.onmouseleave = () => { bot.style.background=''; };
      bot.onclick = (e) => { e.stopPropagation(); openNewRdv(fmtDate(d), bot.dataset.time); };

      slotWrap.appendChild(top);
      slotWrap.appendChild(bot);
      col.appendChild(slotWrap);
    }

    // Appointments — with overlap detection
    const dayAppts = appointments.filter(a =>
      a.date === fmtDate(d) && activeVets.has(a.vet) && activeMotifs.has(a.motif.split(' ')[0])
    );

    // Assign column lanes to overlapping blocks
    const lanes = [];
    dayAppts.forEach(a => {
      const s = timeToMin(a.start);
      const e = s + a.durationMin;
      let lane = 0;
      while(lanes[lane] && lanes[lane] > s) lane++;
      lanes[lane] = e;
      a._lane = lane;
      a._maxLane = 0; // will compute after
    });
    const totalLanes = lanes.length || 1;
    dayAppts.forEach(a => { a._totalLanes = totalLanes; });

    dayAppts.forEach(a => {
      const block = createRdvBlock(a);
      // Adjust horizontal position for lanes
      const laneW = 100 / a._totalLanes;
      const left = a._lane * laneW;
      block.style.left = `calc(${left}% + 3px)`;
      block.style.right = `calc(${100 - left - laneW}% + 3px)`;
      col.appendChild(block);
    });

    // Now line
    if(isToday){
      const nowMin = today.getHours()*60 + today.getMinutes();
      const startMin = HOUR_START * 60;
      if(nowMin >= startMin && nowMin <= HOUR_END*60){
        const top = (nowMin - startMin) / 60 * SLOT_H;
        const line = document.createElement('div');
        line.className = 'now-line';
        line.style.top = `${top}px`;
        line.innerHTML = `<div class="now-dot"></div>`;
        col.appendChild(line);
      }
    }

    // Free slots overlay
    if(freeSlotsActive) renderFreeSlots(col, fmtDate(d));

    inner.appendChild(col);
  });

  // Scroll to 8am
  const body = document.getElementById('agenda-body');
  body.scrollTop = (8 - HOUR_START) * SLOT_H;
}

function countRdv(dateStr){
  return appointments.filter(a => a.date === dateStr && activeVets.has(a.vet)).length;
}

function createRdvBlock(a){
  const animal = ANIMALS.find(x => x.id === a.animalId);
  const vet = VETS[a.vet];
  const startMin = timeToMin(a.start);
  const topPx = (startMin - HOUR_START*60) / 60 * SLOT_H + 1; // +1px inset from hour line
  const heightPx = Math.max(a.durationMin / 60 * SLOT_H - 3, 14); // -3px for top+bottom gap
  const motifColor = MOTIF_COLORS[a.motif.split(' ')[0]] || vet.color;

  const apptEnd = new Date(a.date + 'T' + minToTime(timeToMin(a.start) + a.durationMin));
  const isPast = !a.status && apptEnd < today;
  const isCancelled = a.status === 'cancelled';

  // Available lines: height / 15px
  const lines = Math.floor((heightPx - 2) / 15);

  const block = document.createElement('div');
  block.className = 'rdv-block';
  block.dataset.id = a.id;

  let bg, borderColor, opacity = '1';
  if(isCancelled){ bg='#f1f5f9'; borderColor='#94a3b8'; opacity='0.8'; }
  else if(isPast){ bg='#e2e8f0'; borderColor=vet.color; opacity='0.8'; }
  else { bg=vet.bg; borderColor=vet.color; }

  block.style.cssText = `top:${topPx}px;height:${heightPx}px;background:${bg};border-left-color:${borderColor};opacity:${opacity};overflow:hidden;`;

  const nameColor = (isCancelled||isPast) ? '#334155' : '#0f172a';
  const subColor  = (isCancelled||isPast) ? '#475569' : '#64748b';
  const timeColor = (isCancelled||isPast) ? '#64748b' : vet.color;
  const strikethrough = isCancelled ? 'text-decoration:line-through;' : '';
  const cancelBadge = isCancelled ? `<span style="font-size:9px;background:#fee2e2;color:#dc2626;padding:0 3px;border-radius:2px;flex-shrink:0;margin-left:2px;">✕</span>` : '';

  const dot = `<span style="width:6px;height:6px;border-radius:50%;background:${motifColor};flex-shrink:0;display:inline-block;"></span>`;

  // Each line: fixed height 15px, nowrap, overflow ellipsis
  const R = `display:flex;align-items:center;gap:3px;overflow:hidden;white-space:nowrap;height:15px;min-height:15px;max-height:15px;flex-shrink:0;`;

  let html = `<div style="display:flex;flex-direction:column;overflow:hidden;width:100%;height:${heightPx - 2}px;justify-content:center;">`;

  if(lines <= 0){
    // Too small to display anything
  } else if(lines === 1){
    // Time + duration + name on one line
    html += `<div style="${R}">
      ${dot}
      <span style="font-size:var(--text-xs);font-weight:700;color:${timeColor};flex-shrink:0;">${fmtTime(a.start)} · ${a.durationMin}min</span>
      <span style="font-size:var(--text-xs);color:${nameColor};${strikethrough}overflow:hidden;text-overflow:ellipsis;">· ${animal?.name||'?'}</span>
      ${cancelBadge}
    </div>`;
  } else {
    // L1: time + duration + badges
    html += `<div style="${R}">
      ${dot}
      <span style="font-size:var(--text-xs);font-weight:700;color:${timeColor};flex-shrink:0;">${fmtTime(a.start)} · ${a.durationMin}min</span>
      ${cancelBadge}
    </div>`;
    // L2: name
    if(lines >= 2) html += `<div style="${R}">
      <span style="font-size:var(--text-xs);font-weight:700;color:${nameColor};${strikethrough}overflow:hidden;text-overflow:ellipsis;">${animal?.emoji||''} ${animal?.name||'?'}</span>
    </div>`;
    // L3: owner
    if(lines >= 3) html += `<div style="${R}">
      <span style="font-size:var(--text-xs);color:${subColor};overflow:hidden;text-overflow:ellipsis;">${animal?.owner||''}</span>
    </div>`;
    // L4: motif
    if(lines >= 4) html += `<div style="${R}">
      <span style="font-size:var(--text-xs);color:${isCancelled?subColor:motifColor};font-weight:var(--weight-medium);overflow:hidden;text-overflow:ellipsis;">${a.motif}${a.note ? ` · ${a.note}` : ''}</span>
    </div>`;
  }

  html += `</div>`;
  block.innerHTML = html;
  block.onclick = (e) => { e.stopPropagation(); showRdvPopup(a.id, e); };
  return block;
}

// =============== POPUPS ===============
function showRdvPopup(id, e){
  const a = appointments.find(x => x.id === id);
  if(!a) return;
  const animal = ANIMALS.find(x => x.id === a.animalId);
  const vet = VETS[a.vet];

  document.getElementById('pp-animal').textContent = `${animal?.emoji||''} ${animal?.name||'?'}`;
  document.getElementById('pp-proprio').textContent = `${animal?.owner||''} · ${animal?.phone||''}`;
  const badge = document.getElementById('pp-badge');
  badge.textContent = a.source === 'online' ? 'En ligne' : a.motif;
  badge.className = 'badge ' + (a.source === 'online' ? 'badge-online' : (a.motif==='Urgence'?'badge-urgent':'badge-followup'));
  document.getElementById('pp-time').textContent = `${a.start.replace(':',' h ')} — ${minToTime(timeToMin(a.start)+a.durationMin).replace(':',' h ')}`;
  document.getElementById('pp-duration').textContent = `${a.durationMin} min`;
  document.getElementById('pp-vet').textContent = vet.name;
  document.getElementById('pp-motif').textContent = a.motif;
  const noteRow = document.getElementById('pp-note-row');
  if(a.note){ noteRow.style.display='flex'; document.getElementById('pp-note').textContent = a.note; }
  else noteRow.style.display='none';

  // Adapt buttons based on state
  const apptEnd = new Date(a.date + 'T' + minToTime(timeToMin(a.start) + a.durationMin));
  const isPast = !a.status && apptEnd < today;
  const isCancelled = a.status === 'cancelled';
  const btnArea = document.querySelector('#rdv-popup .pp-actions');
  if(btnArea){
    if(isCancelled){
      btnArea.innerHTML = '';
    } else if(isPast){
      btnArea.innerHTML = `
        <button onclick="openConsultation()" class="btn btn-primary btn-xs" style="flex:1;justify-content:center;">
          <svg width="10" height="10" fill="none" viewBox="0 0 12 12"><path d="M2 6h8M6 2l4 4-4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Ouvrir consultation
        </button>`;
    } else {
      btnArea.innerHTML = `
        <button onclick="placerSalleAttente()" class="btn btn-primary btn-xs" style="flex:1;justify-content:center;">
          <svg width="10" height="10" fill="none" viewBox="0 0 12 12"><path d="M6 1v5l3 2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.3"/></svg>
          Salle d'attente
        </button>
        <button onclick="editRdv()" class="btn btn-secondary btn-xs">
          <svg width="10" height="10" fill="none" viewBox="0 0 12 12"><path d="M8 2l2 2-6 6H2V8l6-6z" stroke="#64748b" stroke-width="1.3" stroke-linejoin="round"/></svg>
        </button>
        <button onclick="cancelRdv()" class="btn btn-secondary btn-xs" style="color:#dc2626;border-color:#fecaca;">
          <svg width="10" height="10" fill="none" viewBox="0 0 8 8"><path d="M1 1l6 6M7 1L1 7" stroke="#dc2626" stroke-width="1.3" stroke-linecap="round"/></svg>
        </button>`;
    }
  }

  const popup = document.getElementById('rdv-popup');
  popup.dataset.rdvId = id;
  positionPopup(popup, e);
  showPopup(popup);
}

function openNewRdv(date, hour){
  newRdvSlotDate = date;
  newRdvSlotHour = hour;
  const d = new Date(date);
  const dow = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
  const dowLabel = dow[d.getDay()];
  document.getElementById('new-rdv-slot').textContent =
    `${dowLabel} ${d.getDate()} ${MONTHS_FR[d.getMonth()]} · ${hour.replace(':','\u00a0h\u00a0')}`;
  document.getElementById('new-rdv-time').value = hour;
  resetNewRdvForm();
  showCenteredPopup(document.getElementById('new-rdv-popup'));
}

function openNewRdvGlobal(){
  newRdvSlotDate = fmtDate(today);
  newRdvSlotHour = '09:00';
  document.getElementById('new-rdv-slot').textContent = `Aujourd'hui · 09\u00a0h\u00a000`;
  document.getElementById('new-rdv-time').value = '09:00';
  resetNewRdvForm();
  showCenteredPopup(document.getElementById('new-rdv-popup'));
}

function showCenteredPopup(popup){
  closeAllPopups();
  popup.style.display = 'block';
  popup.style.position = 'fixed';
  popup.style.top = '50%';
  popup.style.left = '50%';
  popup.style.transform = 'translate(-50%,-50%)';
  popup.style.right = 'auto';
  popup.style.zIndex = '500';
  document.getElementById('overlay').style.display = 'block';
}

function positionPopup(popup, e){
  const x = e.clientX, y = e.clientY;
  const w = 300, h = 300;
  let left = x + 12;
  let top = y - 40;
  if(left + w > window.innerWidth - 16) left = x - w - 12;
  if(top + h > window.innerHeight - 16) top = window.innerHeight - h - 16;
  popup.style.left = left + 'px';
  popup.style.top = Math.max(8, top) + 'px';
  popup.style.right = 'auto';
}

function showPopup(popup){
  closeAllPopups();
  popup.style.display = 'block';
  document.getElementById('overlay').style.display = 'block';
}

function closeAllPopups(){
  document.getElementById('rdv-popup').style.display='none';
  document.getElementById('new-rdv-popup').style.display='none';
  document.getElementById('overlay').style.display='none';
  document.getElementById('animal-suggestions').style.display='none';
}

// =============== NEW RDV FORM ===============
function resetNewRdvForm(){
  document.getElementById('new-animal-input').value='';
  document.getElementById('new-note-input').value='';
  // Time is set before reset is called — don't overwrite
  selectedAnimal = null;
  selectedMotif = '';
  selectedDuration = 20;
  document.querySelectorAll('.motif-chip').forEach(c => {
    c.style.borderColor='#e2e8f0';c.style.background='#f8fafc';c.style.color='#64748b';
  });
  ['15','20','30','60'].forEach(d => {
    const btn = document.getElementById('dur-'+d);
    if(!btn) return;
    btn.style.borderColor='#e2e8f0';btn.style.background='#fff';btn.style.color='#64748b';
  });
  document.getElementById('dur-20').style.borderColor='#4338ca';
  document.getElementById('dur-20').style.background='#eef2ff';
  document.getElementById('dur-20').style.color='#4338ca';
  updateEndTime();
}

function updateEndTime(){
  const timeInput = document.getElementById('new-rdv-time');
  if(!timeInput) return;
  const [h,m] = timeInput.value.split(':').map(Number);
  const endMin = h*60+m+selectedDuration;
  const eh = String(Math.floor(endMin/60)).padStart(2,'0');
  const em = String(endMin%60).padStart(2,'0');
  const el = document.getElementById('new-rdv-end');
  if(el) el.textContent = `${eh} h ${em==='00'?'00':em}`;
}

function setDuration(d){
  selectedDuration = d;
  ['15','20','30','60'].forEach(v => {
    const btn = document.getElementById('dur-'+v);
    if(!btn) return;
    const active = v==d;
    btn.style.borderColor = active ? '#4338ca' : '#e2e8f0';
    btn.style.background = active ? '#eef2ff' : '#fff';
    btn.style.color = active ? '#4338ca' : '#64748b';
  });
  updateEndTime();
}

function selectMotif(el, motif){
  selectedMotif = motif;
  document.querySelectorAll('.motif-chip').forEach(c => {
    c.style.borderColor='#e2e8f0';c.style.background='#f8fafc';c.style.color='#64748b';
  });
  const col = MOTIF_COLORS[motif] || '#4338ca';
  el.style.borderColor=col;el.style.background=col+'18';el.style.color=col;
}

function searchAnimals(q){
  const box = document.getElementById('animal-suggestions');
  if(!q){ box.style.display='none'; return; }
  const results = ANIMALS.filter(a =>
    a.name.toLowerCase().includes(q.toLowerCase()) ||
    a.owner.toLowerCase().includes(q.toLowerCase())
  );
  if(!results.length){ box.style.display='none'; return; }
  box.innerHTML = results.map(a => `
    <div onclick="selectAnimal(${a.id})" style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;transition:background .1s;" onmouseenter="this.style.background='#f8fafc'" onmouseleave="this.style.background=''">
      <span style="font-size:var(--text-lg);">${a.emoji}</span>
      <div><p style="font-size:var(--text-sm);font-weight:600;color:#0f172a;">${a.name}</p><p style="font-size:var(--text-xs);color:#94a3b8;">${a.breed} · ${a.owner}</p></div>
    </div>`).join('');
  box.style.display='block';
}

function selectAnimal(id){
  selectedAnimal = ANIMALS.find(a=>a.id===id);
  document.getElementById('new-animal-input').value = `${selectedAnimal.emoji} ${selectedAnimal.name} · ${selectedAnimal.owner}`;
  document.getElementById('animal-suggestions').style.display='none';
}

function confirmNewRdv(){
  if(!selectedAnimal){ showToast('Sélectionner un patient', '#f59e0b'); return; }
  if(!selectedMotif){ showToast('Sélectionner un motif', '#f59e0b'); return; }
  const vet = document.getElementById('new-vet-select').value;
  const note = document.getElementById('new-note-input').value.trim();
  const source = document.getElementById('new-source-select').value;
  const timeVal = document.getElementById('new-rdv-time').value || newRdvSlotHour;
  appointments.push({
    id: nextId++,
    animalId: selectedAnimal.id,
    date: newRdvSlotDate,
    start: timeVal,
    durationMin: selectedDuration,
    vet, motif: selectedMotif, note, source
  });
  closeAllPopups();
  renderWeek();
  renderMiniCal();
  showToast(`RDV confirmé · ${selectedAnimal.name} · ${timeVal.replace(':',' h ')}`, '#16a34a');
}

// =============== ACTIONS ===============
function placerSalleAttente(){
  closeAllPopups();
  showToast('Placé en salle d\'attente', '#059669');
}

function openConsultation(){
  closeAllPopups();
  showToast('Ouverture de la consultation…', '#4338ca');
}

function editRdv(){ showToast('Modification bientôt disponible', '#64748b'); }
function cancelRdv(){
  const id = +document.getElementById('rdv-popup').dataset.rdvId;
  const a = appointments.find(x => x.id === id);
  if(a) a.status = 'cancelled';
  closeAllPopups();
  renderWeek();
  renderMiniCal();
  showToast('RDV annulé', '#dc2626');
}

// =============== NAV ===============
function prevWeek(){ currentWeekStart = addDays(currentWeekStart, currentView==='week' ? -7 : -1); renderWeek(); renderMiniCal(); }
function nextWeek(){ currentWeekStart = addDays(currentWeekStart, currentView==='week' ? 7 : 1); renderWeek(); renderMiniCal(); }
function goToday(){ currentWeekStart = getWeekStart(today); renderWeek(); renderMiniCal(); }
function setView(v){
  currentView = v;
  if(v==='day') currentWeekStart = today;
  document.getElementById('view-week').style.cssText = v==='week' ? 'padding:4px 10px;border-radius:6px;border:none;font-size:var(--text-sm);font-weight:var(--weight-medium);cursor:pointer;font-family:inherit;background:#fff;color:#0f172a;box-shadow:0 1px 3px rgba(0,0,0,.08);' : 'padding:4px 10px;border-radius:6px;border:none;font-size:var(--text-sm);font-weight:var(--weight-medium);cursor:pointer;font-family:inherit;background:transparent;color:#64748b;';
  document.getElementById('view-day').style.cssText = v==='day' ? 'padding:4px 10px;border-radius:6px;border:none;font-size:var(--text-sm);font-weight:var(--weight-medium);cursor:pointer;font-family:inherit;background:#fff;color:#0f172a;box-shadow:0 1px 3px rgba(0,0,0,.08);' : 'padding:4px 10px;border-radius:6px;border:none;font-size:var(--text-sm);font-weight:var(--weight-medium);cursor:pointer;font-family:inherit;background:transparent;color:#64748b;';
  renderWeek();
}

function toggleVet(el, vet){
  if(activeVets.has(vet)) activeVets.delete(vet);
  else activeVets.add(vet);
  const check = document.getElementById('check-'+vet);
  check.style.opacity = activeVets.has(vet) ? '1' : '0.15';
  renderWeek();
}

// =============== TOAST ===============
function showToast(msg, color='#16a34a'){
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = color;
  t.style.opacity = '1';
  t.style.transform = 'translateX(-50%) translateY(0)';
  setTimeout(() => {
    t.style.opacity='0';
    t.style.transform='translateX(-50%) translateY(8px)';
  }, 2500);
}

// =============== Agenda sidebar drawer (mobile) ===============
function toggleAgendaSidebar(){
  var sb = document.getElementById('agenda-sidebar');
  var ov = document.getElementById('drawer-overlay');
  var isOpen = sb.classList.contains('open');
  if(isOpen){ sb.classList.remove('open'); ov.classList.remove('open'); }
  else { sb.classList.add('open'); ov.classList.add('open'); }
}
function closeAgendaSidebar(){
  document.getElementById('agenda-sidebar').classList.remove('open');
  document.getElementById('drawer-overlay').classList.remove('open');
}

function initMotifFilters(){
  const container = document.getElementById('motif-filters');
  if(!container) return;
  container.innerHTML = '';
  Object.entries(MOTIF_COLORS).forEach(([motif, color]) => {
    const active = activeMotifs.has(motif);
    const chip = document.createElement('div');
    chip.className = 'vet-chip';
    chip.innerHTML = `
      <div style="width:10px;height:10px;border-radius:2px;background:${color};flex-shrink:0;"></div>
      <span style="font-size:var(--text-sm);color:#334155;flex:1;">${motif}</span>
      <svg style="opacity:${active?1:.15}" width="13" height="13" fill="none" viewBox="0 0 16 16"><path d="M3 8l3.5 3.5 6.5-7" stroke="${color}" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
    chip.onclick = () => {
      if(activeMotifs.has(motif)) activeMotifs.delete(motif);
      else activeMotifs.add(motif);
      initMotifFilters();
      renderWeek();
    };
    container.appendChild(chip);
  });
}

// =============== DAY PLANNING ===============
function renderDayPlanning(){
  const list = document.getElementById('day-planning-list');
  const label = document.getElementById('day-planning-label');
  const countEl = document.getElementById('day-planning-count');
  if(!list) return;

  const d = new Date(selectedDay + 'T12:00:00');
  const DOW = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
  label.textContent = `${DOW[d.getDay()]} ${d.getDate()} ${MONTHS_FR[d.getMonth()]}`;

  const dayAppts = appointments
    .filter(a => a.date === selectedDay && activeVets.has(a.vet) && a.status !== 'cancelled')
    .sort((a,b) => timeToMin(a.start) - timeToMin(b.start));

  countEl.textContent = `${dayAppts.length} RDV`;
  countEl.style.display = dayAppts.length ? '' : 'none';

  if(dayAppts.length === 0){
    list.innerHTML = `<p style="font-size:var(--text-xs);color:#94a3b8;text-align:center;padding:12px 0;">Aucun RDV ce jour</p>`;
    return;
  }

  list.innerHTML = dayAppts.map(a => {
    const animal = ANIMALS.find(x => x.id === a.animalId);
    const vet = VETS[a.vet];
    const motifColor = MOTIF_COLORS[a.motif] || vet.color;
    const endTime = minToTime(timeToMin(a.start) + a.durationMin);
    const isPast = new Date(a.date + 'T' + endTime) < today;
    const opacity = isPast ? 'opacity:.55;' : '';
    return `
    <div style="display:flex;align-items:flex-start;gap:8px;padding:7px 8px;border-radius:8px;background:#fff;border:1px solid #f1f5f9;cursor:pointer;transition:background .1s;${opacity}" onmouseenter="this.style.background='#f8fafc'" onmouseleave="this.style.background='#fff'">
      <div style="width:3px;align-self:stretch;border-radius:2px;background:${vet.color};flex-shrink:0;margin-top:1px;"></div>
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:5px;margin-bottom:2px;">
          <span style="font-size:var(--text-xs);font-weight:700;color:${vet.color};">${a.start.replace(':','h')}</span>
          <span style="font-size:var(--text-xs);color:#94a3b8;">·</span>
          <span style="font-size:var(--text-xs);font-weight:600;color:${motifColor};">${a.motif}</span>
          <span style="font-size:var(--text-xs);color:#94a3b8;margin-left:auto;white-space:nowrap;">${a.durationMin}min</span>
        </div>
        <div style="font-size:var(--text-sm);font-weight:var(--weight-medium);color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${animal?.emoji||''} ${animal?.name||'?'}</div>
        <div style="font-size:var(--text-xs);color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${animal?.owner||''}</div>
      </div>
    </div>`;
  }).join('');
}

// =============== FREE SLOTS ===============
function toggleFreeSlots(){
  freeSlotsActive = !freeSlotsActive;
  const btn = document.getElementById('free-slots-btn');
  const picker = document.getElementById('free-dur-picker');
  const sbBtn = document.getElementById('sb-free-slots-btn');
  const sbDur = document.getElementById('sb-free-dur');
  if(freeSlotsActive){
    if(btn){btn.style.background='#eef2ff';btn.style.borderColor='#4338ca';btn.style.color='#4338ca';}
    if(picker) picker.style.display='flex';
    if(sbBtn){sbBtn.style.background='#eef2ff';sbBtn.style.borderColor='#4338ca';sbBtn.style.color='#4338ca';}
    if(sbDur) sbDur.style.display='flex';
  } else {
    if(btn){btn.style.background='';btn.style.borderColor='';btn.style.color='';}
    if(picker) picker.style.display='none';
    if(sbBtn){sbBtn.style.background='';sbBtn.style.borderColor='';sbBtn.style.color='';}
    if(sbDur) sbDur.style.display='none';
  }
  renderWeek();
}

function setFreeDur(dur){
  freeDuration = dur;
  document.querySelectorAll('.free-dur-btn').forEach(b => {
    const active = +b.dataset.dur === dur;
    b.style.borderColor = active ? '#4338ca' : '#e2e8f0';
    b.style.background  = active ? '#eef2ff' : '#fff';
    b.style.color       = active ? '#4338ca' : '#64748b';
  });
  renderWeek();
}

function renderFreeSlots(col, dateStr){
  // For each active vet, find gaps in their schedule for this day
  [...activeVets].forEach(vetKey => {
    const vet = VETS[vetKey];
    if(!vet) return;

    // Get this vet's appointments for the day, sorted by start
    const vetAppts = appointments
      .filter(a => a.date === dateStr && a.vet === vetKey && a.status !== 'cancelled')
      .map(a => ({ s: timeToMin(a.start), e: timeToMin(a.start) + a.durationMin }))
      .sort((a,b) => a.s - b.s);

    // Build occupied ranges
    const occupied = vetAppts;

    // Find free gaps between FREE_START and FREE_END
    const gaps = [];
    let cursor = FREE_START;
    occupied.forEach(({s, e}) => {
      if(s > cursor) gaps.push({ s: cursor, e: s });
      cursor = Math.max(cursor, e);
    });
    if(cursor < FREE_END) gaps.push({ s: cursor, e: FREE_END });

    // For each gap, find all slots of freeDuration that fit
    gaps.forEach(gap => {
      if(gap.e - gap.s < freeDuration) return;

      // Split into freeDuration-sized slots aligned to 15min
      const slotStep = 15;
      let t = Math.ceil(gap.s / slotStep) * slotStep;
      while(t + freeDuration <= gap.e){
        const slotStart = t;
        const slotEnd = t + freeDuration;
        renderFreeSlotBlock(col, vetKey, vet, dateStr, slotStart, slotEnd);
        t += slotStep;
      }
    });
  });
}

function renderFreeSlotBlock(col, vetKey, vet, dateStr, startMin, endMin){
  const topPx  = (startMin - HOUR_START * 60) / 60 * SLOT_H + 1;
  const heightPx = (endMin - startMin) / 60 * SLOT_H - 2;
  if(heightPx < 10) return;

  // Position: use total active vets to distribute horizontally
  const vetList = [...activeVets];
  const idx = vetList.indexOf(vetKey);
  const total = vetList.length;
  const laneW = 100 / total;
  const leftPct = idx * laneW;

  const el = document.createElement('div');
  el.className = 'free-slot';
  el.style.cssText = `top:${topPx}px;height:${heightPx}px;left:calc(${leftPct}% + 2px);width:calc(${laneW}% - 4px);background:${vet.bg};border:1.5px dashed ${vet.color};opacity:.85;align-items:flex-start;padding:2px 5px;`;

  const timeLabel = `${fmtTime(minToTime(startMin))}`;
  el.innerHTML = `<span style="font-size:var(--text-xs);font-weight:700;color:${vet.color};">${timeLabel}</span>`;

  el.onclick = (e) => {
    e.stopPropagation();
    // Pre-fill new RDV with vet and time
    newRdvSlotDate = dateStr;
    newRdvSlotHour = minToTime(startMin);
    const d = new Date(dateStr);
    const dow = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
    document.getElementById('new-rdv-slot').textContent =
      `${dow[d.getDay()]} ${d.getDate()} ${MONTHS_FR[d.getMonth()]} · ${minToTime(startMin).replace(':','\u00a0h\u00a0')}`;
    document.getElementById('new-rdv-time').value = minToTime(startMin);
    // Pre-select the vet
    const vetSel = document.getElementById('new-rdv-vet');
    if(vetSel) vetSel.value = vetKey;
    resetNewRdvForm();
    showCenteredPopup(document.getElementById('new-rdv-popup'));
  };

  col.appendChild(el);
}

// =============== EVENT LISTENERS (stored for cleanup) ===============
let _resizeHandler = null;
let _inputHandler = null;

// =============== EXPOSE TO WINDOW (for onclick attributes in HTML) ===============
window.closeAgendaSidebar = closeAgendaSidebar;
window.openNewRdvGlobal = openNewRdvGlobal;
window.miniCalPrev = miniCalPrev;
window.miniCalNext = miniCalNext;
window.toggleVet = toggleVet;
window.toggleFreeSlots = toggleFreeSlots;
window.setFreeDur = setFreeDur;
window.toggleAgendaSidebar = toggleAgendaSidebar;
window.prevWeek = prevWeek;
window.goToday = goToday;
window.nextWeek = nextWeek;
window.setView = setView;
window.closeAllPopups = closeAllPopups;
window.openConsultation = openConsultation;
window.editRdv = editRdv;
window.cancelRdv = cancelRdv;
window.setDuration = setDuration;
window.selectMotif = selectMotif;
window.confirmNewRdv = confirmNewRdv;
window.placerSalleAttente = placerSalleAttente;
window.selectAnimal = selectAnimal;
window.searchAnimals = searchAnimals;

// =============== INIT / CLEANUP ===============
export function init() {
  // On mobile, force day view
  if(window.innerWidth <= 640 && currentView === 'week'){
    currentView = 'day';
    currentWeekStart = today;
  }
  renderMiniCal();
  renderWeek();
  renderDayPlanning();
  initMotifFilters();

  _resizeHandler = function(){
    if(window.innerWidth <= 640 && currentView === 'week'){
      currentView = 'day'; currentWeekStart = today; renderWeek();
    }
  };
  window.addEventListener('resize', _resizeHandler);

  // Wire time input dynamically (popup content may not exist at init)
  _inputHandler = function(e){
    if(e.target && e.target.id === 'new-rdv-time') updateEndTime();
  };
  document.addEventListener('input', _inputHandler);
}

export function cleanup() {
  if(_resizeHandler) {
    window.removeEventListener('resize', _resizeHandler);
    _resizeHandler = null;
  }
  if(_inputHandler) {
    document.removeEventListener('input', _inputHandler);
    _inputHandler = null;
  }

  // Clean up window references
  delete window.closeAgendaSidebar;
  delete window.openNewRdvGlobal;
  delete window.miniCalPrev;
  delete window.miniCalNext;
  delete window.toggleVet;
  delete window.toggleFreeSlots;
  delete window.setFreeDur;
  delete window.toggleAgendaSidebar;
  delete window.prevWeek;
  delete window.goToday;
  delete window.nextWeek;
  delete window.setView;
  delete window.closeAllPopups;
  delete window.openConsultation;
  delete window.editRdv;
  delete window.cancelRdv;
  delete window.setDuration;
  delete window.selectMotif;
  delete window.confirmNewRdv;
  delete window.placerSalleAttente;
  delete window.selectAnimal;
  delete window.searchAnimals;
}
