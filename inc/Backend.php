<?php
/**
 * Main Backend Class - Parses Ini File for Configuration and starts Search
 *
 * 
 * @package backend
 */

#define("RDFAPI_INCLUDE_DIR", '/home/seebi/projects/powl/trunk/ldap2sparql/api/');
define("RDFAPI_INCLUDE_DIR", './api/');
require_once RDFAPI_INCLUDE_DIR . 'RdfAPI.php';
require_once RDFAPI_INCLUDE_DIR . 'sparql/SPARQL.php';

include("LdapParser.php");
include("SparqlGenerator.php");
include("LdifGenerator.php");
include("Sparqler.php");
//include("vizualizer.php");


function printr($s) {
	print '<pre>';
	print_r($s);
	print '</pre>';
}

class Backend {
	
	private $verbose = false;
	private $global_options;
	
	private $parser,$sparqlGenerator,$sparqler,$ldif;


/**
	 * Backend Constructor
	 * 
	 * Parses specified Ini File and calls constructors of other classes
	 * 
	 * @param String $iniFile Ini File to parse
**/
	 	 
	function Backend($verbose, $iniFile) {
		$this -> verbose = $verbose;
		
		
		if ($this -> verbose)
			echo ("backend started !<br /> \n ");
		
//		if (!file_exists($iniFile)) {
//			die('ERROR: INI-File nicht vorhanden!');
//		}
		
		if (isset($iniFile))
		  $this->global_options = parse_ini_file('ini/'.$iniFile, true);
		else
			die("No INI File!"); 
		
		if ($this -> verbose) printr($this->global_options);

		$sparql_options = array();
		$ldif_options = array();
		
		$sparql_options["NAMESPACES"] = $this->global_options["NAMESPACES"];
		$ldif_options["PREFIXES"] = $this->global_options["NAMESPACES"];
		$ldif_options["NAMESPACES"] = array_flip($this->global_options["NAMESPACES"]);
		$sparql_options["BASE"] = $this->global_options["MAPPING"]["base"];
		$ldif_options["BASE"] = $this->global_options["MAPPING"]["base"];
		$mapping = $this->global_options["MAPPING"]["mapping"] == "internal";
		
		if ($this->global_options["MAPPING"]["mapping_file"] != "") {
			if ($this -> verbose)
				echo ("mapping file imported: ".$this->global_options["MAPPING"]["mapping_file"]. "<p/>\n");
			$sparql_options["MAPPING"] = parse_ini_file('ini/'.$this->global_options["MAPPING"]["mapping_file"], true);

			if ($this -> verbose) {
				print_r ("<pre>");
				print_r("Mappingfile : <p/> \n");
				print_r($sparql_options["MAPPING"]);
				print_r ("</pre>");
			}

			$o = $sparql_options["MAPPING"]["OBJECTS&ATTRIBUTES"];
			$u = $sparql_options["MAPPING"]["USER_FUNCTIONS"];
			$d = $sparql_options["MAPPING"]["DIT_FUNCTIONS"];
			
			if (isset($o)) $sparql_options["MAPPING"]["OBJECTS&ATTRIBUTES"] = array_change_key_case($o, CASE_LOWER);
			if (isset($u)) $sparql_options["MAPPING"]["USER_FUNCTIONS"] = array_change_key_case($u, CASE_LOWER);
			if (isset($d)) $sparql_options["MAPPING"]["DIT_FUNCTIONS"] = array_change_key_case($d, CASE_LOWER);

			$ldif_options["MAPPING"] = array_change_key_case($sparql_options["MAPPING"], CASE_UPPER);
			if (isset($o)) $ldif_options["MAPPING"]["MAPPING_REVERSE"] = $this->swapMappingArray($sparql_options["MAPPING"]["OBJECTS&ATTRIBUTES"]);

			//datei nicht vorhanden?
		}

		$this->parser = new LdapParser($this -> verbose);
		$this->sparqlGenerator = new SparqlGenerator($sparql_options, $mapping, $this -> verbose);
		$this->sparqler = new Sparqler($this->global_options["SPARQL_ENDPOINT"], $this->verbose);
			
		$this->ldif = new LdifGenerator($ldif_options, $mapping, $this -> verbose);
		
	}

/**
 * Swaps Parsed Data of Mapping.ini for use in generating LDIF 
 * 
 */ 		
	private function swapMappingArray($mapping_array) {
		$returnArray = array();
		$cur = $mapping_array;

		while ($nextComponent = each($cur)) {
			$ldap = strval($nextComponent[0]);
			$rdf = explode('/', $nextComponent[1]); 
		
			for ($l=0;$l<count($rdf);$l++) {
				$val = trim($rdf[$l]);
				if (array_key_exists($val, $returnArray)) 
					$returnArray[$val] .= " / ".$ldap;
				else
					$returnArray[$val] = $ldap;
			}
		} 
		
				
		return ($returnArray);			
	}

	/**
	 * Starts LDAP Search on Backend
	 * 
	 * @param array $request Array holding options for search
	 */	
	// Request ist Array mit allen Suchparametern
	function search($request) {
		$resultCode = 0;
		$ldifString = '';
		
		if ($this -> verbose) {
			echo ("Search started with parameters: <p/>\n");
			print_r($request);
		}

		// extract filter
		$filterString = $request["filter"];
		$attributes = $request["attrs"];

		try {
			$parserResult = $this->parser -> parse($filterString);	

			$attrArray = explode(" ", $request["attrs"]);
			$this -> LdapQueryArray["Attr"] = $attrArray;
			$this -> LdapQueryArray["Base"] = $request["base"];
			$this -> LdapQueryArray["Scope"] = $request["scope"];
			$this -> LdapQueryArray = array_merge($this -> LdapQueryArray, $parserResult);

			if ($this->global_options["MAPPING"]["mapping"] != "external") {
				if ($this->verbose)
					echo "Internal Mapping : complete query";	
				// Internal mapping -> No complete URIs -> Slower all in one query necessary
				$sparqlString = $this->sparqlGenerator -> generateQuery($this -> LdapQueryArray, true);
		
				#$sparqlResult = $this->sparqler->doQueryCURL($sparqlString);
		
				#$rap_array = $this -> ldif -> generateRapArray($sparqlResult);
				$rap_array = $this->sparqler->doQueryRAP($sparqlString);

				#echo "ENTRY : ". $entry ."<p/";	
				$ldifString = $this->ldif -> generateLdif($rap_array, $this -> LdapQueryArray["Attr"]);
			}
			else {
				// External mapping
				$sparqlString = $this->sparqlGenerator -> generateQuery($this -> LdapQueryArray, false);

				$sparqlResult = $this->sparqler->doQueryCURL($sparqlString);
				$rap_array = $this -> ldif -> generateRapArray($sparqlResult);
				#$rap_array = $this->sparqler->doQueryRAP($sparqlString);

				for ($entries=0;$entries < count($rap_array);$entries++) {
					$entry = $rap_array[$entries]["?Res"]->getURI();
					$entrySparql = 'SELECT ?Attr ?Val WHERE {<'.$entry.'> ?Attr ?Val}';
					if ($this->verbose)
						echo "Entrysparql : ". htmlentities($entrySparql) ."<p/";
				
					$finalResult = $this->sparqler->doQueryCURL($entrySparql); 
				  $rap_array2 = $this -> ldif -> generateRapArray($finalResult);
					#$rap_array2 = $this->sparqler->doQueryRAP($entrySparql);
				
				  $ldifString .= $this -> ldif -> generateEntry($entry, $rap_array2,$this -> LdapQueryArray["Attr"]);
				}
			}	
		//$ldifString = $this -> ldif -> generateXmlLdif($sparqlResult, $this -> LdapQueryArray["Attr"]);
		}
		catch (Exception $e) {
			if ($this->verbose)
				echo "Exception : ". $e->getMessage()."<p/";	
			$resultCode = $e->getCode();
		}

		$ldifString .= "\nRESULT\ncode: ".$resultCode."\n";
		if ($this -> verbose) printr($ldifString);
		return $ldifString; 
	}

}
?>
