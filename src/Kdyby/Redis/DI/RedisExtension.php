<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Redis\DI;
use Kdyby;
use Kdyby\Redis\RedisClient;
use Nette;
use Nette\Config\Compiler;
use Nette\Config\Configurator;
use Nette\DI\ContainerBuilder;
use Nette\DI\Statement;
use Nette\Utils\Validators;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisExtension extends Nette\Config\CompilerExtension
{

	/**
	 * @var array
	 */
	public $defaults = array(
		'journal' => FALSE,
		'storage' => FALSE,
		'session' => FALSE,
		'lockDuration' => 15,
		'host' => 'localhost',
		'port' => 6379,
		'timeout' => 10,
		'database' => 0,
		'debugger' => '%debugMode%'
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$client = $builder->addDefinition($this->prefix('client'))
			->setClass('Kdyby\Redis\RedisClient', array(
				'host' => $config['host'],
				'port' => $config['port'],
				'database' => $config['database'],
				'timeout' => $config['timeout']
			))
			->addSetup('setupLockDuration', array($config['lockDuration']));

		if ($config['debugger']) {
			$client->addSetup('setPanel', array(
				new Statement('Kdyby\Redis\Diagnostics\Panel::register')
			));
		}

		if ($config['journal']) {
			$builder->addDefinition($this->prefix('cacheJournal'))
				->setClass('Kdyby\Redis\RedisJournal');

			// overwrite
			$builder->removeDefinition('nette.cacheJournal');
			$builder->addDefinition('nette.cacheJournal')->setFactory($this->prefix('@cacheJournal'));
		}

		if ($config['storage']) {
			$builder->addDefinition($this->prefix('cacheStorage'))
				->setClass('Kdyby\Redis\RedisStorage');

			$builder->removeDefinition('cacheStorage');
			$builder->addDefinition('cacheStorage')->setFactory($this->prefix('@cacheStorage'));
		}

		if ($config['session']) {
			$builder->addDefinition($this->prefix('sessionHandler'))
				->setClass('Kdyby\Redis\RedisSessionHandler');

			$builder->getDefinition('session')
				->addSetup('setStorage', array($this->prefix('@sessionHandler')));
		}
	}



	/**
	 * Verify, that redis is installed, working and has the right version.
	 */
	public function beforeCompile()
	{
		$config = $this->getConfig($this->defaults);
		if ($config['journal'] || $config['storage'] || $config['session']) {
			$client = new RedisClient($config['host'], $config['port'], $config['database'], $config['timeout']);
			$client->assertVersion();
			$client->close();
		}
	}



	/**
	 * @param \Nette\Config\Configurator $config
	 */
	public static function register(Configurator $config)
	{
		$config->onCompile[] = function (Configurator $config, Compiler $compiler) {
			$compiler->addExtension('redis', new RedisExtension());
		};
	}

}
