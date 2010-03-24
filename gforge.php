#!/usr/bin/php -q
<?php
/**
 * GForge Command-line Interface
 *
 * Copyright 2005 GForge, LLC
 * http://gforge.org/
 *
 * @version   $Id: gforge.php,v 1.6 2006/02/15 16:50:45 marcelo Exp $
 *
 * This file is part of GForge.
 *
 * GForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GForge; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * GForge CLI main script
 *
 * This script parses command line parameters and passes control to the specified module
 * files.
 */

/**** CONFIGURATION SECTION ****/

/**
 * Directory where NuSOAP library is located (use trailing slash)
 */
define("NUSOAP_DIR",		dirname(__FILE__)."/nusoap/lib/");
/**
 * Directory where common include files and module scripts are located (use trailing slash)
 */ 
define("GFORGE_CLI_DIR",	dirname(__FILE__)."/include/");
/**
 * URL of your server's WSDL
 */
if (array_key_exists("GFORGE_WSDL", $_ENV)) {
	define("WSDL_URL",			$_ENV["GFORGE_WSDL"]);
} else if(getenv('GFORGE_WSDL')) {
	define("WSDL_URL",			getenv('GFORGE_WSDL'));
} else {
	define("WSDL_URL",			"http://acos.alcatel-lucent.com/soap/index.php?wsdl");
}

/**** END OF CONFIGURATION SECTION ****/

if (version_compare(phpversion(),'5.3','<')) {
	error_reporting(E_ALL);
} else {
	error_reporting(E_ALL & ~E_DEPRECATED);
}

if (function_exists('date_default_timezone_set') && function_exists('date_default_timezone_get'))
	date_default_timezone_set(date_default_timezone_get());

/* Include common files */
require_once(NUSOAP_DIR."nusoap.php");		// Main NuSOAP library
require_once(GFORGE_CLI_DIR."common.php");	// Common functions, variables and defines
require_once(GFORGE_CLI_DIR."GForgeSOAP.class.php");	// GForge's SOAP wrapper
require_once(GFORGE_CLI_DIR."Log.class.php");	// Logging class

// This is automatically done by PHP >= 4.3.0
// Code copied from http://ar2.php.net/install.unix.commandline
if (version_compare(phpversion(),'4.3.0','<') || !defined('STDIN')) {
	define('STDIN',fopen("php://stdin","r"));
	define('STDOUT',fopen("php://stdout","r"));
	define('STDERR',fopen("php://stderr","r"));
	register_shutdown_function( create_function( '' , 'fclose(STDIN); fclose(STDOUT); fclose(STDERR); return true;' ) );
}

// Global logging object
$LOG = new Log();

$function_index = 0;		// Points to the position where the information about which function to execute begins

/* Parse the parameters and options passed to the main script */
for ($i = 1; $i <= $argc-1; $i++) {
	// Show user the help screen
	if ($argv[$i] == "--help" || $argv[$i] == "-h") {
		display_help();
		exit(0);
	}

	// Verbose
	else if ($argv[$i] == "--verbose" || $argv[$i] == "-v") {
		// Increase verbose level
		$LOG->setLevel(1);
	}
	
	// Not a parameter for the main script (does not start with "-").
	// Then, it must be a name of a module or a name of a function
	else if (!preg_match("/^-/", $argv[$i])) {
		$function_index = $i;
		break;
	}
	
	// Unknown parameter
	else {
		exit_error("Unknown parameter: \"".$argv[$i]."\"");
	}
}

if (!$function_index) {		// No function was specified. Show the help.
	display_help();
	exit(0);
}

// Get the name of the module or the function to execute
$name = trim($argv[$function_index]);

// Now, check if the name corresponds to a module. It corresponds to a module
// if there exists a directory with that name. In that case, execute the "default.php"
// script in that directory
if (is_dir(GFORGE_CLI_DIR."modules/".$name)) {		// We've found a module with that name
	$script = GFORGE_CLI_DIR."modules/".$name."/default.php";
} else {
	$script = GFORGE_CLI_DIR."modules/default.php";
}

if (!file_exists($script)) {
	exit_error("Could not find file ".$script);
}

// At this point, we know which script we should execute.
// Now we need to prepare the environment for the script (common variables,
// pass the parameters, etc) 

// Set up the parameters for the script... we don't need to pass that script the parameters that were
// passed to THIS script
$PARAMS = array_slice($argv, $function_index);
$SOAP = new GForgeSOAP();

// Pass control to the appropiate script
include($script);

// End script
exit(0);



/////////////////////////////////////////////////////////////////////////////
/**
 * display_help - Show the help string
 */
function display_help() {
	echo <<<EOT
Syntax:
gforge [options] [module name] [function] [parameters]
* Options:
    -h or --help    Display this screen
    -v              Verbose

Available modules:
   * tracker
   * project
   * task
   * document
   
Available functions for the default module:
   * login: Begin a session with the server.
   * logout: Terminate a session

EOT;
}
?>
