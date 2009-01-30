<?php

/**
 * class.flexupload.inc.php
 *
 * PHP-Class for easy implementation of FlexUpload in your own scripts
 *
 * Copyright (C) 2007 SPLINELAB, Mirko Schaal
 * http://www.splinelab.de/flexupload/
 *
 * All rights reserved
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the textfile GPL and important notices to the license from
 * the author is found in the LICENSE file distributed with the program.
 *
 * This copyright notice MUST APPEAR in all copies of the program!
 *
 * @version 1.0
 * @author Mirko Schaal <ms@splinelab.com>
 * @package FlexUpload
 * @subpackage core
 */

/**
 * PHP-Class for easy implementation of FlexUpload in your own scripts
 * @version 1.0
 * @author Mirko Schaal <ms@splinelab.com>
 * @package FlexUpload
 * @subpackage core
 */
class FlexUpload {

	/**
	 * the url to the upload script
	 * @access private
	 * @var String
	 */
	var $_postURL;

	/**
	 * the path to flexupload.swf
	 * @access private
	 * @var String
	 */
	var $_pathToSWF;

	/**
	 * maximal allowed filesize
	 * @access private
	 * @var integer
	 */
	var $_maxFileSize;

	/**
	 * maximal allowed files
	 * @access private
	 * @var integer
	 */
	var $_maxFiles;

	/**
	 * allowed file extensions
	 * @access private
	 * @var String
	 */
	var $_fileExtensions;

	/**
	 * the locale
	 * @access private
	 * @var String
	 */
	var $_locale;

	/**
	 * the width of the flash movie
	 * @access private
	 * @var integer
	 */
	var $_width;

	/**
	 * the height of the flash movie
	 * @access private
	 * @var integer
	 */
	var $_height;

	/**
	 * message to show when using SWFObject an Flash-Plugin is missing or wrong version
	 * @access private
	 * @var string
	 */
	var $_noFlashMsg = '<p>You may not have everything you need to use Flexupload.<br />Please install or update "Flash Player" which is available for free at the <a href="http://www.adobe.com/shockwave/download/download.cgi?P1_Prod_Version=ShockwaveFlash" target="_blank">Adobe website</a>.<br />Please also make sure you have JavaScript enabled.</p>';

	/**
	 * path to the SWFObject Script
	 * @access private
	 * @var string
	 */
	var $_pathToSWFObject = 'js/';

	/**
	 * Constructor
	 *
	 * @param String $postURL see {@link setPostURL}
	 * @param String $pathToSWF see {@link setPathToSWF}
	 * @param String $width see {@link setWidth}
	 * @param String $height see {@link setHeight}
	 * @param String $maxFileSize see {@link setMaxFileSize}
	 * @param String $maxFiles see {@link setMaxFiles}
	 * @param String $fileExtensions see {@link setFileExtensions}
	 * @param String $locale see {@link setLocale}
	 */
	function FlexUpload($postURL, $pathToSWF='', $width=500, $height=300, $maxFileSize=2097152,
						$maxFiles=100, $fileExtensions='*.gif;*.jpg;*.jpeg;*.png', $locale='' ) {
		$this->_postURL = $postURL;
		$this->_pathToSWF = $pathToSWF;
		$this->_width = $width;
		$this->_height = $height;
		$this->_maxFileSize = $maxFileSize;
		$this->_maxFiles = $maxFiles;
		$this->_fileExtensions = $fileExtensions;
		$this->_locale = $locale;
	}

	/**
	 * set the postURL parameter
	 *
	 * the postURL have to be a full url incl. protocol
	 * e.g. "http://localhost/upload.php"
	 *
	 * @param String $postURL
	 */
	function setPostURL($postURL) {
		$this->_postURL = $postURL;
	}

	/**
	 * set the path to the SWF file
	 *
	 * Give an absolute or relative path to flexupload.swf or an empty string if flexupload.swf is in the same
	 * directory like the php-file using this class.
	 *
	 * default is empty string
	 *
	 * @param String $path
	 */
	function setPathToSWF($path) {
		$this->_pathToSWF = $path;
	}

	/**
	 * set the width of the application
	 *
	 * the applet automatically scales to this width
	 *
	 * default is 500
	 *
	 * @param integer $w
	 */
	function setWidth($w) {
		$this->_width = $w;
	}

	/**
	 * set the height of the application
	 *
	 * the applet automatically scales to this height
	 *
	 * default is 300
	 *
	 * @param integer $h
	 */
	function setHeight($h) {
		$this->_height = $h;
	}

	/**
	 * set the maximum filesize (in bytes) allowed to upload
	 *
	 * default is 2MB (2097152 bytes)
	 *
	 * @param integer $mfs
	 */
	function setMaxFileSize($mfs) {
		$this->_maxFileSize = intval($mfs);
	}

	/**
	 * set the maximum of files to upload at once
	 *
	 * set this to -1 for no limit
	 *
	 * default is 100
	 *
	 * @param integer $mf
	 */
	function setMaxFiles($mf) {
		$this->_maxFiles = intval($mf);
	}

	/**
	 * set the allowed file extensions separated by semicolons (;)
	 *
	 * set to empty string for all files
	 *
	 * default is "*.gif;*.jpg;*.jpeg;*.png"
	 *
	 * @param String $fe
	 */
	function setFileExtensions($fe) {
		$this->_fileExtensions = $fe;
	}


	/**
	 * set the language file for the application
	 *
	 * e.g "locale/de.xml" or "http://www.example.com/flexupload/locale/de.xml"
	 * set en empty string to use the default locale (english)
	 *
	 * default is ""
	 *
	 * @param integer $l
	 */
	function setLocale($l) {
		$this->_locale = $l;
	}


	/**
	 * set the message to show when using SWFObject an flash plugin is not installed or wrong version
	 *
	 * default is "You may not have everything you need ..."
	 *
	 * @param string $msg
	 */
	function setNoFlashMessage($msg)
	{
		$this->_noFlashMsg = $msg;
	}

	/**
	 * set the path to the SWFObject JavaScript script
	 *
	 * default is "js/"
	 *
	 * @param string $path
	 */
	function setPathToSWFObject($path)
	{
		$this->_pathToSWFObject = $path;
	}

	/**
	 * get the HTML code for implementing flexupload
	 *
	 * see {@link printHTML}
	 *
	 * @param Boolean	$useSWFObject		Use the SWFObject script for output (recommended)
	 * @param String	$divId				a unique id for the SWFObject <div>-Tag
	 * @param Boolean	$includeSWFObject	Include the swfobject.js in the code (if you don't set this to true you have to manually include swfobject.js) Also note: if you have more than one flexupload in one page you should include the javascript in the first one only!
	 *
	 * @return String $HTML the html code for the application
	 */
	function getHTML($useSWFObject=true, $divId='flexupload', $includeSWFObject=true)
	{
		$params =  '?postURL='.rawurlencode($this->_postURL);
		$params .= '&maxFileSize='.$this->_maxFileSize;
		$params .= '&maxFiles='.$this->_maxFiles;
		$params .= '&fileExtensions='.rawurlencode($this->_fileExtensions);
		$params .= '&locale='.rawurlencode($this->_locale);

		$content = '';

		if ($useSWFObject) {
			if ($includeSWFObject)
			$content .= '<script type="text/javascript" src="../../libs/flexupload/swfobject.js"></script>';
			$content .= '<div id="'.$divId.'">'.$this->_noFlashMsg."\n";
	   		$content .= '<script type="text/javascript">'."\n";
			$content .= '// <![CDATA['."\n";
			$content .= 'var obj_'.$divId.' = new SWFObject("'.$this->_pathToSWF.'flexupload.swf'.$params.'", "'.$divId.'", "'.$this->_width.'", "'.$this->_height.'", "9", "#869ca7");'."\n";
			$content .= 'obj_'.$divId.'.addParam("scale", "noscale");'."\n";
			$content .= 'obj_'.$divId.'.addParam("salign", "lt");'."\n";
			$content .= 'obj_'.$divId.'.addParam("menu", "false");'."\n";
			$content .= 'obj_'.$divId.'.addParam("quality", "best");'."\n";
			$content .= 'obj_'.$divId.'.addParam("wmode", "transparent");'."\n";
			$content .= 'obj_'.$divId.'.write("'.$divId.'");'."\n";
			$content .= '// ]]>'."\n";
			$content .= '</script>'."\n";
			$content .= '</div>'."\n";
		} else {
			$content = '
			<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" id="flexupload" width="'.$this->_width.'" height="'.$this->_height.'" codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
				<param name="movie" value="'.$this->_pathToSWF.'flexupload.swf'.$params.'" />
				<param name="quality" value="best" />
				<param name="bgcolor" value="#869ca7" />
				<param name="wmode" value="transparent" />
				<param name="menu" value="false" />
				<param name="scale" value="noscale" />
				<param name="salign" value="lt" />
				<embed src="'.$this->_pathToSWF.'flexupload.swf'.$params.'" quality="best" bgcolor="#869ca7"
					width="'.$this->_width.'" height="'.$this->_height.'" name="flexupload" align="middle" play="true" loop="false"
					wmode="transparent"
					menu="false"
					scale="noscale"
					salign="lt"
					pluginspage="http://www.adobe.com/go/getflashplayer">
				</embed>
			</object>';
		}
		return $content;
	}

	/**
	 * prints the HTML code for flexupload to screen
	 *
	 * see {@link getHTML}
	 *
	 * @param Boolean	$useSWFObject		Use the SWFObject script for output (recommended)
	 * @param String	$divId				a unique id for the SWFObject <div>-Tag
	 * @param Boolean	$includeSWFObject	Include the swfobject.js in the code (if you don't set this to true you have to manually include swfobject.js) Also note: if you have more than one flexupload in one page you should include the javascript in the first one only!
	 */
	function printHTML($useSWFObject=true, $divId='flexupload', $includeSWFObject=true) {
		echo $this->getHTML($useSWFObject, $divId, $includeSWFObject);
	}
}
?>