<?php

declare(strict_types=1);

namespace KitShop;

use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use cooldogedev\BedrockEconomy\libs\cooldogedev\libSQL\context\ClosureContext;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;

class Main extends PluginBase implements Listener {

    // Kit prices
    private array $kits = [
        "Starter Kit" => 100,
        "Gentleman’s Kit" => 500,
        "Knight’s Kit" => 1000,
        "Paladin Kit" => 2000,
        "Warlord Kit" => 4000,
        "Warlock Kit" => 8000
    ];

    public function onEnable(): void {
        $this->getLogger()->info(TF::GREEN . "KitShop plugin enabled!");
    }

    public function onDisable(): void {
        $this->getLogger()->info(TF::RED . "KitShop plugin disabled!");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "kitshop") {
            if ($sender instanceof Player) {
                $this->openKitShop($sender);
            } else {
                $sender->sendMessage(TF::RED . "This command can only be used in-game.");
            }
            return true;
        }
        return false;
    }

    private function openKitShop(Player $player): void {
        $player->sendMessage(TF::YELLOW . "Available Kits:");
        foreach ($this->kits as $kitName => $price) {
            $player->sendMessage(TF::AQUA . "- " . $kitName . ": " . TF::GOLD . "$" . $price);
        }
        $player->sendMessage(TF::GREEN . "Use /buykit <kitname> to purchase a kit.");
    }

    public function buyKit(Player $player, string $kitName): void {
        if (!isset($this->kits[$kitName])) {
            $player->sendMessage(TF::RED . "Kit not found!");
            return;
        }

        $cost = $this->kits[$kitName];
        BedrockEconomyAPI::getInstance()->getPlayerBalance($player->getName(), ClosureContext::create(
            function (?int $balance) use ($player, $kitName, $cost): void {
                if ($balance === null) {
                    $player->sendMessage(TF::RED . "Unable to retrieve your balance.");
                    return;
                }

                if ($balance >= $cost) {
                    BedrockEconomyAPI::getInstance()->subtractFromPlayerBalance($player->getName(), $cost, ClosureContext::create(
                        function (bool $success) use ($player, $kitName): void {
                            if ($success) {
                                $player->sendMessage(TF::GREEN . "You have successfully purchased the " . $kitName . "!");
                                // TODO: Give the player the kit here
                            } else {
                                $player->sendMessage(TF::RED . "An error occurred while processing your purchase.");
                            }
                        }
                    ));
                } else {
                    $player->sendMessage(TF::RED . "You do not have enough money to buy the " . $kitName . ".");
                }
            }
        ));
    }

    public function onCommandWithArgs(CommandSender $sender, string $label, array $args): bool {
        if (strtolower($label) === "buykit" && $sender instanceof Player) {
            if (count($args) === 0) {
                $sender->sendMessage(TF::RED . "Usage: /buykit <kitname>");
                return true;
            }
            $kitName = implode(" ", $args);
            $this->buyKit($sender, $kitName);
            return true;
        }
        return false;
    }
}