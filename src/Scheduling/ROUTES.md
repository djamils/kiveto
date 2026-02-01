# Routes Scheduling - Référence Complète

## 🌐 Routes Publiques (Clinic)

### Dashboard & Vues

| Route | Method | Controller | Description | Params |
|-------|--------|-----------|-------------|---------|
| `clinic_scheduling_dashboard` | GET | DashboardController | Vue principale agenda + waiting room | `?date=YYYY-MM-DD` |

### Actions Appointment

| Route | Method | Controller | Description | Body |
|-------|--------|-----------|-------------|------|
| `clinic_scheduling_appointment_create` | POST | CreateAppointmentController | Créer un RDV | `startsAtUtc`, `durationMinutes`, `practitionerUserId?`, `ownerId?`, `animalId?`, `reason?`, `notes?` |
| `clinic_scheduling_appointment_checkin` | POST | CheckInAppointmentController | Check-in d'un RDV | `arrivalMode?=STANDARD`, `priority?=0` |

### Actions Waiting Room

| Route | Method | Controller | Description | Body |
|-------|--------|-----------|-------------|------|
| `clinic_scheduling_walkin_create` | POST | CreateWalkInController | Créer une urgence walk-in | `ownerId?`, `animalId?`, `foundAnimalDescription?`, `arrivalMode=EMERGENCY`, `priority=10`, `triageNotes` |
| `clinic_scheduling_waitingroom_start` | POST | StartServiceController | Démarrer service pour entrée | - |
| `clinic_scheduling_waitingroom_close` | POST | CloseWaitingRoomEntryController | Fermer une entrée | - |

---

## 🔐 Permissions Recommandées

| Route | Rôle Minimum | Notes |
|-------|-------------|-------|
| `clinic_scheduling_dashboard` | ROLE_ASSISTANT_VETERINARY | Lecture seule |
| `clinic_scheduling_appointment_create` | ROLE_ASSISTANT_VETERINARY | Création RDV |
| `clinic_scheduling_appointment_checkin` | ROLE_ASSISTANT_VETERINARY | Check-in |
| `clinic_scheduling_walkin_create` | ROLE_ASSISTANT_VETERINARY | Urgences |
| `clinic_scheduling_waitingroom_start` | ROLE_VETERINARY | Praticiens uniquement |
| `clinic_scheduling_waitingroom_close` | ROLE_VETERINARY | Praticiens uniquement |

---

## 📋 Exemples d'Utilisation

### Créer un RDV (CURL)

```bash
curl -X POST http://clinic.kiveto.local/scheduling/appointments/create \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "startsAtUtc=2026-02-15T14:00:00" \
  -d "durationMinutes=30" \
  -d "practitionerUserId=01234567-89ab-cdef-0123-456789abcdef" \
  -d "ownerId=owner-uuid" \
  -d "animalId=animal-uuid" \
  -d "reason=Consultation" \
  -d "notes=Première visite"
```

### Check-in (CURL)

```bash
curl -X POST http://clinic.kiveto.local/scheduling/appointments/{appointmentId}/check-in \
  -d "arrivalMode=STANDARD" \
  -d "priority=0"
```

### Créer Urgence Walk-in (CURL)

```bash
curl -X POST http://clinic.kiveto.local/scheduling/waiting-room/walk-in \
  -d "arrivalMode=EMERGENCY" \
  -d "priority=10" \
  -d "triageNotes=Saignement abondant, état critique" \
  -d "foundAnimalDescription=Chat noir, blessure patte avant"
```

---

## 🧪 Tests avec Postman

### Collection JSON

```json
{
  "info": {
    "name": "Scheduling API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Create Appointment",
      "request": {
        "method": "POST",
        "url": "http://clinic.kiveto.local/scheduling/appointments/create",
        "body": {
          "mode": "urlencoded",
          "urlencoded": [
            {"key": "startsAtUtc", "value": "2026-02-15T14:00:00"},
            {"key": "durationMinutes", "value": "30"},
            {"key": "reason", "value": "Consultation"}
          ]
        }
      }
    },
    {
      "name": "Check-in Appointment",
      "request": {
        "method": "POST",
        "url": "http://clinic.kiveto.local/scheduling/appointments/:appointmentId/check-in",
        "body": {
          "mode": "urlencoded",
          "urlencoded": [
            {"key": "arrivalMode", "value": "STANDARD"},
            {"key": "priority", "value": "0"}
          ]
        }
      }
    },
    {
      "name": "Create Walk-in",
      "request": {
        "method": "POST",
        "url": "http://clinic.kiveto.local/scheduling/waiting-room/walk-in",
        "body": {
          "mode": "urlencoded",
          "urlencoded": [
            {"key": "arrivalMode", "value": "EMERGENCY"},
            {"key": "priority", "value": "10"},
            {"key": "triageNotes", "value": "Urgence vitale"}
          ]
        }
      }
    },
    {
      "name": "Start Service",
      "request": {
        "method": "POST",
        "url": "http://clinic.kiveto.local/scheduling/waiting-room/:entryId/start-service"
      }
    },
    {
      "name": "Close Entry",
      "request": {
        "method": "POST",
        "url": "http://clinic.kiveto.local/scheduling/waiting-room/:entryId/close"
      }
    }
  ]
}
```

---

## 🔄 Redirections

Toutes les routes POST redirigent vers `clinic_scheduling_dashboard` après succès ou erreur (avec flash messages).

---

*Documentation générée le 1er février 2026*
