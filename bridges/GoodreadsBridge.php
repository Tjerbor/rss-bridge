<?php

class GoodreadsBridge extends BridgeAbstract
{
    const MAINTAINER = 'Tjerbor';
    const NAME = 'Goodreads Bridge';
    const URI = 'https://www.goodreads.com/';
    const CACHE_TIMEOUT = 0; // 30min
    const DESCRIPTION = 'Various RSS feeds from Goodreads';

    const CONTEXT_AUTHOR_BOOKS = 'Books by Author';
	
	private $author = '';

    // Using a specific context because I plan to expand this soon
    const PARAMETERS = [
        'Books by Author' => [
            'author_url' => [
                'name' => 'Link to author\'s page on Goodreads',
                'type' => 'text',
                'required' => true,
                'title' => 'Should look somewhat like goodreads.com/author/show/',
                'pattern' => '^(https:\/\/)?(www.)?goodreads\.com\/author\/show\/\d+\..*$',
                'exampleValue' => 'https://www.goodreads.com/author/show/38550.Brandon_Sanderson'
            ],
            'published_only' => [
                'name' => 'Show published books only',
                'type' => 'checkbox',
                'required' => false,
                'title' => 'If left unchecked, this will return unpublished books as well',
                'defaultValue' => 'checked',
            ],
			'maximum_pages' => [
				'name' => 'Maximum page crawling',
				'type' => 'number',
				'required' => false,
				'title' => 'If left empty defaults to 3.',
				'defaultValue' => 3,
			],
			'high_details' => [
				'name' => 'High detail crawl',
				'type' => 'checkbox',
				'required' => false,
				'title' => 'Crawls individual books pages including full publishing date, higher resolution cover, tags, and description. WARNING: Takes significantly longer.',
				'defaultValue' => 'unchecked',
			],
        ],
    ];

    private function collectAuthorBooks($url)
    {
        $regex = '/goodreads\.com\/author\/show\/(\d+)/';

        preg_match($regex, $url, $matches);

        $authorId = $matches[1];

        $authorListUrl = "https://www.goodreads.com/author/list/$authorId?sort=original_publication_year";

        $html = getSimpleHTMLDOMCached($authorListUrl, self::CACHE_TIMEOUT);
		
		$pages_a = $html->find('div[style="float: right"]',0)->find('a[href]');
		
		$pages = 1;
		if (sizeof($pages_a) > 0){
			$index = (int) sizeof($pages_a) - 2;
			$pages_text = $pages_a[$index]->plaintext;
			$pages = (int)$pages_text;
		}

		for($i = 1; $i <= $pages and $i <= $this->getInput('maximum_pages'); $i++){
			$authorListUrl = "https://www.goodreads.com/author/list/$authorId?page=$i&sort=original_publication_year&utf8=✓";
			
			if($i > 1){
				$html = getSimpleHTMLDOMCached($authorListUrl, self::CACHE_TIMEOUT);
			}
			
			foreach ($html->find('tr[itemtype="http://schema.org/Book"]') as $row) {
				$book_url = $row->find('.bookTitle', 0)->getAttribute('href');
				
				if($this->getInput('high_details')){
					$this->add_single_book_item('https://www.goodreads.com' . $book_url);
				} else {
					$item['uri'] = $book_url;
					$dateSpan = $row->find('.uitext', 0)->plaintext;
					$date = null;
	
					// If book is not yet published, ignore for now
					if (preg_match('/published\s+(\d{4})/', $dateSpan, $matches) === 1) {
						// Goodreads doesn't give us exact publication date here, only a year
						// We are skipping future dates anyway, so this is def published
						// but we can't pick a dynamic date either to keep clients from getting
						// confused. So we pick a guaranteed date of 1st-Jan instead.
						$date = $matches[1] . '-01-01';
					} elseif ($this->getInput('published_only') !== 'checked') {
						// We can return unpublished books as well
						$date = date('Y-01-01');
					} else {
						continue;
					}
	
					$row = defaultLinkTo($row, $this->getURI());
	
					$item['title'] = $row->find('.bookTitle', 0)->plaintext;
					$item['author'] = $row->find('.authorName', 0)->plaintext;
					
					$item['content'] = '<a href="'
					. $row->find('.bookTitle', 0)->getAttribute('href')
					. '"><img src="'
					. $row->find('.bookCover', 0)->getAttribute('src')
					. '"></a>';
					$item['timestamp'] = $date;
					$item['enclosures'] = [
					$row->find('.bookCover', 0)->getAttribute('src')
					];
					
					$this->items[] = $item; // Add item to the list	
				}
				
			}
			
		}
    }
	
	private function add_single_book_item($book_url)
	{
		$html = getSimpleHTMLDOMCached($book_url, self::CACHE_TIMEOUT);
		
		$item['uri'] = $book_url;
		$item['title'] = $html->find('h1[data-testid="bookTitle"]',0)->plaintext;
		
		//try {
		//	$is_not_published = $html->find('div[class="Label Label__generic"]',0)->find('span',0)->plaintext;
		//	$if($is_not_published == 'Not yet published')
		//		
		//} catch (Exception $e){
		//	
		//}
		
		$descListItems = $html->find('.EditionDetails',0);
		$descListItems = $descListItems->find('.DescListItem');
		foreach($descListItems as $descListItem)
		{
			$case_str =  $descListItem->find('dt')->plaintext;
			if($case_str == 'Published' or $case_str == 'Expected publication')
			{
				$date_unrefinded = $descListItem->find('div[data-testid="contentContainer"]',0)->plaintext;
				preg_match('/^(.*) [b][y].*$/',$date_unrefinded,$matches);
				$date = strtotime($matches[1]);
				$item['timestamp'] = $date;
			}
		}
		
		//$page_json = json_decode($html->find('script[type="application/ld+json"]',0)->plaintext);
		//$author_json = $page_json->author;
		//$authors = '';
		//for($i = 0; $i < sizeof($author_json); $i++)
		//{
		//	if($i == 0){
		//		$authors .= $author_unrefined->name;
		//	} else {
		//		$authors .= ', ' . $author_unrefined->name;
		//	}
		//}		
		//
		//$item['author'] = $authors;
		//
		//$cover = $html->find('.BookPage__leftColumn',0)->find('img',0)->getAttribute('src');
		//$item['enclosures'] = [$cover];
		//$item['content'] = '<img src="' . $cover . '"/></br>';
		//
		//$description = $html->find('.DetailsLayoutRightParagraph',0)->text;
		//$item['content'] .= $description;
		
		
		//try {
		//	$genres = findKeyInJsonObject('bookGenres',$page_json);
		//	for($i = 0; $i < sizeof($genres); $i++){
		//		if($i == 0){
		//			$item['categories'] = genres[$i]['genre']['__typename'];
		//		} else {
		//			$item['categories'] .= genres[$i]['genre']['__typename'];
		//		}
		//	}
		//} catch(Exception $e) {}
		
		$this->items[] = $item; // Add item to the list	
	}
	
	private function populate_author($url)
	{
		$html_author = getSimpleHTMLDOMCached($url, self::CACHE_TIMEOUT);
		$this->author = $html_author->find('span[itemprop="name"]',0)->plaintext;
	}
	
	/**
	 * Function to find a key in a JSON object.
	 *
	 * @param string $key The key to search for.
	 * @param string $json The JSON object to search in.
	 *
	 * @return mixed|null The value associated with the key if found, null otherwise.
	 */
	private function findKeyInJsonObject($key, $json) {
    // Decode the JSON object into an associative array.
    $data = json_decode($json, true);

    // Use recursive function to search for the key.
    $result = findKeyRecursive($key, $data);

    return $result;
}

	/**
	* Recursive function to search for a key in an associative array.
	*
	* @param string $key The key to search for.
	* @param array $array The associative array to search in.
	*
	* @return mixed|null The value associated with the key if found, null otherwise.
	*/
	private function findKeyRecursive($key, $array) {
		// Loop through each element in the array.
		foreach ($array as $k => $value) {
			// If the current key matches the search key, return the value.
			if ($k === $key) {
				return $value;
			}
	
			// If the current value is an array, recursively search for the key.
			if (is_array($value)) {
				$result = findKeyRecursive($key, $value);
	
				// If the key is found in the nested array, return the result.
				if ($result !== null) {
					return $result;
				}
			}
		}
	
		// Key not found, return null.
		return null;
	}
	
	public function getName()
	{
		switch($this->queriedContext){
			case 'Books by Author':
				if(empty($this->author)){
					$this->populate_author($this->getInput('author_url'));
				}
				return $this->author;
				break;
			case 'By user ID':
				break;
			default: return self::NAME;
		}
	}

    public function collectData()
    {
        switch ($this->queriedContext) {
            case self::CONTEXT_AUTHOR_BOOKS:
                $this->collectAuthorBooks($this->getInput('author_url'));
                break;

            default:
                throw new Exception('Invalid context', 1);
            break;
        }
    }
}