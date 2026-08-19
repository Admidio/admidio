# PR 2 - Foundation Layer: Implementation Plan

**Status:** Foundation tests created  
**Scope:** Database abstraction, Entity CRUD, Roles, Organizations, Permissions  
**Estimated:** 3 weeks, ~100 tests  

---

## ✅ Tests Created (Phase 1)

### Database Abstraction Tests (12 tests)
- `tests/Integration/Database/DatabaseAbstractionTest.php`
- Validates generic SQL across MySQL, MariaDB, PostgreSQL:
  - Connection and basic queries
  - Boolean value handling
  - UUID/GUID handling
  - LIMIT/OFFSET behavior
  - NULL handling
  - Date/time handling
  - Transactions
  - Foreign keys
  - Auto-increment/SEQUENCE
  - Character encoding (UTF-8)
  - Case sensitivity
  - ORDER BY behavior

### User Entity Tests (10 tests)
- `tests/Integration/Users/UserEntityTest.php`
- CRUD operations:
  - Create user
  - Read user
  - Update user
  - Delete user
  - UUID retrieval and uniqueness
  - Multiple users in organization
  - Email validation
  - Creation timestamps
  - Changelog entries
  - User status/active flag
  - Organization isolation

### Role Entity Tests (10 tests)
- `tests/Integration/Roles/RoleEntityTest.php`
- CRUD and membership:
  - Create role
  - Read role
  - Update role
  - Delete role
  - Assign user to role (membership)
  - Multiple members per role
  - Membership with dates
  - Multiple roles in organization
  - UUID uniqueness
  - Creation timestamps
  - Changelog entries
  - Organization isolation

### Organization Entity Tests (10 tests)
- `tests/Integration/Organizations/OrganizationEntityTest.php`
- Multi-tenancy:
  - Create organization
  - Read organization
  - UUID uniqueness
  - Multiple organizations
  - Creation timestamps
  - Changelog entries
  - User organization scope
  - Role organization scope
  - Data isolation between organizations

**Subtotal: 42 foundation tests implemented**

---

## 📋 Tests Still Needed for PR 2 (Phase 2)

### Permission & Authorization Tests (~20 tests)
- User administrator rights
- Role-based permissions
- Component visibility checks
- Delegated rights
- Cross-organization access denial
- Object-level RolesRights
- Rights inheritance

**File:** `tests/Integration/Permissions/PermissionEntityTest.php`

### Component Entity Tests (~10 tests)
- Component creation
- Component visibility
- Component administration
- Component-specific permissions

**File:** `tests/Integration/Components/ComponentEntityTest.php`

### ProfileField Tests (~10 tests)
- Custom profile fields
- Field type validation
- Field value persistence
- Field visibility per role

**File:** `tests/Integration/ProfileFields/ProfileFieldEntityTest.php`

### Category Tests (~8 tests)
- Category CRUD
- Hierarchical categories
- Category visibility
- Category permissions

**File:** `tests/Integration/Categories/CategoryEntityTest.php`

---

## 🔧 Implementation Instructions

### To Complete PR 2:

1. **Create Permission Tests** (Copy pattern from existing tests)
   - Test global administrator rights
   - Test role-specific permissions
   - Test delegated administration
   - Test cross-organization access denial

2. **Create Component Tests**
   - Test component creation/update/delete
   - Test visibility logic
   - Test administration rights

3. **Create ProfileField Tests**
   - Test custom field creation
   - Test field type validation
   - Test value persistence

4. **Create Category Tests**
   - Test CRUD operations
   - Test hierarchical relationships
   - Test visibility per role

5. **Update TestDataBuilder** (in `tests/Support/TestDataBuilder.php`)
   - Add methods for permission assignment
   - Add methods for component creation
   - Add methods for profile field creation
   - Add methods for category creation

6. **Run Tests Locally**
   ```bash
   # Start Docker services
   docker-compose -f docker-compose.test.yml up -d
   php tests/bin/setup-test-env.php
   
   # Run foundation tests
   composer test:integration
   
   # Stop services
   docker-compose -f docker-compose.test.yml down
   ```

7. **Integration Test Patterns**
   - All tests extend `DatabaseTestCase`
   - Use `$this->getTestDataBuilder()` to create fixtures
   - Each test is isolated via transaction rollback
   - No database cleanup needed between tests

---

## 📊 Expected Test Count

| Component | Tests | Status |
|-----------|-------|--------|
| Database Abstraction | 12 | ✅ Done |
| User Entity | 10 | ✅ Done |
| Role Entity | 10 | ✅ Done |
| Organization Entity | 10 | ✅ Done |
| Permissions | 20 | ⏳ Needed |
| Components | 10 | ⏳ Needed |
| Profile Fields | 10 | ⏳ Needed |
| Categories | 8 | ⏳ Needed |
| **Total PR 2** | **~90-100** | **~42% Done** |

---

## 🎯 Next Steps

### Immediate (To Complete PR 2)

1. ✅ Create remaining test files (Permission, Component, ProfileField, Category)
2. ✅ Enhance TestDataBuilder with new fixture methods
3. ✅ Update example integration tests with real implementation patterns
4. ✅ Run full integration test suite
5. ✅ Verify all databases (MariaDB, PostgreSQL, MySQL)
6. ✅ Commit PR 2

### Timeline

- **Days 1-2:** Create Permission, Component, ProfileField tests
- **Days 3-4:** Create Category tests, enhance TestDataBuilder
- **Days 5-6:** Local testing and verification
- **Days 7-8:** Documentation and cleanup
- **Days 9-10:** Buffer for fixes

### PR 2 Success Criteria

- ✅ 90-100 foundation tests passing
- ✅ All database engines (MySQL, MariaDB, PostgreSQL) validated
- ✅ CRUD operations verified for all core entities
- ✅ Organization isolation verified
- ✅ Transaction isolation verified
- ✅ Changelog entries verified
- ✅ All tests documented
- ✅ Ready for PR 3 (Service Layer)

---

## 💡 Key Patterns for New Tests

All new tests should follow this pattern:

```php
<?php
namespace Admidio\Tests\Integration\<Domain>;

use Admidio\Tests\Support\DatabaseTestCase;

class <Entity>Test extends DatabaseTestCase
{
    public function test<Scenario>(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $entity = $builder->create<Entity>(...);

        // Act
        // (perform operation on entity)

        // Assert
        $this->assert<Condition>($entity);
    }
}
```

---

## 📝 Files to Modify/Create

**Create:**
- `tests/Integration/Permissions/PermissionEntityTest.php`
- `tests/Integration/Components/ComponentEntityTest.php`
- `tests/Integration/ProfileFields/ProfileFieldEntityTest.php`
- `tests/Integration/Categories/CategoryEntityTest.php`

**Modify:**
- `tests/Support/TestDataBuilder.php` - Add new creation methods

**Update:**
- `tests/Integration/ExampleIntegrationTest.php` - Replace with real patterns
- `PR-2-Foundation-IMPLEMENTATION.md` - Final summary

---

## 🚀 Commands for Local Development

```bash
# Setup
docker-compose -f docker-compose.test.yml up -d
cp .env.test.example .env.test
php tests/bin/setup-test-env.php

# Run PR 2 tests
composer test:integration

# Run against PostgreSQL
composer test:integration --db=postgres

# Run just one test file
./vendor/bin/phpunit tests/Integration/Database/DatabaseAbstractionTest.php

# Run with verbose output
composer test:integration -- --verbose

# Cleanup
docker-compose -f docker-compose.test.yml down
```

---

## ✨ Expected Outcome

After completing PR 2:
- ✅ ~100 regression tests protecting core infrastructure
- ✅ Database abstraction validated across 3 engines
- ✅ All CRUD operations verified
- ✅ Entity relationships validated
- ✅ Multi-tenancy isolation verified
- ✅ Ready for PR 3 - Service Layer tests

---

**Status: Ready for Phase 2 - Remaining 58-65 tests**

The foundation is in place. Remaining tests follow the same pattern as the 42 tests already created.
