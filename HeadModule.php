<?php

/**
 * Manages collections of items to be placed between the <HEAD> tags 
 * of the page.
 */

class HeadModule extends Module {
	/**
	 * The name of the key in a tag that refers to the tag's name.
	 */
	const TAG_KEY = '_tag';
	const CONTENT_KEY = '_content';
	const SORT_KEY = '_sort';
	
	/**
	 * A collection of tags to be placed in the head.
	 */
	private $tags;
	
	/**
	 * A collection of strings to be placed in the head.
	 */
	private $strings;
	
	/**
	 * The main text for the "title" tag in the head.
	 */
	protected $title;
	
	/**
	 * A string to be concatenated with $this->title.
	 */
	protected $subTitle;

	/**
	 * A string to be concatenated with $this->title if there is also a
	 * $this->subTitle string being concatenated.
	 */
	protected $titleDivider;
	
	public function __construct($sender = '') {
		$this->tags = array();
		$this->strings = array();
		$this->title = '';
		$this->subTitle = '';
		$this->titleDivider = '';
		parent::__construct($sender);
	}

	/**
	 * [config description]
	 * @todo Move config() function to module or pluggable
	 * @param  [type]  $name    [description]
	 * @param  boolean $default [description]
	 * @return [type]           [description]
	 */
	protected function config($name, $default = false) {
		return $this->sender->config($name, $default);
	}

	/**
	 * [FormatText description]
	 * @param [type] $value [description]
	 */
	protected function FormatText($value) {
		if (!function_exists('FormatText')) {
			LoadFunctions('Format');	
		}
		return FormatText($value);
	}

	/**
	 * Adds a "link" tag to the head containing a reference to a stylesheet.
	 *
	 * @param string $hRef Location of the stylesheet relative to the web root (if an absolute path with http:// is provided, it will use the HRef as provided). ie. /themes/default/css/layout.css or http://url.com/layout.css
	 * @param string $media Type media for the stylesheet. ie. "screen", "print", etc.
	 * @param bool $addVersion Whether to append version number as query string.
	 * @param array $options Additional properties to pass to AddTag, e.g. 'ie' => 'lt IE 7';
	 */
	public function AddCss($hRef, $media = '', $options = null) {
		$properties = array(
				'rel' => 'stylesheet',
				'type' => 'text/css',
				'href' => Asset($hRef, false, false),
				'media' => $media);
		
		// Use same underscore convention as AddScript  
		if (is_array($options)) {
				foreach ($options as $key => $value) {
					$properties['_'.strtolower($key)] = $value;
				}
		}
		
		$this->addTag('link', $properties);
	}

	public function AddRss($hRef, $title) {
		$this->addTag('link', array(
				'rel' => 'alternate',
				'type' => 'application/rss+xml',
				'title' => $this->FormatText($title),
				'href' => Asset($hRef)
		));
	}

	/**
	 * Adds a new tag to the head.
	 *
	 * @param string The type of tag to add to the head. ie. "link", "script", "base", "meta".
	 * @param array An associative array of property => value pairs to be placed in the tag.
	 * @param string an index to give the tag for later manipulation.
	 */
	public function AddTag($tag, $properties, $content = null, $index = null) {
		$tag = array_merge(array(self::TAG_KEY => strtolower($tag)), array_change_key_case($properties));
		if ($content) $tag[self::CONTENT_KEY] = $content;
		if (!array_key_exists(self::SORT_KEY, $tag)) $tag[self::SORT_KEY] = count($this->tags);

		if ($index !== null) $this->tags[$index] = $tag;
		
		// Make sure this item has not already been added.
		if (!in_array($tag, $this->tags)) $this->tags[] = $tag;
	}
	
	/**
	 * Adds a "script" tag to the head.
	 *
	 * @param string The location of the script relative to the web root. ie. "/js/jquery.js"
	 * @param string The type of script being added. ie. "text/javascript"
	 * @param mixed Additional options to add to the tag. The following values are accepted:
	 *  - numeric: This will be the script's sort.
	 *  - string: This will hint the script (inline will inline the file in the page.
	 *  - array: An array of options (ex. sort, hint, version).
	 *
	 */
	public function AddScript($src, $type = 'text/javascript', $options = array()) {
		if (is_numeric($options)) {
			$options = array('sort' => $options);
		} elseif (is_string($options)) {
			$options = array('hint' => $options);
		} elseif (!is_array($options)) {
			$options = array();
		}

		$attributes = array('src' => Asset($src, false, GetValue('version', $options)), 'type' => $type);
		if (isset($options['defer'])) {
			$attributes['defer'] = $options['defer'];
		}

		foreach ($options as $key => $value) {
			$attributes['_'.strtolower($key)] = $value;
		}
		
		$this->addTag('script', $attributes);
	}
	
	/**
	 * Adds a string to the collection of strings to be inserted into the head.
	 *
	 * @param string The string to be inserted.
	 */
	public function AddString($string) {
		$this->strings[] = $string;
	}
	
	public function AssetTarget() {
		return 'head';
	}
	
	/**
	 * Removes any added stylesheets from the head.
	 */
	public function ClearCSS() {
		$this->clearTag('link', array('rel' => 'stylesheet'));
	}
	
	/**
	 * Removes any script include tags from the head.
	 */
	public function ClearScripts() {
		$this->clearTag('script');
	}
	
	/**
	 * Removes any tags with the specified $tag, $property, and $value.
	 *
	 * Only $tag is required.
	 *
	 * @param string The name of the tag to remove from the head.  ie. "link"
	 * @param string Any property to search for in the tag.
	 *    - If this is an array then it will be treated as a query of attribute/value pairs to match against.
	 * @param string Any value to search for in the specified property.
	 */
	public function ClearTag($tag, $property = '', $value = '') {
		$tag = strtolower($tag);
		if (is_array($property))
			$query = array_change_key_case($property);
		elseif ($property)
			$query = array(strtolower($property) => $value);
		else
			$query = false;

		foreach($this->tags as $index => $collection) {
			$tagName = $collection[self::TAG_KEY];

			if ($tagName == $tag) {
				// If no property is specified and the tag is found, remove it directly.
				// Otherwise remove it only if all specified property/value pairs match.
				if (!$query || count(array_intersect_assoc($query, $collection)) == count($query)) {
						unset($this->tags[$index]);
				}
			}
		}
	}

	/**
	 * Return all strings.
	 */
	public function GetStrings() {
		return $this->strings;
	}

	/**
	 * Return all Tags of the specified type (or all tags).
	 */
	public function GetTags($requestedType = '') {
		// Make sure that css loads before js (for jquery)
		usort($this->tags, array('HeadModule', 'TagCmp')); // "link" comes before "script"

		if ($requestedType == '')
				return $this->tags;
		
		// Loop through each tag.
		$tags = array();
		foreach ($this->tags as $index => $attributes) {
			$tagType = $attributes[self::TAG_KEY];
			if ($tagType == $requestedType)
				$tags[] = $attributes;
		}
		return $tags;
	}

	/**
	 * Sets the favicon location.
	 *
	 * @param string The location of the fav icon relative to the web root. ie. /themes/default/images/layout.css
	 */
	public function SetFavIcon($hRef) {
		if (!$this->favIconSet) {
				$this->favIconSet = TRUE;
				$this->addTag('link', 
					array('rel' => 'shortcut icon', 'href' => $hRef, 'type' => 'image/x-icon'),
					null,
					'favicon');
		}
	}
	private $favIconSet = false;

	/**
	 * Gets or sets the tags collection.
	 *
	 *  @param array $value.
	 */
	public function Tags($value = null) {
		if ($value != null) $this->tags = $value;
		return $this->tags;
	}
	
	public function Title($title = '', $noSubTitle = false) {
		if ($title != '') {
			// Apply $title to $this->title and return it;
			$this->title = $title;
			$this->sender->Title($title);
			return $title;
		} else if ($this->title != '') {
			// Return $this->title if set;
			return $this->title;
		} else if ($noSubTitle) {
			return GetValueR('data.title', $this->sender, '');
		} else {
			$subtitle = GetValueR('data.subtitle', $this->sender, $this->config('application.title'));
			
			// Default Return title from controller's data.title + banner title;
			return ConcatSep(' - ', GetValueR('data.title', $this->sender, ''), $subtitle);
		}
	}
	
	public static function TagCmp($a, $b) {
		if ($a[self::TAG_KEY] == 'title') return -1;
		if ($b[self::TAG_KEY] == 'title') return 1;
		$cmp = strcasecmp($a[self::TAG_KEY], $b[self::TAG_KEY]);
		if ($cmp == 0) {
			$sortA = GetValue(self::SORT_KEY, $a, 0);
			$sortB = GetValue(self::SORT_KEY, $b, 0);
			if ($sortA < $sortB) $cmp = -1;
			elseif ($sortA > $sortB) $cmp = 1;
		}
		return $cmp;
	}
	
	/**
	 * Render the entire head module.
	 */
	public function ToString() {
		// Add the canonical Url if necessary.
		if (method_exists($this->sender, 'CanonicalUrl') && !$this->config('application.nocanonicalurl', false)) {
				$canonicalUrl = $this->sender->CanonicalUrl();
				
				if (!IsUrl($canonicalUrl))
					$canonicalUrl = Gdn::Router()->ReverseRoute($canonicalUrl);
				
				$this->sender->CanonicalUrl($canonicalUrl);
//            $currentUrl = Url('', TRUE);
//            if ($currentUrl != $canonicalUrl) {
					$this->addTag('link', array('rel' => 'canonical', 'href' => $canonicalUrl));
//            }
		}
		
		// Include facebook open-graph meta information.
		
		if ($fbAppID = $this->config('Plugins.Facebook.ApplicationID')) {
				$this->addTag('meta', array('property' => 'fb:app_id', 'content' => $fbAppID));
		}
		
		$siteName = $this->config('Garden.Title', '');
		if ($siteName != '')
				$this->addTag('meta', array('property' => 'og:site_name', 'content' => $siteName));
		
		$title = $this->FormatText($this->title('', TRUE));
		if ($title != '')
				$this->addTag('meta', array('property' => 'og:title', 'itemprop' => 'name', 'content' => $title));
		
		if (isset($canonicalUrl))
				$this->addTag('meta', array('property' => 'og:url', 'content' => $canonicalUrl));
		
		if (is_object($this->sender) 
			&& method_exists($this->sender, 'Description')
			&& $description = $this->sender->Description()) {
				$this->addTag('meta', array('name' => 'description', 'property' => 'og:description', 'itemprop' => 'description', 'content' => $description));
		}

		// Default to the site logo if there were no images provided by the controller.
		if (is_object($this->sender) && method_exists($this->sender, 'Image')):
		if (count($this->sender->Image()) == 0) {
				$logo = $this->config('Garden.ShareImage', $this->config('Garden.Logo', ''));
				if ($logo != '') {
					// Fix the logo path.
					if (StringBeginsWith($logo, 'uploads/'))
							$logo = substr($logo, strlen('uploads/'));

					$logo = Gdn_Upload::Url($logo);
					$this->addTag('meta', array('property' => 'og:image', 'itemprop' => 'image', 'content' => $logo));
				}
		} else {
			foreach ($this->sender->Image() as $img) {
				$this->addTag('meta', array('property' => 'og:image', 'itemprop' => 'image', 'content' => $img));
			}
		}
		endif;

		$tags = $this->tags;
		// Make sure that css loads before js (for jquery)
		usort($this->tags, array('HeadModule', 'TagCmp')); // "link" comes before "script"

		$this->fireEvent('BeforeToString');

		$tags2 = $this->tags;

		// Start with the title.
		$head = '<title>'.$this->FormatText($this->title())."</title>\n";

		$tagStrings = array();
		// Loop through each tag.
		foreach ($this->tags as $index => $attributes) {
				$tag = $attributes[self::TAG_KEY];

				// Inline the content of the tag, if necessary.
				if (GetValue('_hint', $attributes) == 'inline') {
					$path = GetValue('_path', $attributes);
					if (!StringBeginsWith($path, 'http')) {
							$attributes[self::CONTENT_KEY] = file_get_contents($path);

							if (isset($attributes['src'])) {
								$attributes['_src'] = $attributes['src'];
								unset($attributes['src']);
							}
							if (isset($attributes['href'])) {
								$attributes['_href'] = $attributes['href'];
								unset($attributes['href']);
							}
					}
				}
				
				// If we set an IE conditional AND a "Not IE" condition, we will need to make a second pass.
				do {
					// Reset tag string
					$tagString = '';
				
					// IE conditional? Validates condition.
					$iESpecific = (isset($attributes['_ie']) && preg_match('/((l|g)t(e)? )?IE [0-9\.]/', $attributes['_ie']));
					
					// Only allow $notIE if we're not doing a conditional this loop.
					$notIE = (!$iESpecific && isset($attributes['_notie']));
					
					// Open IE conditional tag
					if ($iESpecific) $tagString .= '<!--[if '.$attributes['_ie'].']>';
					if ($notIE) $tagString .= '<!--[if !IE]> -->';
							
					// Build tag
					$tagString .= '<'.$tag.Attribute($attributes, '_');
					if (array_key_exists(self::CONTENT_KEY, $attributes))
						$tagString .= '>'.$attributes[self::CONTENT_KEY].'</'.$tag.'>';
					elseif ($tag == 'script') {
						$tagString .= '></script>';
					} else {
						$tagString .= ' />';
					}
					
					// Close IE conditional tag
					if ($iESpecific) $tagString .= '<![endif]-->';
					if ($notIE) $tagString .= '<!-- <![endif]-->';
							
					// Cleanup (prevent infinite loop)
					if ($iESpecific) unset($attributes['_ie']);
							
					$tagStrings[] = $tagString;
					
				} while($iESpecific && isset($attributes['_notie'])); // We need a second pass
									
		} //endforeach
		
		$head .= implode("\n", array_unique($tagStrings));

		foreach ($this->strings as $string) {
			$head .= $string;
			$head .= "\n";
		}

		return $head;
	}
}