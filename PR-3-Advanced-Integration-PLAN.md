# PR 3 - Advanced Integration Tests: Implementation Plan

**Status:** In Progress  
**Target Date:** August 20-22, 2026  
**Estimated Tests:** ~50-60 advanced integration tests  
**Scope:** Complex workflows, edge cases, and validation across Admidio entities

---

## 🎯 Strategy Rationale

Services in Admidio have heavy global dependencies ($gCurrentUser, $gCurrentSession, $gL10n, $gSettingsManager, etc.) that make isolated testing challenging. Rather than complex global mocking, PR 3 focuses on:

1. **Realistic Workflows** - Test actual user scenarios that combine multiple entities
2. **Edge Cases** - Test boundary conditions and error handling
3. **Cascading Operations** - Test how operations cascade through related entities
4. **Validation Rules** - Test business rule enforcement across entities

This approach provides comprehensive integration testing coverage while remaining pragmatic about Admidio's architecture.

---

## 📊 Test Coverage Structure

### Category 1: Workflow Scenarios (20 tests)
Test realistic multi-entity workflows that exercise business logic.

**1. Event Management Workflows** (5 tests)
- Create event + assign participants to roles
- Create event with category hierarchy
- Clone event and verify settings copy
- Event with multiple participant roles
- Event removal cascades to participants

**2. Role & Membership Workflows** (5 tests)
- Create role → assign users → manage dates
- Bulk user assignment to multiple roles
- Role hierarchy and permission inheritance
- Role deletion with membership cleanup
- Cross-organization role isolation

**3. Category Management Workflows** (5 tests)
- Create category hierarchy (parent-child)
- Category visibility across organizations
- Category type-specific constraints
- Default category selection logic
- Category reassignment of items

**4. Menu & Navigation Workflows** (5 tests)
- Build menu hierarchy with multiple levels
- Menu visibility based on component status
- Menu ordering and reorganization
- Standard vs. custom menu protection
- Menu component association

---

### Category 2: Edge Cases & Constraints (15 tests)
Test boundary conditions and error handling.

**1. Date & Time Handling** (5 tests)
- Event spanning midnight
- All-day event conversion
- Timezone-aware date operations
- Historical vs. future event validation
- Leap year date handling

**2. Permission Edge Cases** (5 tests)
- Admin override of user permissions
- Delegated admin scope boundaries
- Cross-organization access denial
- Role-based view restrictions
- Object-level permission evaluation

**3. Data Constraints** (5 tests)
- Maximum string length validation
- Numeric range validation
- Required field enforcement
- Unique constraint violation handling
- Foreign key relationship integrity

---

### Category 3: Cascading Operations (10 tests)
Test how operations affect related data.

**1. Deletion Cascades** (5 tests)
- Delete role → cascade to memberships
- Delete user → cascade to owned items
- Delete organization → cascade to scoped data
- Delete category → reassign vs. delete children
- Delete component → reassign visibility

**2. Update Propagation** (5 tests)
- Update role → update all member permissions
- Update category visibility → update item visibility
- Update organization settings → affect all users
- Update user profile → cascade to related records
- Timestamp updates on all touched entities

---

### Category 4: Concurrency & Isolation (8 tests)
Test transaction isolation and concurrent operations.

**1. Transaction Isolation** (4 tests)
- Multiple entities modified in same transaction
- Partial rollback on validation failure
- Nested transaction handling
- Transaction cleanup on exception

**2. Concurrent Operations** (4 tests)
- Simultaneous updates to same entity
- Read during write scenario
- Lock timeouts
- Stale data detection

---

### Category 5: Validation & Error Handling (7 tests)
Test validation across workflows.

**1. Input Validation** (3 tests)
- Invalid date format handling
- Malformed UUID rejection
- Email validation

**2. Business Rule Validation** (4 tests)
- Event start before end validation
- Category type mismatch detection
- Role member count constraints
- Deadline before event start validation

---

## 🏗️ Test Implementation Pattern

### Test File Organization
```
tests/Integration/Workflows/
├── Events/
│   ├── EventWorkflowTest.php        (5 tests)
│   └── EventEdgeCasesTest.php       (3 tests)
├── Roles/
│   ├── RoleWorkflowTest.php         (5 tests)
│   └── RoleCascadeTest.php          (3 tests)
├── Categories/
│   ├── CategoryWorkflowTest.php      (5 tests)
│   └── CategoryHierarchyTest.php     (2 tests)
├── Menu/
│   └── MenuWorkflowTest.php          (5 tests)
├── Concurrency/
│   ├── TransactionIsolationTest.php  (4 tests)
│   └── ConcurrentOperationsTest.php  (4 tests)
└── Validation/
    ├── InputValidationTest.php       (3 tests)
    └── BusinessRuleTest.php          (4 tests)
```

### Test Class Pattern

```php
<?php
namespace Admidio\Tests\Integration\Workflows\Events;

use Admidio\Tests\Support\DatabaseTestCase;

class EventWorkflowTest extends DatabaseTestCase
{
    /**
     * Test creating event with multiple roles
     *
     * @testdox Event creation with multiple participant roles works
     */
    public function testEventWithMultipleParticipantRoles(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Company');
        $category = $builder->createCategory('Events', 'EVT', $org['org_id']);
        
        $leaders = $builder->createRole('Leaders', $org['org_id']);
        $members = $builder->createRole('Members', $org['org_id']);
        
        $user1 = $builder->createUser('leader1', 'leader@company', $org['org_id']);
        $user2 = $builder->createUser('member1', 'member@company', $org['org_id']);
        
        // Act - Create event that both roles can join
        $event = new Event($this->getDatabase());
        $event->setValue('dat_headline', 'Team Meeting');
        $event->setValue('dat_cat_id', $category['cat_id']);
        $event->setValue('dat_begin', '2026-09-01 10:00:00');
        $event->setValue('dat_end', '2026-09-01 11:00:00');
        $event->save();
        
        // Assign participants from different roles
        $leader_role = $builder->createRole('Leaders');
        $member_role = $builder->createRole('Members');
        
        // Assert
        $this->assertNotEmpty($event->getValue('dat_id'));
        // ... additional assertions
    }
}
```

---

## 📋 Success Criteria

- [x] Define advanced test strategy (50-60 tests across 5 categories)
- [x] Create directory structure for workflow tests
- [x] Document realistic workflow patterns
- [ ] Implement all Category 1 workflow tests (20 tests)
- [ ] Implement all Category 2 edge case tests (15 tests)
- [ ] Implement Category 3-5 tests (25 tests)
- [ ] Verify all tests pass
- [ ] Commit with summary
- [ ] Document patterns in tests/README.md

---

## 🎓 Key Testing Patterns

### Workflow Pattern
1. Arrange: Create related entities through TestDataBuilder
2. Act: Perform workflow operation (create, update, delete)
3. Assert: Verify primary and cascading effects

### Edge Case Pattern
1. Arrange: Set up boundary condition data
2. Act: Perform operation at boundary
3. Assert: Verify correct behavior (success or specific failure)

### Cascade Pattern
1. Arrange: Create master and related entities
2. Act: Delete/update master entity
3. Assert: Verify cascading changes to related data

---

## 🔄 Next Steps After PR 3

**PR 4 - CLI Regression Tests** (~60 tests)
- CLI command execution patterns
- JSON output validation
- Exit code verification
- Subprocess communication
- CLI registry contracts

**PR 5 - Lifecycle Tests** (~15 tests)
- Fresh installation
- Database upgrade paths
- Configuration migration
- Backward compatibility

**PR 6 - Optimization & Documentation**
- Performance baselines
- Parallel test optimization
- Final documentation
- CI/CD integration

---

**PR 3 will complete comprehensive integration testing across Admidio's core entities and realistic workflows, preparing for CLI and lifecycle testing in later phases.**
