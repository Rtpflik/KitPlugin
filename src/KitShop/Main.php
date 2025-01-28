<?php

namespace KitShop;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\item\ItemFactory;
use pocketmine\item\Item;
use pocketmine\inventory\Inventory;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;

class Main extends PluginBase {

    private Config $kits;
    private BedrockEconomyAPI $economy;

    public function onEnable(): void {
        // Save and load kits.yml
        $this->saveResource("kits.yml");
        $this->kits = new Config($this->getDataFolder() . "kits.yml", Config::YAML);

        // BedrockEconomy API
        $this->economy = BedrockEconomyAPI::getInstance();

        $this->getLogger()->info(TF::GREEN . "KitShop plugin enabled!");
    }

    public function onDisable(): void {
        $this->getLogger()->info(TF::RED . "KitShop plugin disabled!");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "kit") {
            if (!$sender instanceof Player) {
                $sender->sendMessage(TF::RED . "This command can only be used in-game.");
                return true;
            }

            $this->openKitMenu($sender);
            return true;
        }
        return false;
    }

    private function openKitMenu(Player $player): void {
        $kits = $this->kits->getAll();
        $inventorySize = ceil(count($kits) / 9) * 9;

        $packet = new InventoryContentPacket();
        $packet->windowId = 100;
        $packet->items = [];

        foreach ($kits as $kitName => $kitData) {
            $item = ItemFactory::getInstance()->get($kitData["item_id"]);
            $item->setCustomName(TF::AQUA . $kitName);
            $item->setLore([
                TF::GRAY . "Price: " . TF::GOLD . $kitData["price"],
                TF::GRAY . "Click to purchase!"
            ]);

            $packet->items[] = ItemStackWrapper::legacy($item->getNetworkItemStack());
        }

        $player->getNetworkSession()->sendDataPacket($packet);

        $player->sendMessage(TF::GREEN . "Kit menu opened!");
    }

    private function attemptPurchase(Player $player, string $kitName): void {
        $kits = $this->kits->getAll();
        if (!isset($kits[$kitName])) {
            $player->sendMessage(TF::RED . "Invalid kit selected.");
            return;
        }

        $kitData = $kits[$kitName];
        $price = $kitData["price"];

        $this->economy->getBalance($player->getName(), function (?int $balance) use ($player, $kitName, $kitData, $price): void {
            if ($balance === null || $balance < $price) {
                $player->sendMessage(TF::RED . "You do not have enough money to buy this kit!");
                return;
            }

            $this->economy->subtractFromBalance($player->getName(), $price, function (bool $success) use ($player, $kitName, $kitData): void {
                if ($success) {
                    $this->giveKit($player, $kitName, $kitData);
                    $player->sendMessage(TF::GREEN . "You successfully purchased the $kitName kit!");
                } else {
                    $player->sendMessage(TF::RED . "An error occurred while processing your purchase.");
                }
            });
        });
    }

    private function giveKit(Player $player, string $kitName, array $kitData): void {
        foreach ($kitData["items"] as $itemData) {
            $item = ItemFactory::getInstance()->get($itemData["id"], $itemData["meta"] ?? 0, $itemData["count"] ?? 1);
            $player->getInventory()->addItem($item);
        }
    }
}