<?php
ini_set("display_errors", "On");
error_reporting(E_ALL);

class GeoserverWrapper {
	var $serverUrl = '';
	var $username = '';
	var $password = '';

// Internal stuff
	public function __construct($serverUrl, $username = '', $password = '') {
		if (substr($serverUrl, -1) !== '/') $serverUrl .= '/';
		$this->serverUrl = $serverUrl;
		$this->username = $username;
		$this->password = $password;
	}

	private function authGet($apiPath) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->serverUrl.$apiPath);
		curl_setopt($ch, CURLOPT_USERPWD, $this->username.":".$this->password);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$rslt = curl_exec($ch);
		$info = curl_getinfo($ch);

		if ($info['http_code'] == 401) {
			return 'Access denied. Check login credentials.';
		} else {
			return $rslt;
		}
	}

	private function runApi($apiPath, $method = 'GET', $data = '', $contentType = 'text/xml') {
		$ch = curl_init();
		if (!strpos($url,'/rest/')) {
			$url = $this->serverUrl.'rest/'.$apiPath;
		}
		echo 'URL:'.$url.'<br>';die;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERPWD, $this->username.":".$this->password);
		if ($method == 'POST' || $method == 'PUT') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		} 

		if ($method == 'DELETE' || $method == 'PUT') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}

		if ($data != '') {
			curl_setopt($ch, CURLOPT_HTTPHEADER,
				array("Content-Type: $contentType",
				'Content-Length: '.strlen($data))
			);
		}

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$rslt = curl_exec($ch);
		$info = curl_getinfo($ch);

		if ($info['http_code'] == 401) {
			return 'Access denied. Check login credentials.';
		} else {
			return $rslt;
		}
	}

// Workspace APIs
	public function listWorkspaces() {
		return json_decode($this->runApi('workspaces.json'));
	}

	public function createWorkspace($workspaceName) {
		return $this->runApi('workspaces', 'POST', '<workspace><name>'.htmlentities($workspaceName, ENT_COMPAT).'</name></workspace>');
	}

	public function deleteWorkspace($workspaceName) {
		return $this->runApi('workspaces/'.urlencode($workspaceName), 'DELETE');
	}

// Datastore APIs
	public function listDatastores($workspaceName) {
//		echo '$workspaceName='.$workspaceName.'<br>';
		$s=$this->runApi('workspaces/'.urlencode($workspaceName).'/datastores.json');
//		echo '<pre>';print_r($s);echo '</pre>';
		return json_decode($s);
	}

	public function createPostGISDataStore($datastoreName, $workspaceName, $databaseName, $databaseUser, $databasePass, $databaseHost = 'localhost', $databasePort = '5432') {
		return $this->runApi('workspaces/'.urlencode($workspaceName).'/datastores', 'POST', '<dataStore>
			<name>'.htmlentities($datastoreName, ENT_COMPAT).'</name>
			<type>PostGIS</type>
			<enabled>true</enabled>
			<connectionParameters>
				<entry key="port">'.htmlentities($databasePort, ENT_COMPAT).'</entry>
				<entry key="Connection timeout">20</entry>
				<entry key="passwd">'.htmlentities($databasePass, ENT_COMPAT).'</entry>
				<entry key="dbtype">postgis</entry>
				<entry key="host">'.htmlentities($databaseHost, ENT_COMPAT).'</entry>
				<entry key="validate connections">true</entry>
				<entry key="encode functions">false</entry>
				<entry key="max connections">10</entry>
				<entry key="database">'.htmlentities($databaseName, ENT_COMPAT).'</entry>
				<entry key="namespace">'.htmlentities($workspaceName, ENT_COMPAT).'</entry>
				<entry key="schema">public</entry>
				<entry key="Loose bbox">true</entry>
				<entry key="Expose primary keys">false</entry>
				<entry key="fetch size">1000</entry>
				<entry key="Max open prepared statements">50</entry>
				<entry key="preparedStatements">false</entry>
				<entry key="Estimated extends">true</entry>
				<entry key="user">'.htmlentities($databaseUser, ENT_COMPAT).'</entry>
				<entry key="min connections">1</entry>
			</connectionParameters>
			</dataStore>');
	}

	public function createShpDirDataStore($datastoreName, $workspaceName, $location) {
		return $this->runApi('workspaces/'.urlencode($workspaceName).'/datastores', 'POST', '<dataStore>
			<name>'.htmlentities($datastoreName, ENT_COMPAT).'</name>
			<type>Directory of spatial files (shapefiles)</type>
			<enabled>true</enabled>
			<connectionParameters>
				<entry key="memory mapped buffer">false</entry>
				<entry key="timezone">America/Boise</entry>
				<entry key="create spatial index">true</entry>
				<entry key="charset">ISO-8859-1</entry>
				<entry key="filetype">shapefile</entry>
				<entry key="cache and reuse memory maps">true</entry>
				<entry key="url">file:'.htmlentities($location, ENT_COMPAT).'</entry>
				<entry key="namespace">'.htmlentities($workspaceName, ENT_COMPAT).'</entry>
			</connectionParameters>
			</dataStore>');
	}

	public function deleteDataStore($datastoreName, $workspaceName) {
		return $this->runApi('workspaces/'.urlencode($workspaceName).'/datastores/'.urlencode($datastoreName), 'DELETE');
	}

// Layer APIs
	public function listLayers($workspaceName, $datastoreName) {
		return json_decode($this->runApi('workspaces/'.urlencode($workspaceName).'/datastores/'.urlencode($datastoreName).'/featuretypes.json'));
	}

	public function createLayer($layerName, $workspaceName, $datastoreName, $description = '') {
		// Add the store's feature type:
		// If layerName is a shapefile, the shapefile should exist in store already; uploaded via external means
		// If layerName is a postgis database table, that table should already exist

		// Just in case it's a .shp and the .shp was included
		$layerName = str_replace('.shp', '', str_replace('.SHP', '', $layerName));
		return $this->runApi('workspaces/'.urlencode($workspaceName).'/datastores/'.urlencode($datastoreName).'/featuretypes.xml', 'POST', '<featureType>
			<name>'.$layerName.'</name>
			<nativeName>'.$layerName.'</nativeName>
			<description>'.htmlentities($description, ENT_COMPAT).'</description>
			<store class="dataStore"><name>'.htmlentities($datastoreName, ENT_COMPAT).'</name></store>
			</featureType>');
	}

	public function deleteLayer($layerName, $workspaceName, $datastoreName) {
		$this->runApi('layers/'.urlencode($layerName), 'DELETE');
		return $this->runApi('workspaces/'.urlencode($workspaceName).'/datastores/'.urlencode($datastoreName).'/featuretypes/'.urlencode($layerName), 'DELETE');
	}

	public function viewLayer($layerName, $workspaceName, $format = 'GML', $maxGMLFeatures = 1000000, $overrideServerURL = '') {
		// overrideServerURL = useful if using reverseproxy-like configurations
		if ($format == 'GML') {
			//die(urlencode($layerName).'/ows?service=WFS&version=1.0.0&request=GetFeature&typeName='.urlencode($workspaceName).':'.urlencode($layerName).'&maxFeatures='.$maxGMLFeatures);
			return $this->authGet(urlencode($workspaceName).'/ows?service=WFS&version=1.0.0&request=GetFeature&typeName='.urlencode($workspaceName).':'.urlencode($layerName).'&maxFeatures='.$maxGMLFeatures);
		} else if ($format == 'KML') {
			return $this->authGet(urlencode($workspaceName).'/wms/kml?layers='.urlencode($workspaceName).':'.urlencode($layerName));
		}
	}

	public function viewLayerLegend($layerName, $workspaceName, $width = 20, $height = 20) {
		return $this->authGet("wms?REQUEST=GetLegendGraphic&VERSION=1.0.0&FORMAT=image/png&WIDTH=$width&HEIGHT=$height&LAYER=".urlencode($workspaceName).':'.urlencode($layerName));
	}

	public function wfsPost($apiPath, $post) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->serverUrl.'wfs'.$apiPath);
		curl_setopt($ch, CURLOPT_USERPWD, $this->username.":".$this->password);
		if ($post != '') {
			curl_setopt($ch, CURLOPT_HTTPHEADER,
				array("Content-Type: text/xml",
				'Content-Length: '.strlen($post))
			);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$rslt = curl_exec($ch);
		$info = curl_getinfo($ch);

		if ($info['http_code'] == 401) {
			return 'Access denied. Check login credentials.';
		} else {
			return $rslt;
		}
	}

	public function executeWFSTransaction($WFSTRequest) {
		// WFS-T is just WFS really...
		return $this->wfsPost('', $WFSTRequest);
	}

// Style APIs
	public function listStyles() {
		return json_decode($this->runApi('styles.json'));
	}

	public function createStyle($styleName, $SLD) {
		$rv = $this->runApi('styles.xml', 'POST', '<style>
			<name>'.htmlentities($styleName, ENT_COMPAT).'</name>
			<filename>'.htmlentities($styleName, ENT_COMPAT).'.sld</filename>
			</style>');
		$this->runApi('styles/'.urlencode($styleName), 'PUT', stripslashes($SLD), 'application/vnd.ogc.sld+xml');
		return $rv;
	}
	
	public function updateStyle($styleName, $SLD) {
		$temp = $this->runApi('styles/'.urlencode($styleName), 'PUT', stripslashes($SLD), 'application/vnd.ogc.sld+xml');
		return (strlen($temp) == 0);
	}

	public function addStyleToLayer($layerName, $workspaceName, $styleName) {
		// Just adds style to the list of supported styles - then WMS requests can pass the desired style
		return $this->runApi('layers/'.urlencode($layerName).'/styles', 'POST', '<style><name>'.htmlentities($styleName, ENT_COMPAT).'</name></style>');
	}
	
	public function deleteAllStylesFromLayer($layerName) {
		return $this->runApi('layers/'.urlencode($layerName).'/styles', 'DELETE');
	}

	public function deleteStyle($styleName) {
		return $this->runApi('styles/'.urlencode($styleName), 'DELETE');
	}
}
