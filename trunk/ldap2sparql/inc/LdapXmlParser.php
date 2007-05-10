<?php
/**
 * Parses SPARQL Xml result format
 *
 * @package backend
 * 
 */
 
 //define("RDFAPI_INCLUDE_DIR", "/home/denis/workspace/backend/rdfapi-php/api/");
//include(RDFAPI_INCLUDE_DIR . "RdfAPI.php");  

#include_once ("Resource.php");
#include_once ("Literal.php");
#include_once ("BlankNode.php");

class LdapXmlParser {
	
 	private $verbose = false;
 	private $array_results = array();
 	private $curArray = array();
 	private $curElement = "";
 	private $curVar = "";
 	private $curDataType = "http://www.w3.org/2001/XMLSchema#string";
 	private $curLiteralData = "";
 	
 	
 	function LdapXmlParser($verbose) {
 		
 		if ($this -> verbose) print_r("InitxmlParser <p/> \n");
		$this->verbose = $verbose;
		$this->xml_parser = xml_parser_create();
		xml_set_element_handler($this->xml_parser, array($this, "startElement"), array($this,"endElement"));
		xml_set_character_data_handler($this->xml_parser, array($this, "characters"));
		
 	
 	}
 	
 	
 	/**
 	 * Initializes Parsing of XML File
 	 * 
 	 * @param FileHandler $data Handler for opened XML File for Reading
 	 * @return array $array_results Results of Parsing in RAP array format
 	 */
 	function parse($data) {
  	//$fp = fopen("/home/denis/workspace/backend/daten.xml", "r");
		while ($d = fread($data, 4096)) {
			if (!xml_parse($this->xml_parser, $d))
				if ($this -> verbose)  print_r("Fehler im XML <p/> \n");
		}
		xml_parser_free($this->xml_parser);
		
	 //fclose($data);
		
		return $this -> array_results;
	}
 		
 	private function characters($parser, $data) {
 				
 		//print_r("characters : $data<p/> \n");
 		
 		if ($this -> curElement == "URI" || $this -> curElement == "BNODE" ||$this -> curElement == "LITERAL") {
 		switch ($this -> curVar) {
 			case "Res":
				//print_r("Neue Resource : $data <p/> \n"); 			
 				$this -> curArray["?Res"] = new Resource($data);
 				break;
 			case "Attr":
 				//print_r("Neues Attr : $data <p/> \n");
 				$this -> curArray["?Attr"] = new Resource($data);
 				break;
 			case "Val":
 				
 				if ($this -> curElement != "LITERAL") {
 					//print_r("Neue Resource als Val: $data <p/> \n");
 					$this -> curArray["?Val"] = new Resource($data);
 				}
 				else {
 					$this -> curLiteralData .= $data;
 					
 					
 					$l = new Literal($this -> curLiteralData);
					$l -> setDatatype($this -> curDataType); 					
 					$this -> curArray["?Val"] = $l;
 					
 				}
 				break;
 		}
 		}
 	}
 	
 	private function startElement($parser, $name, $attrs) {
 		
 		
 		//if ($this -> verbose) print_r("startElement : $name --- $attrs <p/> \n");
 		
		$this -> curElement = $name;
		 		
 		switch ($name) {
 			case "RESULT":
 				$this -> curArray = array();
 				break;
 			case "BINDING":
 				$this -> curVar = $attrs["NAME"];
 				//if ($this -> verbose) print_r("Variable(Binding gefunden) : $this->curVar <p/> \n");
 				break;
 			case "LITERAL":
 				$this -> curDataType = $attrs["DATATYPE"];
 				//if ($this -> verbose) print_r("LITERAL : $this->curDataType <p/> \n");
 				break;
 		}	
 		
  	}
 	
 	private function endElement($parser, $name) {
 		//global $curArray, $array_results;
 		
 		//if ($this -> verbose) print_r("endElement : $name  <p/> \n");
 		
 		if ($name == "RESULT") { 			
 			$this -> array_results[] = $this -> curArray;
 		}
 		
 		if ($name == "BINDING") {
 			$this->curVar = "";
 		}
 		if ($name == "URI" || $name == "BNODE" || $name == "LITERAL") {
 			$this->curType = "";
 			$this->curVar = "";
 		}
 		if ($name == "LITERAL") {
 			$this -> curDataType = "http://www.w3.org/2001/XMLSchema#string";
 			$this -> curLiteralData = "";
 			
 		}
 	}
 	}
?>
