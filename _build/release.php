#!/usr/bin/env php
<?php
/**
 * Release a major, minor or patch update w/ release notes from the CHANGELOG.md file.
 */

namespace tad\WPBrowser\Cli;

use lucatume\Cli\Command;
use tad\WPBrowser\Utils\Map;

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';

$command = new Command(
    $argv[0],
    [
        'type' => 'The release type, one of major, minor or patch.',
        '[-d|--dry-run]' => 'Run the script in dry-run mode, without actually modifying anything.',
        '[--no-changelog-update]' => 'Skip the changelog file update.',
        '[--no-check-diff]' => 'Skip the check for uncommitted work in the current branch.',
        '[--no-check-unpushed]' => 'Skip the check for unpushed work between local and remote branch.',
        '[-q|--no-interactive]' => 'Run the script without requiring any interaction.'
    ]
);

$args = $command->parseInputOrExit();

if ($args('help', false)) {
    echo $args('_help');
    exit(0);
}

$changelogFile = $root . '/CHANGELOG.md';

if (!in_array($args('type', 'patch'), ['major', 'minor', 'patch'], true)) {
    echo "\e[31mThe release type has to be one of major, minor or patch.\e[0m\n";
    exit(1);
}

$dryRun = $args('dry-run', false);

$currentGitBranch = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
if ($currentGitBranch !== 'master') {
    echo "\e[31mCan release only from master branch.\e[0m\n";
    exit(1);
}
echo "Current git branch: \e[32m" . $currentGitBranch . "\e[0m\n";

/**
 * Parses the changelog to get the latest notes and the latest, released, version.
 *
 * @param string $changelog The absolute path to the changelog file.
 *
 * @return Map The map of parsed values.
 */
function changelog($changelog)
{
    $notes = '';

    $f = fopen($changelog, 'rb');
    $read = false;
    while ($line = fgets($f)) {
        if (preg_match('/^## \\[unreleased]/', $line)) {
            $read = true;
            continue;
        }

        if (preg_match('/^## \\[(?<version>\\d+\\.\\d\.\\d+)]/', $line, $m)) {
            $latestVersion = $m['version'];
            break;
        }

        if ($read === true) {
            $notes .= $line;
        }
    }

    fclose($f);

    return new Map(['notes' => trim($notes), 'latestVersion' => $latestVersion]);
}

function updateChangelog($changelog, $version, callable $args, $date = null)
{
    $date = $date === null ? date('Y-m-d') : $date;
    $changelogVersionLine = sprintf("\n\n## [%s] %s;", $version, $date);
    $currentContents = file_get_contents($changelog);
    $entryLine = '## [unreleased] Unreleased';
    $changelogContents = str_replace($entryLine, $entryLine . $changelogVersionLine, $currentContents);
    $changelogContents = preg_replace_callback(
        '/^\\[unreleased]:\\s+(?<repo>.*)(?<previous_version>\\d\\.\\d\\.\\d)\\.{3}HEAD$/um',
        static function (array $matches) use ($version) {
            return sprintf('[%1$s]: %2$s%3$s...%1$s' . PHP_EOL . '[unreleased]: %2$s%1$s...HEAD'
                , $version, $matches['repo'], $matches['previous_version']);
        },
        $changelogContents
    );
    echo "Changelog updates:\n\n---\n";
    echo substr($changelogContents, 0, 1024);
    echo "\n\n[...]\n\n";
    echo substr($changelogContents, strlen($changelogContents) - 512);
    echo "---\n\n";
    if (!$args('dry-run', false)
        && (
            $args('no-interactive', false)
            || confirm("Would you like to proceed?"))
    ) {
        file_put_contents($changelog, $changelogContents);
        passthru('git commit -m "doc(CHANGELOG.md) update to version ' . $version . '" -- ' . $changelog);
    }
}

$changelog = changelog($changelogFile);

$releaseType = $args('type', 'patch');
switch ($releaseType) {
    case 'major':
        $releaseVersion = preg_replace_callback('/(?<target>\\d+)\\.\\d\.\\d+/', static function ($m) {
            return (++$m['target']) . '.0.0';
        }, $changelog('latestVersion'));
        break;
    case 'minor':
        $releaseVersion = preg_replace_callback('/(?<major>\\d+)\\.(?<target>\\d)\.\\d+/', static function ($m) {
            return $m['major'] . '.' . (++$m['target']) . '.0';
        }, $changelog('latestVersion'));
        break;
    case 'patch':
        $releaseVersion = preg_replace_callback('/(?<major>\\d+)\\.(?<minor>\\d)\.(?<target>\\d+)/',
            static function ($m) {
                return $m['major'] . '.' . ($m['minor']) . '.' . (++$m['target']);
            }, $changelog('latestVersion'));
        break;
}

$releaseNotesHeader = "{$releaseVersion}\n\n";
$fullReleaseNotes = $releaseNotesHeader . $changelog('notes');

echo "Latest release: \e[32m" . $changelog('latestVersion') . "\e[0m\n";
echo "Release type: \e[32m" . $releaseType . "\e[0m\n";
echo "Next release: \e[32m" . $releaseVersion . "\e[0m\n";
echo "Release notes:\n\n---\n" . $fullReleaseNotes . "\n---\n";
echo "\n\n";

if (!$args('no-changelog-update', false)) {
    updateChangelog($changelogFile, $releaseVersion, $args);
}

if ($args('no-check-diff', true) && !$dryRun) {
    $gitDirty = trim(shell_exec('git diff HEAD'));
    if (!empty($gitDirty)) {
        echo "\e[31mYou have uncommited work.\e[0m\n";
        exit(1);
    }
}

function confirm($question)
{
    $question = "\n{$question} ";
    return preg_match('/y/i', readline($question));
}

if (!$args('no-check-unpushed', false) && !$dryRun) {
    $gitDiff = trim(shell_exec('git log origin/master..HEAD'));
    if (!empty($gitDiff)) {
        echo "\e[31mYou have unpushed changes.\e[0m\n";
        if (confirm('Would you like to push them now?')) {
            passthru('git push');
        } else {
            exit(1);
        }
    }
}

file_put_contents($root . '/.rel', $fullReleaseNotes);

$releaseCommand = 'hub release create -F .rel ' . $releaseVersion;

echo "Releasing with command: \e[32m" . $releaseCommand . "\e[0m\n\n";

if ($dryRun || $args('no-interactive', false) || confirm('Do you want to proceed?')) {
    if (!$dryRun) {
        passthru($releaseCommand);
    }
} else {
    echo "Canceling\n";
}

unlink($root . '/.rel');
