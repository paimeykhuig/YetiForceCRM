<?php

/**
 * Supplies Discount Field Class
 * @package YetiForce.Fields
 * @license licenses/License.html
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class Supplies_Discount_Field extends Supplies_Basic_Field
{

	protected $name = 'Discount';
	protected $defaultLabel = 'LBL_DISCOUNT';
	protected $columnname = 'discount';
	protected $dbType = 'decimal(27,8) DEFAULT \'0\'';
	protected $customColumn = [
		'discountparam' => 'varchar(255) NOT NULL'
	];

	/**
	 * Geting value to display
	 * @param type $value
	 * @return type
	 */
	public function getDisplayValue($value)
	{
		return CurrencyField::convertToUserFormat($value, null, true);
	}
}