<?php
/**
 * GForge Command-line Interface
 *
 * Copyright 2005 GForge, LLC
 * http://gforge.org/
 *
 * @version   $Id: default.php,v 1.6 2005/10/20 18:55:31 marcelo Exp $
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
* Variables passed by parent script:
* - $SOAP: Soap object to talk to the server
* - $PARAMS: parameters passed to this script
* - $LOG: object for logging of events
*/

// These constants are defined in ArtifactExtraField.class.php (GForge's source). They
// declare the different types of extra fields available for an artifact type
define('ARTIFACT_EXTRAFIELDTYPE_SELECT',1);
define('ARTIFACT_EXTRAFIELDTYPE_CHECKBOX',2);
define('ARTIFACT_EXTRAFIELDTYPE_RADIO',3);
define('ARTIFACT_EXTRAFIELDTYPE_TEXT',4);
define('ARTIFACT_EXTRAFIELDTYPE_MULTISELECT',5);
define('ARTIFACT_EXTRAFIELDTYPE_TEXTAREA',6);
define('ARTIFACT_EXTRAFIELDTYPE_STATUS',7);
define('ARTIFACT_EXTRAFIELDTYPE_INTEGER',10);

// function to execute
// $PARAMS[0] is "tracker" (the name of this module) and $PARAMS[1] is the name of the function
$module_name = array_shift($PARAMS);		// Pop off module name
$function_name = array_shift($PARAMS);		// Pop off function name

switch ($function_name) {
case "list":
	tracker_do_list();
	break;
case "typelist":
	tracker_do_typelist();
	break;
case "add":
	tracker_do_add();
	break;
case "update":
	tracker_do_update();
	break;
case "messages":
	tracker_do_messages();
	break;
case "addmessage":
	tracker_do_addmessage();
	break;
case "files":
	tracker_do_files();
	break;
case "getfile":
	tracker_do_getfile();
	break;
case "addfile":
	tracker_do_addfile();
	break;
case "technicians":
	tracker_do_technicians();
	break;
default:
	exit_error("Unknown function name: ".$function_name);
	break;
}

///////////////////////////////
/**
 * tracker_do_list - List of trackers
 */
function tracker_do_list() {
	global $PARAMS, $SOAP, $LOG;

	$cmd_params = array();

	$group_artifact_id = get_parameter($PARAMS, "type", true);
	if (!$group_artifact_id) {
		exit_error("You must specify a tracker type ID using the --type parameter");
	}

	$cmd_params["group_artifact_id"] = $group_artifact_id;

	if ( ($assigned_to = get_parameter($PARAMS, "assigned_to", true)) ) {
		$cmd_params["assigned_to"] = intval($assigned_to);
	} else {
		$cmd_params["assigned_to"] = "";
	}

	if ( ($status = get_parameter($PARAMS, "status", true)) !== null ) {
		$cmd_params["status"] = intval($status);
	} else {
		$cmd_params["status"] = "";
	}

	$group_id = get_working_group($PARAMS);	
	$cmd_params["group_id"] = $group_id;
	
	$res = $SOAP->call("getArtifacts", $cmd_params);

	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	if (!is_array($res) || count($res) == 0) {
		echo "No trackers were found for this type.";
	} else {
		// Temporary hack.
		// Remove the details column to provide a valid output.
		for($i=0;$i<count($res);$i++) {
			unset($res[$i]['details']);
		}
		show_output($res);
	}
}

/**
 * tracker_do_typelist - List of tracker type
 */
function tracker_do_typelist() {
	global $PARAMS, $SOAP, $LOG;

	$group_id = get_working_group($PARAMS);
	
	$res = $SOAP->call("getArtifactTypes", array("group_id" => $group_id));
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);
}

/**
 * tracker_do_add - Add a new tracker
 */
function tracker_do_add() {
	global $PARAMS, $SOAP, $LOG;
	
	if (get_parameter($PARAMS, "help")) {
		echo <<<EOF
Add a new item in a tracker.
Parameters:
--project=<name>: Name of the project in which this item will be added. If you specified the name of
    the working project when you logged in, this parameter is not needed.
--type=<id>: Specify the ID of the tracker the item will be added in. The function "typelist" shows a list
    of available types and their corresponding IDs.
--priority=<number>: Item priority. Goes from 1 (lowest priority) to 5 (top priority). If not specified, 
    defaults to 3.
--assigned_to=<id>: Comma-separated list of user IDs this item should be assigned to (optional)
--summary=<text>: Description of this item (i.e. "Bug when clicking the Help button")
--details=<text>: Detailed description of the item
EOF;
		return;
	}
	
	$add_params = get_artifact_params(true);
	$add_desc = $add_params["desc"];
	$add_data = $add_params["data"];
	
	// Show summary
	// TODO: Show extra field summary
	echo <<<EOF
Confirm you want to add a new tracker with the following information:
Project: {$add_desc['group_name']}
Tracker type: {$add_desc['artifact_type_name']}
Priority: {$add_desc['priority']}
Assigned to: {$add_desc['assigned_to_name']}
Summary: {$add_desc['summary']}
Details:
{$add_desc['details']}

EOF;
	
	// ask for confirmation if the --noask param is not set
	if (!get_parameter($PARAMS, array("n", "noask"))) {
		$input = get_user_input("Is this information correct? (y/n): ");
		$input = strtolower($input);
	} else {
		$input = "y";		// commit changes directly
	}

	if ($input == "yes" || $input == "y") {
		// Everything is OK... add the artifact
		$cmd_params = array(
				"group_id"			=> $add_data["group_id"],
				"group_artifact_id"	=> $add_data["group_artifact_id"],
				"priority"			=> $add_data["priority"],
				"assigned_to"		=> $add_data["assigned_to"],
				"summary"			=> $add_data["summary"],
				"details"			=> $add_data["details"],
				"extra_fields"		=> $add_data["extra_fields_data"]
				);
		$res = $SOAP->call("addArtifact", $cmd_params);
		if (($error = $SOAP->getError())) {
			$LOG->add($SOAP->responseData);
			exit_error($error, $SOAP->faultcode);
		}
		show_output($res);
	} else {
		exit_error("Submission aborted");
	}
}

/**
 * tracker_do_update - Update a tracker
 */
function tracker_do_update() {
	global $PARAMS, $SOAP, $LOG, $extra_fields;
	
	if (get_parameter($PARAMS, "help")) {
		echo <<<EOF
(add help)
EOF;

		return;
	}
	
	$update_params = get_artifact_params(false);
	$update_desc = $update_params["desc"];
	$update_data = $update_params["data"];

	// Show summary
	echo "Confirm you want to update the tracker with the following information:\n";
	echo "Project: ".$update_desc["group_name"]."\n";
	echo "Tracker type: ".$update_desc["artifact_type_name"]."\n";
	echo "Summary: ".$update_desc["original_summary"]."\n";
	if (array_key_exists("priority", $update_desc)) {
		echo "> Priority: ".$update_desc["priority"]."\n";
	}
	if (array_key_exists("assigned_to_name", $update_desc)) {
		echo "> Assigned to: ".$update_desc["assigned_to_name"]."\n";
	}
	if (array_key_exists("summary", $update_desc)) {
		echo "> Summary: ".$update_desc["summary"]."\n";
	}
	
	//NOTE: When updating, the details can't be changed. Instead of that,
	//a new message is added, and we don't want that
	if (array_key_exists("details", $update_desc)) {
		echo "> Details: \n";
		echo $update_desc["details"]."\n";
	}

	// Show extra fields also.
	foreach ($update_data["extra_fields_data"] as $new_extra_field) {
		$extra_field_id = $new_extra_field["extra_field_id"];
		$extra_field_name = '';
		foreach ($extra_fields as $k => $v) {
			if ($v['extra_field_id'] == $extra_field_id)
				$extra_field_name = $v['field_name']; 
		}
		print "> $extra_field_name: ".$new_extra_field["field_data"]."\n";
	}

	// ask for confirmation if the --noask param is not set
	if (!get_parameter($PARAMS, array("n", "noask"))) {
		$input = get_user_input("Is this information correct? (y/n): ");
		$input = strtolower($input);
	} else {
		$input = "y";		// commit changes directly
	}

	// Update the information array
	$update_params = $update_data["original_data"];
	$update_params["description"] = $update_params["details"];
	$update_params["details"] = "";		// see comment above
	
	if ($input == "yes" || $input == "y") {
		if (array_key_exists("priority", $update_data)) {
			$update_params["priority"] = $update_data["priority"];
		}
		if (array_key_exists("assigned_to", $update_data)) {
			$update_params["assigned_to"] = $update_data["assigned_to"];
		}
		if (array_key_exists("summary", $update_data)) {
			$update_params["summary"] = $update_data["summary"];
		}
		if (array_key_exists("status_id", $update_data)) {
			$update_params["status_id"] = $update_data["status_id"];
		}
		
		$update_params["extra_fields_data"] = $update_params["extra_fields"];
		// include the extra fields
		foreach ($update_data["extra_fields_data"] as $new_extra_field) {
			$added = false;
			for ($i = 0; $i < count($update_params["extra_fields"]); $i++) {
				if ($update_params["extra_fields_data"][$i]["extra_field_id"] == $new_extra_field["extra_field_id"]) {
					$update_params["extra_fields_data"][$i] = $new_extra_field;	// overwrite old data with new data
					$added = true;
				}
			}
			// if it couldn't replace the old value, insert a new value
			if (!$added) {
				$update_params["extra_fields_data"][] = $new_extra_field;
			}
		}
		
		$update_params["group_id"] = $update_data["group_id"];
		//TODO: Manage the new artifact_type id
		$update_params["new_artifact_type_id"] = $update_params["group_artifact_id"];
		
		$res = $SOAP->call("updateArtifact", $update_params);
		if (($error = $SOAP->getError())) {
			$LOG->add($SOAP->responseData);
			exit_error($error, $SOAP->faultcode);
		}
		show_output($res);
	} else {
		exit_error("Submission aborted");
	}
}


function tracker_do_messages() {
	global $PARAMS, $SOAP, $LOG;
	
	$group_artifact_id = get_parameter($PARAMS, "type", true);
	if (!$group_artifact_id || !is_numeric($group_artifact_id)) {
		exit_error("You must specify the type ID as a valid number");
	}
	
	$artifact_id = get_parameter($PARAMS, "id", true);
	if (!$artifact_id || !is_numeric($artifact_id)) {
		exit_error("You must specify the artifact ID as a valid number");
	}
	
	$group_id = get_working_group($PARAMS);
	
	$cmd_params = array(
		"group_id"			=> $group_id,
		"group_artifact_id"	=> $group_artifact_id,
		"artifact_id"		=> $artifact_id
	);
	$res = $SOAP->call("getArtifactMessages", $cmd_params);
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);
}

function tracker_do_addmessage() {
	global $PARAMS, $SOAP, $LOG;
	
	$group_artifact_id = get_parameter($PARAMS, "type", true);
	if (!$group_artifact_id || !is_numeric($group_artifact_id)) {
		exit_error("You must specify the type ID as a valid number");
	}
	
	$artifact_id = get_parameter($PARAMS, "id", true);
	if (!$artifact_id || !is_numeric($artifact_id)) {
		exit_error("You must specify the artifact ID as a valid number");
	}
	
	$body = get_parameter($PARAMS, "message", true);
	if (strlen($body) == 0) {
		exit_error("You must specify the message");
	}
	
	$group_id = get_working_group($PARAMS);
	
	$cmd_params = array(
		"group_id"			=> $group_id,
		"group_artifact_id"	=> $group_artifact_id,
		"artifact_id"		=> $artifact_id,
		"body"				=> $body
	);
	$res = $SOAP->call("addArtifactMessage", $cmd_params);
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);
}

function tracker_do_files() {
	global $PARAMS, $SOAP, $LOG;
	
	$group_artifact_id = get_parameter($PARAMS, "type", true);
	if (!$group_artifact_id || !is_numeric($group_artifact_id)) {
		exit_error("You must specify the type ID as a valid number");
	}
	
	$artifact_id = get_parameter($PARAMS, "id", true);
	if (!$artifact_id || !is_numeric($artifact_id)) {
		exit_error("You must specify the artifact ID as a valid number");
	}
	
	$group_id = get_working_group($PARAMS);
	
	$cmd_params = array(
		"group_id"			=> $group_id,
		"group_artifact_id"	=> $group_artifact_id,
		"artifact_id"		=> $artifact_id,
	);

	$res = $SOAP->call("getArtifactFiles", $cmd_params);
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);
}

function tracker_do_getfile() {
	global $PARAMS, $SOAP, $LOG;
	
	$group_artifact_id = get_parameter($PARAMS, "type", true);
	if (!$group_artifact_id || !is_numeric($group_artifact_id)) {
		exit_error("You must specify the type ID as a valid number");
	}
	
	$artifact_id = get_parameter($PARAMS, "id", true);
	if (!$artifact_id || !is_numeric($artifact_id)) {
		exit_error("You must specify the artifact ID as a valid number");
	}
	
	$file_id = get_parameter($PARAMS, "file_id", true);
	if (!$file_id || !is_numeric($file_id)) {
		exit_error("You must specify the file ID as a valid number");
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
		"group_id"			=> $group_id,
		"group_artifact_id"	=> $group_artifact_id,
		"artifact_id"		=> $artifact_id,
		"file_id"			=> $file_id
	);
	
	$res = $SOAP->call("getArtifactFileData", $cmd_params);
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

function tracker_do_addfile() {
	global $PARAMS, $SOAP, $LOG;

	$group_artifact_id = get_parameter($PARAMS, "type", true);
	if (!$group_artifact_id || !is_numeric($group_artifact_id)) {
		exit_error("You must specify the type ID as a valid number");
	}
	
	$artifact_id = get_parameter($PARAMS, "id", true);
	if (!$artifact_id || !is_numeric($artifact_id)) {
		exit_error("You must specify the artifact ID as a valid number");
	}
	
	$description = get_parameter($PARAMS, "description", true);
	if (is_null($description)) $description = "";		// description wasn't specified

	$group_id = get_working_group($PARAMS);
	
	if (!($file = get_parameter($PARAMS, "file", true))) {
		exit_error("You must specify a file for uploading");
	}	
	
	while (!($fh = fopen($file, "rb"))) {
		echo "Couldn't open file ".$file." for reading.\n";
		$file = "";
		while (!$file) {
			$file = get_user_input("Please specify a new file name: ");
		}
	}
	
	$bin_contents = fread($fh, filesize($file));
	$base64_contents = base64_encode($bin_contents);
	$filename = basename($file);
	
	//TODO: Check file type
	$filetype = "";

	$cmd_params = array(
					"group_id"			=> $group_id,
					"group_artifact_id"	=> $group_artifact_id,
					"artifact_id"		=> $artifact_id,
					"base64_contents"	=> $base64_contents,
					"description"		=> $description,
					"filename"			=> $filename,
					"filetype"			=> $filetype 
				);
	
	$res = $SOAP->call("addArtifactFile", $cmd_params);
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);

}

function tracker_do_technicians() {
	global $PARAMS, $SOAP, $LOG;

	$group_artifact_id = get_parameter($PARAMS, "type", true);
	if (!$group_artifact_id || !is_numeric($group_artifact_id)) {
		exit_error("You must specify the type ID as a valid number");
	}
	
	$group_id = get_working_group($PARAMS);
	
	$cmd_params = array(
					"group_id"			=> $group_id,
					"group_artifact_id"	=> $group_artifact_id
				);
	
	$res = $SOAP->call("getArtifactTechnicians", $cmd_params);
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);

}

/**
 * Get the variables for an artifact from the command line. This function is used when
 * adding/updating an artifact
 * 
 * @param bool	Specify that we're getting the variables for adding an artifact and not updating
 * @return array
 */
function get_artifact_params($adding = false) {
	global $PARAMS, $SOAP, $LOG, $extra_fields;
	$group_id = get_working_group($PARAMS);
	$ret = array();
	
	$updating = !$adding;		// we're updating if and only if we're not adding
	
	// Check the type ID
	if (!($group_artifact_id = get_parameter($PARAMS, "type", true))) {
		$group_artifact_id = get_user_input("Type ID of the artifact: ");
	}
	if (!$group_artifact_id || !is_numeric($group_artifact_id)) {
		exit_error("You must specify the type ID of the artifact as a valid number");
	}
	
	// Force the input of the artifact ID only if we're updating
	if ($updating) {
		if (!($artifact_id = get_parameter($PARAMS, "id", true))) {
			$artifact_id = get_user_input("ID of the artifact to modify: ");
		}
		if (!$artifact_id || !is_numeric($artifact_id)) {
			exit_error("You must specify the artifact ID as a valid number");
		}
		
		// check the artifact ID is valid
		$artifacts = $SOAP->call("getArtifacts", array("group_id" => $group_id, "group_artifact_id" => $group_artifact_id, "assigned_to" => "", "status" => ""));
		if (($error = $SOAP->getError())) {
			$LOG->add($SOAP->responseData);
			exit_error($error, $SOAP->faultcode);
		}
		$original_data = array();
		foreach ($artifacts as $artifact) {
			if ($artifact["artifact_id"] == $artifact_id) {
				$original_data = $artifact;
				$artifact_summary = $artifact["summary"];
			}
		}
		
		// The artifact wasn't found
		if (count($original_data) == 0) {
			exit_error("The artifact #".$artifact_id." doesn't belong to tracker #".$group_artifact_id);
		}
	}
	
	// Check the priority
	if (!($priority = get_parameter($PARAMS, "priority", true)) && $adding) {
		// set a default value (only if adding)
		$priority = 3;
	}
	if ($priority && (!is_numeric($priority) || $priority < 1 || $priority > 5)) {
		exit_error("The priority must be a number between 1 and 5");
	}
	
	// ID of the user the artifact is assigned to
	if (!($assigned_to = get_parameter($PARAMS, "assigned_to", true)) && $adding) {
		$assigned_to = 100;		// 100 = nobody
	}
	
	// Status ID (only for updating)
	if ($updating) { 
		$status_id = get_parameter($PARAMS, "status", true);
	}

	// Check the summary
	if (!($summary = get_parameter($PARAMS, "summary")) && $adding) {
		$summary = get_user_input("Summary for this item: ");
	}
	$summary = trim($summary);
	if ($adding && !$summary) {		// Summary is required only if adding an artifact
		exit_error("You must specify a summary for this item");
	}
	
	// Check the details
	if (!($details = get_parameter($PARAMS, "details")) && $adding) {
		$details = get_user_input("Details for this item: ");
	}
	$details = trim($details);
	if ($adding && !$details) {
		exit_error("You must specify a detail for this item");
	}
	
	// Check for invalid IDs
	// Get the group
	$group_res = $SOAP->call("getGroups", array("group_ids" => array($group_id)));
	if (count($group_res) == 0) {		// Group doesn't exist
		exit_error("Group ".$group_id." doesn't exist");
	}
	$group_name = $group_res[0]["group_name"];
	
	// Get the artifact type
	$artifact_type_res = $SOAP->call("getArtifactTypes", array("group_id" => $group_id));
	if (is_array($artifact_type_res) && count($artifact_type_res) > 0) {
		$found = false;
		// Search the name of the selected artifact type in the array of artifact types for the project
		for ($i = 0; $i < count($artifact_type_res); $i++) {
			if ($artifact_type_res[$i]["group_artifact_id"] == $group_artifact_id) {
				$found = true;
				$artifact_type_name = $artifact_type_res[$i]["name"];
				$artifact_index = $i;
				break;
			}
		}
		
		if (!$found) {
			exit_error("Type number ".$group_artifact_id." doesn't belong to project ".$group_name);
		}
	} else {
		exit_error("Type number ".$group_artifact_id." doesn't belong to project ".$group_name);
	}
	
	// Get the extra fields for this artifact and validate the input
	$extra_fields_tmp = $artifact_type_res[$artifact_index]["extra_fields"];
	$extra_fields = array();
	$extra_fields_data = array();
	$efd_index = 0;
	// rebuild the array in a more convenient way
	foreach ($extra_fields_tmp as $extra_field) {
		$alias = $extra_field["alias"];
		if (strlen($alias) == 0) continue;
		$extra_fields[$alias] = $extra_field;
		
		// Get the value specified for this extra field (if any)
		$value = get_parameter($PARAMS, $alias, true);
		// the extra field wasn't specified but it is required...
		if ($adding && strlen($value) == 0 && $extra_field["is_required"]) {
			exit_error("You must specify the parameter '".$alias."'");
		}
		
		if (strlen($value) > 0) {
			$value_ok = false;

			switch ($extra_field["field_type"]) {
				case ARTIFACT_EXTRAFIELDTYPE_TEXT:
				case ARTIFACT_EXTRAFIELDTYPE_TEXTAREA:
					// this doesn't need validation
					$value_ok = true;
					break;
				case ARTIFACT_EXTRAFIELDTYPE_CHECKBOX:
				case ARTIFACT_EXTRAFIELDTYPE_MULTISELECT:
					if (strtolower($value) == "none") {
						$value = "100";
						$value_ok = true;
						break;
					}
					// in this case, $value is a list of comma-separated ids
					$available_values_str = "";
					// first get the list of the available values
					foreach ($extra_field["available_values"] as $available_value) {
						$available_values_str .= $available_value["element_id"]." (".$available_value["element_name"]."), ";
					}
					// remove trailing ,
					$available_values_str = preg_replace("/, \$/", "", $available_values_str);
					
					$value_ok = true;
					$values = split(",", $value);
					$invalid_values = array();		// list of invalid values entered by the user
					foreach ($values as $id) {
						$found = false;
						foreach ($extra_field["available_values"] as $available_value) {
							// note we are comparing strings
							if ("$id" == "".$available_value["element_id"]) {
								$found = true;
								break;
							}
						}
						if (!$found) {
							$value_ok = false;
							$invalid_values[] = $id;
						}
					}
					
					if (!$value_ok) {
						if (count($invalid_values) == 1) {
							$error = "Value ".$invalid_values[0]." is invalid for the field '".$extra_field["field_name"]."'. Available values are: ".$available_values_str;
						} else {
							$error = "Values ".implode(",",$invalid_values)." are invalid for the field '".$extra_field["field_name"]."'. Available values are: ".$available_values_str;
						}
					}
					break;
				case ARTIFACT_EXTRAFIELDTYPE_STATUS:
				case ARTIFACT_EXTRAFIELDTYPE_RADIO:
				case ARTIFACT_EXTRAFIELDTYPE_SELECT:
					// Map the value entered by the user to an existing element_id
					$available_values_str = "";
					foreach ($extra_field["available_values"] as $available_value) {
						$available_values_str .= $available_value["element_id"]." (".$available_value["element_name"]."), ";
						// note we are comparing strings
						if ( "".$available_value["element_id"] == "$value") {
							$value_ok = true;
						}
					}
					// remove trailing ,
					$available_values_str = preg_replace("/, \$/", "", $available_values_str);
					if (!$value_ok) {
						$error = "Value '$value' is invalid for the field '".$extra_field["field_name"]."'. Available values are: ".$available_values_str;
					}
					break;
			}
			
			if (!$value_ok) {
				exit_error($error);
			} else {
				$extra_fields_data[$efd_index] = array();
				$extra_fields_data[$efd_index]["extra_field_id"] = $extra_field["extra_field_id"];
				$extra_fields_data[$efd_index]["field_data"] = $value;
				$efd_index++;
			}
		}
	}
	
	// Get the user
	if ($assigned_to) {
		$users_res = $SOAP->call("getUsers", array("user_ids" => array($assigned_to)));
		if (!$SOAP->getError() && is_array($users_res) && count($users_res) > 0) {
			$assigned_to_name = $users_res[0]["firstname"]." ".$users_res[0]["lastname"]." (".$users_res[0]["user_name"].")";
		} else {
			exit_error("Invalid user ID: ".$assigned_to);
		}
	} else {
		$assigned_to_name = "(nobody)";
	}
	
	// return the data to insert
	$ret["data"]["group_id"]						= $group_id;
	$ret["data"]["group_artifact_id"]				= $group_artifact_id;
	if ($updating) 	{
		$ret["data"]["artifact_id"]					= $artifact_id;
		$ret["data"]["original_data"]				= $original_data;
		if ($status_id) $ret["data"]["status_id"]	= $status_id;
		if ($priority) $ret["data"]["priority"] 		= $priority;
		if ($assigned_to) $ret["data"]["assigned_to"] 	= $assigned_to;
		if ($summary) $ret["data"]["summary"] 			= $summary;
		if ($details) $ret["data"]["details"] 			= $details;

	} else {
		$ret["data"]["priority"] 					= $priority;
		$ret["data"]["assigned_to"] 				= $assigned_to;
		$ret["data"]["summary"] 					= $summary;
		$ret["data"]["details"] 					= $details;
	}
	$ret["data"]["extra_fields_data"]				= $extra_fields_data;
	
	// also return the textual description of the data
	$ret["desc"]["group_name"]						= $group_name;
	$ret["desc"]["artifact_type_name"]				= $artifact_type_name;
	if ($updating) $ret["desc"]["original_summary"]	= $artifact_summary;
	if ($priority) $ret["desc"]["priority"]			= $priority;
	if ($summary) $ret["desc"]["summary"]			= $summary;
	if ($details) $ret["desc"]["details"]			= $details;
	if ($assigned_to) $ret["desc"]["assigned_to_name"]	= $assigned_to_name;
	
	return $ret;

}
?>
