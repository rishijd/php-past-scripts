<?php
/* Class for filter/product facet - "product type" (i.e. boots, shoes, ankle boots, bags) */
class Model_Mapper_ProductFacetTypeMapper extends Model_Mapper_ProductFacetMapper  {
		
	public function __construct($siteid) {
		/* pre: @param $outlet_parentsiteid is the outlet parent site ID set from the SiteInternationalMapper.php model */
		global $conn; 
		
		$this->tablename = "product_type";
		$this->column_id = "prodtypeid";
		//$this->column_desc = "sef_url_uk";
		if (!$conn->isInteger_f($siteid))
			$conn->callError();
		
		$this->siteid = $siteid; //current public site ID
		//static variables
		$this->wfs_prodtypeid = 4; //Shoes demo type ID
		$this->ankleboots_prodtypeid = 9; //Ankle boots demo type ID
		$this->boottrees_prodtypeid = 6; //Boot trees demo type ID
		$this->boots_prodtypeid = 1; //Boot trees demo type ID
	}
	public function isBoots($prodtypeid) {
		/* 	pre: @param $prodtypeid is an ID of product_type table
			post: returns true if $prodtypeid is the boots demo type ID above. This is useful for the ProductList.php mapper which needs to find out if boots are included in the results.
		*/
		return ($prodtypeid == $this->boots_prodtypeid); //true or false
	}
	public function isWFS($prodtypeid) {
		/* 	pre: @param $prodtypeid is an ID of product_type table
			post: returns true if $prodtypeid is the WFS (shoes) demo type ID above. This is useful for the ProductList.php mapper which needs to find out if shoes are included in the results.
		*/
		return ($prodtypeid == $this->wfs_prodtypeid); //true or false
	}
	public function isAnkleBoots($prodtypeid) {
		/* 	pre: @param $prodtypeid is an ID of product_type table
			post: returns true if $prodtypeid is the WFS (shoes) demo type ID above. This is useful for the ProductList.php mapper which needs to find out if shoes are included in the results.
		*/
		return ($prodtypeid == $this->ankleboots_prodtypeid); //true or false
	}
	public function getBootTreesProdTypeID() {
		/* 	post: returns boot trees prodtypeid */
		return $this->boottrees_prodtypeid;
	}
	public function fetchAll($exclude_boot_trees=true, $sql_landingPageInnerJoin="") {	
		/* 	pre:	none.
			@param 	$exclude_boot_trees boolean - if we need boot trees in the list (currently it is excluded for the filters), then we set this as TRUE. 
			@param 	$sql_landingPageInnerJoin is a return value of the list mapper getSQL_landingPageInnerJoin(), and will only have a value when on landing pages or any pages with products assigned to it (i.e. not the usual PLP pages).
			post: 	Generates an array of ALL demo types. Uses caching.
		*/
		global $conn;
		$result_arr_param = array();

		switch($this->siteid) {
			case 6: //UK, no override descriptions here. These settings are used for the SQL below.
			case 8: //Outlet
				$aux_column_select = "pt.typename";
				$aux_column_orderby = $aux_column_select;
				$extra_innerjoin = "";
				break;
			case 9:
				$aux_column_select = "CASE WHEN (pts.typename IS NOT NULL) THEN pts.typename ELSE pt.typename END AS typename ";
				$aux_column_orderby = "typename";
				$extra_innerjoin = "LEFT JOIN demo_type_sitecode pts ON pt.prodtypeid = pts.prodtypeid AND pts.siteid = " . $this->siteid;
				break;
			default: //Euro sites
				$aux_column_select = "pts.typename";
				$aux_column_orderby = $aux_column_select;
				$extra_innerjoin = "INNER JOIN demo_type_sitecode pts ON pt.prodtypeid = pts.prodtypeid AND pts.siteid = " . $this->siteid;
		}
		
		if ($sql_landingPageInnerJoin) { //this is a landing page, so we also need to join in the extra tables since this variable will hold a value = something like "INNER JOIN a12pages_demo a12p ON a12p.pageid=693 AND a12p.productid = p.productid AND a12p.colourid = pdsc.colourid"
			$sql_landingPageInnerJoin = "
					INNER JOIN demo p ON p.prodtypeid = pt.prodtypeid
					INNER JOIN demo_detail_sitecode pds 	ON	pds.demoid = p.productid AND	pds.siteid = " . $this->siteid . "
					INNER JOIN demo_detail_sitecode_colour pdsc 	ON 	pdsc.prod_detailid = pds.prod_detailid
			" . $sql_landingPageInnerJoin;
		}
		
		$filterSQL = "SELECT DISTINCT pt.prodtypeid, pt.sef_url_uk AS sef_url, $aux_column_select, pt.orderno
						FROM " . $this->tablename . " pt
						$extra_innerjoin
						$sql_landingPageInnerJoin
						WHERE	pt.published = 't' 
						" . ($exclude_boot_trees ? "AND pt.prodtypeid != 6" : "") . "
						ORDER BY pt.orderno, $aux_column_orderby
						"; //note the siteid=siteid clause above is simply used to have a unique SQL cache key (used below) PER SITE, so that we have different translation description arrays below per site.

		//APC - database caching
		$apc_key = $conn->demo_apc_create_cache_id($filterSQL);
		if ($data = $conn->demo_apc_fetch($apc_key)) {
			//print("<hr/><div align='right'>Data from CACHE COLOUR</div>");
			$data = unserialize($data);			
			$result_arr_param = $data; //array of [$id]["desc"]
		}
		else { //no cache for this, so execute and cache-store the result
			$result = $conn->Exec($filterSQL);
			//$err = $conn->checkError($result);
			//if ($err) print($err . "--- ". $filterSQL . "<br/>sql landing - $sql_landingPageInnerJoin<hr/>");
			if ($conn->Numrows($result)) {
				while ($row=$conn->fetchArray($result)) {
					$id = $row[$this->column_id]; //$column_id = "prodtypeid"
					$result_arr_param[$id]["desc"] = $row["typename"];
				}
			}
			$conn->db_free_result($result);
			$bool_stored = $conn->demo_apc_store($apc_key, $result_arr_param);
			//if ($bool_stored) print("YES STORED"); else print("NO, NOT STORED");
		}
		$this->result_arr_param_fetchall = $result_arr_param; //store for easy retrival later, if needed.
		return $result_arr_param;
	}
	
	public function fetchAllSefUrl() {	
		/* pre:		none.
		   post: 	Generates an array of SEF URLs of ALL demo types. Uses caching.
		*/
		global $conn;
		$result_arr_param = array();		
		$filterSQL = "SELECT pt.prodtypeid, pt.sef_url_uk AS sef_url
							FROM 	" . $this->tablename . " pt
							WHERE	pt.published = 't'
							ORDER BY pt.sef_url_uk
							";
		
		// APC - database caching
		$apc_key = $conn->demo_apc_create_cache_id($filterSQL);
		if ($data = $conn->demo_apc_fetch($apc_key)) {
			$data = unserialize($data);			
			$result_arr_param = $data; //array of [$id]
		}
		else { // no cache for this, so execute and cache-store the result
			$result = $conn->Exec($filterSQL);
			if ($conn->Numrows($result)) {
				while ($row=$conn->fetchArray($result)) {
					$id = $row[$this->column_id]; // column_id = "prodtypeid"
					$result_arr_param[$id] = $row["sef_url"];
				}
			}
			$conn->db_free_result($result);
			$bool_stored = $conn->demo_apc_store($apc_key, $result_arr_param);
		}
		$this->result_arr_param_fetchall = $result_arr_param; // store for easy retrival later, if needed.
		return $result_arr_param;
	}
	
	public function getProdTypeDefaultKeyword($prodtypeid) {
		/* pre: this will be used generate demo image names
		   post: return the default main keyword related to demo type id
		*/
		switch ($prodtypeid) {
			case 1:	return "ladies-boots";
				break;
			case 4:	return "ladies-shoes";
				break;
			case 9:	return "ankle-boots";
				break;
			case 8:	return "handbags";
				break;
			case 10: return "tights";
				break;
			case 11: return "socks";
				break;
			case 12: return "cleaners-and-protectors";
				break;
			case 13: return "insoles";
				break;
			case 14: return "creams-and-polishes";
				break;
			case 15: return "boot-trees"; // tmp boot tree
				break;
		}
	}

	public function processParam_getParamID($request_param_val) {
		//pre:  Only used in non-Ajax mode for single demo types passed via the SE-friendly URL.
		//@param $request_param_val = $this->_getParam('producttype') - meaning that this is only used in non-Ajax mode. Should always be English, since the translations come from the bootstrap route / URL controller helper.
		//post: see class ProductFacetMapper, but this is specially for demo TYPE.
		global $conn;
		
		/* Note that the demo type is a special case - it's passed via bootstrap routes. We only check this when not in Ajax mode / i.e. when using one demo type only. The $this->_getParam('producttype') is therefore one of:
			('ladies-boots', 'ankle-boots', 'ladies-shoes', 'bags', 'boot-trees')
			*/
		$tablename = $this->_tableData['tablename']; //or we can just do $this->tablename which goes to the magic method __get above, same thing.
		$sql = 'SELECT 		prodtypeid AS idval	
							FROM 	'. $tablename .'												
							WHERE 	lower(sef_url_uk) = '. $conn->pgDollarEscape($request_param_val) .'
							AND		published = \'t\' 							
						'; //AND 	siteid=$siteid	
		return $this->aux_processParam_getParamID_getResult($sql, $request_param_val); //see parent class
	}
	
	
	protected function aux_processParam_getParamID_getResult($sql, $request_param_val) {
		//auxilary shared function for the above processParam_getParamID and also the extended COLOUR class ProductFacetColourMapper.php
		global $conn;
		$apc_key = $conn->demo_apc_create_cache_id($sql);
		if ($data = $conn->demo_apc_fetch($apc_key)) { //$data is available, see $row["idval"] below to understand (it is e.g. the heelID of "low")
			//print("<hr/><div align='right'>************************** ONE-CLICK-FILTER Data ID from CACHE --- $data</div>");
			if (@unserialize($data)!==false) { // if APC stored data is an array then we need to unserilize it
				$data = @unserialize($data);
			}
			$GLOBALS["filternav_selectedparamname"] = $request_param_val; //see below, same code
			return $data;
		}
		else { //no cache for this, so execute and cache-store the result
			$result = $conn->Exec($sql);
			$array_result_found = false; // will be true only if multiple results are found, eg: Hosiery is a product-type that spans 2 product-types - tights and socks.
			if ($result && $conn->Numrows($result)) {
				if ($conn->Numrows($result) == 1) { //if only one result were found from "product_type"
					$row = $conn->fetchArray($result);
				} else if ($conn->Numrows($result) > 1) { 
					$row_array = array();
					$array_result_found = true;
					while($row = $conn->fetchArray($result)){
						$row_array[] = $row["idval"];
					}
				}
				
				if ($array_result_found) { //if the multiple results are found then we change return array and APC cache data
					$drr = $row_array;
				} else {
					$drr = $row["idval"];
				}
					
				//note, we need $request_param_val for the 2-click sef urls such as "black leather boots", so store this in a global variable, to be used in the view file. 
				$GLOBALS["filternav_selectedparamname"] = $request_param_val; //this var will only actually be utilised for 1-click filter pages, because we need it for these special 2-click cases, namely "black leather", "black suede", "brown leather" and "brown suede" boots.
				$bool_stored = $conn->demo_apc_store($apc_key, $drr, $array_result_found); //serilized if $array_result_found is true else we do not need to serialize.
				return $drr;//return the value, e.g. the heelid value of 'flat'.
			}			
		}
		return "";
	}
}