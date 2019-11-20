<?php
define('CONST_ONE','ONE');

if(defined('CONST_ONE') && CONST_ONE){
    define('CONST_TWO','TWO');
}

if(null !== constant('CONST_TWO')){
    define('CONST_THREE','THREE');
}

if(defined('TEST_CONST_ZERO') && TEST_CONST_ZERO === 'YES'){
    define('CONST_FOUR','FOUR');
}

if(!defined('CONST_FOUR') || CONST_FOUR === 'FOO'){
    define('CONST_FIVE','FIVE');
}
