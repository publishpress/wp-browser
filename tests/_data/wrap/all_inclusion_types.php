<?php
include 'file_1.php';
include_once 'file_2.php';
require 'file_3.php';
require_once 'file_4.php';

include (__DIR__ . '/dir/file_1.php');
include_once ( __DIR__ . '/dir/file_2.php' );
require (__DIR__ . '/dir/file_3.php');
require_once ( __DIR__ . '/dir/file_4.php' );
