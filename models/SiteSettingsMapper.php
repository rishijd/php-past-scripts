<?php
/* Class for site international settings - used by IndexController.php */

class Model_Mapper_SiteSettingsMapper {
	
	public function __construct($siteid, $isTestSite="") {
		/*	pre: assume open $conn connection and $siteid is passed for fetchSettings() to work.
			post: This function also gets further information from the database (or cache) about site settings
		 		  i.e. red_bar_desc, red_bar_published via the fetchSettings() function.
		*/
		global $conn;
		$this->tablename = "demo-sitesettings";
        if (!$conn->isInteger_f($siteid)) // Sanitize the siteid
            $conn->callError();
		$this->siteid = $siteid;
		$this->isTestSite = $isTestSite=="" ? $conn->isTestSite() : $isTestSite;
		$this->fetchSettings();
	}
	public function fetchSettings($clearCache=false) {
		/*	pre:	$siteid defined via the constructor.
			post:	select site settings and sets these variables within this class (as class vars).
		*/
		global $conn;
		$sql = "SELECT * FROM ". $this->tablename ."
				WHERE siteid = ". $this->siteid ." AND is_testsite = ". ($this->isTestSite ? "'t'" : "'f'");
			
		// APC - database caching
		$apc_key = $conn->demo_apc_create_cache_id($sql);
		if (!$clearCache && $data = $conn->demo_apc_fetch($apc_key)) {
			$row = unserialize($data); //cached $row of site data
		}
		else { // no cache for this, so execute and cache-store the result
			$conn->Connect();
			$result = $conn->Exec($sql);
			if ($conn->Numrows($result)) {
				$row = $conn->fetchArray($result, "assoc"); // 1 row only				
				$bool_stored = $conn->demo_apc_store($apc_key, $row, true, 604800); // cache for 1 week
			}
			$conn->db_free_result($result);
		}	
		if (!empty($row)) {
			foreach ($row as $key => $val)
				$this->{$key} = $val;
		}
	}

	public function getRedBarDesc() { return $this->red_bar_desc; }
	public function getRedBarExpireSeconds() { return $this->red_bar_expire_seconds; }
	public function isRedBarPublished() {  
		if (isset($this->red_bar_published) && $this->red_bar_published=='t') return true;
		else return false;
	}
	public function displayRedBar() {
		/*	pre: fetchSettings() is called.
			post: tells the site to display the red bar IF it is published and,
				  if duraction = 0 then red bar must always be on
				  if duration !=0 then this should appear for the first page the user lands on for the duration period (as oppose to a per page basis).
		*/
		global $conn;
		
		if ($this->isRedBarPublished()) {
			if ($this->getRedBarExpireSeconds()) {
				if (empty($_SESSION[$conn->getPublicSiteCode() ."_redbar_displayed"])) {
					$_SESSION[$conn->getPublicSiteCode() ."_redbar_displayed"] = 1;
					return true; // not set, so we can show the red bar
				}
				else return false;
				// #684354 raised to re-enable this option
				// red bar was already seen since the session was set, so disable it
				// this check was removed with this Ticket #568840
			}
			else return false; // do not show red bar on every page
		}
	}
}