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
 * Manages the items in the page menu and eventually returns the menu as a
 * string with ToString();
 */
class MenuModule extends Module {
	
	/**
	 * An array of menu items.
	 */
	public $items;
	
	/**
	 * The html id attribute to be applied to the root element of the menu.
	 * Default is "Menu".
	 */
	public $htmlId;
	
	/**
	 * The class attribute to be applied to the root element of the
	 * breadcrumb. Default is none.
	 */
	public $cssClass;
	
	/**
	 * An array of menu group names arranged in the order that the menu
	 * should be rendered.
	 */
	public $sort;
	
	/**
	 * A route that, if found in the menu links, should cause that link to
	 * have the Highlight class applied. This property is assigned with
	 * $this->highlight();
	 */
	private $highlightRoute;

	public function __construct($sender = '') {
		$this->htmlId = 'Menu';
		$this->clearGroups();
		parent::__construct($sender);
	}
	
	public function addLink($group, $text, $url, $permission = false, $attributes = '', $anchorAttributes = '') {
		if (!array_key_exists($group, $this->items))
			$this->items[$group] = array();

		$this->items[$group][] = array('Text' => $text, 'Url' => $url, 'Permission' => $permission, 'Attributes' => $attributes, 'AnchorAttributes' => $anchorAttributes);
	}
	
	public function addItem($group, $text, $permission = false, $attributes = '') {
		if (!array_key_exists($group, $this->items))
			$this->items[$group] = array();

		$this->items[$group][] = array('Text' => $text, 'Url' => false, 'Permission' => $permission, 'Attributes' => $attributes);
	}      
	
	public function assetTarget() {
		return 'menu';
	}
	
	public function clearGroups() {
		$this->items = array();
	}
	
	public function highlightRoute($route) {
		$this->highlightRoute = $route;
	}
	
	public function removeLink($group, $text) {
		if (array_key_exists($group, $this->items) && is_array($this->items[$group])) {
			foreach ($this->items[$group] as $index => $groupArray) {
				if ($this->items[$group][$index]['Text'] == $text) {
					unset($this->items[$group][$index]);
					array_merge($this->items[$group]);
					break;
				}
			}
		}
	}

	/**
	 * Removes all links from a specific group.
	 */
	public function removeLinks($group) {
		$this->items[$group] = array();
	}
	
	/**
	 * Removes an entire group of links, and the group itself, from the menu.
	 */
	public function removeGroup($group) {
		if (array_key_exists($group, $this->items)) {
			unset($this->items[$group]);
		}
	}

	protected function getHighlightClass() {
		return 'Highlight';
	}
	
	public function toString($highlightRoute = '') {
		if ($highlightRoute == '') $highlightRoute = $this->highlightRoute;
		if ($highlightRoute == '') $highlightRoute = StaticRequest('RequestUri');
			
		$this->fireEvent('BeforeToString');
		
		$username = '';
		$userId = '';
		$sessionTransientKey = '';
		$permissions = array();
		$session = application('session.handler');
		$hasPermissions = false;
		$admin = false;
		if ($session->isValid() === true) {
			$userId = $session->userId();
			$username = $session->getUser()->name;
			$sessionTransientKey = $session->transientKey();
			$permissions = array();
			// $permissions = $session->GetPermissions();
			$hasPermissions = count($permissions) > 0;
			// $admin = $session->User->Admin > 0 ? true : false;
		}
		
		$menu = '';
		if (count($this->items) > 0) {
			// Apply the menu group sort if present...
			if (is_array($this->sort)) {
				$items = array();
				$count = count($this->sort);
				for ($i = 0; $i < $count; ++$i) {
					$group = $this->sort[$i];
					if (array_key_exists($group, $this->items)) {
						$items[$group] = $this->items[$group];
						unset($this->items[$group]);
					}
				}
				foreach ($this->items as $group => $links) {
					$items[$group] = $links;
				}
			} else {
				$items = $this->items;
			}
			foreach ($items as $groupName => $links) {
				$itemCount = 0;
				$linkCount = 0;
				$openGroup = false;
				$group = '';
				foreach ($links as $key => $link) {
					$currentLink = false;
					$showLink = false;
					$requiredPermissions = array_key_exists('Permission', $link) ? $link['Permission'] : false;
					if ($requiredPermissions !== false && !is_array($requiredPermissions))
						$requiredPermissions = explode(',', $requiredPermissions);
						
					// Show if there are no permissions or the user has the required permissions or the user is admin
					$showLink = $admin || $requiredPermissions === false || ArrayInArray($requiredPermissions, $permissions, false) === true;
					
					if ($showLink === true) {
						if ($itemCount == 1) {
							$group .= '<ul>';
							$openGroup = true;
						} else if ($itemCount > 1) {
							$group .= "</li>\n";
						}
						
						$url = ArrayValue('Url', $link);
						if (substr($link['Text'], 0, 1) === '\\') {
							$text = substr($link['Text'], 1);
						} else {
							$text = str_replace('{username}', $username, $link['Text']);
						}
						$attributes = ArrayValue('Attributes', $link, array());
						$anchorAttributes = ArrayValue('AnchorAttributes', $link, array());
						if ($url !== false) {
							$replace = array(
								'{username}' => urlencode($username),
								'{userId}' => $userId,
								'{transientKey}' => $sessionTransientKey,
								'{selfUrl}' => $this->sender->selfUrl
							);
							$url = Url(str_replace(array_keys($replace), array_values($replace), $link['Url']));
							$currentLink = ($url == Url($highlightRoute));
							
							$cssClass = ArrayValue('class', $attributes, '');
							if ($currentLink) $attributes['class'] = $cssClass . ' ' . $this->getHighlightClass();
							
							$group .= '<li'.Attribute($attributes).'><a'.Attribute($anchorAttributes).' href="'.$url.'">'.$text.'</a>';
							++$linkCount;
						} else {
							$group .= '<li'.Attribute($attributes).'>'.$text;
						}
						++$itemCount;
					}
				}
				if ($openGroup === true) {
					$group .= "</li>\n</ul>\n";
				}
				if ($group != '' && $linkCount > 0) {
					$menu .= $group . "</li>\n";
				}
			}
			if ($menu != '') {
				$menu = '<ul id="'.$this->htmlId.'"'.($this->cssClass != '' ? ' class="'.$this->cssClass.'"' : '').'>'.$menu.'</ul>';
			}
		}
		return $menu;
	}
}