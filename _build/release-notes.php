#!/usr/bin/env php
<?php
/**
 * Updates the release notes in the CHANGELOG.md file reading git commits.
 */

exec('git describe --tags --abbrev=0', $latestTag);
var_dump($latestTag);
$latestTag = '2.4.2';
exec('git log '.$latestTag.'..HEAD --pretty=format:"%s"',$commits);
var_dump($commits);
