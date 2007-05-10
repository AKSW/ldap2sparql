<?php
/**
 * Created on Jun 14, 2006
 *
 * LdifGenerator generates LDIF using RAP's internal Representation of Result Set
 *  
 * @package backend
 **/



 
include("LdapXmlParser.php");
include("user_functions.php");


 
class LdifGenerator {

	# the maximum length of the ldif line
	private $MAX_LDIF_LINE_LENGTH = 77;
	# Default CRLN
	private $br = "\n";
	private $verbose = false;
	private $options;
	private $mapping_rev_array = array("rdf:type" => "objectClass");
	private $mapping_intern;
	private $user_array = array();
	private $dit_array = array();
	private $base;
	
 	private $namespaces = array("http://purl.org/net/ldap#" => "ldap"  ,
 						"http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf"
 						);
 						
 	private $prefix = array("ldap" => "http://purl.org/net/ldap#" , "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#");					
 						
/**
 *  
 * @param Array $options Options from Backend
 * @param bool $mapping Intern Mapping Flag
 * @param bool $verbose Debug Output
 **/
	
	
  function LdifGenerator($options, $mapping, $verbose) {
		$this -> options = $options;
		$this -> verbose = $verbose;


		$this->mapping_intern = $mapping;
		
		if ($this -> verbose) {
			if ($mapping) 
				print_r("Ldif Generator mapping intern <p/> \n");
			else	
				print_r("Ldif Generator mapping extern <p/> \n");
		}
	
		$this -> prefix = $options["PREFIXES"];
		$this -> namespaces = $options["NAMESPACES"];
		$this -> user_array = $options["MAPPING"]["USER_FUNCTIONS"];
		$this -> dit_array = $options["MAPPING"]["DIT_FUNCTIONS"];
		
 		$this->mapping_rev_array = $options["MAPPING"]["MAPPING_REVERSE"]; 		
		
		$this->base = $options["BASE"];		

/*		
 		else
 			if ($this -> verbose)
 				print_r("No Mapping File <p/> \n");
*/		
		if ($this -> verbose) {
			print "<pre>";
			print_r("Ldif Options");
			print_r($options);
			print "</pre>";
		}	
 		
	}
	
	/**
	 * Returns LDAP Attributes for a given RDF Property
	 * 
	 * @param String $ns Namespace of RDF Property
	 * @param String $rdf_res Ressource Name
	 * @return Array $returnArray LDAP Attributes as Strings
	 * 
	 */
	
	private function getLdapValues($ns, $rdf_res) {
 	 	$returnArray = array();
 	 	$pref = $this -> namespaces[$ns];
 	 	
 	 	//$key = strtolower($pref.":".$rdf_res);
		$key = $pref.":".$rdf_res;
 	 	if (isset($this->mapping_rev_array[$key])) {
 	 		return explode('/', $this->mapping_rev_array[$key]);
 	 	}
 	 	else {
 	 		if ($this -> mapping_intern)
 	 			return array();
 	 		else
 	 			return array($rdf_res);
 	 	}
 	 }
		
	/**
	 * Strips either last Rhombus or Slash of URI
	 * 
	 * @param String $uri  URI to be stripped
	 * @param bool $back if true the part after stripped character is return, otherwise beginning 
	 */
	private function stripRhombusOrSlash($uri, $back=false) {
		$last_strip_pos = strrpos($uri, '#');
		if (!$last_strip_pos)
			$last_strip_pos = strrpos($uri, '/');
		if ($back)
			return substr($uri, 0, $last_strip_pos + 1);
		else 
			return substr($uri, $last_strip_pos + 1);
	}
	

  /**
   * Generates one LDIF Entry
   * 
   * @param String $entry URI of entry to be generated
   * @param array $list  RAP-like Array containing only Data of one Entry
   */
  
	function generateEntry($entry, $list, $attrs) {
		$returnArray[] = array();
		$returnString = "";
		
		
/*		if ($this -> verbose) {
		print "<pre>";
		print_r("GenerateEntry");
		print_r($list);
		print "</pre>";
		}
*/
	
		for ($i = 0; $i < count($list); $i++) {
			$element = $list[$i];
			$attr_uri = $element["?Attr"]->getUri();			
			$attr = $this -> stripRhombusOrSlash($attr_uri, false);
			$attr_ns = $this -> stripRhombusOrSlash($attr_uri, true);
			
			$ldap_attrs = $this -> getLdapValues($attr_ns, $attr);
			
/*			if ($this->verbose) {
			print "<pre>";
			print_r("Attribut $attr : <p/>");
			print_r($ldap_attrs);
			print "</pre>";			
			}
*/
			$val_obj = $element["?Val"];
			
			for ($l=0;$l<count($ldap_attrs);$l++) {
				$attr = trim($ldap_attrs[$l]);
			
				//MAPPING einarbeiten
			
				if (get_class($val_obj) == "Literal") { //Literal
					if ($this -> verbose)
 						print_r($attr_uri . " ist Literal<p/> \n");

					if ($attr != "label" && $attr != "dn") { //kann dann auch raus
						if ($val_obj->getDataType() == "http://www.w3.org/2001/XMLSchema#base64Binary") {
							$str = str_replace(" ", "",$val_obj->getLabel());
						 	//$returnArray[$i+1] = $this->multi_lines_display(sprintf('%s:: %s',$attr,$str));
						 	$returnArray[] = array($attr.":" => $str);
						}
						 else {
/*						 	if (!$this -> is_safe_ascii($val_obj->getLabel()))
								//$returnArray[$i+1] = $this->multi_lines_display(sprintf('%s:: %s',$attr,base64_encode($val_obj->getLabel())));
								$returnArray[] = array($attr.":" => base64_encode($val_obj->getLabel()));
							else */
						    	//$returnArray[$i+1] = $this->multi_lines_display(sprintf('%s: %s',$attr,$val_obj->getLabel()));
						    	$returnArray[] = array($attr => $val_obj->getLabel());
						}
					}
				}
 				else { // Resource
 						
 					$val_uri = $val_obj->getUri();
 					$val_val = $this -> stripRhombusOrSlash($val_uri, false);
 					$val_ns = $this -> stripRhombusOrSlash($val_uri, true);
 					
 					//4 Möglichkeiten
 					// 1. objectClass Attr -> Objekt aus Mappingarray oder Value ohne NS
 					// 2. ldap dn Value (z.B. roleOccupant)-> DN
 					// 3. userfunction Value -> User function
 					// 4. irgendwas -> URI
 					
 					// 1.
 					if (strtolower($attr) == "objectclass") {
 						if ($this -> verbose)
 							print_r($attr_uri . " ist objectClass Attribut <p/> \n");

 						//
 						// fuer objectClass - Tripel Value foaf:Person -> inetOrgPerson 
 						$ldap_objects = $this -> getLdapValues($val_ns, $val_val);
 						for ($k=0;$k<count($ldap_objects);$k++) {
							$val_ldap = trim($ldap_objects[$k]);
 							if ($this -> verbose)
 								print_r($val_ldap . " ist $val_val <p/> \n");


							//$returnArray[$i+1] = $this->multi_lines_display(sprintf('%s: %s',$attr,$val_ldap));
							$returnArray[] = array($attr => $val_ldap);
							//internes Mapping - RDN Daten
							
							$val_ldap_upper = strtoupper($val_ldap);
							if (isset($this->options["MAPPING"][$val_ldap_upper])) {
		 						if ($this -> verbose)
 									print_r($this->options["MAPPING"][strtoupper($val_ldap)]);
								
								$dnName = $this->options["MAPPING"][$val_ldap_upper]["dnName"];
								$dnValue = $this->options["MAPPING"][$val_ldap_upper]["dnValue"];
								$dnHash =  strtolower($this->options["MAPPING"][$val_ldap_upper]["dnFunction"]) == "hash";
							}
 						}
 					}
 					// 2.
 					else if ($val_ns == $this -> prefix["data"]) {
 						
 						$attr_plus_pref = $this->namespaces[$attr_ns].":".$attr;
 						if ($attr_plus_pref != $this->options["MAPPING"]["DIT_FUNCTIONS"]["parent"] && $attr_plus_pref != $this->options["MAPPING"]["DIT_FUNCTIONS"]["child"]) {
 						
					 		if ($this -> verbose) {
 							 	print_r($attr . " ist DN Attribut <p/> \n");
								print_r($attr_plus_pref . " NS <p/> \n");
							}
 								$value = $this -> generateDN(urldecode($val_uri));
 								//$returnArray[$i+1] = $this->multi_lines_display(sprintf('%s: %s',$attr,$value));
 								$returnArray[] = array($attr => $value);
 						}
 					}
 					// 3.
					else if ($user_function = $this->user_array[$attr]) {
						if ($this -> verbose)
 							print_r($attr . " hat Userfunction <p/> \n");
						 
 						$value = $user_function($val_uri);
 						//$returnArray[$i+1] = $this->multi_lines_display(sprintf('%s: %s',$attr,$value));
 						$returnArray[] = array($attr => $value);
 					}
 					// 4
 					else {
						if ($this -> verbose)
 							print_r($attr . " ist Irgendwas <p/> \n");
 							print_r("Value $val_uri <p/> \n");
 							print_r("Val_val $val_val <p/> \n");
 							print_r("Val_ns $val_ns <p/> \n");
							print_r("data_ns ". $this->prefix["data"] ."<p/> \n"); 						
 							//$returnArray[$i+1] = $this->multi_lines_display(sprintf('%s: %s',$attr,$val_uri));
 							$returnArray[] = array($attr => $val_uri);
 					}
 				}
			}
		}
		
		
		//DN
		//extern
		
		if (!$this->mapping_intern) { //extern -> URI = LDAP URI -> DN
			$dn = $this -> generateDN($entry);
			# display dn
		}
		else {  //intern
			if ($dnValue == "IRI") { // RDN=IRI
				if (!$dnHash) {
					$uri = $entry;
					$dn = $dnName."=".$uri;
					if (!$this -> is_safe_ascii($uri))
						$returnString .= $this->multi_lines_display(sprintf('%s:: %s',$dnName,base64_encode($uri)));	
					else
						$returnString .= $this->multi_lines_display(sprintf('%s: %s',$dnName,$uri));
				}
				else {
					$hash = mhash(MHASH_MD5,$entry);
					$dn = $dnName."=".$hash;
					$returnString .= $this->multi_lines_display(sprintf('%s:: %s',$dnName,base64_encode($hash)));
				}
				
			}
		}


		for ($i = 0; $i < count($returnArray); $i++) {
			while ($n = each($returnArray[$i])) {
				if (!$this -> is_safe_ascii($n[1]))
					$returnString .= $this->multi_lines_display(sprintf('%s: %s',$n[0].':',base64_encode($n[1]))); 
				else
					$returnString .= $this->multi_lines_display(sprintf('%s: %s',$n[0],$n[1]));
				if ($this->mapping_intern && !isset($dn)) { //DN intern
						if ($this -> verbose)
 								print_r($n[1] . " Value <p/> \n");
				
					if (strtolower($dnValue) == $n[0]) {
						if (!$dnHash) {
							$dn = $dnName."=".$n[1];
							
							if ($dnValue != $dnName)  // Attribute u. Wert noch nicht im Eintrag
								$returnString .= $this->multi_lines_display(sprintf('%s: %s',$dnName,$n[1]));
						}
						else {
							$hash=mhash(MHASH_MD5,$n[1]);
							$dn = $dnName."=".$hash;
							$returnString .= $this->multi_lines_display(sprintf('%s: %s',$dnName,base64_encode($hash)));
						}
					}
				}
			}
		}

		//jetzt noch Base dranhaengen
		if (isset($dn)) {
			if (isset($this->base))
				$dn .= ",".$this->base;

				if ($this->is_safe_ascii($dn)) //jetzt erst url decode?
					$returnString = $this->multi_lines_display(sprintf('dn: %s',urldecode($dn))).$returnString;
				else
					$returnString = $this->multi_lines_display(sprintf('dn:: %s',base64_encode($dn))).$returnString;
					
					$returnString .= "\n"; //neue Zeile 	
		}
		else // keine dn -> kein eintrag
			unset($returnString);			


/*			if ($this->verbose) {
			print "<pre>";
			print_r("Return String : <p/>");
			print_r($returnString);
			print "</pre>";			
			}
*/

		return $returnString;
	}
		
	/**
	 * Generates DN from LDAP URL
	 *
	 */	
	private function generateDN($url) {
		$returnString = "";
		
		if ($this -> verbose) print_r("GenerateDN : $url <p/> \n");
		
		$last_slash_pos = strrpos($url, '/');
		$rest = substr($url, $last_slash_pos + 1);
			$dn_array = explode(",", $rest);
			for ($i = count($dn_array) -1; $i >= 0; $i--) {
				$returnString = $returnString.trim($dn_array[$i]);
				if ($i > 0)
					$returnString = $returnString.",";
			}	

		return $returnString;	
	}
			
	/**
	 * Generates RAP Array Result Set out of XML File Data
	 * 
	 * @param FileHandler $results File Handler to temporary XML File Containing Sparql XML Result
	 * @return $array $rap_array  The RAP Result Array
	 */		
		
	public function generateRapArray($results) {
		$returnString = "";
		if ($this -> verbose) print_r("XmlLdif : <p/> \n");
		$xmlParser = new LdapXmlParser($this -> verbose);
		$rap_array = $xmlParser -> parse($results);
		
		if ($this -> verbose) {
		print "<pre>";
		print_r("RAP like Array: <p/> \n");
		print_r($rap_array);
		print "</pre>";	
		}		
		
		fclose($results);
		
		return $rap_array;
	}
			
	
	/**
	 * Generates LDIF out of a complete RAP Array Result Set (all Variables)
	 * for internal Mapping
	 * 
	 * @param array $results RAP Result Set
	 * @param array $attrs   Attributes to return to LDAP Client
	 * @return String $returnString  LDIF String
	 */		
	public function generateLdif($results, $attrs) {
		$returnString = "";
		$length = 0;
		$offset = 0;
		if ($this -> verbose) print_r("Ldif : <p/> \n");

		if ($this -> verbose) {
		print "<pre>";
		print_r($results);
		print "</pre>";	
		}


		
		$cur_x = $results[0]["?Res"]->getUri();
		$newResTriple = $results[0];
		do {
/*
			print_r("cur_x : $cur_x <p/> \n"); 
			print_r("new_x : $new_x <p/> \n");		
			print_r("lenngth : $length <p/> \n");
			print_r("offset : $offset <p/> \n");
			print_r("length array : ".count($results)." <p/> \n");
*/			
			$new_x = $newResTriple["?Res"]->getUri();
			
			if ($new_x != $cur_x) {
				$returnString = $returnString . $this -> generateEntry($new_x,array_slice($results, $offset, $length), $attrs);
				$offset += $length;
				$length = 0;
				$cur_x = $new_x;
			}
		
			if ($length + $offset == count($results) - 1) 		 
				$returnString = $returnString . $this -> generateEntry($new_x,array_slice($results, $offset, $length+1), $attrs);
				
			$length++;
		}
		while ($newResTriple = next($results));

		return $returnString;
	}
	
	
		/**
	 * Open LDAP admin - Helper method to wrap ldif lines
	 * @param String $str the line to be wrapped if needed.
	 */
	private function multi_lines_display($str) {
		$length_string = strlen($str);
		$max_length = $this->MAX_LDIF_LINE_LENGTH;

		$output = '';
		while ($length_string > $max_length) {
			$output .= substr($str,0,$max_length).$this->br.' ';
	
			$str = substr($str,$max_length,$length_string);
			$length_string = strlen($str);

			/* need to do minus one to align on the right
			   the first line with the possible following lines
			   as these will have an extra space. */
			$max_length = $this->MAX_LDIF_LINE_LENGTH-1;
		}
		$output .= $str.$this->br;

		return $output;
	}
	
	/**
	 * Open LDAP admin - Helper method to check if the attribute value should be base 64 encoded.
	 * @param String $str the string to check.
	 * @return bool true if the string is safe ascii, false otherwise.
	 */
	private function is_safe_ascii($str) {
		for ($i=0;$i<strlen($str);$i++)
			if (ord($str{$i}) < 32 || ord($str{$i}) > 127)
				return false;
		return true;
	}	
}
?>