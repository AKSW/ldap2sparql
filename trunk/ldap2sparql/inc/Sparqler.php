<?php
/**
 * Class for accessing SPARQL endpoint via http post or API calls
 *
 * @package backend
 * 
 */
 
#define("RDFAPI_INCLUDE_DIR", '/home/seebi/projects/powl/trunk/ldap2sparql/api/');
#require_once RDFAPI_INCLUDE_DIR . 'RdfAPI.php';
#require_once RDFAPI_INCLUDE_DIR . 'sparql/SPARQL.php';

 class Sparqler {
 	private $verbose = false;
 	private $RDF_Model;
 	private $query_client;
 	private $client;
 	private $options;
 	
 	
 	/**
 	 * Constructor
 	 * 
 	 * @param array @options SPARQL endpoint access information 
 	 */
 	function Sparqler($options, $verbose) {
 		
 		$this -> options = $options;
 		$this -> verbose = $verbose;
 		if ($this -> verbose)
 			print_r("QueryMachine: <p/> \n");
			
 			/*$this -> client = ModelFactory::getSparqlClient($this -> options["server"]);
 			$this -> query_client = new ClientQuery();
			if ($this -> options["defaultgraph"] != "") { 
				$this->query_client->addDefaultGraph($this -> options["defaultgraph"]);
		  }
 			*/
	}

 	/**
 	 * Sends a query over the RAP API
 	 * 
 	 * @param String @query Query to be send
 	 * @return Array @result RAP Result set
 	 * 
 	 */
	function doQueryRAP($query) {
		$GLOBALS['dbConf']['type'] = 'MySQL';
		$GLOBALS['dbConf']['host'] = $this->options["host"];
		$GLOBALS['dbConf']['database'] = $this->options["db"];
		$GLOBALS['dbConf']['user'] = $this->options["user"];
		$GLOBALS['dbConf']['password'] = $this->options["pass"];

		$database = ModelFactory::getDbStore(
			$GLOBALS['dbConf']['type'],     $GLOBALS['dbConf']['host'],
			$GLOBALS['dbConf']['database'], $GLOBALS['dbConf']['user'],
			$GLOBALS['dbConf']['password']
		);

		$dbModel  = $database->getModel($this->options["model"]);
		$result = $dbModel->sparqlQuery($query);
		#var_dump($result);
		return $result;
	}

 	
 	/**
 	 * Sends a query to endpoint
 	 * 
 	 * @param String @query Query to be send
 	 * @return FileHandler @result Handler for temporary XML File
 	 * 
 	 */
 	function doQueryCURL($query) {
 		
			//$this->query_client->query($query);
		  //$result = $this->client->query($this->query_client);
		  //SPARQLEngine::writeQueryResultAsHtmlTable($result);
		 
			$url = "http://".$this->options["server"]."?query=".urlencode($query);
			
			if (isset($this->options["defaultgraph"]) && $this->options["defaultgraph"] != "") {
				if (ereg("^[^:]+$", $this->options["defaultgraph"])) {
					if ($this -> verbose) echo 'FileURI Local: '.$this->options["defaultgraph"]."<br />\n";
					$file = 'file:' . REAL_BASE . '/data/'. $this->options["defaultgraph"];
					$url .= "&default-graph-uri=".urlencode($file);				
				} else {
					if ($this -> verbose) echo 'File URI AsGiven: '.$this->options["defaultgraph"]."<br />\n";
					$url .= "&default-graph-uri=".urlencode($this->options["defaultgraph"]);									
				}
			}
			
			if ($this -> verbose) print_r("HTTP GET: ".$url."<p/> \n");

			if (!$temp_xml = fopen(REAL_BASE."log/lastresult.xml", "w+"))
				die ("Could not open ".REAL_BASE."log/lastresult.xml for writing ...\n");

			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_FILE, $temp_xml);
			$curl_result = curl_exec($ch);
			
			if ($curl_result==0)
				throw new Exception(52,52);

			fseek($temp_xml, 0);

		return $temp_xml;
 }
}
?>
