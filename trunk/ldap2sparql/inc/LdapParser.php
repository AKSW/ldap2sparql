<?php
/**
 * Parses LDAP filter string and creates array structure
 * Left recursive parser - functions have names of grammar elements
 * @package backend
 * 
 */

define("LDAP_FILTER_ERROR", 42);
define("SYNTAXCHAR", 3);
define("FILTER", 2);
define("VALUE", 1);
define("ATTR", 0);




class LdapParser { 


private $filter = "";
private $curPos = 0;
private $filterLength = 0;


private $filterArray = array();
private $attr_list = array();
private $verbose = false;

function LdapParser($verbose) {
	$this->verbose = $verbose;
	if ($this -> verbose)
		print_r("Parser gestartet!<p/> \n");
}	

/**
 * Parses filter String
 * 
 * @param String @filterString String to parse
 * @return array @returnArray Filter array structure
 * 
 */

function parse($filterString) {
	$this->filter = $filterString;
	if ($this -> verbose)
		print_r($this -> filter."<p/");
		
	$this -> filterLength = strlen($this -> filter);
	$this -> filterArray["Filter"] = $this -> filter(0);
	$this -> filterArray["Attrs"] = $this -> attr_list;
	
	return $this -> filterArray;
}

private function parsingError($error) {
	throw new Exception($error, LDAP_FILTER_ERROR);
}
	
		
private function moveAhead($type) {
//	$savePos = $curPos; //Soll der Zaehler weiterlaufen
	

	$returnString = "";
	
	
	//$curPosCharacter = $this -> filter[$this -> curPos];
	
	$curPosCharacter = $this -> lookAhead(true);
	
	
	if ($this -> verbose)
		echo("$curPosCharacter $this->curPos $this->filterLength<p/> \n");

	
	if ($type == FILTER) {
			if 	(($curPosCharacter == '~') || ($curPosCharacter == '>') || ($curPosCharacter == '<')) {
				if ($this -> verbose)
					echo("Zeichen $curPosCharacter als Teil eines Filters gefunden<p/> \n");
				$returnString .= $curPosCharacter;
				$this -> curPos++;
				$curPosCharacter = $this -> lookAhead(true);
				// Jetzt muss ein "=" kommen
				if ($curPosCharacter == '=') {
					$returnString .= $curPosCharacter;
					$this -> curPos++;
			
				}
				else 
					$this -> parsingError("kein = nach ~<>");
			}	
			else 
			if ($curPosCharacter == '=') {
				if ($this -> verbose)
					echo("Zeichen $curPosCharacter als Teil eines Filters gefunden<p/> \n");
					$returnString .= $curPosCharacter;
					$this -> curPos++;
					$curPosCharacter = $this -> lookAhead(true);
					// Jetzt kann noch ein "*" kommen
					if ($curPosCharacter == '*') {
						//gehoert es zum '=' (Present) oder zum Substringpattern danach ?
						if ($this -> filter[$this -> curPos + 1] == ')') {
							$returnString .= $curPosCharacter;				
							$this -> curPos++;
						}
					}
			}
			
			
	}
	
	else if ($type == SYNTAXCHAR) {
		
		if ( ($curPosCharacter == '(') || ($curPosCharacter == ')') || 
				($curPosCharacter == '&') || ($curPosCharacter == '|') || 
				($curPosCharacter == '!')) {
			
			$returnString .= $curPosCharacter;
			$this -> curPos++;
		
		}
		else
			$this -> parsingError("Syntaxchar erwartet");
	}
	else if ($type == VALUE) {
		
			$escape = false;
			while ((($curPosCharacter != ')') && ($curPosCharacter != '(')) || ($escape == true)) {
				if ($curPosCharacter == '\\')
					$escape = true;
				else
					$escape = false;
					
				$returnString .= $curPosCharacter;
		
				$this -> curPos++;
				$curPosCharacter = $this -> lookAhead(false);
			}
	}
	else if ($type == ATTR) {
			
			while ( 
				($curPosCharacter != '=') && 
				($curPosCharacter != '~') && ($curPosCharacter != '>') &&  ($curPosCharacter != '<') &&
				($curPosCharacter != ')') && ($curPosCharacter != '(')) {				
	
				$returnString .= $curPosCharacter;
		
				$this -> curPos++;
				$curPosCharacter = $this -> lookAhead(true);
				
			}	
	}
	
	
	
	return trim($returnString);
}

private function lookAhead($space) {
	if ($this -> curPos == $this -> filterLength)
		$this -> parsingError ("Ende des Filters erreicht". $this -> curPos .":". $this -> filterLength);
		
	$returnChar = $this -> filter[$this -> curPos];
	if ($returnChar == " ") {
		while ($this -> filter[$this -> curPos + 1] == " ") {
			$this -> curPos++;
		}
		if ($space) {
			$this -> curPos++;
			$returnChar = $this -> filter[$this -> curPos];
		}
			
	}
	
/*	$a = $this -> filter[$this -> curPos];
	while ($a == " ") {
		echo($a." : ". $this->curPos."<p/> \n");
		$this -> curPos++;
		$a = $this -> filter[$this -> curPos];
	}
*/		
	return $returnChar; 
}

private function match($token) {
	return !strcmp($this -> moveAhead(SYNTAXCHAR), $token);
}	

// "(" filtercomp ")"
private function filter($counter) {
	if ($this -> verbose)
		echo("filter()<p/> \n");
	$this -> openBracket();
	$returnArray = $this -> filterComp($counter);
	$this -> closeBracket();
	return $returnArray;
}

private function filterComp($counter) {
	$returnArray = array();
	if ($this -> verbose)
		echo("filterComp()<p/> \n");
	$value = $this -> lookAhead(true);
	switch ($value) {
		case ('&') :			
			$returnArray[$counter."_AND"] = $this -> andOp();
			break;
		case ('|') :
			$returnArray[$counter."_OR"] = $this -> orOp();
			break;
		case ('!') :
			$returnArray[$counter."_NOT"] = $this -> notOp();
			break;
		default :
			$returnArray[] = $this -> item();			 
			break;
	}
	
	return $returnArray;
}


private function andOp() {
	if ($this -> verbose)
		echo("andOp()<p/> \n");
	$this -> moveAhead(SYNTAXCHAR); //noch checken ob wirjlich "&" ?
	
	return $this -> filterList();
	
}

private function orOp() {
	if ($this -> verbose)
		echo("orOp()<p/> \n");

	$this -> moveAhead(SYNTAXCHAR); //noch checken ob wirjlich "&" ?
	
	return $this -> filterList();	
	
}

private function notOp() {
	if ($this -> verbose)
		echo("notOp()<p/> \n");

	$this -> moveAhead(SYNTAXCHAR); //noch checken ob wirjlich "&" ?
	return $this -> filter(0);	

}

private function filterList() {
	$returnArray = array();
	$counter = 0;
	if ($this -> verbose)
		echo("filterList()<p/> \n");
	//Wieviele Filter gehoeren dazu ?
	while ($this -> lookAhead(true) != ')') {
		$counter ++;
		$filterArray = $this -> filter($counter);
		$returnArray = array_merge($returnArray,$filterArray);
		//$returnArray[] = $this -> filter(); //fuer zusaetzliche ebene
	}	
	return $returnArray;
}

private function item() {
	$returnArray = array();
	
	if ($this -> verbose)
		echo("item()<p/> \n");
	// Attribut immer zuerst
	$attribute = $this -> attr();
	$filterType = $this -> filterType();	
	$value = "";
	
	if ($filterType == "=*") {
		$this -> present();
	}
	else {
		$value = $this -> value();
		
		switch ($filterType) {
			case "=":
				if (strpos($value, "*") != FALSE) {
					if ($this -> verbose)
						echo("Substring : $value <p/> \n");
					$filterType = "=";
				}
				else {
					if ($this -> verbose)
						echo("Equal : $value <p/> \n");				
				}
				//$this -> equalOrSubString();
				break;
			case "~=":
				$this -> approx();
				break;
			case ">=":
				$this -> greater();
				break;
			case "<=":
				$this -> less();
				break;	
		}
	}
	$returnArray["Attr"] = $attribute;
	$returnArray["Filter"] = $filterType;
	$returnArray["Value"] = $value;
	return $returnArray;
	
}

private function simple() {
	if ($this -> verbose)
		echo("simple()<p/> \n");
	$this -> value();
}

private function attr() {
	if ($this -> verbose)
		echo("attr()<p/> \n");
	$attr = $this -> moveAhead(ATTR);
	if ($this -> verbose)
		echo("Attribut : $attr<p/> \n");
	
	// Attribut zu Liste hinzufuegen fuer spaeteres mapping
	if (!in_array(strtolower($attr), $this->attr_list))
		$this->attr_list[] = strtolower($attr);
	
	return $attr;
}

private function filterType() {
	$filterType = $this -> moveAhead(FILTER);
	
	if ($this -> verbose)
		echo("Filtertyp : $filterType <p/> \n");
	
	return $filterType;
}

private function present() {
	if ($this -> verbose)
		echo("present()<p/> \n");	
}



private function equalOrSubstring() {
	if ($this -> verbose)
		echo("equalOrSubString()<p/> \n");
	$value = $this -> moveAhead(VALUE);
	// regular Expression or not ?
	
	if (strpos($value, "*") != FALSE && $this->verbose)
		echo("Substring : $value<p/> \n");
	else
		echo("Equal : $value<p/> \n");
	
	
}

private function approx() {
	if ($this -> verbose) echo("approx()<p/> \n");
}

private function less() {
	if ($this -> verbose) echo("less()<p/> \n");
}

private function greater() {
	if ($this -> verbose) echo("greater()<p/> \n");
}
	

private function value() {
	$val = $this -> moveAhead(VALUE);
	if ($this -> verbose) echo("Value : $val<p/> \n");
	return $val;
}

// "("
private function openBracket() {
	if ($this -> verbose)
		echo("openBracket()<p/> \n");
	if (!$this -> match("(")) 
		$this -> parsingError("Should be open Bracket<p/> \n");
}

// ")"
private function closeBracket() {
	if ($this -> verbose)
		echo("closeBracket()<p/> \n");
	if (!$this -> match(")"))
		$this -> parsingError("Should be close Bracket<p/> \n");
}

}



?>
