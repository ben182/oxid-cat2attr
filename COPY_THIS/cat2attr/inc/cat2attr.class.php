<?php

/**
 * oxid-cat2attr is a simple PHP class for OXID eSales, that will transfer category assigned articles into an attribute.
 *
 * @author     Benjamin Bortels <benjamin.bortels@kussin.de>
 * @license    The MIT License (MIT)
			   Copyright (c) 2016 Benjamin Bortels
	
	           Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the               Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the               Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
	
	           The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
	
	           THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A               PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION               OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
	
 * @version    0.1.0
 */

class cat2attr {

	private $_aSettings = array(
		'BOOTSTRAP_PATH' => '',
		'SEPERATOR' => ', ',
		'GIVEN_TOKEN' => '',
		'ACCESS_TOKEN' => FALSE
	);

	private $_aResponse = array();

	private $_sFileName = '';

	public function __construct($aData) {

		$this->_aSettings = $this->config($aData, $this->_aSettings);

		$this->_aResponse = array(
			'success' => TRUE,
			'request_tokens' => array(),
			'changed_articles' => array(),
			'validation_errors' => array(),
			'system_errors' => array()
		);

		$this->_sFileName = date('Y-m-d') . '.csv';

		if (file_exists($this->_aSettings['BOOTSTRAP_PATH'])) {

			require($this->_aSettings['BOOTSTRAP_PATH']);
		}else{

			$this->addError('BOOTSTRAP_PATH file does not exist.', 'system_errors');
		}

		if ($this->_aSettings['ACCESS_TOKEN']) {
			if ($this->_aSettings['GIVEN_TOKEN'] !== $this->_aSettings['ACCESS_TOKEN']) {
			
				$this->addError('ACCESS_TOKEN is not valid.', 'validation_errors');
			}
		}

		$this->prepareArticleLog();
		$this->prepareRequestLog();

	}

	private function config($aData, $aOutput) {

		foreach ($aData as $sKey => $sValue) {
			
			if (array_key_exists($sKey, $aOutput)) {
				$aOutput[$sKey] = $sValue;
			}
		}

		return $aOutput;
	}

	private function addError($sError, $sErrorType) {
		$this->_aResponse[$sErrorType][] = $sError;
	}

	private function isValid() {
		return (empty($this->_aResponse['validation_errors']) && empty($this->_aResponse['system_errors'])) ? TRUE : FALSE;
	}

	private function validateInput($sCatId, $sAttrId) {

		if (!empty($sCatId) && !empty($sAttrId)) {

			$sQuery = 'SELECT OXID FROM oxcategories WHERE OXID = "' . $sCatId . '"';
			$oResult = oxDb::getDb(true)->Execute($sQuery);

			if( $oResult != false && $oResult->recordCount() > 0 ) {

				$sQuery = 'SELECT OXID FROM oxattribute WHERE OXID = "' . $sAttrId . '"';
				$oResult = oxDb::getDb(true)->Execute($sQuery);

				if( $oResult != false && $oResult->recordCount() > 0 ) {

					return TRUE;
				}
			}
		}

		return FALSE;
	}

	public function getResponse() {
			
		if (php_sapi_name() != 'cli') { //The script was run from a webserver
		
			if (!empty($this->_aResponse['validation_errors']) || !empty($this->_aResponse['system_errors'])) {
				$this->_aResponse['success'] = FALSE;
			}
			
			return json_encode($this->_aResponse);
		}
		
		return false;
	}

	private function getChildCats($sCatId) {

		if ($this->isValid()) {

			$aChilds = array();
		
			$sQuery = 'SELECT OXID FROM oxcategories WHERE OXPARENTID = "' . $sCatId . '"';
			$oResult = oxDb::getDb(true)->Execute($sQuery);

			if( $oResult != false ) {
				
				while ( !$oResult->EOF ) {
					$aChilds[] = $oResult->fields[0];
					
					$oResult->moveNext();
				}
				
				return $aChilds;
				
			}else{
				$this->addError('getChildCats db error with ID: ' . $sCatId . '.', 'system_errors');
			}
		}
		
		return false;
	}

	private function getAllArticlesByCat($sCatId) {

		if ($this->isValid()) {

			$aArticles = array();
			
			$sQuery = 'SELECT OXOBJECTID FROM oxobject2category WHERE OXCATNID = "' . $sCatId . '"';
			$oResult = oxDb::getDb(true)->Execute($sQuery);
			
			if( $oResult != false ) {
				
				while ( !$oResult->EOF ) {
					$aArticles[] = $oResult->fields[0];
					
					$oResult->moveNext();
				}
				
				return $aArticles;
				
			}else{
				$this->addError('getAllArticlesByCat db error with ID: ' . $sCatId . '.', 'system_errors');
			}

		}
		
		return false;
	}

	private function getArticleDetails($sArticleId) {

		if ($this->isValid()) {

			$sQuery = 'SELECT OXTITLE, OXARTNUM FROM oxarticles WHERE OXID = "' . $sArticleId . '"';
			$oResult = oxDb::getDb(true)->Execute($sQuery);
			
			if( $oResult != false && $oResult->recordCount() > 0 ) {
				
				return array(
					'OXTITLE' => $oResult->fields[0],
					'OXARTNUM' => $oResult->fields[1]
				);
				
			}else{
				$this->addError('getArticleDetails db error with ID: ' . $sArticleId . '.', 'system_errors');
			}

		}

		return false;
	}

	private function getCatDetails($sCatId) {

		if ($this->isValid()) {

			$aSearch = array(
				'OXTITLE',
				'OXACTIVE_1',
				'OXTITLE_1',
				'OXACTIVE_2',
				'OXTITLE_2',
				'OXACTIVE_3',
				'OXTITLE_3'
			);

			$sQuery = 'SELECT ' . implode(', ', $aSearch) . ' FROM oxcategories WHERE OXID = "' . $sCatId . '"';
			$oResult = oxDb::getDb(true)->Execute($sQuery);
			
			if( $oResult != false && $oResult->recordCount() > 0 ) {
				
				$aRowData = array();

				foreach ($aSearch as $iKey=>$sSearch) {
					$aRowData[$sSearch] = $oResult->fields[$iKey];
				}

				if (!$aRowData['OXACTIVE_1']) {
					$aRowData['OXTITLE_1'] = FALSE;
				}

				if (!$aRowData['OXACTIVE_2']) {
					$aRowData['OXTITLE_2'] = FALSE;
				}

				if (!$aRowData['OXACTIVE_3']) {
					$aRowData['OXTITLE_3'] = FALSE;
				}

				unset($aRowData['OXACTIVE_1'], $aRowData['OXACTIVE_2'], $aRowData['OXACTIVE_3']);

				return $aRowData;
				
			}else{
				$this->addError('getCatDetails db error with ID: ' . $sCatId . '.', 'system_errors');
			}

		}

		return false;
	}

	private function isLastCat($sCatId) {

		$aChilds = $this->getChildCats($sCatId);

		if (empty($aChilds)) {

			return TRUE;
		}else{
			
			return $aChilds;
		}

		return FALSE;
	}

	private function getDeepCats($sCatId, $db_structure = NULL) {

		foreach ($this->getChildCats($sCatId) as $sChildCat) {

			if ($this->isLastCat($sChildCat) === TRUE) { //has no childs

				$db_structure[] = $sChildCat;
			}else{ //has childs

				$db_structure = $this->getDeepCats($sChildCat, $db_structure);
			}

		}

		return $db_structure;
	}

	private function getAllCats($sCatId, $db_structure = NULL) {

		foreach ($this->getChildCats($sCatId) as $sChildCat) {
			
			$db_structure[] = $sChildCat;

			$db_structure = $this->getAllCats($sChildCat, $db_structure);

		}

		return $db_structure;
	}

	public function prepareAllArticlesAndCats($sCatId, $iSearchMode = NULL) {

		if ($this->isValid()) {

			switch ($iSearchMode) {
				case 1:
					$aChildCats = $this->getDeepCats($sCatId);
					break;
				case 2:
					$aChildCats = $this->getAllCats($sCatId);
					break;
				default:
					$aChildCats = $this->getChildCats($sCatId);
					break;
			}

			if (!empty($aChildCats)) {

				foreach ($aChildCats as $sChildCat) {
				
					$aArticles = $this->getAllArticlesByCat($sChildCat);

					if (!empty($aArticles)) {

						foreach ($aArticles as $sArticle) {
							$aAllArticles[$sArticle]['categorys'][] = array(
								'OXID' => $sChildCat, 
								'DETAILS' => $this->getCatDetails($sChildCat)
							);
							$aAllArticles[$sArticle]['data'] = $this->getArticleDetails($sArticle);

						}
					}
				}

				return $aAllArticles;

			}

		}

		return false;
	}

	private function prepareQuerys($sCatId, $sAttrId, $iSearchMode) {

		if ($this->isValid()) {

			$aAllArticles = $this->prepareAllArticlesAndCats($sCatId, $iSearchMode);

			$aQuery = array();
			$aRawData = array();

			$sOXPOS = '9999';

			if (!empty($aAllArticles)) {

				foreach ($aAllArticles as $sArticleId => $aArticleData) {

					$aThisCats = array();

					//OXVALUE

					foreach ($aArticleData['categorys'] as $aCategory) {
						
						$aThisCats[0][] = $aCategory['DETAILS']['OXTITLE'];
						$aThisCats[1][] = $aCategory['DETAILS']['OXTITLE_1'];
						$aThisCats[2][] = $aCategory['DETAILS']['OXTITLE_2'];
						$aThisCats[3][] = $aCategory['DETAILS']['OXTITLE_3'];
					}

					$sOXID = md5(microtime() . $sArticleId);
					$sOXTIMESTAMP = date("Y-m-d h:i:s");

					$sOXVALUE = implode($this->_aSettings['SEPERATOR'], $aThisCats[0]);
					$sOXVALUE_1 = implode($this->_aSettings['SEPERATOR'], $aThisCats[1]);
					$sOXVALUE_2 = implode($this->_aSettings['SEPERATOR'], $aThisCats[2]);
					$sOXVALUE_3 = implode($this->_aSettings['SEPERATOR'], $aThisCats[3]);

					$aQuery[] = '("' . $sOXID . '", "' . $sArticleId . '", "' . $sAttrId . '", "' . $sOXVALUE . '", "' . $sOXVALUE_1 . '", "' . $sOXVALUE_2 . '", "' . $sOXVALUE_3 . '", "' . $sOXPOS . '", "' . $sOXTIMESTAMP . '"),';
					$aRawData[] = array($sOXID, $sArticleId, $sAttrId, $sOXVALUE, $sOXVALUE_1, $sOXVALUE_2, $sOXVALUE_3, $sOXPOS, $sOXTIMESTAMP);
					
				}

			}else{
				$this->addError('No articles were found.', 'validation_errors');
				return FALSE;
			}

			return array(
				'query' => $aQuery,
				'raw_data' => $aRawData
			);

		}

		return FALSE;
	}

	private function dumpAttr($sAttrId) {

		if ($this->isValid()) {
			$sQuery = 'DELETE FROM oxobject2attribute WHERE OXATTRID = "' . $sAttrId . '"';
			$oResult = oxDb::getDb(true)->Execute($sQuery);
			
			if( $oResult != false) {
				
				return true;
				
			}else{
				$this->addError('dumpAttr db error with ID: ' . $sAttrId . '.', 'system_errors');
			}

		}
		
		return false;
	}

	private function executeQuery($aQuery) {

		if ($this->isValid()) {

			$sRequestToken = md5(microtime());

			$this->_aResponse['request_tokens'][] = $sRequestToken;
			

			$sQuery = 'INSERT INTO oxobject2attribute (OXID, OXOBJECTID, OXATTRID, OXVALUE, OXVALUE_1, OXVALUE_2, OXVALUE_3, OXPOS, OXTIMESTAMP) VALUES ';
			$sQuery = $sQuery . implode(' ', $aQuery);
			$sQuery = substr($sQuery, 0, -1) . ';'; //delete last comma and add semicolon

			$oResult = oxDb::getDb(true)->Execute($sQuery);
			
			if( $oResult != false) {
				
				$this->_aResponse['changed_articles'][$sRequestToken] = count($aQuery);

				return $sRequestToken;
				
			}else{
				$this->addError('executeQuery db error with query: ' . $sQuery . '.', 'system_errors');
			}

		}
		
		return false;
	}

	public function insert($aInputSettings) {

		if ($this->isValid()) {

			$aSettings = array(
				'CAT_ID' => '',
				'ATTR_ID' => '',
				'LOG' => TRUE,
				'CLEAN_DB' => TRUE,
				'SEARCH_MODE' => 0
			);

			$aSettings = $this->config($aInputSettings, $aSettings);

			if ($this->validateInput($aSettings['CAT_ID'], $aSettings['ATTR_ID'])) {

				$aQuery = $this->prepareQuerys($aSettings['CAT_ID'], $aSettings['ATTR_ID'], $aSettings['SEARCH_MODE']);
				//die(print_r($aQuery));
				
				if ($aSettings['CLEAN_DB']) {

					$this->dumpAttr($aSettings['ATTR_ID']);
				}
				
				$sRequestToken = $this->executeQuery($aQuery['query']);

				if ($aSettings['LOG']) {

					$this->logArticles($aQuery['raw_data'], $sRequestToken);
				}

				return TRUE;

			}else{
				$this->addError('insert input is not valid.', 'validation_errors');
			}

		}

		return FALSE;
	}

	/* LOGGING */

	private function prepareArticleLog() {

		if ($this->isValid()) {

			$sFilePath = 'local/articles/' . $this->_sFileName;
		
			if (!file_exists($sFilePath)) {

				
				$sDirname = dirname($sFilePath);

				if (!is_dir($sDirname)) {
				    mkdir($sDirname, 0755, true);
				}

				$rFile = fopen($sFilePath, 'w');
				if ($rFile) {
					// ADD 1. ROW
					fputs($rFile, implode(',', array('OXID', 'OXOBJECTID', 'OXATTRID', 'OXVALUE', 'OXVALUE_1', 'OXVALUE_2', 'OXVALUE_3', 'OXPOS', 'OXTIMESTAMP', 'REQUEST_TOKEN')) . "\r\n");
					return TRUE;

				}else{
					
					$this->addError('Unable to open article log file.', 'system_errors');
				}
				
			}

		}
		
		return FALSE;
	}
	
	private function prepareRequestLog() {

		if ($this->isValid()) {

			$sFilePath = 'local/requests/' . $this->_sFileName;
		
			if (!file_exists($sFilePath)) {

				$sDirname = dirname($sFilePath);

				if (!is_dir($sDirname)) {
				    mkdir($sDirname, 0755, true);
				}

				$rFile = fopen($sFilePath, 'w');
				if ($rFile) {
					// ADD 1. ROW
					fputs($rFile, implode(',', array('SUCCESS', 'CHANGED_ARTICLES', 'VALIDATION_ERRORS', 'SYSTEM_ERRORS', 'REQUEST_TYPE', 'TIME', 'REQUEST_TOKENs')) . "\r\n");

					return TRUE;

				}else{
					
					$this->addError('Unable to open request log file.', 'system_errors');
				}
				
			}

		}
		
		return FALSE;
	}

	private function logArticles($aFileData, $sRequestToken) {

		if ($this->isValid()) {

			$rFile = fopen('local/articles/' . $this->_sFileName, 'a');

			foreach ($aFileData as $aEachFileData) {

				$aEachFileData[] = $sRequestToken;
				fputcsv($rFile, $aEachFileData);
			}
			
			fclose($rFile);

			return TRUE;

		}

		return FALSE;
	}
	
	private function logRequest($aFileData) {

		if ($this->isValid()) {

			$rFile = fopen('local/requests/' . $this->_sFileName, 'a');

			fputcsv($rFile, $aFileData);
			
			fclose($rFile);

			return TRUE;

		}

		return FALSE;
	}
	
}
