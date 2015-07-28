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

class PrepareDataCosts {
	
	public static function prepareData($prodLine, $calcNum, $moduleName) {
		
		$methodName = 'prepareDataCostsFor' . ucfirst($moduleName);
		
		$instance = new self ();
				
		if(method_exists($instance, $methodName)){
			return $instance->$methodName($prodLine, $calcNum);
		} else {
			return $instance->prepareDataCostsForDefault($prodLine, $calcNum);
		}
		
		return array();
	}
	
	protected function prepareDataCostsForOSSInvoiceWDT($prodLine, $calcNum) {
		$single = array();
		$all = array();

		foreach ($prodLine as $value) {
			if ($value['calc'] == $calcNum) {
				switch ($value['crmfield']) {
					case 'quantity':
						$single['quantity'] = $value['val'];
						break;
					case 'price':
						$single['price'] = $value['val'];
						break;
					case 'netto':
						$single['netto'] = $value['val'];
						break;
					case 'postal':
						$single['postal'] = $value['val'];
						break;
					case 'days':
						$single['days'] = $value['val'];
						break;
					case 'cartons':
						$single['cartons'] = $value['val'];
						break;
					case 'netunit':
						$single['netunit'] = $value['val'];
						break;
					case 'netproduct':
						$single['netproduct'] = $value['val'];
						break;
					default:
						break;
				}
			}

			if (array_key_exists('quantity', $single) && array_key_exists('price', $single) && array_key_exists('netto', $single) && array_key_exists('postal',$single) && array_key_exists('days', $single)
				&& array_key_exists('cartons', $single) && array_key_exists('netunit', $single) && array_key_exists('netproduct', $single)) {


				$all[] = $single;
				$single = array();
			}
		}

		return $all;
	}
	
	protected function prepareDataCostsForDefault($prodLine, $calcNum) {
		$single = array();
		$all = array();

		foreach ($prodLine as $value) {
			if ($value['calc'] == $calcNum) {
				switch ($value['crmfield']) {
					case 'quantity':
						$single['quantity'] = $value['val'];
						break;
					case 'price':
						$single['price'] = $value['val'];
						break;
					case 'netto':
						$single['netto'] = $value['val'];
						break;
					case 'postal':
						$single['postal'] = $value['val'];
						break;
					case 'days':
						$single['days'] = $value['val'];
						break;
					default:
						break;
				}
			}

			if (array_key_exists('quantity', $single) && array_key_exists('price', $single) && array_key_exists('netto', $single) && array_key_exists('postal',$single) && array_key_exists('days', $single)) {


				$all[] = $single;
				$single = array();
			}
		}

		return $all;
	}
}
