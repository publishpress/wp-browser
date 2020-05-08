<?php namespace lucatume\Cli\Models;

use tad\Codeception\SnapshotAssertions\SnapshotAssertions;

class ChangelogTest extends \Codeception\Test\Unit
{
    use SnapshotAssertions;

    /**
     * It should throw if building on a non-existing file
     *
     * @test
     */
    public function should_throw_if_building_on_a_non_existing_file()
    {
        $this->expectException(\InvalidArgumentException::class);

        new Changelog('foo/bar.md');
    }

    /**
     * It should throw if the file is not readable
     *
     * @test
     */
    public function should_throw_if_the_file_is_not_readable()
    {
        $this->expectException(\InvalidArgumentException::class);

        $file = codecept_output_dir(md5(time() . 'unreadable.md'));
        touch($file);
        chmod($file, 0333);

        new Changelog($file);
    }

    /**
     * It should allow getting the latest version in the file
     *
     * @test
     */
    public function should_allow_getting_the_latest_version_in_the_file()
    {
        $file = codecept_data_dir('lucatume/Cli/Models/changelog_1.md');

        $changelog = new Changelog($file);

        $this->assertEquals('2.4.8', $changelog->getLatestVersion());
    }

    /**
     * It should allow getting all versions logged in the file
     *
     * @test
     */
    public function should_allow_getting_all_versions_logged_in_the_file()
    {
        $file = codecept_data_dir('lucatume/Cli/Models/changelog_1.md');

        $changelog = new Changelog($file);

        $this->assertEquals(['2.4.7', '2.4.8', 'unreleased'], $changelog->getVersions());
    }

    public function releaseTypeAndExpectedVersionProvider()
    {
        return [
            'patch' => ['patch', '2.4.9'],
            'minor' => ['minor', '2.5.0'],
            'major' => ['major', '3.0.0'],
        ];
    }

    /**
     * It should allow getting the next version depending on the release type
     *
     * @test
     *
     * @dataProvider  releaseTypeAndExpectedVersionProvider
     */
    public function should_allow_getting_the_next_version_depending_on_the_release_type($releaseType, $expected)
    {
        $file = codecept_data_dir('lucatume/Cli/Models/changelog_1.md');

        $changelog = new Changelog($file);

        $this->assertEquals($expected, $changelog->getNextVersion($releaseType));
    }

    /**
     * It should allow getting next version release notes
     *
     * @test
     * @dataProvider  releaseTypeAndExpectedVersionProvider
     */
    public function should_allow_getting_next_version_release_notes($releaseType)
    {
        $file = codecept_data_dir('lucatume/Cli/Models/changelog_1.md');

        $changelog = new Changelog($file);

        $this->assertMatchesStringSnapshot($changelog->getNextVersionReleaseNotes($releaseType));
    }

    /**
     * It should allow updating the changelog file
     *
     * @test
     * @dataProvider releaseTypeAndExpectedVersionProvider
     */
    public function should_allow_updating_the_changelog_file($releaseType)
    {
        $file = codecept_data_dir('lucatume/Cli/Models/changelog_1.md');

        $changelog = new Changelog($file);
        $updated = $changelog->getUpdatedContents($changelog->getNextVersion($releaseType), '2019-01-01');

        $this->assertMatchesStringSnapshot($updated);
    }

    /**
     * It should allow previewing the changelog updates
     *
     * @test
     * @dataProvider releaseTypeAndExpectedVersionProvider
     */
    public function should_allow_previewing_the_changelog_updates($releaseType)
    {
        $file = codecept_data_dir('lucatume/Cli/Models/changelog_1.md');

        $changelog = new Changelog($file);
        $updatePreview = $changelog->getUpdatePreview($changelog->getNextVersion($releaseType), '2019-01-01');

        $this->assertMatchesStringSnapshot($updatePreview);
    }

    /**
     * It should allow updating the changelog contents
     *
     * @test
     * @dataProvider releaseTypeAndExpectedVersionProvider
     */
    public function should_allow_updating_the_changelog_contents($releaseType)
    {
        $file = codecept_data_dir('lucatume/Cli/Models/changelog_1.md');
        $outputFile = codecept_output_dir("changelog_1_{$releaseType}.md");

        if (file_exists($outputFile) && !unlink($outputFile)) {
            throw new \RuntimeException("Could not remove {$outputFile}.");
        }

        $changelog = new Changelog($file);
        $changelog->updateContents($changelog->getNextVersion($releaseType), '2019-01-01', $outputFile);

        $this->assertMatchesStringSnapshot(file_get_contents($outputFile));
    }
}
