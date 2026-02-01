# Bugfix - Doctrine DBAL ArrayParameterType

## 🐛 Problème

```
Undefined constant Doctrine\DBAL\Connection::PARAM_STR_ARRAY
```

Lors du check-in d'un rendez-vous, l'application crashe avec cette erreur.

### Erreur observée

```
Error in DoctrineWaitingRoomReadRepository.php (line 39)
Undefined constant Doctrine\DBAL\Connection::PARAM_STR_ARRAY
```

### Cause

**Doctrine DBAL v3+** a supprimé les constantes `Connection::PARAM_*_ARRAY`.

Elles ont été remplacées par des enums :
- ❌ `Connection::PARAM_STR_ARRAY` (obsolète)
- ✅ `ArrayParameterType::STRING` (nouveau)

---

## ✅ Solution

Remplacer `Connection::PARAM_STR_ARRAY` par `ArrayParameterType::STRING`.

### Fichiers corrigés

#### 1. `DoctrineWaitingRoomReadRepository.php`

**Avant :**
```php
use Doctrine\DBAL\Connection;

$result = $this->connection->fetchAssociative($sql, [
    // params...
], [
    'activeStatuses' => Connection::PARAM_STR_ARRAY,  // ❌ N'existe plus
]);
```

**Après :**
```php
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

$result = $this->connection->fetchAssociative($sql, [
    // params...
], [
    'activeStatuses' => ArrayParameterType::STRING,  // ✅ Nouveau
]);
```

#### 2. `DbalMembershipEligibilityChecker.php`

Même correction appliquée dans 2 méthodes :
- `isUserEligibleForClinicAt()` : ligne 42
- `listEligiblePractitionerUsersForClinic()` : ligne 68

---

## 📖 Documentation Doctrine DBAL v3

### Avant (v2)

```php
use Doctrine\DBAL\Connection;

// Array de strings
['param' => Connection::PARAM_STR_ARRAY]

// Array d'integers
['param' => Connection::PARAM_INT_ARRAY]
```

### Après (v3+)

```php
use Doctrine\DBAL\ArrayParameterType;

// Array de strings
['param' => ArrayParameterType::STRING]

// Array d'integers
['param' => ArrayParameterType::INTEGER]
```

### Types disponibles

```php
enum ArrayParameterType
{
    case STRING;    // Pour array de strings
    case INTEGER;   // Pour array d'integers
    case BINARY;    // Pour array de binaires
    case ASCII;     // Pour array de strings ASCII
}
```

---

## 🔍 Pourquoi ce changement ?

### Avantages de `ArrayParameterType` (enum)

1. **Type safety** : Enum PHP 8.1+ au lieu de constantes magiques
2. **Autocomplete** : Meilleur support IDE
3. **Impossible de se tromper** : `ArrayParameterType::` affiche toutes les options
4. **Cohérence** : Suit les standards PHP modernes

### Migration Doctrine DBAL v2 → v3

C'est un **breaking change** de Doctrine DBAL v3 :

```
DBAL v2 (ancien)          →  DBAL v3+ (nouveau)
=====================================================
Connection::PARAM_STR_ARRAY  →  ArrayParameterType::STRING
Connection::PARAM_INT_ARRAY  →  ArrayParameterType::INTEGER
Connection::PARAM_NULL       →  Supprimé (utiliser null)
Connection::PARAM_STR        →  ParameterType::STRING
Connection::PARAM_INT        →  ParameterType::INTEGER
```

---

## 🧪 Test

### Cas de test : Check-in d'un RDV

1. Créer un RDV via l'UI
2. Cliquer sur "Check-in"
3. **Avant** : Crash avec `PARAM_STR_ARRAY` undefined
4. **Après** : ✅ Check-in réussi, entrée dans waiting room créée

### Requête SQL générée

```sql
SELECT COUNT(*) as cnt
FROM scheduling__waiting_room_entries
WHERE clinic_id = ?
  AND linked_appointment_id = ?
  AND status IN (?, ?, ?)  -- Array expandé correctement
```

Les valeurs `['WAITING', 'CALLED', 'IN_SERVICE']` sont correctement expandées grâce à `ArrayParameterType::STRING`.

---

## 📋 Checklist de migration DBAL v3

Pour les futurs adapters DBAL, utiliser :

### ✅ Pour les arrays

```php
use Doctrine\DBAL\ArrayParameterType;

// Array de strings
$connection->fetchAssociative($sql, $params, [
    'myArrayParam' => ArrayParameterType::STRING,
]);

// Array d'integers
$connection->fetchAssociative($sql, $params, [
    'myIntArrayParam' => ArrayParameterType::INTEGER,
]);
```

### ✅ Pour les types simples (optionnel)

```php
use Doctrine\DBAL\ParameterType;

// String simple (généralement auto-détecté)
$connection->fetchAssociative($sql, $params, [
    'myParam' => ParameterType::STRING,
]);

// Integer simple
$connection->fetchAssociative($sql, $params, [
    'myIntParam' => ParameterType::INTEGER,
]);
```

### ⚠️ Note importante

Pour les types simples (string, int), Doctrine DBAL les **auto-détecte** généralement, donc pas besoin de spécifier le type. 

Pour les **arrays**, c'est **obligatoire** de spécifier `ArrayParameterType::*`.

---

## 🔍 Vérification globale

Pour vérifier qu'il n'y a plus d'anciennes constantes DBAL dans le projet :

```bash
# Rechercher les anciennes constantes
grep -r "Connection::PARAM_" src/

# Devrait retourner 0 résultat
```

---

## ✅ Statut

**Corrigé** le 1er février 2026.

Les fichiers suivants ont été mis à jour :
- ✅ `DoctrineWaitingRoomReadRepository.php`
- ✅ `DbalMembershipEligibilityChecker.php`

Tous les adapters DBAL du module Scheduling utilisent maintenant les enums Doctrine DBAL v3+.

---

## 📚 Références

- [Doctrine DBAL 3.0 Upgrade Guide](https://github.com/doctrine/dbal/blob/3.0.x/UPGRADE.md)
- [ArrayParameterType Documentation](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/types.html#array-types)

---

*Bugfix documenté le 1er février 2026*
