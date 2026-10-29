<?php
/**
 * StudentHub Test Assertion & Runner Framework
 * Lightweight, zero-dependency testing harness for integration tests.
 */
require_once __DIR__ . '/test_config.php';

class IntegrationTestCase {
    protected PDO $pdo;
    protected int $assertionsCount = 0;
    protected array $failures = [];
    protected array $createdUserIds = [];
    protected array $createdStudentIds = [];
    protected array $createdEventIds = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getAssertionsCount(): int {
        return $this->assertionsCount;
    }

    public function assertTrue(bool $condition, string $message = ''): void {
        $this->assertionsCount++;
        if (!$condition) {
            $msg = $message ?: 'Failed asserting that condition is true.';
            throw new Exception($msg);
        }
    }

    public function assertFalse(bool $condition, string $message = ''): void {
        $this->assertionsCount++;
        if ($condition) {
            $msg = $message ?: 'Failed asserting that condition is false.';
            throw new Exception($msg);
        }
    }

    public function assertEquals($expected, $actual, string $message = ''): void {
        $this->assertionsCount++;
        if ($expected != $actual) {
            $expStr = is_scalar($expected) ? var_export($expected, true) : json_encode($expected);
            $actStr = is_scalar($actual) ? var_export($actual, true) : json_encode($actual);
            $msg = $message ?: "Failed asserting that {$actStr} equals expected {$expStr}.";
            throw new Exception($msg);
        }
    }

    public function assertSame($expected, $actual, string $message = ''): void {
        $this->assertionsCount++;
        if ($expected !== $actual) {
            $expStr = var_export($expected, true);
            $actStr = var_export($actual, true);
            $msg = $message ?: "Failed asserting that {$actStr} is identical to {$expStr}.";
            throw new Exception($msg);
        }
    }

    public function assertNotNull($actual, string $message = ''): void {
        $this->assertionsCount++;
        if ($actual === null) {
            throw new Exception($message ?: 'Failed asserting that value is not null.');
        }
    }

    public function assertNull($actual, string $message = ''): void {
        $this->assertionsCount++;
        if ($actual !== null) {
            throw new Exception($message ?: 'Failed asserting that value is null.');
        }
    }

    public function assertGreaterThan(int $expected, int $actual, string $message = ''): void {
        $this->assertionsCount++;
        if ($actual <= $expected) {
            throw new Exception($message ?: "Failed asserting that {$actual} is greater than {$expected}.");
        }
    }

    public function assertStringContains(string $needle, string $haystack, string $message = ''): void {
        $this->assertionsCount++;
        if (strpos($haystack, $needle) === false) {
            throw new Exception($message ?: "Failed asserting that '{$haystack}' contains '{$needle}'.");
        }
    }

    public function assertRegExp(string $pattern, string $string, string $message = ''): void {
        $this->assertionsCount++;
        if (!preg_match($pattern, $string)) {
            throw new Exception($message ?: "Failed asserting that '{$string}' matches pattern '{$pattern}'.");
        }
    }

    public function trackUser(int $id): void {
        $this->createdUserIds[] = $id;
    }

    public function trackStudent(int $id): void {
        $this->createdStudentIds[] = $id;
    }

    public function trackEvent(int $id): void {
        $this->createdEventIds[] = $id;
    }

    /**
     * Clean up test artifacts created during tests
     */
    public function cleanUp(): void {
        try {
            $pdo = getTestPdo(true);
            if (!empty($this->createdUserIds)) {
                $in = implode(',', array_map('intval', $this->createdUserIds));
                $pdo->exec("DELETE FROM users WHERE id IN ({$in})");
                $this->createdUserIds = [];
            }

            if (!empty($this->createdStudentIds)) {
                $in = implode(',', array_map('intval', $this->createdStudentIds));
                $pdo->exec("DELETE FROM students WHERE student_id IN ({$in})");
                $this->createdStudentIds = [];
            }

            if (!empty($this->createdEventIds)) {
                $in = implode(',', array_map('intval', $this->createdEventIds));
                $pdo->exec("DELETE FROM events WHERE event_id IN ({$in})");
                $this->createdEventIds = [];
            }
        } catch (Throwable $e) {
            // Silently ignore cleanup issues
        }
    }
}
