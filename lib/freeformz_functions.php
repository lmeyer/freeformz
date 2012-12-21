<?php


class freeformzFunctions{
	static public function generateKey($length)
	{
		$options = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-";
		$code = "";
		for($i = 0; $i < $length; $i++)
		{
			$key = rand(0, strlen($options) - 1);
			$code .= $options[$key];
		}

		return $code;
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
		$config = unserialize($config);
		foreach ($config as $widget) {
			if($widget['type'] == 'button') {
				$height = $height+32;
				continue;
			}

			if (isset($widget['help'])) {
				$height = $height+25;
			}

			if('textarea' == $widget['type']){
				$height = $height+161;
			} elseif('button' == $widget['type']) {
				$height = $height+32;
			} else {
				$height = $height+36;
			}
			$height = $height+20;
		}

		return $height;
	}
}