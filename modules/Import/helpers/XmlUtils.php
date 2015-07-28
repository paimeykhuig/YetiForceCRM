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

class XmlUtils {

	public static function getListTplForXmlType($moduleName) {
		$output = array();

		$path = 'modules/Import/tpl/';
		$list = new DirectoryIterator($path);

		foreach ($list as $singleFile) {
			if (!$singleFile->isDot()) {
				$fileName = $singleFile->getFilename();

				if (0 === strpos($fileName, $moduleName)) {
					$output[] = $fileName;
				}
			}
		}

		return $output;
	}
	
	public static function checkTplExist($moduleName) {
		if (!empty($moduleName)) {
			$path = 'modules/Import/tpl/';
			$list = new DirectoryIterator($path);
			
			foreach ($list as $single) {
				if (!$single->isDot() && false !== strpos($single->getFilename(), $moduleName) && is_readable($single->getPathname())) {
					return true;
				}
			}
			
			return false;
		}
		
		return false;
	}
	
	public static function readTpl($tplName) {
		$xmlTpl = new XMLReader();
		$xmlTpl->open('modules/Import/tpl/' . $tplName);

		return $xmlTpl;
	}

}
