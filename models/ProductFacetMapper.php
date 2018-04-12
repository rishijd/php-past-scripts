<?php
/* Class for filter/product facets, mapping to database. 
 * The non-standard "colour" filter also has a fetchAll_colours() function in this file used specifically for the colour facet. 
 * Other facets use the standard fetchAll() function. 
 */
include_once("DataMapperAbstract.php");
class Model_Mapper_ProductFacetMapper extends DataMapperAbstract  {
	
	protected $_tableData = array();
	//protected $result_arr_param_fetchall = array(); //return result of fetchAll() function, stored for easy retrieval in case it is needed after the fetchAll() call. --- this is stored in $_tableData above.
	/* The above holds the following keys:
    _tablename; //e.g. "param_heel"
	_tablename_alias; //e.g. "pp_heel"
	_column_id; //e.g. "heelid"
	_column_desc; //e.g. "heeltype_desc"
	_siteid (public site ID)
	*/
	
	public function __construct($siteid, $tablename, $tablename_alias, $column_id, $column_desc, $outlet_parentsiteid="", $modelmapper_site="") {
		/* @params 
			$outlet_parentsiteid is the outlet parent site ID set from the SiteInternationalMapper.php model 
			$modelmapper_site is empty or an instance of Model_Mapper_SiteInternationalMapper
		*/
		global $conn;
		
		$this->tablename = $tablename;
		$this->tablename_alias = $tablename_alias;
		$this->column_id = $column_id;
		$this->setColumnIDforURL(); //this will be overriden to "general_search_desc" for the Colour class (ProductFacetColourMapper) for product colour filter URLs
		$this->column_desc = $column_desc;
		if (!$conn->isInteger_f($siteid))
			$conn->callError();
		
		$this->siteid = $siteid; //current public site ID
		$this->outlet_parentsiteid = $outlet_parentsiteid; //used to identify outlet for certain function(s) below
	}
	protected function setColumnIDforURL() {
		//the reason why we have a separate function for this, used by the constructor, is so that the extended COLOUR mapper class can override it as its URL column id is not the same as its columnid. (unlike the others:, e.g. "heelid" is passed by the URL too for filter URLs.
		$this->column_id_for_url = $this->column_id;
	}
        public function __set($member, $value) {
            /* The ID of the dataset is read-only
            if ($member == "id") {
                return;
            } */
            //if (isset($this->_tableData[$member])) {
            $this->_tableData[$member] = $value;
        }	
        public function __get($member) {
            if (isset($this->_tableData[$member])) {
                return $this->_tableData[$member];
            }
        }
	public function getDefault_param_sql_extra($landing_page=false) {
		/*
			@param: $landing_page : set as true, when this is landing page or any pages with products assigned to it (i.e. not the usual PLP pages).
		*/
		//returns the default SQL clauses for the fetchAll function below, if not already specified. This is for NON SALE products.
		if ($this->outlet_parentsiteid) { //in outlet mode we want ALL sale products even though the sale is off. So we do not differentiate.
			return "";
		}
		else if ($landing_page)
			return ""; //In landing pages, we want to show all on-sale and non-sale products.	
		else {
			return "AND pds.secretsale='f'	AND 
					(pp.active_price = pp.master_price OR (pp.active_price < pp.master_price AND pp.priceband_keep_in_full_area IS TRUE))";
		}
	}

	public function fetchAll($param_sql_extra="", $sql_prodtypeid="", $prodtypeid="", $sql_landingPageInnerJoin="") {	
		/* pre:	@param $param_sql_extra is set from list.php (products list page) for extra SQL parameters. Can be empty.
				@param $sql_prodtypeid must be set (products list page) - may be empty or with an AND (e.g. "AND (p.prodtypeid=9)", OR $prodtypeid must be set as a valid ID (1=boots, 4=shoes)
				@param $sql_landingPageInnerJoin is a return value of the list mapper getSQL_landingPageInnerJoin(), and will only have a value when on landing pages or any pages with products assigned to it (i.e. not the usual PLP pages).
				
		   post: Generates left menu filter parameters for table names starting with prod_param (as passed)
		   Generates the appropriate SQL depending on which site we are on, and gets the list of filters applicable to the set product type in this class. Then executes the SQL via APC CACHE (or normal db if no cache) and returns the list of facet (param) models. (Note in true MVC we would return a list of Facet Models, but in our particular scenario it was just easier/faster to send back the expected result array).
		   Note that for UK, there are no "override" descriptions in our db for the parameters. For Euro sites there are overrides - so our SQL is formed accordingly.	
						The return array is also stored within this class, in case it is needed later.
		*/
		global $conn;
		$tablename = $this->_tableData['tablename']; //or we can just do $this->tablename which goes to the magic method __get above, same thing.
		$tablename_alias = $this->_tableData['tablename_alias'];
		$column_id = $this->_tableData['column_id'];
		$column_desc =  $this->_tableData['column_desc'];
		$extra_innerjoin = "";
		$siteid = $this->siteid;		
		if (empty($sql_prodtypeid) && !empty($prodtypeid) && $conn->isInteger_f($prodtypeid)) $sql_prodtypeid = "AND ( p.prodtypeid=" . $prodtypeid . ")";
		if (empty($param_sql_extra)) $param_sql_extra = $this->getDefault_param_sql_extra($sql_landingPageInnerJoin);
		
		switch($siteid) {
			case 6: //UK, no override descriptions here. These settings are used for the SQL below.
				$aux_tablename_alias_sitecode = "";
				$aux_tablename_alias_column_inuse = $tablename_alias . "." . $column_desc; //the main UK table is in use for the SELECT part of the SQL, e.g. "pp_heel.heeltype_desc"
				break;
			
			default: //Euro sites
				$aux_tablename_alias_sitecode = $tablename_alias . "_s"; //e.g. "pp_heel_s"
				$aux_tablename_alias_column_inuse = $aux_tablename_alias_sitecode . "." . $column_desc . "_override"; //see comment above, e.g. "pp_heel_s.heeltype_desc_override"
		}
		
		switch ($this->_tableData['column_id']) {
			case "materialid":
				$aux_count_tablealias = "pdsc";
				break;
			case "occasionid":
				$extra_innerjoin = "INNER JOIN demo_occasion po ON po.productid = p.productid";
				$aux_count_tablealias = "po";
				break;
			case "shoetypeid":
				$extra_innerjoin = "INNER JOIN demo_shoetype psh ON psh.productid = p.productid";
				$aux_count_tablealias = "psh";
				break;
			case "featuredid":
				$extra_innerjoin = "INNER JOIN demo_featured pf ON pf.productid = p.productid";
				$aux_count_tablealias = "pf";
				break;
			case "heightid": //boot-only attribute, so we need to join the boot table product_att_boot
				$extra_innerjoin = "INNER JOIN demo_att_boot paboot ON paboot.productid = p.productid";
				$aux_count_tablealias = "paboot";
				break;
			default:
				$aux_count_tablealias = "p";
		}
		
		$filterSQL = "SELECT DISTINCT	$tablename_alias.$column_id, 
						" . $aux_tablename_alias_column_inuse . " AS param_desc_this_site,
						$tablename_alias.orderno
						
						FROM product p
						
						$extra_innerjoin
						
						INNER JOIN demo_sitecode pds
						ON		pds.productid = p.productid
						AND		pds.siteid = $siteid
						
						INNER JOIN demo_sitecode_colour pdsc
						ON		pdsc.prod_detailid = pds.prod_detailid

						INNER JOIN demo_pricebands pp 	
						ON 		pp.productid = pds.productid
						AND		pp.currencyid = " . $conn->getPublicCurrencyID() . "
						AND		pp.siteid = " . $this->mastersiteid . "
						AND		pp.colourid = pdsc.colourid

						INNER JOIN $tablename $tablename_alias
						ON 		" . $tablename_alias . ".$column_id = " . $aux_count_tablealias .".". $column_id . "

						" . (empty($aux_tablename_alias_sitecode) ? "" : "
							INNER JOIN " . $tablename . "_sitecode " . $aux_tablename_alias_sitecode . "
							ON	" . $aux_tablename_alias_sitecode . ".siteid='" . $siteid . "'
							AND	" . $aux_tablename_alias_sitecode . ".$column_id = " . $tablename_alias . ".$column_id
						") . "
						
						$sql_landingPageInnerJoin
						
						WHERE 	pds.published = 't'	AND	pdsc.published = 't'
						$sql_prodtypeid
						". $param_sql_extra ."		
						ORDER BY $tablename_alias.orderno, param_desc_this_site
					";
		/* } */
		//APC - database caching
		$apc_key = $conn->demo_apc_create_cache_id($filterSQL);
		$result_arr_param = array();
		//print("<hr/>***  KEY: $apc_key *** Processing - " . $column_id . "</div>");
		if ($data = $conn->demo_apc_fetch($apc_key)) {
			//print("<hr/><div align='right'>******** FILTER LIST DATA from CACHE - " . $column_id . "</div>");
			$data = unserialize($data);
			$result_arr_param = $data; //array of [$id]["desc"]
		}
		else { //no cache for this, so execute and cache-store the result
			$result = $conn->Exec($filterSQL);
			$err = $conn->checkError($result);
			//if ($err) print($err . "--- ". $filterSQL . "<br/>param sql extra $param_sql_extra<br/>prod - $sql_prodtypeid<br/>prodtype - $prodtypeid<br/>sql landing - $sql_landingPageInnerJoin<hr/>");
			if ($conn->Numrows($result)) {
				while ($row=$conn->fetchArray($result)) {
					$id = $row[$column_id];
					$result_arr_param[$id]["desc"] = $row["param_desc_this_site"];
				}
			}
			$conn->db_free_result($result);
			$bool_stored = $conn->demo_apc_store($apc_key, $result_arr_param);
			//if ($bool_stored) print("YES STORED"); else print("NO, NOT STORED");
		}
		$this->result_arr_param_fetchall = $result_arr_param; //store for easy retrival later, if needed.
		return $result_arr_param;
	}
	public function getFetchAllResult() {
		//pre:	fetchAll() function has been called.
		//post: gets the $result_arr_param result of fetchAll(), assuming that fetchAll() was called before.
		return $this->result_arr_param_fetchall;
	}
	
	public function processParam_getParamID($request_param_val) {
		//pre: auxilary function for processParam() in list.php controller. Only called for SEF URL filter parameters, e.g. "/heelid/low/" rather than serialized URL parameters.
		//post: if the paramidname is "heelid" (for example), this function will check for the heelid value from the sef-url name $request_param_val (e.g. "low") to get the ID. This is complicated by the fact that Euro sites can have override descriptions so we check for this too.
		global $conn;
		$request_param_val = str_replace('--slash--','/', $request_param_val); //ticket#787333; reffer to convertToURLformat()
		$tablename = $this->_tableData['tablename']; //or we can just do $this->tablename which goes to the magic method __get above, same thing.
		$column_id = $this->_tableData['column_id']; //e.g. "heelid"
		$column_desc =  $this->_tableData['column_desc']; //$paramdescname e.g. "heeltype_desc"
		$siteid = $this->siteid;		
		switch ($siteid) {
			case 6: //uk site, no overrides
				$override_postfix = "";
				$tablename_postfix = "";
				$sqlsiteid = "";
				break;
			default:
				$override_postfix = "_override";
				$tablename_postfix = "_sitecode";
				$sqlsiteid = "AND siteid=$siteid";
		}	
		$sql = 'SELECT 	'. $column_id .' AS idval	
							FROM 	'. $tablename . $tablename_postfix .' 																
							WHERE 	'. $column_desc . $override_postfix .' ILIKE '. 
							$conn->pgDollarEscape($request_param_val) . $sqlsiteid;
							// Previously WHERE condition was like WHERE lower($column_desc".$override_postfix.") = '$request_param_val': changed due to ticket-#799542		
		return $this->aux_processParam_getParamID_getResult($sql, $request_param_val);
	}	
	protected function aux_processParam_getParamID_getResult($sql, $request_param_val) {
		//auxilary shared function for the above processParam_getParamID and also the extended COLOUR class ProductFacetColourMapper.php
		global $conn;
		$apc_key = $conn->demo_apc_create_cache_id($sql);
		if ($data = $conn->demo_apc_fetch($apc_key)) { //$data is available, see $row["idval"] below to understand (it is e.g. the heelID of "low")
			//print("<hr/><div align='right'>************************** ONE-CLICK-FILTER Data ID from CACHE --- $data</div>");
			$GLOBALS["filternav_selectedparamname"] = $request_param_val; //see below, same code
			return $data;
		}
		else { //no cache for this, so execute and cache-store the result
			$result = $conn->Exec($sql);
			if ($result && $conn->Numrows($result)) {
				$row=$conn->fetchArray($result);
				//note, we need $request_param_val for the 2-click sef urls such as "black leather boots", so store this in a global variable, to be used in the view file. 
				$GLOBALS["filternav_selectedparamname"] = $request_param_val; //this var will only actually be utilised for 1-click filter pages, because we need it for these special 2-click cases, namely "black leather", "black suede", "brown leather" and "brown suede" boots.
				$bool_stored = $conn->demo_apc_store($apc_key, $row["idval"], false); 
				return $row["idval"]; //return the value, e.g. the heelid value of 'flat'.
			}			
		}
		return "";
	}
	
	public function convertToURLformat($text_for_sefurl) {
	/* post: for filter URLs: converts $text_for_sefurl into a string with only specific characters. Used by product list page filter navigation */	
		$text_for_sefurl = str_replace('/','--slash--', $text_for_sefurl); //ticket#787333; '/' is causing problems, so we specialy encoding it
		return urlencode(strtolower($text_for_sefurl));
	}
	public function url_getOneClickFilterURL($url, $idval) {
		//pre:	$url is the boots or shoes URL - e.g. http://www.demoboots.com/boots/ so we will use this to append the filter part of the URL
		//		$idval is the value of the param to be used as a filter, e.g. for heel this can be "low"
		//post:	appends the filter part of the URL.
		//return  $url . $this->_tableData['column_id_for_url'] . "/" . $this->convertToURLformat($idval) . "/";
		return  $url . $this->convertToURLformat($idval) . "/";
	}
	public function getOneClickFilter_URL_List($arr_prod_param, $prodtypeid, $a_name_prefix) {
		//@param $arr_prod_param is a result of fetchAll() above, array of [id]["desc"]
		//@param $prodtypeid (int) - product type id (1, 4, 8 etc.)
		//@param $a_name_prefix is a unique name prefix for these links so that we can use them as NAME attributes, for Coremetrics.
		//post: used by IndexController to get the list of all heel / length etc. valid one-click, SEO URLs for the site
		global $conn;
		$ret_url_list = array();
		$count = 0;
		$url = $GLOBALS["this_controller"]->productMapper->getProductsListURL($prodtypeid);
		while (list($keyid, $darr) = each($arr_prod_param)) {
			//TEMPORARY CODE FOR AW12-13 GO LIVE (UX PROJECT) - Gareth wanted to disable the EQUESTRIAN filter from the featured list, in the top hover menus
			if ($a_name_prefix=="featured" && $keyid==20) continue;
			
			$count++;
			$ret_url_list[$count]["url_with_title"] = "<a name='prodhover_" . $prodtypeid . "_" .  $a_name_prefix . "_" . $keyid . "' href='" . $this->url_getOneClickFilterURL($url, $darr['desc']) . "'>" . $conn->htmlentities_demo($darr['desc']) . "</a>";
			//$ret_url_list[$count]["title"] = $desc;
		}
		/*
		if ($this->_tableData['column_id']=="lengthid") { //for LENGTH, we also add "Ankle boots" as a hard coded URL			
			$count++;
			$ret_url_list[$count]["url_with_title"] = $this->getLengthFacet_AnkleBootsLink();			
		}
		*/
		return $ret_url_list;
	}	
	
	/* Specific filter type functions - Shoe type */
	public function getProductShoeTypeByProductID($productid) {
		/* 	pre: called by PDP to get one shoe type out that is linked with this $productid. We assume this $productid is of a "shoe" prodtypeid (4) only since these are only associated with shoe types. However this function is not limited to other product types should we need to use it for a similar purpose for ankle boots (for example, prodtypeid 9) in the future. 
			post: returns the shoetypeid and description array
		*/
		$arr_prod_param_shoetype = $this->fetchAll("AND p.productid=$productid");
		return $arr_prod_param_shoetype;
	}

 	/* Length filter */
	public function getLengthKneeHighID() {
		/* 
		 * post: returns the KNEE HIGH filter ID (hard-coded, taken from our prod_param_length table, for usage by the list.php controller.
		 */
		return 1; 
	}
}
