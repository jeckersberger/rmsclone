<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->serverPermissionCheck("CONFIG:SET")) {
    finish(false, ["message" => "Keine Berechtigung"]);
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

// Get the application root directory (two levels up from api/server/)
$appRoot = realpath(__DIR__ . '/../../');

if ($action === 'check') {
    // Fetch latest changes from remote without applying them
    $fetchOutput = [];
    $fetchReturn = 0;
    exec('cd ' . escapeshellarg($appRoot) . ' && git fetch origin main 2>&1', $fetchOutput, $fetchReturn);

    if ($fetchReturn !== 0) {
        finish(false, ["message" => "Git fetch fehlgeschlagen: " . implode("\n", $fetchOutput)]);
    }

    // Get current commit
    $currentCommit = trim(shell_exec('cd ' . escapeshellarg($appRoot) . ' && git rev-parse HEAD 2>&1'));
    $remoteCommit = trim(shell_exec('cd ' . escapeshellarg($appRoot) . ' && git rev-parse origin/main 2>&1'));

    // Get current branch
    $currentBranch = trim(shell_exec('cd ' . escapeshellarg($appRoot) . ' && git rev-parse --abbrev-ref HEAD 2>&1'));

    // Get log of new commits
    $logOutput = [];
    exec('cd ' . escapeshellarg($appRoot) . ' && git log --oneline HEAD..origin/main 2>&1', $logOutput);

    $updateAvailable = ($currentCommit !== $remoteCommit) && count($logOutput) > 0;

    finish(true, null, [
        "updateAvailable" => $updateAvailable,
        "currentCommit" => substr($currentCommit, 0, 8),
        "remoteCommit" => substr($remoteCommit, 0, 8),
        "currentBranch" => $currentBranch,
        "newCommits" => $logOutput,
        "commitCount" => count($logOutput),
    ]);

} elseif ($action === 'pull') {
    // Actually pull the update
    $output = [];
    $returnCode = 0;

    // First stash any local changes
    exec('cd ' . escapeshellarg($appRoot) . ' && git stash 2>&1', $stashOutput, $stashReturn);

    // Pull from origin main
    exec('cd ' . escapeshellarg($appRoot) . ' && git pull origin main 2>&1', $output, $returnCode);

    if ($returnCode !== 0) {
        // Try to restore stash if pull failed
        exec('cd ' . escapeshellarg($appRoot) . ' && git stash pop 2>&1');
        finish(false, ["message" => "Git pull fehlgeschlagen: " . implode("\n", $output)]);
    }

    // Get new current commit
    $newCommit = trim(shell_exec('cd ' . escapeshellarg($appRoot) . ' && git rev-parse HEAD 2>&1'));

    finish(true, null, [
        "newCommit" => substr($newCommit, 0, 8),
        "output" => implode("\n", $output),
    ]);

} else {
    finish(false, ["message" => "Unbekannte Aktion"]);
}
