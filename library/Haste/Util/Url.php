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

class Url extends \Haste\Util\Url
{
	/**
	 * Check an url for existing scheme and add if it is missing
	 * @param        $strUrl The url
	 * @param string $strScheme Name of the scheme
	 *
	 * @return string The url with scheme
	 */
	public static function addScheme($strUrl, $strScheme = 'http://')
	{
		return parse_url($strUrl, PHP_URL_SCHEME) === null ? $strScheme . $strUrl : $strUrl;
	}

	public static function getUrl($includeRequestUri = true, $includeFragments = true, $includeParameters = true)
	{
		$strUrl = \Environment::get('url') . ($includeRequestUri ? \Environment::get('requestUri') : '') . ($includeFragments ? static::getUriFragments(\Environment::get('url')) : '');

		if (!$includeParameters)
			$strUrl = static::removeAllParametersFromUri($strUrl);

		return $strUrl;
	}

	public static function getUrlBasename($includeExtension = false, $includeParameters = false) {
		$arrPathInfo = pathinfo($includeParameters ? \Environment::get('requestUri') : static::removeAllParametersFromUri(\Environment::get('requestUri')));
		return $includeExtension ? $arrPathInfo['basename'] : $arrPathInfo['filename'];
	}

	public static function getUriParameters($uri)
	{
		$arrParsed = parse_url($uri);
		$arrParsedExploded = array();
		if (isset($arrParsed['query']))
		{
			foreach (explode('&', $arrParsed['query']) as $currentParameter)
			{
				$arrCurrentParameterExploded = explode('=', $currentParameter);
				$arrParsedExploded[$arrCurrentParameterExploded[0]] = $arrCurrentParameterExploded[1];
			}
		}

		return $arrParsedExploded;
	}

	public static function getUriWithoutParameters($uri)
	{
		$arrParsed = parse_url($uri);
		return rtrim(\Environment::get('url'), '/') . '/' . ltrim($arrParsed['path'], '/');
	}

	public static function getUriFragments($uri)
	{
		$arrParsed = parse_url($uri);
		return $arrParsed['fragment'];
	}

	public static function removeParameterFromUri($uri, $key)
	{
		$arrParameters = static::getUriParameters($uri);
		unset($arrParameters[$key]);
		return static::getUriWithoutParameters($uri) . (!empty($arrParameters) ? '?' . http_build_query($arrParameters) : '') . (static::getUriFragments($uri) ? '#' . static::getUriFragments($uri) : '');
	}

	public static function removeParametersFromUri($uri, $arrKeys)
	{
		$arrParameters = static::getUriParameters($uri);
		foreach ($arrKeys as $strKey)
		{
			unset($arrParameters[$strKey]);
		}
		return static::getUriWithoutParameters($uri) . (!empty($arrParameters) ? '?' . http_build_query($arrParameters) : '') . (static::getUriFragments($uri) ? '#' . static::getUriFragments($uri) : '');
	}

	public static function removeAllParametersFromUri($uri)
	{
		$arrParameters = static::getUriParameters($uri);
		foreach ($arrParameters as $strKey => $strValue)
		{
			unset($arrParameters[$strKey]);
		}
		return static::getUriWithoutParameters($uri) . (!empty($arrParameters) ? '?' . http_build_query($arrParameters) : '') . (static::getUriFragments($uri) ? '#' . static::getUriFragments($uri) : '');
	}

	public static function removeAllParametersFromUriBut($uri, $arrKeys)
	{
		$arrParameters = static::getUriParameters($uri);
		foreach ($arrParameters as $strKey => $strValue)
		{
			if (!in_array($strKey, $arrKeys))
				unset($arrParameters[$strKey]);
		}
		return static::getUriWithoutParameters($uri) . (!empty($arrParameters) ? '?' . http_build_query($arrParameters) : '') . (static::getUriFragments($uri) ? '#' . static::getUriFragments($uri) : '');
	}

	public static function addParameterToUri($uri, $key, $value)
	{
		$arrParameters = static::getUriParameters($uri);
		$arrParameters[$key] = $value;
		return static::getUriWithoutParameters($uri) . (!empty($arrParameters) ? '?' . http_build_query($arrParameters) : '') . (static::getUriFragments($uri) ? '#' . static::getUriFragments($uri) : '');
	}

	public static function addParametersToUri($uri, array $arrParameters)
	{
		$strResult = $uri;
		foreach ($arrParameters as $strKey => $strValue)
		{
			$strResult = static::addParameterToUri($strResult, $strKey, $strValue);
		}
		return $strResult;
	}

	public static function replaceParameterInUri($uri, $key, $value)
	{
		return static::addParameterToUri(static::removeParameterFromUri($uri, $key), $key, $value);
	}

	public static function getTld()
	{
		$arrParsed = parse_url(\Environment::get('base'));
		$arrHostExploded = explode('.', $arrParsed['host']);
		return $arrHostExploded[count($arrHostExploded) - 1];
	}

	/**
	 * Helper function for creating a &/=-concatenated string out of the $_GET superglobal.
	 * ATTENTION: doesn't contain the leading "?".
	 * @param mixed - remove one or more certain parameters
	 */
	public static function getConcatenatedGetString($remove) {
		$result = array();
		foreach ($_GET as $k => $v) {
			if ((is_array($remove) && !in_array($k, $remove)) || (!is_array($remove) && $k != $remove))
				$result[] = $k . '=' . $v;
		}
		return implode('&', $result);
	}

	public static function getParametersFromUri($strUri)
	{
		$arrResult = array();
		parse_str(parse_url($strUri, PHP_URL_QUERY), $arrResult);

		return $arrResult;
	}
}