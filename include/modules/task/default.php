<?php
/**
 * GForge Command-line Interface
 *
 * Copyright 2005 GForge, LLC
 * http://gforge.org/
 *
 * @version   $Id: default.php,v 1.2 2005/10/10 21:01:14 marcelo Exp $
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

// These are hard-coded in the database
define("STATUS_OPEN", 	1);
define("STATUS_CLOSED",	2);

// function to execute
// $PARAMS[0] is "task" (the name of this module) and $PARAMS[1] is the name of the function
$module_name = array_shift($PARAMS);		// Pop off module name
$function_name = array_shift($PARAMS);		// Pop off function name

switch ($function_name) {
case "list":
	task_do_list();
	break;
case "categories":
	task_do_categories();
	break;
case "add":
	task_do_add();
	break;
case "update":
	task_do_update();
	break;
case "groups":
	task_do_groups();
	break;
default:
	exit_error("Unknown function name: ".$function_name);
	break;
}

function task_do_list() {
	global $PARAMS, $SOAP, $LOG;
	
	$group_project_id = get_parameter($PARAMS, "group", true);
	if (!$group_project_id || !is_numeric($group_project_id)) {
		exit_error("You must specify the group ID as a valid number with the --group parameter");
	}

	$assigned_to = get_parameter($PARAMS, "assigned_to", true);
	if ($assigned_to && !is_numeric($assigned_to)) {
		exit_error("You must specify the user ID as a valid number");
	} else if (!$assigned_to) {
		$assigned_to = "";
	}
	
	$category = get_parameter($PARAMS, "category", true);
	if ($category && !is_numeric($category)) {
		exit_error("You must specify the category ID as a valid number");
	} else if (!$category) {
		$category = "";	
	}
	
	$status = get_parameter($PARAMS, "status", true);
	if (!is_null($status) && $status != 1 && $status != 2) {
		if (strtolower($status) == "open") $status = 1;
		else if (strtolower($status) == "closed") $status = 2;
		else exit_error("Status should be either 1 (open) or 2 (closed)");
	} else if (is_null($status)) {
		$status = "";
	}

	//TODO: What is this variable for?
	$group = "";
	
	$group_id = get_working_group($PARAMS);
	
	$cmd_params = array(
				"group_id"			=> $group_id,
				"group_project_id"	=> $group_project_id,
				"assigned_to"		=> $assigned_to,
				"status"			=> $status,
				"category"			=> $category,
				"group"				=> $group
				);
	$res = $SOAP->call("getProjectTasks", $cmd_params);
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);
}

function task_do_categories() {
	global $PARAMS, $SOAP, $LOG;
	
	$group_project_id = get_parameter($PARAMS, "group", true);
	if (!$group_project_id || !is_numeric($group_project_id)) {
		exit_error("You must specify the group ID as a valid number");
	}

	$group_id = get_working_group($PARAMS);
	
	$cmd_params = array(
					"group_id"			=> $group_id,
					"group_project_id"	=> $group_project_id
					);

	$res = $SOAP->call("getProjectTaskCategories", $cmd_params);
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);
}

/**
 * tracker_do_add - Add a new task
 */
function task_do_add() {
	global $PARAMS, $SOAP, $LOG;
	
	if (get_parameter($PARAMS, "help")) {
		echo <<<EOF
(TODO)
EOF;
		return;
	}
	
	$add_params = get_task_params(true);
	$add_desc = $add_params["desc"];
	$add_data = $add_params["data"];
	
	// Show summary
	echo <<<EOF
Confirm you want to add a new tracker with the following information:
Project: {$add_desc['group_name']}
Group: {$add_desc['group_project_name']}
Summary: {$add_desc['summary']}
Priority: {$add_desc['priority']}
Estimated hours: {$add_desc['hours']}
Start date: {$add_desc['start_date']}
End date: {$add_desc['end_date']}
Category: {$add_desc['category_name']}
% complete: {$add_desc['percent_complete']}
Assigned to: {$add_desc['assigned_to']}
Dependent on: {$add_desc['dependent_on']}
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
		// Everything is OK... add the task
		$cmd_params = array(
					"group_id"			=> $add_data["group_id"],
					"group_project_id"	=> $add_data["group_project_id"],
					"summary"			=> $add_data["summary"],
					"details"			=> $add_data["details"],
					"priority"			=> $add_data["priority"],
					"hours"				=> $add_data["hours"],
					"start_date"		=> $add_data["start_date"],
					"end_date"			=> $add_data["end_date"],
					"category_id"		=> $add_data["category_id"],
					"percent_complete"	=> $add_data["percent_complete"],
					"assigned_to"		=> $add_data["assigned_to"],
					"dependent_on"		=> $add_data["dependent_on"]
					);
		$res = $SOAP->call("addProjectTask", $cmd_params);
		if (($error = $SOAP->getError())) {
			$LOG->add($SOAP->responseData);
			exit_error($error, $SOAP->faultcode);
		}
		show_output($res);
	} else {
		exit_error("Submission aborted");
	}
}

function task_do_update() {
	global $PARAMS, $SOAP, $LOG;
	
	if (get_parameter($PARAMS, "help")) {
		echo <<<EOF
(add help)
EOF;

		return;
	}
	
	$update_params = get_task_params(false);
	$update_desc = $update_params["desc"];
	$update_data = $update_params["data"];

	// Show summary
	echo "Confirm you want to update the task with the following information:\n";
	echo "Project: ".$update_desc["group_name"]."\n";
	echo "Group: ".$update_desc["group_project_name"]."\n";
	echo "Task summary: ".$update_desc["original_summary"]."\n";
	if (array_key_exists("summary", $update_desc)) {
		echo "> Summary: ".$update_desc["summary"]."\n";
	}
	if (array_key_exists("priority", $update_desc)) {
		echo "> Priority: ".$update_desc["priority"]."\n";
	}
	if (array_key_exists("hours", $update_desc)) {
		echo "> Estimated hours: ".$update_desc["hours"]."\n";
	}
	if (array_key_exists("start_date", $update_desc)) {
		echo "> Starting date: ".$update_desc["start_date"]."\n";
	}
	if (array_key_exists("end_date", $update_desc)) {
		echo "> Ending date: ".$update_desc["end_date"]."\n";
	}
	if (array_key_exists("category_name", $update_desc)) {
		echo "> Category: ".$update_desc["category_name"]."\n";
	}
	if (array_key_exists("percent_complete", $update_desc)) {
		echo "> Percent complete: ".$update_desc["percent_complete"]."\n";
	}
	if (array_key_exists("assigned_to", $update_desc)) {
		echo "> Assigned to: ".$update_desc["assigned_to"]."\n";
	}
	if (array_key_exists("dependent_on", $update_desc)) {
		echo "> Dependent on: ".$update_desc["dependent_on"]."\n";
	}
	if (array_key_exists("status", $update_desc)) {
		echo "> Status: ".$update_desc["status"]."\n";
	}
	if (array_key_exists("details", $update_desc)) {
		echo "> Details: \n".$update_desc["details"]."\n";
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
	
	if ($input == "yes" || $input == "y") {
		if (array_key_exists("summary", $update_data)) {
			$update_params["summary"] = $update_data["summary"];
		}
		if (array_key_exists("priority", $update_data)) {
			$update_params["priority"] = $update_data["priority"];
		}
		if (array_key_exists("hours", $update_data)) {
			$update_params["hours"] = $update_data["hours"];
		}
		if (array_key_exists("start_date", $update_data)) {
			$update_params["start_date"] = $update_data["start_date"];
		}
		if (array_key_exists("end_date", $update_data)) {
			$update_params["end_date"] = $update_data["end_date"];
		}
		if (array_key_exists("category_id", $update_data)) {
			$update_params["category_id"] = $update_data["category_id"];
		}
		if (array_key_exists("percent_complete", $update_data)) {
			$update_params["percent_complete"] = $update_data["percent_complete"];
		}
		if (array_key_exists("assigned_to", $update_data)) {
			$update_params["assigned_to"] = $update_data["assigned_to"];
		}
		if (array_key_exists("dependent_on", $update_data)) {
			$update_params["dependent_on"] = $update_data["dependent_on"];
		}
		if (array_key_exists("details", $update_data)) {
			$update_params["details"] = $update_data["details"];
		}
		if (array_key_exists("status_id", $update_data)) {
			$update_params["status_id"] = $update_data["status_id"];
		}
		
		$update_params["group_id"] = $update_data["group_id"];
	
		//TODO: Manage the new group_project_id
		$update_params["new_group_project_id"] = $update_params["group_project_id"];
		
		$res = $SOAP->call("updateProjectTask", $update_params);
		if (($error = $SOAP->getError())) {
			$LOG->add($SOAP->responseData);
			exit_error($error, $SOAP->faultcode);
		}
		show_output($res);
	}

}

/**
 * Get the variables for a task from the command line. This function is used when
 * adding/updating a task
 * 
 * @param bool	Specify that we're getting the variables for adding a task and not updating
 * @return array
 */
function get_task_params($adding = false) {
	global $PARAMS, $SOAP, $LOG;
	$group_id = get_working_group($PARAMS);
	$ret = array();
	$ret["data"] = array();
	$ret["desc"] = array();
	
	$updating = !$adding;		// we're updating if and only if we're not adding
	
	$group_project_id = get_parameter($PARAMS, "group", true);
	if (!$group_project_id || !is_numeric($group_project_id)) {
		exit_error("You must specify the group ID as a valid number");
	}
	
	// Force the input of the task ID only if we're updating
	if ($updating) {
		if (!($project_task_id = get_parameter($PARAMS, "id", true))) {
			$project_task_id = get_user_input("ID of the task to modify: ");
		}
		if (!$project_task_id || !is_numeric($project_task_id)) {
			exit_error("You must specify the task ID as a valid number");
		}
		
		// check the task ID is valid
		$tasks = $SOAP->call("getProjectTasks", array("group_id" => $group_id, "group_project_id" => $group_project_id, 
			"assigned_to" => "", "status" => "", "category" => "", "group" => ""));
		if (($error = $SOAP->getError())) {
			$LOG->add($SOAP->responseData);
			exit_error($error, $SOAP->faultcode);
		}
		$original_data = array();
		foreach ($tasks as $task) {
			if ($task["project_task_id"] == $project_task_id) {
				$original_data = $task;
				$original_summary = $task["summary"];
				break;
			}
		}
		
		// The task wasn't found
		if (count($original_data) == 0) {
			exit_error("The task #".$project_task_id." doesn't exist");
		}
	}

	
	// Check the summary
	if (!($summary = get_parameter($PARAMS, "summary")) && $adding) {
		$summary = get_user_input("Summary for this task: ");
	}
	$summary = trim($summary);
	if ($adding && !$summary) {		// Summary is required only if adding an artifact
		exit_error("You must specify a summary for this item");
	}
	
	// Check the details
	if (!($details = get_parameter($PARAMS, "details")) && $adding) {
		$details = get_user_input("Details for this task: ");
	}
	$details = trim($details);
	if ($adding && !$details) {
		exit_error("You must specify a detail for this item");
	}
	
	// Check the priority
	if (!($priority = get_parameter($PARAMS, "priority", true)) && $adding) {
		// set a default value (only if adding)
		$priority = 3;
	}
	if (!is_null($priority) && (!is_numeric($priority) || $priority < 1 || $priority > 5)) {
		exit_error("The priority must be a number between 1 and 5");
	}

	// Check the estimated hours
	if (!($hours = get_parameter($PARAMS, "hours", true)) && $adding) {
		// set a default value (only if adding)
		exit_error("You must define the estimated hours with the --hours parameter");
	}
	if (!is_null($hours) && !is_numeric($hours)) {
		exit_error("The estimated hours must be a valid number");
	}
	
	// Check the start date
	$start_date = get_parameter($PARAMS, "start_date", true);
	if ($start_date) {
		if (($date_error = check_date($start_date))) {
			exit_error("The starting date is invalid: ".$date_error);
		} else {
			$start_date = convert_date($start_date);
		}
	} else if ($adding) {
		// set a default value (only if adding)
		$start_date = time();
	}
	$start_date_desc = strftime("%Y-%m-%d", $start_date);
	
	// Check the end date
	$end_date = get_parameter($PARAMS, "end_date", true);
	if ($end_date) {
		if (($date_error = check_date($end_date))) {
			exit_error("The ending date is invalid: ".$date_error);
		} else {
			$end_date = convert_date($end_date);
		}
	} else if ($adding) {
		// set a default value (only if adding): one week after the starting date
		$end_date = $start_date + (60 * 60 * 24 * 7);
	}
	$end_date_desc = strftime("%Y-%m-%d", $end_date);

	// Check the category
	if (!($category_id = get_parameter($PARAMS, "category", true)) && $adding) {
		$category_id = 100;		// "none"
	}
	if ($category_id && !is_numeric($category_id)) {
		exit_error("The category ID must be a valid number");
	}
	
	// Check the percent
	if (!($percent_complete = get_parameter($PARAMS, "percent", true)) && $adding) {
		// default value if adding
		$percent_complete = 0;
	}
	if (!is_null($percent_complete) && (!is_numeric($percent_complete) || $percent_complete < 0 || $percent_complete > 100 || $percent_complete % 5 != 0)) {
		exit_error("The percent must be a number divisible by 5 between 0 and 100");
	}
	
	// Check the status (only if updating)
	$status_desc = "";
	if ($updating) {
		if (($status_id = get_parameter($PARAMS, "status", true))) {
			if (strtolower($status_id) == "open" || strtolower($status_id) == "o" || $status_id == STATUS_OPEN) {
				$status_id = STATUS_OPEN;
				$status_desc = "Open";
			} elseif (strtolower($status_id) == "closed" || strtolower($status_id) == "c" || $status_id == STATUS_CLOSED) {
				$status_id = STATUS_CLOSED;
				$status_desc = "Closed";
			} else {
				exit_error("Status must be either ".STATUS_OPEN." (open) or ".STATUS_CLOSED." (closed)"); 
			}
		}
	}
	
	// assigned_to is a list of comma-separated user IDs
	$assigned_to = get_parameter($PARAMS, "assigned_to", true);
	if ($assigned_to) {
		// special value
		if (strtolower($assigned_to) == "nobody") {
			$assigned_to = array(100);
		} else {
			$assigned_to = split(",", $assigned_to);
			
			//check they're all valid ints
			for ($i = 0; $i < count($assigned_to); $i++) {
				if (!is_numeric($assigned_to[$i])) {
					exit_error("The list of users must be a comma-separated list of valid users IDs");
				} else {
					$assigned_to[$i] = intval($assigned_to[$i]);
				}
			}
		}
	} elseif ($adding) {
		$assigned_to = array();
	}
	
	// dependent_on is a list of comma-separated task IDs
	$dependent_on = get_parameter($PARAMS, "dependent_on", true);
	if ($dependent_on) {
		// special value
		if (strtolower($dependent_on) == "none") {
			$dependent_on = array();
		} else {
			$dependent_on = split(",", $dependent_on);
			
			//check they're all valid ints
			for ($i = 0; $i < count($dependent_on); $i++) {
				if (!is_numeric($dependent_on[$i])) {
					exit_error("The list of dependent tasks must be a comma-separated list of valid task IDs");
				} else {
					$dependent_on[$i] = intval($dependent_on[$i]);
				}
			}
		}
	} elseif ($adding) {
		$dependent_on = array();
	} else {	// if updating, set to null to indicate we don't want any changes
		$dependent_on = null;
	}
	
	$group_id = get_working_group($PARAMS);

	// Check for invalid IDs
	$group_res = $SOAP->call("getGroups", array("group_ids" => array($group_id)));
	if (count($group_res) == 0) {		// Group doesn't exist
		exit_error("Group ".$group_id." doesn't exist");
	}
	$group_name = $group_res[0]["group_name"];
	
	$project_group_res = $SOAP->call("getProjectGroups", array("group_id" => $group_id));
	if (($error = $SOAP->getError())) {
		$LOG->add($SOAP->responseData);
		exit_error($error, $SOAP->faultcode);
	}
	$found = false;
	foreach ($project_group_res as $project_group) {
		if ($project_group["group_project_id"] == $group_project_id) {
			$found = true;
			$group_project_name = $project_group["name"];
			break;
		}		
	}
	if (!$found) {
		exit_error("Group #".$group_project_id." doesn't exist");
	}
	
	// check the category_id exists
	$category_name = "";
	if ($category_id && $category_id != 100) {
		$categories_res = $SOAP->call("getProjectTaskCategories", array(
					"group_id" => $group_id, 
					"group_project_id" => $group_project_id
					));
		if (($error = $SOAP->getError())) {
			$LOG->add($SOAP->responseData);
			exit_error($error, $SOAP->faultcode);
		}
		
		$found = false;
		foreach ($categories_res as $category) {
			if ($category["category_id"] == $category_id) {
				$found = true;
				$category_name = $category["category_name"];
				break;
			}
		}
		
		if (!$found) {
			exit_error("Category #".$category_id." doesn't exist");
		}
	} elseif ($adding) {
		$category_name = "(none)";
	}
	
	// check the users IDs
	$assigned_to_names = "";
	if (count($assigned_to) > 0) {
		$users_res = $SOAP->call("getUsers", array("user_ids" => $assigned_to));
		if (($error = $SOAP->getError())) {
			$LOG->add($SOAP->responseData);
			exit_error($error, $SOAP->faultcode);
		}
		
		// check all IDs are valid
		foreach ($assigned_to as $user_id) {
			$found = false;
			foreach ($users_res as $user) {
				if ($user["user_id"] == $user_id) {
					$found = true;
					$assigned_to_names .= $user["firstname"]." ".$user["lastname"]." (".$user["user_name"]."), ";
					break;
				}
			}
			
			if (!$found) {
				exit_error("Invalid user ID: ".$user_id);
			}
		}
		// Remove trailing ,
		$assigned_to_names = preg_replace("/, \$/", "", $assigned_to_names);
	} elseif ($adding) {
		$assigned_to_names = "(nobody)";
	}
	
	// check the dependent tasks
	$dependent_on_names = "";
	if (count($dependent_on) > 0) {
		$tasks_res = $SOAP->call("getProjectTasks", array(
									"group_id" 			=> $group_id,
									"group_project_id"	=> $group_project_id,
									"assigned_to"		=> "",
									"status"			=> "",
									"category"			=> "",
									"group"				=> ""
								));
		if (($error = $SOAP->getError())) {
			$LOG->add($SOAP->responseData);
			exit_error($error, $SOAP->faultcode);
		}
		
		foreach ($dependent_on as $dependent_on_id) {
			$found = false;
			foreach ($tasks_res as $task) {
				if ($task["project_task_id"] == $dependent_on_id) {
					$found = true;
					$dependent_on_names .= $task["summary"].", ";
					break;
				}
			}
			if (!$found) {
				exit_error("Invalid task ID: ".$dependent_on_id);
			}
		}
		// Remove trailing ,
		$dependent_on_names = preg_replace("/, \$/", "", $dependent_on_names);
	} elseif ($adding || ($updating && !is_null($dependent_on))) {
		$dependent_on_names = "(none)";
	}

 	$ret["data"]["group_id"] = $group_id;
 	$ret["data"]["group_project_id"] = $group_project_id;
 	if ($updating) {
 		$ret["data"]["project_task_id"] = $project_task_id;
 		$ret["data"]["original_data"] = $original_data;
	 	if ($summary) $ret["data"]["summary"] = $summary;
	 	if ($details) $ret["data"]["details"] = $details;
	 	if (!is_null($priority)) $ret["data"]["priority"] = $priority;
	 	if (!is_null($hours)) $ret["data"]["hours"] = $hours;
	 	if ($start_date) $ret["data"]["start_date"] = $start_date;
	 	if ($end_date) $ret["data"]["end_date"] = $end_date;
	 	if ($category_id) $ret["data"]["category_id"] = $category_id;
	 	if (!is_null($percent_complete)) $ret["data"]["percent_complete"] = $percent_complete;
	 	if (count($assigned_to) > 0) $ret["data"]["assigned_to"] = $assigned_to;
	 	if (!is_null($dependent_on)) $ret["data"]["dependent_on"] = $dependent_on;
	 	if (!is_null($status_id)) $ret["data"]["status_id"] = $status_id;

	 	$ret["desc"]["group_name"] = $group_name; 
	 	$ret["desc"]["group_project_name"] = $group_project_name;
	 	$ret["desc"]["original_summary"] = $original_summary;
	 	if ($summary) $ret["desc"]["summary"] = $summary;
	 	if ($priority) $ret["desc"]["priority"] = $priority;
	 	if (!is_null($hours)) $ret["desc"]["hours"] = $hours;
	 	if ($start_date) $ret["desc"]["start_date"] = $start_date_desc;
	 	if ($end_date) $ret["desc"]["end_date"] = $end_date_desc;
	 	if ($category_name) $ret["desc"]["category_name"] = $category_name;
	 	if (!is_null($percent_complete)) $ret["desc"]["percent_complete"] = $percent_complete."%";
	 	if ($assigned_to_names) $ret["desc"]["assigned_to"] = $assigned_to_names;
	 	if ($dependent_on_names) $ret["desc"]["dependent_on"] = $dependent_on_names;
	 	if ($details) $ret["desc"]["details"] = $details;
	 	if ($status_desc) $ret["desc"]["status"] = $status_desc;
 	} else {
	 	$ret["data"]["summary"] = $summary;
	 	$ret["data"]["details"] = $details;
	 	$ret["data"]["priority"] = $priority;
	 	$ret["data"]["hours"] = $hours;
	 	$ret["data"]["start_date"] = $start_date;
	 	$ret["data"]["end_date"] = $end_date;
	 	$ret["data"]["category_id"] = $category_id;
	 	$ret["data"]["percent_complete"] = $percent_complete;
	 	$ret["data"]["assigned_to"] = $assigned_to;
	 	$ret["data"]["dependent_on"] = $dependent_on;

	 	$ret["desc"]["group_name"] = $group_name; 
	 	$ret["desc"]["group_project_name"] = $group_project_name;
	 	$ret["desc"]["summary"] = $summary;
	 	$ret["desc"]["priority"] = $priority;
	 	$ret["desc"]["hours"] = $hours;
	 	$ret["desc"]["start_date"] = $start_date_desc;
	 	$ret["desc"]["end_date"] = $end_date_desc;
	 	$ret["desc"]["category_name"] = $category_name;
	 	$ret["desc"]["percent_complete"] = $percent_complete."%";
	 	$ret["desc"]["assigned_to"] = $assigned_to_names;
	 	$ret["desc"]["dependent_on"] = $dependent_on_names;
	 	$ret["desc"]["details"] = $details;
 	}
 	
 	return $ret;
}

function task_do_groups() {
	global $PARAMS, $SOAP, $LOG;
	
	if (get_parameter($PARAMS, "help")) {
		return;
	}
	
	$group_id = get_working_group($PARAMS);
	
	$res = $SOAP->call("getProjectGroups", array("group_id" => $group_id));
	if (($error = $SOAP->getError())) {
		exit_error($error, $SOAP->faultcode);
	}
	
	show_output($res);

}



?>
