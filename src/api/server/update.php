<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->serverPermissionCheck("CONFIG:SET")) {
    finish(false, ["message" => "Keine Berechtigung"]);
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

// Get the application root directory (two levels up from api/server/)
$appRoot = realpath(__DIR__ . '/../../');

// Konfigurierbare Git-Quelle
$gitRemote = $CONFIGCLASS->get('UPDATE_GIT_REMOTE') ?: 'origin';
$gitBranch = $CONFIGCLASS->get('UPDATE_GIT_BRANCH') ?: 'main';

// Validate remote/branch to prevent command injection
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $gitRemote)) finish(false, ["message" => "Ungueltiger Git-Remote"]);
if (!preg_match('/^[a-zA-Z0-9_.\/-]+$/', $gitBranch)) finish(false, ["message" => "Ungueltiger Git-Branch"]);

// Git safe.directory setzen, da der Webserver-Benutzer nicht der Repository-Besitzer ist
exec('git config --global --add safe.directory ' . escapeshellarg($appRoot) . ' 2>&1');

if ($action === 'check') {
    // Fetch latest changes from remote without applying them
    $fetchOutput = [];
    $fetchReturn = 0;
    exec('cd ' . escapeshellarg($appRoot) . ' && git fetch ' . escapeshellarg($gitRemote) . ' ' . escapeshellarg($gitBranch) . ' 2>&1', $fetchOutput, $fetchReturn);

    if ($fetchReturn !== 0) {
        finish(false, ["message" => "Git fetch fehlgeschlagen: " . implode("\n", $fetchOutput)]);
    }

    // Get current commit
    $currentCommit = trim(shell_exec('cd ' . escapeshellarg($appRoot) . ' && git rev-parse HEAD 2>&1'));
    $remoteCommit = trim(shell_exec('cd ' . escapeshellarg($appRoot) . ' && git rev-parse ' . escapeshellarg($gitRemote . '/' . $gitBranch) . ' 2>&1'));

    // Get current branch
    $currentBranch = trim(shell_exec('cd ' . escapeshellarg($appRoot) . ' && git rev-parse --abbrev-ref HEAD 2>&1'));

    // Get log of new commits
    $logOutput = [];
    exec('cd ' . escapeshellarg($appRoot) . ' && git log --oneline HEAD..' . escapeshellarg($gitRemote . '/' . $gitBranch) . ' 2>&1', $logOutput);

    $updateAvailable = ($currentCommit !== $remoteCommit) && count($logOutput) > 0;

    // Check if there are migration files in the new commits
    $migrationChanges = [];
    exec('cd ' . escapeshellarg($appRoot) . ' && git diff --name-only HEAD..' . escapeshellarg($gitRemote . '/' . $gitBranch) . ' -- db/migrations/ 2>&1', $migrationChanges);
    $hasMigrations = count($migrationChanges) > 0;

    // Check if composer.lock changed
    $composerChanges = [];
    exec('cd ' . escapeshellarg($appRoot) . ' && git diff --name-only HEAD..' . escapeshellarg($gitRemote . '/' . $gitBranch) . ' -- composer.lock 2>&1', $composerChanges);
    $hasComposerChanges = count($composerChanges) > 0;

    // Version aus version.json lesen
    $versionFile = $appRoot . '/version.json';
    $version = file_exists($versionFile) ? json_decode(file_get_contents($versionFile), true) : null;

    finish(true, null, [
        "updateAvailable" => $updateAvailable,
        "currentCommit" => substr($currentCommit, 0, 8),
        "remoteCommit" => substr($remoteCommit, 0, 8),
        "currentBranch" => $currentBranch,
        "currentVersion" => $version['version'] ?? 'unbekannt',
        "newCommits" => $logOutput,
        "commitCount" => count($logOutput),
        "hasMigrations" => $hasMigrations,
        "hasComposerChanges" => $hasComposerChanges,
        "gitRemote" => $gitRemote,
        "gitBranch" => $gitBranch,
    ]);

} elseif ($action === 'pull') {
    // Actually pull the update
    $output = [];
    $returnCode = 0;
    $steps = [];

    // Step 1: Stash local changes
    $stashOutput = [];
    exec('cd ' . escapeshellarg($appRoot) . ' && git stash 2>&1', $stashOutput, $stashReturn);
    $steps[] = "Git stash: " . implode(" ", $stashOutput);

    // Step 2: Pull from configured remote/branch
    exec('cd ' . escapeshellarg($appRoot) . ' && git pull ' . escapeshellarg($gitRemote) . ' ' . escapeshellarg($gitBranch) . ' 2>&1', $output, $returnCode);

    if ($returnCode !== 0) {
        // Try to restore stash if pull failed
        exec('cd ' . escapeshellarg($appRoot) . ' && git stash pop 2>&1');
        finish(false, ["message" => "Git pull fehlgeschlagen: " . implode("\n", $output)]);
    }
    $steps[] = "Git pull: " . implode(" ", $output);

    // Step 3: Install/update composer dependencies if composer.lock changed
    $composerOutput = [];
    exec('cd ' . escapeshellarg($appRoot) . ' && composer install --no-dev --optimize-autoloader 2>&1', $composerOutput, $composerReturn);
    $steps[] = "Composer install: " . ($composerReturn === 0 ? "OK" : implode(" ", $composerOutput));

    // Step 4: Run database migrations
    $migrateOutput = [];
    exec('cd ' . escapeshellarg($appRoot) . ' && php vendor/bin/phinx migrate -e production 2>&1', $migrateOutput, $migrateReturn);
    $steps[] = "Migrationen: " . ($migrateReturn === 0 ? "OK" : implode(" ", $migrateOutput));

    // Step 5: Run database seeds
    $seedOutput = [];
    exec('cd ' . escapeshellarg($appRoot) . ' && php vendor/bin/phinx seed:run -e production 2>&1', $seedOutput, $seedReturn);
    $steps[] = "Seeds: " . ($seedReturn === 0 ? "OK" : implode(" ", $seedOutput));

    // Step 6: Clear OPcache if available
    $opcacheCleared = false;
    if (function_exists('opcache_reset')) {
        $opcacheCleared = opcache_reset();
    }
    $steps[] = "OPcache: " . ($opcacheCleared ? "geleert" : "nicht verfügbar/nicht nötig");

    // Step 7: Clear Twig cache
    $twigCacheDir = '/tmp/twig_cache';
    if (is_dir($twigCacheDir)) {
        exec('rm -rf ' . escapeshellarg($twigCacheDir) . '/* 2>&1');
        $steps[] = "Twig-Cache: geleert";
    } else {
        $steps[] = "Twig-Cache: kein Cache-Verzeichnis gefunden";
    }

    // Get new current commit
    $newCommit = trim(shell_exec('cd ' . escapeshellarg($appRoot) . ' && git rev-parse HEAD 2>&1'));

    finish(true, null, [
        "newCommit" => substr($newCommit, 0, 8),
        "output" => implode("\n", $output),
        "steps" => $steps,
        "needsRestart" => true,
    ]);

} elseif ($action === 'restart') {
    // Restart Apache gracefully - this will reload all PHP processes
    // Use graceful restart so current requests finish before restarting
    $restartOutput = [];
    $restartReturn = 0;

    // Try graceful restart first (preferred)
    exec('apachectl graceful 2>&1', $restartOutput, $restartReturn);

    if ($restartReturn !== 0) {
        // Fallback: try service command
        exec('service apache2 reload 2>&1', $restartOutput, $restartReturn);
    }

    if ($restartReturn !== 0) {
        // Last fallback: send HUP signal to Apache parent process
        exec('kill -USR1 1 2>&1', $restartOutput, $restartReturn);
    }

    finish($restartReturn === 0,
        $restartReturn !== 0 ? ["message" => "Neustart fehlgeschlagen: " . implode("\n", $restartOutput)] : null,
        $restartReturn === 0 ? ["message" => "Apache wird neu gestartet..."] : []
    );

} else {
    finish(false, ["message" => "Unbekannte Aktion"]);
}
