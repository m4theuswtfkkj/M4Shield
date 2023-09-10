<?php

namespace M4Shield;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\item\Item;
use pocketmine\Player;

use M4Shield\Main;

class EventListener implements Listener {

  /** @var Main */
  private $main;

  public function __construct(Main $main) {
    $this->main = $main;
  }

  public function getServer() {
    return $this->main->getServer();
  }

  private function removeBlockedItem(Player $p) {
    $p->getInventory()->setItemInHand(Item::get(0, 0));
  }

/*  private function processBlockedWords($message) {
    $blockedWords = $this->main->getConfig()->getNested("chatblocker.blockedWords", []);
    $replacementChar = $this->main->getConfig()->getNested("chatblocker.replacementChar", "*");

    foreach ($blockedWords as $word) {
      if (stripos($message, $word) !== false) {
        $message = str_ireplace($word, str_repeat($replacementChar, mb_strlen($word)), $message);
      }
    }

    return $message;
  } */

  private function processBlockedWords($message) {
    $blockedWords = $this->main->getConfig()->getNested("chatblocker.blockedWords", []);
    $replacementChar = $this->main->getConfig()->getNested("chatblocker.replacementChar", "*");

    $blockedWordsSet = array_flip($blockedWords);
    $pattern = '/\b(' . implode('|', array_map('preg_quote', $blockedWords)) . ')\b/i';
    $message = preg_replace_callback($pattern, function($matches) use ($replacementChar) {
        return str_repeat($replacementChar, mb_strlen($matches[0]));
    }, $message);

    return $message;
  }

  private function processLeak($message, $replacementChar, $player) {
    $ipv4Pattern = '/\b(?:\d{1,3}\.){3}\d{1,3}\b/';
    $ipv6Pattern = '/(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}/';

    if (preg_match($ipv4Pattern, $message) || preg_match($ipv6Pattern, $message)) {
        $message = preg_replace($ipv4Pattern, str_repeat($replacementChar, 7), $message);
        $message = preg_replace($ipv6Pattern, str_repeat($replacementChar, 7), $message);
    }

    return $message;
  }

  public function onLogin(PlayerLoginEvent $e) {
    $p = $e->getPlayer();
    $ip = $p->getAddress();
    $users = 0;
    $antibotEnabled = $this->main->getConfig()->getNested("antibot.enabled", true);
    $antibotmaxCount = $this->main->getConfig()->getNested("antibot.maxCount", 4);

    if ($antibotEnabled) {
      foreach ($this->getServer()->getOnlinePlayers() as $ps) {
        if ($ip == $ps->getAddress()) {
          $users++;
        }
      }
      if ($users >= $antibotmaxCount) {
        $this->getServer()->getNetwork()->blockAddress($ip, -1);
      }
    }
  }

  public function onUse(PlayerInteractEvent $e) {
    $p = $e->getPlayer();
    $item = $e->getItem();
    $id = $item->getId();
    $antigriefEnabled = $this->main->getConfig()->getNested("antigrief.enabled", true);
    $blockedItems = $this->main->getConfig()->getNested("antigrief.blockedItems", []);
    $message = $this->main->getConfig()->getNested("antigrief.message", "§8[§bM4Shield§8] §cEste item está bloqueado.");

    if ($antigriefEnabled && in_array($id, $blockedItems)) {
      $this->removeBlockedItem($p, $item);
      $p->sendMessage($message);
      $e->setCancelled(true);
    }
  }

  public function onHeld(PlayerItemHeldEvent $e) {
    $p = $e->getPlayer();
    $item = $e->getItem();
    $id = $item->getId();
    $antigriefEnabled = $this->main->getConfig()->getNested("antigrief.enabled", true);
    $antigriefItemInHand = $this->main->getConfig()->getNested("antigrief.itemInHand", false);
    $blockedItems = $this->main->getConfig()->getNested("antigrief.blockedItems", []);
    $message = $this->main->getConfig()->getNested("antigrief.message", "§8[§bM4Shield§8] §cEste item está bloqueado");

    if ($antigriefEnabled && !$antigriefItemInHand && in_array($id, $blockedItems)) {
      $this->removeBlockedItem($p);
      $p->sendMessage($message);
      $e->setCancelled(true);
    }
  }

  public function onCmd(PlayerCommandPreprocessEvent $e) {
    $p = $e->getPlayer();
    $msg = $e->getMessage();
    $ccmd = explode(" ", $msg);
    $cmd = strtolower(substr(array_shift($ccmd), 1));

    if ($msg[0] !== "/") {
      return;
    }

    $commandBlockerEnabled = $this->main->getConfig()->getNested("commandblocker.enabled", true);
    $blockedCommands = $this->main->getConfig()->getNested("commandblocker.blockedCommands", []);
    $allowedPlayers = $this->main->getConfig()->getNested("commandblocker.allowedPlayers", []);

    if ($commandBlockerEnabled && in_array($cmd, $blockedCommands) && !in_array(strtolower($p->getName()), $allowedPlayers)) {
      $message = $this->main->getConfig()->getNested("commandblocker.message", "§8[§bM4Shield§8] §cEste comando está bloqueado");
      $p->sendMessage($message);
      $e->setCancelled(true);
    }

    $chatBlockerEnabled = $this->main->getConfig()->getNested("chatblocker.enabled", false);
    $blockInCommands = $this->main->getConfig()->getNested("chatblocker.blockInCommands", false);
    $ipLeakBlockEnabled = $this->main->getConfig()->getNested("ipleakblock.enabled", true);
    $replacementChar = $this->main->getConfig()->getNested("ipleakblock.replacementChar", "*");
    $ipBlockInCommands = $this->main->getConfig()->getNested("ipleakblock.blockInCommands", true);
    if ($blockInCommands && $chatBlockerEnabled) {
      $e->setMessage($this->processBlockedWords($msg));
    }
    if ($ipLeakBlockEnabled && $this->containsIpAddress($msg) && $ipBlockInCommands) {
      $e->setMessage($this->blockIpLeak($msg, $replacementChar, $p));
    }
  }

  public function onChat(PlayerChatEvent $e) {
    $p = $e->getPlayer();
    $message = $e->getMessage();

    $chatBlockerEnabled = $this->main->getConfig()->getNested("chatblocker.enabled", false);
    $ipLeakBlockEnabled = $this->main->getConfig()->getNested("ipleakblock.enabled", true);
    $replacementChar = $this->main->getConfig()->getNested("ipleakblock.replacementChar", "*");
    if ($chatBlockerEnabled) {
      $e->setMessage($this->processBlockedWords($message));
    }
    if ($ipLeakBlockEnabled) {
      $e->setMessage($this->processLeak($message, $replacementChar, $p));
    }
  }
}
