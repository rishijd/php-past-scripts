<?php
/* Class for customer session. Abstract - must be extended by another mapper: Saved Searches, Recently Viewed, or Lust List */
include_once("DataMapperAbstract.php");
abstract class Model_Mapper_CustomerSession extends DataMapperAbstract  {
	
	//protected $_tableData = array();
	protected $fieldname; //set by class object constructors
	protected $customer_sessionid = ""; //only manipulated within this class
	
	public function __construct($custid="") {
		/* @param $custid - if customer is logged in, $custid is their customer ID. Otherwise $custid is empty. */
		$this->custid = $custid;
	}
	
	/* Shoe size and width related functions, for cookie functions below. These relate to PLP mapper (where the cookie is set). */
	protected function getLastSavedSearchParams() { /* TO DO - can change these to public for later use. */
		/* post: returns an array of "last saved search" data which should be used by the DB. */
		global $conn;
		$retarr['shoesize'] = ""; //35..43
		$retarr['calfwidth_letter'] = "";
		$retarr['shoewidth_letter'] = "";
		$retarr['sqlvalue_shoesize'] = "NULL"; //35..43 or NULL, for database insert/update.
		$retarr['sqlvalue_calfwidth_letter'] = "NULL";
		$retarr['sqlvalue_shoewidth_letter'] = "NULL";
		
		if (isset($_COOKIE['lastsearch_shoesize']) && $conn->isInteger_f($_COOKIE['lastsearch_shoesize'])) {
			$retarr['shoesize'] = $_COOKIE['lastsearch_shoesize'];
			$retarr['sqlvalue_shoesize'] = $retarr['shoesize'] ;
		}
		if (isset($_COOKIE['lastsearch_calfwidth']) && (strlen($_COOKIE['lastsearch_calfwidth']) < 2)) {	
			$retarr['calfwidth_letter'] = $_COOKIE['lastsearch_calfwidth'];
			$retarr['sqlvalue_calfwidth_letter'] = $conn->pgDollarEscape($retarr['calfwidth_letter']);
		}
		if (isset($_COOKIE['lastsearch_shoewidth']) && (strlen($_COOKIE['lastsearch_shoewidth']) < 2)) {
			$retarr['shoewidth_letter'] = $_COOKIE['lastsearch_shoewidth'];
			$retarr['sqlvalue_shoewidth_letter'] = $conn->pgDollarEscape($retarr['shoewidth_letter']);
		}
		return $retarr;
	}
	protected function setLastSavedSearchParams($shoesize, $shoewidth_letter, $calfwidth_letter) {
		/* simple function to just set these variables so we can access them from the view. These are set from e.g. the Lust List */
		$this->shoesize = $shoesize;
		$this->calfwidth_letter = $calfwidth_letter;
		$this->shoewidth_letter = $shoewidth_letter;
	}
	public function getLastSavedShoeSize() { if (isset($this->shoesize)) return $this->shoesize; else return false; }
	public function getLastSavedCalfWidth() { if (isset($this->calfwidth_letter)) return $this->calfwidth_letter; else return false; }
	public function getLastSavedShoeWidth() { if (isset($this->shoewidth_letter)) return $this->shoewidth_letter; else return false; }
	
	/* Cookie session functions */
	protected function setSessionIDCookie($customer_sessionid) {
		/* 	pre: @param $sessionid is returned from the database - see setDataFromSession() function 
			post: stores the session ID in session and cookie */
		$_SESSION["demo_customer_sessionid"] = $customer_sessionid;
		setcookie("demo_customer_sessionid", $customer_sessionid, time()+3600*24*60, '/'); //60 days
	}
	protected function clearCookieSessionID() {
		/* post: unsets the cookie/session (in case the ID was invalid) */
		$cookieSessionName = "demo_customer_sessionid";
		if (isset($_SESSION[$cookieSessionName])) unset($_SESSION[$cookieSessionName]);
		if (isset($_COOKIE[$cookieSessionName])) {
			// expire cookie from script, server and client side
			setcookie($cookieSessionName, "", 1);
			setcookie($cookieSessionName, false);
			unset($_COOKIE[$cookieSessionName]);
		}
		$this->customer_sessionid = "";
	}
	protected function getCookieSessionID() {
		/* post: fetches the session ID for this customer, if set. */
		global $conn;
		if ($this->customer_sessionid != "") {
			return $this->customer_sessionid;
		}
		else {
			if (isset($_SESSION["demo_customer_sessionid"])) 		$this->customer_sessionid = $_SESSION["demo_customer_sessionid"];
			else if (isset($_COOKIE["demo_customer_sessionid"])) 	$this->customer_sessionid = $_COOKIE["demo_customer_sessionid"];
			if ($this->customer_sessionid != "" && $conn->isInteger_f($this->customer_sessionid)) {
				return $this->customer_sessionid;
			}
		}
		return ""; //no results found	
	}
	protected function getDataFromSession($fields="savedsearch_serialized") {
		/* 	pre: $fields - enum("savedsearch_serialized", "recentlyviewed_serialized", "lustlist_serialized"), 
					we need to use this for other fields as well (mappers that extend this mapper), default will be to select all - namely saved searches, recently viewed, etc.
			post: if the session ID is set, get the saved search data */
		global $conn;
		$sessionid = $this->getCookieSessionID();
		if (!empty($sessionid))	{						
			$result = $conn->Exec("SELECT $fields FROM demo_customer_session
									WHERE customer_sessionid = " . $sessionid);
			if ($result) {
				while ($row=$conn->fetchArray($result, "assoc")) { //one row maximum
					return $row;
				}
			}
		}
		return 0; //no results found
	}
	protected function setDataFromSession($content_serialized) {
		/* 	@param $content_serialized is a serialized, base64encoded array of e.g. saved_search data, set from the save() function below. Or it can be empty if we are clearing the contents, in the profile-to-cookie "sync" function below.
			pre:	$this->fieldname must be set from the child class (not in this class, since it is abstract) = enum('savedsearch_serialized', 'recentlyviewed_serialized', 'lustlist_serialized')
			post: if the session ID is set, get the saved search data */
		global $conn;
		$sessionid = $this->getCookieSessionID();
		$lastsearch_shoeparams = $this->getLastSavedSearchParams(); //array of last search parameters		
		if (empty($sessionid)) { //new session
			$result = $conn->Exec("INSERT INTO demo_session (custid, " .$this->fieldname . ", shoesize, calfwidth, shoewidth)
									VALUES (" . ($this->custid ? $this->custid : "NULL") .", '$content_serialized', 
									" . $lastsearch_shoeparams['sqlvalue_shoesize'] . ",
									" . $lastsearch_shoeparams['sqlvalue_calfwidth_letter'] . ",
									" . $lastsearch_shoeparams['sqlvalue_shoewidth_letter'] . "
									)
									RETURNING customer_sessionid
									");
			if ($result) {
				$row = $conn->fetchArray($result);
				$this->setSessionIDCookie($row["customer_sessionid"]);
			}		
		}
		else { //existing session ID (from session or cookie)
			$result = $conn->Exec("UPDATE demo_session
									SET " .$this->fieldname . " = '$content_serialized',
											shoesize = " . $lastsearch_shoeparams['sqlvalue_shoesize'] . ", 
											calfwidth = " . $lastsearch_shoeparams['sqlvalue_calfwidth_letter'] . ",
											shoewidth = " . $lastsearch_shoeparams['sqlvalue_shoewidth_letter'] . ",
											lastmodified = '" . date("Y-m-d H:i:s") . "'
									WHERE customer_sessionid = " . $sessionid);	
			if (!$conn->affectedRows($result)) { //no result updated, so insert it as the sessionid may be invalid
				$this->clearCookieSessionID();
				$this->setDataFromSession($content_serialized); //call this function again, but now it will do the INSERT.
			}
		}
	}
	
	public function fetchAll() { /* stub */
	}
}