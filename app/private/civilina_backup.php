<?php

declare(strict_types=1);

/*
 * Automatic backups (milestone M5).
 *
 * Once a day (lazily, on the first page view or back-office request after 24 h,
 * like the scheduled articles), one zip file in ~/civilina-site-backups/auto/:
 *   - civilina.sqlite: consistent snapshot of the database (VACUUM INTO);
 *   - site/...: every file the back-office writes in public_html (homepage,
 *     Journal pages and covers, axis pages with the offers, sitemap, privacy);
 *   - manifest.json: SHA-256 of each file and the row count of each table.
 * The 14 most recent zips are kept (one inode each: hosting quota). Files are
 * private (0600) and stored outside public_html. civilina_backup_verify()
 * re-reads a zip, checks every hash and runs PRAGMA integrity_check on the
 * database copy: that is the restore test.
 */

require_once __DIR__ . '/civilina_publish.php';

const CIVILINA_BACKUP_KEEP_DAYS = 14;
const CIVILINA_BACKUP_INTERVAL = 86400;

function civilina_backup_dir(): string
{
    $dir = getenv('CIVILINA_BACKUP_DIR');
    if (!is_string($dir) || $dir === '') {
        // ~/civilina-site-backups/auto, next to ~/civilina_private and ~/public_html.
        $dir = dirname(civilina_db_private_dir()) . '/civilina-site-backups/auto';
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir;
}

/** Files of public_html written by the back-office (relative paths). */
function civilina_backup_site_files(): array
{
    $root = civilina_site_root();
    $files = [];
    foreach (['default.html', 'index.html', 'sitemap.xml', 'journal/index.html', 'engineering/index.html', 'gestion-entreprise/index.html', 'digital/index.html', 'confidentialite/index.html', 'robots.txt', '.htaccess'] as $f) {
        if (is_file($root . '/' . $f)) {
            $files[] = $f;
        }
    }
    foreach (glob($root . '/journal/*/index.html') ?: [] as $f) {
        $files[] = substr($f, strlen($root) + 1);
    }
    foreach (glob($root . '/assets/journal/*.svg') ?: [] as $f) {
        $files[] = substr($f, strlen($root) + 1);
    }
    return array_values(array_unique($files));
}

function civilina_backup_table_counts(PDO $db): array
{
    $counts = [];
    foreach ($db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $counts[$table] = (int) $db->query('SELECT COUNT(*) FROM "' . str_replace('"', '""', $table) . '"')->fetchColumn();
    }
    return $counts;
}

/** @return array{ok: bool, file: string, size: int, files: int, error: string} */
function civilina_backup_run(string $reason): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'file' => '', 'size' => 0, 'files' => 0, 'error' => 'Extension zip absente du serveur.'];
    }
    $lock = fopen(civilina_db_private_dir() . '/storage/backup.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        return ['ok' => false, 'file' => '', 'size' => 0, 'files' => 0, 'error' => 'Sauvegarde déjà en cours.'];
    }
    $tmpDb = '';
    try {
        $dir = civilina_backup_dir();
        $stamp = date('Ymd-His');
        $zipPath = $dir . '/civilina-' . $stamp . '.zip';
        if (is_file($zipPath)) {
            throw new RuntimeException("Une sauvegarde vient d'être créée, réessayez dans une seconde.");
        }
        $db = civilina_db();

        // Consistent copy of the database, even while it is being written.
        $tmpDb = civilina_db_private_dir() . '/storage/backup-' . bin2hex(random_bytes(4)) . '.sqlite';
        $db->exec('VACUUM INTO ' . $db->quote($tmpDb));

        $manifest = ['created_at' => date('c'), 'reason' => $reason, 'schema' => (int) $db->query('PRAGMA user_version')->fetchColumn(), 'tables' => civilina_backup_table_counts($db), 'files' => []];
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Création du fichier de sauvegarde impossible.');
        }
        $zip->addFile($tmpDb, 'civilina.sqlite');
        $manifest['files']['civilina.sqlite'] = hash_file('sha256', $tmpDb);
        $root = civilina_site_root();
        foreach (civilina_backup_site_files() as $rel) {
            $zip->addFile($root . '/' . $rel, 'site/' . $rel);
            $manifest['files']['site/' . $rel] = hash_file('sha256', $root . '/' . $rel);
        }
        $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (!$zip->close()) {
            throw new RuntimeException('Écriture de la sauvegarde interrompue.');
        }
        @chmod($zipPath, 0600);

        // Retention: the 14 most recent.
        $all = glob($dir . '/civilina-*.zip') ?: [];
        sort($all);
        foreach (array_slice($all, 0, max(0, count($all) - CIVILINA_BACKUP_KEEP_DAYS)) as $old) {
            @unlink($old);
        }
        civilina_db()->prepare('INSERT INTO settings(key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
            ->execute(['backup_last_at', date('c')]);
        civilina_audit('backup.created', basename($zipPath) . ' (' . $reason . ', ' . count($manifest['files']) . ' fichiers)');
        return ['ok' => true, 'file' => basename($zipPath), 'size' => (int) filesize($zipPath), 'files' => count($manifest['files']), 'error' => ''];
    } catch (Throwable $e) {
        civilina_audit('backup.failed', $e->getMessage());
        return ['ok' => false, 'file' => '', 'size' => 0, 'files' => 0, 'error' => $e->getMessage()];
    } finally {
        if ($tmpDb !== '' && is_file($tmpDb)) {
            @unlink($tmpDb);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Daily backup when the last one is older than 24 h. Cheap otherwise. */
function civilina_backup_due(): ?array
{
    $statement = civilina_db()->prepare('SELECT value FROM settings WHERE key = ?');
    $statement->execute(['backup_last_at']);
    $last = (string) $statement->fetchColumn();
    if ($last !== '' && strtotime($last) > time() - CIVILINA_BACKUP_INTERVAL) {
        return null;
    }
    return civilina_backup_run('quotidienne');
}

/** @return list<array{file: string, size: int, date: string}> newest first */
function civilina_backups_list(): array
{
    $out = [];
    foreach (glob(civilina_backup_dir() . '/civilina-*.zip') ?: [] as $path) {
        $out[] = ['file' => basename($path), 'size' => (int) filesize($path), 'date' => date('c', (int) filemtime($path))];
    }
    usort($out, static fn (array $a, array $b): int => strcmp($b['file'], $a['file']));
    return $out;
}

function civilina_backup_path(string $file): ?string
{
    if (!preg_match('~^civilina-\d{8}-\d{6}\.zip$~', $file)) {
        return null;
    }
    $path = civilina_backup_dir() . '/' . $file;
    return is_file($path) ? $path : null;
}

/**
 * Restore test: every file of the zip matches its manifest hash, and the
 * database copy opens, passes PRAGMA integrity_check and has the same tables.
 * @return array{ok: bool, report: list<string>}
 */
function civilina_backup_verify(string $file): array
{
    $report = [];
    $path = civilina_backup_path($file);
    if ($path === null) {
        return ['ok' => false, 'report' => ['Sauvegarde introuvable.']];
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::RDONLY) !== true) {
        return ['ok' => false, 'report' => ['Le fichier zip ne s\'ouvre pas.']];
    }
    $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
    if (!is_array($manifest) || empty($manifest['files'])) {
        $zip->close();
        return ['ok' => false, 'report' => ['Manifeste absent ou illisible.']];
    }
    $ok = true;
    $bad = 0;
    foreach ($manifest['files'] as $name => $hash) {
        $content = $zip->getFromName($name);
        if ($content === false || hash('sha256', $content) !== $hash) {
            $bad++;
            $ok = false;
            $report[] = 'Fichier altéré ou manquant : ' . $name;
        }
    }
    $report[] = count($manifest['files']) - $bad . ' / ' . count($manifest['files']) . ' fichiers intacts (empreintes SHA-256).';

    $tmp = civilina_db_private_dir() . '/storage/verify-' . bin2hex(random_bytes(4)) . '.sqlite';
    try {
        file_put_contents($tmp, (string) $zip->getFromName('civilina.sqlite'));
        $copy = new PDO('sqlite:' . $tmp, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $integrity = (string) $copy->query('PRAGMA integrity_check')->fetchColumn();
        $counts = civilina_backup_table_counts($copy);
        $copy = null;
        if ($integrity !== 'ok') {
            $ok = false;
        }
        $report[] = 'Base : contrôle d\'intégrité « ' . $integrity . ' », schéma v' . (int) ($manifest['schema'] ?? 0) . '.';
        $diff = array_diff_assoc($manifest['tables'] ?? [], $counts);
        if ($diff !== []) {
            $ok = false;
            $report[] = 'Nombre de lignes différent pour : ' . implode(', ', array_keys($diff));
        } else {
            $report[] = count($counts) . ' tables, ' . array_sum($counts) . ' lignes, identiques au moment de la sauvegarde.';
        }
    } catch (Throwable $e) {
        $ok = false;
        $report[] = 'Base illisible : ' . $e->getMessage();
    } finally {
        @unlink($tmp);
        $zip->close();
    }
    civilina_audit('backup.verified', $file . ' : ' . ($ok ? 'OK' : 'ÉCHEC'));
    return ['ok' => $ok, 'report' => $report];
}

/** Private files readable by the account only (shared hosting). @return int files changed */
function civilina_backup_harden_permissions(): int
{
    $private = civilina_db_private_dir();
    $changed = 0;
    foreach ([$private . '/storage', $private . '/backups', civilina_backup_dir()] as $dir) {
        if (is_dir($dir) && (fileperms($dir) & 0077) !== 0 && @chmod($dir, 0700)) {
            $changed++;
        }
    }
    foreach (array_merge(glob($private . '/storage/*') ?: [], glob($private . '/*.json') ?: [], glob($private . '/*.log') ?: []) as $file) {
        if (is_file($file) && (fileperms($file) & 0077) !== 0 && @chmod($file, 0600)) {
            $changed++;
        }
    }
    return $changed;
}
