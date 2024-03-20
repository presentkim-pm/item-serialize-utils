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

use kim\present\serializer\nbt\NbtSerializer;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;

use function get_class;
use function json_decode;
use function json_encode;

final class ItemSerializeUtils{
	public static function serialize(Item $item, SerializeMode $mode = SerializeMode::BINARY) : string{
		$tag = $item->nbtSerialize();
		return self::encodeToUTF8(match ($mode) {
			SerializeMode::BINARY => NbtSerializer::toBinary($tag),
			SerializeMode::BASE64 => NbtSerializer::toBase64($tag),
			SerializeMode::HEX    => NbtSerializer::toHex($tag),
			SerializeMode::SNBT   => NbtSerializer::toSNBT($tag),
			SerializeMode::JSON   => self::jsonSerialize($tag)
		});
	}

	/**
	 * @param string        $contents
	 * @param SerializeMode $mode
	 *
	 * @return Item
	 */
	public static function deserialize(string $contents, SerializeMode $mode = SerializeMode::BINARY) : Item{
		$tag = match ($mode) {
			SerializeMode::BINARY => NbtSerializer::fromBinary($contents),
			SerializeMode::BASE64 => NbtSerializer::fromBase64($contents),
			SerializeMode::HEX    => NbtSerializer::fromHex($contents),
			SerializeMode::SNBT   => NbtSerializer::fromSNBT($contents),
			SerializeMode::JSON   => self::jsonDeserialize($contents)
		};
		if(!($tag instanceof CompoundTag)){
			throw new \InvalidArgumentException("Invalid tag type : " . get_class($tag));
		}

		return Item::nbtDeserialize($tag);
	}

	private static function jsonSerialize(CompoundTag $tag) : string{
		$json = [
			SavedItemStackData::TAG_COUNT => $tag->getByte(SavedItemStackData::TAG_COUNT),
			SavedItemData::TAG_NAME => $tag->getString(SavedItemData::TAG_NAME),
			SavedItemData::TAG_DAMAGE => $tag->getShort(SavedItemData::TAG_DAMAGE)
		];

		$block = $tag->getCompoundTag(SavedItemData::TAG_BLOCK);
		if($block !== null){
			$json[SavedItemData::TAG_BLOCK] = NbtSerializer::toSNBT($block);
		}

		$namedTag = $tag->getCompoundTag(SavedItemData::TAG_TAG);
		if($namedTag !== null){
			$json[SavedItemData::TAG_TAG] = NbtSerializer::toSNBT($namedTag);
		}
		return json_encode($json);
	}

	private static function jsonDeserialize(string $contents) : CompoundTag{
		$json = json_decode($contents, true);

		$tag = new CompoundTag();
		$tag->setByte(SavedItemStackData::TAG_COUNT, $json[SavedItemStackData::TAG_COUNT]);
		$tag->setString(SavedItemData::TAG_NAME, $json[SavedItemData::TAG_NAME]);
		$tag->setShort(SavedItemData::TAG_DAMAGE, $json[SavedItemData::TAG_DAMAGE]);

		if(isset($json[SavedItemData::TAG_BLOCK])){
			$tag->setTag(SavedItemData::TAG_BLOCK, NbtSerializer::fromSNBT($json[SavedItemData::TAG_BLOCK]));
		}
		if(isset($json[SavedItemData::TAG_TAG])){
			$tag->setTag(SavedItemData::TAG_TAG, NbtSerializer::fromSNBT($json[SavedItemData::TAG_TAG]));
		}

		return $tag;
	}

	/** Convert the contents to UTF-8 encoding */
	private static function encodeToUTF8(string $contents) : string{
		return mb_convert_encoding($contents, "UTF-8", mb_detect_encoding($contents));
	}
}
