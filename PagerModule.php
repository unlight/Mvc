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
class PagerModule extends Module {

	/**
	 * The id applied to the div tag that contains the pager.
	 */
	public $clientID;

	/**
	 * @var PagerModule
	 */
	protected static $currentPager;
	
	/**
	 * The name of the stylesheet class to be applied to the pager. Default is
	 * 'Pager';
	 */
	public $cssClass;
	
	/**
	 * The number of records in the current page.
	 * @var int 
	 */
	public $currentRecords = false;

	/**
	 * The default number of records per page.
	 * @var int
	 */
	public static $defaultPageSize = 30;

	/**
	 * Translation code to be used for "Next Page" link.
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
	 *
	 * @var type 
	 */
	public $urlCallBack;
	
	/**
	 * The first record of the current page (the dataset offset).
	 */
	public $offset;
	
	/**
	 * The last offset of the current page. (ie. Offset to LastOffset of TotalRecords)
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
		$this->clientID = 'Pager';
		$this->cssClass = 'Pager';
		$this->offset = 0;
		$this->limit = self::$defaultPageSize;
		$this->totalRecords = false;
		$this->wrapper = '<div class="PagerWrap"><div %1$s>%2$s</div></div>';
		$this->pagerEmpty = '';
		$this->moreCode = '»';
		$this->lessCode = '«';
		$this->url = '/controller/action/$s/';
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
			if (is_array($url)) {
				if (count($url) == 1)
					$this->urlCallBack = array_pop($url);
				else
					$this->urlCallBack = $url;
			} else {
				$this->url = $url;
			}

			$this->offset = $offset;         
			$this->limit = is_numeric($limit) && $limit > 0 ? $limit : $this->limit;
			$this->totalRecords = $totalRecords;
			$this->lastOffset = $this->offset + $this->limit;
			$this->totalled = ($this->totalRecords >= $this->limit) ? false : true;
			if ($this->lastOffset > $this->totalRecords)
				$this->lastOffset = $this->totalRecords;
					
			$this->propertiesDefined = true;
		}
	}

	/**
	 * Gets the controller this pager is for.
	 * @return Controller.
	 */
	public function Controller() {
		return $this->sender;
	}
	
	public static function Current($value = NULL) {
		if ($value !== NULL) {
			self::$currentPager = $value;
		} elseif (self::$currentPager == NULL) {
			self::$currentPager = new PagerModule(Gdn::Controller());
		}
		
		return self::$currentPager;
	}
	
	// Builds a string with information about the page list's current position (ie. "1 to 15 of 56").
	// Returns the built string.
	public function Details($formatString = '') {
		if ($this->propertiesDefined === false)
			trigger_error(ErrorMessage('You must configure the pager with $pager->Configure() before retrieving the pager details.', 'MorePager', 'Details'), E_USER_ERROR);
			
		$details = false;
		if ($this->totalRecords > 0) {
			if ($formatString != '') {
				$details = sprintf(T($formatString), $this->offset + 1, $this->lastOffset, $this->totalRecords);
			} else if ($this->totalled === true) {
				$details = sprintf(T('%1$s to %2$s of %3$s'), $this->offset + 1, $this->lastOffset, $this->totalRecords);
			} else {
				$details = sprintf(T('%1$s to %2$s'), $this->offset, $this->lastOffset);
			}
		}
		return $details;
	}
	
	/**
	 * Whether or not this is the first page of the pager.
	 *
	 * @return bool true if this is the first page.
	 */
	public function FirstPage() {
		$result = $this->offset == 0;
		return $result;
	}
	
	public static function FormatUrl($url, $page, $limit = '') {
		// Check for new style page.
		if (strpos($url, '{page}') !== false)
			return str_replace(array('{page}', '{Size}'), array($page, $limit), $url);
		else
			return sprintf($url, $page, $limit);
	}

	/**
	 * Whether or not this is the last page of the pager.
	 *
	 * @return bool true if this is the last page.
	 */
	public function LastPage() {
		return $this->offset + $this->limit >= $this->totalRecords;
	}
	
	public static function Rel($page, $currentPage) {
		if ($page == $currentPage - 1)
			return 'prev';
		elseif ($page == $currentPage + 1)
			return 'next';
		
		return NULL;
	}
	
	public function PageUrl($page) {
		if ($this->urlCallBack) {
			return call_user_func($this->urlCallBack, $this->record, $page);
		} else {
			return self::FormatUrl($this->url, 'p'.$page);
		}
	}

	/**
	 * Builds page navigation links.
	 *
	 * @param string $type Type of link to return: 'more' or 'less'.
	 * @return string HTML page navigation links.
	 */
	public function ToString($type = 'more') {
		if ($this->propertiesDefined === false)
			trigger_error(ErrorMessage('You must configure the pager with $pager->Configure() before retrieving the pager.', 'MorePager', 'GetSimple'), E_USER_ERROR);
		
		// Urls with url-encoded characters will break sprintf, so we need to convert them for backwards compatibility.
		$this->url = str_replace(array('%1$s', '%2$s', '%s'), '{page}', $this->url);
		
		if ($this->totalRecords === false) {
			return $this->toStringPrevNext($type);
		}
		
		$this->cssClass = ConcatSep(' ', $this->cssClass, 'NumberedPager');
			
		$pageCount = ceil($this->totalRecords / $this->limit);
		$currentPage = ceil($this->offset / $this->limit) + 1;
		
		// Show $range pages on either side of current
		$range = C('Garden.Modules.PagerRange', 3);
		
		// String to represent skipped pages
		$separator = C('Garden.Modules.PagerSeparator', '&#8230;');
		
		// Show current page plus $range pages on either side
		$pagesToDisplay = ($range * 2) + 1;
		if ($pagesToDisplay + 2 >= $pageCount) {
			// Don't display an ellipses if the page count is only a little bigger that the number of pages.
			$pagesToDisplay = $pageCount;
		}

		$pager = '';
		$previousText = T($this->lessCode);
		$nextText = T($this->moreCode);
		
		// Previous
		if ($currentPage == 1) {
			$pager = '<span class="Previous">'.$previousText.'</span>';
		} else {
			$pager .= Anchor($previousText, $this->pageUrl($currentPage - 1), 'Previous', array('rel' => 'prev'));
		}
		
		// Build Pager based on number of pages (Examples assume $range = 3)
		if ($pageCount <= 1) {
			// Don't build anything
			
		} else if ($pageCount <= $pagesToDisplay) {
			// We don't need elipsis (ie. 1 2 3 4 5 6 7)
			for ($i = 1; $i <= $pageCount ; $i++) {
				$pager .= Anchor($i, $this->pageUrl($i), $this->getCssClass($i, $currentPage), array('rel' => self::Rel($i, $currentPage)));
			}
			
		} else if ($currentPage + $range <= $pagesToDisplay + 1) { // +1 prevents 1 ... 2
			// We're on a page that is before the first elipsis (ex: 1 2 3 4 5 6 7 ... 81)
			for ($i = 1; $i <= $pagesToDisplay; $i++) {
				$pageParam = 'p'.$i;
				$pager .= Anchor($i, $this->pageUrl($i), $this->getCssClass($i, $currentPage), array('rel' => self::Rel($i, $currentPage)));
			}

			$pager .= '<span class="Ellipsis">'.$separator.'</span>';
			$pager .= Anchor($pageCount, $this->pageUrl($pageCount));
			
		} else if ($currentPage + $range >= $pageCount - 1) { // -1 prevents 80 ... 81
			// We're on a page that is after the last elipsis (ex: 1 ... 75 76 77 78 79 80 81)
			$pager .= Anchor(1, $this->pageUrl(1));
			$pager .= '<span class="Ellipsis">'.$separator.'</span>';
			
			for ($i = $pageCount - ($pagesToDisplay - 1); $i <= $pageCount; $i++) {
				$pageParam = 'p'.$i;
				$pager .= Anchor($i, $this->pageUrl($i), $this->getCssClass($i, $currentPage), array('rel' => self::Rel($i, $currentPage)));
			}
			
		} else {
			// We're between the two elipsises (ex: 1 ... 4 5 6 7 8 9 10 ... 81)
			$pager .= Anchor(1, $this->pageUrl(1));
			$pager .= '<span class="Ellipsis">'.$separator.'</span>';
			
			for ($i = $currentPage - $range; $i <= $currentPage + $range; $i++) {
				$pageParam = 'p'.$i;
				$pager .= Anchor($i, $this->pageUrl($i), $this->getCssClass($i, $currentPage), array('rel' => self::Rel($i, $currentPage)));
			}

			$pager .= '<span class="Ellipsis">'.$separator.'</span>';
			$pager .= Anchor($pageCount, $this->pageUrl($pageCount));
		}
		
		// Next
		if ($currentPage == $pageCount) {
			$pager .= '<span class="Next">'.$nextText.'</span>';
		} else {
			$pageParam = 'p'.($currentPage + 1);
			$pager .= Anchor($nextText, $this->pageUrl($currentPage + 1), 'Next', array('rel' => 'next')); // extra sprintf parameter in case old url style is set
		}
		if ($pageCount <= 1)
			$pager = '';

		$clientID = $this->clientID;
		$clientID = $type == 'more' ? $clientID.'After' : $clientID.'Before';

		if (isset($this->htmlBefore)) {
			$pager = $this->htmlBefore.$pager;
		}
		
		return $pager == '' ? '' : sprintf($this->wrapper, Attribute(array('id' => $clientID, 'class' => $this->cssClass)), $pager);
	}
	
	public function ToStringPrevNext($type = 'more') {
		$this->cssClass = ConcatSep(' ', $this->cssClass, 'PrevNextPager');
		$currentPage = PageNumber($this->offset, $this->limit);
		
		$pager = '';
		
		if ($currentPage > 1) {
			$pageParam = 'p'.($currentPage - 1);
			$pager .= Anchor(T('Previous'), $this->pageUrl($currentPage - 1), 'Previous', array('rel' => 'prev'));
		}
		
		$hasNext = true;
		if ($this->currentRecords !== false && $this->currentRecords < $this->limit)
			$hasNext = false;
		
		if ($hasNext) {
			$pageParam = 'p'.($currentPage + 1);
			$pager = ConcatSep(' ', $pager, Anchor(T('Next'), $this->pageUrl($currentPage + 1), 'Next', array('rel' => 'next')));
		}
		
		$clientID = $this->clientID;
		$clientID = $type == 'more' ? $clientID.'After' : $clientID.'Before';
		
		if (isset($this->htmlBefore)) {
			$pager = $this->htmlBefore.$pager;
		}
		
		return $pager == '' ? '' : sprintf($this->wrapper, Attribute(array('id' => $clientID, 'class' => $this->cssClass)), $pager);
	}

	public static function Write($options = array()) {
		static $writeCount = 0;

		if (!self::$currentPager) {
			if (is_a($options, 'Gdn_Controller')) {
				self::$currentPager = new PagerModule($options);
				$options = array();
			} else {
				self::$currentPager = new PagerModule(GetValue('Sender', $options, Gdn::Controller()));
			}
		}
		$pager = self::$currentPager;
		
		$pager->Wrapper = GetValue('Wrapper', $options, $pager->Wrapper);
		$pager->MoreCode = GetValue('MoreCode', $options, $pager->MoreCode);
		$pager->LessCode = GetValue('LessCode', $options, $pager->LessCode);
		
		$pager->ClientID = GetValue('ClientID', $options, $pager->ClientID);

		$pager->Limit = GetValue('Limit', $options, $pager->Controller()->Data('_Limit', $pager->Limit));
		$pager->HtmlBefore = GetValue('HtmlBefore', $options, GetValue('HtmlBefore', $pager, ''));
		$pager->CurrentRecords = GetValue('CurrentRecords', $options, $pager->Controller()->Data('_CurrentRecords', $pager->CurrentRecords));
		
		// Try and figure out the offset based on the parameters coming in to the controller.
		if (!$pager->Offset) {
			$page = $pager->Controller()->Request->Get('Page', false);
			if (!$page) {
				$page = 'p1';
				foreach($pager->Controller()->RequestArgs as $arg) {
					if (preg_match('`p\d+`', $arg)) {
						$page = $arg;
						break;
					}
				}
			}
			list($offset, $limit) = OffsetLimit($page, $pager->Limit);
			$totalRecords = GetValue('RecordCount', $options, $pager->Controller()->Data('RecordCount', false));

			$get = $pager->Controller()->Request->Get();
			unset($get['Page'], $get['DeliveryType'], $get['DeliveryMethod']);
			$url = GetValue('Url', $options, $pager->Controller()->SelfUrl.'?Page={page}&'.http_build_query($get));

			$pager->Configure($offset, $limit, $totalRecords, $url);
		}

		echo $pager->ToString($writeCount > 0 ? 'more' : 'less');
		$writeCount++;

	}
	
	private function GetCssClass($thisPage, $highlightPage) {
		return $thisPage == $highlightPage ? 'Highlight' : false;
	}
	
	/** 
	 * Are there more pages after the current one?
	 */
	public function HasMorePages() {
		return $this->totalRecords > $this->offset + $this->limit;
	}
}