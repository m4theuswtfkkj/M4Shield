<?php

namespace M4Shield;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\item\Item;
use pocketmine\Player;

use M4Shield\Main;
use M4Shield\Util\Cache;

class EventListener implements Listener
{
    /** @var Main */
    private $main;    
    /** @var array */
    private $configCache;
    /** @var array */
    private $antiSpam;
    
    const IPV4_REGEX = '/\b(?:\d{1,3}\.){3}\d{1,3}\b/';
    const IPV6_REGEX = '/(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}/';

    public function __construct(Main $main)
    {
        $this->main = $main;
    }

    public function getServer()
    {
        return $this->main->getServer();
    }
    
    private function getConfigValue($key, $default = null)
    {
        if (Cache::hasCache($key)) {
            return Cache::get($key);
        }

        $value = $this->main->getConfig()->getNested($key, $default);
        Cache::add($key, $value);

        return $value;
    }

    private function processBlockedWords($msg)
    {
        $blockedWords = $this->getConfigValue("chatblocker.blockedWords", []);
        $replacementChar = $this->getConfigValue("chatblocker.replacementChar", "*");

        foreach ($blockedWords as $word)
        {
            if (stripos($msg, $word) !== false)
            {
                $msg = str_replace($word, str_repeat($replacementChar, strlen($word)), $msg);
            }
        }

        return $msg;
    }
    
    private function hasBlockedWordsInName($name)
    {
        $blockedWords = $this->getConfigValue("nameblocker.blockedWords", []);

        foreach ($blockedWords as $word) {
            if (stripos($name, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    private function hasIpAddress(string $msg)
    {
        return preg_match(self::IPV4_REGEX, $msg) || preg_match(self::IPV6_REGEX, $msg);
    }

    private function blockIpLeak(string $msg)
    {
        $msg = preg_replace(self::IPV4_REGEX, str_repeat($this->getConfigValue("ipleakblock.replacementChar", "*"), 6), $msg);
        $msg = preg_replace(self::IPV6_REGEX, str_repeat($this->getConfigValue("ipleakblock.replacementChar", "*"), 6), $msg);

        return $msg;
    }

    public function onPreLogin(PlayerPreLoginEvent $e)
    {
        $p = $e->getPlayer();
        $ip = $p->getAddress();
        
        if (!$e->isCancelled())
        {
            $users = 0;
            $antibotEnabled = $this->getConfigValue("antibot.enabled", true);
            $nameBlockerEnabled = $this->getConfigValue("nameblocker.enabled", true);
            $antibotmaxCount = $this->getConfigValue("antibot.maxCount", 4);

            if ($antibotEnabled) {
                foreach ($this->getServer()->getOnlinePlayers() as $ps)
                {
                    if ($ip == $ps->getAddress())
                    {
                        $users++;
                    }
                }
            
                if ($users >= $antibotmaxCount)
                {
                    $this->getServer()->getNetwork()->blockAddress($ip, -1);
                }
            }
        
            if ($nameBlockerEnabled && $this->hasBlockedWordsInName($p->getName()))
            {
                $e->setKickMessage($this->getConfigValue("nameblocker.message", "§8[§bM4Shield§8] §cExiste uma palavra bloqueada no seu nick/nome"));
                $e->setCancelled(true);
            }
        }
    }

    public function onUse(PlayerInteractEvent $e)
    {
        $p = $e->getPlayer();
        $item = $e->getItem();
        
        if (!$e->isCancelled())
        {
            $id = $item->getId();
            $antigriefEnabled = $this->getConfigValue("antigrief.enabled", true);
            $blockedItems = $this->getConfigValue("antigrief.blockedItems", []);

            if ($antigriefEnabled && in_array($id, $blockedItems))
            {
                $p->getInventory()->setItemInHand(Item::get(0, 0));
                if ($p->getInventory()->contains(Item::get(327, 0, 1)))
                {
                    $p->getInventory()->removeItem(Item::get(327, 0, 1));
                }
            
                $p->sendMessage($this->getConfigValue("antigrief.message", "§8[§bM4Shield§8] §cEste item está bloqueado"));
                $e->setCancelled(true);
            }
        }
    }

    public function onHeld(PlayerItemHeldEvent $e)
    {
        $p = $e->getPlayer();
        $item = $e->getItem();
        
        if (!$e->isCancelled())
        {
            $id = $item->getId();
            $antigriefEnabled = $this->getConfigValue("antigrief.enabled", true);
            $antigriefItemInHand = $this->getConfigValue("antigrief.itemInHand", false);
            $blockedItems = $this->getConfigValue("antigrief.blockedItems", []);

            if ($antigriefEnabled && in_array($id, $blockedItems))
            {
                if ($antigriefItemInHand)
                {
                    $p->getInventory()->setItemInHand(Item::get(0, 0));
                    if ($p->getInventory()->contains(Item::get(327, 0, 1)))
                    {
                        $p->getInventory()->removeItem(Item::get(327, 0, 1));
                    }
                }
                $p->sendMessage($this->getConfigValue("antigrief.message", "§8[§bM4Shield§8] §cEste item está bloqueado"));
                $e->setCancelled(true);
            }
        }
    }

    public function onCmd(PlayerCommandPreprocessEvent $e)
    {
        $p = $e->getPlayer();
        $msg = $e->getMessage();

        if ($msg[0] !== "/")
        {
            return;
        }

        if (!$e->isCancelled())
        {
            $ccmd = explode(" ", $msg);
            $cmd = strtolower(substr(array_shift($ccmd), 1));
            $commandBlockerEnabled = $this->getConfigValue("commandblocker.enabled", true);
            $blockedCommands = $this->getConfigValue("commandblocker.blockedCommands", []);
            $allowedPlayers = $this->getConfigValue("commandblocker.allowedPlayers", []);
            $chatBlockerEnabled = $this->getConfigValue("chatblocker.enabled", false);
            $blockInCommands = $this->getConfigValue("chatblocker.blockInCommands", false);
            $ipLeakBlockEnabled = $this->getConfigValue("ipleakblock.enabled", true);
            $ipBlockInCommands = $this->getConfigValue("ipleakblock.blockInCommands", true);
        
            if ($commandBlockerEnabled && in_array($cmd, $blockedCommands) && !in_array(strtolower($p->getName()), $allowedPlayers))
            {
                $p->sendMessage($this->getConfigValue("commandblocker.message", "§8[§bM4Shield§8] §cEste comando está bloqueado"));
                $e->setCancelled(true);
            }
        
            if ($blockInCommands && $chatBlockerEnabled)
            {
                $e->setMessage($this->processBlockedWords($msg));
            }
        
            if ($ipLeakBlockEnabled && $this->hasIpAddress($msg) && $ipBlockInCommands)
            {
                $e->setMessage($this->blockIpLeak($msg, $this->getConfigValue("ipleakblock.replacementChar", "*")));
            }
        }
    }

    public function onChat(PlayerChatEvent $e)
    {
        $p = $e->getPlayer();
        $msg = $e->getMessage();

        if (!$e->isCancelled())
        {
            $chatBlockerEnabled = $this->getConfigValue("chatblocker.enabled", false);
            $ipLeakBlockEnabled = $this->getConfigValue("ipleakblock.enabled", true);
            $antiSpamEnabled = $this->getConfigValue("antispam.enabled", true);
            
            if ($antiSpamEnabled)
            {
                if(isset($this->antiSpam[$p->getName()]))
                {
                    $time = time() - $this->antiSpam[$p->getName()];
                    $antiSpamTime = $this->getConfigValue("antispam.time", 2);
                    if($time <= $antiSpamTime)
                    {
                        $e->setCancelled(true);
                        $p->sendMessage(str_replace('{$seg}', $antiSpamTime, $this->getConfigValue("antispam.message", '§8[§bM4Shield§8] §cAguarde {$seg} segundos para enviar outra mensagem')));
                    }
                }
                $this->antiSpam[$p->getName()] = time();
            }
        
            if ($chatBlockerEnabled)
            {
                $e->setMessage($this->processBlockedWords($msg));
            }
        
            if ($ipLeakBlockEnabled && $this->hasIpAddress($msg))
            {
                $e->setMessage($this->blockIpLeak($msg));
            }
        }
    }
}