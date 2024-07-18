<?php

/*
* 
* ███╗░░░███╗░░██╗██╗████████╗██╗░░██╗███████╗██╗░░░██╗░██████╗
* ████╗░████║░██╔╝██║╚══██╔══╝██║░░██║██╔════╝██║░░░██║██╔════╝
* ██╔████╔██║██╔╝░██║░░░██║░░░███████║█████╗░░██║░░░██║╚█████╗░
* ██║╚██╔╝██║███████║░░░██║░░░██╔══██║██╔══╝░░██║░░░██║░╚═══██╗
* ██║░╚═╝░██║╚════██║░░░██║░░░██║░░██║███████╗╚██████╔╝██████╔╝
* ╚═╝░░░░░╚═╝░░░░░╚═╝░░░╚═╝░░░╚═╝░░╚═╝╚══════╝░╚═════╝░╚═════╝░
*
*/

namespace M4Shield;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use M4Shield\EventListener;
use M4Shield\Util\Cache;

class Main extends PluginBase
{
    /** @var Config */
    private $config;
    const M4SHIELD_VERSION = 3.2;

    public function onEnable()
    {
        $this->getLogger()->info("|===============> M4Shield <===============|");
        $this->getLogger()->info("- Plugin by M4theuskkj (@m4theus.wtfkkj)");
        $this->getLogger()->info("- Versão do plugin: " . self::M4SHIELD_VERSION);
        $this->getLogger()->info("- Carregando a config.yml e registrando listener...");
        
        if(!$this->verifyPlugin())
        {
            $this->initConfig();
            if (in_array("player1", $this->config->getNested("commandblocker.allowedPlayers")) || in_array("player2", $this->config->getNested("commandblocker.allowedPlayers")))
            {
                $this->getLogger()->critical("|===============> M4Shield <===============|");
                $this->getLogger()->critical("- Altere os nicks \"player1\" ou \"player2\" no bloqueador de comandos.");
                $this->getLogger()->critical("- Desativando....");
                $this->setEnabled(false);
                return;
            }
            $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        }
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
    }
    
    public function verifyPlugin()
    {
        $changed = false;
        
        if (base64_encode($this->getDescription()->getName()) !== "TTRTaGllbGQ=" || base64_encode($this->getDescription()->getDescription()) !== "VW0gcGx1Z2luIHByYSBwcm90ZWdlciBzZXJ2aWRvcmVzIGRlIHBvY2tldG1pbmUgMi4wLjAgY29udHJhIGdyaWVmLCBib3RzIGUgb3V0cmFzIGNvaXNhcw==")
        {
            $this->getLogger()->error(base64_decode("UGFyZWNlIHF1ZSBhbGd1bWFzIGluZm9ybWHDp8O1ZXMgZG8gcGx1Z2luIGZvcmFtIGFsdGVyYWRhcywgZGVzYXRpdmFuZG8uLi4="));
            $this->setEnabled(false);
            $changed = true;
        }
        
        return $changed;
    }

    public function getConfig()
    {
        return $this->config;
    }
}