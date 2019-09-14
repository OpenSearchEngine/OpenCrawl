<?php
namespace App\Services;

use \Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;
use ogp\Parser;
use Opengraph\Reader;
use DonatelloZa\RakePlus\RakePlus;

class PageService{

    private $crawler;

    public $url;
    public $image;
    public $dom;

    function __construct(string $url){
        $response = (new Client())->request('GET', 'http://puppiteer.the4.net/?url='.urlencode($url));
        $data = json_decode($response->getBody(), true);
        $this->url = $url;
        $this->image = $data['screenshot'];
        $this->dom = $data['html'];
        $this->crawler = new Crawler($this->dom, null, $url);
    }

    function getTitle(){
        $title = substr(trim($this->crawler->filter('title')->text()), 0, 60);
        if(!$title){
            $title = substr(preg_replace('!\s+!', ' ', trim($this->extractCurrentText($this->crawler))), 0, 60);
        }
        return $title;
    }

    function getDescription(){
        $data = $this->crawler->filterXpath("//meta[@name='description']")->extract(array('content'));
        if(count($data) == 0){
            $description = substr(preg_replace('!\s+!', ' ', trim($this->extractCurrentText($this->crawler))), 0, 200);
        }else{
            $description = substr(preg_replace('!\s+!', ' ', trim($data[0])), 0, 200);
        }
        return $description;
    }

    function getOpenGraph(){
        $reader = new Reader();
        return $reader->parse($this->dom)->getArrayCopy();
    }

    function getPhrases(){
        $pageText = preg_replace('!\s+!', ' ', $this->extractCurrentText($this->crawler));
        $allPhrases = RakePlus::create($pageText, 'en_US', 10)->sortByScore('desc')->scores();
        $filteredPhrases = [];
        $phraseCount = count($allPhrases);
        $phraseScore = 0;
        foreach($allPhrases as $phrase){
            $phraseScore += $phrase;
        }
        $phraseAverage = ($phraseScore == 0) ? 0 : $phraseScore / $phraseCount;
        $phraseCeil = $phraseAverage * 0.95;
        $phraseFloor = $phraseAverage * 0.15;
        $keepPhrases = $phraseCount * 0.2;
        foreach($allPhrases as $phrase => $score){
            if($score > $phraseFloor && $score < $phraseCeil && count($filteredPhrases) < $keepPhrases){
                $filteredPhrases[$phrase] = $score;
            }
        }

        return $filteredPhrases;
    }

    function getLinks(){
        $seenLinks = [];
        $links = [];
        $this->crawler->filter('a')->each(function($node) use(&$links, &$seenLinks){
            $href = $node->attr('href');
            if($href != '#' && $href != '' && !\Illuminate\Support\Str::startsWith(strtolower($href), 'javascript')){
                $link = explode('#', $this->rel2abs(trim($href), $this->url))[0];
                $data = [
                    'href' => $link,
                    'title' => trim($node->attr('title')),
                    'text' => trim($node->text())
                ];
                if(!in_array($link, $seenLinks) && strlen($link) < 200){
                    $seenLinks[] = $link;
                    $links[] = $data;
                }
            }
        });

        return $links;
    }

    function getImages(){
        $seenImages = [];
        $images = [];
        $this->crawler->filter('img')->each(function($node) use(&$images, &$seenImages){
            $src = $node->attr('src');
            if($src != '#' && $src != '' && !\Illuminate\Support\Str::startsWith(strtolower($src), 'javascript')){
                $image = explode('#', $this->rel2abs(trim($src), $this->url))[0];
                $data = [
                    'href' => $image,
                    'alt' => trim($node->attr('alt')),
                ];
                if(!in_array($image, $seenImages) && strlen($image) < 200){
                    $seenLinks[] = $image;
                    $images[] = $data;
                }
            }
        });

        return $images;
    }

    private function extractCurrentText(){
        $clone = new Crawler();
        $clone->addHTMLContent($this->crawler->html(), "UTF-8");
        $clone->filter("style")->each(function(Crawler $crawler) {
            foreach($crawler as $node){
                $node->parentNode->removeChild($node);
            }
        });
        $clone->filter("script")->each(function(Crawler $crawler) {
            foreach($crawler as $node){
                $node->parentNode->removeChild($node);
            }
        });
        return $clone->text();
    }

    private function rel2abs($rel, $base)
    {

        /* return if already absolute URL */
        if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;

        /* queries and anchors */
        if ($rel[0]=='#' || $rel[0]=='?') return $base.$rel;

        /* parse base URL and convert to local variables:
           $scheme, $host, $path */
        extract(parse_url($base));

        /* remove non-directory element from path */
        if(!empty($path)){
            $path = preg_replace('#/[^/]*$#', '', $path);
        } else {
            $path = '';
        }

        /* destroy path if relative url points to root */
        if ($rel[0] == '/') $path = '';

        /* dirty absolute URL */
        $abs = "$host$path/$rel";

        /* replace '//' or '/./' or '/foo/../' with '/' */
        $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
        for($n=1; $n>0; $abs=preg_replace($re, '/', $abs, -1, $n)) {}

        if(\Illuminate\Support\Str::startsWith($rel, '//')){
            return $scheme.':'.$rel;
        }
        /* absolute URL is ready! */
        return $scheme.'://'.$abs;
    }


}