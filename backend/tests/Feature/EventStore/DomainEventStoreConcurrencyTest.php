<?php

declare(strict_types=1);

namespace Tests\Feature\EventStore;

use App\EventStore\DomainEventStore;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Optimistic-lock retry behavior verification for DomainEventStore.
 *
 * The store relies on the (stream_type, stream_id, stream_version) unique
 * constraint and retries on duplicate key violations.
 *
 * NOTE on testability: actually triggering the retry path from a single
 * PHPUnit process is hard — competing writers must commit in a different
 * transaction than the one being retried, otherwise RefreshDatabase /
 * Laravel's nested transaction semantics roll back the injected row along
 * with the failed INSERT, and retry never sees a committed conflict.
 *
 * What this test file covers:
 *   1. Happy path: dense, monotonic stream_version
 *   2. The unique-violation recognizer, directly via reflection — proves
 *      that 23505 / 23000+UNIQUE message are recognized, and that other
 *      23000 violations (NOT NULL, FK) are correctly REJECTED so they don't
 *      get masked by retries.
 *
 * The retry loop itself is verified by inspection plus the production
 * Log::warning emission (see DomainEventStore::append). Real retries are
 * only observed under genuine concurrent load, which CI cannot simulate.
 */
final class DomainEventStoreConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_appends_dense_sequential_versions(): void
    {
        $store = app(DomainEventStore::class);
        $streamId = (string) Str::uuid7();

        for ($i = 0; $i < 10; $i++) {
            $store->append('member', $streamId, 'MemberSkillUpdated', ['i' => $i], []);
        }

        $events = $store->streamOf('member', $streamId);
        $this->assertCount(10, $events);
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($i + 1, $events[$i]->stream_version);
        }
    }

    public function test_is_unique_violation_recognizes_pgsql_23505(): void
    {
        // PostgreSQL gives the dedicated SQLSTATE 23505 for unique violations.
        $exception = $this->makeQueryException(
            sqlState: '23505',
            message: 'SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint "domain_events_stream_type_stream_id_stream_version_unique"',
        );
        $this->assertTrue($this->callIsUniqueViolation($exception));
    }

    public function test_is_unique_violation_recognizes_sqlite_23000_with_unique_message(): void
    {
        // SQLite collapses all integrity violations to 23000 — the message
        // disambiguator is required to safely identify a unique violation.
        $exception = $this->makeQueryException(
            sqlState: '23000',
            message: 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: domain_events.stream_version',
        );
        $this->assertTrue($this->callIsUniqueViolation($exception));
    }

    public function test_is_unique_violation_rejects_sqlite_23000_for_foreign_key(): void
    {
        // CRITICAL: a foreign-key violation also reports SQLSTATE 23000 on
        // SQLite. If this returned true, the retry loop would mask real bugs
        // by retrying 5 times on something that will never become valid.
        $exception = $this->makeQueryException(
            sqlState: '23000',
            message: 'SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed',
        );
        $this->assertFalse($this->callIsUniqueViolation($exception));
    }

    public function test_is_unique_violation_rejects_sqlite_23000_for_not_null(): void
    {
        $exception = $this->makeQueryException(
            sqlState: '23000',
            message: 'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: domain_events.event_type',
        );
        $this->assertFalse($this->callIsUniqueViolation($exception));
    }

    public function test_is_unique_violation_rejects_unrelated_codes(): void
    {
        $checkConstraint = $this->makeQueryException(
            sqlState: '23514',
            message: 'SQLSTATE[23514]: Check violation',
        );
        $syntaxError = $this->makeQueryException(
            sqlState: '42601',
            message: 'SQLSTATE[42601]: Syntax error',
        );
        $deadlock = $this->makeQueryException(
            sqlState: '40P01',
            message: 'SQLSTATE[40P01]: Deadlock detected',
        );

        $this->assertFalse($this->callIsUniqueViolation($checkConstraint));
        $this->assertFalse($this->callIsUniqueViolation($syntaxError));
        $this->assertFalse($this->callIsUniqueViolation($deadlock));
    }

    public function test_is_unique_violation_recognizes_pgsql_message_fallback(): void
    {
        // Some drivers / wrappers may emit QueryException without the
        // SQLSTATE in getCode(). The message contains the canonical pgsql
        // wording.
        $exception = $this->makeQueryException(
            sqlState: '0',
            message: 'duplicate key value violates unique constraint "domain_events_stream_type_stream_id_stream_version_unique"',
        );
        $this->assertTrue($this->callIsUniqueViolation($exception));
    }

    private function callIsUniqueViolation(QueryException $e): bool
    {
        $store = app(DomainEventStore::class);
        $method = new ReflectionMethod($store, 'isUniqueViolation');
        $method->setAccessible(true);

        return (bool) $method->invoke($store, $e);
    }

    private function makeQueryException(string $sqlState, string $message): QueryException
    {
        // Construct a QueryException whose code (= SQLSTATE) and message
        // mirror what real drivers produce.
        $previous = new \RuntimeException($message);
        $exception = new QueryException(
            connectionName: 'test',
            sql: 'INSERT INTO domain_events (...) VALUES (...)',
            bindings: [],
            previous: $previous,
        );

        // QueryException's $code property is read-only via constructor; we
        // override it via reflection to mimic SQLSTATE-aware drivers.
        $rp = new \ReflectionProperty($exception, 'code');
        $rp->setAccessible(true);
        $rp->setValue($exception, $sqlState);

        return $exception;
    }
}
