<?php

class PaginatorSorter extends Paginator {

	public static function make($results, $total, $per_page)
	{
		$page = static::page($total, $per_page);

		$last = ceil($total / $per_page);
		
		$default_sort = func_get_arg(3); // additional parameter to the original Paginator::make method

		return new static($results, $page, $total, $per_page, $last, $default_sort);
	}

	protected function __construct($results, $page, $total, $per_page, $last, $default_sort)
	{
		parent::__construct($results, $page, $total, $per_page, $last);
		
		// Define default query parameter labels
		$this->param_labels = array(
			'sort' => 'sort',
			'dir' => 'dir',
			'asc' => 'asc',
			'desc' => 'desc',
			'page' => 'page',
			'per_page' => 'per_page'
		);
		
		// Define the parameter order in the query
		$this->param_order = array(
			'sort',
			'dir',
			'page',
			'per_page'
		);
		
		$this->default_sort = $default_sort;
		
		$this->dots = '<li class="disabled">'.HTML::link('#', '...', array('class' => 'disabled')).'</li>';
	}

	public function links($adjacent = 3)
	{
		if ($this->last <= 1) return '';

		// The hard-coded seven is to account for all of the constant elements in a
		// sliding range, such as the current page, the two ellipses, and the two
		// beginning and ending pages.
		//
		// If there are not enough pages to make the creation of a slider possible
		// based on the adjacent pages, we will simply display all of the pages.
		// Otherwise, we will create a "truncating" sliding window.
		if ($this->last < 7 + ($adjacent * 2))
		{
			$links = $this->range(1, $this->last);
		}
		else
		{
			$links = $this->slider($adjacent);
		}

		$content = $this->previous().' '.$links.' '.$this->next();

		return '<div class="pagination"><ul>'.$content.'</ul></div>';
	}

	protected function range($start, $end)
	{
		$pages = array();

		// To generate the range of page links, we will iterate through each page
		// and, if the current page matches the page, we will generate a span,
		// otherwise we will generate a link for the page. The span elements
		// will be assigned the "current" CSS class for convenient styling.
		for ($page = $start; $page <= $end; $page++)
		{
			if ($this->page == $page)
			{
				$pages[] = '<li class="active">'.HTML::link('#', $page, array('class' => 'current disabled')).'</li>';
			}
			else
			{
				$pages[] = '<li>'.$this->link($page, $page, null).'</li>';
			}
		}

		return implode(' ', $pages);
	}
	
	public function slider($adjacent = 3)
	{
		$window = $adjacent * 2;

		// If the current page is so close to the beginning that we do not have
		// room to create a full sliding window, we will only show the first
		// several pages, followed by the ending of the slider.
		//
		// Likewise, if the page is very close to the end, we will create the
		// beginning of the slider, but just show the last several pages at
		// the end of the slider. Otherwise, we'll build the range.
		//
		// Example: 1 [2] 3 4 5 6 ... 23 24
		if ($this->page <= $window)
		{
			return $this->range(1, $window + 2).' '.$this->ending();
		}
		// Example: 1 2 ... 32 33 34 35 [36] 37
		elseif ($this->page >= $this->last - $window)
		{
			return $this->beginning().' '.$this->range($this->last - $window - 2, $this->last);
		}

		// Example: 1 2 ... 23 24 25 [26] 27 28 29 ... 51 52
		$content = $this->range($this->page - $adjacent, $this->page + $adjacent);

		return $this->beginning().' '.$content.' '.$this->ending();
	}

	protected function element($element, $page, $text, $disabled)
	{
		$class = "{$element}_page";

		if (is_null($text))
		{
			$text = Lang::line("pagination.{$element}")->get($this->language);
		}

		// Each consumer of this method provides a "disabled" Closure which can
		// be used to determine if the element should be a span element or an
		// actual link. For example, if the current page is the first page,
		// the "first" element should be a span instead of a link.
		if ($disabled($this->page, $this->last))
		{
			return '<li class="disabled">'.HTML::link('#', $text, array('class' => 'disabled')).'</li>';
		}
		else
		{
			return '<li>'.$this->link($page, $text, $class).'</li>';
		}
	}

	protected function link($page, $text, $class)
	{
		$params = Input::all();
		
		$params[$this->param_labels['page']] = $page;
		
		$params = $this->reorder_params($params);

		$query = '?'.http_build_query($params);

		return HTML::link(URI::current().$query, $text, compact('class'), Request::secure());
	}

	public function sortlink($sort, $link_label)
	{
		$arrow = '';

		$dir = $this->param_labels['asc'];

		if (!Input::get($this->param_labels['sort']) && isset($this->default_sort))
		{
			if ($sort === $this->default_sort['sort'])
			{
				$sorted_dir = $this->default_sort['dir'];
			}
		}

		else
		{
			if (Input::get($this->param_labels['sort']) === $sort)
			{
				$sorted_dir = Input::get($this->param_labels['dir']);
			}
		}

		if (isset($sorted_dir))
		{
			$dir = $this->reverse_sort_dir($sorted_dir);

			if ($sorted_dir === $this->param_labels['asc'])
			{
				$arrow = '<i class="icon-chevron-up"></i>';
			}
			else
			{
				$arrow = '<i class="icon-chevron-down"></i>';
			}
		}

		$params = Input::all();
		$params[$this->param_labels['sort']] = $sort;
		$params[$this->param_labels['dir']] = $dir;
		
		$params = $this->reorder_params($params);

		$query = http_build_query($params);

		return HTML::link(URI::current().'?'.$query, $link_label, array(), Request::secure()).' '.$arrow;
	}

	protected function reverse_sort_dir($dir)
	{
		if ($dir === $this->param_labels['asc'])
		{
			return $this->param_labels['desc'];
		}
		else
		{
			return $this->param_labels['asc'];
		}
	}
	
	protected function reorder_params($params)
	{
		foreach ($this->param_order as $param)
		{
			$param_label = $this->param_labels[$param];
			
			if (isset($params[$param_label]))
			{
				$pulled_param = $params[$param_label];
				unset($params[$param_label]);
				$params[$param_label] = $pulled_param;
			}
		}
		
		return $params;
	}

}