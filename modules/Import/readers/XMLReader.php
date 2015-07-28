<?php

/* +***********************************************************************************************************************************
 * The contents of this file are subject to the YetiForce Public License Version 1.1 (the "License"); you may not use this file except
 * in compliance with the License.
 * Software distributed under the License is distributed on an "AS IS" basis, WITHOUT WARRANTY OF ANY KIND, either express or implied.
 * See the License for the specific language governing rights and limitations under the License.
 * The Original Code is YetiForce.
 * The Initial Developer of the Original Code is YetiForce. Portions created by YetiForce are Copyright (C) www.yetiforce.com. 
 * All Rights Reserved.
 * Contributor(s): ______________________________________.
 * *********************************************************************************************************************************** */

vimport('modules.Import.helpers.XmlUtils');
vimport('modules.Import.helpers.CheckRecord');

class Import_XmlReader_Reader extends Import_FileReader_Reader {

	protected $moduleName;
	protected $skipField = array('assigned_user_id', 'productid');
	protected $skipRecord = 0;
	protected $importedRecords = 0;

	public function __construct($request, $user) {

		$this->moduleName = $request->get('module');

		parent::__construct($request, $user);
	}

	protected function getMandatoryField() {

		$moduleMeta = $this->moduleModel->getModuleMeta();
		return $moduleMeta->getMandatoryFields($this->moduleName);
	}

	protected function getValueFromTplByField($fieldName) {

		$tpl = XmlUtils::readTpl($this->request->get('xml_import_tpl'));

		while ($tpl->read()) {

			$crmField = $tpl->getAttribute('crmfield');

			if (in_array($fieldName, explode('|', $crmField))) {
				return true;
			} else {
				$fldType = $tpl->getAttribute('crmfieldtype');

				if ('reference' == $fldType) {
					$refModule = Vtiger_Record_Model::getCleanInstance($tpl->getAttribute('refmoule'));
					if ($refModule->getField($fieldName)) {
						return true;
					}
				}
			}
		}

		return false;
	}

	protected function getFieldInfoByTagName($tagName) {

		$fieldInfo = array();

		$tpl = XmlUtils::readTpl($this->request->get('xml_import_tpl'));

		while ($tpl->read()) {
			if (XMLReader::ELEMENT == $tpl->nodeType) {
				if ($tagName == $tpl->localName) {
					while ($tpl->moveToNextAttribute()) {
						$fieldInfo[$tpl->name] = $tpl->value;
					}
				}
			}
		}

		return $fieldInfo;
	}

	protected function checkMendatoryFieldInTpl() {

		$mendatoryTab = $this->getMandatoryField();
		if (count($mendatoryTab)) {
			foreach ($mendatoryTab as $field => $trLabel) {
				if (!in_array($field, $this->skipField) && !$this->getValueFromTplByField($field)) {
					//throw new Exception(vtranslate('LACK_OF_VALUE_FOR_ALL_MANDATORY_FIELDS_IN_TPL') . ' - ' . $field);
				}
			}
		}

		return true;
	}

	protected function createRecords(array $dataTab) {

		$fieldMap = array();
		$unique = uniqid();

		for ($i = 1; $i <= count($dataTab); $i++) {

			if (count($dataTab[$i])) {

				foreach ($dataTab[$i] as $mod => $fieldTab) {

					if (count($fieldTab)) {

						foreach ($fieldTab as $key => $single) {

							if ($single['crmfield'] && !$fieldMap[$mod][$single['crmfield']]) {

								if ($single['refkeyfld']) {
									$fieldMap[$i][$mod][$unique . '_import_rel_module'] = $single['refkeyfld'];
								}

								$fieldMap[$i][$mod][$single['crmfield']] = $single['val'];
							}

							if ('prod_line' === $key) {
								unset($fieldTab[$key][""]);
								$fieldMap[$i][$unique . '_product_line'] = $fieldTab[$key];
							}
						}
					}
				}
			}
		}

		$recordIdTab = array();

		if (count($fieldMap)) {

			foreach ($fieldMap as $key => $singleMap) {

				$allowToCreateRecord = true;

				foreach ($singleMap as $singleKey => $listFld) {

					if ($singleKey == $unique . '_product_line') {

						if (count($singleMap[$unique . '_product_line'])) {

							foreach ($singleMap[$unique . '_product_line'] as $prodKey => $singleProd) {

								foreach ($singleProd as $singleVal) {
									if ('ean' == $singleVal['crmfield']) {
										$eanResult = CheckRecord::checkExistByEAN($singleVal['val']);
										
										if (!$eanResult) {
											$allowToCreateRecord = false;
											$this->skipRecord++;
										} else {
											$singleMap[$unique . '_product_line'][$prodKey]['rec_id'] = $eanResult;
										}
									}
								}
							}
						} else {
							$allowToCreateRecord = false;
							$this->skipRecord++;
						}
					}
				}
				
				$currCode = $this->findInFieldMap('currency_id', $singleMap);
				$currId = $this->currencyExist($currCode);
						
				if(!$currId){
					throw new Exception(vtranslate('LBL_NO_CURRENCY', 'Import') . ' - ' . $currCode);
				}
				
				$preTaxTotal = 0;

				if ($allowToCreateRecord) {

					if (count($singleMap)) {

						foreach ($singleMap as $singleKey => $listFld) {

							if ($unique . '_product_line' != $singleKey) {
								$recordModel = Vtiger_Record_Model::getCleanInstance($singleKey);

								foreach ($listFld as $listKey => $listVal) {
									$recordModel->set($listKey, $listVal);
									
									if ('pre_tax_total' == $listKey) {
										$recordModel->set('currency_id', $currId);
										$preTaxTotal = $listVal;
									}
								}

								$recordModel->save();

								if ($fieldMap[$key][$singleKey][$unique . '_import_rel_module']) {
									$recordIdTab[$fieldMap[$key][$singleKey][$unique . '_import_rel_module']] = $recordModel->getId();
								} else {
									$recordIdTab['main'] = $recordModel->getId();
								}
							}
						}
					}

					if (count($recordIdTab)) {
						$this->updateRecordRel($recordIdTab, $singleMap[$unique . '_product_line'], $preTaxTotal);
					}
				}
			}
		}
	}
	
	protected function findInFieldMap($val, $array) {
		
		foreach ($array as $key => $singleModule) {
			foreach ($singleModule as $singleKey => $fldValue) {
				
				if ($val == $singleKey) {
					return $fldValue;
				}
			}
		}
	}
	
	protected function currencyExist($code) {
		
		$db = PearDatabase::getInstance();
		
		$sql = "SELECT id FROM vtiger_currency_info WHERE currency_code = ?";
		
		$result = $db->pquery($sql, array($code), TRUE);
		
		return $db->query_result($result, 0, 'id');
	}

	protected function updateRecordRel(array $recordList, $productLine, $total) {

		$recordModel = Vtiger_Record_Model::getInstanceById($recordList['main']);
		$recordModel->set('mode', 'edit');

		foreach ($recordList as $fld => $value) {
			$recordModel->set($fld, $value);
		}

		$recordModel->save();

		if (array_key_exists(1, $productLine)) {
			$total = $this->findValueInProdTab('hdnSubTotal', $productLine[1]);
		}
		if (!empty($productLine)) {
			$this->addProduct($recordList['main'], $productLine, $total);
		}
	}

	protected function addProduct($id, $productList, $total) {

		$type = Vtiger_Functions::getSingleFieldValue('vtiger_crmentity', 'setype', 'crmid', $id);

		if ($type) {
			
			require_once "modules/$type/$type.php";

			$focus = new $type();
			$focus->id = $id;
			$focus->retrieve_entity_info($id, $type);
			$focus->mode = 'edit';
			
			$_REQUEST['totalProductCount'] = 0;
			$_REQUEST['taxtype'] = 'group';

			for ($i = 1; $i <= count($productList); $i++) {
				
				$_REQUEST['hdnProductId' . $i] = $productList[$i]['rec_id'];
				$_REQUEST['qty' . $i] = $this->findValueInProdTab('qty', $productList[$i]);
				$_REQUEST['listPrice' . $i] = $this->findValueInProdTab('listPrice', $productList[$i]);

				$_REQUEST['totalProductCount']++;
			}
			
			$_REQUEST['subtotal'] = $total;
			$_REQUEST['total'] = $total;
			
			saveInventoryProductDetails($focus, $type);
			$this->importedRecords++;
		}
	}
	
	protected function findValueInProdTab($val, $array) {
		
		for ($i = 0; $i < count($array); $i++) {
			if ($val == $array[$i]['crmfield']) {
				return $array[$i]['val'];
			}
		}
	}

	public function startImport() {
		
		vglobal('VTIGER_BULK_SAVE_MODE', false);

		if ($this->checkMendatoryFieldInTpl()) {

			$xmlToImport = new XMLReader();
			$xmlToImport->open($this->getFilePath());

			$recordData = array();
			$recordNum = 0;
			$firstElement = '';
			$lineProd = '';

			while ($xmlToImport->read()) {
				if ($xmlToImport->nodeType == XMLReader::ELEMENT) {
					$info = $this->getFieldInfoByTagName($xmlToImport->localName);

					if (0 == $recordNum) {
						$firstElement = $xmlToImport->localName;
						$recordNum++;
					}

					if ($info && 'product' != $info['type']) {
						if ('reference' == $info['crmfieldtype']) {
							$info['val'] = $xmlToImport->readString();
							$recordData[$recordNum][$info['refmoule']][] = $info;
						} else {
							if ('true' == $info['notrepeat']) {

								if ('LineNumber' == $xmlToImport->localName) {
									$lineProd = $xmlToImport->readString();
								}
								
								$info['val'] = $xmlToImport->readString();
								$recordData[$recordNum][$this->request->get('module')]['prod_line'][$lineProd][] = $info;
							} else {
								$info['val'] = $xmlToImport->readString();
								$recordData[$recordNum][$this->request->get('module')][] = $info;
							}
						}
					}
				}

				if (XMLReader::END_ELEMENT == $xmlToImport->nodeType && $xmlToImport->localName == $firstElement) {
					$recordNum++;
					$lineProd = '';
				}
			}

			$this->createRecords($recordData);
		}
	}
	
	public function showResults() {
		header('Location: index.php?module=' . $this->moduleName . '&view=EdiImportResult&ok_rec=' . $this->importedRecords . '&fail=' . $this->skipRecord );
	}
}
