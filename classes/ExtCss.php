<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (C) 2005-2013 Leo Feyer
 *
 * @package   Extassets
 * @author    r.kaltofen@heimrich-hannot.de
 * @license   GNU/LGPL
 * @copyright Heimrich & Hannot GmbH
 */


/**
 * Namespace
 */
namespace ExtAssets;

use Contao\File;

use Template;
use FrontendTemplate;
use ExtCssModel;
use ExtCssFileModel;

//require TL_ROOT . "/system/modules/extassets/classes/vendor/lessphp/lessc.inc.php";

/**
 * Class ExtCss
 *
 * @copyright  Heimrich & Hannot GmbH
 * @author     r.kaltofen@heimrich-hannot.de
 * @package    Devtools
 */
class ExtCss extends \Frontend
{

	/**
	 * Singleton
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return \ExtCSS\ExtCSS
	 */
	public static function getInstance()
	{
		if (self::$instance == null)
		{
			self::$instance = new ExtCss();

			// remember cookie FE_PREVIEW state
			$fePreview = \Input::cookie('FE_PREVIEW');

			// set into preview mode
			\Input::setCookie('FE_PREVIEW', true);

			// request the BE_USER_AUTH login status
			static::setDesignerMode(self::$instance->getLoginStatus('BE_USER_AUTH'));

			// restore previous FE_PREVIEW state
			\Input::setCookie('FE_PREVIEW', $fePreview);
		}
		return self::$instance;
	}

	/**
	 * If is in live mode.
	 */
	protected $blnLiveMode = false;


	/**
	 * Cached be login status.
	 */
	protected $blnBeLoginStatus = null;


	/**
	 * The variables cache.
	 */
	protected $arrVariables = null;

	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 * Get productive mode status.
	 */
	public static function isLiveMode()
	{
		return static::getInstance()->blnLiveMode
		? true
		: false;
	}


	/**
	 * Set productive mode.
	 */
	public static function setLiveMode($liveMode = true)
	{
		static::getInstance()->blnLiveMode = $liveMode;
	}


	/**
	 * Get productive mode status.
	 */
	public static function isDesignerMode()
	{
		return static::getInstance()->blnLiveMode
		? false
		: true;
	}


	/**
	 * Set designer mode.
	 */
	public static function setDesignerMode($designerMode = true)
	{
		static::getInstance()->blnLiveMode = !$designerMode;
	}

	public function hookReplaceDynamicScriptTags($strBuffer)
	{
		global $objPage;

		if(!$objPage) return $strBuffer;

		$objLayout = \LayoutModel::findByPk($objPage->layout);

		if(!$objLayout) return $strBuffer;

		// the dynamic script replacement array
		$arrReplace = array();

		$this->parseExtCss($objLayout, $arrReplace);

		return $strBuffer;
	}

	protected function parseExtCss($objLayout, &$arrReplace)
	{
		$arrCss = array();

		$objCss = ExtCssModel::findMultipleByIds(deserialize($objLayout->extcss));

		if($objCss === null) return false;

		$arrReturn = array();

		while($objCss->next())
		{
			$start = time();

			$combiner = new ExtCssCombiner($objCss->current(), $arrReturn);

			$arrReturn = $combiner->getUserCss();

			// HOOK: add custom css
			if (isset($GLOBALS['TL_HOOKS']['parseExtCss']) && is_array($GLOBALS['TL_HOOKS']['parseExtCss']))
			{
				foreach ($GLOBALS['TL_HOOKS']['parseExtCss'] as $callback)
				{
					$arrCss = static::importStatic($callback[0])->$callback[1]($arrCss);
				}
			}
		}

		$arrUserCss = array();


		// TODO: Refactor equal logic…
		// at first collect bootstrap to prevent overwrite of usercss
		if(isset($arrReturn[ExtCssCombiner::$bootstrapCssKey]) && is_array($arrReturn[ExtCssCombiner::$bootstrapCssKey]))
		{
			$arrHashs = array();

			foreach($arrReturn[ExtCssCombiner::$bootstrapCssKey] as $arrCss)
			{
				if(in_array($arrCss['hash'], $arrHashs)) continue;
				$arrUserCss[] = sprintf('%s|%s|%s|%s', $arrCss['src'], $arrCss['type'], $arrCss['mode'], $arrCss['hash']);
				$arrHashs[] = $arrCss['hash'];
			}
		}

		// collect bootstrap responsive css
		if(isset($arrReturn[ExtCssCombiner::$bootstrapResponsiveCssKey]) && is_array($arrReturn[ExtCssCombiner::$bootstrapResponsiveCssKey]))
		{
			$arrHashs = array();

			foreach($arrReturn[ExtCssCombiner::$bootstrapResponsiveCssKey] as $arrCss)
			{
				if(in_array($arrCss['hash'], $arrHashs)) continue;
				$arrUserCss[] = sprintf('%s|%s|%s|%s', $arrCss['src'], $arrCss['type'], $arrCss['mode'], $arrCss['hash']);
				$arrHashs[] = $arrCss['hash'];
			}
		}
		
		// add font awesome if checked
		if(isset($arrReturn[ExtCssCombiner::$fontAwesomeCssKey]) && is_array($arrReturn[ExtCssCombiner::$fontAwesomeCssKey]))
		{
			$arrHashs = array();

			foreach($arrReturn[ExtCssCombiner::$fontAwesomeCssKey] as $arrCss)
			{
				if(in_array($arrCss['hash'], $arrHashs)) continue;
				$arrUserCss[] = sprintf('%s|%s|%s|%s', $arrCss['src'], $arrCss['type'], $arrCss['mode'], $arrCss['hash']);
				$arrHashs[] = $arrCss['hash'];
			}
		}

		// collect all usercss
		if(isset($arrReturn[ExtCssCombiner::$userCssKey]) && is_array($arrReturn[ExtCssCombiner::$userCssKey]))
		{
			foreach($arrReturn[ExtCssCombiner::$userCssKey] as $arrCss)
			{
				$arrUserCss[] = sprintf('%s|%s|%s|%s', $arrCss['src'], $arrCss['type'], $arrCss['mode'], $arrCss['hash']);
			}
		}

		$GLOBALS['TL_USER_CSS'] = array_merge(is_array($GLOBALS['TL_USER_CSS']) ? $GLOBALS['TL_USER_CSS'] : array(), $arrUserCss);

	}
}
