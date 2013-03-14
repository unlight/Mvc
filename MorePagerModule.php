<?php
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

/**
 * Builds a pager control related to a dataset.
 */
class MorePagerModule extends Module {

	/**
	 * The id applied to the div tag that contains the pager.
	 */
	public $clientID;
	
	/**
	 * The name of the stylesheet class to be applied to the pager. Default is
	 * 'Pager';
	 */
	public $cssClass;

	/**
	 * Translation code to be used for "more" link.
	 */
	public $moreCode;

	/**
	 * If there are no pages to page through, this string will be returned in
	 * place of the pager. Default is an empty string.
	 */
	public $pagerEmpty;
	
	/**
	 * The xhtml code that should wrap around the pager link.
	 *  ie. '<div %1$s>%2$s</div>';
	 * where %1$s represents id and class attributes (if defined by
	 * $this->clientID and $this->cssClass) and %2$s represents the pager link.
	 */
	public $wrapper;

	/**
	 * Translation code to be used for "less" link.
	 */
	public $lessCode;

	/**
	 * The number of records being displayed on a single page of data. Default
	 * is 30.
	 */
	public $limit;
	
	/**
	 * The total number of records in the dataset.
	 */
	public $totalRecords;
	
	/**
	 * The string to contain the record offset. ie. /controller/action/%s/
	 */
	public $url;
	
	/**
	 * The first record of the current page (the dataset offset).
	 */
	private $offset;
	
	/**
	 * The last offset of the current page. (ie. offset to lastOffset of totalRecords)
	 */
	private $lastOffset;
	
	/**
	 * Certain properties are required to be defined before the pager can build
	 * itself. Once they are created, this property is set to true so they are
	 * not needlessly recreated.
	 */
	private $propertiesDefined;
	
	/**
	 * A boolean value indicating if the total number of records is known or
	 * not. Retrieving this number can be a costly database query, so sometimes
	 * it is not retrieved and simple "next/previous" links are displayed
	 * instead. Default is false, meaning that the simple pager is displayed.
	 */
	private $totalled;

	public function __construct($sender = '') {
		$this->clientID = '';
		$this->cssClass = 'MorePager';
		$this->offset = 0;
		$this->limit = 30;
		$this->totalRecords = 0;
		$this->wrapper = '<div %1$s>%2$s</div>';
		$this->pagerEmpty = '';
		$this->moreCode = 'More %s';
		$this->lessCode = 'Newer %s';
		$this->url = '/controller/action/{page}/';
		$this->propertiesDefined = false;
		$this->totalled = false;
		$this->lastOffset = 0;
		parent::__construct($sender);
	}

	function AssetTarget() {
		return false;
	}

	/**
	 * Define all required parameters to create the Pager and PagerDetails.
	 */
	public function Configure($offset, $limit, $totalRecords, $url, $forceConfigure = false) {
		if ($this->propertiesDefined === false || $forceConfigure === true) {
			$this->url = $url;
			$this->offset = $offset;         
			$this->limit = is_numeric($limit) && $limit > 0 ? $limit : $this->limit;
			$this->totalRecords = is_numeric($totalRecords) ? $totalRecords : 0;
			$this->totalled = ($this->totalRecords >= $this->limit) ? false : true;
			$this->lastOffset = $this->offset + $this->limit;
			if ($this->lastOffset > $this->totalRecords) {
				$this->lastOffset = $this->totalRecords;
			}
			$this->propertiesDefined = true;
		}
	}
	
	// Builds a string with information about the page list's current position (ie. "1 to 15 of 56").
	// Returns the built string.
	public function Details() {
		if ($this->propertiesDefined === false) {
			$ErrorMessage = 'You must configure the pager with $pager->Configure() before retrieving the pager details.';
			if (function_exists('ErrorMessage')) {
				$ErrorMessage = ErrorMessage($ErrorMessage, 'MorePager', 'Details');
			}
			trigger_error($ErrorMessage, E_USER_ERROR);
		}
			
		$Details = false;
		if ($this->totalRecords > 0) {
			if ($this->totalled === true) {
				$Details = self::FormatUrl(T('%1$s to %2$s of %3$s'), $this->offset + 1, $this->lastOffset, $this->totalRecords);
			} else {
				$Details = self::FormatUrl(T('%1$s to %2$s'), $this->offset, $this->lastOffset);
			}
		}
		return $Details;
	}
	
	/**
	 * Whether or not this is the first page of the pager.
	 *
	 * @return bool true if this is the first page.
	 */
	public function FirstPage() {
		$Result = $this->offset == 0;
		return $Result;
	}

	public static function FormatUrl($url, $offset, $limit = '') {
		// Check for new style page.
		$page = PageNumber($offset, $limit, true);
		return str_replace(array('{offset}', '{page}', '{size}'), array($offset, $page, $limit), $url);
	}

	/**
	 * Whether or not this is the last page of the pager.
	 *
	 * @return bool true if this is the last page.
	 */
	public function LastPage() {
		$Result = $this->offset + $this->limit >= $this->totalRecords;
		return $Result;
	}

	/**
	 * Returns the "show x more (or less) items" link.
	 *
	 * @param string The type of link to return: more or less
	 */
	public function ToString($Type = 'more') {
		if ($this->propertiesDefined === false)
			trigger_error('You must configure the pager with $pager->Configure() before retrieving the pager.', E_USER_ERROR);
		
		// Urls with url-encoded characters will break sprintf, so we need to convert them for backwards compatibility.
		$this->url = str_replace(array('%1$s', '%2$s', '%s'), array('{offset}', '{size}', '{offset}'), $this->url);

		$pager = '';
		if ($Type == 'more') {
			$clientID = $this->clientID == '' ? '' : $this->clientID . 'More';
			if ($this->offset + $this->limit >= $this->totalRecords) {
				$pager = ''; // $this->offset .' + '. $this->limit .' >= '. $this->totalRecords;
			} else {
				$ActualRecordsLeft = $RecordsLeft = $this->totalRecords - $this->lastOffset;
				if ($RecordsLeft > $this->limit)
					$RecordsLeft = $this->limit;
					
				$NextOffset = $this->offset + $this->limit;

				$pager .= Anchor(
					sprintf(T($this->moreCode), $ActualRecordsLeft),
					self::FormatUrl($this->url, $NextOffset, $this->limit),
					'',
					array('rel' => 'nofollow')
				);
			}
			$cssClass = array($this->cssClass, 'More');
		} else if ($Type == 'less') {
			$clientID = $this->clientID == '' ? '' : $this->clientID . 'Less';
			if ($this->offset <= 0) {
				$pager = '';
			} else {
				$RecordsBefore = $this->offset;
				if ($RecordsBefore > $this->limit) $RecordsBefore = $this->limit;
				$PreviousOffset = $this->offset - $this->limit;
				if ($PreviousOffset < 0) $PreviousOffset = 0;
					
				$pager .= Anchor(
					sprintf(T($this->lessCode), $this->offset),
					self::FormatUrl($this->url, $PreviousOffset, $RecordsBefore),
					'',
					array('rel' => 'nofollow')
				);
			}
			$cssClass = array($this->cssClass, 'Less');
		}
		if ($pager == '') {
			return $this->pagerEmpty;
		} else {
			return sprintf($this->wrapper, Attribute(array('id' => $clientID, 'class' => implode(' ', $cssClass))), $pager);
		}
	}
	
	/** 
	 * Are there more pages after the current one?
	 */
	public function HasMorePages() {
		return $this->totalRecords > $this->offset + $this->limit;
	}
	
}