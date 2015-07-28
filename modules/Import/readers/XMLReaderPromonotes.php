<?php

vimport('modules.Import.readers.XMLReader');
vimport('modules.Import.helpers.PrepareDataCosts');

class Import_XmlReaderPromonotes_Reader extends Import_XmlReader_Reader {

	protected function createRecords(array $dataTab) {

		$fieldMap = array();
		$unique = uniqid();

		for ($i = 1; $i <= count($dataTab); $i++) {

			if (count($dataTab[$i])) {

				foreach ($dataTab[$i] as $mod => $fieldTab) {

					if (count($fieldTab)) {

						foreach ($fieldTab as $key => $single) {

							if ($single['crmfield'] && $single['dontcreate'] == false && !$fieldMap[$mod][$single['crmfield']]) {

								if ($single['refkeyfld']) {
									$fieldMap[$i][$mod][$unique . '_import_rel_module'] = $single['refkeyfld'];
								}
								
								if (false !== strpos($single['crmfield'], '|')) {
									list($explodeField,) = explode('|', $single['crmfield']);
									$fieldMap[$i][$mod][$explodeField] = $single['val'];
								} else {
									$fieldMap[$i][$mod][$single['crmfield']] = $single['val'];
								}
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
									if ('cf_868' == $singleVal['crmfield']) {
										$eanResult = CheckRecord::checkProductExistByInternalNo($singleVal['val']);

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

				$currCode = $this->findInFieldMap('currency', $singleMap);
				$currId = $this->currencyExist($currCode);

				if (!$currId) {
					throw new Exception(vtranslate('LBL_NO_CURRENCY', 'Import') . ' - ' . $currCode);
				}

				$preTaxTotal = 0;

				if ($allowToCreateRecord) {

					if (count($singleMap)) {

						foreach ($singleMap as $singleKey => $listFld) {

							if ($unique . '_product_line' != $singleKey) {
								$recordId = '';
								if ('Accounts' === $singleKey) {

									$recordId = $this->findeAccount($singleMap['Accounts']['cf_822']);

									if (!$recordId) {
										$recordModel = Vtiger_Record_Model::getCleanInstance($singleKey);

										foreach ($listFld as $listKey => $listVal) {
											$recordModel->set($listKey, $listVal);
										}

										$recordModel->save();
										$recordId = $recordModel->getId();
									}
								} else if ($singleKey == $this->moduleName) {
									$recordModel = Vtiger_Record_Model::getCleanInstance($singleKey);

									foreach ($listFld as $listKey => $listVal) {
										$recordModel->set($listKey, $listVal);

										if ('pre_tax_total' == $listKey) {
											$recordModel->set('currency_id', $currId);
											$preTaxTotal = $listVal;
										}
									}

									$recordModel->save();
									$recordId = $recordModel->getId();
								}
								
								if ($fieldMap[$key][$singleKey][$unique . '_import_rel_module']) {
									$recordIdTab[$fieldMap[$key][$singleKey][$unique . '_import_rel_module']] = $recordId;
								} 
								
								if ($this->moduleName == $singleKey){
									$recordIdTab['main'] = $recordId;
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

	protected function findeAccount($number) {
		$db = PearDatabase::getInstance();

		$sql = "SELECT accountid FROM vtiger_accountscf "
				. "INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_accountscf.accountid "
				. "WHERE cf_822 = ? AND deleted = 0";

		$result = $db->pquery($sql, array($number), TRUE);

		if ($db->num_rows($result)) {
			return $db->query_result($result, 0, 'accountid');
		} else {
			return false;
		}
	}

	protected function addProduct($id, $productList, $total) {

		$type = Vtiger_Functions::getSingleFieldValue('vtiger_crmentity', 'setype', 'crmid', $id);

		if ($type) {
			$db = PearDatabase::getInstance();

			$tabNamePrefix = 'vtiger_' . strtolower($type);

			for ($i = 1; $i <= count($productList); $i++) {

				$prodInfo = $this->prepareDataProduct($productList[$i]);

				$productInsertSql = "INSERT INTO " . $tabNamePrefix . "_products (`id`, `index`, `" . strtolower($type) . "id`, `product_id`, `variant_no`, `type`) VALUES
				(?, ?, ?, ?, ?, ?)";

				$db->pquery($productInsertSql, array(NULL, $i, $id, $prodInfo['rec_id'], $prodInfo['variant_no'], 'product'), TRUE);

				$inserPrdId = $db->getLastInsertID();

				$calculationInfo = $this->prepareDataCalculations($productList[$i]);
				
				for ($j = 0; $j < count($calculationInfo); $j++) {
					$calculationInsertSql = "INSERT INTO " . $tabNamePrefix . "_calculations (`id`, `index`, `number`, `description`, `product_id`, `name_work`, `po_no`) VALUES
						(?, ?, ?, ?, ?, ?, ?)";

					$db->pquery($calculationInsertSql, array(NULL, $j, $calculationInfo[$j]['number'], $calculationInfo[$j]['description'], $inserPrdId,
						$calculationInfo[$j]['name_work'], $calculationInfo[$j]['po_no']), TRUE);

					$insertCalcId = $db->getLastInsertID();

					$costInfo = PrepareDataCosts::prepareData($productList[$i], $calculationInfo[$j]['number'], $type);

					$costIndex = 1;

					if ( $type == 'OSSInvoiceWDT' ) {
						for ($k = 0; $k < count($costInfo); $k++) {
							$costInsertSql = "INSERT INTO " . $tabNamePrefix . "_costs (`id`, `lp`, `quantity`, `price`, `netto`, `postal`, `days`, `cartons`, `netunit`, `netproduct`) VALUES
							(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

							$db->pquery($costInsertSql, array($insertCalcId, $costIndex, $costInfo[$k]['quantity'], $costInfo[$k]['price'], $costInfo[$k]['netto'], $costInfo[$k]['postal'], $costInfo[$k]['days'], $costInfo[$k]['cartons'], $costInfo[$k]['netunit'], $costInfo[$k]['netproduct']), TRUE);
							$costIndex++;
						}
					}
					else {
						for ($k = 0; $k < count($costInfo); $k++) {
							$costInsertSql = "INSERT INTO " . $tabNamePrefix . "_costs (`id`, `lp`, `quantity`, `price`, `netto`, `postal`, `days`) VALUES
							(?, ?, ?, ?, ?, ?, ?)";

							$db->pquery($costInsertSql, array($insertCalcId, $costIndex, $costInfo[$k]['quantity'], $costInfo[$k]['price'], $costInfo[$k]['netto'], $costInfo[$k]['postal'], $costInfo[$k]['days']), TRUE);
							$costIndex++;
						}
					}
				}
			}
			$this->importedRecords++;
		}
		
		if ( $type == 'OSSInvoiceWDT' || $type == 'OSSInvoiceEXP' || $type == 'OSSInvoicePL' || $type == 'OSSInvoiceFW' ) {
			$recordModel = Vtiger_Record_Model::getInstanceById( $id, $type );
			$recordModel->updateValue();
		}
	}

	private function prepareDataCalculations($prodLine) {
		
		$single = array();
		$all = array();
		
		
		foreach ($prodLine as $value) {

			switch ($value['crmfield']) {
				case 'name_work':
					$single['name_work'] = $value['val'];
					break;
				case 'number':
					$single['number'] = $value['val'];
					break;
				case 'po_no':
					$single['po_no'] = $value['val'];
					break;
				case 'description':
					$single['description'] = $value['val'];
					break;
				default:
					break;
			}

			if (array_key_exists('name_work', $single) && array_key_exists('number', $single) && array_key_exists('description', $single) && array_key_exists('po_no', $single)) {
				$all[] = $single;
				$single = array();
			}
		}

		return $all;
	}

	private function prepareDataProduct($prodLine) {
		$element = array();

		for ($i = 0; $i < count($prodLine); $i++) {

			if ('variant_no' === $prodLine[$i]['crmfield']) {
				$element['variant_no'] = $prodLine[$i]['val'];
			}
		}

		$element['rec_id'] = $prodLine['rec_id'];

		return $element;
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

								if ($xmlToImport->getAttribute('calc')) {
									$info['calc'] = $xmlToImport->getAttribute('calc');
								}


								if ('VariantNo' == $xmlToImport->localName) {
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

}
