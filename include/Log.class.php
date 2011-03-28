<?php
/**
 * GForge Command-line Interface
 *
 * Copyright 2005 GForge, LLC
 * http://fusionforge.org/
 *
 * @version   $Id: Log.class.php,v 1.1.1.1 2005/07/04 13:50:50 marcelo Exp $
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
 * Log - Class that allows logging of actions
 */
class Log {
	var $level;
	
	/**
	 * Log - Constructor
	 */
	function Log() {
		$this->level = 0;		// By default, don't log
	}

	/**
	 * setLevel - Set the level of logging
	 *
	 * So far only 2 values are accepted: 0 (no logging) and 1 (log to console)
	 */
	function setLevel($level) {
		$this->level = $level;
	}
	
	/**
	 * add - Add some text to the log
	 *
	 * @parameter	string Text to log
	 */
	function add($text) {
		if ($this->level) {
			echo $text."\n";
		}
	}
}
?>