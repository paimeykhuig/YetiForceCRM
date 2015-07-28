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

vimport('modules.Import.export.Export');
vimport('modules.Import.helpers.FormatValue');
vimport('modules.Import.helpers.XmlUtils');

class ExportToXml extends Export {

	protected $attrList = array('crmfield', 'crmfieldtype', 'partvalue', 'constvalue', 'refmoule', 'spec', 'refkeyfld', 'delimiter', 'testcondition');
	protected $product = false;
	protected $moduleName = '';
	protected $tplName = '';
	protected $tmpXmlPath = '';


	public function process(Vtiger_Request $request) {
		$this->moduleName = $request->get('source_module');
		
		if ($request->get('xml_export_type')) {
			$this->tplName = $request->get('xml_export_type');
		} else {
			$this->tplName = $this->moduleName . '_Base.xml';
		}
		
		$fileName = str_replace(' ', '_', decode_html(vtranslate($this->moduleName, $this->moduleName)));

		$entries = $this->getRecords($request);

		if (1 < count($entries) ) {
			
			for ($i = 0; $i < count($entries); $i++) {
				$this->tmpXmlPath = 'cache/import/' . uniqid().'_'.$entries[$i]['label'] . '.xml';
				$this->xmlList[] = $this->tmpXmlPath;

				$this->createXml($entries[$i]);
			}

			$this->outputZipFile($fileName);

		} else {
			$this->tmpXmlPath = 'cache/import/' . uniqid() . '.xml';

			$this->createXml($entries[0]);

			$this->outputFile($fileName);
		}
	}

	public function outputFile($fileName) {
		header("Content-Disposition:attachment;filename=$fileName.xml");
		header("Content-Type:text/csv;charset=UTF-8");
		header("Expires: Mon, 31 Dec 2000 00:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: post-check=0, pre-check=0", false);

		readfile($this->tmpXmlPath);
	}

	protected function outputZipFile($fileName){

		$zipName = 'cache/import/' . uniqid() . '.zip';

		$zip = new ZipArchive();
		$zip->open($zipName, ZipArchive::CREATE);

		for ($i = 0; $i < count($this->xmlList); $i++) { 
			$xmlFile = basename($this->xmlList[$i]);
			$xmlFile = explode('_', $xmlFile);
			array_shift($xmlFile);
			$xmlFile = implode('_', $xmlFile);
			$zip->addFile($this->xmlList[$i], $xmlFile);
		}

		$zip->close();

		header("Content-Disposition:attachment;filename=$fileName.zip");
		header("Content-Type:application/zip");
		header("Expires: Mon, 31 Dec 2000 00:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: post-check=0, pre-check=0", false);

		readfile($zipName);
	}

	/* public function createXml($entries) {var_dump($entries);exit;

		$xml = new XMLWriter();
		$xml->openMemory();
		$xml->setIndent(TRUE);
		$xml->startDocument('1.0', 'UTF-8');
		
		for ($i = 0; $i < count($entries); $i++) {

			$tpl = XmlUtils::readTpl($this->tplName);

			while ($tpl->read()) {
				if (XMLReader::ELEMENT == $tpl->nodeType) {
					if ('true' != $tpl->getAttribute('notrepeat')) {
						$xml->startElement($tpl->name);
					}
					
					if (($tpl->getAttribute('crmfield') || $tpl->getAttribute('constvalue')) && ('product' != $tpl->getAttribute('type') && 'true' != $tpl->getAttribute('notrepeat'))) {
						$xml->text($this->getNodeValue($tpl, $entries[$i]));
					}
					
					if ('product' == $tpl->getAttribute('type')) {
						
						$lineProductTpl = new SimpleXMLElement($tpl->readInnerXml());

						$focus = new $this->moduleName();var_dump($entries[$i]['id']);exit;
						$focus->retrieve_entity_info($entries[$i]['id'], $this->moduleName);
						$focus->id = $entries[$i]['id'];

						$this->product = getAssociatedProducts($this->moduleName, $focus, $entries[$i]['id']);

						$xml = $this->addProduct($xml, $this->product, $lineProductTpl);
						
					}
				} else if (XMLReader::END_ELEMENT == $tpl->nodeType) {
					if ('true' != $tpl->getAttribute('notrepeat')) {
						$xml->endElement();
					}
				}
			}
			
			file_put_contents($this->tmpXmlPath, $xml->flush(true), FILE_APPEND);
		}
		
		file_put_contents($this->tmpXmlPath, $xml->flush(true), FILE_APPEND);
	} */
	
	public function createXml($entries) {

		$xml = new XMLWriter();
		$xml->openMemory();
		$xml->setIndent(TRUE);
		$xml->startDocument('1.0', 'UTF-8');

		$tpl = XmlUtils::readTpl($this->tplName);

		while ($tpl->read()) {

			if (XMLReader::ELEMENT == $tpl->nodeType) {
				if ('true' != $tpl->getAttribute('notrepeat')) {
					$xml->startElement($tpl->name);
				}

				if (($tpl->getAttribute('crmfield') || $tpl->getAttribute('constvalue')) && ('product' != $tpl->getAttribute('type') 
					&& 'true' != $tpl->getAttribute('notrepeat'))) {
					$xml->text($this->getNodeValue($tpl, $entries));
				}

				if ('product' == $tpl->getAttribute('type')) {

					$lineProductTpl = new SimpleXMLElement($tpl->readInnerXml());

					$focus = new $this->moduleName();//var_dump($entries['id'], $this->moduleName);exit;
					$focus->retrieve_entity_info($entries['id'], $this->moduleName);
					$focus->id = $entries['id'];
					//$recordClassName = "{$this->moduleName}_Record_Model";

					//$this->product = $recordClassName::getProducts($entries['id']);
					//var_dump(getAssociatedProducts($this->moduleName, $focus, $entries['id']));exit;
					$this->product = getAssociatedProducts($this->moduleName, $focus, $entries['id']);

					//$xml = $this->addProduct($xml, $this->product, $lineProductTpl);
					$xml = $this->addProduct($xml, $this->product, $lineProductTpl);
				}
			} else if (XMLReader::END_ELEMENT == $tpl->nodeType) {
				if ('true' != $tpl->getAttribute('notrepeat')) {
					$xml->endElement();
				}
			}
		}

		file_put_contents($this->tmpXmlPath, $xml->flush(true), FILE_APPEND);
	}

	protected function addProduct(XMLWriter $xml, $product, SimpleXMLElement $lineProductTpl) {


		for ($i = 1; $i <= count($product); $i++) {

			if (isRecordExists($product[$i]['hdnProductId' . $i])) {
				$prodModel = Vtiger_Record_Model::getInstanceById($product[$i]['hdnProductId' . $i]);

				$xml->startElement('Line-Item');

				foreach ($lineProductTpl as $singele) {
					$nodeName = $singele->getName();
					
					if ('LineNumber' == $nodeName) {
						$xml->writeElement($nodeName, $i);
					} else {
						if ($singele->attributes()->frommodule) {

							$tabField = explode('|', $singele->attributes()->crmfield);
							
							if (1 < count($tabField)) {
								if ('Products' == $product[$i]['entityType' . $i]) {
									$xml->writeElement($nodeName, $prodModel->get($tabField[0]));
								} else {
									$xml->writeElement($nodeName, $prodModel->get($tabField[1]));
								}
							} else {
								$xml->writeElement($nodeName, $prodModel->get($tabField[0]));
							}
						} else {

							if (!$singele->attributes()->fromfinal) {

								$nodeVal = $product[$i][$singele->attributes()->crmfield . $i];

								if ($nodeVal) {
									$xml->writeElement($nodeName, $nodeVal);
								} else {
									$xml->writeElement($nodeName, (string) $singele->attributes()->alt);
								}
							} else {

								$nodeVal = $product[1]['final_details'][(string) $singele->attributes()->crmfield];
								if ($nodeVal) {
									$xml->writeElement($nodeName, $nodeVal);
								} else {
									$xml->writeElement($nodeName, (string) $singele->attributes()->alt);
								}
							}
						}
					}
				}

				$xml->endElement();
			}
		}
		return $xml;
	}

	protected function getNodeValue(XMLReader $tpl, $valTab) {

		$nodeAtribute = $this->getAllAttrbute($tpl);

		if ($nodeAtribute['constvalue']) {
			return $nodeAtribute['constvalue'];
		}

		if ($nodeAtribute['crmfield']) {

			$fieldValue = $valTab[$nodeAtribute['crmfield']];
			
			$format = new FormatValue();

			if (!in_array($nodeAtribute['crmfieldtype'], array('string', 'inventory'))) {
				if (!in_array($nodeAtribute['crmfieldtype'], array('reference', 'ifcondition', 'datediff'))) {
					return $format->formatValueTo($nodeAtribute, $fieldValue);
				} else if (in_array($nodeAtribute['crmfieldtype'], array('datediff', 'ifcondition'))) {
					return $format->formatValueTo($nodeAtribute, $valTab);
				} else {
					if (!in_array($nodeAtribute['refkeyfld'], $valTab)) { // some reference column names are diffrent than field name ex. account_id - accountid
						$refColumn = str_replace('_', '', $nodeAtribute['refkeyfld']);
					} else {
						$refColumn = $nodeAtribute['refkeyfld'];
					}
					return $format->formatValueTo($nodeAtribute, $valTab[$refColumn]);
				}
			} 
			else if ('inventory' == $nodeAtribute['crmfieldtype']) {
				
				if (!$this->product) {
					$focus = new $this->moduleName();
					$focus->retrieve_entity_info($valTab['id'], $this->moduleName);
					$focus->id = $valTab['id'];

					$this->product = getAssociatedProducts($this->moduleName, $focus, $valTab['id']);
				}
				
				switch ($nodeAtribute['crmfield']) {
					case 'line_numbers':
						return count($this->product);
						break;
					case 'quantity_units':
						$qty = 0.00;
						for ($i = 1; $i <= count($this->product); $i++) {
							$qty += $this->product[$i]['qty' . $i];
						}
						return number_format($qty, 3);
						break;
					case 'pre_tax_total':
						return $this->product[1]['final_details']['hdnSubTotal'];
						break;
					default:
						return $fieldValue;
						break;
				}				
			} else {

				$listField = $format->explodeValue($nodeAtribute['crmfield']);

				if (1 < count($listField)) {

					$concatVal = '';

					foreach ($listField as $singe) {
						$concatVal .= $valTab[$singe] . ' ';
					}

					return $concatVal;
				} else {
					return $fieldValue;
				}
			}
		}

		return '';
	}

	protected function getAllAttrbute(XMLReader $tpl) {
		$atrrTab = array();

		if ($tpl->hasAttributes) {
			foreach ($this->attrList as $attr) {
				$atrrTab[$attr] = $tpl->getAttribute($attr);
			}
		}

		return $atrrTab;
	}

}
