<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../lib/freeformz_functions.php';

$app = new Silex\Application();
$app['debug'] = true;
$app['zilex.index'] = 'home';

// Services

$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__.'/../views',
));
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
	'translator.messages' => array(),
));
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
	'db.options' => array(
		'driver'   => 'pdo_mysql',
		'host'     => 'localhost',
		'dbname'     => 'freeformz',
		'user'     => 'root',
		'password'     => '',
	),
));
$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\SwiftmailerServiceProvider());

// Dev conf
if (true == $app['debug']) {
	$app['swiftmailer.options'] = array(
		'host' => 'smtp.free.fr',
		'port' => '25'
	);
}

// Controlers

$app->match('/', function (Request $request) use ($app) {
	$form = $app['form.factory']->createBuilder('form')
		->add('config', 'hidden', array(
			'constraints' => array(new Assert\NotBlank())
		))
		->add('email', 'email', array(
			'constraints' => array(new Assert\NotBlank())
		))
		->getForm();

	if ('POST' == $request->getMethod()) {
		$temp_form = $form;
		$form->bind($request);

		if ($form->isValid()) {
			$data = $form->getData();

			do {
				$code = FreeformzFunctions::generateKey(7);
				$hash = FreeformzFunctions::generateKey(10);

				$sql = "SELECT id FROM form WHERE code = ? OR hash = ?";
				$found = $app['db']->fetchAssoc($sql, array($code, $hash));
			} while ($found);
			$config = FreeformzFunctions::compileForm($data['config']);
			$height = FreeformzFunctions::getFormHeight($config);
			$email = $data['email'];

			// do something with the data
			$sql = "INSERT INTO form VALUES (null, ?, ?, ?, ?, ?)";
			$app['db']->executeUpdate($sql, array($code, $hash, $config, $email, 0));

			//return $app->redirect('/hello');
			return $app['twig']->render('template/code.twig', array(
				'pageName' => 'code',
				'form' => $data['config'],
				'code' => $code,
				'hash' => $hash,
				'email' => $email,
				'height' => $height,
				'server' => $_SERVER['SERVER_NAME']
			));
		}
	}
	return $app['twig']->render('template/home.twig', array(
		'pageName' => 'home',
		'form' => $form->createView()
	));
});

$app->get('/{page}', function ($page) use ($app) {
	try{
		return $app['twig']->render('template/'.$page.'.twig', array(
			'pageName' => $page,
		));
	} catch (Exception $e){
		if('Twig_Error_Loader' == get_class($e)){
			$app->abort(404, 'Twig template does not exist.');
		}else {
			throw $e;
		}
	}
})
->value('page', $app['zilex.index']);

$app->match('/form/{code}/{hash}', function($code, $hash, Request $request) use($app) {

	$sql = "SELECT * FROM form WHERE code = ? AND hash = ?";
	$form_config = $app['db']->fetchAssoc($sql, array($code, $hash));
	if(!$form_config) {
		$app->abort(404, 'Form does not exist.');
	}

	$token = unserialize($form_config['config']);
	$email = $form_config['email'];
	$form_id = $form_config['id'];


	$data = array();
	$btns = array();
	$lag = 0;
	$form = $app['form.factory']->createBuilder('form', $data);
	foreach ($token as $key => $widget) {
		if($widget['type'] == 'button') {
			$btns[$key-$lag] = $widget;
			$lag++;
			continue;
		}

		$constraints = array();
		$attr = array();
		if (isset($widget['default'])) {
			$data[$widget['label']] = $widget['default'];
		}
		if (isset($widget['help'])) {
			$attr['data-help'] = $widget['help'];
		}
		if (isset($widget['placeholder'])) {
			$attr['placeholder'] = $widget['placeholder'];
		}
		if (isset($widget['add-on-text'])) {
			$attr['add-on-text'] = $widget['add-on-text'];
		}
		if (isset($widget['add-on'])) {
			$attr['add-on'] = $widget['add-on'];
		}

		if (isset($widget['required'])) {
			$constraints[] = new Assert\NotBlank(array('message' => 'Don\'t leave blank'));
		}
		switch ($widget['type']) {
			case 'email':
					$constraints[] = new Assert\Email(array('message' => 'Invalid email address'));
				break;
			case 'url':
				$constraints[] = new Assert\Url(array('message' => 'Invalid URL'));
				break;
			case 'integer':
				$constraints[] = new Assert\Type(array('type' => 'integer', 'message' => 'The value {{ value }} is not a valid {{ type }}'));
				break;
			case 'number':
			case 'percent':
			$constraints[] = new Assert\Type(array('type' => 'float', 'message' => 'The value {{ value }} is not a valid {{ type }}'));
				break;
		}

		$form->add( freeformzFunctions::slugify($widget['label']), $widget['type'], array(
			'label' => $widget['label'],
			'required' => isset($widget['required']) ? true : false,
			'constraints' => $constraints,
			'attr' => $attr
		));
	}
	if(0 == $lag) {
		//No button setted, so we make one
		$btns[$key+1] = array(
			'label' => '',
			'value' => 'Send',
			'class' => 'btn'
		);
	}
	$form =	$form->getForm();

	if ('POST' == $request->getMethod()) {
		$form->bind($request);

		if ($form->isValid()) {
			$sql = "UPDATE form SET used=used+1 WHERE id = ?";
			$app['db']->executeUpdate($sql, array($form_id));

			$datas = $form->getData();

			$content = $app['twig']->render('email/contact.twig', array(
				'datas' => $datas,
				'ip'    => $request->getClientIp()
			));

			// do something with the data
			$message = \Swift_Message::newInstance()
				->setContentType('text/html')
				->setSubject('[Freeformz] contact form message')
				->setFrom(array('service@freeformz.com'))
				->setTo(array($email))
				->setBody($content);

			$app['mailer']->send($message);
			return $app->redirect('/thanks');
		}
	}
	// display the form
	return $app['twig']->render('template/form.twig', array('form' => $form->createView(), 'btns' => $btns, 'code' => $code, 'hash' => $hash));
});

$app->error(function (\Exception $e, $code) use ($app) {
	if($app['debug']) {
		return;
	}
	switch ($code) {
		case 404:
			return new Response( $app['twig']->render('content/404.twig'), 404);
			break;
		default:
			$message = 'We are sorry, but something went terribly wrong.';
	}

	return new Response($message);
});

$app->run();