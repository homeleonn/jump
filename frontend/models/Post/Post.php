<?php

namespace frontend\models\Post;

use Jump\Model;
use Jump\helpers\Filter;
use Jump\helpers\Common;
use Jump\DI\DI;
use frontend\models\Post\Taxonomy;

class Post extends Model{
	use \Jump\helpers\CurrentWork;
	use \Jump\traits\PostTrait;
	
	private $id;
	private $url;
	private $title;
	private $content;
	private $tags;
	private $post_type;
	private $parent;
	private $autor;
	private $status;
	private $comment_status;
	private $comment_count;
	private $visits;
	private $created;
	private $modified;
	
	//private $options;
	private $filters;
	private $limit;
	private $page;
	private $start;
	private $allItemsCount;
	private $select = 'Select * from posts where ';
	private $relationship = 'posts p, terms t, term_taxonomy tt, term_relationships tr where t.id = tt.term_id and tt.term_taxonomy_id = tr.term_taxonomy_id and p.id = tr.object_id ';
	private $relationships = 'posts p LEFT JOIN term_relationships tr ON(p.id = tr.object_id) LEFT JOIN term_taxonomy tt ON(tt.term_taxonomy_id = tr.term_taxonomy_id) LEFT JOIN terms t ON(t.id = tt.term_id)';
	
	public function __construct(DI $di, Taxonomy $taxonomy){
		parent::__construct($di);
		$this->taxonomy = $taxonomy;
	}
	
	public function setOptions($options){
		//$this->options = $options;
		var_dump(Options::get('title'));exit;
	}
	
	public function single($url, $id = NULL, $pageTypes = NULL){//var_dump($url, $pageTypes, Options::get('type'));
		return $id ? $this->getPostById($id) : $this->getPostByUrl($url, $pageTypes);
	}
	
	public function getPostById($id){
		return $this->db->getRow($this->select . 'id = ?i', $id);
	}
	
	public function getPostByUrl($url, $pageTypes = NULL){
		$pageTypes = implode($pageTypes ?: [Options::get('type')]);
		$sqlPageTypes = $pageTypes ? ' and post_type IN('.$pageTypes.')' : '';
		return $this->db->getRow($this->select . 'url = ?s ' . $sqlPageTypes, $url);
	}
	
	public function getChildrens($parentId){
		return $this->db->getAll($this->select . 'parent = ?i', (int)$parentId);
	}
	
	public function getPostsByFilters($filters, $postType){
		$sqlFilters = '';
		// Соберем таксономии и проверим их валидность
		// Если найдутся невалидные, нужно вырезать из запроса данный фильтр и перенаправить
		// Если валидных меньше чем присланных, проходимся по валидным, сверяемся с присланными, сохраняем невалидные присланные, формируем и режем
		$validFilters = $this->taxonomyValidation($filters, $postType);
		
		$filtersAsString = Filter::stringFromFilters($filters);
		$validFiltersAsString = Filter::stringFromFilters($validFilters);
	
		//var_dump($filters, $validFilters, $filtersAsString, $validFiltersAsString);
		
		if(strcmp($filtersAsString, $validFiltersAsString) !== 0){var_dump(1);exit;
			$this->request->location(str_replace($filtersAsString . (!$validFiltersAsString ? '/' : ''), $validFiltersAsString, FULL_URL), 301);
		}
		// Формируем условие из валидных фильтров
		foreach($validFilters as $taxonomy => $slugs){
			$sqlFilters .= " (tt.taxonomy = '{$postType}-{$taxonomy}' and t.slug IN('" . str_replace(',', "','", $slugs) . "')) OR";
		}
		
		$query = $this->select . 'id IN(Select DISTINCT p.id from ' . $this->relationship . ' and ('.substr($sqlFilters, 0, -3).')) and post_type = \''.$postType.'\' order by id DESC';
		$this->checkInLimit($query, []);
		$data = $this->db->getAll($query . $this->limit);
		//var_dump($q, $data);exit;
		return $data;
	}
	
	// Поиск валидных фильтров и их значений из присланных
	private function taxonomyValidation($filters, $postType){
		var_dump($postType);
		$type = Options::get('type') . '-';
		$taxonomies = '';
		
		// Формируем условие из фильтров и их значений
		// Узнаем ваилидные фильтры, и валидные значения
		foreach($filters as $taxonomy => $values){
			$taxonomies .= "(tt.taxonomy = '{$type}{$taxonomy}' AND t.slug IN('" . str_replace(',', "','", $values) . "')) OR ";
		}
		
		$validTaxonomies = $this->db->getAll('Select DISTINCT tt.taxonomy as filter, t.slug as value from term_taxonomy as tt, terms as t where tt.term_taxonomy_id = t.id and ' . substr($taxonomies, 0, -3));
		
		return $this->creatingValidFilters($validTaxonomies, $postType);
	}
	
	// Форматирование новых валидных фильтров и их значеий
	private function creatingValidFilters($validFilters, $postType){
		$newValidFilters = [];
		
		foreach($validFilters as $filter){
			$newValidFilters[str_replace($postType . '-', '', $filter['filter'])][] = $filter['value'];
		}
		
		foreach($newValidFilters as $filters => $values){
			$newValidFilters[$filters] = implode(',', $values);
		}
		
		return $newValidFilters;
	}
	
	public function getPostList($taxonomy, $value){var_dump(func_get_args());exit;
		// Проверим есть ли вообще такой термин
		if(!$termName = $this->checkTermExists($taxonomy, $value)) 
			return 0;
		$query = $this->select . 'id IN(Select DISTINCT p.id from ' . $this->relationship . ' and t.slug = ?s and tt.taxonomy = ?s) and post_type = \''.Options::get('type').'\' order by id DESC';
		$post = $this->getAll($query, [$value, $taxonomy]);
		$post['termName'] = $termName;
		return $post;
	}
	
	public function getPostTerms($where){
		if(!$where) return false;
		return $this->db->getAll('Select t.*, tt.* from ' . $this->relationship . $where);
	}
	
	public function getPostsByPostType($type){
		$query = $this->select . 'post_type = ?s order by created DESC';
		return $this->db->getAll($query, $type);
		//return $this->getAll($query, [$type]);
	}
	
	private function checkTermExists($taxonomy, $value){
		return $this->db->getOne('Select t.name from terms as t, term_taxonomy as tt where t.id = tt.term_id and t.slug = ?s and tt.taxonomy = ?s', $value, $taxonomy);
	}
	
	public function getTermNameByTermSlug($slug){
		return $this->db->getOne('Select name from terms where slug = ?s LIMIT 1', $slug);
	}
	
	public function getPostsBysTermsTaxonomyIds($termsTaxonomyIds){
		//$query = 'Select p.*, t.id as term_id, t.name, t.slug, tt.* from ' . $this->relationship . 'and tt.term_taxonomy_id IN(?a) group by p.id order by p.created DESC, t.name ASC';
		$query = 'Select distinct p.* from ' . $this->relationship . 'and tt.term_taxonomy_id IN(?a) group by p.id order by p.created DESC';
		return $this->db->getAll($query, [$termsTaxonomyIds]);
		//return $this->getAll($query, [$termsTaxonomyIds]);
	}
	
	
	public function getMeta($postId){
		return $this->metaProcessing($this->db->getAll('Select meta_key, meta_value from postmeta where post_id = ?i', $postId));
	}
	
	private function metaProcessing($meta){
		if(!$meta) return false;
		$metaNew = [];
		foreach($meta as $m)
			$metaNew[$m['meta_key']] = $m['meta_value'];
		return $metaNew;
	}
	
	private function checkInLimit($query, $params){
		$this->allItemsCount = (int)call_user_func_array([$this->db, 'getOne'], array_merge([str_replace('Select *', 'Select COUNT(*) as count', $query)], $params));
		if($this->allItemsCount && $this->allItemsCount <= $this->start){
			$this->request->location(preg_replace('~page/\d+/?~', '', FULL_URL));
		}
	}
	
	private function getAll($query, $params){
		$this->checkInLimit($query, $params);
		$data = call_user_func_array([$this->db, 'getAll'], array_merge([$query . $this->limit], $params));
		return $data;
	}
	
	public function getAllItemsCount(){
		return $this->allItemsCount;
	}
	
	public function getFiltersHTML($taxonomies, $postType, $postSlug){
		if(!$taxonomies) return false;
		if(!$terms = $this->db->getAll('Select DISTINCT t.*, tt.* from ' . $this->relationship . ' and p.post_type = \''.$postType.'\' and tt.taxonomy IN(\'' . implode("','", $taxonomies) . '\')')) return false;
		
		return $this->getFiltersHTMLForList($terms, $postSlug);
	}
	
	private function getFiltersHTMLForList($terms, $postSlug){
		$tmpTaxonomy = $terms[0]['taxonomy'];
		foreach($terms as $key => $term){
			$key1 = Options::get('taxonomy')[$term['taxonomy']]['title'];
			if(!isset($html[$key1])) $html[$key1] = '';
			$link = SITE_URL . $this->getArchiveSlug() . "{$term['taxonomy']}/{$term['slug']}/";
			$html[$key1] .= $this->setTermLinkHelper($link, $term['name'], $term['count']) . '<br>';
		}
		foreach($html as $tax => $h) 
			$html[$tax] = '<div class="filters"><div class="title">' . $tax . '</div><div class="content">' . $h . '</div></div>';
		
		return implode('', array_merge(['all' => '<a href="'. SITE_URL . $this->getArchiveSlug() . '">Все</a><br>'], $html));
	}
	
	public function getTermsByPostId($postId, $taxonomies = false){
		
		if(!$terms = $this->getTaxonomies($postId)) return false;
		$html = [];
		if($terms){
			foreach($terms as $key => $term){
				if(!isset($html[$term['taxonomy']])) $html[$term['taxonomy']] = ($key ? '<br>' : '') . Options::get('taxonomy')[$term['taxonomy']]['title'] . ': ';
				$html[$term['taxonomy']] .= "<a href='" . SITE_URL . $this->getArchiveSlug() . "{$term['taxonomy']}/{$term['slug']}/'>{$term['name']}</a>, ";
			}
			foreach($html as &$h) 
				$h = substr($h, 0, -2);
		}
		return implode('', $html);
	}
	
	public function getTaxonomies($postId){
		if(!Options::get('taxonomy')) return false;
		$this->terms = isset($this->terms) ? $this->terms : $this->db->getAll('Select t.*, tt.* from terms as t, term_taxonomy as tt, term_relationships as tr where t.id = tt.term_id and tt.term_taxonomy_id = tr.term_taxonomy_id and tr.object_id = ' . $postId . ' and tt.taxonomy IN(\'' . implode("','", array_keys(Options::get('taxonomy'))) . '\')');
		return $this->terms;
	}
	
	
	public function getTermsListByTaxonomy($taxonomy, $delimiter = false, $getCount = true){
		if(!$terms = $this->db->getAll('Select DISTINCT t.*, tt.* from ' . $this->relationship . ' and p.post_type = \''.Options::get('type').'\' and tt.taxonomy = \''.$taxonomy.'\'')) return false;
		$allCount = 0;
		foreach($terms as $t){
			if(!$getCount) $t['count'] = 0;
			$allCount += $t['count'];
			$html[] = $this->setTermLinkHelper(SITE_URL . $this->getArchiveSlug() . "{$t['taxonomy']}/{$t['slug']}/", $t['name'], $t['count']);
		}
		$result = array_merge([$this->setTermLinkHelper(SITE_URL . $this->getArchiveSlug(), 'all')], $html);
		return ($delimiter && is_string($delimiter)) ? (Options::get('taxonomy')[$taxonomy]['title'] . ': ' . implode($delimiter, $result)) : $result;
	}
	
	private function setTermLinkHelper($link, $text, $count = 0){
		$count = $count ? " ({$count})" : '';
		return urldecode(FULL_URL_WITHOUT_PARAMS) == $link ? "<span style='border-bottom: 3px #de1d1d solid;'>{$text}{$count}</span>" : "<a href='{$link}'>{$text}</a>{$count}";
	}
	
	public function getArchiveSlug(){
		return Options::get('has_archive') . (Options::get('has_archive') ? '/' : '');
	}
	
	public function getPostsByTermNames($termNames){
		return $this->db->getAll('Select DISTINCT p.* from ' . $this->relationship . ' and t.name IN(?a)', [$termNames]);
	}
	
	public function getPostsByTaxonomyAndPostType($taxonomy, $postType){
		return $this->db->getAll('Select DISTINCT p.* from ' . $this->relationship . ' and tt.taxonomy = ?s and p.post_type = ?s', $taxonomy, $postType);
	}
}