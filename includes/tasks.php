<?php
/**
 * Recurring checklist items Admin sets for Plant Head (Settings -> Tasks).
 * A task is never auto-removed - it just keeps recurring every period
 * (daily = every day, weekly = every ISO week, monthly = every calendar
 * month, custom = every custom_interval custom_unit(s) counted from when
 * the task was created) until Admin deletes it. Completion is tracked per
 * period via task_completions, keyed by taskPeriodKey() so "is this done
 * right now" is a single lookup.
 */
function taskPeriodKey(array $task): string {
    switch ($task['frequency']) {
        case 'weekly':
            return date('o-\WW');
        case 'monthly':
            return date('Y-m');
        case 'custom':
            $unit = $task['custom_unit'] ?? 'days';
            $interval = max(1, (int) ($task['custom_interval'] ?? 1));
            $anchor = new DateTime($task['created_at']);
            $diff = $anchor->diff(new DateTime());
            switch ($unit) {
                case 'weeks':
                    $elapsed = intdiv((int) $diff->days, 7);
                    break;
                case 'months':
                    $elapsed = ($diff->y * 12) + $diff->m;
                    break;
                case 'years':
                    $elapsed = $diff->y;
                    break;
                default: // days
                    $elapsed = (int) $diff->days;
            }
            // 'C' + which cycle we're in (0, 1, 2 ...) - stable for the
            // whole cycle, changes once the next one starts.
            return 'C' . intdiv($elapsed, $interval);
        default: // daily
            return date('Y-m-d');
    }
}

/** Plain-English label for a task's frequency, e.g. "Weekly" or "Every 10 Days". */
function taskFrequencyLabel(array $task): string {
    switch ($task['frequency']) {
        case 'weekly': return 'Weekly';
        case 'monthly': return 'Monthly';
        case 'custom':
            $n = max(1, (int) ($task['custom_interval'] ?? 1));
            $unit = ucfirst($task['custom_unit'] ?? 'days');
            if ($n === 1) $unit = rtrim($unit, 's'); // "Every 1 Days" -> "Every 1 Day"
            return "Every $n $unit";
        default: return 'Daily';
    }
}

/** Pill colour class (suffix of .pill-*) for a task's frequency badge - one distinct colour per frequency. */
function taskFrequencyPillClass(string $frequency): string {
    $map = ['daily' => 'active', 'weekly' => 'completed', 'monthly' => 'pending', 'custom' => 'inactive'];
    return $map[$frequency] ?? 'inactive';
}

/**
 * Every active task, each annotated with:
 * - done: completed for the current period (task_completions lookup)
 * - is_due: true if there's no due_time set, or that time of day has
 *   already passed today - a task with a future due_time simply isn't due
 *   yet, it's not "overdue"
 * - pending: not done AND already due - this is what actually needs
 *   Plant Head's attention right now (dashboard banner/popup/count all use
 *   this, not the raw !done)
 */
function getActiveTasksWithStatus(PDO $pdo): array {
    $tasks = $pdo->query("SELECT * FROM tasks WHERE status='active' ORDER BY frequency, title")->fetchAll();
    $stmt = $pdo->prepare("SELECT 1 FROM task_completions WHERE task_id=:id AND period_key=:pk");
    $nowTime = date('H:i:s');
    foreach ($tasks as &$t) {
        $pk = taskPeriodKey($t);
        $stmt->execute([':id' => $t['id'], ':pk' => $pk]);
        $t['period_key'] = $pk;
        $t['done'] = (bool) $stmt->fetchColumn();
        $t['is_due'] = $t['due_time'] === null || $nowTime >= $t['due_time'];
        $t['pending'] = !$t['done'] && $t['is_due'];
    }
    unset($t);
    return $tasks;
}
