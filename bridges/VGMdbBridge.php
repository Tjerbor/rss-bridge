<?php

class VGMdbBridge extends BridgeAbstract
{
    const MAINTAINER = 'Tjerbor';
    const NAME = 'VGMdb Bridge';
    const URI = 'https://vgmdb.net/';
    const CACHE_TIMEOUT = 0; // 30min
    const DESCRIPTION = 'Various RSS feeds from VGMdb';

    const ARTIST_PAGE = 'Artist Page';

    // Using a specific context because I plan to expand this soon
    const PARAMETERS = [
        $ARTIST_PAGE => [
            'artist_url' => [
                'name' => 'Link to artist page',
                'type' => 'text',
                'required' => true,
                'title' => 'Example: https://vgmdb.net/artist/33535',
                'pattern' => '^(https:\/\/)?(www.)?vgmdb\.net\/artist\/\d+\$',
                'exampleValue' => 'https://vgmdb.net/artist/33535'
            ],
        ],
    ];

    private function collectReleases($url)
    {
        $html = getSimpleHTMLDOMCached($artist_url, self::CACHE_TIMEOUT);
		$artist_name = $html->find('title',0)->plaintext;
		$artist_name = str_replace($artist_name,' - VGMdb','');
		
		$year_rows = $html->find('div[id="discotable"]',0)->find('tbody[class]');
		
		foreach($year_rows as $year_row){
			$year = $year_row->find('tr[rel="year"]',0)->find('h3',0)->plaintext;
			$releases = $year_row->find('tr[rel^="|r"]');
			foreach($releases as $release){
				$month_date = $release->find('.label',0)->plaintext;
				$date = $year + '.' + $month_date;
				if(strlen($date) == 7){ //YYYY.MM
					$date += '.01';
				}
				$release_a = $release->find('a[title]',0);
				
				$title = $release_a->getAttribute('title');
				$release_uri = $release_a->getAttribute('href');
				
				$content_text = $release->find('td[valign="top"]',0)->plaintext;
				
				$item['title'] = $title;
				$item['timestamp'] = $date;	
				$item['uri'] = $release_uri;
				$item['author'] = $artist_name;
				$item['content'] = '<p>'
				. $content_text
				. </p>;
				$this->items[] = $item;
			}
			
			
		}

    public function collectData()
    {
        switch ($this->queriedContext) {
            case self::ARTIST_PAGE:
                $this->collectReleases($this->getInput('artist_url'));
                break;

            default:
                throw new Exception('Invalid context', 1);
            break;
        }
    }
}
