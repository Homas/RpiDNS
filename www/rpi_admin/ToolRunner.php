<?php
/**
 * ToolRunner - Executes Research network-tool argument vectors safely.
 *
 * (c) Vadim Pavlov 2020 - 2026
 *
 * ToolRunner takes an argument vector (argv) produced by {@see CommandBuilder}
 * and executes it via `proc_open()` WITHOUT a shell (the argv array form bypasses
 * the shell entirely), so tool inputs remain discrete arguments and cannot alter
 * the command structure (defense-in-depth alongside CommandBuilder; Req 6.6).
 *
 * Design guarantees (see .kiro/specs/research-tools/design.md):
 *  - A 30s wall-clock bound is enforced via a non-blocking read loop driven by
 *    `stream_select()`. On timeout the child's whole process group is killed so
 *    no orphaned children linger, and a ToolResult with reason='timeout' is
 *    returned (Req 6.7).
 *  - Output is captured and truncated to a configured maximum size; the
 *    `truncated` flag is true if and only if the raw output exceeded the maximum
 *    (Req 6.2, Property 10).
 *  - A non-zero exit is surfaced via `exitError` (Req 6.12), and a failure to
 *    start the utility returns reason='tool_start_failed' (Req 6.9).
 *  - ToolRunner NEVER modifies database or system state: it only reads a child
 *    process's output streams and terminates the process on timeout (Req 9.3).
 *
 * Returned ToolResult shape (associative array):
 *   [
 *     'tool'      => string,       // logical tool name (caller-supplied)
 *     'target'    => string,       // the target the tool ran against
 *     'output'    => string,       // captured text, truncated to the max size
 *     'truncated' => bool,         // true iff raw output exceeded the max size
 *     'exitError' => bool,         // true iff the utility exited non-zero / failed / timed out
 *     'reason'    => string|null,  // 'timeout' | 'tool_start_failed' | null
 *   ]
 */
class ToolRunner {
    /** Default wall-clock execution bound in seconds (Req 6.7). */
    const DEFAULT_TIMEOUT_SEC = 30;

    /** Default maximum captured output size in bytes (Req 6.2). */
    const DEFAULT_MAX_OUTPUT_BYTES = 1048576; // 1 MiB

    /** Reason returned when the utility could not be started (Req 6.9). */
    const REASON_START_FAILED = 'tool_start_failed';

    /** Reason returned when the wall-clock bound was exceeded (Req 6.7). */
    const REASON_TIMEOUT = 'timeout';

    /** POSIX signal numbers used when terminating a timed-out process group. */
    const SIG_TERM = 15;
    const SIG_KILL = 9;

    /** Grace period (microseconds) between SIGTERM and SIGKILL on timeout. */
    const KILL_GRACE_USEC = 500000; // 0.5s

    /** @var int Wall-clock execution bound in seconds. */
    private $timeoutSec;

    /** @var int Maximum captured output size in bytes. */
    private $maxOutputBytes;

    /**
     * @param int $timeoutSec     Wall-clock execution bound in seconds (> 0).
     * @param int $maxOutputBytes Maximum captured output size in bytes (> 0).
     */
    public function __construct(
        int $timeoutSec = self::DEFAULT_TIMEOUT_SEC,
        int $maxOutputBytes = self::DEFAULT_MAX_OUTPUT_BYTES
    ) {
        $this->timeoutSec = ($timeoutSec > 0) ? $timeoutSec : self::DEFAULT_TIMEOUT_SEC;
        $this->maxOutputBytes = ($maxOutputBytes > 0) ? $maxOutputBytes : self::DEFAULT_MAX_OUTPUT_BYTES;
    }

    /** @return int The configured wall-clock bound in seconds. */
    public function getTimeoutSec(): int {
        return $this->timeoutSec;
    }

    /** @return int The configured maximum output size in bytes. */
    public function getMaxOutputBytes(): int {
        return $this->maxOutputBytes;
    }

    /**
     * Execute a single argument vector and return a ToolResult.
     *
     * @param string             $tool   Logical tool name (for the result envelope).
     * @param string             $target Target the tool runs against (for the result envelope).
     * @param array<int, string> $argv   Argument vector produced by CommandBuilder.
     * @return array ToolResult associative array (see class docblock).
     */
    public function run(string $tool, string $target, array $argv): array {
        // A well-formed argv must have at least the executable name.
        if (count($argv) === 0) {
            return $this->startFailure($tool, $target);
        }

        // Re-index to a flat 0-based list of strings; proc_open's array form
        // requires a sequential list and executes WITHOUT a shell.
        $command = array_values(array_map('strval', $argv));

        $descriptors = [
            0 => ['pipe', 'r'], // child stdin  (closed immediately -> EOF)
            1 => ['pipe', 'w'], // child stdout
            2 => ['pipe', 'w'], // child stderr
        ];
        $pipes = [];

        // bypass_shell is a no-op for the array form on POSIX but is explicit and
        // also correct on Windows; it guarantees no shell interpretation.
        $process = @proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);

        if (!is_resource($process)) {
            return $this->startFailure($tool, $target);
        }

        // Isolate the child in its own process group so that, on timeout, we can
        // kill the entire group (child + any grandchildren) without affecting the
        // PHP process. posix_setpgid() from the parent works while the child still
        // shares our session, which is the case here. Best-effort: if it fails we
        // fall back to terminating just the direct child.
        $status = proc_get_status($process);
        $pid = ($status !== false && isset($status['pid'])) ? (int)$status['pid'] : 0;
        $ownGroup = false;
        if ($pid > 0 && function_exists('posix_setpgid')) {
            $ownGroup = @posix_setpgid($pid, $pid);
        }

        // We do not send anything to the child; closing stdin yields EOF to tools
        // that would otherwise block waiting for input.
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';       // captured output, capped at maxOutputBytes
        $rawBytes = 0;      // total bytes read (used to compute the truncated flag)
        $timedOut = false;
        $deadline = microtime(true) + $this->timeoutSec;

        while (true) {
            $open = [];
            if (is_resource($pipes[1]) && !feof($pipes[1])) {
                $open[] = $pipes[1];
            }
            if (is_resource($pipes[2]) && !feof($pipes[2])) {
                $open[] = $pipes[2];
            }

            $procStatus = proc_get_status($process);
            $running = ($procStatus !== false) && $procStatus['running'];

            // Exit the loop once the process has finished AND both pipes are drained.
            if (empty($open) && !$running) {
                break;
            }

            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                $timedOut = true;
                break;
            }

            if (!empty($open)) {
                $read = $open;
                $write = null;
                $except = null;
                // Cap the select wait so the wall-clock deadline is honored even
                // when the child produces no output.
                $waitSec = (int)min(1, max(0, floor($remaining)));
                $waitUsec = ($waitSec === 0) ? 200000 : 0; // 200ms slice when sub-second
                $ready = @stream_select($read, $write, $except, $waitSec, $waitUsec);

                if ($ready === false) {
                    // Interrupted system call or error; re-evaluate on next loop.
                    continue;
                }

                foreach ($read as $stream) {
                    $chunk = fread($stream, 8192);
                    if ($chunk === '' || $chunk === false) {
                        continue;
                    }
                    $rawBytes += strlen($chunk);
                    // Append only up to the configured maximum; keep draining the
                    // rest so the child never blocks on a full pipe buffer.
                    $capacity = $this->maxOutputBytes - strlen($output);
                    if ($capacity > 0) {
                        $output .= substr($chunk, 0, $capacity);
                    }
                }
            } else {
                // Process still running but pipes reported EOF; brief pause to
                // avoid a busy-loop while waiting for the process to reap.
                usleep(10000); // 10ms
            }
        }

        if ($timedOut) {
            $this->terminate($process, $pid, $ownGroup);
        }

        // Drain any residual buffered output that arrived before termination/exit.
        foreach ([1, 2] as $i) {
            if (isset($pipes[$i]) && is_resource($pipes[$i])) {
                while (($chunk = fread($pipes[$i], 8192)) !== '' && $chunk !== false) {
                    $rawBytes += strlen($chunk);
                    $capacity = $this->maxOutputBytes - strlen($output);
                    if ($capacity > 0) {
                        $output .= substr($chunk, 0, $capacity);
                    }
                }
                fclose($pipes[$i]);
            }
        }

        // Capture the exit code. proc_get_status caches the exit code the first
        // time it observes the process as no longer running; proc_close then
        // returns -1, so prefer the cached value when available.
        $exitCode = -1;
        $finalStatus = proc_get_status($process);
        if ($finalStatus !== false && $finalStatus['running'] === false && $finalStatus['exitcode'] >= 0) {
            $exitCode = (int)$finalStatus['exitcode'];
        }
        $closeCode = proc_close($process);
        if ($exitCode < 0 && $closeCode >= 0) {
            $exitCode = $closeCode;
        }

        if ($timedOut) {
            return $this->result($tool, $target, $output, $rawBytes, true, self::REASON_TIMEOUT);
        }

        $exitError = ($exitCode !== 0);
        return $this->result($tool, $target, $output, $rawBytes, $exitError, null);
    }

    /**
     * Execute several argument vectors (e.g. the NS + MX commands of the NS/MX
     * tool, or one command per bulk item) under a single shared wall-clock and
     * output budget, concatenating their outputs into one ToolResult.
     *
     * The remaining wall-clock budget is divided so the aggregate invocation
     * still self-terminates within the configured bound. If any sub-command
     * times out or exits non-zero, the aggregate result reflects it.
     *
     * @param string                   $tool     Logical tool name.
     * @param string                   $target   Target for the result envelope.
     * @param array<int, array<int,string>> $argvList List of argv arrays.
     * @return array ToolResult associative array.
     */
    public function runMany(string $tool, string $target, array $argvList): array {
        $argvList = array_values($argvList);
        if (count($argvList) === 0) {
            return $this->startFailure($tool, $target);
        }

        $output = '';
        $rawBytes = 0;
        $exitError = false;
        $reason = null;
        $count = count($argvList);
        $deadline = microtime(true) + $this->timeoutSec;

        foreach ($argvList as $index => $argv) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                $exitError = true;
                $reason = self::REASON_TIMEOUT;
                break;
            }

            // Share the wall-clock budget across the remaining sub-commands so the
            // aggregate stays within the overall bound.
            $perCommand = max(1, (int)floor($remaining / ($count - $index)));
            $runner = new self($perCommand, $this->maxOutputBytes);
            $sub = $runner->run($tool, $target, $argv);

            if ($output !== '') {
                $output .= "\n";
                $rawBytes += 1;
            }
            $capacity = $this->maxOutputBytes - strlen($output);
            if ($capacity > 0) {
                $output .= substr($sub['output'], 0, $capacity);
            }
            // Approximate raw size from the sub-result: it is already capped, and
            // its own truncated flag tells us whether the sub-output overflowed.
            $rawBytes += $sub['truncated'] ? $this->maxOutputBytes + 1 : strlen($sub['output']);

            if ($sub['exitError']) {
                $exitError = true;
            }
            if ($sub['reason'] === self::REASON_TIMEOUT) {
                $reason = self::REASON_TIMEOUT;
                break;
            }
        }

        $truncated = strlen($output) >= $this->maxOutputBytes && $rawBytes > $this->maxOutputBytes;
        return [
            'tool'      => $tool,
            'target'    => $target,
            'output'    => $output,
            'truncated' => $truncated,
            'exitError' => $exitError,
            'reason'    => $reason,
        ];
    }

    /**
     * Terminate a (possibly still-running) child. When the child was isolated in
     * its own process group, the whole group is signalled (negative PID) so no
     * grandchildren are orphaned; otherwise only the direct child is signalled.
     *
     * @param resource $process  The proc_open handle.
     * @param int      $pid      The child PID.
     * @param bool     $ownGroup Whether the child leads its own process group.
     * @return void
     */
    private function terminate($process, int $pid, bool $ownGroup): void {
        $groupKilled = false;

        if ($ownGroup && $pid > 0 && function_exists('posix_kill')) {
            // Negative PID targets the entire process group led by $pid.
            $groupKilled = @posix_kill(-$pid, self::SIG_TERM);
            if ($groupKilled) {
                usleep(self::KILL_GRACE_USEC);
                @posix_kill(-$pid, self::SIG_KILL);
            }
        }

        // Always ensure the direct child is signalled too (covers the case where
        // the process group could not be established).
        @proc_terminate($process, self::SIG_TERM);
        usleep(self::KILL_GRACE_USEC);
        @proc_terminate($process, self::SIG_KILL);

        if (!$groupKilled && $pid > 0 && function_exists('posix_kill')) {
            @posix_kill($pid, self::SIG_KILL);
        }
    }

    /**
     * Build a ToolResult for a utility that could not be started. Returned
     * without modifying any system state (Req 6.9).
     *
     * @param string $tool
     * @param string $target
     * @return array ToolResult.
     */
    private function startFailure(string $tool, string $target): array {
        return [
            'tool'      => $tool,
            'target'    => $target,
            'output'    => '',
            'truncated' => false,
            'exitError' => true,
            'reason'    => self::REASON_START_FAILED,
        ];
    }

    /**
     * Assemble a ToolResult, computing the truncation flag from the raw byte
     * count (Property 10: truncated iff raw output exceeded the maximum).
     *
     * @param string      $tool
     * @param string      $target
     * @param string      $output    Captured (already capped) output.
     * @param int         $rawBytes  Total raw bytes produced by the utility.
     * @param bool        $exitError
     * @param string|null $reason
     * @return array ToolResult.
     */
    private function result(
        string $tool,
        string $target,
        string $output,
        int $rawBytes,
        bool $exitError,
        ?string $reason
    ): array {
        return [
            'tool'      => $tool,
            'target'    => $target,
            'output'    => $output,
            'truncated' => $rawBytes > $this->maxOutputBytes,
            'exitError' => $exitError,
            'reason'    => $reason,
        ];
    }
}
