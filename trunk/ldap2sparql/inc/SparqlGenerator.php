<?php
/**
 * This class generates a Sparql String having Data for Mapping and Search 
 *
 * 
 * @package backend
 */

 

 class SparqlGenerator {

 	private $verbose = false;
 	private $options;
	private $mapping_intern = false;
	private $mapping_array = array("objectclass" => "rdf:type");
	private $dit_array = array("parent" => "ldap:hasParent", "child" => "ldap:hasChild");
 	private $Variable_Prefix = "_v";
 	private $existent_counter = 0;
 	
 	
 	private $prefix = array("ldap" => "http://purl.org/net/ldap#",
 						"rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#"
 						);
 	private $variables = array("Res", "Attr", "Val");
	private $ResourceVar = "";
	private $LdapData;
	 
	
	/**
	 * Constructor 
	 * 
	 * @param array $options Sparql Mapping Options
	 * @param bool  $is_mapping internal Mapping Flag
	 *
	 *   	
 	*/
 	
 	function SparqlGenerator($options, $is_mapping, $verbose) {
 		$this->verbose = $verbose;
 		$this->options = $options;
 		
 		if ($this -> verbose)
 			print_r("Sparql gestartet! <p/> \n");

 		$this->mapping_intern = $is_mapping;
 		
 		$this->prefix = $options["NAMESPACES"];
 		if (isset($options["MAPPING"]["OBJECTS&ATTRIBUTES"])) { 
 			$this->mapping_array = $options["MAPPING"]["OBJECTS&ATTRIBUTES"]; 		
 		 		if ($this -> verbose) {
					print_r ("<pre>");
					print_r($this->mapping_array);		
					print_r ("</pre>");
				} 
 		}	
 		else
 			if ($this -> verbose)
 				print_r("No Mapping File <p/> \n");
 		
 		if (isset($options["MAPPING"]["DIT_FUNCTIONS"])) {
 			 $this->dit_array = $options["MAPPING"]["DIT_FUNCTIONS"];
 		 		if ($this -> verbose) {
					print_r ("<pre>");
					print_r($this->dit_array);		
					print_r ("</pre>");
				} 
 		} 			 
 		
 		
 		$this->ResourceVar = $this->variables[0];
 		
 		
 	}
 	
 	/**
 	 * Function for getting RDF Properties out of LDAP Attributes for Search in SPARQL
 	 * 
 	 * @param String $ldapAttr LDAP Attribute
 	 * @return array returnArray RDF Properties 
 	*/
 	
 	private function getMappingResources($ldapAttr) {
 	 	$returnArray = array();
 	 	$key = strtolower($ldapAttr);
 	 	//$key = $ldapAttr;
 	 		if (isset($this->mapping_array[$key])) {
 	 			return explode('/', $this->mapping_array[$key]);
 	 		}
 	 		else
 	 			return array("ldap:".$key);
 	 	}
 	
 	/**
 	 * 
 	 * Generates an URL out of a DN using the configured data prefix (server etc.)
 	 * 
 	 * @param String $dn  The DN to be transformed
 	 * @return String $returnString URL encoded LDAP-URL
*/

 	
 	private function generateUrl($dn) {
 		$returnString = $this->prefix["data"];
 		$dn_array = explode(",", $dn);
//		print "<pre>";
//		print_r($dn_array);
//		print "</pre>";
		for ($i = count($dn_array) -1; $i >= 0; $i--) {
			$returnString = $returnString.$dn_array[$i];
			if ($i > 0)
				$returnString = $returnString.",";
		}
		
		return str_replace(" ","+",$returnString);
		
		//das muss eigentlich hin
		//return urlencode($returnString);	
 	}

 	/**
 	 * 
 	 * Generates an URL out of a DN using the configured data prefix (server etc.)
 	 * for use in regex
 	 * 
 	 * @param String $dn  The DN to be transformed
 	 * @return String $returnString URL encoded LDAP-URL
*/

 	
 	private function generateRegexUrl($dn) {
 		$returnString = $this->prefix["data"];
 		$dn_array = explode(",", $dn);
//		print "<pre>";
//		print_r($dn_array);
//		print "</pre>";
		for ($i = count($dn_array) -1; $i >= 0; $i--) {
			$returnString = $returnString.$dn_array[$i];
			if ($i > 0)
				$returnString = $returnString.",";
		}
		
		return str_replace(" ","\\\+",$returnString);
		
		//das muss eigentlich hin
		//return urlencode($returnString);	
 	}

 	
 	/**
 	 * 
 	 * Generates Base Value Constraint
 	 * 
 	 * @return String $returnString Value Constraint or Basic Graph Pattern constraining search scope
*/

 	private function generateBaseScope() {
 		//
 		$base = preg_replace("/ *, */" , ",", $this -> LdapData["Base"]);
 		$scope = $this -> LdapData["Scope"];
 		if ($this -> verbose) {
 			print_r("Base : $base <p/> \n");
 			print_r("Scope : $scope <p/> \n");
 		}
 		if (($base != "" && !$this->mapping_intern) || $scope == 0) {
 			switch ($scope) {
 			case "0":
 				if (!$this->mapping_intern)
 					//$returnString = $returnString . " FILTER regex(str(?".$this->ResourceVar.'),"^'.$this -> generateUrl($base).'$") .';
 					$returnString = $returnString . " FILTER (?".$this->ResourceVar." = <".$this -> generateUrl($base).">) .";
 				else { //
 					//dn splitten und value extrahieren
 					$rdns = explode(",", $base, 2);
 					$rdn = $rdns[0];
 					$elements = explode("=",$rdn);

					// neues attribut-wert paar wird als array fuer suche definiert
					// original suche muss objectclass=* sein
					
					$item = array();
					$item["Attr"] = trim($elements[0]);
					$item["Filter"] = "=";
					$item["Value"] = trim($elements[1]);

					 	 		if ($this -> verbose) {
 	 		print "<pre>";
 	 			print_r("Neues Item: <p/> \n");
 				print_r($item);
			print "</pre>";
					 	 		}																	
					$returnString = $this->generateItemClause($item, false)." . ";
					
 				}
 				
 				break;
 			case "1":
 				$returnString = $returnString . "?".$this->ResourceVar . " ".$this->dit_array["parent"]. " <".$this -> generateUrl($base)."> ";
 				break;
 			default:
 				$returnString = $returnString . " FILTER regex(str(?".$this->ResourceVar.'),"^'.$this -> generateRegexUrl($base).',.*$")  ';
 				break; 
 			}
 		}
 			
 		return $returnString;
 	}
 
 
 	 
 	/**
 	 * 
 	 * Recursive parsing of LDAP filter array
 	 * 
 	 * @param array $filterArrayComponent Part of LDAP filter in array format
 	 * @param bool $unionType Signals if function is in OR part of filter
 	 * @param bool $negation Signals if function is in NEG part of filter
 	 * @return String $returnString Complete graph patterns of filter
*/	 
 	 	
 
 	 private function parseFilterArray($filterArrayComponent, $unionType, $negation) {
 	 	$returnString = "";
 	 	$sizeOfArray = count($filterArrayComponent);
 	 	$counter = 0;
 	 	
 	 	ksort($filterArrayComponent, SORT_STRING);
 	 	
 	 	if ($this -> verbose) {
 	 		//print_r(krsort($filterArrayComponent, SORT_STRING) . "<p/> \n");
 	 		print_r("Array Groesse : $sizeOfArray <p/> \n");
 	 	}

			// DeMorgan
			if ($negation) {
				if ($this->verbose) print_r("DeMorgan Rule  <p/> \n");
				$unionType = !$unionType;
			}


 	 	while ($nextComponent = each($filterArrayComponent)) {
 	 		$s = strval($nextComponent[0]);
 	 		if ($this -> verbose) {
 	 		print "<pre>";
 	 			print_r("FilterArrayComponent: <p/> \n");
 				print_r($filterArrayComponent);
				print_r("NextComponent: <p/> \n");
 				print_r($nextComponent[1]);
		 		print_r($s);
			print "</pre>";
//			print_r("$s2 <p/> \n");

 	 		}
			$s2 = strstr($s, "_");

			  if ($unionType) 
			  	$returnString = $returnString . "{";
			
  	 		switch ($s2) {
	 			case "_AND" :
	 				if ($this -> verbose) print_r("&".$nextComponent[0]." gefunden: <p/> \n");
						$returnString = $returnString . $this -> parseFilterArray($nextComponent["value"], false, $negation);
					break;
 				case "_OR" :
 					if ($this -> verbose) print_r("| gefunden: <p/> \n");
 						$returnString = $returnString . $this -> parseFilterArray($nextComponent["value"], true, $negation);
 					break;
 		 		case "_NOT" :
 						$returnString = $returnString . $this -> parseFilterArray($nextComponent["value"], $unionType, !$negation);
					break;

				default : // Basic Triple
						$returnString = $returnString . $this -> generateItemClause($nextComponent["value"], $negation);
 	 					break;
	  	 		}

			  if ($unionType) 
			  	$returnString = $returnString . "}";
	  	 		
	  	 		
	  	 		if ($counter < $sizeOfArray - 1) {
	  	 			if ($unionType) {
 	 					if ($this -> verbose) print_r("Add UNION<p/> \n");
 	 					$returnString = $returnString . "\nUNION \n";
	  	 			}
	  	 			else {
	  	 				if ($this -> verbose) print_r("Add . <p/> \n");
	  	 				$returnString = $returnString . " . \n";
	  	 			}
 	 			}
	  	 		
			$counter++; 	 		
 		
			

	 		if ($this -> verbose) print_r("ReturnString: ".htmlentities($returnString). "<br />\n");
 		}	
 		return $returnString;	 
	}


 function unichr($u) {
   return mb_convert_encoding(pack("N",$u), mb_internal_encoding(), 'UCS-4BE');
  }

 /**
	 * Generates Triple Patterns plus Value Constraints for simple LDAP filter
	 * 
	 * @param array $itemArray Last array part of filter array
	 * @param bool $negation Signals negation of simple LDAP filter
	 * @return String @returnString Basic Triple Pattern
	 */
	
 	private function generateItemClause($itemArray, $negation) {
 		$returnString = "";
 		$f = ""; $prefix = ""; $suffix="";$sec_triple = ""; 	
 		$s = "?".$this -> ResourceVar;
 		
 		$filter = $itemArray["Filter"];
 		
 		$attr = strtolower($itemArray["Attr"]);
 		
 		$value_encoded = str_replace("*", ".*", $itemArray["Value"]);
 		$value_encoded = str_replace('\.*', '\*', $value_encoded);
 		// bei verwendung des html frontends entfernen
 		$value_encoded = str_replace('\\', '\\\\', $value_encoded); 
 		
 		
 		// unicode codepoints
		
 		while (substr_count($value_encoded,'\\') > 0) {
 			$v = strstr($value_encoded, '\\'); // Teil hinter ersten \
 			$v_decoded .= substr($value_encoded, 0, strpos($value_encoded, '\\')); // Teil bis ersten \
 			if ($this -> verbose) 
 				print_r("v: $v <p/> \n");
 			$pos = 0; 		
 			$next = substr($v, $pos + 2, 2);
 			//number of bytes in sequence equals first two bits (> or >= ??)
 			
 			$dec = hexdec($next);
 			if ($dec > 240) 
 				$bytes = 4;
 			else if ($dec > 224)
 				$bytes = 3;
 			else if ($dec > 192)
 				$bytes = 2;
 			else 
 				$bytes = 1;	
			
			$pos = 0;
			for ($a=0; $a < $bytes; $a++) {
				$v2 .= substr($v, $pos + 2 , 2).".";
				$pos += 4;
			}
			if ($this -> verbose) {
 			
 			print_r("v2: $v2 <p/> \n");
			print_r("pos: $pos <p/> \n");
			}
			$hex_values = explode('.',$v2);
			
			if ($this -> verbose) {
 			print "<pre>";
 			print_r($hex_values);
 			print "</pre>";
			}
			
			
						
			if ($bytes == 1)  // one byte
				$ucp = hexdec($hex_values[0]);
			else if ($bytes == 2) // two bytes
			  $ucp = (hexdec($hex_values[0]) - 192) * 64 + hexdec($hex_values[1]) - 128;
			else if ($bytes == 3) // three bytes
				$ucp = (hexdec($hex_values[0]) - 224) * 4096 + (hexdec($hex_values[1]) - 128) * 64 + hexdec($hex_values[2]) - 128;
			else
			  $ucp = (hexdec($hex_values[0]) - 240) * 262144 + (hexdec($hex_values[1]) - 128) * 4096 + (hexdec($hex_values[2]) - 128) * 64 + hexdec($hex_values[3]) - 128;

			if ($this -> verbose) print_r("ucp: $ucp <p/> \n");

 				if ($ucp < 256)  
 					$v_final .= "\\\\u"."00".dechex($ucp);
 				else if ($ucp < 4096)
 					$v_final .= "\\\\u"."0".dechex($ucp);
 				else if ($ucp < 65536)
 					$v_final .= "\\\\u".dechex($ucp);
 				else if ($ucp < 1048576)
 					$v_final .= "\\\\u"."000".dechex($ucp);
 				else if ($ucp < 16777216)
 					$v_final .= "\\\\u"."00".dechex($ucp);
 				else if ($ucp < 268435456)
 					$v_final .= "\\\\u"."0".dechex($ucp);
 				else
 					$v_final .= "\\\\u".dechex($ucp);

			
			$v_decoded .= $v_final;
			if ($this -> verbose) print_r("vdecoded: $v_decoded <p/> \n");
			$value_encoded = substr($v,$pos);
			unset($v2);
			unset($v_final);
			if ($this -> verbose) print_r("vencoded: $value_encoded <p/> \n");
 		}
 		
 		$value = $v_decoded.$value_encoded;
 		if ($this -> verbose) print_r("value: $value <p/> \n");
 		if ($this -> verbose) print_r("value_encoded(ende): $value_encoded <p/> \n");
 		
 		
 		
 		
 		$attrList = $this -> getMappingResources($attr);
 		if ($attr == "objectclass")
 			$objList = $this -> getMappingResources($value);
 		else
 			$objList = array($value);
 		
 		$union_counter = count($attrList) * count($objList);
 		
 		if ($this -> verbose) {
 			print "<pre>";
 			print_r($attrList);
 			print_r($objList);
 			print_r($union_counter . " Varianten <p/> \n");
 			print "</pre>";
 		}
 		 
 		for ($attrCounter=0; $attrCounter < count($attrList); $attrCounter++) {
 			for ($objCounter=0; $objCounter < count($objList); $objCounter++) {
				$p = " ".trim($attrList[$attrCounter])." ";
 			
 					
		 		//Filter und Negation
		 		if (($filter == "=") && !$negation) {
					if ($attr == "objectclass") {
 						$o = trim($objList[$objCounter]);
 					}				
					else {
						$this -> existent_counter++;
						$o = "?".$this->Variable_Prefix.strval($this -> existent_counter);
						$f = " FILTER regex(".$o.',"^'.$value.'$", "i")';
					}
 				}
		
 				
		 		else {
					$this -> existent_counter++;
					$o = "?".$this->Variable_Prefix.strval($this -> existent_counter);
					if ($negation) {
						$this -> existent_counter++;
						switch ($filter) {
							case "=*":
								$prefix = "OPTIONAL {";
								$suffix = "}";					
								$f = " FILTER (!bound(" . $o . "))";
								break;
							case "=":
								if ($attr == "objectclass") 
									$f = " FILTER ($o != ldap:".$value.")";
								else {
									$prefix = "OPTIONAL {";
									$suffix = "}";
							
									$o2 = "?".$this->Variable_Prefix.strval($this -> existent_counter);
									$if = " FILTER regex(".$o2.',"^'.$value.'$", "i")';
									$s2 = "?s".strval($this -> existent_counter);
									$sec_triple = $s2.$p.$o2." . ";
									$f = " FILTER ((!bound(" . $o . ") || !regex(".$o.',"^'.$value.'$", "i")) && (!bound('.$s2.') || '.$s." != ".$s2."))";
								}
								break;
								
							}						
 						}
 						else { //keine negation
							switch ($filter) {
 								case ">=":
 										$f =  " FILTER (".$o.' >= "'.$value.'")';
 									break;
 								case "<=":
 										$f =  " FILTER (".$o.' <= "'.$value.'")';
 									break; 									
							}
							
 						}
		 		}
 				if ($returnString != "") {
 					if ($attrCounter * $objCounter < $union_counter) {
 						if (!$negation)
 							$returnString .= " UNION ";
 						else
 							$returnString .= " . ";
 					}
 				}
				
				if ($union_counter > 1)
 					$returnString .= "{".$prefix.$sec_triple.$s.$p.$o.$if.$suffix.$f."}";
 				else
 					$returnString .= $prefix.$sec_triple.$s.$p.$o.$if.$suffix.$f;
 			}
 		}
 		
 		return $returnString; 		
 	}
  
  /**
   * Generates PREFIX definitions of SPARQL query
   * 
   * @param array $prefix Prefixes of used vocabulary, normally part of configuration file, rdf and ldap prefix otherwise
   * @return String @returnString SPARQL Prefix string
   */
  	
 	private function generatePrefixDecl($prefix) {
 		$returnString = "";
 		
 		while ($p = each($prefix)) {
 			$returnString .= "PREFIX ".$p["key"].": "."<".$p["value"].">\n";
 		}
 		return $returnString;
 	}

  /**
   * Generates SELECT Line of SPARQL query
   * 
   * @param array $filterData LDAP filter data array
   * @param bool $attributes_values Should the Query include all Triples of Entries ?
   * @return String @returnString SPARQL SELECT Line
   */
		
 	private function generateSelectClause($filterData, $attributes_values) {
 		$selectString = "SELECT DISTINCT ?".$this->variables[0]."\n";
 		if ($attributes_values)
 			$selectString = "SELECT DISTINCT ?".$this->variables[0].' ?'.$this->variables[1].' ?'.$this->variables[2]."\n";
 		return $selectString;
 	}

	/**
 	 * 
 	 * Generates WHERE Clause by initiating recursive parsing of Filter Array
 	 * 
 	 * @param array $filterData LDAP Search Filter in parsed format
 	 * @param bool $attributes_values Should the Query include all Triples of Entries ?
 	 * @return String $returnString WHERE Clause
*/	
 	
 	private function generateWhereClause($filterData, $attributes_values) {
 		$returnString = "WHERE {\n{ ".$this -> parseFilterArray($this->LdapData["Filter"], false, false)." . ".$this->generateBaseScope()." }\n"; 
 		if ($attributes_values && (!$this->mapping_intern || $this -> LdapData["Base"] == $this->options["BASE"] || $this->LdapData["Scope"] == 0))
 			$returnString	.= '?'.$this->variables[0].' ?'.$this->variables[1].' ?'.$this->variables[2];
 		return $returnString. " }\n";
 	}	


  /**
   * Generates Solution Ordering of SPARQL query
   * 
   * @param bool $ordered Ordered Yes or No
   * @param String $order_var Variable for ordering
   * @return String @returnString ORDER Prefix string
   */
	private function generateSolutionModifier($ordered, $order_var) {
		$returnString = "";
		if ($ordered)
			$returnString .= "ORDER BY ?".$order_var."\n";
			
		return $returnString;
	}
	
	
/**
   * Generates PROLOG of SPARQL query - basically calls generatePrefixDecl
   * 
   */
	private function generateProlog($prefix) {
		$prefix_stuff = $this -> generatePrefixDecl($prefix);
		return $prefix_stuff;
	}
	
	
	/**
	 * Generates Query for given LDAP Data - called by Backend Class
	 * 
	 * @param array $ldapData Set of LDAP Data (Base, Filter,...)
	 * @param bool $attributes_values Should the Query include all Triples of Entries ?
	 * @return String $returnString SPARQL Query
	 * 
	 */ 	
	public function generateQuery($ldapData, $attributes_values) {			
		$this->LdapData = $ldapData;
			
		$filterData = $ldapData["Filter"];
		
		$prolog = $this -> generateProlog($this -> prefix);
		$select_clause = $this -> generateSelectClause($filterData, $attributes_values); 
		$where_clause = $this -> generateWhereClause($filterData, $attributes_values);
 		$modifier_clause = $this -> generateSolutionModifier(true, $this->variables[0]);
		
		
		$query = $prolog.$select_clause.$where_clause.$modifier_clause;
		
		if ($this -> verbose)
			print "<pre>".htmlentities($query)."</pre>";

		if (isset($GLOBALS['loghandle'])) fwrite($GLOBALS['loghandle'], "$sparqlString\n");
		return $query;	
  }
 
 }
 
?>
