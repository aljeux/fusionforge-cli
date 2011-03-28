<?php
/**
 * GForge Command-line Interface
 *
 * Copyright 2005 GForge, LLC
 * http://fusionforge.org/
 *
 * @version   $Id: default.php,v 1.2 2005/10/10 21:01:14 marcelo Exp $
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

// function to execute
// $PARAMS[0] is "task" (the name of this module) and $PARAMS[1] is the name of the function
$module_name = array_shift($PARAMS);		// Pop off module name
$function_name = array_shift($PARAMS);		// Pop off function name

switch ($function_name) {
case "packages":
	frs_do_pkglist();
	break;
case "addpackage":
	frs_do_addpackage();
	break;
case "releases":
	frs_do_releaselist();
	break;
case "addrelease":
	frs_do_addrelease();
	break;
case "files":
	frs_do_filelist();
	break;
case "getfile":
	frs_do_getfile();
	break;
case "addfile":
	frs_do_addfile();
	break;
default:
	exit_error("Unknown function name: ".$function_name);
	break;
}

function frs_do_pkglist() {
	global $PARAMS, $SOAP, $LOG;
	
	$group_id = get_working_group($PARAMS);
	
	$res = $SOAP->call("getPackages", array("group_id" => $group_id));
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);
}

function frs_do_addpackage() {
	global $PARAMS, $SOAP, $LOG;
	
	$name = get_parameter($PARAMS, "name", true);
	if (!$name || strlen($name) == 0) {
		exit_error("You must enter the name of the package with the --name parameter");
	}
	
	$is_public = get_parameter($PARAMS, "public", true);
	if (is_null($is_public) || strtolower($is_public) == "y" || strtolower($is_public) == "yes" || $is_public == "1") {
		// by default, set package as public
		$is_public = 1;
	} else if (!is_null($is_public) && (strtolower($is_public) == "no" || strtolower($is_public) == "n" || $is_public == "0")) {
		$is_public = 0;
	} else {
		exit_error("The 'public' parameter must be either 1 (yes) or 0 (no)");
	}

	$group_id = get_working_group($PARAMS);

	$cmd_params = array(
					"group_id"		=> $group_id,
					"package_name"	=> $name,
					"is_public"		=> $is_public
				);
				
	$res = $SOAP->call("addPackage", $cmd_params);
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);
}

function frs_do_releaselist() {
	global $PARAMS, $SOAP, $LOG;
	
	if (!($package_id = get_parameter($PARAMS, "package", true))) {
		// default value if adding
		exit_error("You must define a package with the --package parameter");
	}

	$group_id = get_working_group($PARAMS);


	$res = $SOAP->call("getReleases", array("group_id" => $group_id, "package_id" => $package_id));
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}

	// Temporary hack.
	// Remove the notes & changes column to provide a valid output.
	if (is_array($res)) {
		for($i=0;$i<count($res);$i++) {
			unset($res[$i]['notes']);
			unset($res[$i]['changes']);
		}
	}

	show_output($res);
}

function frs_do_addrelease() {
	global $PARAMS, $SOAP, $LOG;
	
	if (!($package_id = get_parameter($PARAMS, "package", true))) {
		exit_error("You must define a package with the --package parameter");
	}

	$name = get_parameter($PARAMS, "name", true);
	if (!$name || strlen($name) == 0) {
		exit_error("You must enter the name of the package with the --name parameter");
	}
	
	$notes = get_parameter($PARAMS, "notes", true);
	if (!$notes || strlen($notes) == 0) {
		$notes = "";
	}

	$changes = get_parameter($PARAMS, "changes", true);
	if (!$changes || strlen($changes) == 0) {
		$changes = "";
	}

	$release_date = get_parameter($PARAMS, "date", true);
	if ($release_date) {
		if (($date_error = check_date($release_date))) {
			exit_error("The starting date is invalid: ".$date_error);
		} else {
			$release_date = convert_date($release_date);
		}
	} else {
		$release_date = time();
	}

	$group_id = get_working_group($PARAMS);

	// Validate that the package exists
	$pkg_res = $SOAP->call("getPackages", array("group_id" => $group_id));
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	$found = false;
	foreach ($pkg_res as $pkg) {
		if ($pkg["package_id"] == $package_id) $found = true;
	}
	
	if (!$found) {
		exit_error("Package #".$package_id." does not belong to the project");
	}
	
	$add_params = array(
						"group_id"		=> $group_id,
						"package_id"	=> $package_id,
						"name"			=> $name,
						"notes"			=> $notes,
						"changes"		=> $changes,
						"release_date"	=> $release_date
					);

	$res = $SOAP->call("addRelease", $add_params);
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);
}

function frs_do_filelist() {
	global $PARAMS, $SOAP, $LOG;
	
	if (!($package_id = get_parameter($PARAMS, "package", true))) {
		exit_error("You must define a package with the --package parameter");
	}

	if (!($release_id = get_parameter($PARAMS, "release", true))) {
		exit_error("You must define a release with the --release parameter");
	}

	$group_id = get_working_group($PARAMS);

	$cmd_params = array(
					"group_id" => $group_id,
					"package_id" => $package_id,
					"release_id" => $release_id,
				);
	$res = $SOAP->call("getFiles", $cmd_params);
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}

	show_output($res);
}

function frs_do_getfile() {
	global $PARAMS, $SOAP, $LOG;
	
	if (!($package_id = get_parameter($PARAMS, "package", true))) {
		exit_error("You must define a package with the --package parameter");
	}

	if (!($release_id = get_parameter($PARAMS, "release", true))) {
		exit_error("You must define a release with the --release parameter");
	}

	if (!($file_id = get_parameter($PARAMS, "id", true))) {
		exit_error("You must define a file with the --id parameter");
	}

	// Should we save the contents to a file?
	$output = get_parameter($PARAMS, "output", true); 
	if ($output) {
		if (file_exists($output)) {
			$sure = get_user_input("File $output already exists. Do you want to overwrite it? (y/n): ");
			if (strtolower($sure) != "y" && strtolower($sure) != "yes") {
				exit_error("Retrieval of file aborted");
			}
		}
	}

	$group_id = get_working_group($PARAMS);

	$cmd_params = array(
					"group_id" => $group_id,
					"package_id" => $package_id,
					"release_id" => $release_id,
					"file_id" => $file_id
				);

	$res = $SOAP->call("getFile", $cmd_params);
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	$file = base64_decode($res);

	if ($output) {
		while (!($fh = @fopen($output, "wb"))) {
			echo "Couldn't open file ".$output." for writing.\n";
			$output = "";
			while (!$output) {
				$output = get_user_input("Please specify a new file name: ");
			}
		}
		
		fwrite($fh, $file, strlen($file));
		fclose($fh);
		
		echo "File retrieved successfully.\n";
	} else {
		echo $file;		// if not saving to a file, output to screen
	}
}

function frs_do_addfile() {
	global $PARAMS, $SOAP, $LOG;

	if (!($package_id = get_parameter($PARAMS, "package", true))) {
		exit_error("You must define a package with the --package parameter");
	}

	if (!($release_id = get_parameter($PARAMS, "release", true))) {
		exit_error("You must define a release with the --release parameter");
	}
	
	if (!($file = get_parameter($PARAMS, "file", true))) {
		exit_error("You must define a file with the --file parameter");
	} else if (!file_exists($file)) {
		exit_error("File '$file' doesn't exist");
	} else if (!($fh = fopen($file, "rb"))) {
		exit_error("Could not open '$file' for reading");
	}
	
	if (!($type_id = get_parameter($PARAMS, "type", true))) {
		$type_id = 9999;			// 9999 = "other"
	}
	
	if (!($processor_id = get_parameter($PARAMS, "processor", true))) {
		$processor_id = 9999;			// 9999 = "other"
	}

	$release_time = get_parameter($PARAMS, "date", true);
	if ($release_time) {
		if (($date_error = check_date($release_time))) {
			exit_error("The release date is invalid: ".$date_error);
		} else {
			$release_time = convert_date($release_time);
		}
	} else {
		$release_time = time();
	}
	
	$name = basename($file);
	$contents = fread($fh, filesize($file));
	$base64_contents = base64_encode($contents);
	
	fclose($fh);
	
	$group_id = get_working_group($PARAMS);
	
	$add_params = array(
					"group_id"			=> $group_id,
					"package_id"		=> $package_id,
					"release_id"		=> $release_id,
					"name"				=> $name,
					"base64_contents"	=> $base64_contents,
					"type_id"			=> $type_id,
					"processor_id"		=> $processor_id,
					"release_time"		=> $release_time
				);

	$res = $SOAP->call("addFile", $add_params);
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);
}