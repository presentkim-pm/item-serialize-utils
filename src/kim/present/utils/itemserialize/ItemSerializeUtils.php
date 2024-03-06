<?php

/**
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\utils\itemserialize;

use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;

use function mb_convert_encoding;
use function mb_detect_encoding;

final class ItemSerializeUtils{
	public static function serialize(Item $item) : string{
		$buffer = (new BigEndianNbtSerializer())->write(new TreeRoot($item->nbtSerialize()));
		return mb_convert_encoding($buffer, "UTF-8", mb_detect_encoding($buffer));
	}

	public static function deserialize(string $contents) : Item{
		return Item::nbtDeserialize((new BigEndianNbtSerializer())->read($contents)->mustGetCompoundTag());
	}
}
