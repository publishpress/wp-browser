<?php

header('X-Test-Header-2: two');

if( $send_headers === true){
	header('X-Test-Header-3: three');
}

if( !empty($send_headers)) header('X-Test-Header-4: four');

$send_headers && header('X-Test-Header-5: five');
