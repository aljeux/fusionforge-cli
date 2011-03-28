<?php
/**
 * GForge Command-line Interface
 *
 * Copyright 2005 GForge, LLC
 * http://fusionforge.org/
 *
 * @version   $Id: default.php,v 1.1 2005/10/20 15:19:09 marcelo Exp $
 *
 * This file is part of FusionForge.
 *
 * FusionForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with FusionForge; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
 
/**
* Variables passed by parent script:
* - $SOAP: Soap object to talk to the server
* - $PARAMS: parameters passed to this script
* - $LOG: object for logging of events
*/

require_once("CVSHandler.class.php");
require_once("SVNHandler.class.php");

// function to execute
// $PARAMS[0] is "scm" (the name of this module) and $PARAMS[1] is the name of the function
$module_name = array_shift($PARAMS);		// Pop off module name
$function_name = array_shift($PARAMS);		// Pop off function name

switch ($function_name) {
case "info":
	scm_do_info();
	break;
case "checkout":
	scm_do_checkout();
	break;
case "list":
	scm_do_list();
	break;
case "update":
	scm_do_update();
	break;
case "commit":
	scm_do_commit();
	break;
default:
	exit_error("Unknown function name: ".$function_name);
	break;
}

/**
 * Helper function which will be used extensively
 * @return	array	Array with information about the SCM
 */
function get_scm_data() {
	global $PARAMS, $SOAP, $LOG;
	static $scm_data = null;
	
	if (!$scm_data) {
		$group_id = get_working_group($PARAMS);
		$res = $SOAP->call("getSCMData", array("group_id" => $group_id));
		if (($error = $SOAP->getError())) {
			$LOG->add($SOAP->responseData);
			exit_error($error, $SOAP->faultcode);
		}
		
		$scm_data = $res;
	}
	
	return $scm_data;

}

function scm_do_info() {
	global $PARAMS, $SOAP, $LOG;
	
	$scm_data = get_scm_data();	
	
	show_output($scm_data);
}

/**
 * Factory function
 * Retrieve a SCM Handler
 */
function& get_scm_handler() {
	global $PARAMS, $SOAP, $LOG;

	$scm_data = get_scm_data();
	
	if (!$scm_data) {
		exit_error("No SCM data for project");
	}
	
	switch (strtolower($scm_data["type"])) {
	case "cvs":
		$handler = new CVSHandler($SOAP, $LOG, $scm_data);
		break;
	case "svn":
		$handler = new SVNHandler($SOAP, $LOG, $scm_data);
		break;
	default:
		exit_error("No SCM handler found for \"".$scm_data["type"]."\"");
	}
	
	return $handler;
}

function scm_do_checkout() {
	global $PARAMS, $SOAP, $LOG;
	
	$scm_data = get_scm_data();
	$scm_handler = get_scm_handler();
	
	$anonymous = (get_parameter($PARAMS, array("anonymous", "a"))) ? true : false;
	$destdir = get_parameter($PARAMS, "dir", true);
	$module = get_parameter($PARAMS, "module", true);
	if (!$module) {
		$module = $scm_data["module"];
	}
	if ($destdir) {
		if (!@chdir($destdir)) {
			exit_error("Could not change working directory to ".$destdir);
		}
	}
	
	$scm_handler->checkout($module, $anonymous);
	
}

function scm_do_list() {
	global $PARAMS, $SOAP, $LOG;
	
	$scm_data = get_scm_data();
	$scm_handler = get_scm_handler();
	
	$path = get_parameter($PARAMS, "path", true);
	$module = get_parameter($PARAMS, "module", true);
	$root = get_parameter($PARAMS, array("root", "r"));
	if (!$module) {
		$module = $scm_data["module"];
	}
	if ($root) {
		// not specifying a module will display the root of the repository
		$module = "";
	}
	
	$scm_handler->showFiles($module, $path);
}

function scm_do_update() {
	global $PARAMS, $SOAP, $LOG;
	
	$dir = get_parameter($PARAMS, "dir", true);
	if ($dir) {
		if (!@chdir($dir)) {
			exit_error("Could not change working directory to ".$destdir);
		}
	}

	$scm_handler = get_scm_handler();
	$scm_handler->update();
}

function scm_do_commit() {
	global $PARAMS, $SOAP, $LOG;
	
	$dir = get_parameter($PARAMS, "dir", true);
	if ($dir) {
		if (!@chdir($dir)) {
			exit_error("Could not change working directory to ".$destdir);
		}
	}
	
	$message = get_parameter($PARAMS, array("message", "m"), true);

	$scm_handler = get_scm_handler();
	$scm_handler->commit($message);
}
?>