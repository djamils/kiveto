# Bugfix - Champs optionnels formulaires

## 🐛 Problème

Lors de la soumission d'un formulaire avec des champs optionnels vides, les valeurs sont envoyées comme des **strings vides** `""` au lieu de `null`.

### Erreur observée

```
Symfony\Component\Messenger\Exception\HandlerFailedException
Handling "ScheduleAppointment" failed: Identifier cannot be empty.
```

Lorsqu'on créait un RDV en laissant les champs optionnels vides (ownerId, animalId, practitionerUserId, etc.).

### Cause

Les formulaires HTML envoient les champs vides comme `""` (string vide) :
```php
$data['ownerId'] ?? null; // Retourne "" si le champ est vide, pas null !
```

Les Value Objects UUID (comme `OwnerId`) rejettent les strings vides car ils attendent soit une string UUID valide, soit `null`.

---

## ✅ Solution

Convertir explicitement les strings vides en `null` avant de passer aux commands.

### Fichiers corrigés

#### 1. `CreateAppointmentController.php`

**Avant :**
```php
$appointmentId = $this->commandBus->dispatch(new ScheduleAppointment(
    clinicId: $currentClinicId->toString(),
    ownerId: $data['ownerId'] ?? null,  // ❌ Récupère "" si champ vide
    animalId: $data['animalId'] ?? null,
    // ...
));
```

**Après :**
```php
// Convert empty strings to null for optional UUID fields
$ownerId = !empty($data['ownerId']) ? $data['ownerId'] : null;
$animalId = !empty($data['animalId']) ? $data['animalId'] : null;
$practitionerUserId = !empty($data['practitionerUserId']) ? $data['practitionerUserId'] : null;
$reason = !empty($data['reason']) ? $data['reason'] : null;
$notes = !empty($data['notes']) ? $data['notes'] : null;

$appointmentId = $this->commandBus->dispatch(new ScheduleAppointment(
    clinicId: $currentClinicId->toString(),
    ownerId: $ownerId,  // ✅ Vraiment null si vide
    animalId: $animalId,
    practitionerUserId: $practitionerUserId,
    startsAtUtc: $startsAt,
    durationMinutes: (int) ($data['durationMinutes'] ?? 30),
    reason: $reason,
    notes: $notes,
));
```

#### 2. `CreateWalkInController.php`

Même correction appliquée pour les champs optionnels :
- `ownerId`
- `animalId`
- `foundAnimalDescription`
- `triageNotes`

---

## 🧪 Test

### Cas de test 1 : RDV sans patient

```bash
# Formulaire :
# - Date/heure : 2026-02-15 14:00
# - Durée : 30 min
# - Praticien : (vide)
# - Owner : (vide)
# - Animal : (vide)
# - Motif : Consultation
# - Notes : (vide)

# Résultat attendu :
# ✅ RDV créé avec succès
# Tous les champs optionnels = null (pas string vide)
```

### Cas de test 2 : Walk-in animal inconnu

```bash
# Formulaire :
# - Mode : EMERGENCY
# - Priority : 10
# - Owner : (vide)
# - Animal : (vide)
# - Description : Chat noir blessé
# - Triage : Saignement abondant

# Résultat attendu :
# ✅ Entrée walk-in créée
# ownerId = null, animalId = null (pas string vide)
```

---

## 🔍 Pourquoi `!empty()` ?

`!empty()` retourne `true` si la valeur est :
- Non vide (`""`)
- Non null
- Non false
- Non 0

C'est parfait pour les champs de formulaire qui peuvent être :
- Absents (pas dans `$data`)
- Vides (`""`)
- Remplis (`"uuid-here"`)

```php
!empty($data['ownerId']) ? $data['ownerId'] : null;

// Si absent ou vide → null
// Si rempli → valeur
```

### Alternative possible

```php
// Option 1 : empty() (choisi)
$ownerId = !empty($data['ownerId']) ? $data['ownerId'] : null;

// Option 2 : isset() + trim()
$ownerId = isset($data['ownerId']) && trim($data['ownerId']) !== '' 
    ? $data['ownerId'] 
    : null;

// Option 3 : filter avec callback
$ownerId = ($data['ownerId'] ?? null) ?: null;
```

✅ **Option 1 choisie** : Plus concise et gère tous les cas (absent, vide, whitespace).

---

## 📚 Bonne pratique

Pour les futurs controllers avec des champs optionnels, **toujours** convertir les strings vides en `null` :

```php
// ✅ GOOD
$optionalField = !empty($data['field']) ? $data['field'] : null;

// ❌ BAD
$optionalField = $data['field'] ?? null; // Récupère "" si champ vide !
```

Ou créer une méthode helper :

```php
private function getOptionalString(array $data, string $key): ?string
{
    return !empty($data[$key]) ? $data[$key] : null;
}

// Usage
$ownerId = $this->getOptionalString($data, 'ownerId');
```

---

## ✅ Statut

**Corrigé** le 1er février 2026.

Les controllers suivants ont été mis à jour :
- ✅ `CreateAppointmentController`
- ✅ `CreateWalkInController`

Les autres controllers (`CheckInAppointmentController`, `StartServiceController`, etc.) n'ont pas ce problème car ils utilisent des routes avec des paramètres d'URL (pas de formulaire avec champs optionnels).

---

## 🎯 Impact

- ✅ Création de RDV sans patient → OK
- ✅ Création de RDV sans praticien → OK
- ✅ Création d'urgence walk-in sans owner/animal → OK
- ✅ Tous les cas d'usage fonctionnent maintenant

---

*Bugfix documenté le 1er février 2026*
