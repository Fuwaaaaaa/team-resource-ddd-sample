<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Authorization\Events\UserCreated;
use App\Domain\Authorization\Events\UserPasswordReset;
use App\Domain\Authorization\Events\UserRoleChanged;
use App\Enums\UserRole;
use App\EventStore\EventDescriptorResolver;
use ReflectionClass;
use Tests\TestCase;

/**
 * Schema-level safety nets for the Authorization domain events.
 *
 * Hard rule: payload schemas MUST NOT contain a password field, in either the
 * domain_events stream (EventDescriptorResolver) or the audit_logs view
 * (RecordAuditLog::buildRecord). This test asserts both directly against
 * resolved descriptors AND grep-style against the source files.
 */
final class DomainEventTest extends TestCase
{
    public function test_user_created_descriptor_has_no_password_field(): void
    {
        $resolver = new EventDescriptorResolver;
        $descriptor = $resolver->resolve(new UserCreated(
            userId: 42,
            email: 'safe@example.com',
            role: UserRole::Manager,
        ));

        $this->assertNotNull($descriptor);
        $this->assertSame('user', $descriptor->streamType);
        $this->assertSame('UserCreated', $descriptor->eventType);
        $this->assertArrayNotHasKey('password', $descriptor->eventData);
        $this->assertArrayNotHasKey('generatedPassword', $descriptor->eventData);
    }

    public function test_user_role_changed_descriptor_carries_reason(): void
    {
        $resolver = new EventDescriptorResolver;
        $descriptor = $resolver->resolve(new UserRoleChanged(
            userId: 1,
            from: UserRole::Manager,
            to: UserRole::Viewer,
            reason: 'demotion test',
        ));

        $this->assertNotNull($descriptor);
        $this->assertSame('UserRoleChanged', $descriptor->eventType);
        $this->assertSame('manager', $descriptor->eventData['from']);
        $this->assertSame('viewer', $descriptor->eventData['to']);
        $this->assertSame('demotion test', $descriptor->eventData['reason']);
    }

    public function test_user_password_reset_descriptor_payload_has_no_password(): void
    {
        $resolver = new EventDescriptorResolver;
        $descriptor = $resolver->resolve(new UserPasswordReset(userId: 7));

        $this->assertNotNull($descriptor);
        $this->assertSame('UserPasswordReset', $descriptor->eventType);
        $this->assertArrayNotHasKey('password', $descriptor->eventData);
        $this->assertArrayNotHasKey('hash', $descriptor->eventData);
    }

    public function test_authorization_event_classes_have_no_password_property(): void
    {
        // Grep guard: the event POPOs themselves must not declare a password field.
        foreach ([UserCreated::class, UserRoleChanged::class, UserPasswordReset::class] as $class) {
            $rc = new ReflectionClass($class);
            foreach ($rc->getProperties() as $property) {
                $name = strtolower($property->getName());
                $this->assertStringNotContainsString('password', $name, "Forbidden field on {$class}");
                $this->assertStringNotContainsString('passwd', $name, "Forbidden field on {$class}");
            }
        }
    }
}
