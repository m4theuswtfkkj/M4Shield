<?php

namespace M4Shield;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use M4Shield\EventListener;

class Main extends PluginBase {

  /** @var Config */
  private $config;
  const M4SHIELD_VERSION = 3;

  public function onEnable() {
    $this->getLogger()->info("|=> M4Shield <=|");
    $this->getLogger()->info("- Plugin by M4theuskkj (@M4theuskkj)");
    $this->getLogger()->info("- Versão do plugin: ". self::M4SHIELD_VERSION);
    $this->getLogger()->info("- Carregando a config.yml e registrando listener...");
    $this->initConfig();
    $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
  }

  public function initConfig() {
    @mkdir($this->getDataFolder());
    $configFile = $this->getDataFolder() . "config.yml";

    if (!file_exists($configFile)) {
      $this->saveDefaultConfig();
    }

    $this->config = new Config($configFile, Config::YAML);

    if ($this->config->getNested("plugin.version") !== self::M4SHIELD_VERSION) {
      $this->getLogger()->warning("A config.yml do plugin está desatualizada ou não existe, criando uma nova config.yml...");
      @unlink($configFile);
      $this->saveDefaultConfig();
      $this->config = new Config($configFile, Config::YAML);
    }
  }

  public function getConfig() {
    return $this->config;
  }
}
