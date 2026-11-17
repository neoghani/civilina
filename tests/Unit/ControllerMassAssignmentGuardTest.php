<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Several normative models use `$guarded = []` because only internal
 * services write them (a non-empty guard list would make Eloquent read the
 * table schema on every fill). This guard keeps raw request payloads out of
 * controllers, so those models can never be mass-assigned from user input:
 * controllers must pass validated data only.
 */
class ControllerMassAssignmentGuardTest extends TestCase
{
    public function test_controllers_do_not_forward_raw_request_payloads(): void
    {
        $root = dirname(__DIR__, 2).'/app/Http/Controllers';
        $pattern = '/(\$request|request\(\))\s*->\s*(?:(?:all|post|input|json)\s*\(\s*\)|except\s*\()/';
        $offenders = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            foreach (file($file->getPathname()) as $number => $line) {
                if (preg_match($pattern, $line)) {
                    $offenders[] = $file->getFilename().':'.($number + 1).' '.trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, "Raw request payload used in a controller:\n".implode("\n", $offenders));
    }
}
