<?php
declare(strict_types = 1);

namespace skymin\log;

use pocketmine\plugin\PluginBase;

use pocketmine\player\Player;
use pocketmine\block\Block;
use pocketmine\block\BaseSign;
use pocketmine\block\tile\Sign;
use pocketmine\item\BlazeRod;

use pocketmine\event\Listener;
use pocketmine\event\block\{BlockBreakEvent, BlockPlaceEvent, SignChangeEvent};
use pocketmine\event\player\{PlayerChatEvent, PlayerCommandPreprocessEvent, PlayerInteractEvent};

use skymin\json\Data;

use function mkdir;
use function date_default_timezone_set;
use function date; 
use function is_null;
use function implode;
use function array_map;
use function array_keys;
use function strtolower;
use function file_exists;
use function file_get_contents;
use function json_decode;

class LogManager extends PluginBase implements Listener{
	
	/* Block log Tyoes */
	public const BREAK = 'break';
	public const PLACE = 'place';
	public const SIGN = 'sign';
	
	public array $commandLog;
	
	public array $chatLog;
	
	public array $blockLog = [];
	
	private string $date;
	
	public function onEnable() :void{
		date_default_timezone_set('Asia/Seoul');
		$this->date = date('Y-m-d');
		$DataFolder = $this->getDataFolder();
		@mkdir($DataFolder . 'block/');
		@mkdir($DataFolder . 'command/');
		$this->commandLog = Data::call($DataFolder . 'command/' . $this->date);
		@mkdir($DataFolder . 'chat/');
		$this->chatLog = Data::call($DataFolder . 'chat/' . $this->date);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	
	public function onDisable() :void{
		$DataFolder = $this->getDataFolder();
		Data::save($DataFolder . 'command/' . $this->date, $this->commandLog);
		Data::save($DataFolder . 'chat/' . $this->date, $this->chatLog);
		if(is_null($this->blockLog)) return;
		foreach($this->blockLog as $xyz => $value){
			Data::save($DataFolder . 'block/' . $xyz, $value);
		}
	}
	
	public function setBlockLog(Player $player, Block $block, string $type, string $extraData = '') :void{
		$pos = $block->getPosition();
		$world = $pos->getWorld();
		$posString = $pos->getFloorX() . '^' . $pos->getFloorY() . '^' . $pos->getFloorZ() . '^' . $world->getFolderName();
		if(!isset($this->blockLog[$posString])){
			$this->blockLog[$posString] = [];
		}
		if($block instanceof BaseSign){
			$tile = $world->getTile($pos);
			if($tile instanceof Sign){
				$extraData = implode(' ', array_map(function(int $index, string $line) :string{
					return "[{$index}] " . $line;
				}, array_keys($tile->getText()), $tile->getText()));
			}
		}
		$this->blockLog[$posString] = [
			'date' => date('Y-m-d H:i:s'),
			'player' => strtolower($player->getName()),
			'extraData' => $extraData,
			'type' => $type
		];
	}
	
	public function setChatLog(Player $player, string $msg) :void{
		$this->chatLog[] = [
			'player' => strtolower($player->getName()),
			'message' => $msg,
			'date' => date('Y-m-d H:i:s')
		];
	}
	
	public function setCmdLog(Player $player, string $cmd) :void{
		$this->commandLog[] = [
			'player' => strtolower($player->getName()),
			'message' => $cmd,
			'date' => date('Y-m-d H:i:s')
		];
	}
	
	public function onBlockPlace(BlockPlaceEvent $event) :void{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if($event->isCancelled()) return;
		$this->setBlockLog($player, $block, self::PLACE);
	}
	
	public function onBlockBreak(BlockBreakEvent $event) : void{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if($event->isCancelled()) return;
		$this->setBlockLog($player, $block, self::BREAK);
	}
	
	public function onSignChange(SignChangeEvent $event) :void{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if($event->isCancelled()) return;
		$this->setBlockLog($player, $block, self::SIGN);
	}
	
	public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event) :void{
		$player = $event->getPlayer();
		$command = $event->getMessage();
		if((substr($command, 0, 1) === "/") or (substr($command, 0, 2) === "./")){
			if(!$event->isCancelled()){
				$this->setCmdLog($player, $command);
				$this->getServer()->getLogger()->notice($player->getName() . ": " . $command);
			}
		}
	}
	
	public function onPlayerChat(PlayerChatEvent $event) : void{
		$player = $event->getPlayer();
		$message = $event->getMessage();
		if($event->isCancelled()) return;
		$this->setChatLog($player, $message);
	}
	
	public function onTouch(PlayerInteractEvent $ev) :void{
		$player = $ev->getPlayer();
		$block = $ev->getBlock();
		$pos = $block->getPosition();
		if($this->getServer()->isOp($player->getName())){
			if($ev->getItem() instanceof BlazeRod){
				//Todo
			}
		}
	}
	
}