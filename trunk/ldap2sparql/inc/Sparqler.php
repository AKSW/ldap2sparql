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
		$GLOBALS['dbConf']['host'] = 'localhost';
		$GLOBALS['dbConf']['database'] = 'ontowiki';
		$GLOBALS['dbConf']['user'] = 'root';
		$GLOBALS['dbConf']['password'] = 'root';

		$database = ModelFactory::getDbStore(
			$GLOBALS['dbConf']['type'],     $GLOBALS['dbConf']['host'],
			$GLOBALS['dbConf']['database'], $GLOBALS['dbConf']['user'],
			$GLOBALS['dbConf']['password']
		);
		$dbModel  = $database->getModel("ldap://ldap.seerose.biz/");
		#$dbModel  = $database->getModel("http://3ba.se/conferences/");
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
				if (preg_match('[^:]+', $this->options["defaultgraph"])) {
					echo 'Local: ---'.$this->options["defaultgraph"].'---';
					$file = 'file:' . dirname($_SERVER['SCRIPT_FILENAME']) .'/data/'. $this->options["defaultgraph"];
					$url .= "&default-graph-uri=".urlencode($file);				
				} else {
					echo 'AsGiven: ---'.$this->options["defaultgraph"].'---';
					$url .= "&default-graph-uri=".urlencode($this->options["defaultgraph"]);									
				}
			}
			
			if ($this -> verbose) print_r("HTTP GET: ".$url."<p/> \n");

			if (isset($this->options["xml_temp_file"])) {
				if (!$temp_xml = fopen($this->options["xml_temp_file"], "w+"))
					throw new Exception(80,80);
			} else
				$temp_xml = tmpfile();

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
