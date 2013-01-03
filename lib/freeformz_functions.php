<?php


class freeformzFunctions{
	static public function generateKey($length)
	{
		$options = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-";
		$code = "";
		for($i = 0; $i < $length; $i++) {
			$key = rand(0, strlen($options) - 1);
			$code .= $options[$key];
		}

		return $code;
	}

	static public function slugify($text, $separator = '-')
	{
		$slug = $text;
		// transliterate
		if (function_exists('iconv')) {
			$slug = iconv('utf-8', 'us-ascii//TRANSLIT', $slug);
		}

		// lowercase
		if (function_exists('mb_strtolower')) {
			$slug = mb_strtolower($slug);
		} else {
			$slug = strtolower($slug);
		}

		// remove accents resulting from OSX's iconv
		$slug = str_replace(array('\'', '`', '^'), '', $slug);

		// replace non letter or digits with separator
		$slug = preg_replace('/\W+/', $separator, $slug);

		// trim
		$slug = trim($slug, $separator);
		return $slug;
	}

	static public function compileForm($form){
		phpQuery::newDocument($form);
		$config = array();
		foreach (pq('.control-group') as $widget) {
			$widget_conf = array();
			$widget = pq($widget);

			$widget_conf['label'] = pq('label', $widget)->text();
			$widget_conf['help'] = pq('.help-block', $widget)->text();

			$field = 'input';
			if (count(pq('input', $widget))) {
				$widget_conf['type'] = pq('input', $widget)->attr('type');
				$widget_conf['required'] = pq('input', $widget)->attr('required');
				$widget_conf['placeholder'] = pq('input', $widget)->attr('placeholder');
				$widget_conf['add-on-text'] = pq('.add-on', $widget)->text();

				if (count(pq('.input-prepend', $widget))) {
					$widget_conf['add-on'] = 'prepend';
				} else if (count(pq('.input-append', $widget))){
					$widget_conf['add-on'] = 'append';
				}

			} else if(count(pq('textarea', $widget))) {
				$widget_conf['type'] = 'textarea';
				$widget_conf['required'] = pq('textarea', $widget)->attr('required');
				$widget_conf['placeholder'] = pq('textarea', $widget)->attr('placeholder');
			} else if(count(pq('button', $widget))) {
				$widget_conf['type'] = 'button';
				$widget_conf['value'] = pq('button', $widget)->text();
				$widget_conf['class'] = pq('button', $widget)->attr('class');
			}
			$config[] = $widget_conf;
		}
		$config = serialize($config);
		return $config;
	}
	static public function getFormHeight($config) {
		$height = 0;
		$nb_buttons = 0;
		$config = unserialize($config);
		foreach ($config as $widget) {
			if (isset($widget['help']) && $widget['help']!='') {
				$height = $height+25;

			}

			if('textarea' == $widget['type']){
				$height = $height+160;
			} elseif('button' == $widget['type']) {
				$height = $height+30;
				$nb_buttons++;
			} else {
				$height = $height+30;
			}
			$height = $height+20;

		}

		if (!$nb_buttons) {
			//Add height for the automatic button if no button setted
			$height = $height+30;
		}

		return $height;
	}
}