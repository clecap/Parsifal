<?php

/*** Serves selected PHP constants of config.php as JavaScript globals (window.NAME), so that they are defined only once, in config.php */

require_once (__DIR__ . "/config.php");                                              // single source of truth for the shared constants

// names of the constants from config.php which are exported to JavaScript
const JS_EXPORTS = array ("ERROR_PARSER_START", "ERROR_PARSER_END");

// deliver as JavaScript and prevent stale copies after config.php changes
header ("Content-Type: application/javascript; charset=utf-8");
header ("Cache-Control: no-cache");

// emit one assignment per exported constant; json_encode provides correct JavaScript quoting
foreach (JS_EXPORTS as $name) { echo "window.$name = " . json_encode (constant ($name), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n"; }

?>
