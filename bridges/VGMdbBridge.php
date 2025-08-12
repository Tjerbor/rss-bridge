<?php

class VGMdbBridge extends BridgeAbstract
{
    const MAINTAINER = 'Tjerbor';
    const NAME = 'VGMdb Bridge';
    const URI = 'https://vgmdb.net/';
    const CACHE_TIMEOUT = 0; // 30min
    const DESCRIPTION = 'Various RSS feeds from VGMdb';

    const ARTIST_PAGE = 'Artist Page';
	
	private $artist = '';
	private $crawled = 0;
	private $crawl_limit;

    // Using a specific context because I plan to expand this soon
    const PARAMETERS = [
        'Artist Page' => [
            'artist_url' => [
                'name' => 'Link to artist page',
                'type' => 'text',
                'required' => true,
                'title' => 'Example: https://vgmdb.net/artist/33535',
                //'pattern' => '^(https:\/\/)?(www.)?vgmdb\.net\/artist\/\d+\$',
                'exampleValue' => 'https://vgmdb.net/artist/33535'
            ],
			'album_crawl_limit' => [
				'name' => 'Album crawl limit',
				'type' => 'number',
				'required' => false,
				'title' => 'Number of maximum crawled Albums sorted by recency. Defaults to 1000.',
				'defaultValue' => 1000,
			],
			'high_details' => [
				'name' => 'High detail crawl',
				'type' => 'checkbox',
				'required' => false,
				'title' => 'Crawls individual album pages including cover. WARNING: Takes significantly longer.',
				'defaultValue' => 'unchecked',
			],
        ],
    ];

    private function collectReleases($url)
    {
        $html = getSimpleHTMLDOMCached($url, self::CACHE_TIMEOUT);
		$artist_name = $html->find('title',0)->plaintext;
		$artist_name = str_replace(' - VGMdb','',$artist_name);
		
		$year_rows = $html->find('div[id="discotable"]',0)->find('tbody[class]');
		
		for($i = sizeof($year_rows)-1; $i >= 0; $i--){
			$year_row = $year_rows[$i];
			$year = $year_row->find('tr[rel="year"]',0)->find('h3',0)->plaintext;
			$releases = $year_row->find('tr[rel^="|r"]');
			for($j = sizeof($releases)-1; $j >= 0 and $this->crawled < $this->crawl_limit; $j--){
				$release = $releases[$j];
				$month_date = $release->find('.label',0)->plaintext;
				$month_date = str_replace('??','01',$month_date);
				$date = $year . '.' . $month_date;
				$date = str_replace('.','-',$date);
				//YYYY-MM
				if(strlen($date) == 7){
					$date .= '-01';
				}
				$release_a = $release->find('a[title]',0);
				
				$title = $release_a->getAttribute('title');
				$release_uri = $release_a->getAttribute('href');
				
				$item['title'] = $title;
				$item['timestamp'] = $date;
				$item['uri'] = $release_uri;
				
				if($this->getInput('high_details')){
					$html = getSimpleHTMLDOMCached($release_uri, self::CACHE_TIMEOUT);
					$image_url_style = $html->find('#coverart',0)->getAttribute('style');
					
					preg_match('/^.*\(\'(.*)\'\)$/', $image_url_style, $matches);
					$image_src = $matches[1];
					
					if($image_src == '/db/img/album-nocover-medium.gif')
					{
						$image_src= 'https://vgmdb.net' . $image_src;
					}
					
					$item['content'] = '<img src="' . $image_src . '"></img>';
					$item['content'] .= $html->find('#innermain',0);
					$item['enclosures'] = [$image_src];
					
					$credits_rows = $html->find('#collapse_credits',0)->find('.maincred');
					
					$artists = '';
					
					foreach($credits_rows as $credit_row)
					{
						try{
							$alt_namesss = $credit_row->children(1);
							$alt_names = $alt_namesss->find('.artistname[style$="none"]');				
							
							if(!is_null($alt_names)){
								foreach($alt_names as $alt_name)
								{
									$alt_name->remove();
								}
							}
						} catch (Exception $e){}
						catch (\Error $er) {}
						$line_of_artist = trim(str_replace('/','',$credit_row->find('td[width="100%"]',0)->plaintext));
						
						$artists .= ', ' . $line_of_artist;
					}
					
					$artists = trim($artists);
					
					$artists_array = array_unique(explode(', ', $artists));
					$empty_string_location = array_search('',$artists_array);
					unset($artists_array[$empty_string_location]);
					$item['author'] = implode(', ', $artists_array);
				} else {
					$content_text = $release->find('td[valign="top"]',1);
					$item['content'] = $content_text;
					$item['author'] = $artist_name;
				}
				
				$this->items[] = $item;
				$this->crawled++;
			}
			
			
		}
	}
	
	private function from_array_to_set($array)
	{
		
	}
	
	private function populate_artist($url)
	{
		$html_artist = getSimpleHTMLDOMCached($url, self::CACHE_TIMEOUT);
		$this->artist = $html_artist ->find('span[style="font-family: Arial, sans-serif; font-size: 1.5em; font-weight: bold; letter-spacing: -1px; color: #BEB993;"]',0)->plaintext;
	}
	
	public function getName(){
		switch ($this->queriedContext) {
            case self::ARTIST_PAGE:
				if(empty($this->artist)){
					$this->populate_artist($this->getInput('artist_url'));
				}
				return $this->artist;
				break;
			default:
				return self::NAME;
		}
	}
	
	public function getURI()
	{
		switch ($this->queriedContext) {
            case self::ARTIST_PAGE:
				return $this->getInput('artist_url');
				break;
			default:
				return self::URI;
		}
	}


    public function collectData()
    {
        switch ($this->queriedContext) {
            case self::ARTIST_PAGE:
				$this->crawl_limit = $this->getInput('album_crawl_limit');
                $this->collectReleases($this->getInput('artist_url'));
                break;

            default:
                throw new Exception('Invalid context', 1);
            break;
        }
    }
}
