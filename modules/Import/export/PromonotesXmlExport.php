<?php

vimport('modules.Import.export.ExportToXml');

class PromonotesXmlExport extends ExportToXml {

	protected $xmlList = array();

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

					$recordClassName = "{$this->moduleName}_Record_Model";

					$this->product = $recordClassName::getProducts($entries['id']);

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

		$variantNum = count($product) - 3;

		for ($i = 1; $i <= $variantNum; $i++) {

			$xml->startElement('Variant');

			if (count($product[$i])) {

				foreach ($product[$i] as $calculateKey => $calculateVal) {

					if (isRecordExists($product[$i][$calculateKey]['product_id'])) {

						$prodModel = Vtiger_Record_Model::getInstanceById($product[$i][$calculateKey]['product_id']);

						foreach ($lineProductTpl as $innerVariant) {

							$nodeName = $innerVariant->getName();

							if ('VariantNo' === $nodeName) {
								$xml->writeElement('VariantNo', $i);
							} else {
								$xml->startElement($nodeName);
							}

							foreach ($innerVariant as $innerVariantElementKey => $innerVariantElementVal) {
								
								$nodeVariantElement = $innerVariantElementVal->getName();
							
								if ('LineNumber' === $nodeVariantElement) {
									$xml->writeElement($nodeVariantElement, $i);
								} else {
									if ('Calculations' !== $nodeVariantElement) {
										if ($innerVariantElementVal->attributes()->frommodule) {
											$tabField = explode('|', $innerVariantElementVal->attributes()->crmfield);

											if (1 < count($tabField)) {
												if ('Products' == $product[$i]['entityType' . $i]) {
													$xml->writeElement($nodeVariantElement, $prodModel->get($tabField[0]));
												} else {
													$xml->writeElement($nodeVariantElement, $prodModel->get($tabField[1]));
												}
											} else {
												$xml->writeElement($nodeVariantElement, $prodModel->get($tabField[0]));
											}
										} else {
											$xml->writeElement($nodeVariantElement, $calculateVal[(string)$innerVariantElementVal->attributes()->crmfield]);
										}
									} else {
										$xml->startElement('Calculations');
										$xml = $this->addCalculations($xml, $product[$i][$calculateKey]['calculations'], new SimpleXMLElement($innerVariantElementVal->asXML()));
										$xml->endElement();
									}
								}
							}
						}
						$xml->endElement();
					}
				}
			}
			$xml->endElement();
		}

		return $xml;
	}

	protected function addCalculations(XMLWriter $xml, $calculatnion, SimpleXMLElement $calculatnionLine) {

		for ($i = 0; $i < count($calculatnion); $i++) {

			$xml->startElement('LineCalculations');

			foreach ($calculatnionLine as $singeleLine) {

				foreach ($calculatnion[$i] as $key => $value) {

					$calculatnionField = new SimpleXMLElement($singeleLine->asXML());

					foreach ($calculatnionField as $calculatnionFieldKey => $calculatnionFieldVal) {

						if ((string) $calculatnionFieldVal->attributes()->crmfield === $key) {
							if ( $key == 'description' )
								$value = strip_tags( str_replace( array('&amp;','&nbsp;'), '', $value ) );
							
							$xml->writeElement($calculatnionFieldVal->getName(), $value);
						}
					}
				}

				foreach ($singeleLine as $calculatnionFieldKey => $calculatnionFieldVal) {

					if ('Costs' === $calculatnionFieldVal->getName()) {
						$xml->startElement('Costs');
						
						$xml = $this->addCosts($xml, $calculatnion[$i]['costs'], new SimpleXMLElement($calculatnionFieldVal->asXML()), $calculatnion[$i]['number']);
						$xml->endElement();
					}
				}
			}

			$xml->endElement();
		}

		return $xml;
	}

	protected function addCosts(XMLWriter $xml, $cost, SimpleXMLElement $costTplLine, $calcNumber) {

		for ($i = 1; $i <= count($cost); $i++) {
			$xml->startElement('CostsLine');

			foreach ($cost[$i] as $costKey => $costValue) {

				foreach ($costTplLine as $costTplKey => $costTplLineNode) {

					foreach ($costTplLineNode as $costTplNodeKey => $costTplNodeVal) {
						
						if ((string) $costTplNodeVal->attributes()->crmfield === $costKey) {
							$xml->startElement($costTplNodeVal->getName());
							$xml->writeAttribute('calc', $calcNumber);
							$xml->text($cost[$i][$costKey]);
							$xml->endElement();
						}
						
					}
				}
			}
			$xml->endElement();
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
					return $format->formatValueTo($nodeAtribute, $valTab[$nodeAtribute['refkeyfld']]);
				}
				
			} 
			else if ('inventory' == $nodeAtribute['crmfieldtype']) {
				
				switch ($nodeAtribute['crmfield']) {
					case 'line_numbers':
						return count($this->product['prods']);
						break;
					case 'net_value_incl_freight':
						$model = "{$this->moduleName}_Module_Model";
						return $model::getItemsPriceWithPostal($this->product);
						break;
					case 'net_valuet':
						$model = "{$this->moduleName}_Module_Model";
						return $model::getItemsPriceWithPostal($this->product, FALSE);
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

}