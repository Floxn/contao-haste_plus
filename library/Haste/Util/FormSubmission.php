<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2016 Heimrich & Hannot GmbH
 *
 * @package haste_plus
 * @author  Rico Kaltofen <r.kaltofen@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */

namespace HeimrichHannot\Haste\Util;


use HeimrichHannot\Haste\DC_Table;

class FormSubmission
{
	public static function prepareData(\Model $objModel, $strTable, array $arrDca = array(), $objDc = null, $arrFields=array(), array $arrSkipFields = array())
	{
		if($objDc === null)
		{
			$objDc = DC_Table::getInstanceFromModel($objModel);
		}

		if(empty($arrDca))
		{
			\Controller::loadDataContainer($objModel->getTable());
			$arrDca = $GLOBALS['TL_DCA'][$objModel->getTable()];
		}

		$arrSubmissionData = array();
		$arrRow = $objModel->row();
		$arrSubmission = array();

		foreach (array_keys($arrRow) as $strName)
		{
			$varValue = $arrRow[$strName];
			if(empty($varValue)) continue;

			$arrData = $arrDca['fields'][$strName];

			$arrFieldData = static::prepareDataField($strName, $varValue, $arrData, $strTable, $objDc);

			$arrSubmissionData[$strName] = $arrFieldData;
			$strSubmission = $arrFieldData['submission'];

			$varValue = deserialize($varValue);

			// multicolumnwizard support
			if ($arrData['inputType'] == 'multiColumnWizard') {
				foreach ($varValue as $arrSet) {
					if (!is_array($arrSet)) {
						continue;
					}

					// new line
					$strSubmission .= "\n";

					foreach ($arrSet as $strSetName => $strSetValue) {
						$arrSetData   = $arrData['eval']['columnFields'][$strSetName];
						$arrFieldData = static::prepareDataField($strSetName, $strSetValue, $arrSetData, $strTable, $objDc);
						// intend new line
						$strSubmission .= "\t" . $arrFieldData['submission'];
					}

					// new line
					$strSubmission .= "\n";
				}
			}

			$arrSubmissionData['submission_all'] .= $strSubmission;

			if(in_array($strName, $arrFields) && !in_array($strName, $arrSkipFields))
			{
				$arrSubmission[$strName] = $strSubmission;
			}
		}

		// order submission by arrFields
		$strSubmissionAll = '';
		foreach ($arrFields as $strName)
		{
			$strSubmissionAll .= $arrSubmission[$strName];
		}

		$arrSubmissionData['submission'] = $strSubmissionAll;

		return $arrSubmissionData;
	}

	public static function prepareDataField($strName, $varValue, $arrData, $strTable, $objDc)
	{
		$strLabel = isset($arrData['label'][0]) ? $arrData['label'][0] : $strName;

		$strOutput = static::prepareSpecialValueForPrint($varValue, $arrData, $strTable ?: 'tl_submission', $objDc);

		$varValue = deserialize($varValue);

		if (is_array($varValue))
		{
			$varValue = Arrays::flattenArray($varValue);

			$varValue = array_filter($varValue); // remove empty elements

			$varValue = implode(', ', $varValue);
		}

		$strSubmission = $strLabel . ": " . $strOutput . "\n";

		return array('value' => $varValue, 'output' => $strOutput, 'submission' => $strSubmission);
	}

	public static function tokenizeData(array $arrSubmissionData = array(), $strPrefix = 'form')
	{
		$arrTokens = array();

		foreach($arrSubmissionData as $strName => $arrData)
		{
			if(!is_array($arrData))
			{
				if ($strName != 'submission' && $strName != 'submission_all' && !is_object($arrData))
				{
					$arrTokens[$strName] = $arrData;
					continue;
				}
				else
				{
					continue;
				}
			}

			foreach($arrData as $strType => $varValue)
			{
				switch($strType)
				{
					case 'output':
						$arrTokens[$strPrefix . '_' . $strName] = $varValue;
						$arrTokens[$strPrefix . '_plain_' . $strName] =
							\HeimrichHannot\Haste\Util\StringUtil::convertToText(\StringUtil::decodeEntities($varValue), true);
						break;
					case 'value':
						// check for values causing notification center's json_encode call to fail (unprintable characters like binary!)
						if (ctype_print($varValue))
						{
							$arrTokens[$strPrefix . '_value_' . $strName] = $varValue;
						}
						break;
					case 'submission':
						$arrTokens[$strPrefix . '_submission_' . $strName] = rtrim($varValue, "\n");
						break;
				}
			}
		}

		// token: ##formsubmission_all##
		if(isset($arrSubmissionData['submission_all']))
		{
			$arrTokens[$strPrefix . 'submission_all'] = $arrSubmissionData['submission_all'];
		}

		// token: ##formsubmission##
		if(isset($arrSubmissionData['submission']))
		{
			$arrTokens[$strPrefix . 'submission'] = $arrSubmissionData['submission'];
		}

		// prepare attachments


		return $arrTokens;
	}

	public static function prepareSpecialValueForPrint($varValue, $arrData, $strTable, $objDc, $objItem = null)
	{
		$varValue = deserialize($varValue);
		$arrOpts  = $arrData['options'];
		$arrReference  = $arrData['reference'];
		$strRegExp = $arrData['eval']['rgxp'];

		// get options
		if ((is_array($arrData['options_callback']) || is_callable($arrData['options_callback'])) &&
			!$arrData['reference'])
		{
			if (is_array($arrData['options_callback']))
			{
				$strClass  = $arrData['options_callback'][0];
				$strMethod = $arrData['options_callback'][1];

				$objInstance = \Controller::importStatic($strClass);

				$arrOptionsCallback = @$objInstance->$strMethod($objDc);
			}
			elseif (is_callable($arrData['options_callback']))
			{
				$arrOptionsCallback = @$arrData['options_callback']($objDc);
			}

			$arrOptions = !is_array($varValue) ? array($varValue) : $varValue;

			if ($varValue !== null && is_array($arrOptionsCallback))
			{
				$varValue = array_intersect_key($arrOptionsCallback, array_flip($arrOptions));
			}
		}

		if ($arrData['inputType'] == 'explanation')
        {
            $varValue = $arrData['eval']['text'];
        }
		elseif ($strRegExp == 'date')
		{
			$varValue = \Date::parse(\Config::get('dateFormat'), $varValue);
		}
		elseif ($strRegExp == 'time')
		{
			$varValue = \Date::parse(\Config::get('timeFormat'), $varValue);
		}
		elseif ($strRegExp == 'datim')
		{
			$varValue = \Date::parse(\Config::get('datimFormat'), $varValue);
		}
		elseif ($arrData['inputType'] == 'tag' && in_array('tags_plus', \ModuleLoader::getActive()))
		{
			if (($arrTags = \HeimrichHannot\TagsPlus\TagsPlus::loadTags($strTable, $objItem->id)) !== null)
			{
				$varValue = $arrTags;
			}
		}
		elseif(!is_array($varValue) && \Validator::isBinaryUuid($varValue))
		{
			$strPath = Files::getPathFromUuid($varValue);
			$varValue = $strPath ? (\Environment::get('url') . '/' . $strPath) : \StringUtil::binToUuid($varValue);
		}
		elseif (is_array($varValue))
		{
			$varValue = Arrays::flattenArray($varValue);
			$varValue = array_filter($varValue); // remove empty elements

			// transform binary uuids to paths
			$varValue = array_map(function($varValue) {
				if(\Validator::isBinaryUuid($varValue))
				{
					$strPath = Files::getPathFromUuid($varValue);

					if($strPath) {
						return \Environment::get('url') . '/' . $strPath;
					}

					return \StringUtil::binToUuid($varValue);
				}

				return $varValue;
			}, $varValue);

			if (!$arrReference)
			{
				$varValue = array_map(function($varValue) use ($arrOpts) {
					return isset($arrOpts[$varValue]) ? $arrOpts[$varValue] : $varValue;
				}, $varValue);
			}

			$varValue = array_map(
				function ($varValue) use ($arrReference) {
					if (is_array($arrReference)) {
						return isset($arrReference[$varValue]) ?
							((is_array($arrReference[$varValue])) ? $arrReference[$varValue][0] : $arrReference[$varValue])
							: $varValue;
					} else {
						return $varValue;
					}
				},
				$varValue
			);
		}
		// Replace boolean checkbox value with "yes" and "no"
		else if($arrData['eval']['isBoolean'] || ($arrData['inputType'] == 'checkbox' && !$arrData['eval']['multiple']))
		{
			$varValue = ($varValue != '') ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
		}
		elseif (is_array($arrOpts) && array_is_assoc($arrOpts))
		{
			$varValue = isset($arrOpts[$varValue]) ? $arrOpts[$varValue] : $varValue;
		}
		elseif (is_array($arrReference))
		{
			$varValue = isset($arrReference[$varValue]) ?
				((is_array($arrReference[$varValue])) ? $arrReference[$varValue][0] : $arrReference[$varValue])
				: $varValue;
		}

		if(is_array($varValue))
		{
			$varValue = implode(', ', $varValue);
		}
		
		// Convert special characters (see #1890)
		return specialchars($varValue);
	}

	public static function prepareSpecialValueForSave($varValue, $arrData, $strTable = null, $intId = 0,
		$varDefault = null, &$arrWidgetErrors = array())
	{
		// Convert date formats into timestamps
		if ($varValue != '' && in_array($arrData['eval']['rgxp'], array('date', 'time', 'datim')))
		{
			try
			{
				$objDate  = new \Date($varValue, \Config::get($arrData['eval']['rgxp'] . 'Format'));
				$varValue = $objDate->tstamp;
			}
			catch (\OutOfBoundsException $e)
			{
				$arrWidgetErrors[] = sprintf($GLOBALS['TL_LANG']['ERR']['invalidDate'], $varValue);
				return $varDefault;
			}
		}

		if ($arrData['eval']['multiple'] && isset($arrData['eval']['csv']))
		{
			$varValue = implode($arrData['eval']['csv'], deserialize($varValue, true));
		}

		if ($arrData['inputType'] == 'tag' && in_array('tags_plus', \ModuleLoader::getActive()))
		{
			$varValue = \HeimrichHannot\TagsPlus\TagsPlus::loadTags($strTable, $intId);
		}

		if ($arrData['eval']['encrypt'])
		{
			$varValue = \Encryption::encrypt($varValue);
		}

		return $varValue;
	}
}
