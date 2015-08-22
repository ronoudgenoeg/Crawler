<?php
require_once 'BiedVeilingAuction.php';
/**
 * This is the data getter for the online betting site biedveilingen.nl
 * 
 * This getter basically functions as an API for a website without an API.
 * This is done with a combination of crawling and using functions used by them for ajax requests.
 * 
 * Incase data starts looking weird or incorrect, this could be due to a multitude of reasons:
 * - The navigation of the site changed, not allowing us to correctly get to product pages.
 * - The html class which holds the ids for auctions changed.
 * - The html attribute which holds the actual id in the above class changed.
 * - The return string from the post request we make has changed.
 * - The required parameters for the post request changed.
 * 
 * @author Ron Oudgenoeg
 * @version 0.1
 */
class BiedVeilingDataGetter {
	/* Site specific values */
	protected $_auctionItemClass = 'auction-item';
	protected $_auctionItemIdAttribute = 'title';
	protected $_postRequestIdentifier = 'auctions=';
	protected $_postRequestAuctionIdentifier = 'A|';
	protected $_domain = 'http://biedveilingen.nl/';
	protected $_postRequestUrl = 'http://biedveilingen.nl/getstatus.php?ms=';
	
	/* Object variables */
	/** @var DOMDocument $_dom */
	protected $_dom;

	/** @var DOMXPath $_xpath */
	protected $_xpath;
	
	public function __construct() {
		$this->_setUpDom($this->_domain);
	}
	
	public function getItems() {
		$urls = $this->_getUrls();
		$ids = array();
		foreach($urls['categories'] as $categoryUrl) {
			$this->_setUpDom($this->_domain . $categoryUrl);
			$ids = array_merge($ids, $this->_getIds());
		}
		$data = $this->_getAuctionItemData($ids);
		return $this->_createItems($data);
	}
	
	/**
	 * Sets up the dom to be used for crawling data.
	 */
	protected function _setUpDom($url) {
		$this->_dom = new DOMDocument('1.0');
		@$this->_dom->loadHTMLFile($url); 
		$this->_xpath = new DOMXPath($this->_dom);
	}
	
	/**
	 * Gets the auction ids
	 * 
	 * @return DOMNodeList
	 */
	protected function _getIds() {
		$auctionItems = $this->_xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $this->_auctionItemClass ')]");
		$ids = array();
		foreach($auctionItems as $auctionItem) {
			$ids[] = $auctionItem->getAttribute($this->_auctionItemIdAttribute);
		}
		return $ids;
	}
	
	/**
	 * Gets various urls used by this getter for certain tasks. 
	 * 
	 * @return array Returns an array of different type of urls. Contains the following:
	 * array['categories']
	 */
	protected function _getUrls() {
		$allUrls = $this->_dom->getElementsByTagName('a');
		$urls = array(
			'categories' => array(),
		);
		foreach($allUrls as $url) {
			$relativeUrl = str_replace($this->_domain, '', $url->getAttribute('href'));
			if(strlen($relativeUrl) < 1) {
				continue;
			}
			if(strpos($relativeUrl, 'categories')) {
				$urls['categories'][] = $relativeUrl;
			}
		}
		return $urls;
	}
	
	/**
	 * Gets the auction data for the given IDs. 
	 * 
	 * @param array $ids
	 * @return array:
	 */
	protected function _getAuctionItemData(array $ids) {
		$data = array('auctions' => $ids);
		$url = $this->_postRequestUrl . round(microtime(true) * 1000);
		$options = array(
				'http' => array(
						'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
						'method'  => 'POST',
						'content' => $this->_postRequestIdentifier . implode(',', $ids),
				),
		);
		$context  = stream_context_create($options);
		$result = file_get_contents($url, false, $context);
		$items = explode($this->_postRequestAuctionIdentifier, $result);
		array_shift($items);
		$itemsAsArray = array();
		foreach($items as $item) {
			$itemsAsArray[] = explode('|', $item);
		}
		return $itemsAsArray;
	}
	
	protected function _createItems($itemsAsArray) {
		$itemObjects = array();
		foreach($itemsAsArray as $itemArray) {
			$price = substr($itemArray[4], strpos($itemArray[4], ';') + 1);
			$timeLeft = $itemArray[11];
			
			$item = new BiedVeilingAuction();
			$item->setTimeLeft($timeLeft);
			$item->setCurrentPrice($price);
			$itemObjects[] = $item;
		}
		return $itemObjects;
		
	}
}