<?php

/**
 * Pagenation
 *
 * Created by Victor Puhnyuk
 *
 */

namespace Jump\helpers;
 
class Pagenation
{
	/**
	 * Create and show pagenation block
	 *
	 * @param int $currentPageNumber number of current page
	 * @param int $all count of all items
	 * @param int $perPage count items on one page
	 * @param string $idStyle pagenation block style class
	 *
	 * @return void
	 * 
	 */
	public function run($currentPageNumber, $all, $perPage, $filters = NULL, $idStyle = 'pagenation')
	{//var_dump(func_get_args());exit;
		$this->currentPageNumber = $currentPageNumber;
		$html = '';
		// Узнаем сколько всего страниц
		$countPages = ($perPage > $all) ? 1 : ceil($all / $perPage);
		if($countPages == 1) return false;
		
		if($countPages > 10){
			$start = $currentPageNumber <= 4 ? 2 : $currentPageNumber - 2;
			$end = $currentPageNumber >= $countPages - 3 ? $countPages - 1 : $currentPageNumber + 2;
		}
		
		$html = "<div class=\"clearfix\"></div><ul id=\"{$idStyle}\">";
		if(!isset($start))
		{
			foreach(range(1, $countPages) as $i){
				$html .= $this->getLink($i);
			}
		}
		else
		{
			$html .= $this->getLink(1) . ($currentPageNumber > 4 ? $this->getLink($start - 1, true) : ' ');
			
			foreach(range($start, $end) as $i){
				$html .= $this->getLink($i);
			}
			
			$html .= ($currentPageNumber > $countPages - 4 ? '' : $this->getLink($end + 1, true)); 
			$html .= $this->getLink($countPages);
		}
		$html .= '</ul>';//exit;
		return $html;
	}
	
	
	/**
	 * Helper for show links
	 *
	 * @param int $i number in itepation pages
	 * @param int $currentPageNumber number of current page
	 * @param int $ellipsis substitute for ellipsis link name
	 *
	 * @return void
	 * 
	 */
	private function getLink($i, $ellipsis = NULL){
		$activePageLink = 'javascript:void(0);" class="active-page-link"';
		$link = $activePageLink;
		if($this->currentPageNumber == 1){
			$link = $i == 1 ? $activePageLink : FULL_URL . "page/{$i}/";
		}else{
			if($this->currentPageNumber != $i){
				$replace = $i != 1 ? "/page/{$i}/" : '/';
				$link = str_replace("/page/{$this->currentPageNumber}/", $replace, FULL_URL);
			}
		}
		return '<li><a href="'.$link.'">'.($ellipsis ? ' ... ' : $i).'</a></li>';
	}
}

