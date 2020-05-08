<?php
/**
 * Models a changelog file.
 *
 * @package lucatume\Cli\Models
 */

namespace lucatume\Cli\Models;

/**
 * Class Changelog
 *
 * @package lucatume\Cli\Models
 */
class Changelog
{
    /**
     * The absolute path to the changelog file.
     *
     * @var string
     */
    protected $file;

    /**
     * An ASC array of the versions detailed by the changelog.
     *
     * @var array<string>
     */
    protected $versions = [];

    /**
     * The latest semantic version found in the file.
     *
     * @var string
     */
    protected $latestVersion;
    /**
     * The changelog file contents.
     *
     * @var string
     */
    protected $contents;

    /**
     * Changelog constructor.
     *
     * @param string $file The absolute path to the changelog file.
     *
     * @throws \InvalidArgumentException If the changelog file does not exist or is not readable and writeable.
     */
    public function __construct($file)
    {
        $filePath = realpath($file);
        if ($filePath === false) {
            throw new \InvalidArgumentException("The file {$file} does not exist.");
        }

        if (!is_readable($filePath)) {
            throw new \InvalidArgumentException("The file {$file} is not readable.");
        }

        $this->file = $file;
        $this->versions = $this->parseVersions();
        $this->latestVersion = $this->parseLatestVersion();
    }

    /**
     * Parsed the changelog file contents to set up the model.
     *
     * @return array<string,array> The versions parsed from the changelog and its notes.
     */
    protected function parseVersions()
    {
        $buffer = false;
        $parsedVersions = [];
        $currentVersion = null;
        $currentVersionBuffer = null;

        $f = fopen($this->file, 'rb');
        $versionLinePattern = '/^##\\s*\\[(?<version>(unreleased|\\d+\\.\\d\.\\d+))]/i';
        $versionLinkPattern = '/^\\[(unreleased|\\d+\\.\\d\.\\d+)]:/i';
        while ($line = fgets($f)) {
            if (preg_match($versionLinePattern, $line, $m)) {
                if ($currentVersion !== null) {
                    $parsedVersions[$currentVersion] = trim($currentVersionBuffer);
                }

                // Start a new version buffer.
                $currentVersion = $m['version'];
                $currentVersionBuffer = $line;
                $buffer = true;
                continue;
            }

            if (preg_match($versionLinkPattern, $line)) {
                if ($currentVersion !== null) {
                    $parsedVersions[$currentVersion] = trim($currentVersionBuffer);
                }

                break;
            }

            if (!$buffer) {
                continue;
            }

            $currentVersionBuffer .= $line;
        }

        fclose($f);

        return array_reverse(array_change_key_case($parsedVersions, CASE_LOWER));
    }

    /**
     * Parses the changelog file content to get the latest semantic version in it.
     *
     * @return string The latest version defined in the changelog file.
     */
    protected function parseLatestVersion()
    {
        $ascVersions = array_reverse(array_keys($this->versions));
        $latestVersion = 'unreleased';

        foreach ($ascVersions as $version) {
            if (preg_match('/^\\d+\\.\\d\.\\d+/i', $version)) {
                $latestVersion = $version;
                break;
            }
        }

        return $latestVersion;
    }

    /**
     * Returns the latest semantic version defined in the file.
     *
     * @return string The latest semantic version defined in the file.
     */
    public function getLatestVersion()
    {
        return $this->latestVersion;
    }

    /**
     * Returns the ascending list of versions defined in the changelog file.
     *
     * @return array<string> The ascending list of versions defined in the changelog file.
     */
    public function getVersions()
    {
        return array_keys($this->versions);
    }

    /**
     * Returns the next version (unreleased) release notes.
     *
     * @param string $releaseType The type of release to prepare the notes for; one of `major`, `minor` or `patch`.
     * @return string The version full release notes.
     *
     * @throws \InvalidArgumentException If the file does not contain an `unreleased` entry.
     */
    public function getNextVersionReleaseNotes($releaseType)
    {
        $releaseVersion = $this->getNextVersion($releaseType);
        return sprintf("%s\n\n%s", $releaseVersion, $this->getVersionNotes('unreleased'));
    }

    /**
     * Returns the semantic next version given the latest version defined in the changelog file and the type of release.
     *
     * @param string $releaseType The type of release to package, one of `major`, `minor` or `patch`.
     *
     * @return string The next semantic version string given the latest version defined in the changelog file and the
     *                release type.
     */
    public function getNextVersion($releaseType)
    {
        $latestVersion = $this->latestVersion;
        $releaseVersion = '0.1.0';

        switch ($releaseType) {
            case 'major':
                $releaseVersion = preg_replace_callback('/(?<target>\\d+)\\.\\d\.\\d+/', static function ($m) {
                    return (++$m['target']) . '.0.0';
                }, $latestVersion);
                break;
            case 'minor':
                $releaseVersion = preg_replace_callback('/(?<major>\\d+)\\.(?<target>\\d)\.\\d+/',
                    static function ($m) {
                        return $m['major'] . '.' . (++$m['target']) . '.0';
                    }, $latestVersion);
                break;
            case 'patch':
            default:
                $releaseVersion = preg_replace_callback('/(?<major>\\d+)\\.(?<minor>\\d)\.(?<target>\\d+)/',
                    static function ($m) {
                        return $m['major'] . '.' . ($m['minor']) . '.' . (++$m['target']);
                    }, $latestVersion);
                break;
        }

        return $releaseVersion;
    }

    /**
     * Returns the release notes for a specific version.
     *
     * @param string $version The version to return the release notes for.
     *
     * @return string The release notes from the version.
     *
     * @throws \InvalidArgumentException If the version is not present in the changelog.
     */
    protected function getVersionNotes($version)
    {
        if (!isset($this->versions[$version])) {
            throw new \InvalidArgumentException(
                "The {$version} version is not listed in the {$this->file} file."
            );
        }

        $rawVersionNotes = isset($this->versions[$version]) ? $this->versions[$version] : '';

        return preg_replace('/^.*?###/uis', '###', $rawVersionNotes);
    }

    /**
     * Builds and returns the preview of the changelog updates.
     *
     * @param string $version The version to update the changelog to.
     * @param string|null $date The date of the changelog update, or `null` to use the current date.
     *
     * @return string The changelog update preview.
     */
    public function getUpdatePreview($version, $date = null)
    {
        $changelogUpdates = $this->getUpdatedContents($version, $date);

        $output = <<< OUT
Changelog updates:

---
%s

[...]

%s
---
OUT;
        return sprintf(
            $output,
            substr($changelogUpdates, 0, 512),
            substr($changelogUpdates, strlen($changelogUpdates) - 256)
        );
    }

    /**
     * Updates the changelog contents to the specified version and returns the updated contents.
     *
     * @param string $version The version to update to.
     * @param string|null $date The date of the changelog update, or `null` to use the current date.
     *
     * @return string The changelog updated contents.
     */
    public function getUpdatedContents($version, $date = null)
    {
        $date = $date ?: date('Y-m-d');
        $changelogVersionLine = sprintf("\n\n## [%s] %s;", $version, $date);
        $currentContents = $this->getContents();
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

        return $changelogContents;
    }

    /**
     * Returns the changelog file contents.
     *
     * @return string The changelog file contents.
     *
     * @throws \RuntimeException If the changelog file contents cannot be read.
     */
    public function getContents()
    {
        if ($this->contents === null) {
            $this->contents = file_get_contents($this->file);
            if ($this->contents === false) {
                throw new \RuntimeException("Could not read file {$this->file} contents.");
            }
        }

        return $this->contents;
    }

    /**
     * Updates the contents of the changelog file.
     *
     * @param string $nextVersion The version to update the changelog file to.
     * @param string|null $date The date of the changelog update, or `null` to use the current date.
     * @param null|string $outputFile The file to write the updates to or `null` to write in place.
     *
     * @throws \RuntimeException If the destination file cannot be written.
     */
    public function updateContents($nextVersion, $date = null, $outputFile = null)
    {
        $outputFile = $outputFile ?: $this->file;

        if (!file_put_contents($outputFile, $this->getUpdatedContents($nextVersion, $date))) {
            throw new \RuntimeException("Could not write {$outputFile}.");
        }
    }

}
