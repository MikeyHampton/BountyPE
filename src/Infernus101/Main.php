<?php

/*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*/

namespace Infernus101;

use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerDeathEvent, PlayerJoinEvent};
use pocketmine\event\entity\{EntityDamageEvent, EntityRegainHealthEvent, EntityDamageByEntityEvent};
use pocketmine\plugin\PluginBase;
use pocketmine\{Player, Server};
use pocketmine\command\{Command, CommandSender};
use pocketmine\utils\{Config, TextFormat};
use onebone\economyapi\EconomyAPI;

class Main extends PluginBase implements Listener{
    public $db;
	public function onEnable(): void{
    $this->getLogger()->info("§b§lLoaded Bounty plugin succesfully.");
		$files = array("config.yml");
		foreach($files as $file){
			if(!file_exists($this->getDataFolder() . $file)) {
				@mkdir($this->getDataFolder());
				file_put_contents($this->getDataFolder() . $file, $this->getResource($file));
			}
		}
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		$this->db = new \SQLite3($this->getDataFolder() . "bounty.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS bounty (player TEXT PRIMARY KEY COLLATE NOCASE, money INT);");
	}
    	public function bountyExists($playe) {
		$result = $this->db->query("SELECT * FROM bounty WHERE player='$playe';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	    }
		public function getBountyMoney($play){
        $result = $this->db->query("SELECT * FROM bounty WHERE player = '$play';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return (int) $resultArr["money"];
        }
		public function onEntityDamage(EntityDamageEvent $event){
		$entity = $event->getEntity();
		if($entity instanceof Player){
			$player = $entity->getPlayer();
			if($this->cfg->get("bounty_stats") == 1 or $this->cfg->get("health_stats") == 1){
		    $this->renderNametag($player);
		    }
		  }
	    }
	    public function onEntityRegainHealth(EntityRegainHealthEvent $event){
		$entity = $event->getEntity();
		if($entity instanceof Player){
			$player = $entity->getPlayer();
			if($this->cfg->get("bounty_stats") == 1 or $this->cfg->get("health_stats") == 1){
		    $this->renderNametag($player);
		    }
		  }
	    }
		public function onJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer();
		 if($this->cfg->get("bounty_stats") == 1 or $this->cfg->get("health_stats") == 1){
		 $this->renderNametag($player);
		 }
	    }
		public function getBountyMoney2($play){
		  if(!$this->bountyExists($play)){
			  $i = 0;
			  return $i;
		  }
        $result = $this->db->query("SELECT * FROM bounty WHERE player = '$play';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return (int) $resultArr["money"];
        }
	    public function deleteBounty($pla){
		$this->db->query("DELETE FROM bounty WHERE player = '$pla';");
	    }
		public function addBounty($player, $mon){
		if($this->bountyExists($player)){
		   $stmt = $this->db->prepare("INSERT OR REPLACE INTO bounty (player, money) VALUES (:player, :money);");  	
           $stmt->bindValue(":player", $player);
		   $stmt->bindValue(":money", $this->getBountyMoney($player) + $mon);
		   $result = $stmt->execute();	   
		 }
		 if(!$this->bountyExists($player)){
		   $stmt = $this->db->prepare("INSERT OR REPLACE INTO bounty (player, money) VALUES (:player, :money);");  	
           $stmt->bindValue(":player", $player);
		   $stmt->bindValue(":money", $mon);
		   $result = $stmt->execute();	   
	     }
		}
		public function renderNameTag($player){
		$username = $player->getName();
		$lower = strtolower($username);
		$bounty = $this->getBountyMoney2($lower);
		if($this->cfg->get("bounty_stats") == 1 && $this->cfg->get("health_stats") != 1){
		$player->setNameTag("§a$username\n§eBounty: §6$bounty"."$");
		}
		if($this->cfg->get("health_stats") == 1 && $this->cfg->get("bounty_stats") != 1){
		$player->setNameTag("§a$username §c".$player->getHealth()."§f/§c".$player->getMaxHealth());
		}
		if($this->cfg->get("bounty_stats") == 1 && $this->cfg->get("health_stats") == 1){
		$player->setNameTag("§a$username §c".$player->getHealth()."§f/§c".$player->getMaxHealth()."\n§eBounty: §6$bounty"."$");
		}
	    }
		public function onDeath(PlayerDeathEvent $event) {
        $cause = $event->getEntity()->getLastDamageCause();
        if($cause instanceof EntityDamageByEntityEvent) {
            $player = $event->getEntity();
			$name = $player->getName();
			$lowr = strtolower($name);
            $killer = $event->getEntity()->getLastDamageCause()->getDamager();
			$name2 = $killer->getName();
			if($player instanceof Player){
				if($this->bountyExists($lowr)){
					$money = $this->getBountyMoney($lowr);
					$killer->sendMessage("§7[§5BOUNTY§7] §dYou get extra §6$money §dfrom bounty for killing §6$name"."§d!");
					EconomyAPI::getInstance()->addMoney($killer->getName(), $money);
					if($this->cfg->get("bounty_broadcast") == 1){
			          $this->getServer()->broadcastMessage("§7[§5BOUNTY§7] §6$name2 §djust got §6$money"."$ §dbounty for killing §6$name!");
		            }
				if($this->cfg->get("bounty_fine") == 1){
					$perc = $this->cfg->get("fine_percentage");
					$fine = ($money*$perc)/100;
					if(EconomyAPI::getInstance()->myMoney($player->getName()) > $fine){
					  	EconomyAPI::getInstance()->reduceMoney($player->getName(), $fine);
						$player->sendMessage("§7[§5BOUNTY§7] §dYour §6$fine"."$ §dwas taken as Bounty fine! Bounty Fine = §6$perc §dPercent of Bounty on you!");
					}
					if(EconomyAPI::getInstance()->myMoney($player->getName()) <= $fine){
					  	EconomyAPI::getInstance()->setMoney($player->getName(), 0);
						$player->sendMessage("§7[§5BOUNTY§7] §dYour §6$fine"."$ §dwas taken as Bounty fine! Bounty Fine = §6$perc §dPercent of Bounty on you!");
					}
				}
					$this->deleteBounty($lowr);
				}
		 }
    }
}
	    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool {
		////////////////////// BOUNTY //////////////////////
		 if(strtolower($cmd->getName()) == "bounty"){	
		   if(!isset($args[0])){
		        $sender->sendMessage("§7[§5BOUNTY§7] §aPlease use: §b/bounty §3<set | me | search | top | about>");
			    return false;
		   }	
		 switch(strtolower($args[0])){
		 case "set":
		   if(!(isset($args[1])) or !(isset($args[2]))){
			   $sender->sendMessage("§7[§5BOUNTY§7] §cWrong usage. §aPlease use: §b/bounty set §3<player> <money>");
			   return true;
			   break;
		   }
		   $invited = $args[1];
		   $lower = strtolower($invited);
		   $name = strtolower($sender->getName());
		   if($lower == $name){
			   $sender->sendMessage("§7[§5BOUNTY§7] §2You cannot place bounties on yourself!");
			   return true;
			   break;
		   }
		    $playerid = $this->getServer()->getPlayer($lower);
			$money = $args[2];
		   if(!$playerid instanceof Player) {
			   $sender->sendMessage("§7[§5BOUNTY§7] §2The player named §c$playerid §2not found!");
			   return true;
			   break;
		   }
		   if(!is_numeric($args[2])) {
			   $sender->sendMessage("§7[§5BOUNTY§7] §2Money has to be a number! §aPlease use: §b/bounty set §3$args[1] <money>");
			   return true;
			   break;
		   }
		   $min = $this->cfg->get("min_bounty");
		   if($money < $min){
			  $sender->sendMessage("§7[§5BOUNTY§7] §dMoney has to be more than §6$min"."$");
			  return true;
			  break;
		   }
		   if($fail = EconomyAPI::getInstance()->reduceMoney($sender, $money)) {
		   $player = $sender->getName();
		   $this->addBounty($lower, $money);
		   $sender->sendMessage("§7[§5BOUNTY§7] §dSuccessfully added §6$money"."$ §dbounty on §6$invited");
		   $playerid->sendMessage("§7[§5BOUNTY§7] §dA Bounty has been added on you for §6$money"."$ by §2$name\n§aCheck total bounty on you by typing: §b/bounty me");
		   if($this->cfg->get("bounty_broadcast") == 1){
			   $this->getServer()->broadcastMessage("§7[§5BOUNTY§7] §r§6$player §dJust added §6$money"."$ §dbounty on §6$invited!");
		   }
	           return true;
		   break;
		   }else {
						switch($fail){
							case EconomyAPI::RET_INVALID:
								$sender->sendMessage("§7[§5BOUNTY§7] §2You do not have enough money to set that bounty!");
								return false;
								break;
							case EconomyAPI::RET_CANCELLED:
								$sender->sendMessage("§7[§5BOUNTY§7] §2The transaction has been cancelled.!");
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage("§7[§5BOUNTY§7] §2You do not have an economy account.!"); //Will fix errors soon.
								break;
						}
					}
		   break;
		   case "me":
			   $lower = strtolower($sender->getName());
			   if(isset($args[1])){
				   $sender->sendMessage("§7[§5BOUNTY§7] §aPlease use: §b/bounty me");
				   return true;
				   break;
			   }
			   if(!$this->bountyExists($lower)){
				   $sender->sendMessage("§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=\n§aNo current bounties detected on you!\n§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=");
				   return true;
				   break;
			   }
			   if($this->bountyExists($lower)){
				   $bounty = $this->getBountyMoney($lower);
				   $sender->sendMessage("§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=\n§aBounties on you: §6$bounty"."$\n§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=");
				   return true;
				   break;
			   }
			   break;
		   
		   case "search":
			   if(!isset($args[1])){
				   $sender->sendMessage("§7[§5BOUNTY§7] §aPlease use: §v/bounty search §3<player>");
				   return true;
				   break;
			   }
			   $lower = strtolower($args[1]);
			   if(!$this->bountyExists($lower)){
				   $sender->sendMessage("§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=\n§aNo curent bounties on $args[1]".".\n§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=");
				   return true;
				   break;
			   }
			   if($this->bountyExists($lower)){
				   $bounty = $this->getBountyMoney($lower);
				   $sender->sendMessage("§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=\n§aBounties on $args[1]: §6$bounty"."$\n§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=");
				   return true;
				   break;
			   }
			       break;
		   case "top":
		       if(isset($args[1])){
				   $sender->sendMessage("§7[§5BOUNTY§7] §aPlease use: §b/bounty top");
				   return true;
			           break;
			   }
			          $sender->sendMessage("§a--------- §bTop 10 MOST WANTED LIST §a---------");
		              $result = $this->db->query("SELECT * FROM bounty ORDER BY money DESC LIMIT 10;"); 			
				      $i = 1; 
					  while($row = $result->fetchArray(SQLITE3_ASSOC)){
						    $play = $row["player"];
							$money = $row["money"];
							$sender->sendMessage("§f§l$i. §r§a$play §f--> §6$money"."$");
						    $i++; 
			              }
		        return true; 
		    break;
		   case "about":
		    $sender->sendMessage("§7[§5BOUNTY§7] §5Bounty v2.0.1 by §aVMPE Development Team §eThis plugin was bought to you by §6Void§bFactions§cPE! §aOur server IP: §cplay.voidfactionspe.ml Port - 25655");
		   return true; 
		   break;   
		   default:
		    $sender->sendMessage("§7[§5BOUNTY§7] §aPlease use: §b/bounty §3<set | me | search | top | about>");
		    return true;
		    break;
			 }
	}
  }
}
