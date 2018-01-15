<?php

namespace frontend\controllers;

use Jump\Controller;
use Jump\helpers\Filter;
use Jump\helpers\Common;
use Jump\helpers\Pagenation;
use frontend\models\Post\Options;

class PostController extends Controller{
	use \Jump\traits\PostControllerTrait;
	
	private $taxonomyModel;
	
	/**
	 *  @param $di dependency injection container
	 *  @param object $model base model for this controller
	 */
	public function __construct($di, $model){
		parent::__construct($di, $model);
		$this->setOptions();
		//$this->model->setOptions($this->options);
	}
	
	private function setOptions(){
		$this->options = $this->config->getCurrentPageOptions();
		Options::setOptions($this->options);
	}
	
	public function actionIndex(){
		$a = $this->actionSingle(NULL, $this->config->front_page);
		return $a;
	}
	
	/**
	 *  @param string $url
	 *  @param int $id
	 *  
	 *  @return array post
	 */
	public function actionSingle($url, $id = NULL){//var_dump(func_get_args());exit;
		// get all args, but mey be come 'foo/bar/baz/zab/'
		$hierarchy = explode('/', $url);
		// recognize that this it
		//echo 'Валидация урлов иерархии запущена...<br>';
		if($url && count($hierarchy) > 1){
			foreach($hierarchy as $url){
				if(!preg_match('~^'.URL_PATTERN.'$~u', $url)){
					$this->request->location(NULL, 404);
				}
			}
			$id = NULL;
		}
		// get last component from url(url in db), find in posts with this url and if this a front page - return
		$url = array_pop($hierarchy);
		// may be postType exist
		$postTypes = Options::get('type');
		//echo "Запрос записи из базы(урл: $url, айди: $id)...<br>";
		if(!$post = $this->model->single($url, $id, $postTypes)) return 0;
		
		$this->config->setOption('postType', $post['post_type']);
		$this->setOptions();
		
		if($post['id'] == $this->config->front_page){
			if(SITE_URL != FULL_URL_WITHOUT_PARAMS)
				$this->request->location(SITE_URL, 301);
			$post['__model'] = $this->model;
			return $post;
		}
		
		// else
		if(!$this->options['hierarchical']){
			$PostTypeTaxonomies = array_keys(Options::get('taxonomy'));
			$terms = $this->model->taxonomy->getByTaxonomies($PostTypeTaxonomies); 
			$postTerms = $this->model->getPostTerms(' and tr.object_id = ' . $post['id'] . ' and tt.taxonomy IN(\''.implode("','", $PostTypeTaxonomies).'\')');
			//$post['terms'] = $this->model->getTermListByPostId($postTerms);
			//$post['terms'] = Common::archiveTermsHTML(array_reverse($postTerms), $this->model->getArchiveSlug());;
			//var_dump($terms, $postTerms);exit;
			list($termsOnId, $termsOnParent) = Common::itemsOnKeys($terms, ['id', 'parent']);
			$post['terms'] = Common::termsHTML($this->postTermsLink($termsOnId, $termsOnParent, $postTerms), $this->model->getArchiveSlug());
			$this->filterPermalink($post, $termsOnId, $termsOnParent, $postTerms);
			
			if(SITE_URL . URI != $post['url']){
				exit('$this->request->location('.$post['url'].')');
				//$this->request->location($permalink);
			}
			$this->view->is('single');
		}else{
			if(!empty($hierarchy))
				$this->checkHierarchy($post['url'], $post['parent'], $hierarchy);
		}
		//exit;
	
	
		
		// if(!empty($hierarchy)){
			// if(!$this->options['hierarchical']){
				// $this->checkTermHierarchy($post['id'], $hierarchy);
			// }else{
				// $this->checkHierarchy($post['url'], $post['parent'], $hierarchy);
			// }
		// }
			
		
		
		$this->addBreadCrumbs($post);
		if(!isset($post['__model'])) $post['__model'] = $this->model;
		return $post;
	}
	
	private function setPostOptions($postType){
		$this->options = $this->config->getPageOptionsByType($postType);
		Options::setOptions($this->options);
	}
	public function actionCategory(){
		$funcParams = func_get_args();
		$category = array_pop($funcParams);
		$hierarchy = $funcParams;
		
	}
	
	private function checkHierarchy($url, $parent, $hierarchy){
		
		if(!$parent){
			if($hierarchy)
				$this->request->location(NULL, 404);
			return false;
		}else{
			if(!$hierarchy){
				// взять все страницы, создать иерархию и перенаправить
				$this->request->location(SITE_URL . $this->getParentHierarchy($this->model->getPostsByPostType('page') , $parent, 'url') . '/' . $url . '/', 301);
			}else{
				$parents = $this->db->getAll('Select id, title, url, parent from posts where url IN(\''.implode("','", $hierarchy).'\') order by parent DESC');
				if(count($parents) < count($hierarchy)){
					$this->request->location(NULL, 404);
				}else{
					$h = array_reverse($hierarchy);
					$tempParent = $parent;
					$i = 0;
					$addBreadCrumbs = [];
					foreach($parents as $parent){
						if($parent['id'] != $tempParent || $parent['url'] != $h[$i]){
							$this->request->location(NULL, 404);
						}
						$tempParent = $parent['parent'];
						$addBreadCrumbs[$h[$i]] = $parent['title'];
						$i++;
					}
					foreach(array_reverse($addBreadCrumbs) as $link => $title){
						$this->config->addBreadCrumbs($link, $title);
					}
					
				}
			}
		}
	}
	
	private function checkTermHierarchy($postId, $hierarchy){
		$term = $this->model->getPostTerms('and p.id = ' . $postId . ' and t.slug IN(\''.implode("','", [end($hierarchy)]).'\')');
		//y, , $this->getParentHierarchy($parentId, $items)
		//foreach
		//var_dump($term, $hierarchy);exit;
		if(!$term)
			$this->request->location(null, 404);
		//var_dump($this->getParentHierarchy($term[0]['parent'], $validTerms, 'slug'));
		if(!$term[0]['parent']){
			if(count($hierarchy) > 1)
				$this->request->location(null, 404);
		}else{
			// определить валидную иерархию **-
			$validTerms = $this->model->getTaxonomies($postId);
			$urlHierarchy = implode('/', $hierarchy);
			$validUrlHierarchy = $this->getParentHierarchy($term[0]['parent'], $validTerms, 'slug') . '/' . end($hierarchy);
			//var_dump($validUrlHierarchy, $urlHierarchy);
			if($validUrlHierarchy != $urlHierarchy){
				$this->request->location(str_replace($urlHierarchy, $validUrlHierarchy , FULL_URL), 301);
			}
		}
	}
	
	private function getParentHierarchy($parentId, $items, $compare){
		foreach($items as $item){
			$itemsOnId[$item['id']] = $item;
		}
		$hierarchy = $this->setHierarchy($itemsOnId, $parentId, $compare);
		$hierarchy = implode('/', array_reverse(explode('|', substr($hierarchy, 0, -1))));
		return $hierarchy;
	}
	
	private function setHierarchy($items, $parentId, $compare){
		if(!isset($items[$parentId])) return false;
		$hierarchy = $items[$parentId][$compare] . '|';
		if(isset($items[$parentId]['parent']) && $items[$parentId]['parent']) 
			$hierarchy .= $this->setHierarchy($items, $items[$parentId]['parent']);
		return $hierarchy;
	}
	
	public function actionRouter($params){
		var_dump($params);exit;
	}
	
	public function actionList($taxonomy = null, $taxonomySlug = null, $page = 1){//var_dump(func_get_args());exit;
		$this->model->setLimit($this->page = $page, $this->options['rewrite']['paged']);
		$list = $this->options;
		$listMark = '__list';
		
		// Если не пришла таксономия и у данного типа поста есть архив -  выдаем просто весь архив
		
		//$termsByPostType = $this->model->taxonomy->getAllByPostTypes(Options::get('type'));
		$hierarchy = explode('/', $taxonomySlug);
		if(!$taxonomy && $this->options['has_archive']){
			if(!$list[$listMark] = $this->model->getPostsByPostType(Options::get('type'))) return 0;
			$terms = $this->model->taxonomy->getAllByObjectsIds(array_keys(Common::itemsOnKeys($list[$listMark], ['id'])));
			$termsByPostId = Common::itemsOnKeys($terms, ['object_id']);
			//$terms = Common::clearDuplicateOnKey($terms);
			$terms1 = $this->model->taxonomy->getByTaxonomies(array_keys(Options::get('taxonomy')));
			//var_dump($termsByPostId);exit;
		}else{
			// taxonomy validation
			if(!Common::checkValidation($hierarchy, '/^' . URL_PATTERN . '$/')){
				exit('404-5 - taxonomy validation failed by URL_PATTERN');
			}
			
			
			// 1. проверить иерархию таксономий
			// 2. найти всех потомков
			// 3. взять все записи связанные с потомками включая текущий термин
			// 4. выводить записи, с урлами отфильтрованными в зависимости от slug
			
			// 1
				// 1.1 возьмем все термины данной таксы
				// 1.2 проверим валидность последнего термина
				// 1.3 строим иерархию к первому родителю, компонуем потомков, сверяем иерархию с пришедшей
			
			// 1.1
			$terms1 = $this->model->taxonomy->getByTaxonomies(array_keys(Options::get('taxonomy')));
			$terms  = $this->model->taxonomy->filter($terms1, 'taxonomy', $taxonomy);
			//$terms2 = $this->model->taxonomy->getAll('tt.taxonomy = ?s', $taxonomy);
			// 1.2
			$lastChild = $hierarchy[count($hierarchy) - 1];
			$findTerm = false;
			// get current selected term to know whence build hierarchy
			foreach($terms as $term){
				if($term['slug'] == $lastChild){
					$currentTerm = $term;
					$findTerm = true;
					break;
				}
			}
			if(!$findTerm) exit('404-3');
			// 1.3
			list($termsOnIds, $termsOnParents) = Common::itemsOnKeys($terms, ['id', 'parent']);
			$builtedTermsParentHierarchy = substr(str_replace('|', '/', Common::builtHierarchyDown($termsOnIds, $currentTerm, 'slug') . '|' .$lastChild), 1);
			
			if(implode('/', $hierarchy) != $builtedTermsParentHierarchy) 
				exit('location: ' . $builtedTermsParentHierarchy);
			
			// 2
			$children = isset($termsOnParents[$currentTerm['id']]) ? $termsOnParents[$currentTerm['id']] : [];
			// 3
			$children[] = $currentTerm;
			$termsTaxonomyIds = [];
			foreach($children as $child){
				$termsTaxonomyIds[] = $child['term_taxonomy_id'];
			}
			if(!$list[$listMark] = $this->model->getPostsBysTermsTaxonomyIds($termsTaxonomyIds)) return 0;
			$terms = $this->model->taxonomy->getAllByObjectsIds(array_keys(Common::itemsOnKeys($list[$listMark], ['id'])));
			//var_dump($getAllByObjectsIds);exit;
			$termsByPostId = Common::itemsOnKeys($terms, ['object_id']);
			// 4
				// 4.1 взять первый термин данного поста
				// 4.2 взять все термины по таксе выбранного термина из пункта 4.1
				// 4.3 составить иерархию с помощью хелпера common
		}
		//var_dump($terms, $termsByPostId);exit;
		list($termsOnId, $termsOnParent) = Common::itemsOnKeys($terms1, ['id', 'parent']);
		
		//var_dump($termsOnId, $termsOnParent, $termsByPostId);exit;
		foreach($list[$listMark] as &$post){
			if(!isset($termsByPostId[$post['id']])) $termsByPostId[$post['id']] = false;
			$this->filterPermalink($post, $termsOnId, $termsOnParent, isset($termsByPostId[$post['id']])?$termsByPostId[$post['id']]:false);
			$post['terms'] = Common::termsHTML($this->postTermsLink($termsOnId, $termsOnParent, $termsByPostId[$post['id']]), $this->model->getArchiveSlug());
		}
		
		// Узнаем имя таксономии по метке для хлебных крошек
		$taxonomyName = $taxonomySlug;
		foreach($terms1 as $term){
			if($term['slug'] == $taxonomySlug){
				$taxonomyName = $term['name'];
				break;
			}
		}
		
		$taxonomyTitle = $taxonomy ? $this->options['taxonomy'][$taxonomy]['title'] : '';
		$this->addBreadCrumbs($list, $taxonomyTitle, $taxonomyName, $taxonomyName);
		$list['pagenation'] = $this->pagination();
		
		//$archiveTerms = $this->model->taxonomy->getByTaxonomies(array_keys(Options::get('taxonomy')));
		$archiveTerms = $terms1;
		
		list($termsOnId, $termsOnParent) = Common::itemsOnKeys($archiveTerms, ['id', 'parent']);
		$postTerms = $this->postTermsLink($termsOnId, $termsOnParent, $archiveTerms);
		$list['filters'] = Common::archiveTermsHTML(array_reverse($postTerms), $this->model->getArchiveSlug());
		$list['__model'] = $this->model;
		$this->view->is('list');
		return $list;
	}
	
	private function pagination(){
		return (new Pagenation())->run($this->page, $this->model->getAllItemsCount(), $this->options['rewrite']['paged']);;
	}
	
	private function filterPermalink(&$post, $termsOnId, $termsOnParent, $termsByPostId){//var_dump(func_get_args());exit;
		$permalink 	 = SITE_URL . trim(Options::slug(), '/') . '/' . $post['url'] . '/';
		$post['url'] = applyFilter('postTypeLink', $permalink, $termsOnId, $termsOnParent, $termsByPostId);
	}
	
	private function postTermsLink($termsOnId, $termsOnParent, $termsByPostId, $mergeKey = 'slug'){//var_dump(func_get_args());exit;
		if(!$termsByPostId) return;
		foreach($termsByPostId as $postTerm){
			$title = Options::get('taxonomy')[$postTerm['taxonomy']]['title'];
			if(!isset($postTerms[$title])) $postTerms[$title] = [];
			$postTerms[$title][$postTerm['name']] = $postTerm['taxonomy'] . str_replace('|', '/', Common::builtHierarchyDown($termsOnId, $postTerm, $mergeKey) . '|' . $postTerm[$mergeKey]);
		}
		return $postTerms;
	}
	
	
	/*******************/
	/*** BreadCrumbs ***/
	/*******************/
	
	private function addBreadCrumbs(&$post, $taxonomyTitle = null, $value = null, $type = null){
		if($this->options['has_archive'] && !Options::front())
			$this->config->addBreadCrumbs($this->options['has_archive'], $this->options['title']);
		
		
		if($type){
			$this->addBreadCrumbsHelper($taxonomyTitle, $value, $taxonomyTitle, $post['title']);
		}elseif(isset($post['id']) && $this->config->front_page != $post['id']){
			$this->config->addBreadCrumbs($post['url'], $post['title']);
			if(isset($this->options['rewrite']['slug']))
				$post['title'] .= ' - ' . $this->options['title'];
		}
			
	}
	
	private function addBreadCrumbsHelper($taxonomyTitle, $value, $text, &$postTitle){
		$this->config->addBreadCrumbs($taxonomyTitle, $text . ': ' . $value);
		$postTitle = $value . " - {$text} " . $postTitle;
	}
	
	private function stats(){
		global $start;
		return $this->di->get('db')->getStats();
	}
}