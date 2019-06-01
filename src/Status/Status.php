<?php

namespace Status;

use Core\Core;
use Core\util\Util;
use GuiLibrary\GuiLibrary;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ChestInventory;
use pocketmine\inventory\DoubleChestInventory;
use pocketmine\inventory\transaction\action\CreativeInventoryAction;
use pocketmine\inventory\transaction\action\DropItemAction;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use TutorialManager\TutorialManager;
use UiLibrary\UiLibrary;

class Status extends PluginBase implements Listener {
    private static $instance = null;
    //public $pre = "§l§e[ §f스탯 §e]§r§e ";
    public $pre = "§e• ";
    public $util;
    private $mode = [];

    public static function getInstance() {
        return self::$instance;
    }

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {
        @mkdir($this->getDataFolder());
        $this->stat = new Config ($this->getDataFolder() . "Stat.yml", Config::YAML);
        $this->data = $this->stat->getAll();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->ui = UiLibrary::getInstance();
        $this->core = Core::getInstance();
        $this->util = new Util ($this->core);
        $this->gui = GuiLibrary::getInstance();
        $this->tutorial = TutorialManager::getInstance();
    }

    public function setStatPoint($name, $amount) {
        if (!isset ($this->data [$name] ["스탯포인트"]))
            return;
        $this->data [$name] ["스탯포인트"] = $amount;
    }

    public function StatUI(Player $player) {
        if (!isset($this->setting[$player->getName()]))
            $text = "§l§c▶ §r§f스탯을 관리하는 창입니다.\n  스탯을 선택하면 해당 스탯포인트가 1 만큼 상승합니다.\n§l§c▶ §r§f스탯 관리후, 아래의 결정 버튼을 선택하여야\n  결정 사항이 저장됩니다.\n§l§c▶ §r§f종료를 원할시, 아래의 취소 버튼을 선택해주세요.\n";
        else
            $text = "";
        $this->SettingData($player);
        //$this->getServer()->broadcastMessage("{$this->getStat($player->getName(), "민첩")}, {$this->setting[$player->getName()]["민첩"]}");
        $arr = ["힘", "민첩", "지능", "체력", "운"];
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            $arr = ["힘", "민첩", "지능", "체력", "운"];
            if (!isset($data[0])) {
                $this->check($player, "취소");
                return false;
            }
            if ($data[0] == 5) {
                $this->check($player, "결정");
                return false;
            }
            if ($data[0] == 6) {
                $this->check($player, "취소");
                return false;
            }
            if ($this->setting[$player->getName()]["스탯포인트"] <= 0) {
                $this->StatUI($player);
                return false;
            }
            if (0 <= $data[0] && $data[0] < 5) {
                $this->setting[$player->getName()][$arr[$data[0]]]++;
                $this->setting[$player->getName()]["스탯포인트"]--;
                $this->setting[$player->getName()]["사용스탯포인트"]++;
                $this->StatUI($player);
            }
        });
        $form->setTitle("Tele Stat");
        $text .= "§l§c▶ §r§f현재 스탯포인트 : {$this->setting[$player->getName()]["스탯포인트"]}";
        if ($this->setting[$player->getName()]["스탯포인트"] <= 0)
            $text .= "\n§l§c▶ §r§f스탯포인트가 부족합니다.";
        $form->setContent($text);
        foreach ($arr as $key => $value) {
            if ($this->setting[$player->getName()][$value] <= 0)
                $int[$value] = $this->getStat($player->getName(), $value);
            else
                $int[$value] = $this->getStat($player->getName(), $value) + $this->setting[$player->getName()][$value];
        }
        $form->addButton("§l§c▶ §8힘\n§r§8현재 스탯 : " . $int["힘"]);
        $form->addButton("§l§c▶ §8민첩\n§r§8현재 스탯 : " . $int["민첩"]);
        $form->addButton("§l§c▶ §8지능\n§r§8현재 스탯 : " . $int["지능"]);
        $form->addButton("§l§c▶ §8체력\n§r§8현재 스탯 : " . $int["체력"]);
        $form->addButton("§l§c▶ §8운\n§r§8현재 스탯 : " . $int["운"]);
        $form->addButton("§l결정\n§r§8스탯 관리사항을 결정, 종료합니다.");
        $form->addButton("§l취소\n§r§8스탯 관리를 중단합니다.");
        $form->sendToPlayer($player);
    }

    private function SettingData(Player $player) {
        if (!isset($this->setting[$player->getName()])) {
            $this->setting[$player->getName()]["힘"] = 0;
            $this->setting[$player->getName()]["민첩"] = 0;
            $this->setting[$player->getName()]["지능"] = 0;
            $this->setting[$player->getName()]["체력"] = 0;
            $this->setting[$player->getName()]["운"] = 0;
            $this->setting[$player->getName()]["스탯포인트"] = $this->getStatPoint($player->getName());
            $this->setting[$player->getName()]["사용스탯포인트"] = 0;
        }
    }

    public function getStatPoint($name) {
        if (!isset ($this->data [$name] ["스탯포인트"]))
            return;
        return $this->data [$name] ["스탯포인트"];
    }

    private function check(Player $player, string $type) {
        if ($type == "결정") {
            $list = "";
            foreach ($this->setting[$player->getName()] as $key => $value) {
                if ($key !== "스탯포인트")
                    $list .= "  - {$key} => +{$value}\n";
            }
            $form = $this->ui->ModalForm(function (Player $player, array $data) {
                if ($data[0] == true) {
                    foreach ($this->setting[$player->getName()] as $key => $value) {
                        if ($key == "스탯포인트")
                            continue;
                        elseif ($key !== "사용스탯포인트") {
                            $this->add($player->getName(), $key, $value);
                        } else {
                            $this->reducePoint($player->getName(), $value);
                        }
                    }
                    unset($this->setting[$player->getName()]);
                    $player->sendMessage("{$this->pre}스탯이 결정되었습니다.");
                    $this->tutorial->check($player, 5);
                } else {
                    $this->StatUI($player);
                }
            });
            $form->setTitle("Tele Stat");
            $form->setContent("\n§l§c▶ §r§f스탯 관리사항을 결정하시겠습니까?\n  결정하면 되돌릴 수 없습니다.\n§l§c▶ §r§f수정사항:\n{$list}");
            $form->setButton1("§l§8[예]");
            $form->setButton2("§l§8[아니오]");
            $form->sendToPlayer($player);
        } elseif ($type == "취소") {
            $form = $this->ui->ModalForm(function (Player $player, array $data) {
                if ($data[0] == true) {
                    unset($this->setting[$player->getName()]);
                    $player->sendMessage("{$this->pre}스탯 관리가 중단되었습니다.");
                } else {
                    $this->StatUI($player);
                }
            });
            $form->setTitle("Tele Stat");
            $form->setContent("\n§l§c▶ §r§f스탯 관리사항을 취소하시겠습니까?\n  취소하면 저장되지 않습니다.");
            $form->setButton1("§l§8[예]");
            $form->setButton2("§l§8[아니오]");
            $form->sendToPlayer($player);
        }
    }

    public function add($name, $StatName, $amout) {
        if (!isset ($this->data [$name]))
            return;
        $player = $this->getServer()->getPlayer($name);
        switch ($StatName) {
            case "힘" :
                if ($this->util->getJob($name) == "모험가") {
                    $this->util->addATK($name, 1 * $amout, "Stat");
                }
                if ($this->util->getJob($name) == "나이트") {
                    $this->util->addATK($name, 1 * $amout, "Stat");
                } elseif ($this->util->getJob($name) == "아처") {
                    $this->util->addATK($name, 0.5 * $amout, "Stat");
                    $this->util->addMaxHp($name, 2 * $amout, "Stat");
                } elseif ($this->util->getJob($name) == "위자드" or $this->util->getJob($name) == "프리스트") {
                    $this->util->addATK($name, 0.5 * $amout, "Stat");
                    $this->util->addMaxHp($name, 2 * $amout, "Stat");
                }
                $st = $this->data [$name] ["힘"];
                $sta = $st + $amout;
                if (($int = floor($sta / 100) - floor($st / 100)) > 0) {
                    $this->util->addATK($name, 50 * $int, "Stat");
                    $this->save();
                    $player->sendMessage($this->pre . "힘스탯이 100단위를 " . $int . "번 넘어 공격력 " . 50 * $int . "이올라갑니다.");
                }
                $this->data [$name] ["힘"] += $amout;
                $this->save();
                break;
            case "민첩" :
                if ($this->util->getJob($name) == "모험가") {
                    $this->util->addATK($name, 0.6 * $amout, "Stat");
                }
                if ($this->util->getJob($name) == "나이트") {
                    $this->util->addATK($name, 1 * $amout, "Stat");
                } elseif ($this->util->getJob($name) == "아처") {
                    $this->util->addATK($name, 1 * $amout, "Stat");
                } elseif ($this->util->getJob($name) == "위자드" or $this->util->getJob($name) == "프리스트") {
                    $this->util->addATK($name, 0.5 * $amout, "Stat");
                }
                $st = $this->data [$name] ["민첩"];
                $sta = $st + $amout;
                if (($int = floor($sta / 100) - floor($st / 100)) > 0) {
                    $this->save();
                    $player->sendMessage($this->pre . "민첩스탯이 100단위를 " . $int . "번 넘어 추가타 " . 1 * $int . "이올라갑니다.");
                }
                $this->data [$name] ["민첩"] += $amout;
                $this->save();
                break;
            case "지능" :
                if ($this->util->getJob($name) == "모험가") {
                    $this->util->addMATK($name, 0.1 * $amout, "Stat");
                    $this->util->addMaxMp($name, 1 * $amout, "Stat");
                }
                if ($this->util->getJob($name) == "나이트") {
                    $this->util->addMATK($name, 0.5 * $amout, "Stat");
                    $this->util->addMaxMp($name, 1 * $amout, "Stat");
                } elseif ($this->util->getJob($name) == "아처") {
                    $this->util->addMATK($name, 0.5 * $amout, "Stat");
                    $this->util->addMaxMp($name, 1 * $amout, "Stat");
                } elseif ($this->util->getJob($name) == "위자드" or $this->util->getJob($name) == "프리스트") {
                    $this->util->addMATK($name, 1.5 * $amout, "Stat");
                    $this->util->addMaxMp($name, 1 * $amout, "Stat");
                }
                $st = $this->data [$name] ["지능"];
                $sta = $st + $amout;
                if (($int = floor($sta / 100) - floor($st / 100)) > 0) {
                    $this->util->addMATK($name, $this->util->getMATK($name) * 0.2 * $int, "Stat");
                    $this->save();
                    $player->sendMessage($this->pre . "지능스탯이 100단위를 " . $int . "번 넘어 마법공격력 " . 20 * $int . "%가 올라갑니다.");
                }
                $this->data [$name] ["지능"] += $amout;
                $this->save();
                break;
            case "체력" :
                if ($this->util->getJob($name) == "모험가") {
                    $this->util->addMaxHp($name, 2 * $amout, "Stat");
                    $this->util->addDEF($name, 3 * $amout, "Stat");
                    $this->util->addMDEF($name, 3 * $amout, "Stat");
                }
                if ($this->util->getJob($name) == "나이트") {
                    $this->util->addDEF($name, 0.5 * $amout, "Stat");
                    $this->util->addMDEF($name, 0.5 * $amout, "Stat");
                    $this->util->addMaxHp($name, 5 * $amout, "Stat");
                } elseif ($this->util->getJob($name) == "아처") {
                    $this->util->addDEF($name, 0.2 * $amout, "Stat");
                    $this->util->addMDEF($name, 0.2 * $amout, "Stat");
                    $this->util->addMaxHp($name, 3, "Stat");
                } elseif ($this->util->getJob($name) == "위자드" or $this->util->getJob($name) == "프리스트") {
                    $this->util->addDEF($name, 0.2 * $amout, "Stat");
                    $this->util->addMDEF($name, 0.2 * $amout, "Stat");
                    $this->util->addMaxHp($name, 3 * $amout, "Stat");
                }
                $st = $this->data [$name] ["체력"];
                $sta = $st + $amout;
                if (($int = floor($sta / 100) - floor($st / 100)) > 0) {
                    $this->util->addMDEF($name, 10 * $int, "Stat");
                    $this->util->addMaxHp($name, 500 * $int, "Stat");
                    $this->util->addDEF($name, 10 * $int, "Stat");
                    $this->save();
                    $player->sendMessage($this->pre . "체력스탯이 100단위를 " . $int . "번 넘어 체력 " . 500 * $int . ", 방어력과 마법방어력 " . 10 * $int . "이 올라갑니다.");
                }
                $this->data [$name] ["체력"] += $amout;
                $this->save();
                break;
            case "운" :
                $st = $this->data [$name] ["운"];
                $sta = $st + $amout;
                $this->util->addCritical($name, 0.1 * $amout, "Stat");
                $this->util->addEvasion($name, 0.075 * $amout, "Stat");
                if (($int = floor($sta / 100) - floor($st / 100)) > 0) {
                    $this->util->addCD($name, 30 * $int, "Stat");
                    $this->save();
                    $player->sendMessage($this->pre . "운스탯이 100단위를 " . $int . "번 넘어 크리티컬 데미지가 " . 30 * $int . "만큼 올라갑니다.");
                }
                $this->data [$name] ["운"] += $amout;
                $this->save();
                break;
        }
    }

    public function reducePoint($name, $amount) {
        if ($this->data [$name] ["스탯포인트"] <= $amount)
            $this->data [$name] ["스탯포인트"] = 0;
        else
            $this->data [$name] ["스탯포인트"] -= $amount;
        $this->save();
    }

    public function save() {
        $this->stat->setAll($this->data);
        $this->stat->save();
    }

    public function getStat($name, $StatName) {
        if (!isset ($this->data [$name]))
            return;
        switch ($StatName) {
            case "힘" :
                $i = $this->data [$name] ["힘"];
                break;
            case "민첩" :
                $i = $this->data [$name] ["민첩"];
                break;
            case "지능" :
                $i = $this->data [$name] ["지능"];
                break;
            case "체력" :
                $i = $this->data [$name] ["체력"];
                break;
            case "운" :
                $i = $this->data [$name] ["운"];
                break;
        }
        return $i;
    }

    public function onJoin(PlayerJoinEvent $ev) {
        $p = $ev->getPlayer();
        $n = $p->getName();
        if (!isset ($this->data [$n])) {
            $this->data [$n] = [];
            $this->data [$n] ["스탯포인트"] = 5;
            $this->data [$n] ["힘"] = 0;
            $this->data [$n] ["민첩"] = 0;
            $this->data [$n] ["체력"] = 0;
            $this->data [$n] ["지능"] = 0;
            $this->data [$n] ["운"] = 0;
            $this->save();
        }
    }

    public function onDisable() {
        unset ($this->mode);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() == "스탯관리") {
            if (!$sender->isOp()) {
                $sender->sendMessage("{$this->pre} 권한이 없습니다.");
                return false;
            }
            if (!isset ($args [0])) {
                $sender->sendMessage("{$this->pre} /스탯관리 <추가|뺏기|초기화>");
                return false;
            }
            $name = $sender->getName();
            switch ($args [0]) {
                case "추가" :
                    if (!$sender->isOp()) {
                        $sender->sendMessage("{$this->pre} 권한이 없습니다.");
                        return false;
                    }
                    if (!isset ($this->data [$args [1]])) {
                        $sender->sendMessage("{$this->pre} 그 사람의 데이터가 없습니다.");
                        return false;
                    }
                    if (!isset ($args [2])) {
                        $sender->sendMessage("{$this->pre} 수량을 입력해 주세요.");
                        return false;
                    }
                    if (!isset ($args [1])) {
                        $sender->sendMessage("{$this->pre} 이름을 입력해 주세요.");
                        return false;
                    }
                    if (!is_numeric($args [2])) {
                        $sender->sendMessage("{$this->pre} 숫자로 입력해주세요.");
                        return false;
                    }
                    $this->addPoint($args [1], $args [2]);
                    $this->save();
                    $sender->sendMessage("{$this->pre} 성공적으로 {$args[1]}님 에게 {$args[2]} 만큼 스탯포인트를 주었습니다.");
                    break;
                case "뺏기" :
                    if (!$sender->isOp()) {
                        $sender->sendMessage("{$this->pre} 권한이 없습니다.");
                        return false;
                    }
                    if (!isset ($this->data [$args [1]])) {
                        $sender->sendMessage("{$this->pre} 그 사람의 데이터가 없습니다.");
                        return false;
                    }
                    if (!isset ($args [2])) {
                        $sender->sendMessage("{$this->pre} 수량을 입력해 주세요.");
                        return false;
                    }
                    if (!isset ($args [1])) {
                        $sender->sendMessage("{$this->pre} 이름을 입력해 주세요.");
                        return false;
                    }
                    if (!is_numeric($args [2])) {
                        $sender->sendMessage("{$this->pre} 숫자로 입력해주세요.");
                        return false;
                    }
                    $this->addPoint($args [1], $args [2] * -1);
                    $this->save();
                    $sender->sendMessage("{$this->pre} 성공적으로 {$args[1]}님 에게 {$args[2]} 만큼 스탯포인트를 뺏었습니다.");
                    break;
            }
            return true;
        }
    }

    public function addPoint($name, $amount) {
        $this->data [$name] ["스탯포인트"] += $amount;
        $this->save();
    }

    public function onQuit(PlayerQuitEvent $ev) {
        if (isset ($this->mode [$ev->getPlayer()->getName()])) {
            unset ($this->mode [$ev->getPlayer()->getName()]);
        }
    }
}
