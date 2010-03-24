<?php
/**
 * GForge Command-line Interface
 *
 * Copyright 2005 GForge, LLC
 * http://gforge.org/
 *
 * @version   $Id: GForgeSOAP.class.php,v 1.3 2006/02/15 16:50:46 marcelo Exp $
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
 * GForgeSOAP - Wrapper function for NuSOAP class.
 *
 * This class will pass on each command common variables to the server, like the
 * session ID and the project name
 */
class GForgeSOAP extends nusoap_client {
	var $sess_hash;
	var $wsdl_string;
	var $connected;
	var $session_string;
	var $session_file;		// Configuration file for this session
	var $session_group_id;	// Default group
	var $sesson_user;		// Logged user
	
	/**
	 * constructor
	 */
	function GForgeSOAP() {
		$this->wsdl_string = "";
		$this->connected = false;
		$this->session_string = "";
		$this->session_group_id = 0;		// By default don't use a group
		$this->session_user = "";
		
		// Try to find a dir where to put the session file
		if (array_key_exists("HOME", $_ENV)) {
			$session_dir = $_ENV["HOME"]."/";
		} else if (getenv('HOME')) {
			$session_dir = getenv('HOME')."/";
		} else if (array_key_exists("HOMEPATH", $_ENV) && array_key_exists("HOMEDRIVE", $_ENV)) {		// For Windows
			$session_dir = $_ENV["HOMEDRIVE"]."\\".$_ENV["HOMEPATH"]."\\";
		}
		$this->session_file = $session_dir.".gforgerc";
		$this->readSession();
	}
	
	/**
	 * call - Calls a SOAP method
	 *
	 * @param string	Command name
	 * @param array	Parameter array
	 * @param bool		Specify if we should pass the server common parameters like the session ID
	 */
	function call($command,$params=array(),$use_extra_params=true) {
		global $LOG;
		
		// checks if a session is established
		if ($command != "login" && strlen($this->session_string) == 0) {
			exit_error("You must start a session first using the \"login\" function");
		}
		
		if (!$this->connected) {		// try to connect to the server
			$this->connect();
		}
		
		// Add session parameters
		if ($use_extra_params) {
			if (!array_key_exists("session_ser", $params)) $params["session_ser"] = $this->session_string;
		}
		$LOG->add("GForgeSOAP::Executing command ".$command."...");
		return parent::call($command,$params);
	}
	
	/**
	 * connect - Establish the connection to the server. This is done in the constructor
	 * of the nusoapclient class
	 */
	function connect() {
		global $LOG;
		
		if (!$this->wsdl_string) {
			if (defined("WSDL_URL")) {
				$this->wsdl_string = WSDL_URL;
			} else {
				exit_error("GForgeSOAP: URL of the WSDL is not defined. Please set your GFORGE_WSDL environment variable.");
			}
		}
		
		$LOG->add("GForgeSOAP::Connecting to the server ".$this->wsdl_string."...");
		parent::nusoap_client($this->wsdl_string, "wsdl");
		if (($error = $this->getError())) {
			exit_error($error, $this->faultcode);
		}
		$LOG->add("GForgeSOAP::Connected!");
		$this->connected = true;
	}
	
	/** 
	 * setSessionString - Set the session ID for future calls
	 *
	 * @param string Session string ID
	 */
	function setSessionString($string) {
		$this->session_string = $string;
	}
	
	function setSessionGroupID($group_id) {
		$this->session_group_id = $group_id;
	}
	
	function getSessionGroupID() {
		return $this->session_group_id;
	}
	
	function setSessionUser($user) {
		$this->session_user = $user;
	}
	
	function getSessionUser() {
		return $this->session_user;
	}
	
	function setWSDL($wsdl) {
		$this->wsdl_string = $wsdl;
	}
	
	function saveSession() {
		$handler = fopen($this->session_file, "w");
		if (!$handler) {
			exit_error("Could not open session file ".$this->session_file." for writing");
		}
		
		fputs($handler, "wsdl_string=\"".$this->wsdl_string."\"\n");
		fputs($handler, "session_string=\"".$this->session_string."\"\n");
		fputs($handler, "session_group_id=\"".$this->session_group_id."\"\n");
		fputs($handler, "session_user=\"".$this->session_user."\"\n");
		fclose($handler);
		
		chmod($this->session_file, 0600);
	}
	
	function readSession() {
		// Read session info (if exists)
		if (file_exists($this->session_file)) {
			$session = parse_ini_file($this->session_file, false);
			if (array_key_exists("session_string", $session)) {
				$this->session_string = $session["session_string"];
				$this->session_group_id = $session["session_group_id"];
				$this->session_user = $session["session_user"];
				$this->wsdl_string = $session["wsdl_string"];
			}
		}
	}
	
	function endSession() {
		if (file_exists($this->session_file) && !@unlink($this->session_file)) {
			exit_error("Could not delete existing session file ".$this->session_file);
		}
		
		$this->session_group_id = 0;
		$this->session_string = "";
		$this->session_user = "";
	}
}
?>
