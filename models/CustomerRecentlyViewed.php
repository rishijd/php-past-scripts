<?php
/* Class for customer saved searches - extends the abstract class CustomerSession to store the session data. */
class Model_Mapper_CustomerRecentlyViewed extends Model_Mapper_CustomerSession  {
	
	public function __construct($custid="") {
		/* @param $custid - if customer is logged in, $custid is their customer ID. Otherwise $custid is empty. */
		$this->fieldname = "demo_recentlyviewed_serialized"; //used by CustomerSession abstract class for INSERT/UPDATE SQL commands.
		$this->custid = $custid;
	}
	
	/* "Recently Viewed" functions */
	public function fetchAll() {
		/* post: fetches all recentlyviewed items from the cookie, as an array. */
		global $conn;
		$row_session = $this->getDataFromSession($this->fieldname);
		if (!empty($row_session)) {
			$arr = unserialize($row_session[$this->fieldname]);
			return $arr;
		}
		return array(); //no results found
	}
	protected function saveToRecentlyViewedQueue(&$recentlyviewed_array, $productid, $colourid="") {
		/*	@param $productid integer - a valid productid from our database
			@param $colourid integer - empty (for boot trees) or a valid colourid from our database
			@param $recentlyviewed_array is the return value of fetchAll()
			post: return the array with maximum 6 product records.
		*/	
		//first, search whether this item already exists in our array
		$needle_arr = array('productid'=>$productid, 'colourid'=>$colourid);
		if (is_array($recentlyviewed_array)) $key = array_search($needle_arr, $recentlyviewed_array, TRUE); //String=TRUE since we should match arrays only (not that any other type exists in our array!)
		else $key = false; //this will be reached when the database field value is empty, since it is not an array
		
		$unset_done = false; //see below code
		if ($key !== false) { //duplicate found, so we want to remove it
			unset($recentlyviewed_array[$key]); 
			$unset_done = true;
		}
		//then add it to array
		$recentlyviewed_array[] = $needle_arr;	
		if (count($recentlyviewed_array)>6){ //remove first product record if there are over 6 records now
			$notneeded_last_item = array_shift($recentlyviewed_array); //array_shift also resets the keys from 0 to 9.
		}
		else if ($unset_done) { //just reset the key, as another one was unset above and we just want to keep a simple list of keys 0 to 9 for developer easy debugging if we ever need to check our array
			$recentlyviewed_array = array_values($recentlyviewed_array);
		}
	}	
	public function save($productid, $colourid="") {
		/* 	@param  - see saveToRecentlyViewedQueue() above
			post: Save the item in the recently viewed / session cookie.
		*/
		global $conn;
		$result_arr = $this->fetchAll(); //fetch from the cookie / session table
		$this->saveToRecentlyViewedQueue($result_arr, $productid, $colourid);	
		$recentlyviewed_serialized = serialize($result_arr);	
		$this->setDataFromSession($recentlyviewed_serialized); //save it back to cookie / session table
	}	
}