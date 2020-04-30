#!/usr/bin/env php
<?php
/**
 * Updates the release notes in the CHANGELOG.md file reading git commits.
 */

exec('git describe --tags --abbrev=0', $latestTag);
var_dump($latestTag);
exec('git log '.reset($latestTag).'..HEAD --pretty=format:"%s"',$commits);
$commitFilterPattern = '/^(?<type>(fix|feat|doc|refactor))/';
$commits = array_filter($commits, static function($commit)use($commitFilterPattern){
    return preg_match($commitFilterPattern,$commit);
});
var_dump($commits);
