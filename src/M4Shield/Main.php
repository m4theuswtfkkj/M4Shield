<?php

namespace M4Shield;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use M4Shield\EventListener;
use M4Shield\Util\Cache;

class Main extends PluginBase
{
    /** @var Config */
    private $config;
    const M4SHIELD_VERSION = 3;

    public function onEnable()
    {
        $this->getLogger()->info("|===============> M4Shield <===============|");
        $this->getLogger()->info("- Plugin by M4theuskkj (@m4theus.wtfkkj)");
        $this->getLogger()->info("- Versão do plugin: " . self::M4SHIELD_VERSION);
        $this->getLogger()->info("- Carregando a config.yml, registrando listener e verificando novas versões...");
        $this->initConfig();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
    }
    
    public function onDisable()
    {
        if (Cache::hasCache("all"))
        {
            Cache::clearAll();
        }
    }

    public function initConfig()
    {
        if (!is_dir($this->getDataFolder()))
        {
            @mkdir($this->getDataFolder());
        }

        $configFile = $this->getDataFolder() . "config.yml";
        
        if (!file_exists($configFile))
        {
            $this->saveDefaultConfig();
        }

        $this->config = new Config($configFile, Config::YAML);

        if ($this->config->getNested("plugin.version") !== self::M4SHIELD_VERSION)
        {
            $this->getLogger()->warning("A config.yml do plugin está desatualizada ou não existe, criando uma nova config.yml...");
            @unlink($configFile);
            $this->saveDefaultConfig();
        }
        
        if (in_array("player1", $this->config->getNested("commandblocker.allowedPlayers")) || in_array("player2", $this->config->getNested("commandblocker.allowedPlayers")))
        {
            $this->getLogger()->critical("Por favor, altere os nicks de player1 ou player2 na área de jogadores permitidos do commandblocker se você quiser que o seu servidor inicie");
            $this->getServer()->shutdown();
        }
        
    }

    public function getConfig()
    {
        return $this->config;
    }
}